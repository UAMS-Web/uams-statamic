<?php

declare(strict_types=1);

namespace App\Modifiers;

use Statamic\Fields\Value;
use Statamic\Modifiers\Modifier;
use Stringable;

/**
 * Antlers modifier: make an editor/importer-controlled URL safe to render into an
 * `href`/`itemid`/`src` HTML attribute.
 *
 * A browser HTML-decodes an attribute value exactly once before resolving it, so
 * both attribute-safety concerns are closed together against that single decode:
 *
 * - **Dangerous scheme.** Detection runs on the HTML-decoded, control-stripped URL,
 *   so entity- and whitespace-obfuscated payloads (`javascript&#58;…`,
 *   `jav&#97;script:…`, `java&#9;script:…`, `  javascript:…`, `JaVaScRiPt:…`) are all
 *   seen as their real scheme. Only `http`/`https`/`mailto`/`tel` and site-relative
 *   (no-scheme) URLs survive; every other scheme collapses to an inert `#`.
 * - **Attribute breakout.** The result is re-encoded with `htmlspecialchars(…,
 *   double_encode: true)`, so a `"` — literal or pre-encoded as `&#34;` — cannot
 *   escape the attribute, and no surviving character reference can be re-decoded by
 *   the browser into a scheme colon or a quote.
 *
 * Because it owns the encoding, use it as the LAST step and do NOT chain `entities`
 * after it: `{{ url | sanitize_url }}` (a following `entities` would double-encode
 * the ampersands and break legitimate links).
 */
class SanitizeUrl extends Modifier
{
    /**
     * Schemes allowed to render as an active URL; every other scheme is neutralized.
     *
     * @var list<string>
     */
    private const array SAFE_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Neutralize a dangerous URL scheme and HTML-encode the result for an attribute.
     *
     * @param  mixed  $value  The URL to sanitize (string / Stringable / Statamic Value)
     * @param  array<int|string, mixed>  $params  `$params[0]` overrides the inert fallback (default `#`)
     * @param  array<int|string, mixed>  $context  Unused Statamic modifier context
     * @return string An attribute-safe URL: the original when safe, otherwise the inert fallback
     */
    public function index(
        mixed $value,
        array $params,
        array $context
    ): string {
        // Unwrap a Statamic Value, then coerce scalars/Stringable to a plain string.
        if ($value instanceof Value) {
            $value = $value->value();
        }

        $url = ($value instanceof Stringable || \is_scalar($value)) ? (string) $value : '';

        $fallback = (isset($params[0]) && \is_scalar($params[0])) ? (string) $params[0] : '#';

        // Resolve the URL the browser actually sees: HTML-decode character references,
        // then strip the C0 control + space characters it ignores when reading a scheme.
        // This catches entity/whitespace obfuscation (`javascript&#58;`, `java<TAB>script:`).
        $canonical = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $probe = (string) preg_replace('/[\x00-\x20]+/', '', $canonical);

        // A leading "letter [letter|digit|+|-|.]* :" run is a URL scheme.
        if (preg_match('/^([a-zA-Z][a-zA-Z0-9+.\-]*):/', $probe, $matches) === 1) {
            if (! \in_array(strtolower($matches[1]), self::SAFE_SCHEMES, true)) {
                $canonical = $fallback;
            }
        }

        // Re-encode so the browser's single HTML-decode reproduces exactly $canonical:
        // no `"` breakout, and no surviving reference can smuggle a scheme colon back.
        return htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8', true);
    }
}
