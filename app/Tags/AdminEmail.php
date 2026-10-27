<?php

declare(strict_types=1);

namespace App\Tags;

use Statamic\Tags\Tags;

/**
 * Emulates Gravity Forms {admin_email} merge tag.
 *
 * Provides a Statamic tag that returns the admin email address from the mail
 * configuration, matching the behavior of Gravity Forms' {admin_email} merge tag.
 *
 * Usage: {{ admin_email }}
 */
class AdminEmail extends Tags
{
    /**
     * Returns the admin email address from the mail configuration.
     *
     * @return string The admin email address from the mail configuration
     */
    public function index(): string
    {
        // Return the admin email address from the mail configuration
        $address = config('mail.from.address');

        return is_string($address) ? $address : '';
    }
}
