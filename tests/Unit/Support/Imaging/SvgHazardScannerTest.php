<?php

declare(strict_types=1);

use App\Support\Imaging\SvgHazardScanner;

/**
 * Covers the at-rest SVG scan behind UAMS-Web/uams-statamic-archive#1739. Statamic sanitizes SVG on upload,
 * but `public/assets` is a submodule where a file can be committed directly,
 * bypassing the sanitizer entirely — this scanner is what covers that path.
 *
 * These cases run everywhere. The companion scan over the real stored corpus
 * lives in tests/Feature/Assets/StoredSvgHazardScanTest.php and needs the
 * `public/assets` submodule, which CI deliberately does not check out.
 *
 * @command  php artisan test --compact tests/Unit/Support/Imaging/SvgHazardScannerTest.php
 */

/**
 * Wrap SVG body markup in a minimal well-formed root element.
 *
 * @param  string  $body  Markup to place inside the <svg> root
 * @return string Complete SVG document markup
 */
function svgHazardScannerTestDocument(
    string $body
): string {
    return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" '
        .'width="100" height="100" viewBox="0 0 100 100">'.$body.'</svg>';
}

it('passes a clean svg', function (): void {
    $markup = svgHazardScannerTestDocument('<rect width="100" height="100" fill="#1f4e79"/>');

    expect((new SvgHazardScanner)->scan($markup))->toBe([])
        ->and((new SvgHazardScanner)->isClean($markup))->toBeTrue();
});

it('flags hazardous elements', function (string $body, string $expected): void {
    $findings = (new SvgHazardScanner)->scan(svgHazardScannerTestDocument($body));

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['kind'])->toBe('element')
        ->and($findings[0]['detail'])->toContain($expected);
})->with([
    'script' => ['<script>alert(1)</script>', 'script'],
    'foreignObject' => ['<foreignObject><body xmlns="http://www.w3.org/1999/xhtml"/></foreignObject>', 'foreignobject'],
    'iframe' => ['<iframe src="https://evil.test"/>', 'iframe'],
    'embed' => ['<embed src="https://evil.test"/>', 'embed'],
    'object' => ['<object data="https://evil.test"/>', 'object'],
]);

it('flags an event-handler attribute', function (): void {
    $findings = (new SvgHazardScanner)->scan(
        svgHazardScannerTestDocument('<rect width="10" height="10" onload="alert(1)"/>')
    );

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['kind'])->toBe('event-handler')
        ->and($findings[0]['detail'])->toContain('onload');
});

it('flags any on-prefixed handler, not just a fixed list', function (string $attribute): void {
    $findings = (new SvgHazardScanner)->scan(
        svgHazardScannerTestDocument(sprintf('<rect width="10" height="10" %s="alert(1)"/>', $attribute))
    );

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['kind'])->toBe('event-handler');
})->with(['onclick', 'onmouseover', 'onerror', 'onfocusin', 'onbegin', 'onanimationstart']);

it('flags a javascript url on a link', function (): void {
    $findings = (new SvgHazardScanner)->scan(
        svgHazardScannerTestDocument('<a xlink:href="javascript:alert(1)"><rect width="10" height="10"/></a>')
    );

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['kind'])->toBe('script-url');
});

it('flags a javascript url despite leading whitespace or mixed case', function (string $value): void {
    $findings = (new SvgHazardScanner)->scan(
        svgHazardScannerTestDocument(sprintf('<a href="%s"><rect width="10" height="10"/></a>', $value))
    );

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['kind'])->toBe('script-url');
})->with([
    'leading space' => ' javascript:alert(1)',
    'mixed case' => 'JaVaScRiPt:alert(1)',
    'space before colon' => 'javascript :alert(1)',
    // Browsers ignore control characters inside a URL scheme, so a check
    // anchored on the literal string alone is trivially bypassed.
    'tab inside the scheme' => "java\tscript:alert(1)",
    'newline inside the scheme' => "java\nscript:alert(1)",
    'carriage return inside the scheme' => "java\rscript:alert(1)",
]);

it('still fails a file whose scheme is split by a null byte', function (): void {
    // ---
    // A raw NUL cannot appear in well-formed XML (nor as `&#0;`), so this never
    // reaches the URL check — the parse fails first. The scan still fails, which
    // is the property that matters; asserting it pins the behavior so a future
    // "be lenient about parse errors" change cannot open the hole.
    // ---
    $findings = (new SvgHazardScanner)->scan(
        svgHazardScannerTestDocument("<a href=\"java\x00script:alert(1)\"><rect width=\"10\" height=\"10\"/></a>")
    );

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['kind'])->toBe('unparseable');
});

it('does not flag a benign url that merely contains the word javascript', function (string $value): void {
    $markup = svgHazardScannerTestDocument(
        sprintf('<a href="%s"><rect width="10" height="10"/></a>', $value)
    );

    expect((new SvgHazardScanner)->scan($markup))->toBe([]);
})->with([
    'path segment' => 'https://example.test/javascript:guide',
    'query value' => 'https://example.test/?to=javascript:alert(1)',
    'fragment' => 'https://example.test/page#javascript:alert(1)',
]);

