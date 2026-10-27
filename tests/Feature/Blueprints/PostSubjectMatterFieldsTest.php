<?php

declare(strict_types=1);

use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/*
|--------------------------------------------------------------------------
| UAMS-Web/uams-statamic-archive#2026 — post-level subject matter across the four post blueprints
|--------------------------------------------------------------------------
|
| The WordPress importer writes `post_categories` and `post_tags` onto every
| post it creates, `audio` included. Only `post_categories` had nowhere to
| land, though: `content/collections/posts.yaml` then declared
| `taxonomies: [post_tags]`, so `Collection::ensureEntryBlueprintFields()`
| (vendor/statamic/cms/src/Entries/Collection.php:406-417) was already injecting
| a working `post_tags` control into the *sidebar* of any post blueprint that
| did not declare one. Declaring both here pinned them against that fallback and
| moved `post_tags` into the same main-tab "Subject Matter" section the other
| three post types use. UAMS-Web/uams-statamic-archive#2532 converted `post_tags` to a collection and dropped
| that `taxonomies:` declaration, so there is no fallback left to inject it: the
| declarations here are now the only source of the field.
|
| These assertions read the *repository* blueprint (`Blueprint::in(...)`), which
| is the raw declaration — NOT `Collection::entryBlueprint()`, which appends the
| auto-ensured fields. That is deliberate: the declaration is what UAMS-Web/uams-statamic-archive#2026 is
| about, and reading the entry blueprint would let a deleted `post_tags` line
| pass silently because Statamic would re-inject it. What this proves is that
| the `field: <fieldset>.<handle>` references resolve and carry the expected
| fieldtype config — not that an entry renders them.
|
| `about`/`mentions` are deliberately NOT part of that: they are post-level
| aboutness for the two text post types only. The media post types model
| aboutness on the media layer instead — `video` on its segment fieldset
| (`uamsweb_post_video_segment`), `audio` on the audio *asset* blueprint that
| each segment's file points at. Importing the whole `uamsweb_post_subject_matter`
| fieldset into `audio` would create a second, competing place to record the
| aboutness of the same audio, which is why UAMS-Web/uams-statamic-archive#2026 took the cherry-pick.
|
| @command php artisan test --compact tests/Feature/Blueprints/PostSubjectMatterFieldsTest.php
*/

beforeEach(fn () => Blink::flush());

dataset('post_blueprints', ['post', 'news_release', 'audio', 'video']);

/** The two text post types, which own post-level aboutness. */
dataset('text_post_blueprints', ['post', 'news_release']);

/** The two media post types, which model aboutness on the media layer. */
dataset('media_post_blueprints', ['audio', 'video']);

it('defines post_categories and post_tags on every post blueprint (#2026)', function (string $handle): void {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('collections/posts')->get($handle);

    expect($blueprint->hasField('post_categories'))->toBeTrue("{$handle} is missing post_categories")
        ->and($blueprint->hasField('post_tags'))->toBeTrue("{$handle} is missing post_tags");

    /** @var Field $categories */
    $categories = $blueprint->field('post_categories');
    /** @var Field $tags */
    $tags = $blueprint->field('post_tags');

    // Both are entries references since UAMS-Web/uams-statamic-archive#2532 converted `post_tags` to a collection.
    expect($categories->type())->toBe('entries')
        ->and($categories->config()['collections'])->toBe(['post_categories'])
        ->and($tags->type())->toBe('entries')
        ->and($tags->config()['collections'])->toBe(['post_tags'])
        ->and($tags->config())->not->toHaveKey('taxonomies');
})->with('post_blueprints');

it('keeps post-level aboutness on the text post types', function (string $handle): void {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('collections/posts')->get($handle);

    expect($blueprint->hasField('about'))->toBeTrue("{$handle} is missing about")
        ->and($blueprint->hasField('mentions'))->toBeTrue("{$handle} is missing mentions");
})->with('text_post_blueprints');

it('keeps post-level aboutness off the media post types (#2026)', function (string $handle): void {
    // Bounds the cherry-pick: growing `audio` to hold the importer's two handles
    // must not drag `about`/`mentions` in behind them.
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('collections/posts')->get($handle);

    expect($blueprint->hasField('about'))->toBeFalse("{$handle} should not carry post-level about")
        ->and($blueprint->hasField('mentions'))->toBeFalse("{$handle} should not carry post-level mentions");
})->with('media_post_blueprints');

it('models audio aboutness on the audio asset blueprint instead', function (): void {
    // The premise the cherry-pick rests on: audio aboutness already has a home,
    // so a post-level copy would compete with it. If this moves, revisit UAMS-Web/uams-statamic-archive#2026.
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('assets')->get('audio');

    expect($blueprint->hasField('about'))->toBeTrue()
        ->and($blueprint->hasField('mentions'))->toBeTrue();
});
