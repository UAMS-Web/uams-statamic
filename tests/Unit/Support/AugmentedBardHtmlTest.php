<?php

declare(strict_types=1);

use App\Support\AugmentedBardHtml;
use Illuminate\Support\Collection;
use Statamic\Fields\Value;
use Statamic\Fields\Values;

covers(AugmentedBardHtml::class);

/**
 * Tests for the helpers the Bard injection modifiers use to rewrite augmented Bard HTML.
 *
 * @command  php artisan test --compact tests/Unit/Support/AugmentedBardHtmlTest.php
 */

/**
 * A mutation callable that records each call and wraps the HTML it receives.
 *
 * @param  array<int, array{0: string, 1: string, 2: string}>  $calls  Receives one entry per call
 */
function augmentedBardHtmlRecorder(array &$calls): Closure
{
    return function (string $html, string $injected, string $element) use (&$calls): string {
        $calls[] = [$html, $injected, $element];

        return "[{$element}:{$injected}]{$html}";
    };
}

/**
 * Run a callback with PCRE limited so a match fails, restoring the limits before returning.
 *
 * The limits stay in force only for the call, and a plain error handler stands in for the runner's,
 * because the runner's handler and printer use PCRE themselves and fail under the limit.
 *
 * @template TResult
 *
 * @param  Closure(): TResult  $callback  The call to run under the limit
 * @return TResult What the callback returned
 */
function augmentedBardHtmlUnderBacktrackLimit(Closure $callback): mixed
{
    set_error_handler(static function (int $severity, string $message): never {
        throw new ErrorException($message, 0, $severity);
    });
    ini_set('pcre.jit', '0');
    ini_set('pcre.backtrack_limit', '1');

    try {
        return $callback();
    } finally {
        ini_restore('pcre.jit');
        ini_restore('pcre.backtrack_limit');
        restore_error_handler();
    }
}

it('returns the value untouched and never mutates when there is nothing to inject', function (mixed $value): void {
    $calls = [];

    expect(AugmentedBardHtml::transformFirstTextBlock($value, '', 'span', augmentedBardHtmlRecorder($calls)))->toBe($value)
        ->and($calls)->toBe([]);
})->with([
    'string' => ['<p>Hi</p>'],
    'rows' => [[['type' => 'text', 'text' => '<p>Hi</p>']]],
]);

it('mutates a set-less string field as one fragment with the normalized element', function (): void {
    $calls = [];

    $result = AugmentedBardHtml::transformFirstTextBlock('<p>Hi</p>', 'NEW', ' SPAN ', augmentedBardHtmlRecorder($calls));

    expect($result)->toBe('[span:NEW]<p>Hi</p>')
        ->and($calls)->toBe([['<p>Hi</p>', 'NEW', 'span']]);
});

it('returns a value that is neither a string nor rows unchanged', function (mixed $value): void {
    $calls = [];

    expect(AugmentedBardHtml::transformFirstTextBlock($value, 'NEW', 'p', augmentedBardHtmlRecorder($calls)))->toBe($value)
        ->and($calls)->toBe([]);
})->with([
    'null' => [null],
    'integer' => [7],
    'object' => [new stdClass],
]);

it('mutates only the first text row of an array, keeping keys and the other rows', function (): void {
    $calls = [];
    $image = ['type' => 'image', 'text' => '<p>caption</p>'];

    $result = AugmentedBardHtml::transformFirstTextBlock([
        'a' => $image,
        'b' => 'loose',
        'c' => ['type' => 'text', 'text' => '<p>One</p>', 'extra' => 1],
        'd' => ['type' => 'text', 'text' => '<p>Two</p>'],
    ], 'NEW', 'p', augmentedBardHtmlRecorder($calls));

    expect($result)->toBe([
        'a' => $image,
        'b' => 'loose',
        'c' => ['type' => 'text', 'text' => '[p:NEW]<p>One</p>', 'extra' => 1],
        'd' => ['type' => 'text', 'text' => '<p>Two</p>'],
    ])->and($calls)->toBe([['<p>One</p>', 'NEW', 'p']]);
});

