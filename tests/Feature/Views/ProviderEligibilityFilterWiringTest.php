<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#919 wiring guard: the clinical-provider-eligibility output filter must be present
 * at every surface that renders a provider relationship, so a person listed as a
 * provider anywhere is narrowed to clinical-provider-eligible, searchable `people`.
 *
 * The FaD Providers block filters via a `{{ collection:people }}` tag condition
 * (mirroring `searchable:is`); the authored `entries` relationships filter via the
 * shared `clinical_provider_eligible` modifier. This centralizes the contract so
 * removing the filter from any one call site fails here (the behavior itself is
 * covered by FadProviderEligibilityFilterTest and ClinicalProviderEligibleTest).
 *
 * @command php artisan test --compact tests/Feature/Views/ProviderEligibilityFilterWiringTest.php
 */
it('wires the eligibility filter at every provider-relationship render site', function (string $template, string $needle): void {
    $src = File::get(resource_path($template));

    expect($src)->toContain($needle);
})->with([
    'providers block — collection tag condition' => [
        'views/page_builder/_uamsweb_block_fad_providers.antlers.html',
        'clinical_provider_profile_eligible_query:is="true"',
    ],
    'location profile — providers at this location' => [
        'views/clinical_locations/show.antlers.html',
        'physician_locations | clinical_provider_eligible',
    ],
    'AoE overview — related clinical providers' => [
        'views/clinical_expertise/_related.antlers.html',
        'clinical_providers | clinical_provider_eligible',
    ],
    'AoE routed section — providers' => [
        'views/clinical_expertise/section.antlers.html',
        'clinical_providers | clinical_provider_eligible',
    ],
    'clinical resource — related providers' => [
        'views/clinical_resources/_related.antlers.html',
        'clinical_providers | clinical_provider_eligible',
    ],
]);
