<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Render coverage for the Area of Expertise "Also called" callout, Featured Video and meta
 * keywords (UAMS-Web/uams-statamic#1580), ported from Find-a-Doc's single-expertise page:
 * `uamswp_expertise_keywords()`, `uamswp_expertise_youtube()` and
 * `uamswp_expertise_header_metadata()`. All three read the alternate names or the video
 * URL, so each case is driven hermetically through a fixture scope. `alternate_name` is
 * passed in the shape the replicator augments to, one set per name with a `text` field.
 *
 * @command  php artisan test --compact tests/Feature/Views/ExpertiseAlternateNamesVideoKeywordsTest.php
 */

/**
 * The alternate names as replicator sets.
 *
 * @param  list<string>  $names  The names, in order.
 * @return list<array{type: string, text: string}>
 */
function expertiseAlternateNames(array $names): array
{
    return array_map(static fn (string $name): array => ['type' => 'new_set', 'text' => $name], $names);
}

/**
 * Render a partial in an Area of Expertise scope.
 *
 * @param  array<array-key, mixed>  $scope  The entry's fields, keyed by handle.
 */
function renderExpertisePartial(string $partial, array $scope): string
{
    return (string) Antlers::parse('{{ partial:'.$partial.' }}', [
        'title' => 'Cardiology',
        'collection' => ['handle' => 'clinical_expertise'],
        ...$scope,
    ]);
}

it('shows Also called with the names separated by semicolons, escaped', function (): void {
    $html = renderExpertisePartial('clinical_expertise/also_called', [
        'alternate_name' => expertiseAlternateNames(['Heart Care', '', 'Cardio "CV"']),
    ]);

    expect($html)->toContain('<p class="text-callout text-callout-info expertise-also-called">Also called: Heart Care; Cardio &quot;CV&quot;</p>');
});

it('shows no Also called without alternate names', function (array $scope): void {
    expect(renderExpertisePartial('clinical_expertise/also_called', $scope))->not->toContain('Also called');
})->with([
    'no field' => [[]],
    'no names' => [['alternate_name' => []]],
    'only blank names' => [['alternate_name' => expertiseAlternateNames([''])]],
]);

it('embeds the featured video, and nothing without one', function (): void {
    $html = renderExpertisePartial('clinical_expertise/featured_video', [
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    expect($html)->toContain('expertise-featured-video')
        ->and($html)->toContain('<iframe')
        ->and(trim(renderExpertisePartial('clinical_expertise/featured_video', [])))->toBe('');
});

it('emits meta keywords from the alternate names, each with its commas removed', function (): void {
    $html = renderExpertisePartial('layout/header/seo_meta', [
        'alternate_name' => expertiseAlternateNames(['Heart, Vascular', 'Cardio "CV"']),
    ]);

    expect($html)->toContain('<meta name="keywords" content="Heart Vascular, Cardio &quot;CV&quot;">');
});

it('emits no meta keywords without alternate names', function (): void {
    expect(renderExpertisePartial('layout/header/seo_meta', []))->not->toContain('name="keywords"');
});

it('emits no meta keywords on another collection', function (): void {
    $html = renderExpertisePartial('layout/header/seo_meta', [
        'collection' => ['handle' => 'clinical_locations'],
        'alternate_name' => expertiseAlternateNames(['Heart Care']),
    ]);

    expect($html)->not->toContain('name="keywords"');
});

it('places Also called before the page content and the video after it on the show template', function (): void {
    $show = File::get(resource_path('views/clinical_expertise/show.antlers.html'));
    $alsoCalled = strpos($show, '{{ partial:clinical_expertise/also_called }}');
    $content = strpos($show, '{{ page_builder scope="block" }}');
    $video = strpos($show, '{{ partial:clinical_expertise/featured_video }}');

    expect($alsoCalled)->toBeInt()
        ->and($content)->toBeInt()
        ->and($video)->toBeInt()
        ->and($alsoCalled < $content && $content < $video)->toBeTrue();
});
