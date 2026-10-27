<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Regression guard for UAMS-Web/uams-statamic-archive#1883: the shared SEO partial must not fatal on a
 * description source that is not plain text.
 *
 * `resources/views/layout/header/_seo_meta.antlers.html` picks the description
 * from `excerpt`, then `bio_short`, then `spotlight_auto_excerpt`, and pipes it
 * through `strip_tags`. All three are `textarea` fields by design — every
 * "short" biography on `person.yaml` is `uamsweb_common.excerpt`, while only the
 * "full" ones are Bard — but four people entries had `bio_short` stored as a
 * Bard/ProseMirror document. A non-empty array is truthy, so it reached
 * `strip_tags()` and threw `must be of type string, array given`.
 *
 * The partial sits in `layout.antlers.html`, so that broke EVERY page rendered
 * for those entries, not just the people lenses that surfaced it. The four
 * entries are repaired in `content`; this pins the template so the next
 * array-shaped value degrades to "no description" instead of a 500.
 *
 * @command  php artisan test --compact tests/Feature/Seo/SeoMetaDescriptionShapeTest.php
 */

/** @var array<int, array<string, mixed>> A Bard document exactly as the four entries stored it. */
$bardShapedBio = [
    [
        'type' => 'paragraph',
        'attrs' => ['id' => null],
        'content' => [
            ['type' => 'text', 'text' => 'Andrea Peel leads UAMS internal communications.'],
        ],
    ],
];

it('does not fatal when the description source is a rich-text document', function () use ($bardShapedBio): void {
    $html = (string) Antlers::parse(
        '{{ partial:layout/header/seo_meta }}',
        ['title' => 'Andrea Peel', 'bio_short' => $bardShapedBio]
    );

    // Degrades to no description rather than throwing.
    expect($html)->not->toContain('name="description"')
        ->and($html)->not->toContain('og:description')
        // The rest of the card still renders, so one bad field cannot blank the head.
        ->and($html)->toContain('og:title');
});

it('still emits a description when the source is plain text', function (): void {
    $html = (string) Antlers::parse(
        '{{ partial:layout/header/seo_meta }}',
        ['title' => 'Josh Daugherty', 'bio_short' => 'Leads the web team.']
    );

    expect($html)->toContain('<meta name="description" content="Leads the web team.">')
        ->and($html)->toContain('og:description');
});

it('does not fatal when the title is a rich-text document', function () use ($bardShapedBio): void {
    // `$seo_title` runs through the same `strip_tags` and would fatal the same
    // way; `site:name` is the string fallback the guard falls back to.
    $html = (string) Antlers::parse(
        '{{ partial:layout/header/seo_meta }}',
        ['title' => $bardShapedBio]
    );

    expect($html)->toContain('og:title');
});
