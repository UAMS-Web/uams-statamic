<?php

declare(strict_types=1);

use Tests\TestCase;
use UAMSWeb\Aether\Forms\Settings\CpForms\FieldDefinitions;
use UAMSWeb\Aether\Forms\Settings\Features;

/**
 * Proves the site keeps its own values for the add-on defaults that
 * UAMS-Web/statamic-form-settings#20 changed (UAMS-Web/uams-statamic#288).
 *
 * The third of them, `article_bard_extra_buttons`, is covered by `RenamedAddOnsTest`.
 *
 * @command  php artisan test --compact tests/Feature/Forms/FormSettingsDefaultsTest.php
 */
uses(TestCase::class);

it('keeps quiz scoring on from the site\'s own configuration', function (): void {
    // Read the site's file, so the add-on's own default cannot satisfy this
    $site = require config_path('aether-form-settings.php');

    expect($site)->toHaveKey('quiz_scoring', true)
        ->and(Features::quizScoring())->toBeTrue();
});

it('shows the site\'s URL in the add-on\'s URL field placeholder', function (): void {
    expect(FieldDefinitions::url()['placeholder'])->toBe('e.g., https://uams.edu/');
});
