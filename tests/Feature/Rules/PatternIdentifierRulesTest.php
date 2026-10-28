<?php

declare(strict_types=1);

use App\Rules\Ahaid;
use App\Rules\BaseRules\BaseValidationRule;
use App\Rules\CageCode;
use App\Rules\CmsSpecialtyCode;
use App\Rules\GoogleCid;
use App\Rules\GoogleScholarAuthorId;
use App\Rules\GoogleTagManagerContainerId;
use App\Rules\HealthCareProviderTaxonomyCode;
use App\Rules\IpedsId;
use App\Rules\Isco08Code;
use App\Rules\IsicRev4Code;
use App\Rules\Iso6523Code;
use App\Rules\LegalEntityIdentifier;
use App\Rules\NaicsCode;
use App\Rules\OnetSocOccupationCode;
use App\Rules\OpeId;
use App\Rules\Orcid;
use App\Rules\PubmedAuthorId;
use App\Rules\ResearcherId;
use App\Rules\ScopusAuthorId;
use App\Rules\TaxpayerIdentificationNumber;
use App\Rules\TickerSymbol;
use App\Rules\Uei;
use App\Rules\VaStationSubstationId;
use App\Rules\VaSubstationId;
use Tests\TestCase;

uses(TestCase::class);

covers(
    Ahaid::class,
    CageCode::class,
    CmsSpecialtyCode::class,
    GoogleCid::class,
    GoogleScholarAuthorId::class,
    GoogleTagManagerContainerId::class,
    HealthCareProviderTaxonomyCode::class,
    IpedsId::class,
    Isco08Code::class,
    IsicRev4Code::class,
    Iso6523Code::class,
    LegalEntityIdentifier::class,
    NaicsCode::class,
    OnetSocOccupationCode::class,
    OpeId::class,
    Orcid::class,
    PubmedAuthorId::class,
    ResearcherId::class,
    ScopusAuthorId::class,
    TaxpayerIdentificationNumber::class,
    TickerSymbol::class,
    Uei::class,
    VaStationSubstationId::class,
    VaSubstationId::class,
);

/**
 * Behavior of the identifier rules whose whole check is one anchored pattern and
 * one message: each accepts the values its documented format admits, and rejects
 * the values one step outside it (a character short or over, a character from the
 * wrong class, a lowercase letter, a trailing newline) with its own message.
 *
 * @command  php artisan test --compact tests/Feature/Rules/PatternIdentifierRulesTest.php
 */

/**
 * Each rule under test, with its message key, values it accepts and values it
 * rejects.
 *
 * @return array<class-string<BaseValidationRule>, array{key: string, valid: list<string>, invalid: list<string>}>
 */
