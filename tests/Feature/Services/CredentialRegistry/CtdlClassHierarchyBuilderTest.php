<?php

declare(strict_types=1);

use App\Services\CredentialRegistry\CtdlClassHierarchyBuilder;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for CtdlClassHierarchyBuilder (CTDL class hierarchy preload maps
 * built from CredentialRegistry enum metadata).
 *
 * Mutation: 100% on App\Services\CredentialRegistry\CtdlClassHierarchyBuilder (38 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Services/CredentialRegistry/CtdlClassHierarchyBuilderTest.php
 */
it('includes agent and credential at the top level', function () {
    // Build preload maps from CredentialRegistry.
    $preload = CtdlClassHierarchyBuilder::preload();

    // Extract top-level slugs (option keys).
    $slugs = array_column($preload['top_level'], 'key');

    // Check representative CTDL root classes from the spec.
    expect($slugs)->toContain('Agent');
    expect($slugs)->toContain('Credential');
    expect($slugs)->toContain('AssessmentProfile');
});

it('lists credential subtypes and gives badge a digital badge child', function () {
    // Build preload maps from CredentialRegistry.
    $preload = CtdlClassHierarchyBuilder::preload();

    // Read child option keys for Credential and Badge.
    $byParent = $preload['children_by_shorthand'];
    $credentialChildren = array_column($byParent['ceterms:Credential'] ?? [], 'key');
    $badgeChildren = array_column($byParent['ceterms:Badge'] ?? [], 'key');

    // Assert direct credential subtypes from the CTDL hierarchy.
    expect($credentialChildren)->toContain('Badge');
    expect($credentialChildren)->toContain('Certificate');
    expect($credentialChildren)->toContain('MicroCredential');

    // Assert badge subtypes.
    expect($badgeChildren)->toContain('DigitalBadge');
    expect($badgeChildren)->toContain('OpenBadge');
});

it('uses the slug as the key and the label as the value in option rows', function () {
    // Build preload maps from CredentialRegistry.
    $preload = CtdlClassHierarchyBuilder::preload();

    // Find the DigitalBadge option under Badge.
    $badgeKids = $preload['children_by_shorthand']['ceterms:Badge'] ?? [];
    $digital = collect($badgeKids)->firstWhere('key', 'DigitalBadge');

    // Assert slug/label shape for YAML-friendly storage.
    expect($digital)->not->toBeNull();
    expect(data_get($digital, 'key'))->toBe('DigitalBadge');
    expect(data_get($digital, 'value'))->toBe('Digital Badge');
});
