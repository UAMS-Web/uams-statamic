<?php

declare(strict_types=1);

use App\Services\ComputedFields\ProviderSpotlight\ProviderSpotlightComputedValues;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Assets\Asset;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Fields\Field;
use Tests\TestCase;

/**
 * Unit + light-integration coverage for the `provider_spotlight` derived values
 * (UAMS-Web/uams-statamic-archive#1425): the introduction / excerpt prose assembly (across pronoun sets and
 * vowel/consonant clinical titles), the appointment-phone formatting, the
 * featured-image precedence, and the featured-provider resolution. The name /
 * clinical-title RESOLUTION is covered by the provider-people computed suites;
 * here the assembly and precedence are pinned directly.
 *
 * @command php artisan test --compact tests/Feature/ProviderSpotlightComputedValuesTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

function spotlightService(): ProviderSpotlightComputedValues
{
    return app(ProviderSpotlightComputedValues::class);
}

/**
 * An in-memory `clinical_resources` provider-spotlight fixture.
 *
 * @param  array<array-key, mixed>  $data
 */
function spotlightFixture(array $data): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('clinical_resources');
    $entry->locale('default');
    $entry->id($data['id'] ?? 'spotlight-1');
    $entry->slug($data['slug'] ?? 'spotlight-1');
    $entry->data($data);

    return $entry;
}

/**
 * An in-memory `people` provider fixture.
 *
 * @param  array<array-key, mixed>  $data
 */
function spotlightProviderFixture(array $data): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->locale('default');
    $entry->id($data['id'] ?? 'prov-1');
    $entry->slug($data['slug'] ?? 'prov-1');
    $entry->data($data);

    return $entry;
}

it('composes the introduction with the right article, lowercased title, and possessive pronoun', function (string $title, string $gender, string $expected): void {
    // Possessive pronoun matches gender; article matches the title's leading
    // sound; the title is lowercased in the prose even when passed title-cased.
    $possessive = ['Female' => 'her', 'Male' => 'his', 'Other' => 'their'][$gender];
    expect(spotlightService()->composeIntroduction('Dr. Jane Smith', 'Dr. Smith', $title, $possessive))->toBe($expected);
})->with([
    'consonant title, female' => ['Cardiologist', 'Female', 'Get to know Dr. Jane Smith, a cardiologist at UAMS Health. In the Q&A below, Dr. Smith shares what inspired her work in healthcare, her life outside of medicine, and more.'],
    'vowel title, male' => ['Oncologist', 'Male', 'Get to know Dr. Jane Smith, an oncologist at UAMS Health. In the Q&A below, Dr. Smith shares what inspired his work in healthcare, his life outside of medicine, and more.'],
    'vowel title, other' => ['Orthopaedic Surgeon', 'Other', 'Get to know Dr. Jane Smith, an orthopaedic surgeon at UAMS Health. In the Q&A below, Dr. Smith shares what inspired their work in healthcare, their life outside of medicine, and more.'],
    'padded title is trimmed' => ['  Cardiologist  ', 'Female', 'Get to know Dr. Jane Smith, a cardiologist at UAMS Health. In the Q&A below, Dr. Smith shares what inspired her work in healthcare, her life outside of medicine, and more.'],
]);

it('falls back to an opener without a title when the clinical title is empty', function (): void {
    expect(spotlightService()->composeIntroduction('Dr. Jane Smith', 'Dr. Smith', '', 'her'))
        ->toBe('Get to know Dr. Jane Smith of UAMS Health. In the Q&A below, Dr. Smith shares what inspired her work in healthcare, her life outside of medicine, and more.');
});

it('composes the excerpt from name and lowercased title', function (): void {
    expect(spotlightService()->composeExcerpt('Dr. Jane Smith', 'Cardiologist'))
        ->toBe('Meet Dr. Jane Smith, cardiologist at UAMS Health, in this provider spotlight Q&A.');
});

it('composes the excerpt without a title', function (): void {
    expect(spotlightService()->composeExcerpt('Dr. Jane Smith', ''))
        ->toBe('Meet Dr. Jane Smith of UAMS Health in this provider spotlight Q&A.');
});

