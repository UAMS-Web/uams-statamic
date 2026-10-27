<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Attributes\IscedInfo;
use App\Services\EnumGeneration\EnumDataUtils;

/**
 * International Standard Classification of Education: Fields of Education and Training 2013 (ISCED-F 2013) code list.
 *
 * Source: Eurostat SDMX codelist ISCEDF13 (English labels), frozen as JSON
 *   https://ec.europa.eu/eurostat/api/dissemination/sdmx/2.1/codelist/ESTAT/ISCEDF13/latest?format=TSV&lang=en
 *
 * Codes use the canonical 2/3/4-digit UIS scheme. Refresh `isced-f-2013.json` from the Eurostat ISCEDF13 TSV when you need updated labels.
 *
 * Generated: 2026-05-20
 * Regenerate: php artisan generate:isced-enums
 *
 * Case names use the prefix ISCEDF_ (backing value is the canonical code).
 * Total cases: 216
 */
enum IscedF2013: string
{
    #[IscedInfo(
        code: '00',
        label: 'Generic programmes and qualifications',
        parent: null,
        ancestors: [],
        children: ['000', '001', '002', '003', '009'],
        descendants: ['000', '001', '002', '003', '009', '0000', '0011', '0021', '0031', '0099'],
        tier: 1,
    )]
    case ISCEDF_00 = '00';

    #[IscedInfo(
        code: '01',
        label: 'Education',
        parent: null,
        ancestors: [],
        children: ['011', '018'],
        descendants: ['011', '018', '0110', '0111', '0112', '0113', '0114', '0119', '0188'],
        tier: 1,
    )]
    case ISCEDF_01 = '01';

    #[IscedInfo(
        code: '02',
        label: 'Arts and humanities',
        parent: null,
        ancestors: [],
        children: ['020', '021', '022', '023', '028', '029'],
        descendants: ['020', '021', '022', '023', '028', '029', '0200', '0210', '0211', '0212', '0213', '0214', '0215', '0219', '0220', '0221', '0222', '0223', '0229', '0230', '0231', '0232', '0239', '0288', '0299'],
        tier: 1,
    )]
    case ISCEDF_02 = '02';

    #[IscedInfo(
        code: '03',
        label: 'Social sciences, journalism and information',
        parent: null,
        ancestors: [],
        children: ['030', '031', '032', '038', '039'],
        descendants: ['030', '031', '032', '038', '039', '0300', '0310', '0311', '0312', '0313', '0314', '0319', '0320', '0321', '0322', '0329', '0388', '0399'],
        tier: 1,
    )]
    case ISCEDF_03 = '03';

    #[IscedInfo(
        code: '04',
        label: 'Business, administration and law',
        parent: null,
        ancestors: [],
        children: ['040', '041', '042', '048', '049'],
        descendants: ['040', '041', '042', '048', '049', '0400', '0410', '0411', '0412', '0413', '0414', '0415', '0416', '0417', '0419', '0421', '0488', '0499'],
        tier: 1,
    )]
    case ISCEDF_04 = '04';

    #[IscedInfo(
        code: '05',
        label: 'Natural sciences, mathematics and statistics',
        parent: null,
        ancestors: [],
        children: ['050', '051', '052', '053', '054', '058', '059'],
        descendants: ['050', '051', '052', '053', '054', '058', '059', '0500', '0510', '0511', '0512', '0519', '0520', '0521', '0522', '0529', '0530', '0531', '0532', '0533', '0539', '0540', '0541', '0542', '0588', '0599'],
        tier: 1,
    )]
    case ISCEDF_05 = '05';

    #[IscedInfo(
        code: '06',
        label: 'Information and communication technologies',
        parent: null,
        ancestors: [],
        children: ['061', '068'],
        descendants: ['061', '068', '0610', '0611', '0612', '0613', '0619', '0688'],
        tier: 1,
    )]
    case ISCEDF_06 = '06';

    #[IscedInfo(
        code: '07',
        label: 'Engineering, manufacturing and construction',
        parent: null,
        ancestors: [],
        children: ['070', '071', '072', '073', '078', '079'],
        descendants: ['070', '071', '072', '073', '078', '079', '0700', '0710', '0711', '0712', '0713', '0714', '0715', '0716', '0719', '0720', '0721', '0722', '0723', '0724', '0729', '0730', '0731', '0732', '0788', '0799'],
        tier: 1,
    )]
    case ISCEDF_07 = '07';

    #[IscedInfo(
        code: '08',
        label: 'Agriculture, forestry, fisheries and veterinary',
        parent: null,
        ancestors: [],
        children: ['080', '081', '082', '083', '084', '088', '089'],
        descendants: ['080', '081', '082', '083', '084', '088', '089', '0800', '0810', '0811', '0812', '0819', '0821', '0831', '0841', '0888', '0899'],
        tier: 1,
    )]
    case ISCEDF_08 = '08';

    #[IscedInfo(
        code: '09',
        label: 'Health and welfare',
        parent: null,
        ancestors: [],
        children: ['090', '091', '092', '098', '099'],
        descendants: ['090', '091', '092', '098', '099', '0900', '0910', '0911', '0912', '0913', '0914', '0915', '0916', '0917', '0919', '0920', '0921', '0922', '0923', '0929', '0988', '0999'],
        tier: 1,
    )]
    case ISCEDF_09 = '09';

    #[IscedInfo(
        code: '10',
        label: 'Services',
        parent: null,
        ancestors: [],
        children: ['100', '101', '102', '103', '104', '108', '109'],
        descendants: ['100', '101', '102', '103', '104', '108', '109', '1000', '1010', '1011', '1012', '1013', '1014', '1015', '1019', '1020', '1021', '1022', '1029', '1030', '1031', '1032', '1039', '1041', '1088', '1099'],
        tier: 1,
    )]
    case ISCEDF_10 = '10';

    #[IscedInfo(
        code: '000',
        label: 'Generic programmes and qualifications not further defined',
        parent: '00',
        ancestors: ['00'],
        children: ['0000'],
        descendants: ['0000'],
        tier: 2,
    )]
    case ISCEDF_000 = '000';

    #[IscedInfo(
        code: '001',
        label: 'Basic programmes and qualifications',
        parent: '00',
        ancestors: ['00'],
        children: ['0011'],
        descendants: ['0011'],
        tier: 2,
    )]
    case ISCEDF_001 = '001';

    #[IscedInfo(
        code: '002',
        label: 'Literacy and numeracy',
        parent: '00',
        ancestors: ['00'],
        children: ['0021'],
        descendants: ['0021'],
        tier: 2,
    )]
    case ISCEDF_002 = '002';

    #[IscedInfo(
        code: '003',
        label: 'Personal skills and development',
        parent: '00',
        ancestors: ['00'],
        children: ['0031'],
        descendants: ['0031'],
        tier: 2,
    )]
    case ISCEDF_003 = '003';

    #[IscedInfo(
        code: '009',
        label: 'Generic programmes and qualifications not elsewhere classified',
        parent: '00',
        ancestors: ['00'],
        children: ['0099'],
        descendants: ['0099'],
        tier: 2,
    )]
    case ISCEDF_009 = '009';

    #[IscedInfo(
        code: '011',
        label: 'Education',
        parent: '01',
        ancestors: ['01'],
        children: ['0110', '0111', '0112', '0113', '0114', '0119'],
        descendants: ['0110', '0111', '0112', '0113', '0114', '0119'],
        tier: 2,
    )]
    case ISCEDF_011 = '011';

    #[IscedInfo(
        code: '018',
        label: 'Inter-disciplinary programmes and qualifications involving education',
        parent: '01',
        ancestors: ['01'],
        children: ['0188'],
        descendants: ['0188'],
        tier: 2,
    )]
    case ISCEDF_018 = '018';

    #[IscedInfo(
        code: '020',
        label: 'Arts and humanities not further defined',
        parent: '02',
        ancestors: ['02'],
        children: ['0200'],
        descendants: ['0200'],
        tier: 2,
    )]
    case ISCEDF_020 = '020';

    #[IscedInfo(
        code: '021',
        label: 'Arts',
        parent: '02',
        ancestors: ['02'],
        children: ['0210', '0211', '0212', '0213', '0214', '0215', '0219'],
        descendants: ['0210', '0211', '0212', '0213', '0214', '0215', '0219'],
        tier: 2,
    )]
    case ISCEDF_021 = '021';

    #[IscedInfo(
        code: '022',
        label: 'Humanities (except languages)',
        parent: '02',
        ancestors: ['02'],
        children: ['0220', '0221', '0222', '0223', '0229'],
        descendants: ['0220', '0221', '0222', '0223', '0229'],
        tier: 2,
    )]
    case ISCEDF_022 = '022';

    #[IscedInfo(
        code: '023',
        label: 'Languages',
        parent: '02',
        ancestors: ['02'],
        children: ['0230', '0231', '0232', '0239'],
        descendants: ['0230', '0231', '0232', '0239'],
        tier: 2,
    )]
    case ISCEDF_023 = '023';

    #[IscedInfo(
        code: '028',
        label: 'Inter-disciplinary programmes and qualifications involving arts and humanities',
        parent: '02',
        ancestors: ['02'],
        children: ['0288'],
        descendants: ['0288'],
        tier: 2,
    )]
    case ISCEDF_028 = '028';

    #[IscedInfo(
        code: '029',
        label: 'Arts and humanities not elsewhere classified',
        parent: '02',
        ancestors: ['02'],
        children: ['0299'],
        descendants: ['0299'],
        tier: 2,
    )]
    case ISCEDF_029 = '029';

    #[IscedInfo(
        code: '030',
        label: 'Social sciences, journalism and information not further defined',
        parent: '03',
        ancestors: ['03'],
        children: ['0300'],
        descendants: ['0300'],
        tier: 2,
    )]
    case ISCEDF_030 = '030';

    #[IscedInfo(
        code: '031',
        label: 'Social and behavioural sciences',
        parent: '03',
        ancestors: ['03'],
        children: ['0310', '0311', '0312', '0313', '0314', '0319'],
        descendants: ['0310', '0311', '0312', '0313', '0314', '0319'],
        tier: 2,
    )]
    case ISCEDF_031 = '031';

    #[IscedInfo(
        code: '032',
        label: 'Journalism and information',
        parent: '03',
        ancestors: ['03'],
        children: ['0320', '0321', '0322', '0329'],
        descendants: ['0320', '0321', '0322', '0329'],
        tier: 2,
    )]
    case ISCEDF_032 = '032';

    #[IscedInfo(
        code: '038',
        label: 'Inter-disciplinary programmes and qualifications involving social sciences, journalism and information',
        parent: '03',
        ancestors: ['03'],
        children: ['0388'],
        descendants: ['0388'],
        tier: 2,
    )]
    case ISCEDF_038 = '038';

    #[IscedInfo(
        code: '039',
        label: 'Social sciences, journalism and information not elsewhere classified',
        parent: '03',
        ancestors: ['03'],
        children: ['0399'],
        descendants: ['0399'],
        tier: 2,
    )]
    case ISCEDF_039 = '039';

    #[IscedInfo(
        code: '040',
        label: 'Business, administration and law not further defined',
        parent: '04',
        ancestors: ['04'],
        children: ['0400'],
        descendants: ['0400'],
        tier: 2,
    )]
    case ISCEDF_040 = '040';

    #[IscedInfo(
        code: '041',
        label: 'Business and administration',
        parent: '04',
        ancestors: ['04'],
        children: ['0410', '0411', '0412', '0413', '0414', '0415', '0416', '0417', '0419'],
        descendants: ['0410', '0411', '0412', '0413', '0414', '0415', '0416', '0417', '0419'],
        tier: 2,
    )]
    case ISCEDF_041 = '041';

    #[IscedInfo(
        code: '042',
        label: 'Law',
        parent: '04',
        ancestors: ['04'],
        children: ['0421'],
        descendants: ['0421'],
        tier: 2,
    )]
    case ISCEDF_042 = '042';

    #[IscedInfo(
        code: '048',
        label: 'Inter-disciplinary programmes and qualifications involving business, administration and law',
        parent: '04',
        ancestors: ['04'],
        children: ['0488'],
        descendants: ['0488'],
        tier: 2,
    )]
    case ISCEDF_048 = '048';

    #[IscedInfo(
        code: '049',
        label: 'Business, administration and law not elsewhere classified',
        parent: '04',
        ancestors: ['04'],
        children: ['0499'],
        descendants: ['0499'],
        tier: 2,
    )]
    case ISCEDF_049 = '049';

    #[IscedInfo(
        code: '050',
        label: 'Natural sciences, mathematics and statistics not further defined',
        parent: '05',
        ancestors: ['05'],
        children: ['0500'],
        descendants: ['0500'],
        tier: 2,
    )]
    case ISCEDF_050 = '050';

    #[IscedInfo(
        code: '051',
        label: 'Biological and related sciences',
        parent: '05',
        ancestors: ['05'],
        children: ['0510', '0511', '0512', '0519'],
        descendants: ['0510', '0511', '0512', '0519'],
        tier: 2,
    )]
    case ISCEDF_051 = '051';

    #[IscedInfo(
        code: '052',
        label: 'Environment',
        parent: '05',
        ancestors: ['05'],
        children: ['0520', '0521', '0522', '0529'],
        descendants: ['0520', '0521', '0522', '0529'],
        tier: 2,
    )]
    case ISCEDF_052 = '052';

    #[IscedInfo(
        code: '053',
        label: 'Physical sciences',
        parent: '05',
        ancestors: ['05'],
        children: ['0530', '0531', '0532', '0533', '0539'],
        descendants: ['0530', '0531', '0532', '0533', '0539'],
        tier: 2,
    )]
    case ISCEDF_053 = '053';

    #[IscedInfo(
        code: '054',
        label: 'Mathematics and statistics',
        parent: '05',
        ancestors: ['05'],
        children: ['0540', '0541', '0542'],
        descendants: ['0540', '0541', '0542'],
        tier: 2,
    )]
    case ISCEDF_054 = '054';

    #[IscedInfo(
        code: '058',
        label: 'Inter-disciplinary programmes and qualifications involving natural sciences, mathematics and statistics',
        parent: '05',
        ancestors: ['05'],
        children: ['0588'],
        descendants: ['0588'],
        tier: 2,
    )]
    case ISCEDF_058 = '058';

    #[IscedInfo(
        code: '059',
        label: 'Natural sciences, mathematics and statistics not elsewhere classified',
        parent: '05',
        ancestors: ['05'],
        children: ['0599'],
        descendants: ['0599'],
        tier: 2,
    )]
    case ISCEDF_059 = '059';

    #[IscedInfo(
        code: '061',
        label: 'Information and communication technologies',
        parent: '06',
        ancestors: ['06'],
        children: ['0610', '0611', '0612', '0613', '0619'],
        descendants: ['0610', '0611', '0612', '0613', '0619'],
        tier: 2,
    )]
    case ISCEDF_061 = '061';

    #[IscedInfo(
        code: '068',
        label: 'Inter-disciplinary programmes and qualifications involving information and communication technologies',
        parent: '06',
        ancestors: ['06'],
        children: ['0688'],
        descendants: ['0688'],
        tier: 2,
    )]
    case ISCEDF_068 = '068';

    #[IscedInfo(
        code: '070',
        label: 'Engineering, manufacturing and construction not further defined',
        parent: '07',
        ancestors: ['07'],
        children: ['0700'],
        descendants: ['0700'],
        tier: 2,
    )]
    case ISCEDF_070 = '070';

    #[IscedInfo(
        code: '071',
        label: 'Engineering and engineering trades',
        parent: '07',
        ancestors: ['07'],
        children: ['0710', '0711', '0712', '0713', '0714', '0715', '0716', '0719'],
        descendants: ['0710', '0711', '0712', '0713', '0714', '0715', '0716', '0719'],
        tier: 2,
    )]
    case ISCEDF_071 = '071';

    #[IscedInfo(
        code: '072',
        label: 'Manufacturing and processing',
        parent: '07',
        ancestors: ['07'],
        children: ['0720', '0721', '0722', '0723', '0724', '0729'],
        descendants: ['0720', '0721', '0722', '0723', '0724', '0729'],
        tier: 2,
    )]
    case ISCEDF_072 = '072';

    #[IscedInfo(
        code: '073',
        label: 'Architecture and construction',
        parent: '07',
        ancestors: ['07'],
        children: ['0730', '0731', '0732'],
        descendants: ['0730', '0731', '0732'],
        tier: 2,
    )]
    case ISCEDF_073 = '073';

    #[IscedInfo(
        code: '078',
        label: 'Inter-disciplinary programmes and qualifications involving engineering, manufacturing and construction',
        parent: '07',
        ancestors: ['07'],
        children: ['0788'],
        descendants: ['0788'],
        tier: 2,
    )]
    case ISCEDF_078 = '078';

    #[IscedInfo(
        code: '079',
        label: 'Engineering, manufacturing and construction not elsewhere classified',
        parent: '07',
        ancestors: ['07'],
        children: ['0799'],
        descendants: ['0799'],
        tier: 2,
    )]
    case ISCEDF_079 = '079';

    #[IscedInfo(
        code: '080',
        label: 'Agriculture, forestry, fisheries and veterinary not further defined',
        parent: '08',
        ancestors: ['08'],
        children: ['0800'],
        descendants: ['0800'],
        tier: 2,
    )]
    case ISCEDF_080 = '080';

    #[IscedInfo(
        code: '081',
        label: 'Agriculture',
        parent: '08',
        ancestors: ['08'],
        children: ['0810', '0811', '0812', '0819'],
        descendants: ['0810', '0811', '0812', '0819'],
        tier: 2,
    )]
    case ISCEDF_081 = '081';

    #[IscedInfo(
        code: '082',
        label: 'Forestry',
        parent: '08',
        ancestors: ['08'],
        children: ['0821'],
        descendants: ['0821'],
        tier: 2,
    )]
    case ISCEDF_082 = '082';

    #[IscedInfo(
        code: '083',
        label: 'Fisheries',
        parent: '08',
        ancestors: ['08'],
        children: ['0831'],
        descendants: ['0831'],
        tier: 2,
    )]
    case ISCEDF_083 = '083';

    #[IscedInfo(
        code: '084',
        label: 'Veterinary',
        parent: '08',
        ancestors: ['08'],
        children: ['0841'],
        descendants: ['0841'],
        tier: 2,
    )]
    case ISCEDF_084 = '084';

    #[IscedInfo(
        code: '088',
        label: 'Inter-disciplinary programmes and qualifications involving agriculture, forestry, fisheries and veterinary',
        parent: '08',
        ancestors: ['08'],
        children: ['0888'],
        descendants: ['0888'],
        tier: 2,
    )]
    case ISCEDF_088 = '088';

    #[IscedInfo(
        code: '089',
        label: 'Agriculture, forestry, fisheries and veterinary not elsewhere classified',
        parent: '08',
        ancestors: ['08'],
        children: ['0899'],
        descendants: ['0899'],
        tier: 2,
    )]
    case ISCEDF_089 = '089';

    #[IscedInfo(
        code: '090',
        label: 'Health and welfare not further defined',
        parent: '09',
        ancestors: ['09'],
        children: ['0900'],
        descendants: ['0900'],
        tier: 2,
    )]
    case ISCEDF_090 = '090';

    #[IscedInfo(
        code: '091',
        label: 'Health',
        parent: '09',
        ancestors: ['09'],
        children: ['0910', '0911', '0912', '0913', '0914', '0915', '0916', '0917', '0919'],
        descendants: ['0910', '0911', '0912', '0913', '0914', '0915', '0916', '0917', '0919'],
        tier: 2,
    )]
    case ISCEDF_091 = '091';

    #[IscedInfo(
        code: '092',
        label: 'Welfare',
        parent: '09',
        ancestors: ['09'],
        children: ['0920', '0921', '0922', '0923', '0929'],
        descendants: ['0920', '0921', '0922', '0923', '0929'],
        tier: 2,
    )]
    case ISCEDF_092 = '092';

    #[IscedInfo(
        code: '098',
        label: 'Inter-disciplinary programmes and qualifications involving health and welfare',
        parent: '09',
        ancestors: ['09'],
        children: ['0988'],
        descendants: ['0988'],
        tier: 2,
    )]
    case ISCEDF_098 = '098';

    #[IscedInfo(
        code: '099',
        label: 'Health and welfare not elsewhere classified',
        parent: '09',
        ancestors: ['09'],
        children: ['0999'],
        descendants: ['0999'],
        tier: 2,
    )]
    case ISCEDF_099 = '099';

    #[IscedInfo(
        code: '100',
        label: 'Services not further defined',
        parent: '10',
        ancestors: ['10'],
        children: ['1000'],
        descendants: ['1000'],
        tier: 2,
    )]
    case ISCEDF_100 = '100';

    #[IscedInfo(
        code: '101',
        label: 'Personal services',
        parent: '10',
        ancestors: ['10'],
        children: ['1010', '1011', '1012', '1013', '1014', '1015', '1019'],
        descendants: ['1010', '1011', '1012', '1013', '1014', '1015', '1019'],
        tier: 2,
    )]
    case ISCEDF_101 = '101';

    #[IscedInfo(
        code: '102',
        label: 'Hygiene and occupational health services',
        parent: '10',
        ancestors: ['10'],
        children: ['1020', '1021', '1022', '1029'],
        descendants: ['1020', '1021', '1022', '1029'],
        tier: 2,
    )]
    case ISCEDF_102 = '102';

    #[IscedInfo(
        code: '103',
        label: 'Security services',
        parent: '10',
        ancestors: ['10'],
        children: ['1030', '1031', '1032', '1039'],
        descendants: ['1030', '1031', '1032', '1039'],
        tier: 2,
    )]
    case ISCEDF_103 = '103';

    #[IscedInfo(
        code: '104',
        label: 'Transport services',
        parent: '10',
        ancestors: ['10'],
        children: ['1041'],
        descendants: ['1041'],
        tier: 2,
    )]
    case ISCEDF_104 = '104';

    #[IscedInfo(
        code: '108',
        label: 'Inter-disciplinary programmes and qualifications involving services',
        parent: '10',
        ancestors: ['10'],
        children: ['1088'],
        descendants: ['1088'],
        tier: 2,
    )]
    case ISCEDF_108 = '108';

    #[IscedInfo(
        code: '109',
        label: 'Services not elsewhere classified',
        parent: '10',
        ancestors: ['10'],
        children: ['1099'],
        descendants: ['1099'],
        tier: 2,
    )]
    case ISCEDF_109 = '109';

    #[IscedInfo(
        code: '0000',
        label: 'Generic programmes and qualifications not further defined',
        parent: '000',
        ancestors: ['00', '000'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0000 = '0000';

    #[IscedInfo(
        code: '0011',
        label: 'Basic programmes and qualifications',
        parent: '001',
        ancestors: ['00', '001'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0011 = '0011';

    #[IscedInfo(
        code: '0021',
        label: 'Literacy and numeracy',
        parent: '002',
        ancestors: ['00', '002'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0021 = '0021';

    #[IscedInfo(
        code: '0031',
        label: 'Personal skills and development',
        parent: '003',
        ancestors: ['00', '003'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0031 = '0031';

    #[IscedInfo(
        code: '0099',
        label: 'Generic programmes and qualifications not elsewhere classified',
        parent: '009',
        ancestors: ['00', '009'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0099 = '0099';

    #[IscedInfo(
        code: '0110',
        label: 'Education not further defined',
        parent: '011',
        ancestors: ['01', '011'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0110 = '0110';

    #[IscedInfo(
        code: '0111',
        label: 'Education science',
        parent: '011',
        ancestors: ['01', '011'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0111 = '0111';

    #[IscedInfo(
        code: '0112',
        label: 'Training for pre-school teachers',
        parent: '011',
        ancestors: ['01', '011'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0112 = '0112';

    #[IscedInfo(
        code: '0113',
        label: 'Teacher training without subject specialisation',
        parent: '011',
        ancestors: ['01', '011'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0113 = '0113';

    #[IscedInfo(
        code: '0114',
        label: 'Teacher training with subject specialisation',
        parent: '011',
        ancestors: ['01', '011'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0114 = '0114';

    #[IscedInfo(
        code: '0119',
        label: 'Education not elsewhere classified',
        parent: '011',
        ancestors: ['01', '011'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0119 = '0119';

    #[IscedInfo(
        code: '0188',
        label: 'Inter-disciplinary programmes and qualifications involving education',
        parent: '018',
        ancestors: ['01', '018'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0188 = '0188';

    #[IscedInfo(
        code: '0200',
        label: 'Arts and humanities not further defined',
        parent: '020',
        ancestors: ['02', '020'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0200 = '0200';

    #[IscedInfo(
        code: '0210',
        label: 'Arts not further defined',
        parent: '021',
        ancestors: ['02', '021'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0210 = '0210';

    #[IscedInfo(
        code: '0211',
        label: 'Audio-visual techniques and media production',
        parent: '021',
        ancestors: ['02', '021'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0211 = '0211';

    #[IscedInfo(
        code: '0212',
        label: 'Fashion, interior and industrial design',
        parent: '021',
        ancestors: ['02', '021'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0212 = '0212';

    #[IscedInfo(
        code: '0213',
        label: 'Fine arts',
        parent: '021',
        ancestors: ['02', '021'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0213 = '0213';

    #[IscedInfo(
        code: '0214',
        label: 'Handicrafts',
        parent: '021',
        ancestors: ['02', '021'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0214 = '0214';

    #[IscedInfo(
        code: '0215',
        label: 'Music and performing arts',
        parent: '021',
        ancestors: ['02', '021'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0215 = '0215';

    #[IscedInfo(
        code: '0219',
        label: 'Arts not elsewhere classified',
        parent: '021',
        ancestors: ['02', '021'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0219 = '0219';

    #[IscedInfo(
        code: '0220',
        label: 'Humanities (except languages) not further defined',
        parent: '022',
        ancestors: ['02', '022'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0220 = '0220';

    #[IscedInfo(
        code: '0221',
        label: 'Religion and theology',
        parent: '022',
        ancestors: ['02', '022'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0221 = '0221';

    #[IscedInfo(
        code: '0222',
        label: 'History and archaeology',
        parent: '022',
        ancestors: ['02', '022'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0222 = '0222';

    #[IscedInfo(
        code: '0223',
        label: 'Philosophy and ethics',
        parent: '022',
        ancestors: ['02', '022'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0223 = '0223';

    #[IscedInfo(
        code: '0229',
        label: 'Humanities (except languages) not elsewhere classified',
        parent: '022',
        ancestors: ['02', '022'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0229 = '0229';

    #[IscedInfo(
        code: '0230',
        label: 'Languages not further defined',
        parent: '023',
        ancestors: ['02', '023'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0230 = '0230';

    #[IscedInfo(
        code: '0231',
        label: 'Language acquisition',
        parent: '023',
        ancestors: ['02', '023'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0231 = '0231';

    #[IscedInfo(
        code: '0232',
        label: 'Literature and linguistics',
        parent: '023',
        ancestors: ['02', '023'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0232 = '0232';

    #[IscedInfo(
        code: '0239',
        label: 'Languages not elsewhere classified',
        parent: '023',
        ancestors: ['02', '023'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0239 = '0239';

    #[IscedInfo(
        code: '0288',
        label: 'Inter-disciplinary programmes and qualifications involving arts and humanities',
        parent: '028',
        ancestors: ['02', '028'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0288 = '0288';

    #[IscedInfo(
        code: '0299',
        label: 'Arts and humanities not elsewhere classified',
        parent: '029',
        ancestors: ['02', '029'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0299 = '0299';

    #[IscedInfo(
        code: '0300',
        label: 'Social sciences, journalism and information not further defined',
        parent: '030',
        ancestors: ['03', '030'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0300 = '0300';

    #[IscedInfo(
        code: '0310',
        label: 'Social and behavioural sciences not further defined',
        parent: '031',
        ancestors: ['03', '031'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0310 = '0310';

    #[IscedInfo(
        code: '0311',
        label: 'Economics',
        parent: '031',
        ancestors: ['03', '031'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0311 = '0311';

    #[IscedInfo(
        code: '0312',
        label: 'Political sciences and civics',
        parent: '031',
        ancestors: ['03', '031'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0312 = '0312';

    #[IscedInfo(
        code: '0313',
        label: 'Psychology',
        parent: '031',
        ancestors: ['03', '031'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0313 = '0313';

    #[IscedInfo(
        code: '0314',
        label: 'Sociology and cultural studies',
        parent: '031',
        ancestors: ['03', '031'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0314 = '0314';

    #[IscedInfo(
        code: '0319',
        label: 'Social and behavioural sciences not elsewhere classified',
        parent: '031',
        ancestors: ['03', '031'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0319 = '0319';

    #[IscedInfo(
        code: '0320',
        label: 'Journalism and information not further defined',
        parent: '032',
        ancestors: ['03', '032'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0320 = '0320';

    #[IscedInfo(
        code: '0321',
        label: 'Journalism and reporting',
        parent: '032',
        ancestors: ['03', '032'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0321 = '0321';

    #[IscedInfo(
        code: '0322',
        label: 'Library, information and archival studies',
        parent: '032',
        ancestors: ['03', '032'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0322 = '0322';

    #[IscedInfo(
        code: '0329',
        label: 'Journalism and information not elsewhere classified',
        parent: '032',
        ancestors: ['03', '032'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0329 = '0329';

    #[IscedInfo(
        code: '0388',
        label: 'Inter-disciplinary programmes and qualifications involving social sciences, journalism and information',
        parent: '038',
        ancestors: ['03', '038'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0388 = '0388';

    #[IscedInfo(
        code: '0399',
        label: 'Social sciences, journalism and information not elsewhere classified',
        parent: '039',
        ancestors: ['03', '039'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0399 = '0399';

    #[IscedInfo(
        code: '0400',
        label: 'Business, administration and law not further defined',
        parent: '040',
        ancestors: ['04', '040'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0400 = '0400';

    #[IscedInfo(
        code: '0410',
        label: 'Business and administration not further defined',
        parent: '041',
        ancestors: ['04', '041'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0410 = '0410';

    #[IscedInfo(
        code: '0411',
        label: 'Accounting and taxation',
        parent: '041',
        ancestors: ['04', '041'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0411 = '0411';

    #[IscedInfo(
        code: '0412',
        label: 'Finance, banking and insurance',
        parent: '041',
        ancestors: ['04', '041'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0412 = '0412';

    #[IscedInfo(
        code: '0413',
        label: 'Management and administration',
        parent: '041',
        ancestors: ['04', '041'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0413 = '0413';

    #[IscedInfo(
        code: '0414',
        label: 'Marketing and advertising',
        parent: '041',
        ancestors: ['04', '041'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0414 = '0414';

    #[IscedInfo(
        code: '0415',
        label: 'Secretarial and office work',
        parent: '041',
        ancestors: ['04', '041'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0415 = '0415';

    #[IscedInfo(
        code: '0416',
        label: 'Wholesale and retail sales',
        parent: '041',
        ancestors: ['04', '041'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0416 = '0416';

    #[IscedInfo(
        code: '0417',
        label: 'Work skills',
        parent: '041',
        ancestors: ['04', '041'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0417 = '0417';

    #[IscedInfo(
        code: '0419',
        label: 'Business and administration not elsewhere classified',
        parent: '041',
        ancestors: ['04', '041'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0419 = '0419';

    #[IscedInfo(
        code: '0421',
        label: 'Law',
        parent: '042',
        ancestors: ['04', '042'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0421 = '0421';

    #[IscedInfo(
        code: '0488',
        label: 'Inter-disciplinary programmes and qualifications involving business, administration and law',
        parent: '048',
        ancestors: ['04', '048'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0488 = '0488';

    #[IscedInfo(
        code: '0499',
        label: 'Business, administration and law not elsewhere classified',
        parent: '049',
        ancestors: ['04', '049'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0499 = '0499';

    #[IscedInfo(
        code: '0500',
        label: 'Natural sciences, mathematics and statistics not further defined',
        parent: '050',
        ancestors: ['05', '050'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0500 = '0500';

    #[IscedInfo(
        code: '0510',
        label: 'Biological and related sciences not further defined',
        parent: '051',
        ancestors: ['05', '051'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0510 = '0510';

    #[IscedInfo(
        code: '0511',
        label: 'Biology',
        parent: '051',
        ancestors: ['05', '051'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0511 = '0511';

    #[IscedInfo(
        code: '0512',
        label: 'Biochemistry',
        parent: '051',
        ancestors: ['05', '051'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0512 = '0512';

    #[IscedInfo(
        code: '0519',
        label: 'Biological and related sciences not elsewhere classified',
        parent: '051',
        ancestors: ['05', '051'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0519 = '0519';

    #[IscedInfo(
        code: '0520',
        label: 'Environment not further defined',
        parent: '052',
        ancestors: ['05', '052'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0520 = '0520';

    #[IscedInfo(
        code: '0521',
        label: 'Environmental sciences',
        parent: '052',
        ancestors: ['05', '052'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0521 = '0521';

    #[IscedInfo(
        code: '0522',
        label: 'Natural environments and wildlife',
        parent: '052',
        ancestors: ['05', '052'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0522 = '0522';

    #[IscedInfo(
        code: '0529',
        label: 'Environment not elsewhere classified',
        parent: '052',
        ancestors: ['05', '052'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0529 = '0529';

    #[IscedInfo(
        code: '0530',
        label: 'Physical sciences not further defined',
        parent: '053',
        ancestors: ['05', '053'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0530 = '0530';

    #[IscedInfo(
        code: '0531',
        label: 'Chemistry',
        parent: '053',
        ancestors: ['05', '053'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0531 = '0531';

    #[IscedInfo(
        code: '0532',
        label: 'Earth sciences',
        parent: '053',
        ancestors: ['05', '053'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0532 = '0532';

    #[IscedInfo(
        code: '0533',
        label: 'Physics',
        parent: '053',
        ancestors: ['05', '053'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0533 = '0533';

    #[IscedInfo(
        code: '0539',
        label: 'Physical sciences not elsewhere classified',
        parent: '053',
        ancestors: ['05', '053'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0539 = '0539';

    #[IscedInfo(
        code: '0540',
        label: 'Mathematics and statistics not further defined',
        parent: '054',
        ancestors: ['05', '054'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0540 = '0540';

    #[IscedInfo(
        code: '0541',
        label: 'Mathematics',
        parent: '054',
        ancestors: ['05', '054'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0541 = '0541';

    #[IscedInfo(
        code: '0542',
        label: 'Statistics',
        parent: '054',
        ancestors: ['05', '054'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0542 = '0542';

    #[IscedInfo(
        code: '0588',
        label: 'Inter-disciplinary programmes and qualifications involving natural sciences, mathematics and statistics',
        parent: '058',
        ancestors: ['05', '058'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0588 = '0588';

    #[IscedInfo(
        code: '0599',
        label: 'Natural sciences, mathematics and statistics not elsewhere classified',
        parent: '059',
        ancestors: ['05', '059'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0599 = '0599';

    #[IscedInfo(
        code: '0610',
        label: 'Information and communication technologies not further defined',
        parent: '061',
        ancestors: ['06', '061'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0610 = '0610';

    #[IscedInfo(
        code: '0611',
        label: 'Computer use',
        parent: '061',
        ancestors: ['06', '061'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0611 = '0611';

    #[IscedInfo(
        code: '0612',
        label: 'Database and network design and administration',
        parent: '061',
        ancestors: ['06', '061'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0612 = '0612';

    #[IscedInfo(
        code: '0613',
        label: 'Software and applications development and analysis',
        parent: '061',
        ancestors: ['06', '061'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0613 = '0613';

    #[IscedInfo(
        code: '0619',
        label: 'Information and communication technologies not elsewhere classified',
        parent: '061',
        ancestors: ['06', '061'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0619 = '0619';

    #[IscedInfo(
        code: '0688',
        label: 'Inter-disciplinary programmes and qualifications involving information and communication technologies',
        parent: '068',
        ancestors: ['06', '068'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0688 = '0688';

    #[IscedInfo(
        code: '0700',
        label: 'Engineering, manufacturing and construction not further defined',
        parent: '070',
        ancestors: ['07', '070'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0700 = '0700';

    #[IscedInfo(
        code: '0710',
        label: 'Engineering and engineering trades not further defined',
        parent: '071',
        ancestors: ['07', '071'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0710 = '0710';

    #[IscedInfo(
        code: '0711',
        label: 'Chemical engineering and processes',
        parent: '071',
        ancestors: ['07', '071'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0711 = '0711';

    #[IscedInfo(
        code: '0712',
        label: 'Environmental protection technology',
        parent: '071',
        ancestors: ['07', '071'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0712 = '0712';

    #[IscedInfo(
        code: '0713',
        label: 'Electricity and energy',
        parent: '071',
        ancestors: ['07', '071'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0713 = '0713';

    #[IscedInfo(
        code: '0714',
        label: 'Electronics and automation',
        parent: '071',
        ancestors: ['07', '071'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0714 = '0714';

    #[IscedInfo(
        code: '0715',
        label: 'Mechanics and metal trades',
        parent: '071',
        ancestors: ['07', '071'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0715 = '0715';

    #[IscedInfo(
        code: '0716',
        label: 'Motor vehicles, ships and aircraft',
        parent: '071',
        ancestors: ['07', '071'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0716 = '0716';

    #[IscedInfo(
        code: '0719',
        label: 'Engineering and engineering trades not elsewhere classified',
        parent: '071',
        ancestors: ['07', '071'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0719 = '0719';

    #[IscedInfo(
        code: '0720',
        label: 'Manufacturing and processing not further defined',
        parent: '072',
        ancestors: ['07', '072'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0720 = '0720';

    #[IscedInfo(
        code: '0721',
        label: 'Food processing',
        parent: '072',
        ancestors: ['07', '072'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0721 = '0721';

    #[IscedInfo(
        code: '0722',
        label: 'Materials (glass, paper, plastic and wood)',
        parent: '072',
        ancestors: ['07', '072'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0722 = '0722';

    #[IscedInfo(
        code: '0723',
        label: 'Textiles (clothes, footwear and leather)',
        parent: '072',
        ancestors: ['07', '072'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0723 = '0723';

    #[IscedInfo(
        code: '0724',
        label: 'Mining and extraction',
        parent: '072',
        ancestors: ['07', '072'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0724 = '0724';

    #[IscedInfo(
        code: '0729',
        label: 'Manufacturing and processing not elsewhere classified',
        parent: '072',
        ancestors: ['07', '072'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0729 = '0729';

    #[IscedInfo(
        code: '0730',
        label: 'Architecture and construction not further defined',
        parent: '073',
        ancestors: ['07', '073'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0730 = '0730';

    #[IscedInfo(
        code: '0731',
        label: 'Architecture and town planning',
        parent: '073',
        ancestors: ['07', '073'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0731 = '0731';

    #[IscedInfo(
        code: '0732',
        label: 'Building and civil engineering',
        parent: '073',
        ancestors: ['07', '073'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0732 = '0732';

    #[IscedInfo(
        code: '0788',
        label: 'Inter-disciplinary programmes and qualifications involving engineering, manufacturing and construction',
        parent: '078',
        ancestors: ['07', '078'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0788 = '0788';

    #[IscedInfo(
        code: '0799',
        label: 'Engineering, manufacturing and construction not elsewhere classified',
        parent: '079',
        ancestors: ['07', '079'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0799 = '0799';

    #[IscedInfo(
        code: '0800',
        label: 'Agriculture, forestry, fisheries and veterinary not further defined',
        parent: '080',
        ancestors: ['08', '080'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0800 = '0800';

    #[IscedInfo(
        code: '0810',
        label: 'Agriculture not further defined',
        parent: '081',
        ancestors: ['08', '081'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0810 = '0810';

    #[IscedInfo(
        code: '0811',
        label: 'Crop and livestock production',
        parent: '081',
        ancestors: ['08', '081'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0811 = '0811';

    #[IscedInfo(
        code: '0812',
        label: 'Horticulture',
        parent: '081',
        ancestors: ['08', '081'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0812 = '0812';

    #[IscedInfo(
        code: '0819',
        label: 'Agriculture not elsewhere classified',
        parent: '081',
        ancestors: ['08', '081'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0819 = '0819';

    #[IscedInfo(
        code: '0821',
        label: 'Forestry',
        parent: '082',
        ancestors: ['08', '082'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0821 = '0821';

    #[IscedInfo(
        code: '0831',
        label: 'Fisheries',
        parent: '083',
        ancestors: ['08', '083'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0831 = '0831';

    #[IscedInfo(
        code: '0841',
        label: 'Veterinary',
        parent: '084',
        ancestors: ['08', '084'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0841 = '0841';

    #[IscedInfo(
        code: '0888',
        label: 'Inter-disciplinary programmes and qualifications involving agriculture, forestry, fisheries and veterinary',
        parent: '088',
        ancestors: ['08', '088'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0888 = '0888';

    #[IscedInfo(
        code: '0899',
        label: 'Agriculture, forestry, fisheries and veterinary not elsewhere classified',
        parent: '089',
        ancestors: ['08', '089'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0899 = '0899';

    #[IscedInfo(
        code: '0900',
        label: 'Health and welfare not further defined',
        parent: '090',
        ancestors: ['09', '090'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0900 = '0900';

    #[IscedInfo(
        code: '0910',
        label: 'Health not further defined',
        parent: '091',
        ancestors: ['09', '091'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0910 = '0910';

    #[IscedInfo(
        code: '0911',
        label: 'Dental studies',
        parent: '091',
        ancestors: ['09', '091'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0911 = '0911';

    #[IscedInfo(
        code: '0912',
        label: 'Medicine',
        parent: '091',
        ancestors: ['09', '091'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0912 = '0912';

    #[IscedInfo(
        code: '0913',
        label: 'Nursing and midwifery',
        parent: '091',
        ancestors: ['09', '091'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0913 = '0913';

    #[IscedInfo(
        code: '0914',
        label: 'Medical diagnostic and treatment technology',
        parent: '091',
        ancestors: ['09', '091'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0914 = '0914';

    #[IscedInfo(
        code: '0915',
        label: 'Therapy and rehabilitation',
        parent: '091',
        ancestors: ['09', '091'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0915 = '0915';

    #[IscedInfo(
        code: '0916',
        label: 'Pharmacy',
        parent: '091',
        ancestors: ['09', '091'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0916 = '0916';

    #[IscedInfo(
        code: '0917',
        label: 'Traditional and complementary medicine and therapy',
        parent: '091',
        ancestors: ['09', '091'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0917 = '0917';

    #[IscedInfo(
        code: '0919',
        label: 'Health not elsewhere classified',
        parent: '091',
        ancestors: ['09', '091'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0919 = '0919';

    #[IscedInfo(
        code: '0920',
        label: 'Welfare not further defined',
        parent: '092',
        ancestors: ['09', '092'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0920 = '0920';

    #[IscedInfo(
        code: '0921',
        label: 'Care of the elderly and of disabled adults',
        parent: '092',
        ancestors: ['09', '092'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0921 = '0921';

    #[IscedInfo(
        code: '0922',
        label: 'Child care and youth services',
        parent: '092',
        ancestors: ['09', '092'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0922 = '0922';

    #[IscedInfo(
        code: '0923',
        label: 'Social work and counselling',
        parent: '092',
        ancestors: ['09', '092'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0923 = '0923';

    #[IscedInfo(
        code: '0929',
        label: 'Welfare not elsewhere classified',
        parent: '092',
        ancestors: ['09', '092'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0929 = '0929';

    #[IscedInfo(
        code: '0988',
        label: 'Inter-disciplinary programmes and qualifications involving health and welfare',
        parent: '098',
        ancestors: ['09', '098'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0988 = '0988';

    #[IscedInfo(
        code: '0999',
        label: 'Health and welfare not elsewhere classified',
        parent: '099',
        ancestors: ['09', '099'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_0999 = '0999';

    #[IscedInfo(
        code: '1000',
        label: 'Services not further defined',
        parent: '100',
        ancestors: ['10', '100'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_1000 = '1000';

    #[IscedInfo(
        code: '1010',
        label: 'Personal services not further defined',
        parent: '101',
        ancestors: ['10', '101'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_1010 = '1010';

    #[IscedInfo(
        code: '1011',
        label: 'Domestic services',
        parent: '101',
        ancestors: ['10', '101'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_1011 = '1011';

    #[IscedInfo(
        code: '1012',
        label: 'Hair and beauty services',
        parent: '101',
        ancestors: ['10', '101'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_1012 = '1012';

    #[IscedInfo(
        code: '1013',
        label: 'Hotel, restaurants and catering',
        parent: '101',
        ancestors: ['10', '101'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_1013 = '1013';

    #[IscedInfo(
        code: '1014',
        label: 'Sports',
        parent: '101',
        ancestors: ['10', '101'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_1014 = '1014';

    #[IscedInfo(
        code: '1015',
        label: 'Travel, tourism and leisure',
        parent: '101',
        ancestors: ['10', '101'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_1015 = '1015';

    #[IscedInfo(
        code: '1019',
        label: 'Personal services not elsewhere classified',
        parent: '101',
        ancestors: ['10', '101'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_1019 = '1019';

    #[IscedInfo(
        code: '1020',
        label: 'Hygiene and occupational health services not further defined',
        parent: '102',
        ancestors: ['10', '102'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_1020 = '1020';

    #[IscedInfo(
        code: '1021',
        label: 'Community sanitation',
        parent: '102',
        ancestors: ['10', '102'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_1021 = '1021';

    #[IscedInfo(
        code: '1022',
        label: 'Occupational health and safety',
        parent: '102',
        ancestors: ['10', '102'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_1022 = '1022';

    #[IscedInfo(
        code: '1029',
        label: 'Hygiene and occupational health services not elsewhere classified',
        parent: '102',
        ancestors: ['10', '102'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_1029 = '1029';

    #[IscedInfo(
        code: '1030',
        label: 'Security services not further defined',
        parent: '103',
        ancestors: ['10', '103'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_1030 = '1030';

    #[IscedInfo(
        code: '1031',
        label: 'Military and defence',
        parent: '103',
        ancestors: ['10', '103'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_1031 = '1031';

    #[IscedInfo(
        code: '1032',
        label: 'Protection of persons and property',
        parent: '103',
        ancestors: ['10', '103'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_1032 = '1032';

    #[IscedInfo(
        code: '1039',
        label: 'Security services not elsewhere classified',
        parent: '103',
        ancestors: ['10', '103'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_1039 = '1039';

    #[IscedInfo(
        code: '1041',
        label: 'Transport services',
        parent: '104',
        ancestors: ['10', '104'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_1041 = '1041';

    #[IscedInfo(
        code: '1088',
        label: 'Inter-disciplinary programmes and qualifications involving services',
        parent: '108',
        ancestors: ['10', '108'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_1088 = '1088';

    #[IscedInfo(
        code: '1099',
        label: 'Services not elsewhere classified',
        parent: '109',
        ancestors: ['10', '109'],
        children: [],
        descendants: [],
        tier: 3,
    )]
    case ISCEDF_1099 = '1099';

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
            'IscedF2013',
        );
    }

    public function label(): string
    {
        return EnumDataUtils::getFieldValue(
            self::iscedData(),
            $this->value,
            'label',
            'IscedF2013',
        );
    }

    public function parent(): ?string
    {
        $v = EnumDataUtils::getFieldValue(
            self::iscedData(),
            $this->value,
            'parent',
            'IscedF2013',
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
            'IscedF2013',
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
            'IscedF2013',
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
            'IscedF2013',
        );
    }

    public function tier(): int
    {
        return EnumDataUtils::getIntegerFieldValue(
            self::iscedData(),
            $this->value,
            'tier',
            'IscedF2013',
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
