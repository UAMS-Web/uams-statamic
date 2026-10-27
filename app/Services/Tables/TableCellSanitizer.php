<?php

declare(strict_types=1);

namespace App\Services\Tables;

use App\Modifiers\SanitizeTableCell;
use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Statamic\View\Antlers\Language\Runtime\NodeProcessor;
use Throwable;

/**
 * Sanitizes table-cell HTML to a small inline allowlist before it is rendered.
 *
 * Table cells (in the Table Bard set and the `tables` collection) store raw HTML
 * — imported WordPress tables carry inline `<a>` links and basic emphasis inside
 * cells, and the render partial historically emitted that value unescaped
 * (`{{ value }}`). That is both a feature gap (links could not be authored) and a
 * stored-XSS surface (any markup reaching a cell rendered verbatim).
 *
 * This service runs each cell through {@see HTMLPurifier} with an allowlist that
 * permits inline links and emphasis and escapes everything else, so:
 * - imported/authored links and emphasis survive and render, and
 * - scripts, event handlers, and dangerous URL schemes (`javascript:`, `data:`)
 *   are stripped.
 *
 * Plain-text cells pass through unchanged apart from correct entity encoding, so
 * there is no regression for tables that never used markup.
 *
 * @see SanitizeTableCell The Antlers modifier that exposes this.
 */
class TableCellSanitizer
{
    /**
     * The inline tags permitted inside a table cell.
     *
     * Links (`a`) plus basic emphasis/format tags. Block-level and interactive
     * elements are intentionally excluded so cells stay inline and safe.
     */
    private const string ALLOWED_HTML = 'a[href|title|target],br,strong,em,b,i,u,s,sub,sup,code,abbr[title]';

    /**
     * The URL schemes permitted on cell links.
     *
     * Restricting to these schemes blocks `javascript:` and `data:` URIs, which
     * are the primary link-borne XSS vectors.
     *
     * @var list<string>
     */
    private const array ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * The memoized purifier instance, built once per process.
     *
     * Building the purifier compiles and caches an HTML definition, so it is
     * reused across the many cells in a table rather than rebuilt per call.
     */
    private ?HTMLPurifier $purifier = null;

    /**
     * Whether this process has abandoned the on-disk definition cache.
     *
     * Latched once a cache failure has been recovered from, so the fallback is
     * paid at most once per process rather than on every cell.
     */
    private bool $definitionCacheAbandoned = false;

    /**
     * Sanitize a single table cell's HTML to the inline allowlist.
     *
     * @param  mixed  $value  The raw cell value (string, scalar, or null)
     * @return string The sanitized HTML, safe to render unescaped
     */
    public function sanitize(
        mixed $value
    ): string {
        // Coerce non-string scalars to string; treat null/non-scalar as empty
        $html = \is_scalar($value) ? (string) $value : '';

        // Nothing to sanitize for an empty cell
        if ($html === '') {
            return '';
        }

        // Purify the cell against the inline allowlist.
        try {
            return $this->purifier()->purify($html);
        } catch (Throwable $throwable) {
            return $this->sanitizeAfterFailure($html, $throwable);
        }
    }

    /**
     * Recover from a purifier failure without ever emitting the raw cell.
     *
     * This is a security boundary, not merely error handling. Statamic catches a
     * throwing modifier and returns the value it held BEFORE the modifier ran
     * ({@see NodeProcessor::runModifier()}
     * rethrows only when `app.debug` is on), and
     * `resources/views/sets/_table.antlers.html` emits that value unescaped —
     * Antlers is raw-by-default here. So a throw out of `sanitize()` publishes
     * the raw cell verbatim in production: a stored-XSS fail-open. Both
     * known triggers are the on-disk definition cache — HTMLPurifier raises
     * `E_USER_WARNING` when it cannot create its type subdirectory, and
     * `unserialize()` warns on a `.ser` a concurrent writer truncated — and
     * Laravel promotes both to an `ErrorException` in every environment, not
     * just under test. Every path below therefore returns something safe —
     * including when the LOGGING itself fails (UAMS-Web/uams-statamic-archive#1869). That is one fault, not
     * two: a full disk (or a `storage/logs` that cannot be written) makes HTMLPurifier's
     * unsuppressed `file_put_contents()` throw AND makes Monolog throw
     * `UnexpectedValueException` when it cannot open its stream, so the
     * condition that triggers this recovery is the same one that breaks
     * reporting it. Such a throw escapes `sanitize()`, and measured end to end
     * it does NOT publish the raw cell — Statamic's own handler is broken by
     * the same fault, so the render 500s — but a table that could have
     * rendered correctly is lost to a missing log line. Hence
     * {@see self::logWithoutThrowing()}.
     *
     * @param  string  $html  The raw cell HTML that could not be purified
     * @param  Throwable  $failure  The failure being recovered from
     * @return string Sanitized HTML, or escaped text if nothing else works
     */
    private function sanitizeAfterFailure(
        string $html,
        Throwable $failure
    ): string {
        // Dropping the cache clears both known triggers and keeps the allowlist,
        // so cells that merely lost their cache still render their links.
        if (! $this->definitionCacheAbandoned) {
            $this->logWithoutThrowing(static fn () => Log::warning('The table-cell sanitizer abandoned its HTMLPurifier definition cache after a failure; recompiling in-process.', [
                'reason' => $failure->getMessage(),
            ]));

            $this->definitionCacheAbandoned = true;
            $this->purifier = null;

            try {
                return $this->purifier()->purify($html);
            } catch (Throwable $retryFailure) {
                $failure = $retryFailure;
            }
        }

        $this->logWithoutThrowing(static fn () => Log::error('The table-cell sanitizer could not purify a cell; falling back to escaped text.', [
            'reason' => $failure->getMessage(),
        ]));

        // Fail closed. No markup survives, so nothing in the cell can execute.
        // Reached only if purifying without any cache also fails, which no known
        // trigger produces — it is the backstop that keeps the guarantee above
        // true for failures we have not seen.
        return e(strip_tags($html));
    }

