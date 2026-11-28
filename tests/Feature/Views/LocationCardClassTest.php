<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The location card's own class (UAMS-Web/uams-statamic#1602): `clinical_locations/_card` appends a
 * caller's `card_class` to its `div.card`, the related-locations section of a clinical resource
 * page passes `location-card`, and the Find-a-Doc stylesheet keys the card's layout rules to that
 * class instead of to a class on the list container.
 *
 * Rendered hermetically from synthetic data arrays. Each render appends a unique comment to bust
 * Antlers' parsed-document cache.
 *
 * @command  php artisan test --compact tests/Feature/Views/LocationCardClassTest.php
 */

/**
 * Render a template string with the given context.
 *
 * @param  string  $template  The Antlers template.
 * @param  array<string, mixed>  $context  The render context.
 * @return string The rendered markup.
 */
function renderLocationCardClass(string $template, array $context): string
{
    /** @var int $render */
    static $render = 0;
    $render++;

    return (string) Antlers::parse($template."\n{{# location-card-class-{$render} #}}", $context, true);
}

/**
 * One synthetic location entry.
 *
 * @return array<string, string> The entry's fields.
 */
function locationCardClassEntry(): array
{
    return ['title' => 'UAMS Medical Center', 'url' => '/locations/medical-center'];
}

it('renders the card class alone when no card_class is passed', function (): void {
    $html = renderLocationCardClass('{{ partial:clinical_locations/card }}', locationCardClassEntry());

    expect($html)->toContain('<div class="card h-100">')
        ->and($html)->not->toContain('location-card');
});

it('appends a passed card_class to the card, escaped', function (): void {
    $plain = renderLocationCardClass('{{ partial:clinical_locations/card card_class="location-card" }}', locationCardClassEntry());
    $marked = renderLocationCardClass('{{ partial:clinical_locations/card :card_class="cls" }}', [
        ...locationCardClassEntry(),
        'cls' => 'x" onclick="alert(1)',
    ]);

    expect($plain)->toContain('<div class="card h-100 location-card">')
        ->and($marked)->toContain('<div class="card h-100 x&quot; onclick=&quot;alert(1)">')
        ->and($marked)->not->toContain('onclick="alert(1)"');
});

it('gives the related-location cards on a resource page their own class inside a plain container', function (): void {
    $html = renderLocationCardClass('{{ partial:clinical_resources/related }}', [
        'clinical_providers' => [],
        'clinical_locations' => [locationCardClassEntry()],
        'clinical_expertise' => [],
        'clinical_conditions' => [],
        'clinical_services' => [],
        'clinical_resources' => [],
    ]);

    expect($html)->toContain('<div class="card-list-container">')
        ->and($html)->toContain('<div class="card h-100 location-card">')
        ->and($html)->not->toContain('location-card-list-container');
});

it('leaves the other location card callers without a card_class', function (string $view): void {
    $source = File::get(resource_path("views/{$view}"));

    // Control: the scan reads the caller it names.
    expect($source)->toContain('partial:clinical_locations/card')
        ->and($source)->not->toContain('card_class');
})->with([
    'location listing' => 'clinical_locations/_list_body.antlers.html',
    'child locations' => 'clinical_locations/_child_locations.antlers.html',
    'search result card' => 'vendor/aether-search/cards/_clinical_locations.antlers.html',
]);

it('keys the location card layout rules to the card class', function (): void {
    $scss = File::get(resource_path('scss/bootstrap/uamswp_find_a_doc/_locations-list.scss'));

    expect($scss)->toContain(".card-list-container {\n    .card-list {\n        .card.location-card {");
});

it('leaves no live view or stylesheet referencing the old container class', function (): void {
    $carrying = [];
    foreach ([resource_path('views'), resource_path('scss')] as $root) {
        foreach (File::allFiles($root) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            if (! str_starts_with($relative, 'bootstrap/uams_2020/') && str_contains($file->getContents(), 'location-card-list-container')) {
                $carrying[] = $relative;
            }
        }
    }

    // Control: the commented-out `uams_2020` copies, left alone, still carry it.
    $legacy = File::get(resource_path('scss/bootstrap/uams_2020/uams-supporting/find-a-doc/locations-list.scss'));

    expect($carrying)->toBe([])
        ->and($legacy)->toContain('location-card-list-container');
});
