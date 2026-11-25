<?php

declare(strict_types=1);

// cspell:ignore learndash

/**
 * Tests the keyboard focus ring on the LearnDash controls
 * (UAMS-Web/uams-statamic#154, WCAG 2.4.7). Four `:focus` rules in the
 * LearnDash plugin stylesheet reset `outline` to `0`, leaving only a box-shadow
 * glow; each now carries the site's `uams-focus-visible()` ring instead. The
 * reset has to go as well as the mixin arriving: it sits in a later `:focus`
 * rule of equal specificity, so it would hide the ring the mixin draws.
 * Appearance is visual, so this pins the rules in the source; the compiled CSS
 * was compared before and after the change.
 *
 * @command php artisan test --compact tests/Feature/Views/LearnDashFocusVisibleTest.php
 */

/**
 * The LearnDash plugin stylesheet, with comment lines removed so a commented-out
 * rule, or a comment that mentions one, cannot satisfy an assertion about it.
 */
function learnDashPluginScss(): string
{
    $scss = file_get_contents(dirname(__DIR__, 3).'/resources/scss/bootstrap/uams_2020/uams-supporting/plugins/learndash.scss');

    return (string) preg_replace('~^\s*//.*$~m', '', (string) $scss);
}

it('gives each LearnDash control the brand focus ring', function (string $selector): void {
    $pattern = '~'.preg_quote($selector, '~').'\s*\{\s*(?:&\.btn\s*\{\s*)?@include uams-focus-visible\(\);\s*&:focus\s*\{~';

    expect(learnDashPluginScss())->toMatch($pattern);
})->with([
    'course link buttons' => ['.learndash-wrapper a'],
    'section expand button' => ['.learndash-wrapper .ld-item-list .ld-section-heading .ld-item-list-actions .ld-expand-button'],
    'item expand button' => ['.learndash-wrapper .ld-item-list .ld-item-list-item .ld-item-details .ld-expand-button'],
    'not-enrolled course button' => ['.learndash-wrapper .ld-course-status.ld-course-status-not-enrolled .ld-button'],
]);

it('leaves no outline reset in the LearnDash stylesheet', function (): void {
    $scss = learnDashPluginScss();

    expect(substr_count($scss, '@include uams-focus-visible('))->toBe(4)
        ->and($scss)->not->toMatch('~^\s*outline\s*:\s*(?:0|none)\s*;~m');
});
