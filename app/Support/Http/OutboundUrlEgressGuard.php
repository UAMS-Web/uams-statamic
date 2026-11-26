<?php

declare(strict_types=1);

namespace App\Support\Http;

use App\Providers\MapsServiceProvider;
use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Override;
use RuntimeException;
use UAMSWeb\Aether\Fieldtypes\Contracts\EgressGuard;

/**
 * The site's egress guard for the shared fieldtype add-on.
 *
 * The add-on's GPS address-search endpoint fetches its configured geocoding service through the
 * {@see EgressGuard} bound in the container. This adapter answers that contract with
 * {@see OutboundUrlGuard}, so the endpoint keeps the site's SSRF protection: every resolved address
 * is vetted, the connection is pinned to the vetted address, and each redirect hop is checked again.
 *
 * @see MapsServiceProvider Where the binding is made
 */
final class OutboundUrlEgressGuard implements EgressGuard
{
    /**
     * The verbs {@see OutboundUrlGuard::fetch()} can send.
     *
     * @var list<string>
     */
    private const array METHODS = ['GET', 'HEAD'];

    /**
     * Decide, before any connection, whether the URL may be fetched.
     *
     * @param  string  $url  The fully-qualified URL about to be fetched
     * @return bool True when every address the host resolves to is public
     */
    #[Override]
    public function isAllowedUrl(string $url): bool
    {
        return OutboundUrlGuard::isAllowedUrl($url);
    }

    /**
     * Perform the request through the site's guard.
     *
     * @param  string  $method  The HTTP verb, `GET` or `HEAD`
     * @param  string  $url  The fully-qualified URL
     * @param  (Closure(PendingRequest): PendingRequest)|null  $configure  Hook that adds headers and timeouts to the pending request
     * @return Response The upstream response
     *
     * @throws RuntimeException When the verb is not one the guard sends, or the guard refuses the URL
     */
    #[Override]
    public function fetch(string $method, string $url, ?Closure $configure = null): Response
    {
        // Refuse a verb the site's guard would otherwise send as a GET
        $method = strtoupper($method);
        if (! in_array($method, self::METHODS, true)) {
            throw new RuntimeException(sprintf('The egress guard does not send %s requests.', $method));
        }

        return OutboundUrlGuard::fetch($method, $url, $configure);
    }
}
