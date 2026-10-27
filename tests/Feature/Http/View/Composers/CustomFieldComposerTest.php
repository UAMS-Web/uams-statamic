<?php

declare(strict_types=1);

use App\Http\View\Composers\CustomFieldComposer;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Fieldset;
use Statamic\Fields\Fieldset as FieldsetModel;
use Statamic\Statamic;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(CustomFieldComposer::class);

/**
 * Mutation: tracked under App\Http\View\Composers\CustomFieldComposer (--covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Http/View/Composers/CustomFieldComposerTest.php
 */
beforeEach(function (): void {
    static $padded = false;
    if (! $padded) {
        if (runningUnderMutate()) {
            usleep(20_000_000);
            $padded = true;
        }
    }

    // Reset Statamic script variables so tests do not leak merge state
    $reflection = new ReflectionClass(Statamic::class);
    $property = $reflection->getProperty('jsonVariables');
    $property->setAccessible(true);
    $property->setValue(null, []);
});

/**
 * Ensure compose() merges fieldsets and extension rules into StatamicConfig.
 */
it('merges fieldsets and extension rules', function () {
    // Resolve the Statamic scripts partial as the CP would
    $view = view('statamic::partials.scripts');

    // Run the composer so Statamic receives merged script variables
    $composer = new CustomFieldComposer;
    $composer->compose($view);

    // Read the payload Statamic exposes to the CP bootstrap script
    /** @var array<string, mixed> $payload */
    $payload = Statamic::jsonVariables(request());

    expect($payload['fieldsets'])->toBeInstanceOf(Collection::class);
    expect($payload['extensionRules'])->toBeArray();
});

/**
 * Sectioned fieldsets must expose flattened fields and section metadata.
 */
it('flattens sectioned fieldset fields for the script payload', function () {
    // Build a fieldset stored with sections (never mixed with top-level fields)
    $sectioned = new FieldsetModel;
    $sectioned->setHandle('unit_test_sectioned');
    $sectioned->setContents([
        'title' => 'Unit test sectioned',
        'sections' => [
            [
                'display' => 'Primary',
                'fields' => [
                    [
                        'handle' => 'alpha',
                        'field' => [
                            'type' => 'text',
                            'display' => 'Alpha',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    // Stub the fieldset repository so this test does not depend on disk files
    Fieldset::partialMock()
        ->shouldReceive('all')
        ->once()
        ->andReturn(collect([$sectioned]));

    // Resolve the Statamic scripts partial as the CP would
    $view = view('statamic::partials.scripts');

    // Run the composer so Statamic receives merged script variables
    $composer = new CustomFieldComposer;
    $composer->compose($view);

    // Read the payload Statamic exposes to the CP bootstrap script
    /** @var array<string, mixed> $payload */
    $payload = Statamic::jsonVariables(request());

    // Confirm the sectioned fieldset flattened fields and preserved flags
    $fieldsets = $payload['fieldsets'];
    expect($fieldsets)->toBeInstanceOf(Collection::class);

    /** @var Collection<string, mixed> $fieldsets */
    $fieldsetPayload = $fieldsets->get('unit_test_sectioned');
    expect($fieldsetPayload)->toBeArray();

    /** @var array<string, mixed> $fieldsetPayload */
    // RemoveArrayItem on handle/title would drop these keys.
    expect($fieldsetPayload['handle'])->toBe('unit_test_sectioned');
    expect($fieldsetPayload['title'])->toBe('Unit test sectioned');
    expect($fieldsetPayload['has_sections'])->toBeTrue();
    expect($fieldsetPayload['sections_count'])->toBe(1);
    expect($fieldsetPayload['fields'])->toHaveCount(1);

    /** @var array<int, array<string, mixed>> $fields */
    $fields = $fieldsetPayload['fields'];
    expect($fields[0]['handle'])->toBe('alpha');
});

/**
 * Flat (non-sectioned) fieldsets use the contents.fields branch and zero
 * sections_count.
 */
it('maps flat fieldset fields without sections', function (): void {
    $flat = new FieldsetModel;
    $flat->setHandle('unit_test_flat');
    $flat->setContents([
        'title' => 'Unit test flat',
        'fields' => [
            [
                'handle' => 'beta',
                'field' => [
                    'type' => 'text',
                    'display' => 'Beta',
                ],
            ],
        ],
    ]);

    Fieldset::partialMock()
        ->shouldReceive('all')
        ->once()
        ->andReturn(collect([$flat]));

    $composer = new CustomFieldComposer;
    $composer->compose(view('statamic::partials.scripts'));

    /** @var array<string, mixed> $payload */
    $payload = Statamic::jsonVariables(request());

    /** @var Collection<string, mixed> $fieldsets */
    $fieldsets = $payload['fieldsets'];
    $fieldsetPayload = $fieldsets->get('unit_test_flat');

    expect($fieldsetPayload)->toBeArray();
    /** @var array<string, mixed> $fieldsetPayload */
    expect($fieldsetPayload['handle'])->toBe('unit_test_flat');
    expect($fieldsetPayload['title'])->toBe('Unit test flat');
    expect($fieldsetPayload['has_sections'])->toBeFalse();
    // IncrementInteger/DecrementInteger on the `: 0` arm.
    expect($fieldsetPayload['sections_count'])->toBe(0);
    expect($fieldsetPayload['fields'])->toHaveCount(1);
    expect(mustBeArray(mustBeArray($fieldsetPayload['fields'], 'the fieldset fields')[0], 'the first fieldset field')['handle'])->toBe('beta');
});

/**
 * Extension rules expose custom labels when registered and title-case fallbacks
 * (with underscores replaced) otherwise.
 */
it('labels extension rules with custom names and title fallbacks', function (): void {
    Fieldset::partialMock()
        ->shouldReceive('all')
        ->once()
        ->andReturn(collect());

    $composer = new CustomFieldComposer;
    $composer->compose(view('statamic::partials.scripts'));

    /** @var array<string, mixed> $payload */
    $payload = Statamic::jsonVariables(request());

    /** @var array<int, array{label: string, value: string}> $rules */
    $rules = $payload['extensionRules'];
    // AlwaysReturnEmptyArray would leave this empty.
    expect($rules)->not->toBeEmpty();

    $byValue = collect($rules)->keyBy('value');

    // CoalesceRemoveLeft would drop the custom "YouTube URL" label.
    expect($byValue->get('youtube_url'))->toMatchArray([
        'label' => 'YouTube URL',
        'value' => 'youtube_url',
    ]);

    // UnwrapStrReplace leaves underscores; RemoveArrayItem drops label/value.
    expect($byValue->get('after_or_equal'))->toMatchArray([
        'label' => 'After Or Equal',
        'value' => 'after_or_equal',
    ]);
});
