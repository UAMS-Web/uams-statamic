<?php

declare(strict_types=1);

use App\Listeners\PreserveGoogleBusinessProfileCategoryGcid;
use App\Providers\EventServiceProvider;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Statamic\Events\TermSaving;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Term;
use Statamic\Facades\User;
use Statamic\Fields\Blueprint as FieldsBlueprint;
use Statamic\Fields\Field;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\withoutMiddleware;

/**
 * Coverage for UAMS-Web/uams-statamic-archive#2613: Google Business Profile category terms carry Google's category id
 * (`gcid`), the key UAMS-Web/uams-statamic-archive#2091 chose because Google renames categories and a slug is editable.
 *
 * The blueprint marks the field read-only, which only stops the editor offering it:
 * `TermsController::update()` merges whatever the request carries. So a listener restores
 * a stored `gcid` on every save, while a term with none may still be given one, which is
 * how a seed or a backfill writes it.
 */
uses(TestCase::class);

/**
 * Slugs of the throwaway terms a test saved, deleted in afterEach.
 *
 * @var array<int, string>
 */
$gbpGcidTestCreatedSlugs = [];

afterEach(function () use (&$gbpGcidTestCreatedSlugs): void {
    foreach ($gbpGcidTestCreatedSlugs as $slug) {
        $term = gbpGcidTestFind($slug)?->term();

        if ($term instanceof TaxonomyTerm) {
            $term->delete();
        }
    }

    $gbpGcidTestCreatedSlugs = [];
});

/**
 * Build an unsaved term with the given data, its original synced as if read from disk.
 *
 * @param  string  $taxonomy  The taxonomy handle
 * @param  array<string, mixed>  $data  The term's stored data
 */
function gbpGcidTestTerm(string $taxonomy, array $data): TaxonomyTerm
{
    $term = Term::make();

    if (! $term instanceof TaxonomyTerm) {
        throw new RuntimeException('Term::make() did not return '.TaxonomyTerm::class.'.');
    }

    $term->taxonomy($taxonomy);
    $term->slug('kirk-'.Str::lower(Str::random(8)));
    gbpGcidTestLocalized($term)->data($data);
    $term->syncOriginal();

    return $term;
}

/**
 * The term's default-locale view, which holds its non-localizable data.
 */
function gbpGcidTestLocalized(TaxonomyTerm $term): LocalizedTerm
{
    $localized = $term->inDefaultLocale();

    if (! $localized instanceof LocalizedTerm) {
        throw new RuntimeException('inDefaultLocale() did not return '.LocalizedTerm::class.'.');
    }

    return $localized;
}

/**
 * Save a throwaway term in the Google Business Profile category taxonomy.
 *
 * @param  array<string, mixed>  $data  The term's stored data
 * @param  array<int, string>  $createdSlugs  By-reference record for afterEach
 */
function gbpGcidTestSaveTerm(array $data, array &$createdSlugs): TaxonomyTerm
{
    $term = gbpGcidTestTerm('google_business_profile_categories', $data);
    $term->save();
    $createdSlugs[] = gbpGcidTestSlug($term);

    return $term;
}

/**
 * A term's slug, as a string.
 */
function gbpGcidTestSlug(TaxonomyTerm $term): string
{
    $slug = $term->slug();

    if (! is_string($slug)) {
        throw new RuntimeException('The term has no slug.');
    }

    return $slug;
}

/**
 * Resolve a category term fresh from the Stache.
 */
function gbpGcidTestFind(string $slug): ?LocalizedTerm
{
    $term = Term::find('google_business_profile_categories::'.$slug);

    return $term instanceof LocalizedTerm ? $term : null;
}

it('gives the category blueprint a read-only, non-localizable gcid field', function (): void {
    $blueprint = Blueprint::find('taxonomies.google_business_profile_categories.google_business_profile_category');

    if (! $blueprint instanceof FieldsBlueprint) {
        throw new RuntimeException('The category blueprint did not resolve.');
    }

    $field = $blueprint->field('gcid');

    if (! $field instanceof Field) {
        throw new RuntimeException('The category blueprint has no gcid field.');
    }

    expect($field->type())->toBe('text')
        ->and($field->visibility())->toBe('read_only')
        ->and($field->isLocalizable())->toBeFalse();
});

it('backfills each existing category with its Google category id', function (string $slug, string $gcid): void {
    $term = gbpGcidTestFind($slug) ?? ContentSubmodule::reportMissing("google_business_profile_categories term {$slug}");

    expect($term->get('gcid'))->toBe($gcid);
})->with([
    ['acupuncture-clinic', 'acupuncture_clinic'],
    ['acupuncturist', 'acupuncturist'],
    ['addiction-treatment-center', 'addiction_treatment_center'],
    ['allergist', 'allergist'],
    ['anesthesiologist', 'anesthesiologist'],
]);

