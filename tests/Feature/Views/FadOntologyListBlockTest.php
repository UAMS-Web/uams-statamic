<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * Guards the shared Find-a-Doc block dispatcher and the now-complete retirement of
 * the shared ontology-list partial.
 *
 * UAMS-Web/uams-statamic-archive#889 consolidated three near-duplicate Find-a-Doc blocks onto one parameterized
 * partial, `_block_fad_ontology_list.antlers.html`. That partial has since been
 * retired: every Find-a-Doc block is now bespoke — Locations (UAMS-Web/uams-statamic-archive#63) a card
 * directory, Areas of Expertise (UAMS-Web/uams-statamic-archive#62) a list-only card grid, and Clinical Resources
 * (UAMS-Web/uams-statamic-archive#61) a card grid with the UAMS-Web/uams-statamic-archive#1242 type facet. This test now guards two things that
 * outlived the partial: the dispatcher's dead-wrapper fix (UAMS-Web/uams-statamic-archive#889), and that the
 * retired partial is gone and unreferenced (the migration did not leave a dangling
 * `partial:` include).
 *
 * @command php artisan test --compact tests/Feature/Views/FadOntologyListBlockTest.php
 */

/** Absolute path to the page_builder view directory. */
function fadOntologyViewDir(): string
{
    $path = mustNotBeFalse(realpath(__DIR__.'/../../../resources/views/page_builder'), 'path');

    return $path;
}

/** Read a page_builder partial's raw source. */
function fadOntologyRead(string $relative): string
{
    $contents = mustNotBeFalse(file_get_contents(fadOntologyViewDir().'/'.$relative), 'contents');

    return $contents;
}

it('drops the dead dispatcher wrappers while keeping the real wrapper chain', function () {
    $src = fadOntologyRead('_uamsweb_block_fad.antlers.html');

    // The two non-existent partials are gone (they 500'd the block on render, UAMS-Web/uams-statamic-archive#889).
    expect($src)->not->toContain('refactoring/remove-empty-lines')
        ->and($src)->not->toContain('syntax-highlighting/code-block');

    // The real wrapper chain (matching the working people block) is intact.
    expect($src)->toContain('partial:page_builder/uamsweb_block')
        ->and($src)->toContain('partial:page_builder/components/container_row')
        ->and($src)->toContain('partial:page_builder/components/block_header');
});

it('retires the shared ontology-list partial with no block left referencing it', function () {
    // The partial file is gone.
    expect(file_exists(fadOntologyViewDir().'/components/_block_fad_ontology_list.antlers.html'))->toBeFalse();

    // No Find-a-Doc block still delegates to it (a dangling include would 500 on render).
    foreach ([
        '_uamsweb_block_fad_clinical_resources',
        '_uamsweb_block_fad_expertise',
        '_uamsweb_block_fad_locations',
        '_uamsweb_block_fad_providers',
    ] as $block) {
        expect(fadOntologyRead($block.'.antlers.html'))->not->toContain('block_fad_ontology_list');
    }
});

it('aligns each Find-a-Doc filter to its clinical_* collection, with no pre-rename short name left (#1353)', function () {
    // Each block wrapper passes the `clinical_*` collection handle it renders to the
    // filter — realigned from the pre-rename short names (UAMS-Web/uams-statamic-archive#973 / UAMS-Web/uams-statamic-archive#957 / UAMS-Web/uams-statamic-archive#1054).
    $wrappers = [
        '_uamsweb_block_fad_providers' => 'clinical_providers',
        '_uamsweb_block_fad_locations' => 'clinical_locations',
        '_uamsweb_block_fad_clinical_resources' => 'clinical_resources',
    ];
    foreach ($wrappers as $block => $handle) {
        expect(fadOntologyRead($block.'.antlers.html'))->toContain('fad_filter_collection="'.$handle.'"');
    }

    // The filter partial's `switch` discriminators use the same `clinical_*` handles,
    // so each block still surfaces exactly its intended facet fields.
    $filter = fadOntologyRead('components/_block_fad_filter.antlers.html');
    foreach (['clinical_providers', 'clinical_locations', 'clinical_expertise', 'clinical_resources'] as $handle) {
        expect($filter)->toContain("fad_filter_collection == '".$handle."'");
    }

    // No pre-rename short-name value survives in the wrappers or the filter switch —
    // guarding the wrapper value and the switch literals from drifting apart again.
    $combined = $filter.implode('', array_map(fn (string $b): string => fadOntologyRead($b.'.antlers.html'), array_keys($wrappers)));
    foreach ([
        'fad_filter_collection="providers"',
        'fad_filter_collection="locations"',
        'fad_filter_collection="clinical-resource"',
        "== 'providers'",
        "== 'locations'",
        "== 'expertise'",
        "== 'clinical-resource'",
    ] as $stale) {
        expect($combined)->not->toContain($stale);
    }
});
