<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Tests\TestCase;

/**
 * The two rendered surfaces of the `?provider=` archive filter (UAMS-Web/uams-statamic-archive#1692): the
 * resource-type control that has to carry an active provider filter across every
 * tab, and the clinical provider lens's "more resources" CTA.
 *
 * Plain-array fixtures — the scope's own behavior is covered by
 * `ClinicalResourcesForProviderTest`, and the URL by
 * `ProviderResourcesArchiveUrlResolverTest`. What these pin is the markup.
 *
 * @command php artisan test --compact tests/Feature/Views/ClinicalResourcesProviderFilterViewTest.php
 */
uses(TestCase::class);

/**
 * Render the resource-type control with the given scope.
 *
 * Named distinctly from `ClinicalResourcesTemplatesTest`'s own
 * `renderResourceTypeFilter()`: two Pest files declaring the same top-level function
 * fatal the whole run with `Cannot redeclare` the moment they land in one worker
 * process, and each file passes when run alone.
 * `scripts/check-parallel-safe-tests.php` is the guard.
 *
 * @param  array<string, mixed>  $scope
 */
function renderProviderAwareTypeFilter(array $scope = []): string
{
    return (string) Antlers::parse('{{ partial:clinical_resources/type_filter }}', $scope + [
        'current_url' => '/health/resources',
    ]);
}

// --- The type control carries the provider filter -----------------------------

it('leaves every type tab unqualified when no provider filter is active', function (): void {
    $html = renderProviderAwareTypeFilter();

    expect($html)->toContain('href="/health/resources"')
        ->and($html)->toContain('href="/health/resources?type=article"')
        ->and($html)->not->toContain('provider=');
});

it('carries an active provider filter across every type tab', function (): void {
    // Without this the first type click silently widens the archive from one
    // provider's resources to the whole site's, which reads as a bug to a reader
    // who arrived from a provider profile.
    $html = renderProviderAwareTypeFilter(['filter_provider' => 'mccoy']);

    expect($html)->toContain('href="/health/resources?provider=mccoy"')
        ->and($html)->toContain('href="/health/resources?type=article&amp;provider=mccoy"')
        ->and($html)->toContain('href="/health/resources?type=video&amp;provider=mccoy"');
});

it('keeps the All tab on the provider rather than clearing both filters', function (): void {
    // "All" clears the TYPE. Clearing the provider too is the job of the notice's
    // own "Show all clinical resources" link, which is a different affordance.
    $html = renderProviderAwareTypeFilter(['filter_provider' => 'mccoy', 'filter_type' => 'video']);

    expect($html)->toContain('href="/health/resources?provider=mccoy"');
});

it('escapes a provider slug arriving from the query string', function (): void {
    // The slug is request input rendered into an href, and Antlers is raw by default.
    $html = renderProviderAwareTypeFilter(['filter_provider' => '" onmouseover=alert(1) x="']);

    expect($html)->not->toContain('" onmouseover=alert(1)')
        ->and($html)->not->toContain('javascript:');
});

// --- The provider lens CTA -----------------------------------------------------

/**
 * Render the clinical provider lens's resources section with the given scope.
 *
 * @param  array<string, mixed>  $scope
 */
function renderProviderResourcesSection(array $scope = []): string
{
    return (string) Antlers::parse('{{ partial:people/provider/profile }}', $scope + [
        'title' => 'Leonard McCoy',
        'display_name_full_with_credentials' => ['text' => 'Leonard McCoy, M.D.'],
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy'],
        'clinical_resources' => [
            ['id' => 'guide', 'title' => 'A Guide', 'url' => '/health/resources/guide'],
        ],
    ]);
}

it('renders the more-resources CTA pointing at the filtered archive', function (): void {
    $html = renderProviderResourcesSection([
        'provider_resources_url' => '/health/resources?provider=mccoy',
    ]);

    expect($html)->toContain('provider-resources-more')
        ->and($html)->toContain('href="/health/resources?provider=mccoy"')
        ->and($html)->toContain('More Resources From Dr. McCoy');
});

it('renders no CTA when there is no archive to link to', function (): void {
    // An unmounted collection or an unpublished landing resolves to '' — no link
    // beats a link to a 404, the same call UAMS-Web/uams-statamic-archive#1684 made before the filter existed.
    $html = renderProviderResourcesSection(['provider_resources_url' => '']);

    expect($html)->toContain('profile-resources')
        ->and($html)->not->toContain('provider-resources-more');
});

it('renders no resources section, and so no CTA, for a provider with no resources', function (): void {
    $html = (string) Antlers::parse('{{ partial:people/provider/profile }}', [
        'title' => 'Leonard McCoy',
        'display_name_full_with_credentials' => ['text' => 'Leonard McCoy, M.D.'],
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy'],
        'clinical_resources' => [],
        'provider_resources_url' => '/health/resources?provider=mccoy',
    ]);

    expect($html)->not->toContain('profile-resources')
        ->and($html)->not->toContain('provider-resources-more');
});

it('escapes the provider name in the CTA label', function (): void {
    $html = renderProviderResourcesSection([
        'provider_resources_url' => '/health/resources?provider=mccoy',
        'display_name_short_with_prefix' => ['text' => 'Dr. McCoy<svg/onload=alert(1)>'],
    ]);

    expect($html)->toContain('provider-resources-more')
        ->and($html)->not->toContain('<svg/onload');
});
