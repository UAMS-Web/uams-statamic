<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Assert;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Structural coverage for the Departmental News Hub view
 * (`resources/views/pages/department_hub.antlers.html`, UAMS-Web/uams-statamic-archive#1602) and its blueprint.
 *
 * The tag behavior (org-filtered magazine, leadership, degrade) is covered in
 * `DepartmentHubTagTest`; this guards the view wiring — curated zones, magazine,
 * people zone, degrade path — against the template source.
 *
 * @command  php artisan test --compact tests/Feature/Views/DepartmentHubViewTest.php
 */

/** Read a resources template's source. */
function deptHubFile(string $relative): string
{
    return File::get(resource_path($relative));
}

/**
 * Read a nested list out of a parsed-YAML node, tolerating any non-list shape.
 *
 * @return array<int|string, mixed>
 */
function deptHubList(mixed $node, string $key): array
{
    if (! \is_array($node)) {
        return [];
    }

    $value = $node[$key] ?? [];

    return \is_array($value) ? $value : [];
}

it('drives the hub through the department_hub tag with an is_hub branch', function (): void {
    $template = deptHubFile('views/pages/department_hub.antlers.html');

    expect($template)
        ->toContain('{{ department_hub }}')
        ->toContain('{{ if is_hub }}')
        ->toContain('{{ lead }}')
        ->toContain('{{ sections }}')
        ->toContain('partial:posts/card show_primary_category="true"');
});

it('renders the curated hero and Action-Path links, sanitized', function (): void {
    $template = deptHubFile('views/pages/department_hub.antlers.html');

    expect($template)
        ->toContain('{{ hero_mission | sanitize }}')
        ->toContain('{{ action_path_links }}')
        // Action-Path link fields are editor-controlled and rendered into text/href.
        // The href uses `sanitize_url`, not `sanitize`: the latter only entity-encodes
        // and would let a `javascript:` scheme through (UAMS-Web/uams-statamic-archive#1500).
        ->toContain('{{ url | sanitize_url }}')
        ->toContain('{{ label | sanitize }}')
        // Section headings come from config/editor input, so they are escaped too.
        ->toContain('{{ heading | sanitize }}');
});

it('renders the derived leadership people zone, guarded by has_people', function (): void {
    $template = deptHubFile('views/pages/department_hub.antlers.html');

    expect($template)
        ->toContain('has_people')
        ->toContain('{{ leadership }}')
        ->toContain('{{ title | sanitize }}');
});

it('degrades to the base-rung posts listing when not a hub', function (): void {
    $template = deptHubFile('views/pages/department_hub.antlers.html');

    expect($template)
        ->toContain('{{ else }}')
        ->toContain('partial:posts/listing');
});

it('defines a department_hub blueprint with the hub template and curated fields', function (): void {
    $blueprint = deptHubFile('blueprints/collections/pages/department_hub.yaml');

    expect($blueprint)
        ->toContain('pages/department_hub')
        ->toContain('hero_mission')
        ->toContain('action_path_links')
        ->toContain('lead_count')
        ->toContain('archive_cta_url');
});

it('adds the one-per-site department_hub_page designation to the multisite global', function (): void {
    // Parse the YAML and assert on the field's OWN config — a bare `max_items: 1`
    // string match would pass on any of the ten other single-entry fields in this file.
    $global = Yaml::parse(deptHubFile('blueprints/globals/uams_multisite_settings.yaml'));
    Assert::assertIsArray($global);

    $field = null;

    foreach (deptHubList($global, 'tabs') as $tab) {
        foreach (deptHubList($tab, 'sections') as $section) {
            foreach (deptHubList($section, 'fields') as $candidate) {
                if (\is_array($candidate) && ($candidate['handle'] ?? null) === 'department_hub_page') {
                    $field = $candidate['field'] ?? null;
                }
            }
        }
    }

    Assert::assertIsArray($field, 'department_hub_page is not defined on the multisite global.');

    expect($field['type'])->toBe('entries')
        // One per site by construction, pointing at a pages entry.
        ->and($field['max_items'])->toBe(1)
        ->and($field['collections'])->toBe(['pages'])
        ->and($field['localizable'])->toBeTrue();
});
