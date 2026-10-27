<?php

declare(strict_types=1);

use App\Tags\AdminEmail;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

uses(TestCase::class);

covers(AdminEmail::class);

/**
 * Feature tests for the AdminEmail tag (Gravity Forms {admin_email} parity).
 *
 * Mutation: 100% on App\Tags\AdminEmail (2 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Tags/AdminEmailTagTest.php
 */
it('returns the mail from address', function () {
    Config::set(
        'mail.from.address',
        'admin@example.test'
    );

    $tag = new AdminEmail;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('admin@example.test');
});

it('returns an empty string when the mail from address is not a string', function (): void {
    Config::set(
        'mail.from.address',
        null
    );

    $tag = new AdminEmail;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('');
});
