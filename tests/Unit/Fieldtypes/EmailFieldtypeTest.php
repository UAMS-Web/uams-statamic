<?php

declare(strict_types=1);

use App\Fieldtypes\Email;

/**
 * @covers \App\Fieldtypes\Email
 *
 * @command  php artisan test --compact tests/Unit/Fieldtypes/EmailFieldtypeTest.php
 */
it('validates its content with the email rule', function (): void {
    // The whole point of the fieldtype over a plain Input: it constrains the value
    // to a valid email address.
    expect((new Email)->rules())->toBe(['email']);
});

it('renders through the dedicated email component and icon', function (): void {
    $email = new Email;

    expect($email->component())->toBe('email')
        ->and($email->icon())->toBe('mail-sign-at');
});
