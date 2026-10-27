<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * People Showcase photoless single-profile portrait suppression (UAMS-Web/uams-statamic-archive#1183).
 *
 * resources/views/page_builder/_uamsweb_block_people_item.antlers.html renders the
 * fixed-width `.uamspeople-box-photo` column only when the person has a photo. On a
 * photoless profile it emits no placeholder image and no photo column at all, so the
 * info column (`.uamspeople-box-info`, `flex: 1 1 0`) reflows to fill the row rather
 * than sitting beside a grey `no-image-available` placeholder graphic.
 *
 * @command php artisan test --compact tests/Feature/Views/PeopleShowcasePhotolessPortraitTest.php
 */
/**
 * @param  array<string, mixed>  $data
 */
function renderPeopleShowcaseItem(array $data): string
{
    return (string) Antlers::parse('{{ partial:page_builder/uamsweb_block_people_item }}', $data, true);
}

it('renders the portrait column when the person has a photo', function () {
    $out = renderPeopleShowcaseItem([
        'portrait' => ['url' => 'https://img.test/kirk.jpg', 'alt' => 'Kirk'],
        'title' => 'James Kirk',
    ]);

    // The portrait <img> and its fixed-width photo column are present.
    expect($out)->toContain('uamspeople-box-photo')
        ->and($out)->toContain('<img')
        ->and($out)->toContain('width="128"');

    // The info column still carries the name.
    expect($out)->toContain('uamspeople-box-info')
        ->and($out)->toContain('James Kirk');
});

it('omits the portrait column and placeholder entirely for a photoless profile', function () {
    $out = renderPeopleShowcaseItem([
        'title' => 'Spock',
    ]);

    // No portrait image and no fixed-width photo column, so the info column
    // reflows to fill the row.
    expect($out)->not->toContain('uamspeople-box-photo')
        ->and($out)->not->toContain('<img');

    // The grey `no-image-available_3-4` placeholder SVG is gone (identified by its
    // viewBox and fill), not just the <img>.
    expect($out)->not->toContain('viewBox="0 0 245 327"')
        ->and($out)->not->toContain('#dcdadb');

    // The profile still renders — the info column and name remain.
    expect($out)->toContain('uamspeople-box-info')
        ->and($out)->toContain('Spock');
});
