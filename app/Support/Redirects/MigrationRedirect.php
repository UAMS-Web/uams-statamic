<?php

declare(strict_types=1);

namespace App\Support\Redirects;

use App\Services\Redirects\MigrationRedirectGenerator;
use App\Tags\FindRedirectRule;
use Illuminate\Support\Str;

/**
 * A typed, immutable migration redirect planned by the generator (UAMS-Web/uams-statamic-archive#1641).
 *
 * The WordPress → Statamic migration changes URLs; every production URL that no
 * longer resolves would 404 and forfeit its SEO equity. This value object is one
 * planned rule in that migration map — an old WordPress path that must resolve,
 * either by moving to the entity's new Statamic URL (a `301`) or, for retired
 * content with no surviving ancestor, by declaring the resource permanently
 * {@see gone()} (a `410`).
 *
 * Unlike a {@see MarketingRedirect} (which enters the approval queue as
 * `pending`), a migration redirect is a deterministic, system-generated rule and
 * is written {@see RedirectStatus::Approved} so it serves immediately. It is
 * persisted as one `redirect` replicator row via {@see toRow()}, matching the
 * keys the serving tag ({@see FindRedirectRule}) reads.
 */
final readonly class MigrationRedirect
{
    /**
     * The replicator set handle every redirect row is stored under.
     */
    public const string SET_HANDLE = 'redirect';

    /**
     * The `order` value stamped on generated migration redirects.
     *
     * Higher than the page-specific default (30) so a human-authored marketing
     * redirect for the same path always sorts — and therefore matches — ahead of
     * a bulk migration rule.
     */
    public const int DEFAULT_ORDER = 40;

    /**
     * @param  string  $source  Canonical stored source path (see {@see RedirectSource::normalize()}), or a PCRE pattern when `$useRegex`
     * @param  string|null  $target  Destination URL for a move; null for a `410 Gone`
     * @param  string  $response  HTTP redirect status — '301' for a move, '410' for gone
     * @param  string|null  $notes  Free-text provenance recorded on the row
     * @param  bool  $useRegex  Whether `$source` is a PCRE pattern rather than a literal path
     */
    public function __construct(
        public string $source,
        public ?string $target,
        public string $response = '301',
        public ?string $notes = null,
        public bool $useRegex = false,
    ) {}

    /**
     * Build a permanent `301` move from an old WordPress path to a new Statamic URL.
     *
     * @param  string  $from  The original WordPress path (canonicalized here)
     * @param  string  $to  The current Statamic URL to move to
     * @param  string|null  $notes  Optional provenance note
     * @return self The planned 301 redirect
     */
    public static function moved(string $from, string $to, ?string $notes = null): self
    {
        return new self(
            source: RedirectSource::normalize($from),
            target: $to,
            response: '301',
            notes: $notes,
        );
    }

    /**
     * Build a `410 Gone` for a retired WordPress path with no surviving ancestor.
     *
     * @param  string  $from  The original WordPress path (canonicalized here)
     * @param  string|null  $notes  Optional provenance note
     * @return self The planned 410 redirect
     */
    public static function gone(string $from, ?string $notes = null): self
    {
        return new self(
            source: RedirectSource::normalize($from),
            target: null,
            response: '410',
            notes: $notes,
        );
    }

    /**
     * Build a permanent `301` move for a whole *family* of old WordPress URLs
     * matched by one PCRE pattern (UAMS-Web/uams-statamic-archive#1657).
     *
     * Some structural WordPress URL shapes are unbounded — `/<anything>/feed/`
     * and `/<archive>/page/<N>/` have no finite enumeration — so one pattern rule
     * covers them where one row per URL never could. A regex rule is only ever
     * consulted *after* every literal source has failed to match
     * ({@see FindRedirectRule} runs the exact pass first), so a pattern can never
     * shadow a real page or a human-authored rule.
     *
     * @param  string  $pattern  The PCRE pattern (delimiters included) matched against the request path
     * @param  string  $to  The Statamic URL every matching path moves to
     * @param  string|null  $notes  Optional provenance note
     * @return self The planned 301 pattern redirect
     */
    public static function movedPattern(string $pattern, string $to, ?string $notes = null): self
    {
        return new self(
            source: $pattern,
            target: $to,
            response: '301',
            notes: $notes,
            useRegex: true,
        );
    }

    /**
     * Build a `410 Gone` for a whole family of old WordPress URLs matched by one
     * PCRE pattern (UAMS-Web/uams-statamic-archive#1657) — the disposition when no surviving page can stand in
     * for the shape (e.g. pagination on a site with no posts index to land on).
     *
     * @param  string  $pattern  The PCRE pattern (delimiters included) matched against the request path
     * @param  string|null  $notes  Optional provenance note
     * @return self The planned 410 pattern redirect
     */
    public static function gonePattern(string $pattern, ?string $notes = null): self
    {
        return new self(
            source: $pattern,
            target: null,
            response: '410',
            notes: $notes,
            useRegex: true,
        );
    }

    /**
     * The case-insensitive key used to dedupe this redirect against existing and
     * sibling planned rows (one rule per source path wins).
     *
     * @return string The lowercased canonical source key
     */
    public function collisionKey(): string
    {
        return self::keyFor($this->source, $this->useRegex);
    }

    /**
     * Derive the dedup key for a source, honoring whether it is a pattern.
     *
     * A regex source is a *pattern*, not a path: running it through
     * {@see RedirectSource::normalize()} would strip its delimiters and graft on a
     * leading slash, so it is keyed verbatim (lowercased) under its own `regex:`
     * namespace instead. That also keeps a pattern and a literal path from ever
     * colliding, and — because {@see MigrationRedirectGenerator} keys the *stored*
     * rows through this same method — keeps a re-run idempotent for pattern rules
     * exactly as it already is for literal ones.
     *
     * @param  string  $source  The stored source (a path, or a PCRE pattern)
     * @param  bool  $useRegex  Whether the source is a PCRE pattern
     * @return string The dedup key for that source
     */
    public static function keyFor(string $source, bool $useRegex): string
    {
        return $useRegex
            ? 'regex:'.mb_strtolower($source)
            : RedirectSource::collisionKey($source);
    }

    /**
     * Render this migration redirect as a `redirect` replicator row for the
     * `redirects` global.
     *
     * A `301` stores an explicit-URL target; a `410` carries no destination — the
     * serving template detects the `410`/`403` response and aborts with that
     * status (there is no target to move to). Either way the row is stamped
     * {@see RedirectStatus::Approved} so it serves without human review.
     *
     * @param  string|null  $id  The replicator row id (a fresh UUID when omitted)
     * @return array{id: string, type: string, enabled: bool, status: string, source: string, use_regex: bool, target_type: string, target_url: string|null, response: string, order: int, notes: string|null} The stored redirect row
     */
    public function toRow(?string $id = null): array
    {
        return [
            // Uuid is Stringable; the cast is observationally identical under mutation.
            // @pest-mutate-ignore: RemoveStringCast
            'id' => $id ?? (string) Str::uuid(),
            'type' => self::SET_HANDLE,
            'enabled' => true,
            'status' => RedirectStatus::Approved->value,
            'source' => $this->source,
            'use_regex' => $this->useRegex,
            'target_type' => 'url',
            'target_url' => $this->target,
            'response' => $this->response,
            'order' => self::DEFAULT_ORDER,
            'notes' => $this->notes,
        ];
    }
}
