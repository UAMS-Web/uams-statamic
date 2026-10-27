<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Structural coverage for the `news` site home view
 * (`resources/views/pages/news_home.antlers.html`, UAMS-Web/uams-statamic-archive#1612) and the primary-category
 * addition to the shared post card.
 *
 * The lead/section/dedup behavior is covered at the tag + service level in
 * `NewsMagazineTest`; this guards the view wiring (embeds, CTA, and the dropped
 * newsroom widgets) against the template source.
 *
 * @command  php artisan test --compact tests/Feature/Views/NewsHomeViewTest.php
 */

/** Read a resources/views template's source. */
function newsHomeView(string $relative): string
{
    return File::get(resource_path('views/'.$relative));
}

it('drives the lead and sections through the news_home tag with primary-category cards', function (): void {
    $template = newsHomeView('pages/news_home.antlers.html');

    expect($template)
        ->toContain('{{ news_home }}')
        ->toContain('{{ lead }}')
        ->toContain('{{ sections }}')
        // Cards render the primary category (opt-in flag).
        ->toContain('partial:posts/card show_primary_category="true"')
        ->toContain('no_results')
        // The editor-configured section heading is escaped (raw-by-default Antlers).
        ->toContain('{{ heading | sanitize }}');
});

it('renders the calendar and YouTube embeds, each guarded by its id and sanitized', function (): void {
    $template = newsHomeView('pages/news_home.antlers.html');

    expect($template)
        // LiveWhale calendar (calendar.uams.edu).
        ->toContain('calendar.uams.edu/livewhale')
        ->toContain('{{ calendar_livewhale_id | sanitize }}')
        // YouTube playlist (lazy iframe).
        ->toContain('videoseries?list={{ youtube_playlist_id | sanitize }}')
        ->toContain('loading="lazy"')
        // Bottom archive CTA.
        ->toContain('{{ archive_cta_url | sanitize_url }}');
});

it('drops the "In the News" scroller and the internal events_list zone', function (): void {
    $template = newsHomeView('pages/news_home.antlers.html');

    // Per UAMS-Web/uams-statamic-archive#1609 these newsroom widgets are not carried over.
    $lower = strtolower($template);

    expect($lower)->not->toContain('in the news')
        ->and($lower)->not->toContain('newsdataservice')
        ->and($lower)->not->toContain('events_list');
});

it('adds an opt-in, sanitized primary-category label to the shared post card', function (): void {
    $card = newsHomeView('posts/_card.antlers.html');

    expect($card)
        ->toContain('show_primary_category')
        ->toContain('post-card-category')
        // The editor/importer-controlled category title is sanitized (raw-by-default Antlers).
        ->toContain('{{ title | sanitize }}');
});
