<?php

declare(strict_types=1);

namespace App\Search;

use App\Http\Middleware\SecurityHeaders;
use UAMSWeb\Aether\Search\Contracts\ResponseAllowance;

/**
 * Lifts this application's content security policy for one response that renders
 * Google's results.
 *
 * The policy stays here: `config/security-headers.php` holds what the allowance
 * grants, so the provider's declared needs are not read.
 */
final class SecurityHeadersResponseAllowance implements ResponseAllowance
{
    /**
     * {@inheritDoc}
     */
    public function raise(string $provider, array $needs): void
    {
        if ($provider !== 'google') {
            return;
        }

        SecurityHeaders::allowOnThisResponse(SecurityHeaders::GOOGLE_SEARCH);
    }
}
