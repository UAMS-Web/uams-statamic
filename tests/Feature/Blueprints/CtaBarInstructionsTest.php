<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Structure coverage for the CTA Bar editor instructions (UAMS-Web/uams-statamic-archive#1182): the body field
 * must carry CP guidance that admonishes brief, single-action content and names
 * the better-suited blocks for multi-link layouts. CP-guidance only — no change
 * to the block's rendered output.
 *
 * @command  php artisan test --compact tests/Feature/Blueprints/CtaBarInstructionsTest.php
 */
it('gives the CTA Bar body field brevity instructions naming the alternative blocks', function (): void {
    /** @var array{fields: array<int, array<string, mixed>>} $fieldset */
    $fieldset = Yaml::parse(File::get(resource_path('fieldsets/uamsweb_block_cta.yaml')));

    /** @var array{config?: array<string, mixed>} $body */
    $body = collect($fieldset['fields'])->firstWhere('handle', 'body');
    $instructions = $body['config']['instructions'] ?? '';

    expect($instructions)
        ->toContain('single action')
        ->toContain('two at most')
        ->toContain('Link List')
        ->toContain('Side-by-Side Image & Text');
});
