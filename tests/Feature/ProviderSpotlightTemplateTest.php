<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

/**
 * Render coverage for the flat provider-spotlight body partial (UAMS-Web/uams-statamic-archive#1425): the
 * always-generated introduction, the Q&A block with the provider portrait, and
 * the eligibility-gated appointment CTA (bookable "Make an Appointment" with a
 * formatted phone vs. "Learn More"). The derived values are passed in scope; the
 * values themselves are covered by ProviderSpotlightComputedValuesTest.
 *
 * @command php artisan test --compact tests/Feature/ProviderSpotlightTemplateTest.php
 */
uses(TestCase::class);

/**
 * Render the provider-spotlight body partial with the given entry scope.
 *
 * @param  array<string, mixed>  $scope
 */
function renderProviderSpotlight(array $scope): string
{
    return (string) Antlers::parse('{{ partial:clinical_resources/provider_spotlight }}', $scope);
}

it('renders the generated introduction, the portrait, and the Q&A', function (): void {
    $html = renderProviderSpotlight([
        'spotlight_auto_introduction' => 'Get to know Dr. Jane Smith, a cardiologist at UAMS Health. Read on.',
        'featured_provider' => [[
            'url' => '/health/providers/jane-smith',
            'portrait' => [['url' => '/img/jane-3x4.jpg']],
        ]],
        'spotlight_provider_name' => 'Dr. Jane Smith',
        'spotlight_qa' => [[
            'spotlight_qa_question' => 'What inspired you to work in healthcare?',
            'spotlight_qa_answer' => '<p>A calling to help people.</p>',
        ]],
        'spotlight_bookable' => false,
    ]);

    expect($html)
        ->toContain('Provider Spotlight')
        ->toContain('Get to know Dr. Jane Smith, a cardiologist at UAMS Health. Read on.')
        ->toContain('What inspired you to work in healthcare?')
        ->toContain('A calling to help people.')
        ->toContain('src="/img/jane-3x4.jpg"')
        ->toContain('alt="Dr. Jane Smith"');
});

it('renders the bookable CTA with a formatted appointment phone', function (): void {
    $html = renderProviderSpotlight([
        'spotlight_auto_introduction' => 'Intro.',
        'featured_provider' => [['url' => '/health/providers/jane-smith']],
        'spotlight_provider_name' => 'Dr. Jane Smith',
        'spotlight_bookable' => true,
        'spotlight_phone' => '(501) 686-8998',
        'spotlight_appointment_phone_display' => '501-686-8998',
        'spotlight_appointment_phone_href' => '5016868998',
    ]);

    expect($html)
        ->toContain('Make an Appointment')
        ->toContain('href="tel:5016868998"')
        ->toContain('501-686-8998');
    expect($html)->not->toContain('Learn More');
});

it('prepends the referral-only note in the bookable CTA', function (): void {
    $html = renderProviderSpotlight([
        'spotlight_auto_introduction' => 'Intro.',
        'featured_provider' => [['url' => '/health/providers/jane-smith']],
        'spotlight_provider_name' => 'Dr. Jane Smith',
        'spotlight_bookable' => true,
        'spotlight_referral_required' => true,
    ]);

    expect($html)
        ->toContain('Make an Appointment')
        ->toContain('by referral only');
});

it('renders the not-bookable CTA as Learn More with no phone', function (): void {
    $html = renderProviderSpotlight([
        'spotlight_auto_introduction' => 'Intro.',
        'featured_provider' => [['url' => '/health/providers/jane-smith']],
        'spotlight_provider_name' => 'Dr. Jane Smith',
        'spotlight_provider_title' => 'cardiologist',
        'spotlight_bookable' => false,
        'spotlight_phone' => '(501) 686-8998',
        'spotlight_appointment_phone_display' => '501-686-8998',
    ]);

    expect($html)->toContain('Learn More');
    expect($html)->not->toContain('Make an Appointment');
    expect($html)->not->toContain('tel:');
});
