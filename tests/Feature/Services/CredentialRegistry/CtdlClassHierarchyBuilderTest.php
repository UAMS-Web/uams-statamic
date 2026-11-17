<?php

declare(strict_types=1);

use App\Services\CredentialRegistry\CtdlClassHierarchyBuilder;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for CtdlClassHierarchyBuilder (CTDL class hierarchy preload maps
 * built from CredentialRegistry enum metadata).
 *
 * Mutation: no unexplained survivors on App\Services\CredentialRegistry\CtdlClassHierarchyBuilder
 * (#886). The guards for a case with no attribute, an empty slug or an unknown child URI,
 * and the two slug sorts, cannot be reached or changed by the generated enum, so each
 * carries `@pest-mutate-ignore` in the source with its reason.
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

it('keeps a class with a ceterms parent out of the top level', function () {
    // Build preload maps from CredentialRegistry.
    $preload = CtdlClassHierarchyBuilder::preload();

    // Extract top-level slugs (option keys).
    $slugs = array_column($preload['top_level'], 'key');

    // Badge is a subclass of ceterms:Credential, so it is listed only as a child.
    expect($slugs)->not->toContain('Badge');
    expect($slugs)->not->toContain('DigitalBadge');
});

it('treats a class whose parent is outside ceterms as top level', function () {
    // Build preload maps from CredentialRegistry.
    $preload = CtdlClassHierarchyBuilder::preload();

    // Extract top-level slugs (option keys).
    $slugs = array_column($preload['top_level'], 'key');

    // ValueProfile is a subclass of schema:QuantitativeValue; Agent has no parent at all.
    expect($slugs)->toContain('ValueProfile');
    expect($slugs)->toContain('Agent');
});

it('records labels for every registry class, including those outside ceterms', function () {
    // Build preload maps from CredentialRegistry.
    $preload = CtdlClassHierarchyBuilder::preload();

    // asn:ProgressionLevel is not a ceterms class, so it reaches only the label map.
    expect($preload['labels_by_slug'])->toHaveKey('ProgressionLevel');
    expect($preload['labels_by_slug']['ProgressionLevel'])->toBe('Progression Level');
    expect($preload['labels_by_slug']['DigitalBadge'])->toBe('Digital Badge');
    expect(array_column($preload['top_level'], 'key'))->not->toContain('ProgressionLevel');
});
