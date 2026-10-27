<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Locks the UAMS-Web/uams-statamic-archive#1059 marketing-palette + CTA-inheritance wiring on the AoE blueprint:
 * the `page_builder` field is the curated Replicator palette (not the Bard-only
 * `uamsweb_common_fad.page_builder` alias), and a per-entry CTA-inheritance opt-out
 * toggle is present alongside the authored `cta` field.
 *
 * @command  php artisan test --compact tests/Feature/Blueprints/ClinicalExpertisePaletteTest.php
 */
beforeEach(function () {
    $bp = mustBe(Statamic\Fields\Blueprint::class, Blueprint::find('collections.clinical_expertise.clinical_expertise'), 'bp');
    $this->bp = $bp;
    $this->fields = $bp->fields()->all();
});

it('exposes the marketing page-builder palette (a Replicator), not the Bard-only alias', function () {
    /** @var Collection<string, Field> $fields */
    $fields = $this->fields;
    $pageBuilder = $fields->get('page_builder');

    $pageBuilder = mustBe(Field::class, $pageBuilder, 'page builder');
    // The imported palette is a Replicator; the replaced alias was a Bard field.
    expect($pageBuilder->type())->toBe('replicator');
});

it('keeps the authored CTA field and adds a per-entry inheritance opt-out toggle', function () {
    /** @var Collection<string, Field> $fields */
    $fields = $this->fields;
    expect($fields->get('cta'))->not->toBeNull();

    $optOut = mustBe(Field::class, $fields->get('cta_inherit_disabled'), 'opt out');
    expect($optOut->config()['display'] ?? '')->toContain('inherit');
});
