<?php

declare(strict_types=1);

use App\Support\Forms\FormRequestSubmissionContext;
use Statamic\Facades\Addon;
use Tests\TestCase;
use UAMSWeb\Aether\Forms\Settings\Contracts\SubmissionContext;

/**
 * Proves the application reaches the add-ons under their `aether` names (#435).
 *
 * A published configuration file only takes effect under the add-on's current name,
 * and a file left under the old name is ignored without any error, so these read the
 * values back rather than checking that the files exist.
 *
 * @command  php artisan test --compact tests/Feature/Forms/RenamedAddOnsTest.php
 */
uses(TestCase::class);

it('resolves a contract in an add-on\'s renamed namespace to the application\'s binding', function (): void {
    expect(app()->bound(SubmissionContext::class))->toBeTrue()
        ->and(get_class(app(SubmissionContext::class)))->toBe(FormRequestSubmissionContext::class);
});

it('registers each add-on under its renamed package', function (string $package): void {
    expect(Addon::get($package))->not->toBeNull();
})->with([
    'uams-web/aether-alpine-csp-forms',
    'uams-web/aether-form-settings',
    'uams-web/aether-multisite-forms',
    'uams-web/aether-wordpress-importer',
]);

it('applies the application\'s configuration under each add-on\'s renamed key', function (): void {
    expect(config('aether-form-settings.article_bard_extra_buttons'))->toBe(['bts_attributes'])
        ->and(config('aether-multisite-forms.not_assigned'))->toBe('empty')
        ->and(config('statamic-form-settings'))->toBeNull()
        ->and(config('statamic-multisite-forms'))->toBeNull();
});
