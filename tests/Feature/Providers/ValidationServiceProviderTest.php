<?php

declare(strict_types=1);

use App\Providers\ValidationServiceProvider;
use App\Rules\Ahaid;
use App\Rules\AsciiIdentifier;
use App\Rules\AssetValidationSatisfied;
use App\Rules\AtomFeedUrl;
use App\Rules\BoardCertificationTerm;
use App\Rules\BoxSharedStaticAudioUrl;
use App\Rules\BoxSharedStaticUrl;
use App\Rules\BoxSharedStaticVideoUrl;
use App\Rules\CageCode;
use App\Rules\CmsSpecialtyCode;
use App\Rules\CredentialTypeCertification;
use App\Rules\CssSelectorSyntax;
use App\Rules\DepInDeclaredList;
use App\Rules\DunsNumber;
use App\Rules\EntrySchemaTypeIn;
use App\Rules\EpicDepId;
use App\Rules\FeedUrl;
use App\Rules\FirstPartyUrl;
use App\Rules\GlobalLocationNumber;
use App\Rules\GoogleCid;
use App\Rules\GoogleScholarAuthorId;
use App\Rules\GoogleTagManagerContainerId;
use App\Rules\Gs1Modulo10;
use App\Rules\HealthCareProviderTaxonomyCode;
use App\Rules\IdentifierRegex;
use App\Rules\InternationalPhone;
use App\Rules\InternationalPhoneticAlphabet;
use App\Rules\IpedsId;
use App\Rules\Isco08Code;
use App\Rules\IsicRev4Code;
use App\Rules\Iso6523Code;
use App\Rules\Latitude;
use App\Rules\LatLng;
use App\Rules\LeadershipDevelopmentActivitiesTerm;
use App\Rules\LegalEntityIdentifier;
use App\Rules\Longitude;
use App\Rules\Luhn;
use App\Rules\NaicsCode;
use App\Rules\Name;
use App\Rules\NationalPhone;
use App\Rules\Npi;
use App\Rules\OnetSocOccupationCode;
use App\Rules\OpeId;
use App\Rules\Orcid;
use App\Rules\OtherCertificationTerm;
use App\Rules\Pmid;
use App\Rules\ProfessionalDevelopmentActivitiesTerm;
use App\Rules\PubmedAuthorId;
use App\Rules\ResearcherId;
use App\Rules\RssFeedUrl;
use App\Rules\RxCui;
use App\Rules\ScopusAuthorId;
use App\Rules\SmsShortCode;
use App\Rules\SpecialtyTaxonomyTermType;
use App\Rules\SubfieldsAll;
use App\Rules\SubfieldsAny;
use App\Rules\SubfieldsList;
use App\Rules\TaxpayerIdentificationNumber;
use App\Rules\TermsSubsetOf;
use App\Rules\TickerSymbol;
use App\Rules\TriProfileUrl;
use App\Rules\UamsVideoUrl;
use App\Rules\Uei;
use App\Rules\UniqueIdentifier;
use App\Rules\UniqueIdentifierList;
use App\Rules\VaStationId;
use App\Rules\VaStationSubstationId;
use App\Rules\VaSubstationId;
use App\Rules\VimeoUrl;
use App\Rules\XpathSyntax;
use App\Rules\YouTubeUrl;
use App\Rules\ZipCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tests\Support\LegacyRuleProbe;
use Tests\Support\ValidatorAwareRuleProbe;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for {@see ValidationServiceProvider}: what `boot()` registers, and how
 * `registerCustomRule()` turns a rule class into a string rule with its message.
 *
 * The registration table pins every name `boot()` registers, the class it builds,
 * its parameter map, its baked constructor arguments and its Control Panel display
 * name. The class and the two arrays are read from the variables the extension
 * closure captures, because the closure is the only place they are kept.
 *
 * The engine is exercised through two probe rules, {@see LegacyRuleProbe} for the
 * legacy `Rule` path and {@see ValidatorAwareRuleProbe} for the `ValidationRule`
 * path. The provider registers no message replacer (#1002).
 *
 * Mutation: see the closing record on #874.
 *
 * @command  php artisan test --compact tests/Feature/Providers/ValidationServiceProviderTest.php
 */

/**
 * Every rule `boot()` registers, in registration order.
 *
 * @return array<string, array{0: class-string, 1: string|null, 2: array<int, string>, 3: array<int, mixed>}> Rule name to its class, display name, parameter map and constructor arguments
 */
function validationProviderRegistrations(): array
{
    return [
        'international_phone' => [InternationalPhone::class, null, [], []],
        'national_phone' => [NationalPhone::class, null, [], []],
        'ahaid' => [Ahaid::class, 'American Hospital Association (AHA) ID', [], []],
        'cage_code' => [CageCode::class, 'Commercial and Government Entity (CAGE) Code', [], []],
        'cms_specialty_code' => [CmsSpecialtyCode::class, 'Centers for Medicare & Medicaid Services (CMS) Specialty Code', [], []],
        'duns_number' => [DunsNumber::class, 'Dun & Bradstreet Data Universal Numbering System (D-U-N-S) Number', [], []],
        'epic_dep_id' => [EpicDepId::class, 'UAMS Health Epic Department (DEP) ID', [], []],
        'global_location_number' => [GlobalLocationNumber::class, 'Global Location Number (GLN)', [], []],
        'google_business_profile_cid' => [GoogleCid::class, 'Google CID', [], []],
        'google_scholar_author_id' => [GoogleScholarAuthorId::class, 'Google Scholar Author ID', [], []],
        'google_tag_manager_container_id' => [GoogleTagManagerContainerId::class, 'Google Tag Manager Container ID', [], []],
        'gs1_modulo_10' => [Gs1Modulo10::class, 'GS1 Modulo 10', [], []],
        'health_care_provider_taxonomy_code' => [HealthCareProviderTaxonomyCode::class, null, [], []],
        'isco_08_code' => [Isco08Code::class, 'ISCO-08 Code', [], []],
        'ipeds_id' => [IpedsId::class, 'Integrated Postsecondary Education Data System (IPEDS) ID', [], []],
        'isic_rev4_code' => [IsicRev4Code::class, 'International Standard of Industrial Classification of All Economic Activities (ISIC), Revision 4 Code', [], []],
        'iso_6523_code' => [Iso6523Code::class, 'ISO 6523 Code', [], []],
        'legal_entity_identifier' => [LegalEntityIdentifier::class, 'Legal Entity Identifier (LEI)', [], []],
        'luhn' => [Luhn::class, 'Luhn algorithm', [], []],
        'naics_code' => [NaicsCode::class, 'North American Industry Classification System (NAICS) Code', [], []],
        'npi' => [Npi::class, 'National Provider Identifier (NPI)', [], []],
        'onet_soc_occupation_code' => [OnetSocOccupationCode::class, 'O*Net-SOC Occupation Code', [], []],
        'ope_id' => [OpeId::class, 'Office of Postsecondary Education (OPE) ID', [], []],
        'orcid' => [Orcid::class, 'Open Researcher and Contributor ID (ORCID)', [], []],
        'pmid' => [Pmid::class, 'PubMed Identifier (PMID)', [], []],
        'pubmed_author_id' => [PubmedAuthorId::class, 'PubMed Author Identifier (PMID)', [], []],
        'researcher_id' => [ResearcherId::class, 'Web of Science ResearcherID', [], []],
        'rxcui' => [RxCui::class, 'National Library of Medicine RxNorm Concept Unique Identifier (RxCUI)', [], []],
        'scopus_author_id' => [ScopusAuthorId::class, 'Scopus Author ID', [], []],
        'sms_short_code' => [SmsShortCode::class, 'SMS Short Code', [], []],
        'taxpayer_identification_number' => [TaxpayerIdentificationNumber::class, 'U.S. Taxpayer Identification Numbers (TIN) / Employer Identification Numbers (EIN)', [], []],
        'ticker_symbol' => [TickerSymbol::class, 'Stock Ticker Symbol', [], []],
        'uei' => [Uei::class, 'Unique Entity ID (Federal/SAM.gov)', [], []],
        'va_station_id' => [VaStationId::class, 'United States Department of Veterans Affairs (VA) Station Identifier (STA3N)', [], []],
        'va_station_substation_id' => [VaStationSubstationId::class, 'United States Department of Veterans Affairs (VA) Station Identifier (STA3N) or Substation Identifier (STA6A)', [], []],
        'va_substation_id' => [VaSubstationId::class, 'United States Department of Veterans Affairs (VA) Substation Identifier (STA6A)', [], []],
        'latitude' => [Latitude::class, null, [], []],
        'lat_lng' => [LatLng::class, 'Latitude and Longitude', [], []],
        'longitude' => [Longitude::class, null, [], []],
        'name' => [Name::class, null, [], []],
        'atom_feed_url' => [AtomFeedUrl::class, 'Atom Feed URL', [], []],
        'box_shared_static_audio_url' => [BoxSharedStaticAudioUrl::class, 'Box.com shared static audio URL', [], []],
        'box_shared_static_url' => [BoxSharedStaticUrl::class, 'Box.com shared static URL', [], []],
        'box_shared_static_video_url' => [BoxSharedStaticVideoUrl::class, 'Box.com shared static video URL', [], []],
        'feed_url' => [FeedUrl::class, 'Feed URL (Atom or RSS)', [], []],
        'rss_feed_url' => [RssFeedUrl::class, 'RSS Feed URL', [], []],
        'tri_profile_url' => [TriProfileUrl::class, 'UAMS Translational Research Institute (TRI) Profile URL', [], []],
        'first_party_url' => [FirstPartyUrl::class, 'First-party URL (must be a UAMS-owned domain)', [], [true]],
        'non_first_party_url' => [FirstPartyUrl::class, 'External URL (must not be a UAMS-owned domain)', [], [false]],
        'uams_video_url' => [UamsVideoUrl::class, 'UAMS Video URL (YouTube, Vimeo, or Box)', [], []],
        'vimeo_url' => [VimeoUrl::class, 'Vimeo URL', [], []],
        'youtube_url' => [YouTubeUrl::class, 'YouTube URL', [], []],
        'ascii_identifier' => [AsciiIdentifier::class, 'ASCII identifier', [], []],
        'identifier_regex' => [IdentifierRegex::class, null, [], []],
        'subfields_all' => [SubfieldsAll::class, 'Subfields (All)', [], []],
        'subfields_any' => [SubfieldsAny::class, 'Subfields (Any)', [], []],
        'subfields_list' => [SubfieldsList::class, 'Subfields (List)', [], []],
        'dep_in_declared_list' => [DepInDeclaredList::class, 'DEP ID must be in the declared list', ['parent_field'], []],
        'terms_subset_of' => [TermsSubsetOf::class, 'Terms subset of field', ['parent_field'], []],
        'unique_identifier' => [UniqueIdentifier::class, 'Identifier must be unique', [], []],
        'unique_identifier_list' => [UniqueIdentifierList::class, 'Identifiers must be unique', [], []],
        'zip_code' => [ZipCode::class, 'U.S. ZIP Code', [], []],
        'xpath_syntax' => [XpathSyntax::class, 'XPath Syntax', [], []],
        'css_selector_syntax' => [CssSelectorSyntax::class, 'CSS Selector Syntax', [], []],
        'international_phonetic_alphabet' => [InternationalPhoneticAlphabet::class, 'International Phonetic Alphabet (IPA)', [], []],
        'entry_schema_type_in' => [EntrySchemaTypeIn::class, 'Entry schema type', [], []],
        'asset_validation_satisfied' => [AssetValidationSatisfied::class, 'Asset validation satisfied', [], []],
        'board_certification_term' => [BoardCertificationTerm::class, 'Certifications taxonomy term (board certifications scope)', [], []],
        'other_certification_term' => [OtherCertificationTerm::class, 'Certifications taxonomy term (other certifications scope)', [], []],
        'specialty_taxonomy_term_type' => [SpecialtyTaxonomyTermType::class, 'Specialty taxonomy term type (matches specialty_taxonomy_term_type scope)', [], []],
        'professional_development_activity_term' => [ProfessionalDevelopmentActivitiesTerm::class, 'Professional and leadership development activity term (professional scope)', [], []],
        'leadership_development_activity_term' => [LeadershipDevelopmentActivitiesTerm::class, 'Professional and leadership development activity term (leadership scope)', [], []],
        'credential_type_certification' => [CredentialTypeCertification::class, 'Credential term (CTDL credential type Certification)', [], []],
    ];
}

/**
 * Register a rule on a fresh provider, as `boot()` does.
 *
 * @param  string  $ruleName  The rule name
 * @param  class-string  $ruleClass  The rule class
 * @param  array<int, string>|string  $parameterMapOrDisplayName  A parameter map, or a display name
 * @param  array<int, string>  $parameterMap  A parameter map, when a display name is given
 * @param  array<int, mixed>  $constructorArguments  Baked constructor arguments
 */
function registerValidationProbe(
    string $ruleName,
    string $ruleClass,
    array|string $parameterMapOrDisplayName = [],
    array $parameterMap = [],
    array $constructorArguments = []
): ValidationServiceProvider {
    $provider = new ValidationServiceProvider(app());

    (new ReflectionMethod($provider, 'registerCustomRule'))->invoke(
        $provider,
        $ruleName,
        $ruleClass,
        $parameterMapOrDisplayName,
        $parameterMap,
        $constructorArguments
    );

    return $provider;
}

/**
 * The first error a rule string produces for `field`.
 *
 * @param  string  $rule  The rule string
 */
function validationProbeError(string $rule): string
{
    return Validator::make(['field' => 'value'], ['field' => $rule])->errors()->first('field');
}

beforeEach(function (): void {
    $this->savedDisplayNames = ValidationServiceProvider::$staticCustomDisplayNames;

    LegacyRuleProbe::$constructedWith = [];
    LegacyRuleProbe::$passes = true;
    ValidatorAwareRuleProbe::$constructedWith = [];
    ValidatorAwareRuleProbe::$receivedValidator = null;
});

afterEach(function (): void {
    ValidationServiceProvider::$staticCustomDisplayNames = $this->savedDisplayNames;
});

it('registers every rule with its class, parameter map and baked constructor arguments', function (): void {
    $validator = Validator::make([], []);

    $registered = [];

    foreach (array_keys(validationProviderRegistrations()) as $name) {
        expect($validator->extensions)->toHaveKey($name);

        /** @var Closure $extension */
        $extension = $validator->extensions[$name];
        $captured = (new ReflectionFunction($extension))->getClosureUsedVariables();

        $registered[$name] = [$captured['ruleClass'], $captured['parameterMap'], $captured['constructorArguments']];
    }

    $expected = array_map(
        fn (array $row): array => [$row[0], $row[2], $row[3]],
        validationProviderRegistrations()
    );

    expect(validationProviderRegistrations())->toHaveCount(73)
        ->and($registered)->toBe($expected);
});

// On a Control Panel route, Statamic's translator reads every `validation.*` key
// from `statamic::validation`. The app's override there used to replace these five
// lines with arrays, which made Laravel's message formatting throw a TypeError
// (#1047); with the override gone, Statamic's own line is used.
it('renders Statamic\'s own date rule message on a Control Panel route', function (string $rule, string $value, string $expected): void {
    $cp = config('statamic.cp.route');
    app()->instance('request', Request::create('/'.(is_string($cp) ? $cp : 'cp').'/collections'));

    expect(Statamic\Statamic::isCpRoute())->toBeTrue()
        ->and(Validator::make(['field' => $value], ['field' => $rule])->errors()->first('field'))->toBe($expected);
})->with([
    'after' => ['after:2020-01-01', '2019-01-01', 'Must be a date after 2020-01-01.'],
    'after_or_equal' => ['after_or_equal:2020-01-01', '2019-01-01', 'Must be a date after or equal to 2020-01-01.'],
    'before' => ['before:2020-01-01', '2021-01-01', 'Must be a date before 2020-01-01.'],
    'before_or_equal' => ['before_or_equal:2020-01-01', '2021-01-01', 'Must be a date before or equal to 2020-01-01.'],
    'date_equals' => ['date_equals:2020-01-01', '2021-01-01', 'Must be a date equal to 2020-01-01.'],
]);

it('keeps each date rule\'s statamic::validation line a string', function (string $rule): void {
    expect(trans('statamic::validation.'.$rule))->toBeString();
})->with(['after', 'after_or_equal', 'before', 'before_or_equal', 'date_equals']);

it('registers no extension under a Laravel date rule name', function (): void {
    expect(array_keys(Validator::make([], [])->extensions))
        ->not->toContain('after', 'after_or_equal', 'before', 'before_or_equal', 'date_equals');
});

it('stores the display names boot() gives, on the provider and statically', function (): void {
    $expected = array_filter(
        array_map(fn (array $row): ?string => $row[1], validationProviderRegistrations()),
        fn (?string $displayName): bool => $displayName !== null
    );

    /** @var ValidationServiceProvider $provider */
    $provider = app()->getProvider(ValidationServiceProvider::class);

    expect($provider->getCustomDisplayNames())->toBe($expected)
        ->and(ValidationServiceProvider::getStaticCustomDisplayNames())->toBe($expected)
        ->and($expected)->toHaveCount(66);
});

it('keeps a display name only when the third argument is a string', function (): void {
    $named = registerValidationProbe('legacy_probe', LegacyRuleProbe::class, 'Legacy Probe');
    $mapped = registerValidationProbe('mapped_probe', LegacyRuleProbe::class, ['limit']);

    expect($named->getCustomDisplayNames())->toBe(['legacy_probe' => 'Legacy Probe'])
        ->and(ValidationServiceProvider::getStaticCustomDisplayNames())->toHaveKey('legacy_probe', 'Legacy Probe')
        ->and($mapped->getCustomDisplayNames())->toBe([])
        ->and(ValidationServiceProvider::getStaticCustomDisplayNames())->not->toHaveKey('mapped_probe');
});

it('passes and fails a legacy Rule as the rule decides', function (bool $passes): void {
    registerValidationProbe('legacy_probe', LegacyRuleProbe::class);
    LegacyRuleProbe::$passes = $passes;

    expect(Validator::make(['field' => 'value'], ['field' => 'legacy_probe'])->passes())->toBe($passes);
})->with(['passing' => [true], 'failing' => [false]]);

it('builds the rule from baked arguments followed by the rule parameters', function (): void {
    registerValidationProbe('legacy_probe', LegacyRuleProbe::class, [], [], ['baked', true]);

    Validator::make(['field' => 'value'], ['field' => 'legacy_probe:first,second'])->passes();

    expect(LegacyRuleProbe::$constructedWith)->toBe(['baked', true, 'first', 'second']);
});

it('builds the rule from the mapped parameters only', function (): void {
    registerValidationProbe('mapped_probe', LegacyRuleProbe::class, [0 => 'first', 2 => 'third']);

    Validator::make(['field' => 'value'], ['field' => 'mapped_probe:a,b,c,d'])->passes();

    expect(LegacyRuleProbe::$constructedWith)->toBe(['a', 'c']);
});

it('maps parameters to the positions the map names, and null where one is missing', function (array $parameters, array $map, array $expected): void {
    $provider = new ValidationServiceProvider(app());

    expect((new ReflectionMethod($provider, 'mapParameters'))->invoke($provider, $parameters, $map))->toBe($expected);
})->with([
    'no map keeps the parameters' => [['a', 'b'], [], ['a', 'b']],
    'a map keeps only its positions' => [['a', 'b', 'c'], [0 => 'first', 2 => 'third'], [0 => 'a', 2 => 'c']],
    'a missing parameter maps to null' => [['a'], [0 => 'first', 1 => 'second'], [0 => 'a', 1 => null]],
]);

it('hands a validator-aware rule the validator running it, and passes', function (): void {
    registerValidationProbe('aware_probe', ValidatorAwareRuleProbe::class, [], [], ['baked']);

    $validator = Validator::make(['field' => 'value'], ['field' => 'aware_probe:given']);

    expect($validator->passes())->toBeTrue()
        ->and(ValidatorAwareRuleProbe::$receivedValidator)->toBe($validator)
        ->and(ValidatorAwareRuleProbe::$constructedWith)->toBe(['baked', 'given']);
});

it('reports a ValidationRule failure with the message the rule passes to $fail', function (): void {
    expect(Validator::make(['field' => '1234'], ['field' => 'zip_code'])->errors()->first('field'))
        ->toBe(trans('statamic::validation.zip_code'));
});

// UAMS-Web/uams-statamic#1002 retired the provider's message replacer (#1014,
// option B). A legacy `Rule` that fails by name now reads Laravel's own
// `validation.<name>` line, with Laravel's own placeholder replacement: the
// rule's `message()` is not read, and no parameter is placed by position.
it('reports a legacy Rule failure with its translation line, filling only what Laravel fills', function (string $template, string $rule, string $expected): void {
    registerValidationProbe('legacy_probe', LegacyRuleProbe::class);
    LegacyRuleProbe::$passes = false;
    app('translator')->addLines(['validation.legacy_probe' => $template], 'en');

    expect(validationProbeError($rule))->toBe($expected);
})->with([
    'the attribute' => ['The :attribute is not a probe.', 'legacy_probe', 'The field is not a probe.'],
    'parameters are not placed' => [':attribute between :min and :max.', 'legacy_probe:1,5', 'field between :min and :max.'],
]);

it('registers no message replacer for any rule', function (): void {
    registerValidationProbe('legacy_probe', LegacyRuleProbe::class);

    expect(Validator::make([], [])->replacers)->toBe([]);
});

// The five date names are Laravel's own rules and the provider registers none of
// them (#1016, #1047), so Laravel's validator and its own replacer handle them.
it('lets Laravel fill the date in a date rule failure', function (string $rule, string $value, string $expected): void {
    expect(Validator::make(['field' => $value], ['field' => $rule])->errors()->first('field'))->toBe($expected);
})->with([
    'after' => ['after:2020-01-01', '2019-01-01', 'The field field must be a date after 2020-01-01.'],
    'after_or_equal' => ['after_or_equal:2020-01-01', '2019-01-01', 'The field field must be a date after or equal to 2020-01-01.'],
    'before' => ['before:2020-01-01', '2021-01-01', 'The field field must be a date before 2020-01-01.'],
    'before_or_equal' => ['before_or_equal:2020-01-01', '2021-01-01', 'The field field must be a date before or equal to 2020-01-01.'],
    'date_equals' => ['date_equals:2020-01-01', '2021-01-01', 'The field field must be a date equal to 2020-01-01.'],
]);
