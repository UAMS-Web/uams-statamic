<?php

declare(strict_types=1);

namespace App\Support\Imaging;

use DOMDocument;
use DOMElement;
use DOMNamedNodeMap;
use DOMXPath;

/**
 * Scans SVG markup for the script-bearing constructs that make a stored SVG a
 * same-origin XSS vector when navigated to directly.
 *
 * Statamic sanitizes SVG on upload (`statamic.assets.svg_sanitization_on_upload`
 * runs `rhukster/dom-sanitizer` in `Statamic\Assets\Uploader::write()`), but the
 * sanitizer only sees files arriving *through* Statamic. `public/assets` is a
 * separate submodule repository where a file can be committed directly — which
 * is how the SVGs currently in the `logos` container got there. This scanner
 * covers that at-rest path (UAMS-Web/uams-statamic-archive#1739).
 *
 * Parsing is done with DOM rather than a regex on purpose: an attribute regex
 * over-matches on things like `<text>onclick=</text>` and under-matches on
 * namespaced or oddly-spaced markup, and a security check that is wrong in
 * either direction is worse than none.
 */
class SvgHazardScanner
{
    /**
     * Element names that execute or embed script when the SVG is rendered as a
     * document. `foreignObject` is included because it embeds arbitrary XHTML.
     *
     * @var list<string>
     */
    private const array HAZARDOUS_ELEMENTS = [
        'script',
        'foreignobject',
        'iframe',
        'embed',
        'object',

        // ---
        // SMIL animation elements. These rewrite another element's attribute at
        // run time, so they defeat any check that reads attribute values as
        // static text: `<animate attributeName="xlink:href"
        // values="#x;javascript:…">` assigns a script URL the scanner never saw
        // in the source. Rejecting the elements outright is both simpler and
        // stricter than trying to interpret their value lists, and it matches
        // `rhukster/dom-sanitizer`, whose own disallowed list carries them.
        // ---
        'animate',
        'animatetransform',
        'animatemotion',
        'set',
        'handler',
    ];

    /**
     * Attribute values carrying a script URL scheme, tested after whitespace
     * and control characters have been stripped (see stripsToScriptUrl).
     */
    private const string SCRIPT_URL_PATTERN = '/^javascript:/i';

