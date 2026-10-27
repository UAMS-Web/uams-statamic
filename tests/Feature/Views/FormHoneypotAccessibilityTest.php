<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests the honeypot field in resources/views/components/_form.antlers.html (issue UAMS-Web/uams-statamic-archive#887).
 *
 * The spam-trap honeypot must stay reachable by bots (real `name` attribute, submitted)
 * but unreachable by genuine users — otherwise keyboard, screen-reader, and autofill users
 * populate it and get silently spam-rejected on submit. The fix hides it from the tab order
 * (`tabindex="-1"`), browser autofill (`autocomplete="off"`), and the accessibility tree
 * (`aria-hidden="true"`, replacing the announcing `aria-label`), on top of the visual `sr-only`.
 *
 * @command php artisan test --compact tests/Feature/Views/FormHoneypotAccessibilityTest.php
 */

/**
 * Render the form component partial and return the honeypot <input> markup.
 */
function renderFormHoneypotInput(): string
{
    $out = (string) Antlers::parse(
        File::get(resource_path('views/components/_form.antlers.html')),
        ['honeypot' => 'honeypot'],
        true,
    );

    expect($out)->toMatch('/<input\b[^>]*\bname="honeypot"[^>]*>/s');

    preg_match('/<input\b[^>]*\bname="honeypot"[^>]*>/s', $out, $matches);

    return $matches[0] ?? '';
}

it('hides the honeypot from keyboard, autofill, and assistive tech', function (): void {
    $input = renderFormHoneypotInput();

    expect($input)
        // Unreachable by genuine users...
        ->toContain('tabindex="-1"')
        ->toContain('autocomplete="off"')
        ->toContain('aria-hidden="true"')
        ->toContain('class="sr-only"')
        // ...but still submitted, so bots still trip it.
        ->toContain('name="honeypot"');
    // ...not announced (aria-hidden makes a label meaningless and contradictory).
    expect($input)->not->toContain('aria-label');
});

it('honors a custom honeypot field name', function (): void {
    $out = (string) Antlers::parse(
        File::get(resource_path('views/components/_form.antlers.html')),
        ['honeypot' => 'winnie_the_pooh'],
        true,
    );

    expect($out)
        ->toContain('name="winnie_the_pooh"')
        ->toContain('aria-hidden="true"');
});
