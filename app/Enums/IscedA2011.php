<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Attributes\IscedInfo;
use App\Services\EnumGeneration\EnumDataUtils;

/**
 * International Standard Classification of Education 2011, Educational Attainment (ISCED-A 2011) code list.
 *
 * Source: UNESCO Institute for Statistics — ISCED 2011, Annex III (coding of educational attainment)
 *   https://uis.unesco.org/sites/default/files/documents/international-standard-classification-of-education-isced-2011-en.pdf
 *
 * Codes use the UIS digit scheme (1–3 digit strings). Top-tier codes are single digits.
 *
 * Generated: 2026-05-20
 * Regenerate: php artisan generate:isced-enums
 *
 * Case names use the prefix ISCEDA_ (backing value is the canonical code).
 * Total cases: 66
 */
enum IscedA2011: string
{
    #[IscedInfo(
        code: '0',
        label: 'Less than primary education',
        parent: null,
        ancestors: [],
        children: ['01', '02', '03'],
        descendants: ['01', '02', '03', '010', '020', '030'],
        tier: 1,
    )]
    case ISCEDA_0 = '0';

    #[IscedInfo(
        code: '1',
        label: 'Primary education',
        parent: null,
        ancestors: [],
        children: ['10'],
        descendants: ['10', '100'],
        tier: 1,
    )]
    case ISCEDA_1 = '1';

    #[IscedInfo(
        code: '2',
        label: 'Lower secondary education',
        parent: null,
        ancestors: [],
        children: ['24', '25'],
        descendants: ['24', '25', '242', '243', '244', '252', '253', '254'],
        tier: 1,
    )]
    case ISCEDA_2 = '2';

    #[IscedInfo(
        code: '3',
        label: 'Upper secondary education',
        parent: null,
        ancestors: [],
        children: ['34', '35'],
        descendants: ['34', '35', '342', '343', '344', '352', '353', '354'],
        tier: 1,
    )]
    case ISCEDA_3 = '3';

    #[IscedInfo(
        code: '4',
        label: 'Post-secondary non-tertiary education',
        parent: null,
        ancestors: [],
        children: ['44', '45'],
        descendants: ['44', '45', '443', '444', '453', '454'],
        tier: 1,
    )]
    case ISCEDA_4 = '4';

    #[IscedInfo(
        code: '5',
        label: 'Short-cycle tertiary education',
        parent: null,
        ancestors: [],
        children: ['54', '55', '56'],
        descendants: ['54', '55', '56', '540', '550', '560'],
        tier: 1,
    )]
    case ISCEDA_5 = '5';

    #[IscedInfo(
        code: '6',
        label: 'Bachelor\'s or equivalent level',
        parent: null,
        ancestors: [],
        children: ['64', '65', '66'],
        descendants: ['64', '65', '66', '640', '650', '660'],
        tier: 1,
    )]
    case ISCEDA_6 = '6';

    #[IscedInfo(
        code: '7',
        label: 'Master\'s or equivalent level',
        parent: null,
        ancestors: [],
        children: ['74', '75', '76'],
        descendants: ['74', '75', '76', '740', '750', '760'],
        tier: 1,
    )]
    case ISCEDA_7 = '7';

    #[IscedInfo(
        code: '8',
        label: 'Doctoral or equivalent level',
        parent: null,
        ancestors: [],
        children: ['84', '85', '86'],
        descendants: ['84', '85', '86', '840', '850', '860'],
        tier: 1,
    )]
    case ISCEDA_8 = '8';

    #[IscedInfo(
        code: '9',
        label: 'Not elsewhere classified',
        parent: null,
        ancestors: [],
        children: ['99'],
        descendants: ['99', '999'],
        tier: 1,
    )]
    case ISCEDA_9 = '9';

    #[IscedInfo(
        code: '01',
        label: 'Never attended an education programme',
        parent: '0',
        ancestors: ['0'],
        children: ['010'],
        descendants: ['010'],
        tier: 2,
    )]
    case ISCEDA_01 = '01';

    #[IscedInfo(
        code: '02',
        label: 'Some early childhood education',
        parent: '0',
        ancestors: ['0'],
        children: ['020'],
        descendants: ['020'],
        tier: 2,
    )]
    case ISCEDA_02 = '02';

    #[IscedInfo(
        code: '03',
        label: 'Some primary education (without level completion)',
        parent: '0',
        ancestors: ['0'],
        children: ['030'],
        descendants: ['030'],
        tier: 2,
    )]
    case ISCEDA_03 = '03';

    #[IscedInfo(
        code: '10',
        label: 'Primary',
        parent: '1',
        ancestors: ['1'],
        children: ['100'],
        descendants: ['100'],
        tier: 2,
    )]
    case ISCEDA_10 = '10';

    #[IscedInfo(
        code: '24',
        label: 'General',
        parent: '2',
        ancestors: ['2'],
        children: ['242', '243', '244'],
        descendants: ['242', '243', '244'],
        tier: 2,
    )]
    case ISCEDA_24 = '24';

    #[IscedInfo(
        code: '25',
        label: 'Vocational',
        parent: '2',
        ancestors: ['2'],
        children: ['252', '253', '254'],
        descendants: ['252', '253', '254'],
        tier: 2,
    )]
    case ISCEDA_25 = '25';

    #[IscedInfo(
        code: '34',
        label: 'General',
        parent: '3',
        ancestors: ['3'],
        children: ['342', '343', '344'],
        descendants: ['342', '343', '344'],
        tier: 2,
    )]
    case ISCEDA_34 = '34';

    #[IscedInfo(
        code: '35',
        label: 'Vocational',
        parent: '3',
        ancestors: ['3'],
        children: ['352', '353', '354'],
        descendants: ['352', '353', '354'],
        tier: 2,
    )]
    case ISCEDA_35 = '35';

    #[IscedInfo(
        code: '44',
        label: 'General',
        parent: '4',
        ancestors: ['4'],
        children: ['443', '444'],
        descendants: ['443', '444'],
        tier: 2,
    )]
    case ISCEDA_44 = '44';

    #[IscedInfo(
        code: '45',
        label: 'Vocational',
        parent: '4',
        ancestors: ['4'],
        children: ['453', '454'],
        descendants: ['453', '454'],
        tier: 2,
    )]
    case ISCEDA_45 = '45';

    #[IscedInfo(
        code: '54',
        label: 'General',
        parent: '5',
        ancestors: ['5'],
        children: ['540'],
        descendants: ['540'],
        tier: 2,
    )]
    case ISCEDA_54 = '54';

    #[IscedInfo(
        code: '55',
        label: 'Vocational',
        parent: '5',
        ancestors: ['5'],
        children: ['550'],
        descendants: ['550'],
        tier: 2,
    )]
    case ISCEDA_55 = '55';

    #[IscedInfo(
        code: '56',
        label: 'Orientation unspecified',
        parent: '5',
        ancestors: ['5'],
        children: ['560'],
        descendants: ['560'],
        tier: 2,
    )]
    case ISCEDA_56 = '56';

    #[IscedInfo(
        code: '64',
        label: 'Academic',
        parent: '6',
        ancestors: ['6'],
        children: ['640'],
        descendants: ['640'],
        tier: 2,
    )]
    case ISCEDA_64 = '64';

    #[IscedInfo(
        code: '65',
        label: 'Professional',
        parent: '6',
        ancestors: ['6'],
        children: ['650'],
        descendants: ['650'],
        tier: 2,
    )]
    case ISCEDA_65 = '65';

    #[IscedInfo(
        code: '66',
        label: 'Orientation unspecified',
        parent: '6',
        ancestors: ['6'],
        children: ['660'],
        descendants: ['660'],
        tier: 2,
    )]
    case ISCEDA_66 = '66';

    #[IscedInfo(
        code: '74',
        label: 'Academic',
        parent: '7',
        ancestors: ['7'],
        children: ['740'],
        descendants: ['740'],
        tier: 2,
    )]
    case ISCEDA_74 = '74';

    #[IscedInfo(
        code: '75',
        label: 'Professional',
        parent: '7',
        ancestors: ['7'],
        children: ['750'],
        descendants: ['750'],
        tier: 2,
    )]
    case ISCEDA_75 = '75';

    #[IscedInfo(
        code: '76',
        label: 'Orientation unspecified',
        parent: '7',
        ancestors: ['7'],
        children: ['760'],
        descendants: ['760'],
        tier: 2,
    )]
    case ISCEDA_76 = '76';

    #[IscedInfo(
        code: '84',
        label: 'Academic',
        parent: '8',
        ancestors: ['8'],
        children: ['840'],
        descendants: ['840'],
        tier: 2,
    )]
    case ISCEDA_84 = '84';

    #[IscedInfo(
        code: '85',
        label: 'Professional',
        parent: '8',
        ancestors: ['8'],
        children: ['850'],
        descendants: ['850'],
        tier: 2,
    )]
    case ISCEDA_85 = '85';

    #[IscedInfo(
        code: '86',
        label: 'Orientation unspecified',
        parent: '8',
        ancestors: ['8'],
        children: ['860'],
        descendants: ['860'],
        tier: 2,
    )]
    case ISCEDA_86 = '86';

    #[IscedInfo(
        code: '99',
        label: 'Not elsewhere classified',
        parent: '9',
        ancestors: ['9'],
        children: ['999'],
        descendants: ['999'],
        tier: 2,
    )]
    case ISCEDA_99 = '99';

    #[IscedInfo(
        code: '010',
        label: 'Never attended an education programme',
        parent: '01',
        ancestors: ['0', '01'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_010 = '010';

    #[IscedInfo(
        code: '020',
        label: 'Some early childhood education',
        parent: '02',
        ancestors: ['0', '02'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_020 = '020';

    #[IscedInfo(
        code: '030',
        label: 'Some primary education (without level completion)',
        parent: '03',
        ancestors: ['0', '03'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_030 = '030';

    #[IscedInfo(
        code: '100',
        label: 'Including recognised successful completion of a lower secondary programme insufficient for level completion or partial level completion',
        parent: '10',
        ancestors: ['1', '10'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_100 = '100';

    #[IscedInfo(
        code: '242',
        label: 'Partial level completion, without direct access to upper secondary education',
        parent: '24',
        ancestors: ['2', '24'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_242 = '242';

    #[IscedInfo(
        code: '243',
        label: 'Level completion, without direct access to upper secondary education',
        parent: '24',
        ancestors: ['2', '24'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_243 = '243';

    #[IscedInfo(
        code: '244',
        label: 'Level completion, with direct access to upper secondary education',
        parent: '24',
        ancestors: ['2', '24'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_244 = '244';

    #[IscedInfo(
        code: '252',
        label: 'Partial level completion, without direct access to upper secondary education',
        parent: '25',
        ancestors: ['2', '25'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_252 = '252';

    #[IscedInfo(
        code: '253',
        label: 'Level completion, without direct access to upper secondary education',
        parent: '25',
        ancestors: ['2', '25'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_253 = '253';

    #[IscedInfo(
        code: '254',
        label: 'Level completion, with direct access to upper secondary education',
        parent: '25',
        ancestors: ['2', '25'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_254 = '254';

    #[IscedInfo(
        code: '342',
        label: 'Partial level completion, without direct access to tertiary education',
        parent: '34',
        ancestors: ['3', '34'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_342 = '342';

    #[IscedInfo(
        code: '343',
        label: 'Level completion, without direct access to tertiary education',
        parent: '34',
        ancestors: ['3', '34'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_343 = '343';

    #[IscedInfo(
        code: '344',
        label: 'Level completion, with direct access to tertiary education',
        parent: '34',
        ancestors: ['3', '34'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_344 = '344';

    #[IscedInfo(
        code: '352',
        label: 'Partial level completion, without direct access to tertiary education',
        parent: '35',
        ancestors: ['3', '35'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_352 = '352';

    #[IscedInfo(
        code: '353',
        label: 'Level completion, without direct access to tertiary education',
        parent: '35',
        ancestors: ['3', '35'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_353 = '353';

    #[IscedInfo(
        code: '354',
        label: 'Level completion, with direct access to tertiary education',
        parent: '35',
        ancestors: ['3', '35'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_354 = '354';

    #[IscedInfo(
        code: '443',
        label: 'Level completion, without direct access to tertiary education',
        parent: '44',
        ancestors: ['4', '44'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_443 = '443';

    #[IscedInfo(
        code: '444',
        label: 'Level completion, with direct access to tertiary education',
        parent: '44',
        ancestors: ['4', '44'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_444 = '444';

    #[IscedInfo(
        code: '453',
        label: 'Level completion, without direct access to tertiary education',
        parent: '45',
        ancestors: ['4', '45'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_453 = '453';

    #[IscedInfo(
        code: '454',
        label: 'Level completion, with direct access to tertiary education',
        parent: '45',
        ancestors: ['4', '45'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_454 = '454';

    #[IscedInfo(
        code: '540',
        label: 'Not further defined',
        parent: '54',
        ancestors: ['5', '54'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_540 = '540';

    #[IscedInfo(
        code: '550',
        label: 'Not further defined',
        parent: '55',
        ancestors: ['5', '55'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_550 = '550';

    #[IscedInfo(
        code: '560',
        label: 'Not further defined',
        parent: '56',
        ancestors: ['5', '56'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_560 = '560';

    #[IscedInfo(
        code: '640',
        label: 'Not further defined',
        parent: '64',
        ancestors: ['6', '64'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_640 = '640';

    #[IscedInfo(
        code: '650',
        label: 'Not further defined',
        parent: '65',
        ancestors: ['6', '65'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_650 = '650';

    #[IscedInfo(
        code: '660',
        label: 'Not further defined',
        parent: '66',
        ancestors: ['6', '66'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_660 = '660';

    #[IscedInfo(
        code: '740',
        label: 'Not further defined',
        parent: '74',
        ancestors: ['7', '74'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_740 = '740';

    #[IscedInfo(
        code: '750',
        label: 'Not further defined',
        parent: '75',
        ancestors: ['7', '75'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_750 = '750';

    #[IscedInfo(
        code: '760',
        label: 'Not further defined',
        parent: '76',
        ancestors: ['7', '76'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_760 = '760';

    #[IscedInfo(
        code: '840',
        label: 'Not further defined',
        parent: '84',
        ancestors: ['8', '84'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_840 = '840';

    #[IscedInfo(
        code: '850',
        label: 'Not further defined',
        parent: '85',
        ancestors: ['8', '85'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_850 = '850';

    #[IscedInfo(
        code: '860',
        label: 'Not further defined',
        parent: '86',
        ancestors: ['8', '86'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_860 = '860';

    #[IscedInfo(
        code: '999',
        label: 'Not elsewhere classified',
        parent: '99',
        ancestors: ['9', '99'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDA_999 = '999';

    /**
     * Lazily built data map from case value to metadata fields.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function iscedData(): array
    {
        $dataExtractor = static fn (IscedInfo $info, string $caseValue): array => [
            'code' => $info->code,
            'label' => $info->label,
            'parent' => $info->parent ?? '',
            'ancestors' => $info->ancestors,
            'children' => $info->children,
            'descendants' => $info->descendants,
            'tier' => $info->tier,
        ];
        $result = EnumDataUtils::buildLazyDataMap(
            self::class,
            IscedInfo::class,
            $dataExtractor
        );
        unset($dataExtractor);

        return $result;
    }

    public function code(): string
    {
        return EnumDataUtils::getFieldValue(
            self::iscedData(),
            $this->value,
            'code',
            'IscedA2011',
        );
    }

    public function label(): string
    {
        return EnumDataUtils::getFieldValue(
            self::iscedData(),
            $this->value,
            'label',
            'IscedA2011',
        );
    }

    public function parent(): ?string
    {
        $v = EnumDataUtils::getFieldValue(
            self::iscedData(),
            $this->value,
            'parent',
            'IscedA2011',
        );

        return $v === '' ? null : $v;
    }

    /**
     * @return array<int, string>
     */
    public function ancestors(): array
    {
        return EnumDataUtils::getArrayFieldValue(
            self::iscedData(),
            $this->value,
            'ancestors',
            'IscedA2011',
        );
    }

    /**
     * @return array<int, string>
     */
    public function children(): array
    {
        return EnumDataUtils::getArrayFieldValue(
            self::iscedData(),
            $this->value,
            'children',
            'IscedA2011',
        );
    }

    /**
     * @return array<int, string>
     */
    public function descendants(): array
    {
        return EnumDataUtils::getArrayFieldValue(
            self::iscedData(),
            $this->value,
            'descendants',
            'IscedA2011',
        );
    }

    public function tier(): int
    {
        return EnumDataUtils::getIntegerFieldValue(
            self::iscedData(),
            $this->value,
            'tier',
            'IscedA2011',
        );
    }

    /**
     * Human-readable labels from the broadest ancestor through this code.
     *
     * @param  string|null  $separator  When null, returns an ordered list of labels; when set, the same sequence joined by this string (for example `', '`, `'; '`, or `' — '`).
     * @return array<int, string>|string
     */
    public function labelsWithAncestors(?string $separator = null): array|string
    {
        $labels = [];
        foreach ($this->ancestors() as $ancestorCode) {
            $labels[] = self::from($ancestorCode)->label();
        }
        $labels[] = $this->label();

        if ($separator === null) {
            return $labels;
        }

        return implode($separator, $labels);
    }
}