it('trims a padded clinical title in the excerpt', function (): void {
    expect(spotlightService()->composeExcerpt('Dr. Jane Smith', '  Cardiologist  '))
        ->toBe('Meet Dr. Jane Smith, cardiologist at UAMS Health, in this provider spotlight Q&A.');
});

it('truncates the excerpt to at most 160 characters on a word boundary', function (): void {
    $name = 'Dr. '.str_repeat('Wingutzenheimerschmidt ', 12);
    $excerpt = spotlightService()->composeExcerpt(trim($name), 'interventional cardiologist');

    expect(mb_strlen($excerpt))->toBeLessThanOrEqual(160);
    expect($excerpt)->toEndWith('…');
    // Begins at the start of the summary (kills a start-offset / drop-text mutant).
    expect($excerpt)->toStartWith('Meet Dr. Wingutzenheimerschmidt');
    expect($excerpt)->not->toContain('  ');
});

it('formats a clean US appointment phone for display and an E.164 tel href', function (string $entered): void {
    $spotlight = spotlightFixture(['spotlight_phone' => $entered]);

    expect(spotlightService()->appointmentPhoneDisplay($spotlight))->toBe('501-686-8998');
    expect(spotlightService()->appointmentPhoneHref($spotlight))->toBe('+15016868998');
})->with([
    '10-digit, parenthesized' => ['(501) 686-8998'],
    '11-digit with a leading +1' => ['+1 501-686-8998'],
]);

// A number whose digit count is not a clean US 10 (or leading-1 11) must be
// shown exactly as entered: libphonenumber otherwise *parses* it as a wrong
// foreign number (e.g. "…ext 123" becomes a +501 Belize number) instead of
// throwing, so a bare parse-failure guard would surface a mangled dial target.
it('shows a non-standard appointment phone as entered rather than a mangled foreign number', function (string $entered, string $expectedHref): void {
    $spotlight = spotlightFixture(['spotlight_phone' => $entered]);

    expect(spotlightService()->appointmentPhoneDisplay($spotlight))->toBe($entered);
    expect(spotlightService()->appointmentPhoneHref($spotlight))->toBe($expectedHref);
})->with([
    'carries an extension' => ['501-686-8998 ext 123', '5016868998123'],
    'local seven-digit number' => ['686-8998', '6868998'],
    // Eleven digits are only treated as US when the leading digit is the "1"
    // country code; an eleven-digit number starting with anything else is foreign.
    'eleven digits not starting with a country-code 1' => ['25016868998', '25016868998'],
    'free text with no digits' => ['call the front desk', 'call the front desk'],
]);

it('returns null appointment phone strings when the phone is unset', function (): void {
    expect(spotlightService()->appointmentPhoneDisplay(spotlightFixture([])))->toBeNull();
    expect(spotlightService()->appointmentPhoneHref(spotlightFixture([])))->toBeNull();
});

it('resolves the featured provider from the relationship', function (): void {
    $provider = spotlightProviderFixture(['id' => 'prov-mccoy']);
    Entry::partialMock()->shouldReceive('find')->with('prov-mccoy')->andReturn($provider);

    $resolved = spotlightService()->resolveFeaturedProvider(spotlightFixture(['featured_provider' => ['prov-mccoy']]));

    expect($resolved?->id())->toBe('prov-mccoy');
});

it('returns no provider when the relationship is empty', function (): void {
    expect(spotlightService()->resolveFeaturedProvider(spotlightFixture([])))->toBeNull();
});

it('composes the title with a "Get to Know" prefix and trims the name', function (): void {
    expect(spotlightService()->composeTitle('Dr. Jane Smith'))->toBe('Get to Know Dr. Jane Smith');
    expect(spotlightService()->composeTitle('  Dr. Jane Smith  '))->toBe('Get to Know Dr. Jane Smith');
});

it('composes the slug from a plain name, lowercased and hyphenated', function (string $name, string $expected): void {
    expect(spotlightService()->composeSlug($name))->toBe($expected);
})->with([
    'plain name' => ['Jane Smith', 'get-to-know-jane-smith'],
    'middle initial' => ['Leonard H. McCoy', 'get-to-know-leonard-h-mccoy'],
    'accented name is transliterated' => ['Renée Zoë', 'get-to-know-renee-zoe'],
]);