    /**
     * Scan SVG markup and return one finding per hazard discovered.
     *
     * @param  string  $markup  Raw SVG markup
     * @return list<array{kind: string, detail: string}> Findings, empty when clean
     */
    public function scan(string $markup): array
    {
        // Treat an empty or whitespace-only file as unparseable rather than clean
        if (trim($markup) === '') {
            return [[
                'kind' => 'unparseable',
                'detail' => 'File is empty.',
            ]];
        }

        // Parse without resolving external entities or hitting the network
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $parsed = $document->loadXML($markup, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        // ---
        // Report a parse failure rather than passing it. A file this scanner
        // cannot read is a file it cannot clear, and silently returning "clean"
        // for it would be the false green the scan exists to prevent.
        // ---
        if ($parsed === false) {
            return [[
                'kind' => 'unparseable',
                'detail' => 'Markup is not well-formed XML, so it could not be scanned.',
            ]];
        }

        // Collect findings across the whole document
        $findings = $this->scanDoctype($document);

        // Build one XPath engine for both the element and the node sweep
        $xpath = new DOMXPath($document);

        foreach ($xpath->query('//*') ?: [] as $element) {
            // Skip non-element nodes the query cannot produce but the type allows
            if (! $element instanceof DOMElement) {
                continue;
            }

            // Flag elements that execute or embed foreign content
            $findings = [...$findings, ...$this->scanElement($element)];

            // Flag attributes that bind handlers or carry script URLs
            $findings = [...$findings, ...$this->scanAttributes($element)];
        }

        /*
         * Processing instructions are NOT elements, so the `//*` sweep above
         * cannot see them. An `xml-stylesheet` instruction binds an XSLT
         * stylesheet that the browser applies to the document, which is a
         * script-equivalent transform. Rejecting every PI is right here:
         * legitimate SVG has no need of one, and the real corpus carries none.
         *
         * (Block comment on purpose — a PI's closing delimiter inside a `//`
         * comment would end PHP mode and break the file.)
         */
        foreach ($xpath->query('//processing-instruction()') ?: [] as $instruction) {
            $findings[] = [
                'kind' => 'processing-instruction',
                'detail' => sprintf('<?%s ...?> processing instruction.', $instruction->nodeName),
            ];
        }

        return $findings;
    }

    /**
     * Determine whether SVG markup is free of script-bearing constructs.
     *
     * @param  string  $markup  Raw SVG markup
     * @return bool True when the scan produced no findings
     */
    public function isClean(string $markup): bool
    {
        return $this->scan($markup) === [];
    }

    /**
     * Flag a DOCTYPE that declares internal entities.
     *
     * This is the scanner's most important guard and the least obvious. libxml
     * parses an internal-subset entity into a `DOMEntityReference` node and its
     * XPath engine does not descend into one, so `//*` is blind to whatever the
     * entity carries — while a browser expands it and runs it. Wrapping a
     * `<script>` in `<!ENTITY xss "…">` therefore hides it from every other
     * check in this class.
     *
     * Expanding entities instead (`LIBXML_NOENT`) would be worse, not better:
     * it makes a `SYSTEM "file:///…"` entity read local files, and
     * `LIBXML_NO_XXE` is not available on the libxml this app ships. So the
     * scanner fails closed on the declaration rather than resolving it. No
     * legitimate SVG in the corpus declares an entity, so this costs nothing.
     *
     * @param  DOMDocument  $document  The parsed document
     * @return list<array{kind: string, detail: string}> Findings for the DOCTYPE
     */
    private function scanDoctype(
        DOMDocument $document
    ): array {
        // Read the declared internal entities, if the document has a DOCTYPE
        $entities = $document->doctype?->entities;

        // Pass a document that declares none
        if (! $entities instanceof DOMNamedNodeMap || $entities->length === 0) {
            return [];
        }

        return [[
            'kind' => 'entity-declaration',
            'detail' => sprintf(
                'DOCTYPE declares %d internal %s, which can hide markup from this scan.',
                $entities->length,
                $entities->length === 1 ? 'entity' : 'entities'
            ),
        ]];
    }

    /**
     * Flag an element whose tag name executes script or embeds foreign content.
     *
     * @param  DOMElement  $element  The element to inspect
     * @return list<array{kind: string, detail: string}> Findings for this element
     */
    private function scanElement(
        DOMElement $element
    ): array {
        // Compare on the local name so a namespace prefix cannot hide the tag
        $localName = strtolower((string) $element->localName);

        // Pass elements that are not on the hazardous list
        if (! in_array($localName, self::HAZARDOUS_ELEMENTS, true)) {
            return [];
        }

        return [[
            'kind' => 'element',
            'detail' => sprintf('<%s> element.', $localName),
        ]];
    }

    /**
     * Flag event-handler attributes and attributes carrying a script URL.
     *
     * @param  DOMElement  $element  The element whose attributes to inspect
     * @return list<array{kind: string, detail: string}> Findings for this element
     */
    private function scanAttributes(
        DOMElement $element
    ): array {
        // Collect findings across the element's attributes
        $findings = [];

        foreach ($element->attributes as $attribute) {
            // Compare on the local name so `xlink:href` and `href` both match
            $name = strtolower((string) $attribute->localName);

            // ---
            // Flag any `on*` attribute. Matching the prefix rather than a fixed
            // list is deliberate: the handler set grows with the platform, and a
            // list would silently pass whatever it had not heard of yet.
            // ---
            if (str_starts_with($name, 'on')) {
                $findings[] = [
                    'kind' => 'event-handler',
                    'detail' => sprintf(
                        '%s="..." on <%s>.',
                        $attribute->localName,
                        strtolower((string) $element->localName)
                    ),
                ];

                continue;
            }

            // Flag a `javascript:` URL in any attribute that can navigate
            if ($this->stripsToScriptUrl($attribute->value)) {
                $findings[] = [
                    'kind' => 'script-url',
                    'detail' => sprintf(
                        '%s="javascript:..." on <%s>.',
                        $attribute->localName,
                        strtolower((string) $element->localName)
                    ),
                ];
            }
        }

        return $findings;
    }

    /**
     * Determine whether an attribute value resolves to a `javascript:` URL.
     *
     * Whitespace and control characters are stripped before the test because
     * browsers ignore them inside a URL scheme — `java&#9;script:alert(1)` and
     * `\njavascript:alert(1)` both navigate, so a check anchored on the literal
     * scheme alone is trivially bypassed.
     *
     * @param  string  $value  The raw attribute value
     * @return bool True when the value carries a script URL
     */
    private function stripsToScriptUrl(
        string $value
    ): bool {
        // Strip every whitespace and C0/C1 control character from the value
        $collapsed = preg_replace('/[\s\x00-\x1F\x7F-\xA0]+/u', '', $value)
            ?? preg_replace('/[\s\x00-\x1F\x7F]+/', '', $value)
            ?? $value;

        return preg_match(self::SCRIPT_URL_PATTERN, $collapsed) === 1;
    }
}
