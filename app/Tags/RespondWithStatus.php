<?php

declare(strict_types=1);

namespace App\Tags;

use App\Exceptions\SiteHttpException;
use App\Support\Statamic\Tags\TagParam;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Aborts the current request with an HTTP error status from a redirect rule
 * ({@see FindRedirectRule}).
 *
 * The stock `{{ redirect }}` tag can only *move* a request (301/302) or throw a
 * 404 — it has no way to emit a `410 Gone` (or `403`) for a rule that has no
 * destination. The migration redirect generator (UAMS-Web/uams-statamic-archive#1641) needs exactly that:
 * retired content with no surviving ancestor is declared permanently gone so
 * crawlers drop it rather than chase a soft-404. This tag closes that gap by
 * aborting with the rule's response code.
 *
 * Usage in the 404 redirects partial:
 *
 *     {{ respond_with_status code="410" message="{message}" }}
 *
 * Only the targetless error codes the redirects blueprint offers are honored
 * (`403`, `404`, `410`, `451`); any other value is ignored so a misconfigured
 * rule can never abort with an arbitrary status.
 */
class RespondWithStatus extends Tags
{
    /**
     * The error responses this tag is allowed to emit — the targetless codes the
     * redirects blueprint exposes. A move (3xx) is the `{{ redirect }}` tag's job.
     *
     * @var list<int>
     */
    private const array ALLOWED_CODES = [403, 404, 410, 451];

    /**
     * The codes raised as a {@see SiteHttpException}, so the visitor gets the
     * site's own error page carrying the rule's message rather than the
     * framework's fallback (UAMS-Web/uams-statamic-archive#1942).
     *
     * `404` is deliberately absent. `errors/404.antlers.html` includes
     * `errors/_redirects`, so rendering it through Statamic's view re-runs the
     * very rule that raised the error — unbounded recursion, where a bare
     * `abort(404)` merely bottoms out inside Laravel's try/catch. That partial's
     * `404` branch is inert for the same reason (UAMS-Web/uams-statamic-archive#1934), which means this tag is
     * never reached with `404` from there; the exclusion keeps that true for any
     * future caller rather than relying on the branch staying inert.
     *
     * @var list<int>
     */
    private const array SITE_RENDERED_CODES = [403, 410, 451];

    /**
     * The {{ respond_with_status }} tag.
     *
     * Aborts the request with the given error status (and optional message) when
     * it is one of the allowed targetless codes; otherwise does nothing.
     */
    public function index(): void
    {
        /** @var Parameters $params */
        $params = $this->params;

        // Resolve the requested status code; a non-numeric value yields 0 (ignored).
        $codeParam = TagParam::get($params, 'code', '0');
        $code = \is_numeric($codeParam) ? (int) $codeParam : 0;

        // Only emit the small set of allowed targetless error codes.
        if (! \in_array($code, self::ALLOWED_CODES, true)) {
            return;
        }

        // Resolve the optional human-readable message for the error response.
        $messageParam = TagParam::get($params, 'message', '');
        $message = \is_string($messageParam) ? $messageParam : '';

        // Render the site's own error page for the codes that have one, so the
        // message reaches the visitor. Statamic's `View` is the only thing that
        // applies the site layout — Laravel's error path renders a view with none.
        if (\in_array($code, self::SITE_RENDERED_CODES, true)) {
            throw new SiteHttpException($code, $message);
        }

        // Abort the request with the resolved status, ending the render.
        abort($code, $message);
    }
}
