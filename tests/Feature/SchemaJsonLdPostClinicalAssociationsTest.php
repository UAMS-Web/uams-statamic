<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry;
use Statamic\Facades\YAML;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\TestCase;

/**
 * UAMS-Web/uams-statamic-archive#1153 — a post that associates clinical entities (provider / area of expertise / location)
 * must emit them as `@id`-linked entity nodes under the article's `about`, wired on the post
 * `Article` schema_object nodes via `schema_thing_from_relationship` (`clinical_providers`,
 * `areas_of_expertise`, `clinical_locations`).
 *
 * The wiring rides on `post-article` (`81576578…`), so it flows to `post`, `news_release`, and
 * `audio` via the schema-object parent chain; the parentless `video` node (`291a8cc1…`) carries
 * its own copy. Both use `mode: inherit_custom`, so the association blocks MERGE onto — rather
 * than replace — the `subject_matter` `about` inherited from `creativework-type-base`; a
 * structural assertion pins that mode so a future edit to plain `custom` (which would silently
 * drop `subject_matter`) fails the build.
 *
 * The related entities are import-time / purged content, so `Entry::find()` is partially mocked
 * to return in-memory fixtures, which then resolve their own nodes via each collection's cascade
 * (clinical_expertise → MedicalSpecialty, clinical_locations → its base MedicalOrganization/LocalBusiness type, clinical_providers
 * → Person/IndividualPhysician).
 *
 * @command php artisan test --compact tests/Feature/SchemaJsonLdPostClinicalAssociationsTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

/** The `post`/`news_release`/`audio` Article node carrying the UAMS-Web/uams-statamic-archive#1153 associations (via inheritance). */
const POST_ARTICLE_NODE = '81576578-6d83-45a7-88bf-ead06a9443cc';

/** The parentless `video` Article node carrying its own copy of the UAMS-Web/uams-statamic-archive#1153 associations. */
const POST_VIDEO_NODE = '291a8cc1-8658-42ad-a821-40c48d0819ad';

beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();
    app()->instance('request', Request::create('http://uams-statamic.test/', 'GET'));
    Blink::flush();
});

/**
 * An in-memory related entity that resolves to its own schema node via its collection cascade.
 *
 * @param  array<string, mixed>  $data
 */
function fixtureRelated(string $collection, string $blueprint, string $id, array $data): StatamicEntry
{
    /** @var StatamicEntry $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->blueprint($blueprint);
    $entry->locale('health');
    $entry->id($id);
    /** @var string $title */
    $title = $data['title'] ?? $id;
    $entry->slug(Str::slug($title));
    $entry->data($data);

    return $entry;
}

/**
 * Build a post schema node for an in-memory post carrying the given association data, with
 * `Entry::find()` mocked to return the related fixtures the traversal resolves.
 *
 * @param  array<string, mixed>  $data
 * @param  array<string, StatamicEntry>  $findable  id => entry
 * @return array<string, mixed>|null
 */
function buildPostNode(string $nodeId, string $blueprint, array $data, array $findable): ?array
{
    $find = Entry::partialMock();
    foreach ($findable as $id => $entry) {
        $find->shouldReceive('find')->with($id)->andReturn($entry);
    }

    /** @var StatamicEntry $post */
    $post = Entry::make();
    $post->collection('posts');
    $post->blueprint($blueprint);
    $post->locale('health');
    $post->id($data['id'] ?? 'fixture-post');
    $post->slug($data['slug'] ?? 'fixture-post');
    $post->data($data);

    return app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode($nodeId, $post);
}

/**
 * Every @type string emitted under the node's `about`, flattened (a node's @type may be a list).
 *
 * @param  array<string, mixed>|null  $node
 * @return list<string>
 */
function aboutTypes(?array $node): array
{
    /** @var array<int|string, mixed>|null $about */
    $about = $node['about'] ?? null;
    if ($about === null) {
        return [];
    }

    $nodes = array_is_list($about) ? $about : [$about];

    $types = [];
    foreach ($nodes as $n) {
        if (! is_array($n)) {
            continue;
        }
        $type = $n['@type'] ?? null;
        foreach (is_array($type) ? $type : [$type] as $t) {
            if (is_string($t)) {
                $types[] = $t;
            }
        }
    }

    return $types;
}

