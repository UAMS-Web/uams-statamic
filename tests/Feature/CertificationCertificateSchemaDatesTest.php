<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Term as TermFacade;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\TestCase;

/**
 * Locks the UAMS-Web/uams-statamic-archive#2973 gate on the certification schema template: an
 * assessment-based certificate emits no `expires` or `auditDate`, while every
 * other certifications row keeps both. Each case renders the template's own
 * stored row against a person, with the row's term and dates in the overlay.

The person template's certifications loop hands the row's term over as a
collection of one `LocalizedTerm`, so that is the shape tested, alongside the
bare term. The dates are passed as the stored strings `format_schema_date`
reads.
 *
 * @command  php artisan test --compact tests/Feature/CertificationCertificateSchemaDatesTest.php
 */
uses(TestCase::class);

/**
 * Render one property row of the certification schema template.
 *
 * @param  string  $property  The schema property handle, `expires` or `auditDate`
 * @param  array<string, mixed>  $overlay  Antlers context the row renders with
 * @return string The trimmed rendered value
 */
function certificateSchemaDatesRender(string $property, array $overlay): string
{
    // Read the stored row from the certification template entry
    $template = mustBe(
        Entry::class,
        EntryFacade::find('c3d0e5b2-8f9e-4c5d-ab1e-2f3a4b5c6d7e'),
        'the certification schema template'
    );
    $row = data_get($template->get('schema'), "properties.{$property}.value.0");

    expect($row)->toBeArray("The template has no {$property} row.");

    // Render it against a person, as the person template's certifications loop does
    $person = mustBe(
        EntryContract::class,
        EntryFacade::find('721e22af-79f0-4222-a9b6-51858016a855'),
        'the fixture person'
    );
    $service = app(SchemaOutputService::class);
    $method = (new ReflectionClass($service))->getMethod('resolveSingleReplicatorRowToValue');

    $emittedEntryIds = [];
    $emittedNodeIds = [];
    $materializedFullNodeAtIds = [];

    $out = $method->invokeArgs($service, [
        $row,
        $person,
        [],
        [],
        &$emittedEntryIds,
        &$emittedNodeIds,
        &$materializedFullNodeAtIds,
        $overlay,
        null,
    ]);

    return \is_string($out) ? trim($out) : '';
}

/**
 * Build the overlay for one row, with its term handed over as the collection of
 * terms the person template's loop produces, or as the term itself.
 *
 * @param  string  $slug  The certifications term slug
 * @param  string  $setType  The row's replicator set handle
 * @param  bool  $asCollection  Whether to hand the term over as a collection
 * @return array<string, mixed> The overlay
 */
function certificateSchemaDatesOverlay(string $slug, string $setType, bool $asCollection): array
{
    // Resolve the row's term
    $term = mustBe(
        LocalizedTerm::class,
        TermFacade::find('certifications::'.$slug),
        "the {$slug} term"
    );

    // Wrap it as the loop does, or hand it over bare
    $activity = $asCollection ? collect([$term]) : $term;

    return [
        'activity' => $activity,
        'type' => $setType,
        'activity_date_expires' => '2030-01-31',
        'activity_date_audit' => '2029-06-30',
    ];
}

it('leaves a certificate\'s expiration and audit dates out of the JSON-LD', function (bool $asCollection) {
    $overlay = certificateSchemaDatesOverlay(
        'american-society-for-metabolic-and-bariatric-surgery-fundamentals-for-the-metabolic-and-bariatric-surgery-patient-assessment-based-certificate',
        'other_certification',
        $asCollection
    );

    expect(certificateSchemaDatesRender('expires', $overlay))->toBe('')
        ->and(certificateSchemaDatesRender('auditDate', $overlay))->toBe('');
})->with(['collection of terms' => [true], 'term' => [false]]);

it('keeps the expiration and audit dates of every other kind', function (string $slug, string $setType, bool $asCollection) {
    $overlay = certificateSchemaDatesOverlay($slug, $setType, $asCollection);

    expect(certificateSchemaDatesRender('expires', $overlay))->toBe('2030-01-31')
        ->and(certificateSchemaDatesRender('auditDate', $overlay))->toBe('2029-06-30');
})->with([
    'board certification' => ['american-board-of-orthopaedic-surgery-orthopaedic-surgery', 'board_certification', true],
    'focused practice designation' => ['american-board-of-surgery-metabolic-and-bariatric-surgery-focused-practice-designation', 'other_certification', true],
    'other certification' => ['american-academy-of-nurse-practitioners-certification-board-psychiatric-mental-health-nurse-practitioner', 'other_certification', false],
]);
