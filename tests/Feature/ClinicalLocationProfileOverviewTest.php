<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Fields\LabeledValue;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Render coverage for the opening blocks of the `clinical_locations` profile
 * (UAMS-Web/uams-statamic#1578), in
 * {@see clinical_locations/_profile_overview.antlers.html}: the Get Directions and
 * parking links, the photo gallery, and the About section with its video and
 * description sections. Each case is driven hermetically through a fixture scope;
 * the Find-a-Doc Settings global is passed in that scope as `uams_fad_settings`, the
 * handle the cascade gives it on a page. The gallery is passed as the list of assets
 * `resolved_gallery_image` resolves to, each reduced to the `url` and `alt` it reads.
 *
 * @command  php artisan test --compact tests/Feature/ClinicalLocationProfileOverviewTest.php
 */

/**
 * Render the profile-overview partial with the given location scope.
 *
 * @param  array<array-key, mixed>  $location  The location's fields, keyed by handle.
 */
function renderLocationOverview(array $location): string
{
    return (string) Antlers::parse('{{ partial:clinical_locations/profile_overview }}', ['title' => 'Enterprise Clinic', ...$location]);
}

/**
 * A standard-content modification's augmented value.
 */
function standardModification(string $key): LabeledValue
{
    return new LabeledValue($key, $key);
}

it('renders nothing for a location with none of the fields', function (): void {
    $html = renderLocationOverview([]);

    expect($html)->not->toContain('location-actions')
        ->and($html)->not->toContain('location-gallery')
        ->and($html)->not->toContain('location-about')
        ->and(trim($html))->toBe('');
});

it('links Get Directions to the same destination the card builds', function (): void {
    $address = [
        'resolved_street_address' => '1701 Starfleet Blvd',
        'resolved_city' => 'Riverside',
        'resolved_state' => 'IA',
        'resolved_postal_code' => '52327',
    ];
    $profile = renderLocationOverview($address);
    $card = (string) Antlers::parse('{{ partial:clinical_locations/card }}', ['title' => 'Enterprise Clinic', 'url' => '/health/locations/enterprise', ...$address]);

    $destination = 'https://www.google.com/maps/dir/?api=1&amp;destination='.rawurlencode('1701 Starfleet Blvd, Riverside, IA 52327');
    expect($profile)->toContain('<a href="'.$destination.'" class="btn btn-primary"')
        ->and($profile)->toContain('aria-label="Get directions to Enterprise Clinic">Get Directions</a>')
        ->and($card)->toContain('<a href="'.$destination.'" class="btn btn-outline-primary"')
        ->and($profile)->not->toContain('#parking-info');
});

it('links to the parking section when parking information exists, and not otherwise', function (string $field): void {
    $html = renderLocationOverview([$field => 'Garage B']);

    expect($html)->toContain('<a class="btn btn-outline-primary" href="#parking-info"')
        ->and($html)->not->toContain('Get Directions');
})->with(['resolved_parking_facility_name', 'resolved_parking_instructions', 'resolved_directions_from_parking']);

it('renders each gallery image with the alt text from its asset, escaped', function (): void {
    $html = renderLocationOverview([
        'resolved_gallery_image' => [
            ['url' => '/assets/lobby.jpg', 'alt' => 'The clinic lobby'],
            ['url' => '/assets/desk.jpg', 'alt' => 'Front desk "check-in"'],
        ],
    ]);

    expect($html)->toContain('<section class="location-gallery" aria-label="Photos of Enterprise Clinic">')
        ->and($html)->toContain('<img src="/assets/lobby.jpg" alt="The clinic lobby" loading="lazy">')
        ->and($html)->toContain('<img src="/assets/desk.jpg" alt="Front desk &quot;check-in&quot;" loading="lazy">')
        ->and(renderLocationOverview(['resolved_gallery_image' => []]))->not->toContain('location-gallery');
});

it('renders the About section with the lead description, the video and each description section', function (): void {
    $html = renderLocationOverview([
        'about' => '<p>A full-service clinic.</p>',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'description_sections' => [
            ['type' => 'section', 'heading' => 'Our Team', 'body' => '<p>Twelve providers.</p>'],
            ['type' => 'affiliation', 'body' => '<p>Part of the regional network.</p>'],
        ],
    ]);

    expect($html)->toContain('<section class="location-about" id="description">')
        ->and($html)->toContain('<h2 data-jump-link="About">About Enterprise Clinic</h2>')
        ->and($html)->toContain('A full-service clinic.')
        ->and($html)->toContain('location-video')
        ->and($html)->toContain('<h3>Our Team</h3>')
        ->and($html)->toContain('Twelve providers.')
        ->and($html)->toContain('<h3 id="affiliation">Affiliation</h3>')
        ->and($html)->toContain('Part of the regional network.');
});

it('renders the About section for any one of its parts alone', function (array $location, string $text): void {
    $html = renderLocationOverview($location);

    expect($html)->toContain('<h2 data-jump-link="About">About Enterprise Clinic</h2>')
        ->and($html)->toContain($text);
})->with([
    'lead description' => [['about' => '<p>A full-service clinic.</p>'], 'A full-service clinic.'],
    'video' => [['video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'], 'location-video'],
    'affiliation' => [['description_sections' => [['type' => 'affiliation', 'body' => '<p>Network member.</p>']]], 'Network member.'],
]);

it('combines the standard Billing and Medical Records copy with the location content', function (string $mode, string $expected): void {
    $html = renderLocationOverview([
        'description_sections' => [
            ['type' => 'billing', 'modification' => standardModification($mode), 'body' => '<p>LOCAL</p>'],
            ['type' => 'medical_records', 'modification' => standardModification($mode), 'body' => '<p>LOCAL</p>'],
        ],
        'uams_fad_settings' => [
            'location_section_billing' => '<p>BILLING</p>',
            'location_section_medical_records' => '<p>RECORDS</p>',
        ],
    ]);

    // Reduce each section body to its words, in order.
    $sections = [];
    foreach (['billing' => 'BILLING', 'medical-records' => 'RECORDS'] as $id => $standard) {
        preg_match('~<h3 id="'.$id.'">[^<]*</h3>\s*<div class="location-description-section">(.*?)</div>~s', $html, $match);
        $sections[] = trim((string) preg_replace('~\s+~', ' ', strip_tags($match[1] ?? '')));
    }

    expect($html)->toContain('<h3 id="billing">Billing</h3>')
        ->and($html)->toContain('<h3 id="medical-records">Medical Records</h3>')
        ->and($sections)->toBe([
            str_replace('STANDARD', 'BILLING', $expected),
            str_replace('STANDARD', 'RECORDS', $expected),
        ]);
})->with([
    'inherit' => ['inherit', 'STANDARD'],
    'prepend' => ['prepend', 'LOCAL STANDARD'],
    'append' => ['append', 'STANDARD LOCAL'],
    'override' => ['override', 'LOCAL'],
]);

it('omits a standard section that has no copy to show', function (): void {
    $html = renderLocationOverview([
        'description_sections' => [
            ['type' => 'billing', 'modification' => standardModification('inherit')],
        ],
    ]);

    expect($html)->not->toContain('<h3 id="billing">');
});
