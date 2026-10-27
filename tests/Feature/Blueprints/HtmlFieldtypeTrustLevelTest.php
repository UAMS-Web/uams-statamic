<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/*
 * Pins the trust level of the `html` fieldtype surfaces in this repo (UAMS-Web/uams-statamic-archive#1742).
 *
 * The importer's Tiptap allowlist keeps unsafe markup out of Bard content, but
 * it does not cover `type: html` fields, which bypass that path entirely. The
 * audit behind UAMS-Web/uams-statamic-archive#1742 established that they are not an editor-authored surface:
 *
 *   - Statamic's `html` fieldtype is CP-display-only and persists nothing —
 *     "This is for display purposes only, the HTML will not be saved"
 *     (`vendor/statamic/cms/lang/en/fieldtypes.php`, `html.config.html_instruct`).
 *   - Its markup comes from the blueprint YAML's `html:` key, so authoring one
 *     requires the `configure fields` permission, which NO role in
 *     resources/users/roles.yaml grants — super-admins only.
 *   - Sanitization is on unless a field explicitly opts out.
 *
 * Two things could quietly invalidate that and are therefore asserted rather
 * than left as prose: a field turning sanitization off, and a role gaining
 * `configure fields`. The ticket described "three" such fields; the audit found
 * 16, so these assert the properties rather than a count that churns.
 *
 * Note the deliberately adjacent gap: `configure form fields` IS granted (to
 * `form_editor`) and gates Statamic's FormBlueprintController, so that role can
 * author new `html` fields into FORM blueprints. No form blueprint carries one
 * today, and closing that is tracked separately — see the follow-up on UAMS-Web/uams-statamic-archive#1742.
 *
 * @command php artisan test --compact tests/Feature/Blueprints/HtmlFieldtypeTrustLevelTest.php
 */

/**
 * Every `html`-fieldtype field declared under a blueprint/fieldset YAML root.
 *
 * Blueprints nest fields arbitrarily deep (tabs, sections, grid/replicator sets,
 * imported fieldsets), so this walks the whole parsed tree rather than assuming
 * a shape.
 *
 * @return array<string, array<mixed>> `<file>:<nth>` => the field config
 */
function htmlFieldtypeDeclarations(string $directory): array
{
    $found = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'yaml') {
            continue;
        }

        $parsed = Yaml::parseFile($file->getPathname());

        if (! is_array($parsed)) {
            continue;
        }

        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
        $index = 0;

        $walk = function (array $node) use (&$walk, &$found, &$index, $relative): void {
            if (($node['type'] ?? null) === 'html') {
                $found[$relative.':'.$index++] = $node;
            }

            foreach ($node as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };

        $walk($parsed);
    }

    return $found;
}

it('finds the html fieldtype surfaces it is meant to be auditing', function (): void {
    // Without this the two assertions below would pass vacuously if the scan ever
    // stopped matching (a moved directory, a changed YAML shape).
    expect(htmlFieldtypeDeclarations(resource_path('blueprints')))->not->toBeEmpty();
});

it('never disables sanitization on an html fieldtype', function (): void {
    $optedOut = collect(htmlFieldtypeDeclarations(resource_path('blueprints')))
        ->merge(htmlFieldtypeDeclarations(resource_path('fieldsets')))
        ->filter(fn (array $field): bool => ($field['sanitize'] ?? true) === false)
        ->keys()
        ->all();

    expect($optedOut)->toBe([]);
});

it('grants the blueprint-authoring permission to no role', function (): void {
    $roles = Yaml::parseFile(resource_path('users/roles.yaml'));

    $granted = collect(is_array($roles) ? $roles : [])
        ->filter(function (mixed $role): bool {
            $permissions = is_array($role) ? ($role['permissions'] ?? null) : null;

            return is_array($permissions) && in_array('configure fields', $permissions, true);
        })
        ->keys()
        ->all();

    // `configure fields` is what puts a blueprint's raw `html:` blob in reach. If a
    // role ever gains it, the trust argument above needs re-auditing, not a green
    // test.
    expect($granted)->toBe([]);
});
