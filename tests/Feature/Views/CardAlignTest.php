<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * A card's own text alignment (UAMS-Web/uams-statamic#1604): each card partial this repository
 * owns takes `card_align="left"` and adds `card-align-left` to its card element, and the lists
 * that used to left-align their cards through a class on the list pass it instead.
 *
 * Rendered hermetically from synthetic data arrays. Each render appends a unique comment to bust
 * Antlers' parsed-document cache.
 *
 * @command  php artisan test --compact tests/Feature/Views/CardAlignTest.php
 */

/**
 * Render a template string with the given context.
 *
 * @param  string  $template  The Antlers template.
 * @param  array<string, mixed>  $context  The render context.
 * @return string The rendered markup.
 */
function renderCardAlign(string $template, array $context): string
{
    /** @var int $render */
    static $render = 0;
    $render++;

    return (string) Antlers::parse($template."\n{{# card-align-{$render} #}}", $context, true);
}

it('adds the alignment class to the card only when card_align is left', function (string $partial, string $without, string $with): void {
    $entry = ['title' => 'Uhura', 'url' => '/card/uhura'];

    $plain = renderCardAlign("{{ partial:{$partial} }}", $entry);
    $left = renderCardAlign("{{ partial:{$partial} card_align=\"left\" }}", $entry);
    $other = renderCardAlign("{{ partial:{$partial} card_align=\"center\" }}", $entry);

    expect($plain)->toContain($without)
        ->and($plain)->not->toContain('card-align-left')
        ->and($left)->toContain($with)
        ->and($other)->toContain($without)
        ->and($other)->not->toContain('card-align-left');
})->with([
    'classified card' => ['classifieds/card', '<div class="card h-100">', '<div class="card h-100 card-align-left">'],
    'expertise card' => ['clinical_expertise/card', '<article class="card expertise-card h-100"', '<article class="card expertise-card h-100 card-align-left"'],
    'location card' => ['clinical_locations/card', '<div class="card h-100">', '<div class="card h-100 card-align-left">'],
    'resource card' => ['clinical_resources/card', '<div class="card h-100">', '<div class="card h-100 card-align-left">'],
    'post card' => ['posts/card', '<div class="card h-100">', '<div class="card h-100 card-align-left">'],
]);

it('keeps a caller card_class after the alignment class on the location card', function (): void {
    $html = renderCardAlign('{{ partial:clinical_locations/card card_align="left" card_class="location-card" }}', ['title' => 'Sulu', 'url' => '/card/sulu']);

    expect($html)->toContain('<div class="card h-100 card-align-left location-card">');
});

it('passes the alignment from every list that used to carry the list class', function (string $view, string $call, int $count): void {
    $source = File::get(resource_path("views/{$view}"));

    expect(substr_count($source, $call))->toBe($count)
        ->and($source)->not->toContain('card-list-left');
})->with([
    'classifieds listing' => ['classifieds/_listing.antlers.html', '{{ partial:classifieds/card card_align="left" }}', 1],
    'expertise listing' => ['clinical_expertise/_listing.antlers.html', '{{ partial:clinical_expertise/card card_align="left" }}', 1],
    'expertise related resources' => ['clinical_expertise/_related.antlers.html', '{{ partial:clinical_resources/card card_align="left" }}', 1],
    'child locations' => ['clinical_locations/_child_locations.antlers.html', '{{ partial:clinical_locations/card card_align="left" }}', 1],
    'location listing' => ['clinical_locations/_list_body.antlers.html', '{{ partial:clinical_locations/card :fad_facet="fad_facet" card_align="left" }}', 1],
    'resource listing' => ['clinical_resources/_listing.antlers.html', '{{ partial:clinical_resources/card card_align="left" }}', 1],
    'resource related resources' => ['clinical_resources/_related.antlers.html', '{{ partial:clinical_resources/card card_heading_level="h3" card_align="left" }}', 1],
    'department hub' => ['pages/department_hub.antlers.html', '{{ partial:posts/card show_primary_category="true" card_align="left" }}', 2],
    'news home' => ['pages/news_home.antlers.html', '{{ partial:posts/card show_primary_category="true" card_align="left" }}', 2],
    'post listing' => ['posts/_listing.antlers.html', '{{ partial:posts/card card_align="left" }}', 1],
    'news archive' => ['posts/_news_archive.antlers.html', '{{ partial:posts/card card_align="left" }}', 1],
    'term archive' => ['taxonomies/_term_archive_posts.antlers.html', '{{ partial:posts/card card_align="left" }}', 1],
    'news block' => ['page_builder/_uamsweb_block_news.antlers.html', '<div class="card card-align-left">', 1],
]);

it('styles the card class at the specificity of the list rule, directly after it', function (): void {
    $scss = File::get(resource_path('scss/bootstrap/uams_2020/uams-supporting/layout.scss'));

    $listRule = strpos($scss, "    &.card-list-left {\n        .card {\n            text-align: left;");
    $cardRule = strpos($scss, "    .card.card-align-left {\n        text-align: left;");

    expect($listRule)->toBeInt()
        ->and($cardRule)->toBeInt()
        ->and(is_int($listRule) && is_int($cardRule) && $cardRule > $listRule)->toBeTrue();
});

it('adds the alignment class to a card item only when card_align is left', function (): void {
    $plain = renderCardAlign('{{ partial:page_builder/components/card_item }}Chekov{{ /partial:page_builder/components/card_item }}', []);
    $left = renderCardAlign('{{ partial:page_builder/components/card_item card_align="left" }}Chekov{{ /partial:page_builder/components/card_item }}', []);

    expect($plain)->toMatch('/<div\s+class="card"\s/')
        ->and($plain)->not->toContain('card-align-left')
        ->and($left)->toMatch('/<div\s+class="card card-align-left"\s/');
});

it('renders a card list without an alignment class, whatever card_list_position is passed', function (string $call): void {
    $html = renderCardAlign("{{ {$call} }}Scotty{{ /partial:page_builder/components/card_list }}", []);

    expect($html)->toContain('class="card-list"')
        ->and($html)->not->toContain('card-list-left')
        ->and($html)->toContain('Scotty');
})->with([
    'default' => ['partial:page_builder/components/card_list'],
    'left' => ['partial:page_builder/components/card_list card_list_position="left"'],
    'center' => ['partial:page_builder/components/card_list card_list_position="center"'],
]);

it('left-aligns the cards of every card list caller through the card item', function (string $view, int $count): void {
    $source = File::get(resource_path("views/{$view}"));

    // Control: the scan reads a view that renders a card item.
    expect($source)->toContain('partial:page_builder/components/card_item')
        ->and(substr_count($source, 'card_align="left"'))->toBe($count);
})->with([
    'knowledge base attachments' => ['knowledge_bases/default.antlers.html', 1],
    'knowledge base topics' => ['knowledge_bases/_topic_list.antlers.html', 1],
    'knowledge base article card' => ['knowledge_bases/_article_card.antlers.html', 1],
    'stacked block' => ['page_builder/_uamsweb_block_stacked.antlers.html', 1],
]);
