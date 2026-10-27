<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Regression test for stored XSS via the entry-meta person partial.
 *
 * `_person.antlers.html` concatenates each related person's `title` and
 * `permalink` into `<span>`/`<a href>` strings and prints them with `| raw`. An
 * editor-controlled person title containing markup was therefore rendered live
 * in the page header. The values are now `| sanitize`d before concatenation;
 * this pins that behavior while keeping the structural markup intact.
 *
 * No mutable PHP surface: the system under test is the Antlers partial
 * `resources/views/layout/page-header/entry-meta/components/_person.antlers.html`,
 * a template with no backing App\ PHP class.
 *
 * @command  php artisan test --compact tests/Feature/EntryMetaPersonPartialXssTest.php
 */

/**
 * Render the entry-meta person partial with the given related people.
 *
 * @param  array<int, array<string, mixed>>  $people
 */
function renderEntryMetaPersonPartialXss(array $people): string
{
    return (string) Antlers::parse(
        '{{ partial:layout/page-header/entry-meta/components/person }}',
        ['value' => $people],
        true
    );
}

it('escapes markup in a person title', function (): void {
    $html = renderEntryMetaPersonPartialXss([
        ['title' => '<img src=x onerror=alert(document.cookie)>', 'permalink' => '/people/x'],
    ]);

    expect($html)->not->toContain('<img');
    expect($html)->toContain('&lt;img');
});

it('prevents a quote in a permalink from breaking the href attribute', function (): void {
    $html = renderEntryMetaPersonPartialXss([
        ['title' => 'Jane Doe', 'permalink' => '/people/x"><script>alert(1)</script>'],
    ]);

    expect($html)->not->toContain('"><script>');
    expect($html)->not->toContain('<script>');
    expect($html)->toContain('&quot;');
});

it('still renders the name and link for a benign person', function (): void {
    $html = renderEntryMetaPersonPartialXss([
        ['title' => 'Jane Doe', 'permalink' => '/people/jane'],
    ]);

    expect($html)->toContain('Jane Doe');
    expect($html)->toContain('href="/people/jane"');
    expect($html)->toContain('itemtype="https://schema.org/Person"');
});