it('is registered for TermSaving', function (): void {
    $listen = (new ReflectionProperty(EventServiceProvider::class, 'listen'))->getValue(new EventServiceProvider(app()));

    expect($listen)->toBeArray()
        ->and(is_array($listen) ? $listen[TermSaving::class] ?? [] : [])->toContain(PreserveGoogleBusinessProfileCategoryGcid::class);
});

it('restores a stored gcid that a save would change', function (): void {
    $term = gbpGcidTestTerm('google_business_profile_categories', ['title' => 'Kirk', 'gcid' => 'allergist']);
    $term->set('gcid', 'anesthesiologist');

    (new PreserveGoogleBusinessProfileCategoryGcid)->handle(new TermSaving($term));

    expect(gbpGcidTestLocalized($term)->get('gcid'))->toBe('allergist');
});

it('restores a stored gcid that a save would remove', function (?string $replacement): void {
    $term = gbpGcidTestTerm('google_business_profile_categories', ['title' => 'Spock', 'gcid' => 'allergist']);
    $term->set('gcid', $replacement);

    (new PreserveGoogleBusinessProfileCategoryGcid)->handle(new TermSaving($term));

    expect(gbpGcidTestLocalized($term)->get('gcid'))->toBe('allergist');
})->with([
    'removed' => [null],
    'emptied' => [''],
]);

it('lets a term without a gcid be given one', function (mixed $original): void {
    $term = gbpGcidTestTerm('google_business_profile_categories', ['title' => 'McCoy', 'gcid' => $original]);
    $term->set('gcid', 'acupuncturist');

    (new PreserveGoogleBusinessProfileCategoryGcid)->handle(new TermSaving($term));

    expect(gbpGcidTestLocalized($term)->get('gcid'))->toBe('acupuncturist');
})->with([
    'absent' => [null],
    'empty' => [''],
]);

it('leaves a gcid key alone on terms in any other taxonomy', function (): void {
    $term = gbpGcidTestTerm('audio_series', ['title' => 'Uhura', 'gcid' => 'allergist']);
    $term->set('gcid', 'anesthesiologist');

    (new PreserveGoogleBusinessProfileCategoryGcid)->handle(new TermSaving($term));

    expect(gbpGcidTestLocalized($term)->get('gcid'))->toBe('anesthesiologist');
});

it('keeps a gcid through a real save, and still saves a new one on a term that had none', function () use (&$gbpGcidTestCreatedSlugs): void {
    $existing = gbpGcidTestSaveTerm(['title' => 'Scotty', 'gcid' => 'allergist'], $gbpGcidTestCreatedSlugs);
    $existing->set('gcid', 'anesthesiologist');
    $existing->save();

    $fresh = gbpGcidTestSaveTerm(['title' => 'Sulu'], $gbpGcidTestCreatedSlugs);
    $fresh->set('gcid', 'acupuncturist');
    $fresh->save();

    expect(gbpGcidTestFind(gbpGcidTestSlug($existing))?->get('gcid'))->toBe('allergist')
        ->and(gbpGcidTestFind(gbpGcidTestSlug($fresh))?->get('gcid'))->toBe('acupuncturist');
});

it('cannot change a gcid through a Control Panel term save, while the rest of the save applies', function (?string $posted) use (&$gbpGcidTestCreatedSlugs): void {
    $term = gbpGcidTestSaveTerm(['title' => 'Chekov', 'gcid' => 'allergist'], $gbpGcidTestCreatedSlugs);
    $slug = gbpGcidTestSlug($term);

    // Annotated before the calls: `User::make()` is `mixed` to PHPStan. The user is never
    // saved, so nothing is written to the tracked `users/` submodule.
    /** @var Statamic\Auth\User $user */
    $user = User::make();
    $user->email('chekov@example.com');
    $user->makeSuper();

    withoutMiddleware(PreventRequestForgery::class);
    actingAs($user);

    $payload = [
        'title' => 'Chekov Renamed',
        'slug' => $slug,
        'published' => true,
        '_localized' => [],
    ];

    if ($posted !== null) {
        $payload['gcid'] = $posted;
    }

    patchJson(route('statamic.cp.taxonomies.terms.update', ['google_business_profile_categories', $slug, 'default']), $payload)
        ->assertOk();

    $stored = gbpGcidTestFind($slug);

    expect($stored?->get('gcid'))->toBe('allergist')
        ->and($stored?->get('title'))->toBe('Chekov Renamed');
})->with([
    'a different id' => ['anesthesiologist'],
    'no value at all' => [null],
]);