it('unwraps a collection of rows into a plain array', function (): void {
    $calls = [];

    $result = AugmentedBardHtml::transformFirstTextBlock(
        new Collection([['type' => 'text', 'text' => '<p>One</p>']]),
        'NEW',
        'p',
        augmentedBardHtmlRecorder($calls),
    );

    expect($result)->toBe([['type' => 'text', 'text' => '[p:NEW]<p>One</p>']]);
});

it('reads and rebuilds a Values text row from its raw data', function (): void {
    $calls = [];
    $set = new Values(['type' => 'quote', 'text' => '<p>Quoted</p>']);

    $result = AugmentedBardHtml::transformFirstTextBlock([
        $set,
        new Values(['type' => 'text', 'text' => new Value('<p>One</p>'), 'caption' => new Value('Cap'), 'id' => 'x']),
        new Values(['type' => 'text', 'text' => '<p>Two</p>']),
    ], 'NEW', 'p', augmentedBardHtmlRecorder($calls));

    expect($result)->toBeArray()->toHaveCount(3);

    /** @var array<int, mixed> $result */
    expect($result[0])->toBe($set)
        ->and($result[1])->toBe(['type' => 'text', 'text' => '[p:NEW]<p>One</p>', 'caption' => 'Cap', 'id' => 'x'])
        ->and($result[2])->toBeInstanceOf(Values::class)
        ->and($calls)->toBe([['<p>One</p>', 'NEW', 'p']]);
});

it('treats a Values row whose raw type is not text as a set', function (): void {
    $calls = [];
    $row = new Values(['text' => '<p>One</p>']);

    expect(AugmentedBardHtml::transformFirstTextBlock([$row], 'NEW', 'p', augmentedBardHtmlRecorder($calls)))->toBe([$row])
        ->and($calls)->toBe([]);
});

it('coerces the text of an array row to a string before mutating it', function (mixed $text, string $expected): void {
    $calls = [];

    AugmentedBardHtml::transformFirstTextBlock([['type' => 'text', 'text' => $text]], 'NEW', 'p', augmentedBardHtmlRecorder($calls));

    expect($calls[0][0])->toBe($expected);
})->with([
    'missing' => [null, ''],
    'wrapped value' => [new Value('<p>Wrapped</p>'), '<p>Wrapped</p>'],
    'wrapped integer' => [new Value(12), '12'],
    'integer' => [42, '42'],
    'float' => [1.5, '1.5'],
    'true' => [true, '1'],
    'false' => [false, ''],
    'array' => [['<p>x</p>'], ''],
]);

it('reads an empty string from a text row that has no text key', function (): void {
    $calls = [];

    $result = AugmentedBardHtml::transformFirstTextBlock([['type' => 'text']], 'NEW', 'p', augmentedBardHtmlRecorder($calls));

    expect($calls)->toBe([['', 'NEW', 'p']])
        ->and($result)->toBe([['type' => 'text', 'text' => '[p:NEW]']]);
});

it('coerces the raw text of a Values row to a string', function (mixed $text, string $expected): void {
    $calls = [];

    AugmentedBardHtml::transformFirstTextBlock([new Values(['type' => 'text', 'text' => $text])], 'NEW', 'p', augmentedBardHtmlRecorder($calls));

    expect($calls[0][0])->toBe($expected);
})->with([
    'missing' => [null, ''],
    'integer' => [5, '5'],
    'array' => [['x'], ''],
]);

it('injects after the first opening tag of the element only', function (string $html, string $element, string $expected): void {
    expect(AugmentedBardHtml::injectAfterFirstOpeningTag($html, 'NEW ', $element))->toBe($expected);
})->with([
    'plain' => ['<p>Body</p><p>More</p>', 'p', '<p>NEW Body</p><p>More</p>'],
    'attributes kept' => ['<p class="lede">Body</p>', 'p', '<p class="lede">NEW Body</p>'],
    'leading whitespace kept' => ["\n  <p>Body</p>", 'p', "\n  <p>NEW Body</p>"],
    'case insensitive' => ['<P>Body</P>', 'p', '<P>NEW Body</P>'],
    'not at the start' => ['<div><p>Body</p></div>', 'p', '<div><p>Body</p></div>'],
    'prefix of a longer tag' => ['<pre>Body</pre>', 'p', '<pre>Body</pre>'],
    'other element' => ['<h2>Title</h2>', 'h2', '<h2>NEW Title</h2>'],
    'empty' => ['', 'p', ''],
]);