// The title carries the "Dr." prefix (a spotlight heading); the slug is built
// from the prefix/degree-free name so the URL reads as a plain name.
it('derives the title with the "Dr." prefix and a prefix-free slug from the featured provider', function (): void {
    $provider = spotlightProviderFixture([
        'id' => 'prov-name',
        'credentials' => ['md'],
        'person_name' => [
            'first_name' => ['text' => 'Leonard', 'phonetic_text' => null],
            'no_middle_name' => false,
            'middle_name' => ['text' => 'Horatio', 'phonetic_text' => null],
            'no_nickname' => true,
            'last_name' => ['text' => 'McCoy', 'phonetic_text' => null],
            'no_generational_suffix' => true,
            'generational_suffix' => null,
            'display_format' => 'full_first_middle_initial@ns',
        ],
    ]);
    Entry::partialMock()->shouldReceive('find')->with('prov-name')->andReturn($provider);

    $spotlight = spotlightFixture(['featured_provider' => ['prov-name']]);

    expect(spotlightService()->autoTitle($spotlight))->toBe('Get to Know Dr. Leonard H. McCoy');
    expect(spotlightService()->autoSlug($spotlight))->toBe('get-to-know-leonard-h-mccoy');
});

it('derives no title or slug when the featured provider is unset', function (): void {
    expect(spotlightService()->autoTitle(spotlightFixture([])))->toBeNull();
    expect(spotlightService()->autoSlug(spotlightFixture([])))->toBeNull();
});

it('derives no title or slug when the provider resolves but has no name', function (): void {
    // A resolved provider whose (absent) person_name yields an empty display
    // name — the null-guard must leave the stored title/slug untouched.
    $provider = spotlightProviderFixture(['id' => 'prov-nameless']);
    Entry::partialMock()->shouldReceive('find')->with('prov-nameless')->andReturn($provider);

    $spotlight = spotlightFixture(['featured_provider' => ['prov-nameless']]);

    expect(spotlightService()->autoTitle($spotlight))->toBeNull();
    expect(spotlightService()->autoSlug($spotlight))->toBeNull();
});

it('omits the "Dr." prefix in the title (and slug) for a non-credentialed provider', function (): void {
    // No `md`/`do` credential → no "Dr." prefix; the prefix tracks credentials.
    $provider = spotlightProviderFixture([
        'id' => 'prov-nurse',
        'person_name' => [
            'first_name' => ['text' => 'Jane', 'phonetic_text' => null],
            'no_middle_name' => true,
            'middle_name' => null,
            'no_nickname' => true,
            'last_name' => ['text' => 'Smith', 'phonetic_text' => null],
            'no_generational_suffix' => true,
            'generational_suffix' => null,
            'display_format' => 'full_first_middle_initial@ns',
        ],
    ]);
    Entry::partialMock()->shouldReceive('find')->with('prov-nurse')->andReturn($provider);

    $spotlight = spotlightFixture(['featured_provider' => ['prov-nurse']]);

    expect(spotlightService()->autoTitle($spotlight))->toBe('Get to Know Jane Smith');
    expect(spotlightService()->autoSlug($spotlight))->toBe('get-to-know-jane-smith');
});

it('resolves the featured image by precedence', function (array $spotlightData, array $providerData, string $expected): void {
    $provider = spotlightProviderFixture(['id' => 'prov-img'] + $providerData);
    Entry::partialMock()->shouldReceive('find')->with('prov-img')->andReturn($provider);

    $spotlight = spotlightFixture(['featured_provider' => ['prov-img']] + $spotlightData);

    expect(spotlightService()->imageSource($spotlight))->toBe($expected);
})->with([
    'override wins' => [
        ['spotlight_image_wide' => ['portraits::override.jpg']],
        ['portrait_wide' => ['portraits_wide::wide.jpg'], 'portrait' => ['portraits::head.jpg']],
        'override',
    ],
    'provider wide when no override' => [
        [],
        ['portrait_wide' => ['portraits_wide::wide.jpg'], 'portrait' => ['portraits::head.jpg']],
        'provider_wide',
    ],
    'provider headshot when no wide' => [
        [],
        ['portrait' => ['portraits::head.jpg']],
        'provider_headshot',
    ],
    'none when no image anywhere' => [
        [],
        [],
        'none',
    ],
]);