it('sees through a namespace prefix on a hazardous element', function (): void {
    // A prefixed <svg:script> is still a script element to the browser.
    $markup = '<svg:svg xmlns:svg="http://www.w3.org/2000/svg" width="10" height="10">'
        .'<svg:script>alert(1)</svg:script></svg:svg>';

    $findings = (new SvgHazardScanner)->scan($markup);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['kind'])->toBe('element');
});

it('does not flag text content that merely looks like a handler', function (): void {
    // The regex-based version of this scan flagged this; the DOM-based one must not.
    $markup = svgHazardScannerTestDocument('<text x="0" y="10">onclick="alert(1)" javascript:void</text>');

    expect((new SvgHazardScanner)->scan($markup))->toBe([]);
});

it('does not flag a legitimate attribute that starts with the letters on', function (): void {
    // `opacity` and friends must not trip the `on` prefix match.
    $markup = svgHazardScannerTestDocument('<rect width="10" height="10" opacity="0.5" origin="default"/>');

    expect((new SvgHazardScanner)->scan($markup))->toBe([]);
});

it('reports unparseable markup rather than passing it', function (string $markup, string $detail): void {
    $findings = (new SvgHazardScanner)->scan($markup);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['kind'])->toBe('unparseable')
        ->and($findings[0]['detail'])->toContain($detail);
})->with([
    'malformed' => ['<svg><rect></svg>', 'well-formed'],
    'empty' => ['', 'empty'],
    'whitespace only' => ["  \n\t ", 'empty'],
]);

it('flags a doctype that declares internal entities', function (): void {
    // ---
    // The bypass this closes: libxml parses an internal-subset entity into a
    // DOMEntityReference, and its XPath engine does not descend into one — so
    // the `//*` sweep is blind to whatever the entity carries, while a browser
    // expands it and runs it. Verified executing in headless Chromium before
    // this guard existed.
    //
    // Expanding entities instead (LIBXML_NOENT) would trade this for local file
    // disclosure via a SYSTEM entity, so the scanner fails closed on the
    // declaration rather than resolving it.
    // ---
    $markup = '<!DOCTYPE svg [<!ENTITY xss "&#60;script&#62;alert(1)&#60;/script&#62;">]>'
        .'<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10">&xss;</svg>';

    $findings = (new SvgHazardScanner)->scan($markup);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['kind'])->toBe('entity-declaration')
        ->and((new SvgHazardScanner)->isClean($markup))->toBeFalse();
});

it('passes a doctype that declares no entities', function (): void {
    // The standard SVG 1.1 doctype must not trip the entity guard.
    $markup = '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" '
        .'"http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">'
        .'<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10">'
        .'<rect width="10" height="10"/></svg>';

    expect((new SvgHazardScanner)->scan($markup))->toBe([]);
});

it('flags smil animation elements', function (string $element): void {
    // ---
    // These rewrite another element's attribute at run time, so they defeat any
    // check that reads attribute values as static text. Verified: an
    // `<animate attributeName="xlink:href" values="#x;javascript:...">` on an
    // <a> executed in headless Chromium while scanning clean, because the
    // `javascript:` scheme is not at the start of the value.
    // ---
    $markup = svgHazardScannerTestDocument(
        sprintf(
            '<a><%s attributeName="xlink:href" values="#x;javascript:alert(1)" dur="1s"/>'
            .'<text x="0" y="10">click</text></a>',
            $element
        )
    );

    $findings = (new SvgHazardScanner)->scan($markup);

    expect($findings)->not->toBeEmpty()
        ->and(array_column($findings, 'kind'))->toContain('element');
})->with(['animate', 'animateTransform', 'animateMotion', 'set', 'handler']);

it('flags a processing instruction', function (): void {
    // ---
    // `<?xml-stylesheet type="text/xsl" href="...">` binds an XSLT stylesheet
    // the browser applies to the document — a script-equivalent transform. PIs
    // are not elements, so the `//*` sweep cannot see them; they need their own
    // query. Legitimate SVG has no need of one.
    // ---
    $markup = '<?xml-stylesheet type="text/xsl" href="evil.xsl"?>'
        .'<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10">'
        .'<rect width="10" height="10"/></svg>';

    $findings = (new SvgHazardScanner)->scan($markup);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['kind'])->toBe('processing-instruction');
});

it('reports every hazard in a file, not just the first', function (): void {
    $findings = (new SvgHazardScanner)->scan(svgHazardScannerTestDocument(
        '<script>alert(1)</script><rect width="10" height="10" onload="alert(2)"/>'
        .'<a href="javascript:alert(3)"><rect width="10" height="10"/></a>'
    ));

    expect($findings)->toHaveCount(3)
        ->and(array_column($findings, 'kind'))
        ->toBe(['element', 'event-handler', 'script-url']);
});
