<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Attributes\IscedInfo;
use App\Services\EnumGeneration\EnumDataUtils;

/**
 * International Standard Classification of Education 2011, Education Programmes (ISCED-P 2011) code list.
 *
 * Source: UNESCO Institute for Statistics — ISCED 2011, Annex II (coding of education programmes)
 *   https://uis.unesco.org/sites/default/files/documents/international-standard-classification-of-education-isced-2011-en.pdf
 *
 * Codes use the UIS digit scheme (1–3 digit strings). Top-tier codes are single digits.
 *
 * Generated: 2026-05-20
 * Regenerate: php artisan generate:isced-enums
 *
 * Case names use the prefix ISCEDP_ (backing value is the canonical code).
 * Total cases: 91
 */
enum IscedP2011: string
{
    #[IscedInfo(
        code: '0',
        label: 'Early childhood education',
        parent: null,
        ancestors: [],
        children: ['01', '02'],
        descendants: ['01', '02', '010', '020'],
        tier: 1,
    )]
    case ISCEDP_0 = '0';

    #[IscedInfo(
        code: '1',
        label: 'Primary education',
        parent: null,
        ancestors: [],
        children: ['10'],
        descendants: ['10', '100'],
        tier: 1,
    )]
    case ISCEDP_1 = '1';

    #[IscedInfo(
        code: '2',
        label: 'Lower secondary education',
        parent: null,
        ancestors: [],
        children: ['24', '25'],
        descendants: ['24', '25', '241', '242', '243', '244', '251', '252', '253', '254'],
        tier: 1,
    )]
    case ISCEDP_2 = '2';

    #[IscedInfo(
        code: '3',
        label: 'Upper secondary education',
        parent: null,
        ancestors: [],
        children: ['34', '35'],
        descendants: ['34', '35', '341', '342', '343', '344', '351', '352', '353', '354'],
        tier: 1,
    )]
    case ISCEDP_3 = '3';

    #[IscedInfo(
        code: '4',
        label: 'Post-secondary non-tertiary education',
        parent: null,
        ancestors: [],
        children: ['44', '45'],
        descendants: ['44', '45', '441', '443', '444', '451', '453', '454'],
        tier: 1,
    )]
    case ISCEDP_4 = '4';

    #[IscedInfo(
        code: '5',
        label: 'Short-cycle tertiary education',
        parent: null,
        ancestors: [],
        children: ['54', '55'],
        descendants: ['54', '55', '541', '544', '551', '554'],
        tier: 1,
    )]
    case ISCEDP_5 = '5';

    #[IscedInfo(
        code: '6',
        label: 'Bachelor\'s or equivalent level',
        parent: null,
        ancestors: [],
        children: ['64', '65', '66'],
        descendants: ['64', '65', '66', '641', '645', '646', '647', '651', '655', '656', '657', '661', '665', '666', '667'],
        tier: 1,
    )]
    case ISCEDP_6 = '6';

    #[IscedInfo(
        code: '7',
        label: 'Master\'s or equivalent level',
        parent: null,
        ancestors: [],
        children: ['74', '75', '76'],
        descendants: ['74', '75', '76', '741', '746', '747', '748', '751', '756', '757', '758', '761', '766', '767', '768'],
        tier: 1,
    )]
    case ISCEDP_7 = '7';

    #[IscedInfo(
        code: '8',
        label: 'Doctoral or equivalent level',
        parent: null,
        ancestors: [],
        children: ['84', '85', '86'],
        descendants: ['84', '85', '86', '841', '844', '851', '854', '861', '864'],
        tier: 1,
    )]
    case ISCEDP_8 = '8';

    #[IscedInfo(
        code: '9',
        label: 'Not elsewhere classified',
        parent: null,
        ancestors: [],
        children: ['99'],
        descendants: ['99', '999'],
        tier: 1,
    )]
    case ISCEDP_9 = '9';

    #[IscedInfo(
        code: '01',
        label: 'Early childhood educational development',
        parent: '0',
        ancestors: ['0'],
        children: ['010'],
        descendants: ['010'],
        tier: 2,
    )]
    case ISCEDP_01 = '01';

    #[IscedInfo(
        code: '02',
        label: 'Pre-primary education',
        parent: '0',
        ancestors: ['0'],
        children: ['020'],
        descendants: ['020'],
        tier: 2,
    )]
    case ISCEDP_02 = '02';

    #[IscedInfo(
        code: '10',
        label: 'Primary education',
        parent: '1',
        ancestors: ['1'],
        children: ['100'],
        descendants: ['100'],
        tier: 2,
    )]
    case ISCEDP_10 = '10';

    #[IscedInfo(
        code: '24',
        label: 'General',
        parent: '2',
        ancestors: ['2'],
        children: ['241', '242', '243', '244'],
        descendants: ['241', '242', '243', '244'],
        tier: 2,
    )]
    case ISCEDP_24 = '24';

    #[IscedInfo(
        code: '25',
        label: 'Vocational',
        parent: '2',
        ancestors: ['2'],
        children: ['251', '252', '253', '254'],
        descendants: ['251', '252', '253', '254'],
        tier: 2,
    )]
    case ISCEDP_25 = '25';

    #[IscedInfo(
        code: '34',
        label: 'General',
        parent: '3',
        ancestors: ['3'],
        children: ['341', '342', '343', '344'],
        descendants: ['341', '342', '343', '344'],
        tier: 2,
    )]
    case ISCEDP_34 = '34';

    #[IscedInfo(
        code: '35',
        label: 'Vocational',
        parent: '3',
        ancestors: ['3'],
        children: ['351', '352', '353', '354'],
        descendants: ['351', '352', '353', '354'],
        tier: 2,
    )]
    case ISCEDP_35 = '35';

    #[IscedInfo(
        code: '44',
        label: 'General',
        parent: '4',
        ancestors: ['4'],
        children: ['441', '443', '444'],
        descendants: ['441', '443', '444'],
        tier: 2,
    )]
    case ISCEDP_44 = '44';

    #[IscedInfo(
        code: '45',
        label: 'Vocational',
        parent: '4',
        ancestors: ['4'],
        children: ['451', '453', '454'],
        descendants: ['451', '453', '454'],
        tier: 2,
    )]
    case ISCEDP_45 = '45';

    #[IscedInfo(
        code: '54',
        label: 'General',
        parent: '5',
        ancestors: ['5'],
        children: ['541', '544'],
        descendants: ['541', '544'],
        tier: 2,
    )]
    case ISCEDP_54 = '54';

    #[IscedInfo(
        code: '55',
        label: 'Vocational',
        parent: '5',
        ancestors: ['5'],
        children: ['551', '554'],
        descendants: ['551', '554'],
        tier: 2,
    )]
    case ISCEDP_55 = '55';

    #[IscedInfo(
        code: '64',
        label: 'Academic',
        parent: '6',
        ancestors: ['6'],
        children: ['641', '645', '646', '647'],
        descendants: ['641', '645', '646', '647'],
        tier: 2,
    )]
    case ISCEDP_64 = '64';

    #[IscedInfo(
        code: '65',
        label: 'Professional',
        parent: '6',
        ancestors: ['6'],
        children: ['651', '655', '656', '657'],
        descendants: ['651', '655', '656', '657'],
        tier: 2,
    )]
    case ISCEDP_65 = '65';

    #[IscedInfo(
        code: '66',
        label: 'Orientation unspecified',
        parent: '6',
        ancestors: ['6'],
        children: ['661', '665', '666', '667'],
        descendants: ['661', '665', '666', '667'],
        tier: 2,
    )]
    case ISCEDP_66 = '66';

    #[IscedInfo(
        code: '74',
        label: 'Academic',
        parent: '7',
        ancestors: ['7'],
        children: ['741', '746', '747', '748'],
        descendants: ['741', '746', '747', '748'],
        tier: 2,
    )]
    case ISCEDP_74 = '74';

    #[IscedInfo(
        code: '75',
        label: 'Professional',
        parent: '7',
        ancestors: ['7'],
        children: ['751', '756', '757', '758'],
        descendants: ['751', '756', '757', '758'],
        tier: 2,
    )]
    case ISCEDP_75 = '75';

    #[IscedInfo(
        code: '76',
        label: 'Orientation unspecified',
        parent: '7',
        ancestors: ['7'],
        children: ['761', '766', '767', '768'],
        descendants: ['761', '766', '767', '768'],
        tier: 2,
    )]
    case ISCEDP_76 = '76';

    #[IscedInfo(
        code: '84',
        label: 'Academic',
        parent: '8',
        ancestors: ['8'],
        children: ['841', '844'],
        descendants: ['841', '844'],
        tier: 2,
    )]
    case ISCEDP_84 = '84';

    #[IscedInfo(
        code: '85',
        label: 'Professional',
        parent: '8',
        ancestors: ['8'],
        children: ['851', '854'],
        descendants: ['851', '854'],
        tier: 2,
    )]
    case ISCEDP_85 = '85';

    #[IscedInfo(
        code: '86',
        label: 'Orientation unspecified',
        parent: '8',
        ancestors: ['8'],
        children: ['861', '864'],
        descendants: ['861', '864'],
        tier: 2,
    )]
    case ISCEDP_86 = '86';

    #[IscedInfo(
        code: '99',
        label: 'Not elsewhere classified',
        parent: '9',
        ancestors: ['9'],
        children: ['999'],
        descendants: ['999'],
        tier: 2,
    )]
    case ISCEDP_99 = '99';

    #[IscedInfo(
        code: '010',
        label: 'Early childhood educational development',
        parent: '01',
        ancestors: ['0', '01'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_010 = '010';

    #[IscedInfo(
        code: '020',
        label: 'Pre-primary education',
        parent: '02',
        ancestors: ['0', '02'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_020 = '020';

    #[IscedInfo(
        code: '100',
        label: 'Primary education',
        parent: '10',
        ancestors: ['1', '10'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_100 = '100';

    #[IscedInfo(
        code: '241',
        label: 'Insufficient for level completion or partial level completion, without direct access to upper secondary education',
        parent: '24',
        ancestors: ['2', '24'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_241 = '241';

    #[IscedInfo(
        code: '242',
        label: 'Sufficient for partial level completion, without direct access to upper secondary education',
        parent: '24',
        ancestors: ['2', '24'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_242 = '242';

    #[IscedInfo(
        code: '243',
        label: 'Sufficient for level completion, without direct access to upper secondary education',
        parent: '24',
        ancestors: ['2', '24'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_243 = '243';

    #[IscedInfo(
        code: '244',
        label: 'Sufficient for level completion, with direct access to upper secondary education',
        parent: '24',
        ancestors: ['2', '24'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_244 = '244';

    #[IscedInfo(
        code: '251',
        label: 'Insufficient for level completion or partial level completion, without direct access to upper secondary education',
        parent: '25',
        ancestors: ['2', '25'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_251 = '251';

    #[IscedInfo(
        code: '252',
        label: 'Sufficient for partial level completion, without direct access to upper secondary education',
        parent: '25',
        ancestors: ['2', '25'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_252 = '252';

    #[IscedInfo(
        code: '253',
        label: 'Sufficient for level completion, without direct access to upper secondary education',
        parent: '25',
        ancestors: ['2', '25'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_253 = '253';

    #[IscedInfo(
        code: '254',
        label: 'Sufficient for level completion, with direct access to upper secondary education',
        parent: '25',
        ancestors: ['2', '25'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_254 = '254';

    #[IscedInfo(
        code: '341',
        label: 'Insufficient for level completion or partial level completion, without direct access to tertiary education',
        parent: '34',
        ancestors: ['3', '34'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_341 = '341';

    #[IscedInfo(
        code: '342',
        label: 'Sufficient for partial level completion, without direct access to tertiary education',
        parent: '34',
        ancestors: ['3', '34'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_342 = '342';

    #[IscedInfo(
        code: '343',
        label: 'Sufficient for level completion, without direct access to tertiary education',
        parent: '34',
        ancestors: ['3', '34'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_343 = '343';

    #[IscedInfo(
        code: '344',
        label: 'Sufficient for level completion, with direct access to tertiary education',
        parent: '34',
        ancestors: ['3', '34'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_344 = '344';

    #[IscedInfo(
        code: '351',
        label: 'Insufficient for level completion or partial level completion, without direct access to tertiary education',
        parent: '35',
        ancestors: ['3', '35'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_351 = '351';

    #[IscedInfo(
        code: '352',
        label: 'Sufficient for partial level completion, without direct access to tertiary education',
        parent: '35',
        ancestors: ['3', '35'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_352 = '352';

    #[IscedInfo(
        code: '353',
        label: 'Sufficient for level completion, without direct access to tertiary education',
        parent: '35',
        ancestors: ['3', '35'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_353 = '353';

    #[IscedInfo(
        code: '354',
        label: 'Sufficient for level completion, with direct access to tertiary education',
        parent: '35',
        ancestors: ['3', '35'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_354 = '354';

    #[IscedInfo(
        code: '441',
        label: 'Insufficient for level completion, without direct access to tertiary education',
        parent: '44',
        ancestors: ['4', '44'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_441 = '441';

    #[IscedInfo(
        code: '443',
        label: 'Sufficient for level completion, without direct access to tertiary education',
        parent: '44',
        ancestors: ['4', '44'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_443 = '443';

    #[IscedInfo(
        code: '444',
        label: 'Sufficient for level completion, with direct access to tertiary education',
        parent: '44',
        ancestors: ['4', '44'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_444 = '444';

    #[IscedInfo(
        code: '451',
        label: 'Insufficient for level completion, without direct access to tertiary education',
        parent: '45',
        ancestors: ['4', '45'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_451 = '451';

    #[IscedInfo(
        code: '453',
        label: 'Sufficient for level completion, without direct access to tertiary education',
        parent: '45',
        ancestors: ['4', '45'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_453 = '453';

    #[IscedInfo(
        code: '454',
        label: 'Sufficient for level completion with direct access to tertiary education',
        parent: '45',
        ancestors: ['4', '45'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_454 = '454';

    #[IscedInfo(
        code: '541',
        label: 'Insufficient for level completion',
        parent: '54',
        ancestors: ['5', '54'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_541 = '541';

    #[IscedInfo(
        code: '544',
        label: 'Sufficient for level completion',
        parent: '54',
        ancestors: ['5', '54'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_544 = '544';

    #[IscedInfo(
        code: '551',
        label: 'Insufficient for level completion',
        parent: '55',
        ancestors: ['5', '55'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_551 = '551';

    #[IscedInfo(
        code: '554',
        label: 'Sufficient for level completion',
        parent: '55',
        ancestors: ['5', '55'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_554 = '554';

    #[IscedInfo(
        code: '641',
        label: 'Insufficient for level completion',
        parent: '64',
        ancestors: ['6', '64'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_641 = '641';

    #[IscedInfo(
        code: '645',
        label: 'First degree (3–4 years)',
        parent: '64',
        ancestors: ['6', '64'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_645 = '645';

    #[IscedInfo(
        code: '646',
        label: 'Long first degree (more than 4 years)',
        parent: '64',
        ancestors: ['6', '64'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_646 = '646';

    #[IscedInfo(
        code: '647',
        label: 'Second or further degree (following a Bachelor\'s or equivalent programme)',
        parent: '64',
        ancestors: ['6', '64'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_647 = '647';

    #[IscedInfo(
        code: '651',
        label: 'Insufficient for level completion',
        parent: '65',
        ancestors: ['6', '65'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_651 = '651';

    #[IscedInfo(
        code: '655',
        label: 'First degree (3–4 years)',
        parent: '65',
        ancestors: ['6', '65'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_655 = '655';

    #[IscedInfo(
        code: '656',
        label: 'Long first degree (more than 4 years)',
        parent: '65',
        ancestors: ['6', '65'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_656 = '656';

    #[IscedInfo(
        code: '657',
        label: 'Second or further degree (following a Bachelor\'s or equivalent programme)',
        parent: '65',
        ancestors: ['6', '65'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_657 = '657';

    #[IscedInfo(
        code: '661',
        label: 'Insufficient for level completion',
        parent: '66',
        ancestors: ['6', '66'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_661 = '661';

    #[IscedInfo(
        code: '665',
        label: 'First degree (3–4 years)',
        parent: '66',
        ancestors: ['6', '66'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_665 = '665';

    #[IscedInfo(
        code: '666',
        label: 'Long first degree (more than 4 years)',
        parent: '66',
        ancestors: ['6', '66'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_666 = '666';

    #[IscedInfo(
        code: '667',
        label: 'Second or further degree (following a Bachelor\'s or equivalent programme)',
        parent: '66',
        ancestors: ['6', '66'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_667 = '667';

    #[IscedInfo(
        code: '741',
        label: 'Insufficient for level completion',
        parent: '74',
        ancestors: ['7', '74'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_741 = '741';

    #[IscedInfo(
        code: '746',
        label: 'Long first degree (at least 5 years)',
        parent: '74',
        ancestors: ['7', '74'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_746 = '746';

    #[IscedInfo(
        code: '747',
        label: 'Second or further degree (following a Bachelor\'s or equivalent programme)',
        parent: '74',
        ancestors: ['7', '74'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_747 = '747';

    #[IscedInfo(
        code: '748',
        label: 'Second or further degree (following a Master\'s or equivalent programme)',
        parent: '74',
        ancestors: ['7', '74'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_748 = '748';

    #[IscedInfo(
        code: '751',
        label: 'Insufficient for level completion',
        parent: '75',
        ancestors: ['7', '75'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_751 = '751';

    #[IscedInfo(
        code: '756',
        label: 'Long first degree (at least 5 years)',
        parent: '75',
        ancestors: ['7', '75'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_756 = '756';

    #[IscedInfo(
        code: '757',
        label: 'Second or further degree (following a Bachelor\'s or equivalent programme)',
        parent: '75',
        ancestors: ['7', '75'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_757 = '757';

    #[IscedInfo(
        code: '758',
        label: 'Second or further degree (following a Master\'s or equivalent programme)',
        parent: '75',
        ancestors: ['7', '75'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_758 = '758';

    #[IscedInfo(
        code: '761',
        label: 'Insufficient for level completion',
        parent: '76',
        ancestors: ['7', '76'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_761 = '761';

    #[IscedInfo(
        code: '766',
        label: 'Long first degree (at least 5 years)',
        parent: '76',
        ancestors: ['7', '76'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_766 = '766';

    #[IscedInfo(
        code: '767',
        label: 'Second or further degree (following a Bachelor\'s or equivalent programme)',
        parent: '76',
        ancestors: ['7', '76'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_767 = '767';

    #[IscedInfo(
        code: '768',
        label: 'Second or further degree (following a Master\'s or equivalent programme)',
        parent: '76',
        ancestors: ['7', '76'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_768 = '768';

    #[IscedInfo(
        code: '841',
        label: 'Insufficient for level completion',
        parent: '84',
        ancestors: ['8', '84'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_841 = '841';

    #[IscedInfo(
        code: '844',
        label: 'Sufficient for completion of level',
        parent: '84',
        ancestors: ['8', '84'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_844 = '844';

    #[IscedInfo(
        code: '851',
        label: 'Insufficient for level completion',
        parent: '85',
        ancestors: ['8', '85'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_851 = '851';

    #[IscedInfo(
        code: '854',
        label: 'Sufficient for completion of level',
        parent: '85',
        ancestors: ['8', '85'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_854 = '854';

    #[IscedInfo(
        code: '861',
        label: 'Insufficient for level completion',
        parent: '86',
        ancestors: ['8', '86'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_861 = '861';

    #[IscedInfo(
        code: '864',
        label: 'Sufficient for completion of level',
        parent: '86',
        ancestors: ['8', '86'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_864 = '864';

    #[IscedInfo(
        code: '999',
        label: 'Not elsewhere classified',
        parent: '99',
        ancestors: ['9', '99'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDP_999 = '999';

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
            'IscedP2011',
        );
    }

    public function label(): string
    {
        return EnumDataUtils::getFieldValue(
            self::iscedData(),
            $this->value,
            'label',
            'IscedP2011',
        );
    }

    public function parent(): ?string
    {
        $v = EnumDataUtils::getFieldValue(
            self::iscedData(),
            $this->value,
            'parent',
            'IscedP2011',
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
            'IscedP2011',
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
            'IscedP2011',
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
            'IscedP2011',
        );
    }

    public function tier(): int
    {
        return EnumDataUtils::getIntegerFieldValue(
            self::iscedData(),
            $this->value,
            'tier',
            'IscedP2011',
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