/**
 * The three fixtures + their findable map, shared across the association tests.
 *
 * @return array{
 *     data: array<string, list<string>>,
 *     findable: array<string, StatamicEntry>,
 * }
 */
function associationFixtures(): array
{
    $provider = fixtureRelated('clinical_providers', 'provider', 'prov-endo', ['title' => 'Dr. Fixture']);
    $aoe = fixtureRelated('clinical_expertise', 'clinical_expertise', 'aoe-endo', ['title' => 'Endocrinology']);
    $location = fixtureRelated('clinical_locations', 'location', 'loc-clinic', [
        'title' => 'UAMS Endocrine Clinic',
    ]);

    return [
        'data' => [
            'clinical_providers' => ['prov-endo'],
            'areas_of_expertise' => ['aoe-endo'],
            'clinical_locations' => ['loc-clinic'],
        ],
        'findable' => ['prov-endo' => $provider, 'aoe-endo' => $aoe, 'loc-clinic' => $location],
    ];
}

it('emits the three clinical-entity associations into the post Article about (#1153)', function () {
    $f = associationFixtures();

    $node = buildPostNode(POST_ARTICLE_NODE, 'post', $f['data'], $f['findable']);

    $types = aboutTypes($node);

    expect($types)->toContain('MedicalSpecialty')   // areas_of_expertise
        ->and($types)->toContain('LocalBusiness')    // clinical_locations (base @type of a nested ref)
        ->and($types)->toContain('IndividualPhysician'); // clinical_providers
});

it('emits the same associations on the parentless video Article node (#1153)', function () {
    $f = associationFixtures();

    $node = buildPostNode(POST_VIDEO_NODE, 'video', $f['data'], $f['findable']);

    $types = aboutTypes($node);

    expect($types)->toContain('MedicalSpecialty')
        ->and($types)->toContain('LocalBusiness')
        ->and($types)->toContain('IndividualPhysician');
});

it('merges the associations onto the inherited subject_matter about rather than replacing it (#1153)', function () {
    // A post that is both about a subject_matter entry (the inherited mapping) AND a
    // clinical area of expertise (a new association) must emit BOTH under `about` —
    // proving `inherit_custom` merged the association rows onto the inherited base.
    $aoe = fixtureRelated('clinical_expertise', 'clinical_expertise', 'aoe-endo', ['title' => 'Endocrinology']);

    // `award` is a real subject_matter entry, so the inherited base
    // resolves to a DefinedTerm node the merge must keep alongside the association.
    // Subject matter is a collection since UAMS-Web/uams-statamic-archive#2822, so `about` stores its entry id.
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'subject_matter');
    $query->where('slug', 'award');
    $subject = $query->first();

    if (! $subject instanceof StatamicEntry) {
        throw new RuntimeException('The award subject_matter entry is missing');
    }

    $node = buildPostNode(
        POST_ARTICLE_NODE,
        'post',
        ['about' => [$subject->id()], 'areas_of_expertise' => ['aoe-endo']],
        ['aoe-endo' => $aoe],
    );

    $types = aboutTypes($node);

    // The association (rows) and the inherited subject_matter emission (base) both survive.
    expect($types)->toContain('MedicalSpecialty')
        ->and($types)->toContain('DefinedTerm');
});

it('omits an association whose field is empty (#1153)', function () {
    // Only an area of expertise is associated; no provider/location fields set.
    $aoe = fixtureRelated('clinical_expertise', 'clinical_expertise', 'aoe-endo', ['title' => 'Endocrinology']);

    $node = buildPostNode(POST_ARTICLE_NODE, 'post', ['areas_of_expertise' => ['aoe-endo']], ['aoe-endo' => $aoe]);

    $types = aboutTypes($node);

    expect($types)->toContain('MedicalSpecialty')
        ->and($types)->not->toContain('IndividualPhysician')
        ->and($types)->not->toContain('LocalBusiness');
});

