<?php

declare(strict_types=1);

use App\Services\LinkHealth\ContentItemType;
use App\Services\LinkHealth\LinkKind;
use App\Services\LinkHealth\LinkOccurrence;

covers(LinkOccurrence::class);

/**
 * @covers \App\Services\LinkHealth\LinkOccurrence
 *
 * @command  php artisan test --compact tests/Unit/Services/LinkHealth/LinkOccurrenceTest.php
 */
it('renders location as a dotted path of field plus path segments', function (): void {
    $occurrence = new LinkOccurrence(
        ContentItemType::Entry,
        'posts',
        'post-1',
        'default',
        'page_builder',
        [2, 'cta', 'url'],
        'https://example.test',
        LinkKind::External,
    );

    expect($occurrence->location())->toBe('page_builder.2.cta.url');
});

it('renders location as the field alone when the path is empty', function (): void {
    $occurrence = new LinkOccurrence(
        ContentItemType::Entry,
        'posts',
        'post-1',
        'default',
        'external_link',
        [],
        'https://example.test',
        LinkKind::External,
    );

    expect($occurrence->location())->toBe('external_link');
});