function patternIdentifierRules(): array
{
    return [
        Ahaid::class => [
            'key' => 'statamic::validation.ahaid',
            'valid' => ['1234567', '0000000'],
            'invalid' => ['123456', '12345678', '123456A', "1234567\n"],
        ],
        CageCode::class => [
            'key' => 'statamic::validation.cage_code',
            'valid' => ['1A2B3', '0Z9Z9', '12345'],
            'invalid' => ['1I2B3', '1O2B3', 'A12B3', '1a2b3', '1A2BC', '1A2B', '1A2B34'],
        ],
        CmsSpecialtyCode::class => [
            'key' => 'statamic::validation.cms_specialty_code',
            'valid' => ['01', 'B4'],
            'invalid' => ['1', '012', 'b4', 'BB'],
        ],
        GoogleCid::class => [
            'key' => 'statamic::validation.google_business_profile_cid',
            'valid' => ['12345678901234', '12345678901234567890'],
            'invalid' => ['1234567890123', '123456789012345678901', '1234567890123A'],
        ],
        GoogleScholarAuthorId::class => [
            'key' => 'statamic::validation.google_scholar_author_id',
            'valid' => ['qc6CJjYAAAAJ', '-_aZ09-_aZ09'],
            'invalid' => ['qc6CJjYAAAA', 'qc6CJjYAAAAJX', 'qc6CJjYAAA.J'],
        ],
        GoogleTagManagerContainerId::class => [
            'key' => 'statamic::validation.google_tag_manager_container_id',
            'valid' => ['GTM-ABC123', 'GTM-ABCD1234'],
            'invalid' => ['GTM-ABC12', 'GTM-ABCDE1234', 'gtm-ABC123', 'GTM-abc123', 'ABC123'],
        ],
        HealthCareProviderTaxonomyCode::class => [
            'key' => 'statamic::validation.health_care_provider_taxonomy_code',
            'valid' => ['207Q00000X', '208VP0014X'],
            'invalid' => ['207Q00000x', '207q00000X', '207Q0000X', '207Q000000X', '20AQ00000X'],
        ],
        IpedsId::class => [
            'key' => 'statamic::validation.ipeds_id',
            'valid' => ['106245'],
            'invalid' => ['10624', '1062451', '10624A'],
        ],
        Isco08Code::class => [
            'key' => 'statamic::validation.isco_08_code',
            'valid' => ['2', '2211'],
            'invalid' => ['22111', '22A1'],
        ],
        IsicRev4Code::class => [
            'key' => 'statamic::validation.isic_rev4_code',
            'valid' => ['A', 'U', '01', '0111'],
            'invalid' => ['V', 'a', '1', '01111', 'AB', 'A1'],
        ],
        Iso6523Code::class => [
            'key' => 'statamic::validation.iso6523_code',
            'valid' => ['0088:5012345678900', '0060:123456789:ABC', '0088:'.str_repeat('A', 35)],
            'invalid' => ['088:1', '0088:', '0088:abc', '0088:A:B:C', '0088:'.str_repeat('A', 36), '0088-1'],
        ],
        LegalEntityIdentifier::class => [
            'key' => 'statamic::validation.legal_entity_identifier',
            'valid' => ['5493001KJTIIGC8Y1R12'],
            'invalid' => ['5493001kjtiigc8y1r12', '5493001KJTIIGC8Y1R1', '5493001KJTIIGC8Y1RAB'],
        ],
        NaicsCode::class => [
            'key' => 'statamic::validation.naics_code',
            'valid' => ['62', '622110'],
            'invalid' => ['6', '6221101', '62A'],
        ],
        OnetSocOccupationCode::class => [
            'key' => 'statamic::validation.onet_soc_occupation_code',
            'valid' => ['29-1141.00'],
            'invalid' => ['29-1141', '29-1141.0', '291141.00', '29-1141x00'],
        ],
        OpeId::class => [
            'key' => 'statamic::validation.ope_id',
            'valid' => ['00110800'],
            'invalid' => ['0011080', '001108000', '0011080A'],
        ],
        Orcid::class => [
            'key' => 'statamic::validation.orcid',
            'valid' => ['0000-0002-1825-0097', '0000-0002-1694-233X'],
            'invalid' => ['0000-0002-1694-233x', '0000000218250097', '0000-0002-1825-00970', '0000-002-1825-0097'],
        ],
        PubmedAuthorId::class => [
            'key' => 'statamic::validation.pubmed.pubmed_author_id',
            'valid' => ['Smith J', 'Smith, J', "O'Brien-Lee AB", 'van der Berg J'],
            'invalid' => ['Smith', 'Smith1 J', 'Smith  J', 'Smith,J', 'Smith J '],
        ],
        ResearcherId::class => [
            'key' => 'statamic::validation.researcher_id',
            'valid' => ['A-1234-2009', 'ABC-1234-1999'],
            'invalid' => ['ABCD-1234-2009', 'A-1234-2109', 'a-1234-2009', 'A-123-2009'],
        ],
        ScopusAuthorId::class => [
            'key' => 'statamic::validation.scopus_author_id',
            'valid' => ['7004212771', '123456789012'],
            'invalid' => ['0123456789', '123456789', '1234567890123'],
        ],
        TaxpayerIdentificationNumber::class => [
            'key' => 'statamic::validation.taxpayer_identification_number',
            'valid' => ['71-0123456'],
            'invalid' => ['710123456', '71-012345', '7-10123456'],
        ],
        TickerSymbol::class => [
            'key' => 'statamic::validation.ticker_symbol',
            'valid' => ['A', 'GOOGL', 'BRK.B', 'ABC.P', 'ABC.PK', 'ABC.SC', 'ABC.NM'],
            'invalid' => ['GOOGLE', 'brk.b', 'BRK.', 'BRK.BB', 'ABC.PKX'],
        ],
        Uei::class => [
            'key' => 'statamic::validation.uei',
            'valid' => ['J1XYZ12345A1', 'abcdefghijkl'],
            'invalid' => ['J1XYZ12345A', 'J1XYZ12345A12', 'J1XYZ-2345A1'],
        ],
        VaStationSubstationId::class => [
            'key' => 'statamic::validation.veterans_affairs.station_substation_id',
            'valid' => ['598', '598A4C'],
            'invalid' => ['59', '5984', '598a4c', '598A4CD'],
        ],
        VaSubstationId::class => [
            'key' => 'statamic::validation.veterans_affairs.substation_id',
            'valid' => ['598A4C', '598001'],
            'invalid' => ['598', '598a4c', '598A4CD'],
        ],
    ];
}

/**
 * One dataset case per rule, carrying its message key and both value lists.
 *
 * @return array<string, array{class-string<BaseValidationRule>, string, list<string>, list<string>}>
 */
function patternIdentifierCases(): array
{
    $cases = [];

    // Label each case with the rule's short name so a failure names it
    foreach (patternIdentifierRules() as $class => $rule) {
        $cases[class_basename($class)] = [$class, $rule['key'], $rule['valid'], $rule['invalid']];
    }

    return $cases;
}

/**
 * Run a rule and return every message it failed with.
 *
 * @param  string  $class  The rule to run.
 * @return list<mixed>
 */
function patternIdentifierMessages(string $class, mixed $value): array
{
    $messages = [];

    mustBe(BaseValidationRule::class, app($class), 'rule')->validate('identifier', $value, function (mixed $message) use (&$messages): void {
        $messages[] = $message;
    });

    return $messages;
}

it('accepts every value in the documented format', function (string $class, string $key, array $valid, array $invalid): void {
    foreach ($valid as $value) {
        expect(patternIdentifierMessages($class, $value))->toBe([], 'accepts '.json_encode($value));
    }
})->with(patternIdentifierCases());

it('rejects every value outside the documented format with the rule message', function (string $class, string $key, array $valid, array $invalid): void {
    // The message key resolves to a translation rather than echoing the key back
    expect(__($key))->not->toBe($key);

    foreach ($invalid as $value) {
        expect(patternIdentifierMessages($class, $value))->toBe([__($key)], 'rejects '.json_encode($value));
    }
})->with(patternIdentifierCases());