it('keeps mode inherit_custom so the association blocks merge onto (not replace) inherited subject_matter about (#1153)', function (string $file) {
    $path = __DIR__.'/../../content/collections/schema_objects/default/'.$file;
    /** @var array<string, mixed> $parsed */
    $parsed = YAML::file($path)->parse();

    /** @var array<string, mixed> $schema */
    $schema = $parsed['schema'] ?? [];
    /** @var array<string, mixed> $properties */
    $properties = $schema['properties'] ?? [];
    /** @var array<string, mixed> $about */
    $about = $properties['about'] ?? [];

    /** @var string|null $mode */
    $mode = $about['mode'] ?? null;
    expect($mode)->toBe('inherit_custom');

    // The three association blocks are present, keyed to the association fields.
    /** @var array<int, array<string, mixed>> $blocks */
    $blocks = $about['value'] ?? [];
    $fields = array_map(
        function (array $block): string {
            /** @var array<string, mixed> $value */
            $value = $block['value'] ?? [];
            /** @var string $code */
            $code = $value['code'] ?? '';

            return $code;
        },
        $blocks
    );
    $joined = implode("\n", $fields);

    expect($joined)->toContain('current_field="clinical_providers"')
        ->and($joined)->toContain('current_field="areas_of_expertise"')
        ->and($joined)->toContain('current_field="clinical_locations"');
})->with([
    'post-article.md',
    'posts-collection-video-blueprint-entries-content-article.md',
]);

/*
 * -----------------------------------------------------------------------------
 *  UAMS-Web/uams-statamic-archive#1473 — post schema `about` provider list: PRESERVE (not eligibility-filtered)
 * -----------------------------------------------------------------------------
 * A post's schema/JSON-LD `about` provider list is NOT filtered by clinical-provider
 * eligibility (recorded in docs/DECISIONS.md 2026-07-15, resolving the schema-`about` leg
 * the UAMS-Web/uams-statamic-archive#919 provider-eligibility output filter deferred). `about` is an editorial/historical
 * statement ("this article is about Dr. X"), not a live "who can I see now" directory, and
 * eligibility is a time-variant `people` computed — gating `about` by it would silently drop
 * a validly-referenced subject on an unrelated HR event, degrading the structured data while
 * protecting no one.
 *
 * The decision named two rejected filter routes. This pins the architecturally-plausible one:
 * an `eligible_clinical_providers_only` (or any) filtering tag param on the emission. The
 * rejected resolver-seam route — field-handle special-casing inside the generic
 * `SchemaThingFromRelationshipResolver` — stays backstopped by the
 * `emits the three clinical-entity associations` test above (a resolver gate that dropped
 * providers would fail its `IndividualPhysician` assertion), so it is not re-tested here.
 */

it('pins the post about provider block to the bare unfiltered emission (#1473)', function (string $file) {
    $path = __DIR__.'/../../content/collections/schema_objects/default/'.$file;
    /** @var array<string, mixed> $parsed */
    $parsed = YAML::file($path)->parse();

    /** @var array<string, mixed> $schema */
    $schema = $parsed['schema'] ?? [];
    /** @var array<string, mixed> $properties */
    $properties = $schema['properties'] ?? [];
    /** @var array<string, mixed> $about */
    $about = $properties['about'] ?? [];
    /** @var array<int, array<string, mixed>> $blocks */
    $blocks = $about['value'] ?? [];

    // The `about` block resolving the post `clinical_providers` field.
    $providerBlock = collect($blocks)
        ->map(function (array $block): string {
            /** @var array<string, mixed> $value */
            $value = $block['value'] ?? [];
            /** @var string $code */
            $code = $value['code'] ?? '';

            return $code;
        })
        ->first(fn (string $code): bool => str_contains($code, 'current_field="clinical_providers"'));

    // Pinned to the EXACT bare emission — `mode="direct"` over `clinical_providers`, no extra
    // param. Exact equality rejects ANY added param (`eligible_clinical_providers_only`, a
    // renamed `*_only` filter, a `searchable`/`where` scope, a `current_limit` cap), so the
    // reserved eligibility fallback cannot be slipped in without re-opening UAMS-Web/uams-statamic-archive#1473.
    expect($providerBlock)->toBe('{{ schema_thing_from_relationship mode="direct" current_field="clinical_providers" }}');
})->with([
    'post-article.md',
    'posts-collection-video-blueprint-entries-content-article.md',
]);