it('gates the appointment CTA on the provider eligibility', function (array $providerData, bool $expected): void {
    $provider = spotlightProviderFixture(['id' => 'prov-gate'] + $providerData);
    Entry::partialMock()->shouldReceive('find')->with('prov-gate')->andReturn($provider);

    expect(spotlightService()->isBookable(spotlightFixture(['featured_provider' => ['prov-gate']])))->toBe($expected);
})->with([
    'bookable: appointments + accepting, not resident' => [
        ['appointments_query' => true, 'accepting_patients_query' => true, 'resident_query' => false],
        true,
    ],
    'not accepting new patients' => [
        ['appointments_query' => true, 'accepting_patients_query' => false],
        false,
    ],
    'no scheduled appointments' => [
        ['appointments_query' => false, 'accepting_patients_query' => true],
        false,
    ],
    'resident is never bookable' => [
        ['appointments_query' => true, 'accepting_patients_query' => true, 'resident_query' => true],
        false,
    ],
    // true_false values can arrive as strings; the coercion must treat "0" /
    // "false" as false (a naive (bool) cast would make "0" truthy).
    'bookable via string "1"/"true"' => [
        ['appointments_query' => '1', 'accepting_patients_query' => 'true', 'resident_query' => '0'],
        true,
    ],
    'not bookable via string "0"' => [
        ['appointments_query' => 'true', 'accepting_patients_query' => '0'],
        false,
    ],
    'resident via string "1"' => [
        ['appointments_query' => 'true', 'accepting_patients_query' => 'true', 'resident_query' => '1'],
        false,
    ],
]);

it('is not bookable when there is no featured provider', function (): void {
    expect(spotlightService()->isBookable(spotlightFixture([])))->toBeFalse();
});

it('reports the referral requirement from the featured provider', function (bool $stored, bool $expected): void {
    $provider = spotlightProviderFixture(['id' => 'prov-ref', 'referral_required' => $stored]);
    Entry::partialMock()->shouldReceive('find')->with('prov-ref')->andReturn($provider);

    expect(spotlightService()->referralRequired(spotlightFixture(['featured_provider' => ['prov-ref']])))->toBe($expected);
})->with([
    'referral required' => [true, true],
    'no referral required' => [false, false],
]);

it('resolves the provider-spotlight blueprint with the expected fields and no intro or closing field', function (): void {
    $blueprint = Blueprint::find('collections.clinical_resources.provider_spotlight');

    expect($blueprint)->not->toBeNull();

    // The assertion above guarantees a resolved blueprint at this point.
    /** @var Statamic\Fields\Blueprint $blueprint */
    $handles = $blueprint->fields()->all()->keys()->all();

    // The spotlight's own editor fields and lean sidebar.
    expect($handles)->toContain('featured_provider');
    expect($handles)->toContain('spotlight_qa');
    expect($handles)->toContain('spotlight_image_wide');
    expect($handles)->toContain('spotlight_phone');
    expect($handles)->toContain('slug');
    expect($handles)->toContain('date');

    // The introduction is always derived from the provider — there is no editor
    // intro field. There is no closing field and no `type` discriminator.
    expect($handles)->not->toContain('intro');
    expect($handles)->not->toContain('spotlight_closing');
    expect($handles)->not->toContain('closing');
    expect($handles)->not->toContain('type');

    // The featured provider's ontology is derived onto these relationships, which
    // are present (hidden) so the bidirectional sync can carry the back-references.
    expect($blueprint->tabs()->keys()->all())->toContain('relationships');
    expect($handles)->toContain('clinical_providers');
    expect($handles)->toContain('clinical_locations');
    expect($handles)->toContain('clinical_expertise');
    expect($handles)->toContain('clinical_conditions');
    expect($handles)->toContain('clinical_services');

    // The auto-derived title, title_short, and excerpt, and the five
    // provider-derived relationship inputs, are all hidden from editors.
    $hidden = [
        'title', 'title_short', 'excerpt',
        'clinical_providers', 'clinical_locations', 'clinical_expertise', 'clinical_conditions', 'clinical_services',
    ];
    foreach ($hidden as $handle) {
        $field = $blueprint->field($handle);
        expect($field)->toBeInstanceOf(Field::class);
        expect($field instanceof Field ? $field->visibility() : null)->toBe('hidden');
    }
});

