<?php

declare(strict_types=1);

use App\Fieldtypes\Consent;
use Statamic\Facades\Markdown;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

covers(Consent::class);

/**
 * Behavioral and mutation coverage for the Consent fieldtype preload and config.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/ConsentFieldtypeTest.php
 */

/**
 * Bind a Consent fieldtype to a Field with the given config.
 *
 * @param  array<string, mixed>  $config  Field config merged over the type handle
 */
function makeConsent(array $config = []): Consent
{
    // Build the fieldtype under test
    $fieldtype = new Consent;

    // Bind a Field with the given config over the type handle
    $fieldtype->setField(new Field('consent', array_merge(['type' => 'consent'], $config)));

    return $fieldtype;
}

it('parses the consent agreement markdown to html and passes the raw config through', function (): void {
    // The agreement text is authored in Markdown but the Vue component needs HTML,
    // and the raw agreement must survive so it can be stored with the submission.
    $preload = makeConsent([
        'consent_agreement' => 'I **agree** to the [terms](/terms).',
        'checkbox_label' => 'I accept',
    ])->preload();

    expect($preload['consent_agreement_html'])->toContain('<strong>agree</strong>')
        ->and($preload['consent_agreement_html'])->toContain('href="/terms"')
        ->and($preload['consent_agreement'])->toBe('I **agree** to the [terms](/terms).')
        ->and($preload['checkbox_label'])->toBe('I accept');
});

it('renders an empty html agreement when none is configured', function (): void {
    // No agreement configured → the fieldtype parses the empty string rather than
    // emitting null, so the Vue component always receives a string.
    expect(makeConsent()->preload()['consent_agreement_html'])->toBe(Markdown::parse(''));
});

it('uses the checkboxes fieldtype icon', function (): void {
    expect(makeConsent()->icon())->toBe('fieldtype-checkboxes');
});

it('exposes the consent options config section exactly', function (): void {
    // Reflect the protected configFieldItems map
    $method = new ReflectionMethod(Consent::class, 'configFieldItems');

    // Invoke configFieldItems on a bound fieldtype
    /** @var array<int, array<string, mixed>> $items */
    $items = $method->invoke(makeConsent());

    // Assert the Consent Options section shape exactly
    expect($items)->toHaveCount(1)
        ->and($items[0]['display'] ?? null)->toBe(__('Consent Options'))
        ->and($items[0]['fields'] ?? null)->toMatchArray([
            'checkbox_label' => [
                'display' => __('Checkbox Label'),
                'instructions' => __('Set the label for the checkbox.'),
                'type' => 'text',
                'default' => 'I agree',
                'placeholder' => 'e.g., I agree',
            ],
            'consent_agreement' => [
                'type' => 'markdown',
                'display' => __('Consent Agreement'),
                'instructions' => __(
                    'The consent field will display this text to the user and will store this agreement text with the form entry in order to track what the user has consented to.'
                ),
                'buttons' => [
                    'bold',
                    'italic',
                    'unorderedlist',
                    'orderedlist',
                    'quote',
                    'link',
                ],
                'automatic_links' => true,
            ],
        ]);
});

it('parses a non-string consent agreement as an empty markdown string', function (): void {
    $preload = makeConsent(['consent_agreement' => ['not' => 'a string']])->preload();

    expect($preload['consent_agreement_html'])->toBe(Markdown::parse(''));
});
