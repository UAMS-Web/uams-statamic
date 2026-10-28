<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Redirects\RedirectDomainRules;
use Closure;
use Illuminate\Http\Middleware\TrustHosts as BaseTrustHosts;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a request for an address the site does not serve (UAMS-Web/uams-statamic-archive#3014).
 *
 * UAMS-Web/uams-statamic-archive#2873 decided that the application refuses unknown addresses itself (A1) and
 * that links then follow the requested address (B2). A request's `Host` header is
 * written by whoever sends it, and Laravel builds full links from it, so without
 * this guard a Control Panel password reset requested under a made-up address
 * would email a link on that address.
 *
 * The allowed hosts are:
 *
 *   - the application URL's host and all of its subdomains;
 *   - the site's other domains, listed by hand until #259 records them where this
 *     class can read them;
 *   - every published entry in the `redirect_domains` collection, read on each
 *     request, so a domain published after boot is allowed with no deploy.
 *
 * A redirect domain is matched exactly. Its `www.` form is not allowed unless the
 * collection names that form too: `ServeRedirectDomainRequests` answers only the
 * hosts the collection names, so an allowed `www.` form it does not name would
 * pass through to the main site and render site content under that address.
 *
 * A request for any other host is refused with a 400, here, before any later
 * middleware, route or redirect rule runs. Laravel's guard only records the
 * allowed hosts and leaves the refusal to the first read of the host; this class
 * reads it straight away so the refusal does not depend on what runs next.
 *
 * Like Laravel's, the guard is off in local development and in tests.
 */
class TrustHosts extends BaseTrustHosts
{
    /**
     * The site's other domains, each matched exactly.
     *
     * Listed by hand until #259 records the site domains where this class can
     * read them; replace this list with that read when it lands.
     *
     * @var list<string>
     */
    public const array SITE_DOMAINS = ['uamshealth.com'];

    /**
     * The host patterns a request may name.
     *
     * @return list<string> The patterns, as `Request::setTrustedHosts()` reads them.
     */
    #[\Override]
    public function hosts(): array
    {
        // Allow the application URL's host and all of its subdomains
        $patterns = [];
        $appUrl = $this->allSubdomainsOfApplicationUrl();

        if (\is_string($appUrl)) {
            $patterns[] = $appUrl;
        }

        // Allow the site's other domains and every published redirect domain, exactly
        foreach ([...self::SITE_DOMAINS, ...RedirectDomainRules::publishedHosts()] as $host) {
            $patterns[] = self::exactPattern($host);
        }

        return $patterns;
    }

    /**
     * Record the allowed hosts and refuse a request for any other.
     *
     * @param  Request  $request  The incoming request.
     * @param  Closure(Request): Response  $next  The rest of the pipeline.
     * @return Response The pipeline's response.
     */
    #[\Override]
    public function handle(Request $request, $next): Response
    {
        if ($this->shouldSpecifyTrustedHosts()) {
            Request::setTrustedHosts($this->hosts());

            // Read the host now, so an untrusted one is refused before anything else
            // runs; the read throws, and the exception handler answers with a 400
            $request->getHost();
        }

        return $next($request);
    }

    /**
     * A pattern that matches one host and nothing else.
     *
     * The host's dots match only dots, and the anchors keep a longer name that
     * contains the host, or ends with it after another label, from matching.
     *
     * @param  string  $host  The host.
     * @return string The pattern.
     */
    public static function exactPattern(string $host): string
    {
        return '^'.preg_quote($host, '/').'$';
    }
}
