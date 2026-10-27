<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Fields\Field;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

/**
 * Structural + wiring coverage for the per-post boilerplate selector (UAMS-Web/uams-statamic-archive#1184):
 * a `boilerplates` taxonomy term blueprint (heading + Bard content), a per-post
 * `boilerplate` picker on every post blueprint, the site-default picker on the
 * multisite global (root gate relaxed), and the show/PDF templates resolving the
 * selected term with a fall back to the site default.
 *
 * The runtime term-resolution + fallback is exercised behaviorally by
 * PostsReadingMetricsServiceTest (word counts sourced from the resolved term).
 *
 * @command  php artisan test --compact tests/Feature/BoilerplateSelectorTest.php
 */
uses(TestCase::class);

dataset('post_blueprints', ['post', 'news_release', 'audio', 'video']);

/**
 * The boilerplates term blueprint fields, keyed by handle.
 *
 * @return array<string, array<string, mixed>>
 */
function boilerplateTermFields(): array
{
    /** @var non-empty-string $path */
    $path = realpath(__DIR__.'/../../resources/blueprints/taxonomies/boilerplates/boilerplate.yaml');
    /** @var array{tabs: list<array{sections: list<array{fields: list<array<string, mixed>>}>}>} $bp */
    $bp = Yaml::parseFile($path);

    $keyed = [];
    foreach ($bp['tabs'] as $tab) {
        foreach ($tab['sections'] as $section) {
            foreach ($section['fields'] as $field) {
                /** @var string $handle */
                $handle = $field['handle'];
                $keyed[$handle] = $field;
            }
        }
    }

    return $keyed;
}

it('defines a boilerplates term blueprint with a heading and Bard content (#1184)', function (): void {
    $fields = boilerplateTermFields();

    /** @var array{type: string} $headingField */
    $headingField = $fields['heading']['field'];
    expect($headingField['type'])->toBe('text')
        ->and($fields['boilerplate']['field'])->toBe('uamsweb_common.bard_boilerplate');
});

it('adds a boilerplate term picker to every post blueprint (#1184)', function (string $handle): void {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('collections/posts')->get($handle);

    /** @var Field $field */
    $field = $blueprint->field('boilerplate');
    expect($field->type())->toBe('terms_search_index')
        ->and($field->config()['taxonomies'])->toBe(['boilerplates'])
        ->and($field->config()['max_items'])->toBe(1);
})->with('post_blueprints');

it('makes the multisite global boilerplate a boilerplates picker with no root gate (#1184)', function (): void {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('globals')->get('uams_multisite_settings');
    /** @var Field $field */
    $field = $blueprint->field('boilerplate');

    expect($field->type())->toBe('terms_search_index')
        ->and($field->config()['taxonomies'])->toBe(['boilerplates'])
        // The former `if_any: subject contains_any root` gate is gone so a
        // non-root site (e.g. news) can set its own default boilerplate.
        ->and($field->config())->not->toHaveKey('if_any')
        ->and($field->config())->not->toHaveKey('if');
});

it('resolves the selected boilerplate term with a site-default fallback in every template (#1184)', function (string $template): void {
    $source = file_get_contents(resource_path("views/{$template}"));

    // Selected per-post term wins; the multisite global term is the fallback.
    expect($source)->toContain('boilerplate ? boilerplate:heading : uams_multisite_settings:boilerplate:heading')
        ->and($source)->toContain('boilerplate ? boilerplate:boilerplate : uams_multisite_settings:boilerplate:boilerplate')
        // The retired global fields must no longer be referenced.
        ->and($source)->not->toContain('uams_multisite_settings:boilerplate_heading');
})->with([
    'posts/post.antlers.html',
    'posts/news_release.antlers.html',
    'posts/audio.antlers.html',
    'posts/video.antlers.html',
    'pdf/news_release.antlers.html',
]);

it('enables the boilerplates taxonomy on every site the posts collection spans (#1184)', function (): void {
    // The general boilerplate renders on posts across the multisite. terms_search_index
    // augmentation site-filters (`where('site', $site)`), so a term only resolves on a
    // site the taxonomy is enabled on; if the taxonomy omits a posts site, the default
    // boilerplate augments to null there and the block renders empty. Guard that.
    /** @var Illuminate\Support\Collection<int, string>|null $postSitesCollection */
    $postSitesCollection = Collection::findByHandle('posts')?->sites();
    $postSites = $postSitesCollection?->all() ?? [];

    // Needs the content submodule (collection + taxonomy configs); skip if absent.
    if ($postSites === []) {
        ContentSubmodule::reportMissing('posts collection sites');
    }

    /** @var Statamic\Taxonomies\Taxonomy|null $boilerplatesTaxonomy */
    $boilerplatesTaxonomy = Taxonomy::findByHandle('boilerplates');
    /** @var Illuminate\Support\Collection<int, string>|null $taxSitesCollection */
    $taxSitesCollection = $boilerplatesTaxonomy?->sites();
    $taxSites = $taxSitesCollection?->all() ?? [];
    $missing = array_values(array_diff($postSites, $taxSites));

    expect($missing)->toBe([], 'boilerplates taxonomy is missing posts sites: '.implode(', ', $missing));
});

it('parses and resolves the selected-term / site-default ternary in both branches (#1184)', function (): void {
    // The template packs the ternary colon and the field-access colon into one
    // expression (`a ? a:b : c:d:e`); prove Antlers parses it and picks the right
    // branch rather than silently mis-tokenizing the colons.
    $expr = '{{ boilerplate ? boilerplate:heading : uams_multisite_settings:boilerplate:heading }}';

    // A selected per-post boilerplate wins.
    $selected = Antlers::parse($expr, [
        'boilerplate' => ['heading' => 'Override Heading'],
        'uams_multisite_settings' => ['boilerplate' => ['heading' => 'Default Heading']],
    ]);
    expect(trim((string) $selected))->toBe('Override Heading');

    // An empty selection falls back to the multisite site default.
    $fallback = Antlers::parse($expr, [
        'boilerplate' => null,
        'uams_multisite_settings' => ['boilerplate' => ['heading' => 'Default Heading']],
    ]);
    expect(trim((string) $fallback))->toBe('Default Heading');
});
