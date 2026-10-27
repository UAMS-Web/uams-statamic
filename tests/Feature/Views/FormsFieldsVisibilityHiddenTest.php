<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests for resources/views/vendor/statamic/forms/fields.antlers.html —
 * specifically the `visibility: hidden` filter wrapping the render branches.
 *
 * Blueprint fields with `visibility: hidden` are admin- or system-only inputs
 * (e.g. WordPress-imported Gravity Forms fields that store post bodies or
 * admin-context metadata). The public-form render must skip them entirely so they
 * never reach the DOM (issue UAMS-Web/uams-statamic-archive#86). Rendering the override directly against a
 * hand-built field shape has no mutable PHP surface.
 *
 * @command php artisan test --compact tests/Feature/Views/FormsFieldsVisibilityHiddenTest.php
 */

/**
 * Render the fields loop override against the given field shapes.
 *
 * @param  array<int, array<string, mixed>>  $fields
 */
function renderFormFieldsLoop(array $fields): string
{
    return (string) Antlers::parse(
        File::get(resource_path('views/vendor/statamic/forms/fields.antlers.html')),
        ['fields' => $fields],
        true,
    );
}

/**
 * Build a minimal renderable-field shape sufficient to exercise the loop branches.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function formFieldShape(array $overrides = []): array
{
    return [
        'handle' => 'sample',
        'type' => 'text',
        'visibility' => 'visible',
        'show_field' => 'true',
        'id' => 'form-sample',
        'display' => 'Sample',
        'instructions' => null,
        'validate' => [],
        'field' => '<input name="sample" type="text">',
        'error' => null,
        ...$overrides,
    ];
}

it('renders a visible text field', function () {
    $out = renderFormFieldsLoop([formFieldShape([
        'handle' => 'name',
        'id' => 'form-name',
        'display' => 'Name',
        'field' => '<input name="name" type="text">',
    ])]);

    expect($out)->toContain('class="form-group"');
    expect($out)->toContain('for="form-name"');
    expect($out)->toMatch('/<label[^>]*>\s*Name\b/');
    expect($out)->toContain('<input name="name" type="text">');
});

it('skips a visibility:hidden field entirely', function () {
    $out = renderFormFieldsLoop([formFieldShape([
        'handle' => 'post_body',
        'visibility' => 'hidden',
        'display' => 'Post Body',
        'field' => '<textarea name="post_body"></textarea>',
    ])]);

    expect($out)->not->toContain('form-group');
    expect($out)->not->toContain('post_body');
    expect($out)->not->toContain('Post Body');
    expect($out)->not->toContain('<textarea');
});

it('renders only the visible fields when visibility is mixed', function () {
    $out = renderFormFieldsLoop([
        formFieldShape([
            'handle' => 'name',
            'id' => 'form-name',
            'display' => 'Name',
            'field' => '<input name="name" type="text">',
        ]),
        formFieldShape([
            'handle' => 'state_of_event',
            'visibility' => 'hidden',
            'display' => 'State of Event',
            'field' => '<input name="state_of_event" type="text">',
        ]),
        formFieldShape([
            'handle' => 'story',
            'type' => 'textarea',
            'id' => 'form-story',
            'display' => 'Story',
            'field' => '<textarea name="story"></textarea>',
        ]),
    ]);

    expect($out)->toContain('name="name"');
    expect($out)->toContain('name="story"');
    expect($out)->not->toContain('name="state_of_event"');
    expect($out)->not->toContain('State of Event');
    expect(substr_count($out, 'class="form-group"'))->toBe(2);
});

it('still renders a type:hidden field that has visible visibility', function () {
    // The blueprint distinguishes `type: hidden` (a real <input type="hidden">
    // fieldtype that should render) from `visibility: hidden` (admin-only).
    $out = renderFormFieldsLoop([formFieldShape([
        'handle' => 'token',
        'type' => 'hidden',
        'visibility' => 'visible',
        'field' => '<input name="token" type="hidden" value="abc">',
    ])]);

    expect($out)->toContain('<input name="token" type="hidden" value="abc">');
});

it('renders a field that omits the visibility key (defaults to visible)', function () {
    $field = formFieldShape([
        'handle' => 'email',
        'display' => 'Email',
        'field' => '<input name="email" type="email">',
    ]);
    unset($field['visibility']);

    $out = renderFormFieldsLoop([$field]);

    expect($out)->toContain('name="email"');
    expect($out)->toMatch('/<label[^>]*>\s*Email\b/');
});
