<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/*
|--------------------------------------------------------------------------
| Posts reverse-reference display field (UAMS-Web/uams-statamic-archive#1419)
|--------------------------------------------------------------------------
|
| `referenced_by_posts` surfaces read-only in the CP via a `visibility: computed`
| entries field in the shared `uamsweb_post_curation` fieldset (imported by every
| post blueprint), placed beside the `related_posts` override it inverts. These
| parse the fieldset YAML and assert the reverse is a read-only computed entries
| field, and that the manual override stays author-editable.
|
| @command php artisan test --compact tests/Feature/Blueprints/PostsReverseReferenceFieldsTest.php
*/

/**
 * Find a field's config block by handle in the post-curation fieldset.
 *
 * @return array<string, mixed>|null
 */
function postCurationField(string $handle): ?array
{
    $path = resource_path('fieldsets/uamsweb_post_curation.yaml');
    /** @var array<string, mixed> $parsed */
    $parsed = Yaml::parseFile($path);

    /** @var list<array<string, mixed>> $fields */
    $fields = $parsed['fields'] ?? [];

    foreach ($fields as $field) {
        $config = $field['field'] ?? null;

        if (($field['handle'] ?? null) === $handle && is_array($config)) {
            /** @var array<string, mixed> $config */
            return $config;
        }
    }

    return null;
}

it('exposes referenced_by_posts as a read-only computed entries field with instructions', function (): void {
    /** @var array<string, mixed> $field */
    $field = postCurationField('referenced_by_posts');

    expect($field)->not->toBeNull()
        ->and($field['type'])->toBe('entries')
        ->and($field['visibility'])->toBe('computed')
        ->and($field['instructions'] ?? '')->not->toBe('')
        // No max_items — many posts may reference one.
        ->and($field)->not->toHaveKey('max_items');
});

it('leaves the manual related_posts override author-editable, not computed', function (): void {
    /** @var array<string, mixed> $field */
    $field = postCurationField('related_posts');

    // The override is unchanged (UAMS-Web/uams-statamic-archive#1419 adds a separate read-only reverse, no sync).
    expect($field)->not->toBeNull()
        ->and($field['type'])->toBe('entries')
        ->and($field['visibility'] ?? 'visible')->not->toBe('computed');
});
