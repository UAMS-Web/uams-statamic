<?php

declare(strict_types=1);

use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#1598 — the post→`organizations` association. A multi-value `entries` →
 * `organizations` field (`related_organizations`), scoped to the department /
 * division / service-line org roles, added once to the shared
 * `uamsweb_post_associations` fieldset so it resolves onto all four `posts`
 * blueprints. It is the join key a Departmental News Hub (UAMS-Web/uams-statamic-archive#1602) and its news
 * archive (UAMS-Web/uams-statamic-archive#1599) filter on; the importer crosswalk (`wordpress-importer#438`)
 * seeds it historically.
 *
 * @command  php artisan test --compact tests/Feature/Blueprints/PostRelatedOrganizationsFieldTest.php
 */
it('exposes a multi-value related_organizations entries field scoped to department-unit roles', function (string $blueprintHandle) {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::find($blueprintHandle);
    expect($blueprint)->not->toBeNull();

    /** @var ?Field $field */
    $field = $blueprint->fields()->all()->get('related_organizations');
    expect($field)->not->toBeNull("{$blueprintHandle} is missing related_organizations");

    /** @var Field $field */
    expect($field->type())->toBe('entries');

    $config = $field->config();

    // Targets the organizations collection…
    expect($config['collections'] ?? null)->toBe(['organizations']);

    // …scoped to the shared department/division/service-line role group…
    expect($config['query_scopes'] ?? [])
        ->toContain('entries_by_organization_role_department_unit');

    // …multi-value (no single-item cap)…
    expect($config['max_items'] ?? null)->toBeNull();

    // …and selectable across sites, since organizations live only on `default`
    // while posts live on the `news` and department sites.
    expect($config['select_across_sites'] ?? null)->toBeTrue();
})->with([
    'post' => ['collections.posts.post'],
    'audio' => ['collections.posts.audio'],
    'video' => ['collections.posts.video'],
    'news_release' => ['collections.posts.news_release'],
]);