/**
 * A named, credentialed provider (`Dr. Leonard H. McCoy`, short `Dr. McCoy`)
 * whose primary specialization resolves to the given patient-facing title, both
 * reachable through Entry::find(). An empty title gives the provider no
 * specialization at all.
 *
 * @param  array<array-key, mixed>  $extra
 */
function spotlightNamedProvider(string $clinicalTitle, array $extra = []): Statamic\Entries\Entry
{
    $specializations = [];
    $finder = Entry::partialMock();

    if ($clinicalTitle !== '') {
        /** @var Statamic\Entries\Entry $specialization */
        $specialization = Entry::make();
        $specialization->collection('clinical_specializations');
        $specialization->id('spec-1');
        $specialization->data(['title_specialization_patient' => $clinicalTitle]);
        $finder->shouldReceive('find')->with('spec-1')->andReturn($specialization);
        $specializations = ['spec-1'];
    }

    $provider = spotlightProviderFixture([
        'id' => 'prov-named',
        'credentials' => ['md'],
        'clinical_specializations' => $specializations,
        'person_name' => [
            'first_name' => ['text' => 'Leonard', 'phonetic_text' => null],
            'no_middle_name' => false,
            'middle_name' => ['text' => 'Horatio', 'phonetic_text' => null],
            'no_nickname' => true,
            'last_name' => ['text' => 'McCoy', 'phonetic_text' => null],
            'no_generational_suffix' => true,
            'generational_suffix' => null,
            'display_format' => 'full_first_middle_initial@ns',
        ],
    ] + $extra);
    $finder->shouldReceive('find')->with('prov-named')->andReturn($provider);

    return $provider;
}

it('derives the introduction from the featured provider\'s names, title and pronoun', function (): void {
    spotlightNamedProvider('Optometrist', ['gender' => 'Male']);

    expect(spotlightService()->autoIntroduction(spotlightFixture(['featured_provider' => ['prov-named']])))
        ->toBe('Get to know Dr. Leonard H. McCoy, an optometrist at UAMS Health. In the Q&A below, Dr. McCoy shares what inspired his work in healthcare, his life outside of medicine, and more.');
});

it('derives the introduction with no title and the neutral pronoun when neither is set', function (): void {
    spotlightNamedProvider('');

    expect(spotlightService()->autoIntroduction(spotlightFixture(['featured_provider' => ['prov-named']])))
        ->toBe('Get to know Dr. Leonard H. McCoy of UAMS Health. In the Q&A below, Dr. McCoy shares what inspired their work in healthcare, their life outside of medicine, and more.');
});

it('derives the excerpt from the featured provider\'s name and title', function (): void {
    spotlightNamedProvider('  Optometrist  ');

    expect(spotlightService()->autoExcerpt(spotlightFixture(['featured_provider' => ['prov-named']])))
        ->toBe('Meet Dr. Leonard H. McCoy, optometrist at UAMS Health, in this provider spotlight Q&A.');
});

it('exposes the featured provider\'s display name and trimmed clinical title', function (): void {
    spotlightNamedProvider('  Optometrist  ');
    $spotlight = spotlightFixture(['featured_provider' => ['prov-named']]);

    expect(spotlightService()->providerName($spotlight))->toBe('Dr. Leonard H. McCoy')
        ->and(spotlightService()->providerTitle($spotlight))->toBe('Optometrist');
});

it('exposes no clinical title when the provider has none', function (): void {
    spotlightNamedProvider('');

    expect(spotlightService()->providerTitle(spotlightFixture(['featured_provider' => ['prov-named']])))->toBeNull();
});

