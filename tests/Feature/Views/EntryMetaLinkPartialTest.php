<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Renders the entry-meta link partials (layout/page-header/entry-meta/...) and
 * asserts the anchor markup. These are Antlers template partials with no backing
 * PHP class, so there is no mutable PHP surface to mutation-test.
 *
 * @command  php artisan test --compact tests/Feature/Views/EntryMetaLinkPartialTest.php
 */
it('renders the link component as an anchor with href and class', function () {
    $out = (string) Antlers::parse('{{ partial:layout/page-header/entry-meta/components/link }}', [
        'url' => 'https://example.com/doc.pdf',
        'link_text' => 'Handout',
        'use_download' => true,
    ], true);

    expect($out)->toContain('href="https://example.com/doc.pdf"');
    expect($out)->toContain('Handout');
    expect($out)->toContain('entry-meta-link');
    expect($out)->toContain('download');
});

it('renders the download item partial with label and link', function () {
    $out = (string) Antlers::parse('{{ partial:layout/page-header/entry-meta/download }}', [
        'label' => 'Download:',
        'value' => 'https://example.com/a.pdf',
        'link_text' => 'PDF',
    ], true);

    expect($out)->toContain('Download:');
    expect($out)->toContain('href="https://example.com/a.pdf"');
    expect($out)->toContain('PDF');
    expect($out)->toContain('fa-download');
});
