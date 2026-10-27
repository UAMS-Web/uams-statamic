<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * The organization schema template emits `PronounceableText` for `name` when an
 * editor has supplied a spoken form and its IPA, and a plain `Text` string
 * otherwise (UAMS-Web/uams-statamic-archive#2363).
 *
 * The Person half of UAMS-Web/uams-statamic-archive#554 has been live for some time; the organization half was
 * never adopted, so an organization `name` emitted a bare string and a speech
 * engine read an acronym like "UAMS" as a word.
 *
 * These read the Antlers out of the **shipped content file** rather than
 * restating it, so removing the tag call from the template fails here. That is
 * the criterion's own wording -- "fails if the organization template stops
 * emitting `PronounceableText`" -- and a test carrying its own copy of the
 * template could not do it.
 *
 * @command  php artisan test --compact tests/Feature/SchemaJsonLdOrganizationPronounceableNameTest.php
 */
uses(TestCase::class);

const ORGANIZATION_SCHEMA_TEMPLATE = 'content/collections/schema_objects/default/organization-type-base-organization.md';

/**
 * The shipped organization schema template, exactly as it sits on disk.
 */
function organizationSchemaTemplateSource(): string
{
    $path = base_path(ORGANIZATION_SCHEMA_TEMPLATE);

    expect(file_exists($path))->toBeTrue('missing '.ORGANIZATION_SCHEMA_TEMPLATE);

    return (string) file_get_contents($path);
}

/**
 * The YAML front matter of a Statamic entry file.
 *
 * The `---` delimiter may end in CRLF: the `content` submodule carries no
 * `.gitattributes`, so a clone with `core.autocrlf=true` checks it out that way
 * (UAMS-Web/uams-statamic-archive#2517). A multiline `$` does not match before `\r\n`, hence the `\r?`.
 *
 * @return array<string, mixed>
 */
function entryFrontMatter(string $raw): array
{
    $parts = preg_split('/^---\r?$/m', $raw, 3);

    expect($parts)->toBeArray();

    $front = Yaml::parse(\is_array($parts) ? ($parts[1] ?? '') : '');

    expect($front)->toBeArray();

    /** @var array<string, mixed> $front */
    return $front;
}

/**
 * The Antlers source of one property on the organization schema template.
 *
 * @param  string  $property  Schema.org property name, e.g. `name`
 * @return string The template code
 */
function organizationSchemaProperty(string $property): string
{
    $front = entryFrontMatter(organizationSchemaTemplateSource());

    $schema = $front['schema'] ?? [];
    $properties = \is_array($schema) ? ($schema['properties'] ?? []) : [];
    $node = \is_array($properties) ? ($properties[$property] ?? []) : [];
    $value = \is_array($node) ? ($node['value'] ?? []) : [];
    $first = \is_array($value) ? ($value[0] ?? []) : [];
    $inner = \is_array($first) ? ($first['value'] ?? []) : [];
    $code = \is_array($inner) ? ($inner['code'] ?? '') : '';

    expect($code)->toBeString();

    return \is_string($code) ? $code : '';
}

/**
 * Render that property against a set of entry values.
 *
 * @param  array<string, mixed>  $data
 */
function renderOrganizationName(array $data): string
{
    return trim((string) Antlers::parse(organizationSchemaProperty('name'), $data));
}

it('reads the template out of the content file at all', function (): void {
    // Positive control: without this, every assertion below would pass just as
    // well against an empty string extracted from the wrong key.
    expect(organizationSchemaProperty('name'))->toContain('name');
});

it('reads the same front matter from a CRLF checkout as from an LF one', function (): void {
    // Built from the shipped file in both forms, so the result does not depend
    // on how this clone's `core.autocrlf` checked `content/` out (UAMS-Web/uams-statamic-archive#2517).
    $lf = str_replace("\r\n", "\n", organizationSchemaTemplateSource());
    $crlf = str_replace("\n", "\r\n", $lf);

    expect($crlf)->toContain("---\r\n");

    $fromLf = entryFrontMatter($lf);

    expect($fromLf)->toHaveKey('schema')
        ->and(entryFrontMatter($crlf))->toBe($fromLf);
});

it('emits PronounceableText when a spoken form and its IPA are supplied', function (): void {
    $html = renderOrganizationName([
        'name' => 'University of Arkansas for Medical Sciences',
        'name_pronunciation' => [
            'text' => 'UAMS',
            'phonetic_text' => 'ˈju ˈeɪ ˈɛm ˈɛs',
        ],
        'site' => ['lang' => 'en'],
    ]);

    $decoded = json_decode($html, true);

    expect($decoded)->toBeArray();

    /** @var array<int, array<string, mixed>> $nodes */
    $nodes = \is_array($decoded) ? $decoded : [];

    expect($nodes)->not->toBeEmpty();
    expect($html)->toContain('PronounceableText');

    // The spoken form is what carries the pronunciation, not the official name
    expect($nodes[0]['textValue'] ?? null)->toBe('UAMS');
    expect($nodes[0]['speechToTextMarkup'] ?? null)->toBe('IPA');

    // UAMS-Web/uams-statamic-archive#2362's derivation gives an SSML sibling off the same input
    expect($html)->toContain('SSML');
});

it('emits the plain official name when no pronunciation is supplied', function (): void {
    // The load-bearing regression case: `name_pronunciation` is optional and is
    // empty on every organization entry today, so this is what the overwhelming
    // majority of organizations render.
    $html = renderOrganizationName([
        'name' => 'University of Arkansas for Medical Sciences',
        'name_pronunciation' => ['text' => '', 'phonetic_text' => ''],
        'site' => ['lang' => 'en'],
    ]);

    expect($html)
        ->toBe('University of Arkansas for Medical Sciences')
        ->not->toContain('PronounceableText');
});

it('emits the plain official name when the field is absent entirely', function (): void {
    // An entry saved before the field existed carries no key at all
    $html = renderOrganizationName([
        'name' => 'University of Arkansas for Medical Sciences',
        'site' => ['lang' => 'en'],
    ]);

    expect($html)
        ->toBe('University of Arkansas for Medical Sciences')
        ->not->toContain('PronounceableText');
});

it('wires the pronunciation input as a text_phonetic_pair on the organizations blueprint', function (): void {
    $parsed = Yaml::parseFile(base_path('resources/blueprints/collections/organizations/organization.yaml'));

    expect($parsed)->toBeArray();

    $found = null;

    $walk = function (mixed $node) use (&$walk, &$found): void {
        if (! \is_array($node)) {
            return;
        }

        if (($node['handle'] ?? null) === 'name_pronunciation') {
            $found = $node;

            return;
        }

        foreach ($node as $child) {
            $walk($child);
        }
    };

    $walk($parsed);

    expect($found)->not->toBeNull();

    $node = $found;

    /** @var array<string, mixed> $node */
    $node = \is_array($node) ? $node : [];

    $field = $node['field'] ?? [];

    expect(\is_array($field) ? ($field['type'] ?? null) : null)->toBe('text_phonetic_pair');

    // Not required: an organization without a pronunciation must still save
    expect(\is_array($field) ? ($field['validate'] ?? []) : [])->not->toContain('required');
});
