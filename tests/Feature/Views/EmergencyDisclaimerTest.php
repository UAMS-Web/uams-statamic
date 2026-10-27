<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The shared 911 emergency-disclaimer partial (UAMS-Web/uams-statamic-archive#1466) and its placement above each
 * Find-a-Doc directory listing — patient-safety parity with the legacy WordPress
 * Find-a-Doc archives, which carried a "call 911" banner above every listing. The
 * areas-of-expertise archive never carried the banner and is intentionally excluded.
 */
it('renders the shared 911 emergency disclaimer with the call-911 guidance', function (): void {
    $html = (string) Antlers::parse('{{ partial:components/emergency_disclaimer_911 }}', []);

    expect($html)->toContain('911')
        ->and($html)->toContain('emergency')
        ->and($html)->toContain('href="tel:911"')
        ->and($html)->toContain('role="note"');
});

it('includes the 911 disclaimer above the listing on each Find-a-Doc directory index', function (string $index, string $listing): void {
    $src = File::get(resource_path("views/{$index}"));

    // The disclaimer is included via the single shared partial (not duplicated copy)...
    expect($src)->toContain('partial:components/emergency_disclaimer_911');

    // ...and sits ABOVE the listing (parity: patients saw it before the results).
    /** @var int<0, max> $listingPosition */
    $listingPosition = strpos($src, $listing);

    expect(strpos($src, 'emergency_disclaimer_911'))
        ->toBeLessThan($listingPosition);
})->with([
    'clinical locations' => ['clinical_locations/index.antlers.html', 'partial:clinical_locations/listing'],
    'clinical resources' => ['clinical_resources/index.antlers.html', 'partial:clinical_resources/listing'],
    'provider directory (people mount)' => ['people/provider/index.antlers.html', 'partial:people/provider/listing'],
]);

it('does not add the 911 disclaimer to the areas-of-expertise archive (out of scope)', function (): void {
    $src = File::get(resource_path('views/clinical_expertise/index.antlers.html'));

    expect($src)->not->toContain('emergency_disclaimer_911');
});
