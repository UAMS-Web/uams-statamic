<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for the post clinical-entity association fields (UAMS-Web/uams-statamic-archive#1153) and the
 * curation fields + render (UAMS-Web/uams-statamic-archive#1155). Resolves the real post blueprints (so the
 * fieldset imports are proven end-to-end) and renders the curation partial.
 *
 * @command  php artisan test --compact tests/Feature/PostsAssociationsCurationTest.php
 */
beforeEach(fn () => Blink::flush());

dataset('post_blueprints', ['post', 'news_release', 'audio', 'video']);

it('adds the three clinical-entity association fields to every post blueprint (#1153)', function (string $handle): void {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('collections/posts')->get($handle);

    $associations = [
        'clinical_providers' => 'clinical_providers',
        'areas_of_expertise' => 'clinical_expertise',
        'clinical_locations' => 'clinical_locations',
    ];

    foreach ($associations as $field => $collection) {
        expect($blueprint->hasField($field))->toBeTrue("{$handle} is missing {$field}");

        /** @var Field $fieldObj */
        $fieldObj = $blueprint->field($field);
        expect($fieldObj->type())->toBe('entries');
        expect($fieldObj->config()['collections'])->toBe([$collection]);
        expect($fieldObj->config()['create'])->toBeFalse();
    }
})->with('post_blueprints');

it('adds the related_posts override and cta curation fields to every post blueprint (#1155)', function (string $handle): void {
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('collections/posts')->get($handle);

    /** @var Field $relatedPosts */
    $relatedPosts = $blueprint->field('related_posts');
    /** @var Field $cta */
    $cta = $blueprint->field('cta');

    expect($relatedPosts->type())->toBe('entries')
        ->and($relatedPosts->config()['collections'])->toBe(['posts'])
        ->and($cta->type())->toBe('group');
})->with('post_blueprints');

it('wires the curation partial into every post show template (#1155)', function (string $handle): void {
    // The partial only renders curation if a show template actually includes it;
    // pin the wiring so dropping the include from any post type turns the suite red.
    $template = resource_path("views/posts/{$handle}.antlers.html");

    expect(file_get_contents($template))->toContain('partial:posts/components/curation');
})->with('post_blueprints');

// ---------------------------------------------------------------------------
// Curation render (UAMS-Web/uams-statamic-archive#1155)
// ---------------------------------------------------------------------------

/**
 * Render the curation partial with the given scope.
 *
 * @param  array<string, mixed>  $data
 */
function renderCuration(array $data): string
{
    /** @var int $render */
    static $render = 0;
    $render++;

    return (string) Antlers::parse('{{ partial:posts/components/curation }}'."\n{{# r-{$render} #}}", $data);
}

it('renders the CTA only when both a label and a link are present', function (): void {
    $with = renderCuration(['cta' => ['label' => 'LearnMore', 'url' => '/take-action', 'style' => 'outline']]);
    expect($with)->toContain('post-cta')
        ->and($with)->toContain('LearnMore')
        ->and($with)->toContain('href="/take-action"')
        ->and($with)->toContain('btn-outline-primary');

    $labelOnly = renderCuration(['cta' => ['label' => 'LearnMore', 'url' => null]]);
    expect($labelOnly)->not->toContain('post-cta');
});

it('renders the related posts list only when populated', function (): void {
    $with = renderCuration(['related_posts' => [
        ['url' => '/story-a', 'title' => 'StoryA'],
        ['url' => '/story-b', 'title' => 'StoryB'],
    ]]);
    expect($with)->toContain('post-related')
        ->and(substr_count($with, '<li>'))->toBe(2)
        ->and($with)->toContain('/story-a');

    $empty = renderCuration(['related_posts' => []]);
    expect($empty)->not->toContain('post-related');
});

it('renders nothing when neither curation field is populated', function (): void {
    expect(trim(renderCuration([])))->toBe('');
});