it('treats the injected text literally when it holds replacement references', function (): void {
    expect(AugmentedBardHtml::injectAfterFirstOpeningTag('<p class="a">Body</p>', 'Cost $2, $1 or \\1: ', 'p'))
        ->toBe('<p class="a">Cost $2, $1 or \\1: Body</p>');
});

it('returns the fragment unchanged when the opening-tag match fails', function (): void {
    $html = str_repeat(' ', 50).'<p a="b">Body</p>';

    expect(augmentedBardHtmlUnderBacktrackLimit(fn (): string => AugmentedBardHtml::injectAfterFirstOpeningTag($html, 'NEW', 'p')))->toBe($html);
});

it('injects before the last closing tag of the element', function (string $html, string $element, string $expected): void {
    expect(AugmentedBardHtml::injectBeforeLastClosingTag($html, ' NEW', $element))->toBe($expected);
})->with([
    'single' => ['<p>Body</p>', 'p', '<p>Body NEW</p>'],
    'last of several' => ['<p>One</p><p>Two</p>', 'p', '<p>One</p><p>Two NEW</p>'],
    'whitespace before the bracket' => ['<p>Body</p  >', 'p', '<p>Body NEW</p  >'],
    'case insensitive' => ['<P>Body</P>', 'p', '<P>Body NEW</P>'],
    'trailing content kept' => ['<p>Body</p><br>', 'p', '<p>Body NEW</p><br>'],
    'multibyte before the tag' => ['<p>ÉÑ-12 Café</p>', 'p', '<p>ÉÑ-12 Café NEW</p>'],
    'no closing tag' => ['<p>Body', 'p', '<p>Body'],
    'other element only' => ['<div>Body</div>', 'p', '<div>Body</div>'],
    'empty' => ['', 'p', ''],
]);

it('returns the fragment unchanged when the closing-tag match fails', function (): void {
    $html = '<p>Body</p'.str_repeat(' ', 50).'>';

    // Capture the PCRE error inside the limited call, before the runner's own matching can reset it
    [$result, $error] = augmentedBardHtmlUnderBacktrackLimit(fn (): array => [
        AugmentedBardHtml::injectBeforeLastClosingTag($html, ' NEW', 'p'),
        preg_last_error(),
    ]);

    // PHP caches each compiled pattern with the JIT setting in force when it was first compiled,
    // and the dataset above compiles this one first. The JIT-compiled match has been measured
    // succeeding under a limit of 1 (PCRE 10.44, Windows), so the failure cannot be forced (#1226).
    if ($error !== PREG_BACKTRACK_LIMIT_ERROR) {
        $this->markTestSkipped('The backtrack limit did not make the closing-tag match fail: the pattern was compiled with JIT before the limit was set (#1226).');
    }

    expect($result)->toBe($html);
});

it('normalizes the element argument to a lowercase tag name or p', function (mixed $candidate, string $expected): void {
    expect(AugmentedBardHtml::normalizeElementName($candidate))->toBe($expected);
})->with([
    'plain' => ['span', 'span'],
    'uppercase' => ['STRONG', 'strong'],
    'trimmed' => ["  em\t", 'em'],
    'digits after a letter' => ['H2', 'h2'],
    'empty' => ['', 'p'],
    'whitespace' => ['   ', 'p'],
    'leading digit' => ['2h', 'p'],
    'attribute smuggled' => ['p onclick', 'p'],
    'markup' => ['<b>', 'p'],
    'trailing junk' => ['b>', 'p'],
    'leading junk' => ['-b', 'p'],
    'null' => [null, 'p'],
    'integer' => [1, 'p'],
    'array' => [['b'], 'p'],
]);