it('derives nothing provider-based when no provider resolves', function (mixed $featured): void {
    Entry::partialMock()->shouldReceive('find')->with('prov-missing')->andReturnNull();
    $spotlight = spotlightFixture(['featured_provider' => $featured]);

    expect(spotlightService()->autoIntroduction($spotlight))->toBeNull()
        ->and(spotlightService()->autoExcerpt($spotlight))->toBeNull()
        ->and(spotlightService()->providerName($spotlight))->toBeNull()
        ->and(spotlightService()->providerTitle($spotlight))->toBeNull()
        ->and(spotlightService()->featuredImage($spotlight))->toBeNull();
})->with([
    'an empty reference string' => [''],
    'an id that resolves to nothing' => ['prov-missing'],
]);

it('resolves the featured image asset by precedence', function (array $spotlightData, array $providerData, string $expectedPath): void {
    $provider = spotlightProviderFixture(['id' => 'prov-img'] + $providerData);
    Entry::partialMock()->shouldReceive('find')->with('prov-img')->andReturn($provider);
    $asset = new Asset;
    Statamic\Facades\Asset::shouldReceive('find')->once()->with($expectedPath)->andReturn($asset);

    $spotlight = spotlightFixture(['featured_provider' => ['prov-img']] + $spotlightData);

    expect(spotlightService()->featuredImage($spotlight))->toBe($asset);
})->with([
    'the override' => [
        ['spotlight_image_wide' => ['portraits::override.jpg']],
        ['portrait_wide' => ['portraits_wide::wide.jpg'], 'portrait' => ['portraits::head.jpg']],
        'portraits::override.jpg',
    ],
    'the provider wide portrait' => [
        [],
        ['portrait_wide' => ['portraits_wide::wide.jpg'], 'portrait' => ['portraits::head.jpg']],
        'portraits_wide::wide.jpg',
    ],
    'the provider headshot' => [
        [],
        ['portrait' => ['portraits::head.jpg']],
        'portraits::head.jpg',
    ],
]);

it('returns no featured image when there is no image anywhere, or the asset does not resolve', function (array $providerData): void {
    $provider = spotlightProviderFixture(['id' => 'prov-img'] + $providerData);
    Entry::partialMock()->shouldReceive('find')->with('prov-img')->andReturn($provider);
    Statamic\Facades\Asset::shouldReceive('find')->andReturnNull();

    expect(spotlightService()->featuredImage(spotlightFixture(['featured_provider' => ['prov-img']])))->toBeNull();
})->with([
    'no image anywhere' => [[]],
    'a headshot whose asset is missing' => [['portrait' => ['portraits::gone.jpg']]],
]);

it('looks the featured provider up once per spotlight id', function (): void {
    $provider = spotlightProviderFixture(['id' => 'prov-memo']);
    Entry::partialMock()->shouldReceive('find')->once()->with('prov-memo')->andReturn($provider);
    $service = spotlightService();
    $spotlight = spotlightFixture(['id' => 'spot-memo', 'featured_provider' => ['prov-memo']]);

    expect($service->resolveFeaturedProvider($spotlight))->toBe($provider)
        ->and($service->resolveFeaturedProvider($spotlight))->toBe($provider);
});

it('remembers a provider that did not resolve', function (): void {
    Entry::partialMock()->shouldReceive('find')->once()->with('prov-gone')->andReturnNull();
    $service = spotlightService();
    $spotlight = spotlightFixture(['id' => 'spot-gone', 'featured_provider' => ['prov-gone']]);

    expect($service->resolveFeaturedProvider($spotlight))->toBeNull()
        ->and($service->resolveFeaturedProvider($spotlight))->toBeNull();
});

it('looks the provider up every time for a spotlight with no id', function (): void {
    $provider = spotlightProviderFixture(['id' => 'prov-no-id']);
    Entry::partialMock()->shouldReceive('find')->twice()->with('prov-no-id')->andReturn($provider);
    $service = spotlightService();

    /** @var Statamic\Entries\Entry $spotlight */
    $spotlight = Entry::make();
    $spotlight->collection('clinical_resources');
    $spotlight->data(['featured_provider' => ['prov-no-id']]);

    expect($spotlight->id())->toBeNull()
        ->and($service->resolveFeaturedProvider($spotlight))->toBe($provider)
        ->and($service->resolveFeaturedProvider($spotlight))->toBe($provider);
});

