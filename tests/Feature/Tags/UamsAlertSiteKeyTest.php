<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Site;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Wiring coverage for the `{{ uams_alert_site_key }}` tag (UAMS-Web/uams-statamic-archive#299): it renders the
 * current site's alert bucket from `config/uamsalert.php` via
 * `App\Support\UamsAlert\AlertSiteKeyResolver`. The detailed mapping cases live
 * in `AlertSiteKeyResolverTest`; this proves the tag is auto-registered and
 * resolves end-to-end against the real config.
 *
 * @covers \App\Tags\UamsAlertSiteKey
 *
 * @command  php artisan test --compact tests/Feature/Tags/UamsAlertSiteKeyTest.php
 */
it('renders the resolved alert bucket for the current site', function () {
    // The shipped config has no buckets, so any site resolves to the `uams`
    // default — the value emitted into the body `data-uams-alert-site` attribute.
    expect((string) Antlers::parse('{{ uams_alert_site_key }}'))->toBe('uams');
});

it('renders a configured bucket for a mapped current site', function () {
    /** @var Statamic\Sites\Site $currentSite */
    $currentSite = Site::current();
    config()->set('uamsalert.buckets', ['uamshealth' => [$currentSite->handle()]]);

    expect((string) Antlers::parse('{{ uams_alert_site_key }}'))->toBe('uamshealth');
});
