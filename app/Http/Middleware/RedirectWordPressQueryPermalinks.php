<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\SiteHttpException;
use App\Services\Redirects\MarketingRedirectStore;
use App\Services\Redirects\StructuralRedirectSynthesizer;
use App\Support\Redirects\RedirectStatus;
use App\Support\Redirects\ValidityWindow;
use App\Tags\FindRedirectRule;
use App\Tags\RespondWithStatus;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the WordPress ugly-permalink redirects (`?p=` / `?page_id=`) the
 * migration generator synthesizes (UAMS-Web/uams-statamic-archive#1657).
 *
 * Every other migration redirect is served by {@see FindRedirectRule} on the 404
 * template, which cannot reach these two for two independent reasons: the tag
 * matches `request()->path()`, which never carries a query string, and — more
 * fundamentally — `/?p=123` does not 404 at all. Its *path* is the site root, so
 * WordPress's ugly permalink renders the home page with a `200` and the 404
 * template never runs. Only a middleware sitting in front of the response can
 * intercept it, so that is where this shape is served.
 *
 * WordPress resolves `?p=` and `?page_id=` interchangeably (its post ids are unique
 * across post types), so both are normalized onto the single `/?p=<id>` source the
 * generator writes rather than storing near-identical rows for each spelling. The
 * rule still lives in the shared `redirects` store, so an editor can retarget or
 * disable a generated ugly permalink exactly like any other redirect, and the same
 * `enabled` / validity-window / approval gates apply.
 *
 * The lookup is guarded behind the presence of a numeric WordPress id parameter,
 * so ordinary traffic never pays for reading the redirects global.
 *
 * The destination is taken from the stored row verbatim, matching what the serving
 * template does for every other redirect: a redirect's whole purpose can be to send
 * a visitor off-site, so an absolute target is legitimate here. The trust boundary
 * is the store, not this class — a generated row's target is always an entity's own
 * Statamic URL, and a hand-authored one passes through the CP approval gate.
 */
class RedirectWordPressQueryPermalinks
{
    /**
     * The query parameters WordPress resolves an ugly permalink from.
     *
     * @var list<string>
     */
    private const array ID_PARAMETERS = ['p', 'page_id'];

    /**
     * Response codes that abort with a status rather than move the visitor,
     * mirroring the targetless branch of the serving template.
     *
     * Deliberately the same set as {@see RespondWithStatus::SITE_RENDERED_CODES},
     * because both must raise the *same* exception: the moment an
     * `errors/<code>.antlers.html` view exists, a bare `abort()` resolves it
     * through Laravel's `response()->view()`, which applies no layout and answers
     * with an unstyled fragment. Adding a code to one set without the other is
     * what makes that regression silent (UAMS-Web/uams-statamic-archive#1942).
     *
     * @var list<string>
     */
    private const array ABORT_RESPONSES = ['403', '410', '451'];

    /**
     * @param  MarketingRedirectStore  $store  Reads the shared redirect rules
     */
    public function __construct(private readonly MarketingRedirectStore $store) {}

    /**
     * Redirect a WordPress ugly permalink to its migrated entry, else continue.
     *
     * @param  Request  $request  The incoming request
     * @param  Closure(Request): Response  $next  The rest of the pipeline
     * @return Response The redirect, or the pipeline's own response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $postId = $this->wordPressPostId($request);

        // Not an ugly permalink: leave the request completely untouched, without
        // reading the redirects global.
        if ($postId === null) {
            return $next($request);
        }

        $row = $this->servableRow(StructuralRedirectSynthesizer::queryPermalinkSource($postId));

        // No rule for this id — the post was never imported, or an editor disabled
        // its rule. Fall through so the request renders as it otherwise would.
        if ($row === null) {
            return $next($request);
        }

        $response = $this->responseCode($row['response'] ?? null);

        // A targetless disposition (an editor declaring the id permanently gone)
        // aborts with that status, as the 404 template does for path redirects —
        // and through the same exception, so this surface gets the site's own error
        // page carrying the rule's message rather than a layout-less fragment
        // (UAMS-Web/uams-statamic-archive#1942). A bare `abort()` here would resolve the new `errors/<code>`
        // view through Laravel's error path, which applies no layout.
        if (\in_array($response, self::ABORT_RESPONSES, true)) {
            throw new SiteHttpException(
                (int) $response,
                $this->stringValue($row['message'] ?? null) ?? ''
            );
        }

        $target = $this->stringValue($row['target_url'] ?? null);

        // A move with nowhere to move to is not servable; render normally instead.
        if ($target === null) {
            return $next($request);
        }

        return redirect()->to($target, $this->redirectStatus($response));
    }

    /**
     * Read the WordPress post id this request is an ugly permalink for.
     *
     * @param  Request  $request  The incoming request
     * @return int|null The positive numeric id, or null when this is not an ugly permalink
     */
    private function wordPressPostId(Request $request): ?int
    {
        // Only a read can be an ugly permalink; a POST carrying `p` is something else.
        if (! $request->isMethodSafe()) {
            return null;
        }

        foreach (self::ID_PARAMETERS as $parameter) {
            $value = $request->query($parameter);

            // Reject an array parameter (`?p[]=1`) outright — it is not an id.
            if (! \is_string($value)) {
                continue;
            }

            // Reject anything that is not a bare positive integer — a slug, a
            // padded value, or `0` is not a WordPress post id and must not hit
            // the store.
            if (! ctype_digit($value)) {
                continue;
            }

            $id = (int) $value;

            if ($id > 0) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Find the stored rule for a source, if one is currently allowed to serve.
     *
     * Applies the same three gates {@see FindRedirectRule} applies on the 404 path
     * — enabled, inside its validity window, and approved — so a rule withheld
     * there is withheld here too.
     *
     * Reads the shared default-site list, which is where the generator writes and
     * where the non-localizable `redirects` field is edited (UAMS-Web/uams-statamic-archive#1110). A per-site
     * localization that overrides an ugly permalink for one site alone is therefore
     * not consulted here; no generated rule is shaped that way.
     *
     * @param  string  $source  The canonical source to match exactly
     * @return array<string, mixed>|null The matching servable row, or null when there is none
     */
    private function servableRow(string $source): ?array
    {
        foreach ($this->store->all() as $row) {
            if (($row['source'] ?? null) !== $source) {
                continue;
            }

            if (! ($row['enabled'] ?? true)) {
                continue;
            }

            if (! ValidityWindow::fromRow($row)->contains(CarbonImmutable::now())) {
                continue;
            }

            if (! RedirectStatus::fromRow($row)->servesTraffic()) {
                continue;
            }

            return $row;
        }

        return null;
    }

    /**
     * Read a row's configured response as a string, defaulting to a permanent move.
     *
     * The globals editor stores the response as a string, but a hand-edited or
     * re-serialized row can carry it as an integer — the serving template's loose
     * Antlers comparison tolerates both, so this does too rather than silently
     * treating a numeric `410` as an unrecognized response.
     *
     * @param  mixed  $value  The row's raw `response` value
     * @return string The response code, or '301' when the row carries none
     */
    private function responseCode(mixed $value): string
    {
        if (\is_int($value)) {
            return (string) $value;
        }

        return $this->stringValue($value) ?? '301';
    }

    /**
     * Coerce a stored redirect response to a usable HTTP redirect status.
     *
     * @param  string  $response  The row's configured response
     * @return int The redirect status to send (falling back to a permanent move)
     */
    private function redirectStatus(string $response): int
    {
        // Only the genuine redirect statuses are honored; anything else stored on
        // the row falls back to the permanent move these rules are generated as.
        return \in_array($response, ['301', '302', '307', '308'], true)
            ? (int) $response
            : 301;
    }

    /**
     * Coerce a loose row value to a non-empty string, or null.
     *
     * @param  mixed  $value  The raw row value
     * @return string|null The trimmed string, or null when empty/non-string
     */
    private function stringValue(mixed $value): ?string
    {
        if (! \is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
