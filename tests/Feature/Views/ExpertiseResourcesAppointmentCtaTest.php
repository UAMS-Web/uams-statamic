<?php

declare(strict_types=1);

use Statamic\Entries\Entry;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry as EntryFacade;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The appointment call to action on Area of Expertise and clinical resource pages
 * (UAMS-Web/uams-statamic#1584).
 *
 * An Area of Expertise renders its own authored `cta` bar(s) when it has any,
 * otherwise the default "Make an Appointment" band, and nothing at all when
 * `cta_inherit_disabled` is on. A clinical resource always renders the default
 * band, except a Provider Spotlight, which carries its own call to action.
 *
 * The expertise cases render `clinical_expertise/_appointment` from an entry
 * scope; the resource cases render the whole show template from an in-memory
 * entry's augmented data, so they fail if the show template stops including the
 * band. The resource collection config lives in the `content/` submodule, so
 * those cases self-skip without it.
 *
 * @command php artisan test --compact tests/Feature/Views/ExpertiseResourcesAppointmentCtaTest.php
 */
beforeEach(function (): void {
    Blink::flush();
});

/**
 * Render the Area of Expertise appointment partial with the given entry scope.
 *
 * @param  array<string, mixed>  $scope
 */
function renderExpertiseAppointment(array $scope): string
{
    return (string) Antlers::parse('{{ partial:clinical_expertise/appointment }}', $scope, true);
}

/**
 * An authored CTA bar, shaped as the `uamsweb_block_cta` block reads it once
 * augmented, with every field the block requires before it renders.
 *
 * @return array<string, mixed>
 */
function authoredExpertiseCtaBar(): array
{
    return [
        'type' => 'new_set',
        'block_heading' => 'Call the Cardiology Clinic',
        // Augmented Bard: a list of sets, each rendered through `sets/{type}`.
        'body' => [['type' => 'text', 'text' => '<p>Our cardiologists see new patients on weekdays.</p>']],
        'action_type' => 'none',
        'layout' => 'cta-bar-centered',
        'size' => 'normal',
        'background_color' => 'bg-auto',
        // The block renders only with a background image (`$block_valid` in uamsweb_block_cta).
        'background_image_query' => true,
        'background_image' => ['url' => '/assets/cta-background.jpg'],
    ];
}

/**
 * Render the clinical resource show template for an in-memory entry authored
 * through the named blueprint.
 *
 * @param  array<string, mixed>  $data
 */
function renderResourceShowForAppointment(string $blueprint, array $data = []): string
{
    if (! Collection::find('clinical_resources')) {
        ContentSubmodule::reportMissing('clinical_resources collection config');
    }

    /** @var Entry $entry */
    $entry = EntryFacade::make();
    /** @var Entry $entry */
    $entry = $entry->collection('clinical_resources');
    /** @var Entry $entry */
    $entry = $entry->blueprint($blueprint);
    /** @var Entry $entry */
    $entry = $entry->locale('health');
    /** @var Entry $entry */
    $entry = $entry->slug('clinical-resource-appointment-render');
    /** @var Entry $entry */
    $entry = $entry->data(['title' => 'Managing Diabetes', ...$data]);
    /** @var array<string, mixed> $scope */
    $scope = $entry->toAugmentedArray();

    return (string) Antlers::parse(
        (string) file_get_contents(resource_path('views/clinical_resources/show.antlers.html')),
        $scope,
        true,
    );
}

it('shows the default appointment band on an area of expertise with no authored call to action', function (): void {
    $out = renderExpertiseAppointment(['cta' => null, 'cta_inherit_disabled' => false]);

    expect(substr_count($out, 'id="appointment-info"'))->toBe(1)
        ->and($out)->toContain('Make an Appointment')
        ->and($out)->toContain('href="tel:501-686-8000"')
        ->and($out)->toContain('(501) 686-8000');
});

it('links the default band to the related locations list when the area of expertise has locations', function (): void {
    $out = renderExpertiseAppointment([
        'cta' => null,
        'clinical_locations' => [['title' => 'UAMS Medical Center', 'url' => '/locations/uams-medical-center']],
    ]);

    expect($out)->toContain('<a href="#locations" data-itemtitle="Contact a clinic directly">contacting a clinic directly</a>');
});

it('renders the clinic wording as plain text when there are no related locations and no locations mount', function (): void {
    // With no related locations the link falls back to the locations collection's mount;
    // the test app has no such mount, so the wording stands without a link.
    $out = renderExpertiseAppointment(['cta' => null]);

    expect($out)->toContain('Request an appointment by contacting a clinic directly or by calling')
        ->and($out)->not->toContain('href="#locations"');
});

it('shows only the authored call to action when the area of expertise has one', function (): void {
    $out = renderExpertiseAppointment(['cta' => [authoredExpertiseCtaBar()], 'cta_inherit_disabled' => false]);

    // The heading and body go through `widont`, which joins the last two words with a no-break space.
    expect($out)->toContain('Call the Cardiology&nbsp;Clinic')
        ->and($out)->toContain('Our cardiologists see new patients on&nbsp;weekdays.')
        ->and($out)->not->toContain('id="appointment-info"')
        ->and($out)->not->toContain('Make an Appointment');
});

it('shows no call to action when cta_inherit_disabled is on and none is authored', function (): void {
    $out = renderExpertiseAppointment(['cta' => null, 'cta_inherit_disabled' => true]);

    expect(trim($out))->toBe('');
});

it('still shows the authored call to action when cta_inherit_disabled is on', function (): void {
    // The opt-out suppresses only what the entry would otherwise be given; its own call to action always renders.
    $out = renderExpertiseAppointment(['cta' => [authoredExpertiseCtaBar()], 'cta_inherit_disabled' => true]);

    expect($out)->toContain('Call the Cardiology&nbsp;Clinic')
        ->and($out)->not->toContain('id="appointment-info"');
});

it('renders the area of expertise show template through the appointment partial', function (): void {
    $source = (string) file_get_contents(resource_path('views/clinical_expertise/show.antlers.html'));

    expect(substr_count($source, '{{ partial:clinical_expertise/appointment }}'))->toBe(1);
});

it('gives the related locations section the anchor the default band links to', function (): void {
    $out = (string) Antlers::parse('{{ partial:clinical_expertise/related }}', [
        'clinical_locations' => [['title' => 'UAMS Medical Center', 'url' => '/locations/uams-medical-center']],
    ], true);

    expect($out)->toContain('<section class="aoe-related-section aoe-related-locations" id="locations">');
});

it('shows the default appointment band on a clinical resource page', function (): void {
    $out = renderResourceShowForAppointment('article', ['article' => '<p>What every patient should know.</p>']);

    expect(substr_count($out, 'id="appointment-info"'))->toBe(1)
        ->and($out)->toContain('Make an Appointment')
        ->and($out)->toContain('href="tel:501-686-8000"');
});

it('does not add the default band to a provider spotlight resource', function (): void {
    $out = renderResourceShowForAppointment('provider_spotlight');

    expect($out)->not->toContain('id="appointment-info"')
        ->and($out)->toContain('Provider Spotlight');
});
