<?php

declare(strict_types=1);

use App\Providers\ValidationServiceProvider;
use App\Rules\After;
use App\Rules\AfterOrEqual;
use App\Rules\Ahaid;
use App\Rules\AsciiIdentifier;
use App\Rules\AssetValidationSatisfied;
use App\Rules\AtomFeedUrl;
use App\Rules\Before;
use App\Rules\BeforeOrEqual;
use App\Rules\BoardCertificationTerm;
use App\Rules\BoxSharedStaticAudioUrl;
use App\Rules\BoxSharedStaticUrl;
use App\Rules\BoxSharedStaticVideoUrl;
use App\Rules\CageCode;
use App\Rules\CmsSpecialtyCode;
use App\Rules\CredentialTypeCertification;
use App\Rules\CssSelectorSyntax;
use App\Rules\DateEquals;
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
 * legacy `Rule` path and the message replacer, and {@see ValidatorAwareRuleProbe}
 * for the `ValidationRule` path. No rule `boot()` registers carries a replacer
 * message, so the replacer is reachable only through a probe.
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
        'after' => [After::class, null, [], []],
        'after_or_equal' => [AfterOrEqual::class, null, [], []],
        'before' => [Before::class, null, [], []],
        'before_or_equal' => [BeforeOrEqual::class, null, [], []],
        'date_equals' => [DateEquals::class, null, [], []],
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
function validationProbeError(string $rule): ?string
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
    $replacers = [];

    foreach (array_keys(validationProviderRegistrations()) as $name) {
        expect($validator->extensions)->toHaveKey($name);

        /** @var Closure $extension */
        $extension = $validator->extensions[$name];
        $captured = (new ReflectionFunction($extension))->getClosureUsedVariables();

        $registered[$name] = [$captured['ruleClass'], $captured['parameterMap'], $captured['constructorArguments']];
        $replacers[$name] = array_key_exists($name, $validator->replacers);
    }

    $expected = array_map(
        fn (array $row): array => [$row[0], $row[2], $row[3]],
        validationProviderRegistrations()
    );

    expect(validationProviderRegistrations())->toHaveCount(78)
        ->and($registered)->toBe($expected)
        ->and(array_unique($replacers))->toBe([array_key_first($replacers) => true]);
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

it('leaves a ValidationRule message as it is', function (): void {
    $validator = Validator::make([], []);

    /** @var Closure $replacer */
    $replacer = $validator->replacers['zip_code'];

    expect($replacer('Kept as given.', 'field', 'zip_code', ['ignored']))->toBe('Kept as given.');
});

it('builds a legacy Rule message from its generic translation', function (): void {
    registerValidationProbe('legacy_probe', LegacyRuleProbe::class);
    LegacyRuleProbe::$passes = false;
    app('translator')->addLines(['validation.legacy_probe' => 'The :attribute is not a probe.'], 'en');

    expect(validationProbeError('legacy_probe'))->toBe('The field is not a probe.');
});

// A rule whose translation is a group of typed messages is called through the
// replacer directly. Through a real validation Laravel formats the group itself
// before the replacer runs, and fails on the array, unless the field carries a
// custom message.
it('picks a legacy Rule message from a translation group by its type', function (array $lines, array $parameters, string $expected): void {
    registerValidationProbe('legacy_probe', LegacyRuleProbe::class);
    app('translator')->addLines($lines, 'en');

    /** @var Closure $replacer */
    $replacer = Validator::make([], [])->replacers['legacy_probe'];

    expect($replacer('Incoming message.', 'field', 'legacy_probe', $parameters))->toBe($expected);
})->with([
    'the message for the type in the second parameter' => [
        ['validation.legacy_probe.typed' => 'Typed :attribute.', 'validation.legacy_probe.default' => 'Default :attribute.'],
        ['x', 'typed'],
        'Typed field.',
    ],
    'the default message for a type with none' => [
        ['validation.legacy_probe.typed' => 'Typed :attribute.', 'validation.legacy_probe.default' => 'Default :attribute.'],
        ['x', 'other'],
        'Default field.',
    ],
    'the default message with no type' => [
        ['validation.legacy_probe.typed' => 'Typed :attribute.', 'validation.legacy_probe.default' => 'Default :attribute.'],
        [],
        'Default field.',
    ],
    // The fallback names `validation.required`, which resolves in no group this
    // site loads, so the key itself comes back. Pinned as found; no rule `boot()`
    // registers reaches this branch.
    'the required key for a group with no default' => [
        ['validation.legacy_probe.typed' => 'Typed :attribute.'],
        ['x', 'other'],
        'validation.required',
    ],
]);

it('builds a legacy Rule message from a translation group when the field carries a custom message', function (): void {
    registerValidationProbe('legacy_probe', LegacyRuleProbe::class);
    LegacyRuleProbe::$passes = false;
    app('translator')->addLines(['validation.legacy_probe.typed' => 'Typed :attribute.'], 'en');

    $errors = Validator::make(['field' => 'value'], ['field' => 'legacy_probe:x,typed'], ['legacy_probe' => 'Custom message.'])->errors();

    expect($errors->first('field'))->toBe('Typed field.');
});

// Without a map, each placeholder takes the parameter at its own position in the
// template, counting :attribute, so a template that opens with :attribute skips
// the first parameter. This pins that behavior; it does not endorse it.
it('fills placeholders by their position in the template when there is no map', function (string $template, string $rule, string $expected): void {
    registerValidationProbe('legacy_probe', LegacyRuleProbe::class);
    LegacyRuleProbe::$passes = false;
    app('translator')->addLines(['validation.legacy_probe' => $template], 'en');

    expect(validationProbeError($rule))->toBe($expected);
})->with([
    'no :attribute' => ['Between :min and :max.', 'legacy_probe:1,5', 'Between 1 and 5.'],
    'an :attribute first' => [':attribute between :min and :max.', 'legacy_probe:1,5,9', 'field between 5 and 9.'],
    'a missing parameter stays a placeholder' => [':attribute up to :max.', 'legacy_probe', 'field up to :max.'],
]);

it('fills placeholders by the parameter map when there is one', function (): void {
    registerValidationProbe('mapped_probe', LegacyRuleProbe::class, [0 => 'low', 2 => 'high']);
    LegacyRuleProbe::$passes = false;
    app('translator')->addLines(['validation.mapped_probe' => ':attribute from :low to :high.'], 'en');

    expect(validationProbeError('mapped_probe:3,x,8'))->toBe('field from 3 to 8.');
});