    /**
     * Record a sanitizer failure without ever throwing out of the sanitizer.
     *
     * A logger is not guaranteed to work at the moment a sanitizer fails, and
     * the interesting case is that the SAME fault causes both: a full disk (or a
     * `storage/logs` that cannot be written) makes HTMLPurifier's unsuppressed
     * `file_put_contents()` of its definition throw, and makes Monolog throw
     * `UnexpectedValueException` when it cannot open its stream. Letting that
     * escape would undo {@see self::sanitizeAfterFailure()} entirely: Statamic
     * returns the value held BEFORE a throwing modifier ran, and
     * `resources/views/sets/_table.antlers.html` emits it unescaped. Measured,
     * that does not become a leak — Statamic's handler is broken by the same
     * fault and the render 500s instead — so this is availability, not the
     * stored-XSS fail-open UAMS-Web/uams-statamic-archive#1853 closed.
     *
     * Losing a log line is the correct trade against losing the page. Nothing
     * here can be reported anywhere else, because reporting is what is broken.
     *
     * @param  callable(): void  $log  The log call to attempt
     */
    private function logWithoutThrowing(
        callable $log
    ): void {
        try {
            $log();
        } catch (Throwable) {
            // Deliberately swallowed. See the docblock: the alternative is losing
            // the page, and there is no working channel left to report on.
        }
    }

    /**
     * Build (once) and return the configured purifier instance.
     *
     * @return HTMLPurifier The memoized, allowlist-configured purifier
     */
    private function purifier(): HTMLPurifier
    {
        // Reuse the already-built instance when present
        if ($this->purifier instanceof HTMLPurifier) {
            return $this->purifier;
        }

        // Start from the default configuration
        $config = HTMLPurifier_Config::createDefault();

        // Restrict output to the inline allowlist (links + emphasis)
        $config->set(
            'HTML.Allowed',
            self::ALLOWED_HTML
        );

        // Permit only `target="_blank"`; HTMLPurifier then auto-adds
        // `rel="noopener noreferrer"` to those links (TargetNoopener /
        // TargetNoreferrer defaults), matching the site's link hardening.
        $config->set(
            'Attr.AllowedFrameTargets',
            ['_blank']
        );

        // Block dangerous URL schemes by allowing only safe ones
        $config->set(
            'URI.AllowedSchemes',
            \array_fill_keys(
                self::ALLOWED_SCHEMES,
                true
            )
        );

        // Cache the compiled definition under storage (vendor is read-only in
        // production), creating the directory if it does not yet exist.
        $cachePath = $this->definitionCacheAbandoned ? null : $this->prepareCacheDirectory();

        if ($cachePath === null) {
            // No usable cache — storage cannot be written, or a failure already
            // made us give up on it. Compile the definition in-process rather
            // than leave HTMLPurifier warning on every cell; the memoization
            // above amortizes that to once per PHP process.
            $this->definitionCacheAbandoned = true;

            $config->set(
                'Cache.DefinitionImpl',
                null
            );

            return $this->purifier = new HTMLPurifier($config);
        }

        $config->set(
            'Cache.SerializerPath',
            $cachePath
        );

        // Build and memoize the purifier
        return $this->purifier = new HTMLPurifier($config);
    }

    /**
     * Resolve the serializer-cache directory and make sure it exists.
     *
     * @return string|null The cache directory, or null when it cannot be created
     */
    private function prepareCacheDirectory(): ?string
    {
        /** @var string $path */
        $path = config('tables.cell_sanitizer.cache_path') ?? storage_path('app/htmlpurifier');

        if (File::isDirectory($path)) {
            return $path;
        }

        // Create it without a check-then-create race. `ensureDirectoryExists()`
        // tests `isDirectory()` and then calls Laravel's UNGUARDED `mkdir()`, so
        // two workers reaching a cold cache together both see the directory
        // absent and the loser takes a `mkdir(): File exists` warning — promoted
        // to an ErrorException by the test environment's error handler, which is
        // how UAMS-Web/uams-statamic-archive#1853 surfaced. Suppressing the warning (the `$force` argument)
        // and re-checking is the race-safe idiom: the loser simply observes the
        // directory the winner has just created.
        if (File::makeDirectory($path, 0755, true, true)) {
            return $path;
        }

        // A suppressed failure means either "a sibling already made it" or "it
        // genuinely cannot be made", and only the filesystem can tell those
        // apart. Re-read it directly — deliberately NOT through the check above,
        // whose whole purpose was to answer before the concurrent create — after
        // dropping PHP's stat cache, which still holds the "absent" answer.
        clearstatcache(true, $path);

        if (is_dir($path)) {
            return $path;
        }

        Log::warning('Could not create the table-cell sanitizer cache directory; HTMLPurifier will recompile its definition once per process.', [
            'path' => $path,
        ]);

        return null;
    }
}
