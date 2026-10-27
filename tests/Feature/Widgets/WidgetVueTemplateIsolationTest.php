<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#2572 — the Control Panel dashboard compiles every widget's HTML as a Vue
 * template, so any `{{ ... }}` in text a widget prints would be evaluated in the
 * viewer's session. Blade's escaping does not touch `{{`. Each widget therefore
 * carries `v-pre` on its root, which stops Vue compiling anything inside it.
 *
 * The views are rendered directly with the data their widgets pass, so the
 * templates are what is tested, independent of the widgets' own logic.
 *
 * @command php artisan test --compact tests/Feature/Widgets/WidgetVueTemplateIsolationTest.php
 */
const WIDGET_TEMPLATE_PAYLOAD = 'Dr X {{ $emit.constructor(1)() }}';

/**
 * Whether the rendered HTML opens with a root element carrying `v-pre`.
 */
function widgetRootIsVuePre(string $html): bool
{
    return preg_match('/^\s*<div\s+v-pre\b/', $html) === 1;
}

it('keeps the duplicate-people widget\'s entry titles inside a v-pre root', function (): void {
    $html = view('widgets.duplicate_people', [
        'title' => 'Potential Duplicate People',
        'clusters' => [[
            'confidence' => 'high',
            'signals' => 'npi',
            'members' => [
                ['title' => WIDGET_TEMPLATE_PAYLOAD, 'edit_url' => '/cp/collections/people/entries/a'],
                ['title' => 'Second Person', 'edit_url' => '/cp/collections/people/entries/b'],
            ],
            'merge_url' => '/cp/people/merge?ids=a,b',
            'count' => 2,
        ]],
    ])->render();

    expect(widgetRootIsVuePre($html))->toBeTrue()
        ->and($html)->toContain(WIDGET_TEMPLATE_PAYLOAD)
        ->toContain('/cp/people/merge?ids=a,b')
        ->toContain('Review &amp; merge');
});

it('keeps the incomplete-entries widget\'s entry titles inside a v-pre root', function (): void {
    $html = view('widgets.incomplete_published_entries', [
        'title' => 'Published Entries With Validation Issues',
        'entries' => collect([[
            'title' => WIDGET_TEMPLATE_PAYLOAD,
            'edit_url' => '/cp/collections/pages/entries/a',
            'collection_title' => 'Pages',
            'blueprint_title' => 'Page',
        ]]),
        'pagination' => [
            'current_page' => 1,
            'last_page' => 2,
            'per_page' => 1,
            'total' => 2,
            'query' => [],
            'page_query_key' => 'incomplete_entries_page',
            'base_url' => '/cp/dashboard',
        ],
        'computed' => true,
        'scan' => ['scanned' => 2, 'total' => 2, 'truncated' => false],
        'computed_at' => time() - 60,
        'content_changed_since' => true,
    ])->render();

    // The pagination link and the content-changed notice still render inside the root
    expect(widgetRootIsVuePre($html))->toBeTrue()
        ->and($html)->toContain(WIDGET_TEMPLATE_PAYLOAD)
        ->toContain('incomplete_entries_page=2')
        ->toContain('data-incomplete-entries-state="content-changed"');
});

it('keeps the incomplete-entries widget\'s not-computed state inside the v-pre root', function (): void {
    $html = view('widgets.incomplete_published_entries', [
        'title' => 'Published Entries With Validation Issues',
        'entries' => collect(),
        'pagination' => null,
        'computed' => false,
        'scan' => null,
        'computed_at' => null,
        'content_changed_since' => false,
    ])->render();

    expect(widgetRootIsVuePre($html))->toBeTrue()
        ->and($html)->toContain('data-incomplete-entries-state="not-computed"');
});

it('keeps the Google Business Profile category check\'s category names inside a v-pre root', function (): void {
    $html = view('widgets.google_business_profile_category_check', [
        'title' => 'Google Business Profile categories',
        'latest' => [
            'checked_at' => '2026-09-30T12:00:00+00:00',
            'source' => 'file: categories.tsv',
            'counts' => ['new' => 0, 'renamed' => 1, 'removed' => 0, 'without_gcid' => 0, 'malformed' => 0],
            'report' => ['renamed' => [['gcid' => 'acupuncturist', 'slug' => 'acupuncturist', 'title' => 'Acupuncturist', 'name' => WIDGET_TEMPLATE_PAYLOAD]]],
        ],
        'overdue' => false,
        'overdue_after_days' => 30,
    ])->render();

    expect(widgetRootIsVuePre($html))->toBeTrue()
        ->and($html)->toContain(WIDGET_TEMPLATE_PAYLOAD);
});

it('keeps the gender-override widget\'s entry titles inside a v-pre root', function (): void {
    $html = view('widgets.workday_gender_overrides', [
        'title' => 'Gender Overrides to Review',
        'rows' => [[
            'id' => 'a',
            'title' => WIDGET_TEMPLATE_PAYLOAD,
            'edit_url' => '/cp/collections/people/entries/a',
            'gender' => 'Other',
            'workday_gender' => 'Female',
            'confirm_url' => '/cp/people/workday-gender/a/confirm',
            'take_url' => '/cp/people/workday-gender/a/take',
        ]],
    ])->render();

    expect(widgetRootIsVuePre($html))->toBeTrue()
        ->and($html)->toContain(WIDGET_TEMPLATE_PAYLOAD)
        ->toContain('/cp/people/workday-gender/a/confirm')
        ->toContain('/cp/people/workday-gender/a/take')
        ->toContain('name="_token"');
});
