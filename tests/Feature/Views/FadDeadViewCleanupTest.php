<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * Guards the 2026-07-06 "Conditions & treatments are NOT published as pages"
 * decision (`docs/DECISIONS.md`): the non-routable `clinical_conditions` and
 * `clinical_services` collections have no front-end views, so their orphaned
 * empty view stubs and the dead template-name entries in the layout's
 * Find-a-Doc SCSS asset gate must stay removed. (UAMS-Web/uams-statamic-archive#1460)
 */
it('has removed the orphaned clinical_services and condition view stubs', function (string $stub) {
    expect(is_file(resource_path("views/{$stub}")))->toBeFalse();
})->with([
    'clinical_services/index.antlers.html',
    'clinical_services/show.antlers.html',
    'condition/index.antlers.html',
    'condition/show.antlers.html',
]);

it('no longer lists the non-routable clinical_conditions/clinical_services templates in the layout FaD asset gate', function (string $template) {
    expect(file_get_contents(resource_path('views/layout.antlers.html')))->not->toContain($template);
})->with([
    'clinical_conditions/index',
    'clinical_conditions/show',
    'clinical_services/index',
    'clinical_services/show',
]);