it('reads the featured provider from a plain string or the first usable item of a list', function (mixed $featured): void {
    $provider = spotlightProviderFixture(['id' => 'prov-str']);
    Entry::partialMock()->shouldReceive('find')->with('prov-str')->andReturn($provider);

    expect(spotlightService()->resolveFeaturedProvider(spotlightFixture(['featured_provider' => $featured])))->toBe($provider);
})->with([
    'a plain string id' => ['prov-str'],
    'a list led by an empty string' => [['', 'prov-str']],
    'a list led by a non-string' => [[5, 'prov-str']],
]);

it('treats an empty override image reference as no override', function (): void {
    $provider = spotlightProviderFixture(['id' => 'prov-img', 'portrait' => ['portraits::head.jpg']]);
    Entry::partialMock()->shouldReceive('find')->with('prov-img')->andReturn($provider);

    expect(spotlightService()->imageSource(spotlightFixture(['featured_provider' => ['prov-img'], 'spotlight_image_wide' => ''])))
        ->toBe('provider_headshot');
});

it('returns no featured image when the asset lookup finds something other than an asset', function (): void {
    $provider = spotlightProviderFixture(['id' => 'prov-img', 'portrait' => ['portraits::head.jpg']]);
    Entry::partialMock()->shouldReceive('find')->with('prov-img')->andReturn($provider);
    Statamic\Facades\Asset::shouldReceive('find')->with('portraits::head.jpg')->andReturn(Mockery::mock(Statamic\Contracts\Assets\Asset::class));

    expect(spotlightService()->featuredImage(spotlightFixture(['featured_provider' => ['prov-img']])))->toBeNull();
});

it('does not require a referral when there is no featured provider', function (): void {
    expect(spotlightService()->referralRequired(spotlightFixture([])))->toBeFalse();
});

it('treats a whitespace-only appointment phone as unset', function (): void {
    $spotlight = spotlightFixture(['spotlight_phone' => '   ']);

    expect(spotlightService()->appointmentPhoneDisplay($spotlight))->toBeNull()
        ->and(spotlightService()->appointmentPhoneHref($spotlight))->toBeNull();
});

it('links the entered phone when its dialable characters cannot be extracted', function (): void {
    // With the JIT off and a backtrack limit of 1, both phone patterns fail and
    // return null; the href then falls back to the entered value. Turning the JIT
    // off at runtime does not reach a pattern another test already compiled with
    // it, so this runs only where PHP starts with the JIT off (Herd on macOS does).
    $jit = ini_get('pcre.jit');
    $limit = ini_get('pcre.backtrack_limit');
    ini_set('pcre.jit', '0');
    ini_set('pcre.backtrack_limit', '1');

    try {
        $href = spotlightService()->appointmentPhoneHref(spotlightFixture(['spotlight_phone' => '(501) 555']));
    } finally {
        ini_set('pcre.jit', (string) $jit);
        ini_set('pcre.backtrack_limit', (string) $limit);
    }

    expect($href)->toBe('(501) 555');
})->skip(fn (): bool => ini_get('pcre.jit') === '1', 'needs PHP started with pcre.jit=0');

it('keeps an excerpt of exactly 160 characters and cuts one of 161', function (int $nameLength, string $expected): void {
    $excerpt = spotlightService()->composeExcerpt(str_repeat('a', $nameLength), '');

    expect($excerpt)->toBe($expected);
})->with([
    '160 characters' => [108, 'Meet '.str_repeat('a', 108).' of UAMS Health in this provider spotlight Q&A.'],
    '161 characters' => [109, 'Meet '.str_repeat('a', 109).' of UAMS Health in this provider spotlight…'],
]);

it('cuts a long excerpt at the last space within 159 characters and trims trailing punctuation', function (string $name): void {
    // The 52 fixed characters around the name make each summary 252 long. The
    // name starts at index 5, so name index 152 is summary index 157.
    expect(spotlightService()->composeExcerpt($name, ''))->toBe('Meet '.str_repeat('a', 152).'…');
})->with([
    'a comma at 157 and a space at 158' => [str_repeat('a', 152).', '.str_repeat('a', 46)],
    'spaces at 157 and 159' => [str_repeat('a', 152).' b '.str_repeat('a', 45)],
]);
