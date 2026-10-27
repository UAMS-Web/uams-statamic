<?php

declare(strict_types=1);

use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

// Blueprint::in() Blink-caches resolved file paths; flush between tests so a
// stale in-memory path cache can never mask a genuine structure regression.
beforeEach(fn () => Blink::flush());

/*
|--------------------------------------------------------------------------
| Clinical Resource — Document blueprint documents replicator (UAMS-Web/uams-statamic-archive#799)
|--------------------------------------------------------------------------
|
| On the `document` blueprint the `documents` replicator's generic scaffold
| becomes a meaningful, format-specific structure: a `document_formats`
| group holding a single `pdf` set, leaving room for additional file-format
| sets later. The PDF-only limit is surfaced in the set/field display and
| instructions, and still enforced by `mimes:pdf` on the upload.
*/

/**
 * The `documents` replicator's `sets` config (group => set scaffold).
 *
 * @return array{
 *     document_formats: array{
 *         display: string,
 *         sets: array{
 *             pdf: array{
 *                 display: string,
 *                 instructions: string,
 *                 fields: list<array<string, mixed>>,
 *             },
 *         },
 *     },
 * }
 */
function clinicalResourceDocumentsSets(): array
{
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('collections/clinical_resources')->get('document');

    /** @var Field $documents */
    $documents = $blueprint->field('documents');

    /** @var array{document_formats: array{display: string, sets: array{pdf: array{display: string, instructions: string, fields: list<array<string, mixed>>}}}} $sets */
    $sets = $documents->get('sets');

    return $sets;
}

it('names the documents set group and its PDF set meaningfully', function () {
    $sets = clinicalResourceDocumentsSets();

    expect(array_keys($sets))->toBe(['document_formats']);
    expect($sets['document_formats']['display'])->toBe('Document Formats');
    expect(array_keys($sets['document_formats']['sets']))->toBe(['pdf']);
    expect($sets['document_formats']['sets']['pdf']['display'])->toBe('PDF Document');
});

it('surfaces the PDF-only limit in display and instructions, enforced by mimes:pdf', function () {
    $pdf = clinicalResourceDocumentsSets()['document_formats']['sets']['pdf'];

    // The PDF limit is stated for the editor (set + field copy)...
    expect($pdf['instructions'])->toContain('PDF');

    /** @var array{field: array<string, mixed>} $fileSet */
    $fileSet = collect($pdf['fields'])->firstWhere('handle', 'file');
    $file = $fileSet['field'];

    expect($file['display'])->toBe('PDF File');
    expect($file['instructions'])->toContain('.pdf');

    // ...and still enforced by validation, not copy alone.
    expect($file['validate'])->toContain('mimes:pdf');
});

it('requires a title on each PDF document', function () {
    $pdf = clinicalResourceDocumentsSets()['document_formats']['sets']['pdf'];
    /** @var array{field: array<string, mixed>} $titleSet */
    $titleSet = collect($pdf['fields'])->firstWhere('handle', 'title');
    $title = $titleSet['field'];

    expect($title['validate'])->toContain('required');
});

it('requires at least one document and a file on each PDF', function () {
    // The documents replicator is the document blueprint's primary content, and
    // every PDF needs an actual file — both must stay `required`.
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::in('collections/clinical_resources')->get('document');
    /** @var Field $documents */
    $documents = $blueprint->field('documents');
    expect($documents->get('validate'))
        ->toContain('required');

    $pdf = clinicalResourceDocumentsSets()['document_formats']['sets']['pdf'];
    /** @var array{field: array<string, mixed>} $fileSet */
    $fileSet = collect($pdf['fields'])->firstWhere('handle', 'file');
    $file = $fileSet['field'];

    expect($file['validate'])->toContain('required');
});

it('lives only on the document blueprint', function (string $blueprint) {
    /** @var Statamic\Fields\Blueprint $bp */
    $bp = Blueprint::in('collections/clinical_resources')->get($blueprint);
    expect($bp->hasField('documents'))
        ->toBeFalse();
})->with(['article', 'infographic', 'video']);
