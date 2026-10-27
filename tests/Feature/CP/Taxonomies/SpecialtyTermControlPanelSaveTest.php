<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Facades\Term;
use Statamic\Facades\User;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Stache\Stores\Store;
use Statamic\Stache\Stores\TermsStore;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\withoutMiddleware;

/**
 * Coverage for UAMS-Web/uams-statamic-archive#3217: an edited `specialties_non_accredited` term saves through the Control
 * Panel. `TermSubjectMatterValuesTest` runs the blueprint's rules through the validator the
 * Control Panel uses, which covers the rules and not the save: `TermsController::update()`
 * also resolves the blueprint, processes each field and writes the term, and nothing else
 * in the suite takes that path for a specialty term.
 *
 * The term under test is `shoulder-and-elbow-surgery`, whose Control Panel half of UAMS-Web/uams-statamic-archive#3179's
 * criterion closed unverified. It is a real term, so the save writes its file in the test
 * content mirror. The file's bytes are read before each test and written back after it,
 * and the file is quarantined into the `serial` group, so no parallel worker reads the
 * term while it carries the test's value.
 *
 * @command  php artisan test --compact tests/Feature/CP/Taxonomies/SpecialtyTermControlPanelSaveTest.php
 */
uses(TestCase::class, RequiresContentSubmodule::class)->group('serial');

const SPECIALTY_TERM_CP_SAVE_TAXONOMY = 'specialties_non_accredited';
const SPECIALTY_TERM_CP_SAVE_SLUG = 'shoulder-and-elbow-surgery';

/**
 * The path and bytes of the term's file before the test, restored in afterEach.
 *
 * @var array{path: string, bytes: string}|null
 */
$specialtyTermCpSaveOriginal = null;

/**
 * Resolve the term under test fresh from the Stache, in its default locale.
 */
function specialtyTermCpSaveFind(): LocalizedTerm
{
    $term = Term::find(SPECIALTY_TERM_CP_SAVE_TAXONOMY.'::'.SPECIALTY_TERM_CP_SAVE_SLUG);

    if (! $term instanceof LocalizedTerm) {
        throw new RuntimeException('The '.SPECIALTY_TERM_CP_SAVE_SLUG.' term was not found.');
    }

    return $term;
}

/**
 * Forget every cached term in the taxonomy, so the next read comes from the file.
 */
function specialtyTermCpSaveForgetCache(): void
{
    $store = Stache::store('terms');

    if (! $store instanceof TermsStore) {
        throw new RuntimeException('The terms Stache store is not a '.TermsStore::class.'.');
    }

    mustBe(Store::class, $store->store(SPECIALTY_TERM_CP_SAVE_TAXONOMY), 'the taxonomy terms store')->clear();
}

/**
 * Sign in a super user who is never saved, so nothing is written to the `users/` submodule.
 */
function specialtyTermCpSaveSignIn(): void
{
    /** @var Statamic\Auth\User $user */
    $user = User::make();
    $user->email('specialty-term-save@example.com');
    $user->makeSuper();

    withoutMiddleware(PreventRequestForgery::class);
    actingAs($user);
}

/**
 * The payload the Control Panel submits for the term, with `subject_matter` replaced.
 *
 * @param  array<int, string>  $subjectMatter  The entry ids to submit.
 * @return array<string, mixed> The request body.
 */
function specialtyTermCpSavePayload(array $subjectMatter): array
{
    $term = specialtyTermCpSaveFind();

    return [
        'title' => $term->get('title'),
        'slug' => SPECIALTY_TERM_CP_SAVE_SLUG,
        'alternate_name' => $term->get('alternate_name'),
        'specialty_subspecialty_type' => $term->get('specialty_subspecialty_type'),
        'subject_matter' => $subjectMatter,
        'published' => true,
        '_localized' => [],
    ];
}

beforeEach(function () use (&$specialtyTermCpSaveOriginal): void {
    $this->requireContentCollection('clinical_specializations');

    // Record the term file's exact bytes so afterEach can put them back
    $file = specialtyTermCpSaveFind()->term();

    if (! $file instanceof TaxonomyTerm) {
        throw new RuntimeException('The term has no underlying term.');
    }

    $path = mustBeString($file->path(), 'the term file path');
    $bytes = is_file($path) ? file_get_contents($path) : false;

    if ($bytes === false) {
        throw new RuntimeException("Cannot read the term file at {$path}.");
    }

    $specialtyTermCpSaveOriginal = ['path' => $path, 'bytes' => $bytes];
});

afterEach(function () use (&$specialtyTermCpSaveOriginal): void {
    // Put the file back byte for byte, then drop the cached copy of the saved term
    if ($specialtyTermCpSaveOriginal !== null) {
        file_put_contents($specialtyTermCpSaveOriginal['path'], $specialtyTermCpSaveOriginal['bytes']);
        specialtyTermCpSaveForgetCache();
    }

    $specialtyTermCpSaveOriginal = null;
});

it('saves an edited subject_matter on the term through the Control Panel', function (): void {
    $current = specialtyTermCpSaveFind()->get('subject_matter');

    // Pick a clinical specialization the term does not already name, so the save is visible
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'clinical_specializations');

    /** @var Collection<int, StatamicEntry> $entries */
    $entries = $query->get();
    $replacement = $entries
        ->map(fn (StatamicEntry $entry): string => mustBeString($entry->id(), 'an entry id'))
        ->first(fn (string $id): bool => ! in_array($id, is_array($current) ? $current : [], true));

    expect($replacement)->toBeString();

    specialtyTermCpSaveSignIn();

    patchJson(
        route('statamic.cp.taxonomies.terms.update', [SPECIALTY_TERM_CP_SAVE_TAXONOMY, SPECIALTY_TERM_CP_SAVE_SLUG, 'default']),
        specialtyTermCpSavePayload([mustBeString($replacement, 'the replacement id')]),
    )->assertOk();

    // Read the term back from its file rather than from the copy the request cached
    specialtyTermCpSaveForgetCache();

    expect(specialtyTermCpSaveFind()->get('subject_matter'))->toBe([$replacement]);
});

it('refuses a save the blueprint rules reject and names subject_matter', function (): void {
    $before = specialtyTermCpSaveFind()->get('subject_matter');

    specialtyTermCpSaveSignIn();

    patchJson(
        route('statamic.cp.taxonomies.terms.update', [SPECIALTY_TERM_CP_SAVE_TAXONOMY, SPECIALTY_TERM_CP_SAVE_SLUG, 'default']),
        specialtyTermCpSavePayload([]),
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subject_matter']);

    // The refused save wrote nothing
    specialtyTermCpSaveForgetCache();

    expect(specialtyTermCpSaveFind()->get('subject_matter'))->toBe($before);
});
