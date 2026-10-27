<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests for resources/views/layout/footer/contact/_contact.antlers.html (global
 * footer direct-contact): the visible phone link text is formatted to AP-style
 * national via `format_phone` (UAMS-Web/uams-statamic-archive#817), while the `tel:` href and the schema
 * `<meta>` keep the raw E.164 value so click-to-call and machine-readable schema
 * still work. The formatting is scoped to the phone link only — fax numbers are
 * left untouched. Antlers template partial; FormatPhone's value logic is owned by
 * tests/Feature/Modifiers/FormatPhoneTest.
 *
 * @command php artisan test --compact tests/Feature/Views/FooterContactPhoneFormatTest.php
 */
/**
 * @param  array<int, array<string, mixed>>  $directContact
 */
function renderFooterContact(array $directContact): string
{
    return (string) Antlers::parse('{{ partial:layout/footer/contact/contact }}', [
        'uams_multisite_settings' => [
            'physical_address' => [],
            'direct_contact' => $directContact,
        ],
    ], true);
}

it('formats the phone link display text while keeping href and meta raw E.164', function () {
    $out = renderFooterContact([[
        'type' => 'phone_number',
        'id' => 'abc123',
        'phone_group' => [
            'title' => 'Main Line',
            'phone' => '+15016867000',
            'phone_contactpointoption' => null,
        ],
    ]]);

    // Visible link text: AP-style national.
    expect($out)->toContain('>501-686-7000</a>');
    // tel: href stays raw E.164 so click-to-call still works.
    expect($out)->toContain('href="tel:+15016867000"');
    // Schema <meta> telephone value stays raw E.164 (machine-readable).
    expect($out)->toContain('<meta content="+15016867000" />');
    // The raw E.164 is never the visible link text.
    expect($out)->not->toContain('>+15016867000</a>');
});

it('leaves fax numbers untouched (format_phone is scoped to the phone link only)', function () {
    $out = renderFooterContact([[
        'type' => 'fax_number',
        'id' => 'def456',
        'fax_group' => [
            'title' => 'Fax',
            'fax' => '+15016867001',
        ],
    ]]);

    // Fax renders the stored value verbatim — not run through format_phone.
    expect($out)->toContain('+15016867001');
    expect($out)->not->toContain('501-686-7001');
});
