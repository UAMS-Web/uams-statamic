<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * Locks the shape of clinical provider profile eligibility config.
 *
 * @command  php artisan test --compact tests/Feature/Config/ClinicalProviderProfileConfigTest.php
 *
 * No mutable PHP surface under test: this asserts the shape of the
 * `config/clinical_provider_profile.php` array; there is no backing App class.
 */
it('locks the clinical provider profile eligibility config shape', function (): void {
    // Load eligibility rules for people clinical provider profiles
    $config = config('clinical_provider_profile');

    // Narrow the mixed config() return to an array before key/shape assertions
    expect($config)->toBeArray();

    /** @var array<string, mixed> $config */

    // Assert top-level keys exist
    expect($config)->toHaveKey('base');
    expect($config)->toHaveKey('paths');

    // Assert base rules (AND)
    expect($config['base'])->toBe([
        [
            'field' => 'uams_worker_query',
            'operator' => 'equals',
            'value' => true,
        ],
        [
            'field' => 'uams_worker_subtype',
            'operator' => 'equals',
            'value' => 'employee',
        ],
        [
            'field' => 'uams_employee_subtype',
            'operator' => 'equals',
            'value' => 'regular',
        ],
        [
            'field' => 'resident_query',
            'operator' => 'equals',
            'value' => false,
        ],
        [
            'field' => 'fellow_query',
            'operator' => 'equals',
            'value' => false,
        ],
        [
            'field' => 'clinical_role_query',
            'operator' => 'equals',
            'value' => true,
        ],
        [
            'field' => 'direct_patient_care_query',
            'operator' => 'equals',
            'value' => true,
        ],
        [
            'field' => 'clinical_profile_disable_query',
            'operator' => 'not_equals',
            'value' => true,
        ],
        [
            'field' => 'clinical_locations',
            'operator' => 'has_published_entry',
        ],
    ]);

    // Narrow the paths sub-config to an array before indexing
    $paths = $config['paths'];
    expect($paths)->toBeArray();

    /** @var array<string, mixed> $paths */

    // Assert Path A (appointments; the location and opt-out rules are base rules since UAMS-Web/uams-statamic-archive#3123)
    expect($paths['appointments_and_locations'])->toBe([
        [
            'field' => 'appointments_query',
            'operator' => 'equals',
            'value' => true,
        ],
        [
            'field' => 'appointments_individual_query',
            'operator' => 'equals',
            'value' => true,
        ],
    ]);

    // Assert Path B (credential slugs)
    expect($paths['clinical_credentials'])->toBe([
        [
            'field' => 'credentials',
            'operator' => 'contains_any',
            'values' => [
                'md',
                'm-d',
                'do',
                'd-o',
                'dpm',
                'd-p-m',
                'od',
                'o-d',
                'dds',
                'd-d-s',
                'dmd',
                'd-m-d',
                'cnm',
                'c-n-m',
            ],
        ],
    ]);
});
