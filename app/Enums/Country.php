<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\EnumGeneration\EnumDataUtils;
use Attribute;

/**
 * Attribute for storing country information on enum cases.
 *
 * @param  string  $country_name  The full name of the country
 * @param  string  $alpha3  The ISO 3166 Alpha-3 code
 * @param  string  $numeric  The ISO 3166 numeric code
 * @param  bool  $useThe  Whether to prefix the country name with 'the' in sentences
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class CountryInfo
{
    /**
     * @param  string  $country_name  The full name of the country
     * @param  string  $alpha3  The ISO 3166 Alpha-3 code
     * @param  string  $numeric  The ISO 3166 numeric code
     * @param  bool  $useThe  Whether to prefix the country name with 'the' in sentences
     */
    public function __construct(
        public string $country_name,
        public string $alpha3,
        public string $numeric,
        public bool $useThe = false,
    ) {}
}

/**
 * Country enum with ISO 3166-1 codes and exonyms.
 *
 * This enum is intended for use wherever a standardized list of countries is
 * required, such as form dropdowns, validation, or formatting for display.
 *
 * This enum provides two ways to access attribute data:
 *
 *   1. **Getter Methods** (Recommended for common use):
 *      - `country_name()`, `alpha2()`, `alpha3()`, `numeric()`, `requiresThe()`,
 *      `sentenceName()`
 *      - These methods use cached data for optimal performance
 *      - Only include the most commonly used attributes
 *      - Add new getter methods whenever an attribute becomes frequently used
 *
 *   2. **Direct Reflection** (For advanced/rare use cases):
 *      - Access any attribute directly via PHP's reflection API
 *      - Example:
 *        `$reflection = new \ReflectionEnumUnitCase(Country::class, 'US');`
 *      - Then:
 *        `$info = $reflection->getAttributes(CountryInfo::class)[0]->newInstance();`
 *      - Provides access to ALL attributes: country_name, alpha3, numeric, useThe
 *
 * **Adding New Getter Methods:**
 *
 * When an attribute becomes commonly used, add a getter method following this
 * pattern:
 *   ```php
 *   public function attributeName(): string
 *   {
 *       $data = self::countryData();
 *       return EnumDataUtils::getFieldValue($data, $this->value, 'attributeName', 'country');
 *   }
 *   ```
 *
 * Also update the `countryData()` method to include the new attribute in the
 * returned array.
 *
 * Each case's string value represents the ISO 3166-1 alpha-2 code (e.g., 'US'
 * for United States of America). The case name is a human-readable identifier
 * for the country.
 *
 * Each case includes:
 *   - country_name // Country name (exonym, e.g., "United States of America")
 *   - alpha3 // ISO 3166-1 alpha-3 code (e.g., "USA")
 *   - numeric // ISO 3166-1 numeric code (e.g., "840")
 *   - useThe // Whether to prefix the country name with 'the' in sentences
 */
enum Country: string
{
    #[CountryInfo(country_name: 'United States of America', alpha3: 'USA', numeric: '840', useThe: true)]
    case US = 'US';
    #[CountryInfo(country_name: 'Afghanistan', alpha3: 'AFG', numeric: '004', useThe: false)]
    case AF = 'AF';
    #[CountryInfo(country_name: 'Åland Islands', alpha3: 'ALA', numeric: '248', useThe: true)]
    case AX = 'AX';
    #[CountryInfo(country_name: 'Albania', alpha3: 'ALB', numeric: '008', useThe: false)]
    case AL = 'AL';
    #[CountryInfo(country_name: 'Algeria', alpha3: 'DZA', numeric: '012', useThe: false)]
    case DZ = 'DZ';
    #[CountryInfo(country_name: 'American Samoa', alpha3: 'ASM', numeric: '016', useThe: false)]
    case AS = 'AS';
    #[CountryInfo(country_name: 'Andorra', alpha3: 'AND', numeric: '020', useThe: false)]
    case AD = 'AD';
    #[CountryInfo(country_name: 'Angola', alpha3: 'AGO', numeric: '024', useThe: false)]
    case AO = 'AO';
    #[CountryInfo(country_name: 'Anguilla', alpha3: 'AIA', numeric: '660', useThe: false)]
    case AI = 'AI';
    #[CountryInfo(country_name: 'Antarctica', alpha3: 'ATA', numeric: '010', useThe: false)]
    case AQ = 'AQ';
    #[CountryInfo(country_name: 'Antigua and Barbuda', alpha3: 'ATG', numeric: '028', useThe: false)]
    case AG = 'AG';
    #[CountryInfo(country_name: 'Argentina', alpha3: 'ARG', numeric: '032', useThe: false)]
    case AR = 'AR';
    #[CountryInfo(country_name: 'Armenia', alpha3: 'ARM', numeric: '051', useThe: false)]
    case AM = 'AM';
    #[CountryInfo(country_name: 'Aruba', alpha3: 'ABW', numeric: '533', useThe: false)]
    case AW = 'AW';
    #[CountryInfo(country_name: 'Australia', alpha3: 'AUS', numeric: '036', useThe: false)]
    case AU = 'AU';
    #[CountryInfo(country_name: 'Austria', alpha3: 'AUT', numeric: '040', useThe: false)]
    case AT = 'AT';
    #[CountryInfo(country_name: 'Azerbaijan', alpha3: 'AZE', numeric: '031', useThe: false)]
    case AZ = 'AZ';
    #[CountryInfo(country_name: 'Bahamas', alpha3: 'BHS', numeric: '044', useThe: true)]
    case BS = 'BS';
    #[CountryInfo(country_name: 'Bahrain', alpha3: 'BHR', numeric: '048', useThe: false)]
    case BH = 'BH';
    #[CountryInfo(country_name: 'Bangladesh', alpha3: 'BGD', numeric: '050', useThe: false)]
    case BD = 'BD';
    #[CountryInfo(country_name: 'Barbados', alpha3: 'BRB', numeric: '052', useThe: false)]
    case BB = 'BB';
    #[CountryInfo(country_name: 'Belarus', alpha3: 'BLR', numeric: '112', useThe: false)]
    case BY = 'BY';
    #[CountryInfo(country_name: 'Belgium', alpha3: 'BEL', numeric: '056', useThe: false)]
    case BE = 'BE';
    #[CountryInfo(country_name: 'Belize', alpha3: 'BLZ', numeric: '084', useThe: false)]
    case BZ = 'BZ';
    #[CountryInfo(country_name: 'Benin', alpha3: 'BEN', numeric: '204', useThe: false)]
    case BJ = 'BJ';
    #[CountryInfo(country_name: 'Bermuda', alpha3: 'BMU', numeric: '060', useThe: false)]
    case BM = 'BM';
    #[CountryInfo(country_name: 'Bhutan', alpha3: 'BTN', numeric: '064', useThe: false)]
    case BT = 'BT';
    #[CountryInfo(country_name: 'Bolivia', alpha3: 'BOL', numeric: '068', useThe: false)]
    case BO = 'BO';
    #[CountryInfo(country_name: 'Bonaire, Sint Eustatius and Saba', alpha3: 'BES', numeric: '535', useThe: false)]
    case BQ = 'BQ';
    #[CountryInfo(country_name: 'Bosnia and Herzegovina', alpha3: 'BIH', numeric: '070', useThe: false)]
    case BA = 'BA';
    #[CountryInfo(country_name: 'Botswana', alpha3: 'BWA', numeric: '072', useThe: false)]
    case BW = 'BW';
    #[CountryInfo(country_name: 'Bouvet Island', alpha3: 'BVT', numeric: '074', useThe: false)]
    case BV = 'BV';
    #[CountryInfo(country_name: 'Brazil', alpha3: 'BRA', numeric: '076', useThe: false)]
    case BR = 'BR';
    #[CountryInfo(country_name: 'British Indian Ocean Territory', alpha3: 'IOT', numeric: '086', useThe: true)]
    case IO = 'IO';
    #[CountryInfo(country_name: 'Brunei', alpha3: 'BRN', numeric: '096', useThe: false)]
    case BN = 'BN';
    #[CountryInfo(country_name: 'Bulgaria', alpha3: 'BGR', numeric: '100', useThe: false)]
    case BG = 'BG';
    #[CountryInfo(country_name: 'Burkina Faso', alpha3: 'BFA', numeric: '854', useThe: false)]
    case BF = 'BF';
    #[CountryInfo(country_name: 'Burundi', alpha3: 'BDI', numeric: '108', useThe: false)]
    case BI = 'BI';
    #[CountryInfo(country_name: 'Cambodia', alpha3: 'KHM', numeric: '116', useThe: false)]
    case KH = 'KH';
    #[CountryInfo(country_name: 'Cameroon', alpha3: 'CMR', numeric: '120', useThe: false)]
    case CM = 'CM';
    #[CountryInfo(country_name: 'Canada', alpha3: 'CAN', numeric: '124', useThe: false)]
    case CA = 'CA';
    #[CountryInfo(country_name: 'Cape Verde', alpha3: 'CPV', numeric: '132', useThe: false)]
    case CV = 'CV';
    #[CountryInfo(country_name: 'Cayman Islands', alpha3: 'CYM', numeric: '136', useThe: true)]
    case KY = 'KY';
    #[CountryInfo(country_name: 'Central African Republic', alpha3: 'CAF', numeric: '140', useThe: true)]
    case CF = 'CF';
    #[CountryInfo(country_name: 'Chad', alpha3: 'TCD', numeric: '148', useThe: false)]
    case TD = 'TD';
    #[CountryInfo(country_name: 'Chile', alpha3: 'CHL', numeric: '152', useThe: false)]
    case CL = 'CL';
    #[CountryInfo(country_name: 'China', alpha3: 'CHN', numeric: '156', useThe: false)]
    case CN = 'CN';
    #[CountryInfo(country_name: 'Christmas Island', alpha3: 'CXR', numeric: '162', useThe: false)]
    case CX = 'CX';
    #[CountryInfo(country_name: 'Cocos (Keeling) Islands', alpha3: 'CCK', numeric: '166', useThe: true)]
    case CC = 'CC';
    #[CountryInfo(country_name: 'Colombia', alpha3: 'COL', numeric: '170', useThe: false)]
    case CO = 'CO';
    #[CountryInfo(country_name: 'Comoros', alpha3: 'COM', numeric: '174', useThe: true)]
    case KM = 'KM';
    #[CountryInfo(country_name: 'Republic of the Congo', alpha3: 'COG', numeric: '178', useThe: true)]
    case CG = 'CG';
    #[CountryInfo(country_name: 'Democratic Republic of the Congo', alpha3: 'COD', numeric: '180', useThe: true)]
    case CD = 'CD';
    #[CountryInfo(country_name: 'Cook Islands', alpha3: 'COK', numeric: '184', useThe: true)]
    case CK = 'CK';
    #[CountryInfo(country_name: 'Costa Rica', alpha3: 'CRI', numeric: '188', useThe: false)]
    case CR = 'CR';
    #[CountryInfo(country_name: 'Croatia', alpha3: 'HRV', numeric: '191', useThe: false)]
    case HR = 'HR';
    #[CountryInfo(country_name: 'Cuba', alpha3: 'CUB', numeric: '192', useThe: false)]
    case CU = 'CU';
    #[CountryInfo(country_name: 'Curaçao', alpha3: 'CUW', numeric: '531', useThe: false)]
    case CW = 'CW';
    #[CountryInfo(country_name: 'Cyprus', alpha3: 'CYP', numeric: '196', useThe: false)]
    case CY = 'CY';
    #[CountryInfo(country_name: 'Czechia', alpha3: 'CZE', numeric: '203', useThe: false)]
    case CZ = 'CZ';
    #[CountryInfo(country_name: 'Denmark', alpha3: 'DNK', numeric: '208', useThe: false)]
    case DK = 'DK';
    #[CountryInfo(country_name: 'Djibouti', alpha3: 'DJI', numeric: '262', useThe: false)]
    case DJ = 'DJ';
    #[CountryInfo(country_name: 'Dominica', alpha3: 'DMA', numeric: '212', useThe: false)]
    case DM = 'DM';
    #[CountryInfo(country_name: 'Dominican Republic', alpha3: 'DOM', numeric: '214', useThe: true)]
    case DO = 'DO';
    #[CountryInfo(country_name: 'Ecuador', alpha3: 'ECU', numeric: '218', useThe: false)]
    case EC = 'EC';
    #[CountryInfo(country_name: 'Egypt', alpha3: 'EGY', numeric: '818', useThe: false)]
    case EG = 'EG';
    #[CountryInfo(country_name: 'El Salvador', alpha3: 'SLV', numeric: '222', useThe: false)]
    case SV = 'SV';
    #[CountryInfo(country_name: 'Equatorial Guinea', alpha3: 'GNQ', numeric: '226', useThe: false)]
    case GQ = 'GQ';
    #[CountryInfo(country_name: 'Eritrea', alpha3: 'ERI', numeric: '232', useThe: false)]
    case ER = 'ER';
    #[CountryInfo(country_name: 'Estonia', alpha3: 'EST', numeric: '233', useThe: false)]
    case EE = 'EE';
    #[CountryInfo(country_name: 'Eswatini', alpha3: 'SWZ', numeric: '748', useThe: false)]
    case SZ = 'SZ';
    #[CountryInfo(country_name: 'Ethiopia', alpha3: 'ETH', numeric: '231', useThe: false)]
    case ET = 'ET';
    #[CountryInfo(country_name: 'Falkland Islands', alpha3: 'FLK', numeric: '238', useThe: true)]
    case FK = 'FK';
    #[CountryInfo(country_name: 'Faroe Islands', alpha3: 'FRO', numeric: '234', useThe: true)]
    case FO = 'FO';
    #[CountryInfo(country_name: 'Fiji', alpha3: 'FJI', numeric: '242', useThe: false)]
    case FJ = 'FJ';
    #[CountryInfo(country_name: 'Finland', alpha3: 'FIN', numeric: '246', useThe: false)]
    case FI = 'FI';
    #[CountryInfo(country_name: 'France', alpha3: 'FRA', numeric: '250', useThe: false)]
    case FR = 'FR';
    #[CountryInfo(country_name: 'French Guiana', alpha3: 'GUF', numeric: '254', useThe: false)]
    case GF = 'GF';
    #[CountryInfo(country_name: 'French Polynesia', alpha3: 'PYF', numeric: '258', useThe: false)]
    case PF = 'PF';
    #[CountryInfo(country_name: 'French Southern and Antarctic Lands', alpha3: 'ATF', numeric: '260', useThe: true)]
    case TF = 'TF';
    #[CountryInfo(country_name: 'Gabon', alpha3: 'GAB', numeric: '266', useThe: false)]
    case GA = 'GA';
    #[CountryInfo(country_name: 'Gambia', alpha3: 'GMB', numeric: '270', useThe: true)]
    case GM = 'GM';
    #[CountryInfo(country_name: 'Georgia', alpha3: 'GEO', numeric: '268', useThe: false)]
    case GE = 'GE';
    #[CountryInfo(country_name: 'Germany', alpha3: 'DEU', numeric: '276', useThe: false)]
    case DE = 'DE';
    #[CountryInfo(country_name: 'Ghana', alpha3: 'GHA', numeric: '288', useThe: false)]
    case GH = 'GH';
    #[CountryInfo(country_name: 'Gibraltar', alpha3: 'GIB', numeric: '292', useThe: false)]
    case GI = 'GI';
    #[CountryInfo(country_name: 'Greece', alpha3: 'GRC', numeric: '300', useThe: false)]
    case GR = 'GR';
    #[CountryInfo(country_name: 'Greenland', alpha3: 'GRL', numeric: '304', useThe: false)]
    case GL = 'GL';
    #[CountryInfo(country_name: 'Grenada', alpha3: 'GRD', numeric: '308', useThe: false)]
    case GD = 'GD';
    #[CountryInfo(country_name: 'Guadeloupe', alpha3: 'GLP', numeric: '312', useThe: false)]
    case GP = 'GP';
    #[CountryInfo(country_name: 'Guam', alpha3: 'GUM', numeric: '316', useThe: false)]
    case GU = 'GU';
    #[CountryInfo(country_name: 'Guatemala', alpha3: 'GTM', numeric: '320', useThe: false)]
    case GT = 'GT';
    #[CountryInfo(country_name: 'Guernsey', alpha3: 'GGY', numeric: '831', useThe: false)]
    case GG = 'GG';
    #[CountryInfo(country_name: 'Guinea', alpha3: 'GIN', numeric: '324', useThe: false)]
    case GN = 'GN';
    #[CountryInfo(country_name: 'Guinea-Bissau', alpha3: 'GNB', numeric: '624', useThe: false)]
    case GW = 'GW';
    #[CountryInfo(country_name: 'Guyana', alpha3: 'GUY', numeric: '328', useThe: false)]
    case GY = 'GY';
    #[CountryInfo(country_name: 'Haiti', alpha3: 'HTI', numeric: '332', useThe: false)]
    case HT = 'HT';
    #[CountryInfo(country_name: 'Heard Island and McDonald Islands', alpha3: 'HMD', numeric: '334', useThe: true)]
    case HM = 'HM';
    #[CountryInfo(country_name: 'Honduras', alpha3: 'HND', numeric: '340', useThe: false)]
    case HN = 'HN';
    #[CountryInfo(country_name: 'Hong Kong', alpha3: 'HKG', numeric: '344', useThe: false)]
    case HK = 'HK';
    #[CountryInfo(country_name: 'Hungary', alpha3: 'HUN', numeric: '348', useThe: false)]
    case HU = 'HU';
    #[CountryInfo(country_name: 'Iceland', alpha3: 'ISL', numeric: '352', useThe: false)]
    case IS = 'IS';
    #[CountryInfo(country_name: 'India', alpha3: 'IND', numeric: '356', useThe: false)]
    case IN = 'IN';
    #[CountryInfo(country_name: 'Indonesia', alpha3: 'IDN', numeric: '360', useThe: false)]
    case ID = 'ID';
    #[CountryInfo(country_name: 'Iran', alpha3: 'IRN', numeric: '364', useThe: false)]
    case IR = 'IR';
    #[CountryInfo(country_name: 'Iraq', alpha3: 'IRQ', numeric: '368', useThe: false)]
    case IQ = 'IQ';
    #[CountryInfo(country_name: 'Ireland', alpha3: 'IRL', numeric: '372', useThe: false)]
    case IE = 'IE';
    #[CountryInfo(country_name: 'Isle of Man', alpha3: 'IMN', numeric: '833', useThe: false)]
    case IM = 'IM';
    #[CountryInfo(country_name: 'Israel', alpha3: 'ISR', numeric: '376', useThe: false)]
    case IL = 'IL';
    #[CountryInfo(country_name: 'Italy', alpha3: 'ITA', numeric: '380', useThe: false)]
    case IT = 'IT';
    #[CountryInfo(country_name: 'Ivory Coast', alpha3: 'CIV', numeric: '384', useThe: true)]
    case CI = 'CI';
    #[CountryInfo(country_name: 'Jamaica', alpha3: 'JAM', numeric: '388', useThe: false)]
    case JM = 'JM';
    #[CountryInfo(country_name: 'Japan', alpha3: 'JPN', numeric: '392', useThe: false)]
    case JP = 'JP';
    #[CountryInfo(country_name: 'Jersey', alpha3: 'JEY', numeric: '832', useThe: false)]
    case JE = 'JE';
    #[CountryInfo(country_name: 'Jordan', alpha3: 'JOR', numeric: '400', useThe: false)]
    case JO = 'JO';
    #[CountryInfo(country_name: 'Kazakhstan', alpha3: 'KAZ', numeric: '398', useThe: false)]
    case KZ = 'KZ';
    #[CountryInfo(country_name: 'Kenya', alpha3: 'KEN', numeric: '404', useThe: false)]
    case KE = 'KE';
    #[CountryInfo(country_name: 'Kiribati', alpha3: 'KIR', numeric: '296', useThe: false)]
    case KI = 'KI';
    #[CountryInfo(country_name: 'Kosovo', alpha3: 'XKK', numeric: '983', useThe: false)]
    case XK = 'XK';
    #[CountryInfo(country_name: 'Kuwait', alpha3: 'KWT', numeric: '414', useThe: false)]
    case KW = 'KW';
    #[CountryInfo(country_name: 'Kyrgyzstan', alpha3: 'KGZ', numeric: '417', useThe: false)]
    case KG = 'KG';
    #[CountryInfo(country_name: 'Laos', alpha3: 'LAO', numeric: '418', useThe: false)]
    case LA = 'LA';
    #[CountryInfo(country_name: 'Latvia', alpha3: 'LVA', numeric: '428', useThe: false)]
    case LV = 'LV';
    #[CountryInfo(country_name: 'Lebanon', alpha3: 'LBN', numeric: '422', useThe: false)]
    case LB = 'LB';
    #[CountryInfo(country_name: 'Lesotho', alpha3: 'LSO', numeric: '426', useThe: false)]
    case LS = 'LS';
    #[CountryInfo(country_name: 'Liberia', alpha3: 'LBR', numeric: '430', useThe: false)]
    case LR = 'LR';
    #[CountryInfo(country_name: 'Libya', alpha3: 'LBY', numeric: '434', useThe: false)]
    case LY = 'LY';
    #[CountryInfo(country_name: 'Liechtenstein', alpha3: 'LIE', numeric: '438', useThe: false)]
    case LI = 'LI';
    #[CountryInfo(country_name: 'Lithuania', alpha3: 'LTU', numeric: '440', useThe: false)]
    case LT = 'LT';
    #[CountryInfo(country_name: 'Luxembourg', alpha3: 'LUX', numeric: '442', useThe: false)]
    case LU = 'LU';
    #[CountryInfo(country_name: 'Macao', alpha3: 'MAC', numeric: '446', useThe: false)]
    case MO = 'MO';
    #[CountryInfo(country_name: 'Madagascar', alpha3: 'MDG', numeric: '450', useThe: false)]
    case MG = 'MG';
    #[CountryInfo(country_name: 'Malawi', alpha3: 'MWI', numeric: '454', useThe: false)]
    case MW = 'MW';
    #[CountryInfo(country_name: 'Malaysia', alpha3: 'MYS', numeric: '458', useThe: false)]
    case MY = 'MY';
    #[CountryInfo(country_name: 'Maldives', alpha3: 'MDV', numeric: '462', useThe: true)]
    case MV = 'MV';
    #[CountryInfo(country_name: 'Mali', alpha3: 'MLI', numeric: '466', useThe: false)]
    case ML = 'ML';
    #[CountryInfo(country_name: 'Malta', alpha3: 'MLT', numeric: '470', useThe: false)]
    case MT = 'MT';
    #[CountryInfo(country_name: 'Marshall Islands', alpha3: 'MHL', numeric: '584', useThe: true)]
    case MH = 'MH';
    #[CountryInfo(country_name: 'Martinique', alpha3: 'MTQ', numeric: '474', useThe: false)]
    case MQ = 'MQ';
    #[CountryInfo(country_name: 'Mauritania', alpha3: 'MRT', numeric: '478', useThe: false)]
    case MR = 'MR';
    #[CountryInfo(country_name: 'Mauritius', alpha3: 'MUS', numeric: '480', useThe: false)]
    case MU = 'MU';
    #[CountryInfo(country_name: 'Mayotte', alpha3: 'MYT', numeric: '175', useThe: false)]
    case YT = 'YT';
    #[CountryInfo(country_name: 'Mexico', alpha3: 'MEX', numeric: '484', useThe: false)]
    case MX = 'MX';
    #[CountryInfo(country_name: 'Micronesia', alpha3: 'FSM', numeric: '583', useThe: false)]
    case FM = 'FM';
    #[CountryInfo(country_name: 'Moldova', alpha3: 'MDA', numeric: '498', useThe: false)]
    case MD = 'MD';
    #[CountryInfo(country_name: 'Monaco', alpha3: 'MCO', numeric: '492', useThe: false)]
    case MC = 'MC';
    #[CountryInfo(country_name: 'Mongolia', alpha3: 'MNG', numeric: '496', useThe: false)]
    case MN = 'MN';
    #[CountryInfo(country_name: 'Montenegro', alpha3: 'MNE', numeric: '499', useThe: false)]
    case ME = 'ME';
    #[CountryInfo(country_name: 'Montserrat', alpha3: 'MSR', numeric: '500', useThe: false)]
    case MS = 'MS';
    #[CountryInfo(country_name: 'Morocco', alpha3: 'MAR', numeric: '504', useThe: false)]
    case MA = 'MA';
    #[CountryInfo(country_name: 'Mozambique', alpha3: 'MOZ', numeric: '508', useThe: false)]
    case MZ = 'MZ';
    #[CountryInfo(country_name: 'Myanmar', alpha3: 'MMR', numeric: '104', useThe: false)]
    case MM = 'MM';
    #[CountryInfo(country_name: 'Namibia', alpha3: 'NAM', numeric: '516', useThe: false)]
    case NA = 'NA';
    #[CountryInfo(country_name: 'Nauru', alpha3: 'NRU', numeric: '520', useThe: false)]
    case NR = 'NR';
    #[CountryInfo(country_name: 'Nepal', alpha3: 'NPL', numeric: '524', useThe: false)]
    case NP = 'NP';
    #[CountryInfo(country_name: 'Netherlands', alpha3: 'NLD', numeric: '528', useThe: true)]
    case NL = 'NL';
    #[CountryInfo(country_name: 'New Caledonia', alpha3: 'NCL', numeric: '540', useThe: false)]
    case NC = 'NC';
    #[CountryInfo(country_name: 'New Zealand', alpha3: 'NZL', numeric: '554', useThe: false)]
    case NZ = 'NZ';
    #[CountryInfo(country_name: 'Nicaragua', alpha3: 'NIC', numeric: '558', useThe: false)]
    case NI = 'NI';
    #[CountryInfo(country_name: 'Niger', alpha3: 'NER', numeric: '562', useThe: false)]
    case NE = 'NE';
    #[CountryInfo(country_name: 'Nigeria', alpha3: 'NGA', numeric: '566', useThe: false)]
    case NG = 'NG';
    #[CountryInfo(country_name: 'Niue', alpha3: 'NIU', numeric: '570', useThe: false)]
    case NU = 'NU';
    #[CountryInfo(country_name: 'Norfolk Island', alpha3: 'NFK', numeric: '574', useThe: false)]
    case NF = 'NF';
    #[CountryInfo(country_name: 'North Korea', alpha3: 'PRK', numeric: '408', useThe: false)]
    case KP = 'KP';
    #[CountryInfo(country_name: 'North Macedonia', alpha3: 'MKD', numeric: '807', useThe: false)]
    case MK = 'MK';
    #[CountryInfo(country_name: 'Northern Mariana Islands', alpha3: 'MNP', numeric: '580', useThe: true)]
    case MP = 'MP';
    #[CountryInfo(country_name: 'Norway', alpha3: 'NOR', numeric: '578', useThe: false)]
    case NO = 'NO';
    #[CountryInfo(country_name: 'Oman', alpha3: 'OMN', numeric: '512', useThe: false)]
    case OM = 'OM';
    #[CountryInfo(country_name: 'Pakistan', alpha3: 'PAK', numeric: '586', useThe: false)]
    case PK = 'PK';
    #[CountryInfo(country_name: 'Palau', alpha3: 'PLW', numeric: '585', useThe: false)]
    case PW = 'PW';
    #[CountryInfo(country_name: 'Palestine', alpha3: 'PSE', numeric: '275', useThe: false)]
    case PS = 'PS';
    #[CountryInfo(country_name: 'Panama', alpha3: 'PAN', numeric: '591', useThe: false)]
    case PA = 'PA';
    #[CountryInfo(country_name: 'Papua New Guinea', alpha3: 'PNG', numeric: '598', useThe: false)]
    case PG = 'PG';
    #[CountryInfo(country_name: 'Paraguay', alpha3: 'PRY', numeric: '600', useThe: false)]
    case PY = 'PY';
    #[CountryInfo(country_name: 'Peru', alpha3: 'PER', numeric: '604', useThe: false)]
    case PE = 'PE';
    #[CountryInfo(country_name: 'Philippines', alpha3: 'PHL', numeric: '608', useThe: true)]
    case PH = 'PH';
    #[CountryInfo(country_name: 'Pitcairn', alpha3: 'PCN', numeric: '612', useThe: false)]
    case PN = 'PN';
    #[CountryInfo(country_name: 'Poland', alpha3: 'POL', numeric: '616', useThe: false)]
    case PL = 'PL';
    #[CountryInfo(country_name: 'Portugal', alpha3: 'PRT', numeric: '620', useThe: false)]
    case PT = 'PT';
    #[CountryInfo(country_name: 'Puerto Rico', alpha3: 'PRI', numeric: '630', useThe: false)]
    case PR = 'PR';
    #[CountryInfo(country_name: 'Qatar', alpha3: 'QAT', numeric: '634', useThe: false)]
    case QA = 'QA';
    #[CountryInfo(country_name: 'Réunion', alpha3: 'REU', numeric: '638', useThe: false)]
    case RE = 'RE';
    #[CountryInfo(country_name: 'Romania', alpha3: 'ROU', numeric: '642', useThe: false)]
    case RO = 'RO';
    #[CountryInfo(country_name: 'Russia', alpha3: 'RUS', numeric: '643', useThe: false)]
    case RU = 'RU';
    #[CountryInfo(country_name: 'Rwanda', alpha3: 'RWA', numeric: '646', useThe: false)]
    case RW = 'RW';
    #[CountryInfo(country_name: 'Saint Barthélemy', alpha3: 'BLM', numeric: '652', useThe: false)]
    case BL = 'BL';
    #[CountryInfo(country_name: 'Saint Helena, Ascension and Tristan da Cunha', alpha3: 'SHN', numeric: '654', useThe: false)]
    case SH = 'SH';
    #[CountryInfo(country_name: 'Saint Kitts and Nevis', alpha3: 'KNA', numeric: '659', useThe: false)]
    case KN = 'KN';
    #[CountryInfo(country_name: 'Saint Lucia', alpha3: 'LCA', numeric: '662', useThe: false)]
    case LC = 'LC';
    #[CountryInfo(country_name: 'Saint Martin', alpha3: 'MAF', numeric: '663', useThe: false)]
    case MF = 'MF';
    #[CountryInfo(country_name: 'Saint Pierre and Miquelon', alpha3: 'SPM', numeric: '666', useThe: false)]
    case PM = 'PM';
    #[CountryInfo(country_name: 'Saint Vincent and the Grenadines', alpha3: 'VCT', numeric: '670', useThe: false)]
    case VC = 'VC';
    #[CountryInfo(country_name: 'Samoa', alpha3: 'WSM', numeric: '882', useThe: false)]
    case WS = 'WS';
    #[CountryInfo(country_name: 'San Marino', alpha3: 'SMR', numeric: '674', useThe: false)]
    case SM = 'SM';
    #[CountryInfo(country_name: 'Sao Tome and Principe', alpha3: 'STP', numeric: '678', useThe: false)]
    case ST = 'ST';
    #[CountryInfo(country_name: 'Saudi Arabia', alpha3: 'SAU', numeric: '682', useThe: false)]
    case SA = 'SA';
    #[CountryInfo(country_name: 'Senegal', alpha3: 'SEN', numeric: '686', useThe: false)]
    case SN = 'SN';
    #[CountryInfo(country_name: 'Serbia', alpha3: 'SRB', numeric: '688', useThe: false)]
    case RS = 'RS';
    #[CountryInfo(country_name: 'Seychelles', alpha3: 'SYC', numeric: '690', useThe: true)]
    case SC = 'SC';
    #[CountryInfo(country_name: 'Sierra Leone', alpha3: 'SLE', numeric: '694', useThe: false)]
    case SL = 'SL';
    #[CountryInfo(country_name: 'Singapore', alpha3: 'SGP', numeric: '702', useThe: false)]
    case SG = 'SG';
    #[CountryInfo(country_name: 'Sint Maarten', alpha3: 'SXM', numeric: '534', useThe: false)]
    case SX = 'SX';
    #[CountryInfo(country_name: 'Slovakia', alpha3: 'SVK', numeric: '703', useThe: false)]
    case SK = 'SK';
    #[CountryInfo(country_name: 'Slovenia', alpha3: 'SVN', numeric: '705', useThe: false)]
    case SI = 'SI';
    #[CountryInfo(country_name: 'Solomon Islands', alpha3: 'SLB', numeric: '090', useThe: true)]
    case SB = 'SB';
    #[CountryInfo(country_name: 'Somalia', alpha3: 'SOM', numeric: '706', useThe: false)]
    case SO = 'SO';
    #[CountryInfo(country_name: 'South Africa', alpha3: 'ZAF', numeric: '710', useThe: false)]
    case ZA = 'ZA';
    #[CountryInfo(country_name: 'South Georgia and the South Sandwich Islands', alpha3: 'SGS', numeric: '239', useThe: false)]
    case GS = 'GS';
    #[CountryInfo(country_name: 'South Korea', alpha3: 'KOR', numeric: '410', useThe: false)]
    case KR = 'KR';
    #[CountryInfo(country_name: 'South Sudan', alpha3: 'SSD', numeric: '728', useThe: false)]
    case SS = 'SS';
    #[CountryInfo(country_name: 'Spain', alpha3: 'ESP', numeric: '724', useThe: false)]
    case ES = 'ES';
    #[CountryInfo(country_name: 'Sri Lanka', alpha3: 'LKA', numeric: '144', useThe: false)]
    case LK = 'LK';
    #[CountryInfo(country_name: 'Sudan', alpha3: 'SDN', numeric: '729', useThe: false)]
    case SD = 'SD';
    #[CountryInfo(country_name: 'Suriname', alpha3: 'SUR', numeric: '740', useThe: false)]
    case SR = 'SR';
    #[CountryInfo(country_name: 'Svalbard and Jan Mayen', alpha3: 'SJM', numeric: '744', useThe: false)]
    case SJ = 'SJ';
    #[CountryInfo(country_name: 'Sweden', alpha3: 'SWE', numeric: '752', useThe: false)]
    case SE = 'SE';
    #[CountryInfo(country_name: 'Switzerland', alpha3: 'CHE', numeric: '756', useThe: false)]
    case CH = 'CH';
    #[CountryInfo(country_name: 'Syria', alpha3: 'SYR', numeric: '760', useThe: false)]
    case SY = 'SY';
    #[CountryInfo(country_name: 'Taiwan', alpha3: 'TWN', numeric: '158', useThe: false)]
    case TW = 'TW';
    #[CountryInfo(country_name: 'Tajikistan', alpha3: 'TJK', numeric: '762', useThe: false)]
    case TJ = 'TJ';
    #[CountryInfo(country_name: 'Tanzania', alpha3: 'TZA', numeric: '834', useThe: false)]
    case TZ = 'TZ';
    #[CountryInfo(country_name: 'Thailand', alpha3: 'THA', numeric: '764', useThe: false)]
    case TH = 'TH';
    #[CountryInfo(country_name: 'Timor-Leste', alpha3: 'TLS', numeric: '626', useThe: false)]
    case TL = 'TL';
    #[CountryInfo(country_name: 'Togo', alpha3: 'TGO', numeric: '768', useThe: false)]
    case TG = 'TG';
    #[CountryInfo(country_name: 'Tokelau', alpha3: 'TKL', numeric: '772', useThe: false)]
    case TK = 'TK';
    #[CountryInfo(country_name: 'Tonga', alpha3: 'TON', numeric: '776', useThe: false)]
    case TO = 'TO';
    #[CountryInfo(country_name: 'Trinidad and Tobago', alpha3: 'TTO', numeric: '780', useThe: false)]
    case TT = 'TT';
    #[CountryInfo(country_name: 'Tunisia', alpha3: 'TUN', numeric: '788', useThe: false)]
    case TN = 'TN';
    #[CountryInfo(country_name: 'Turkey', alpha3: 'TUR', numeric: '792', useThe: false)]
    case TR = 'TR';
    #[CountryInfo(country_name: 'Turkmenistan', alpha3: 'TKM', numeric: '795', useThe: false)]
    case TM = 'TM';
    #[CountryInfo(country_name: 'Turks and Caicos Islands', alpha3: 'TCA', numeric: '796', useThe: true)]
    case TC = 'TC';
    #[CountryInfo(country_name: 'Tuvalu', alpha3: 'TUV', numeric: '798', useThe: false)]
    case TV = 'TV';
    #[CountryInfo(country_name: 'Uganda', alpha3: 'UGA', numeric: '800', useThe: false)]
    case UG = 'UG';
    #[CountryInfo(country_name: 'Ukraine', alpha3: 'UKR', numeric: '804', useThe: false)]
    case UA = 'UA';
    #[CountryInfo(country_name: 'United Arab Emirates', alpha3: 'ARE', numeric: '784', useThe: true)]
    case AE = 'AE';
    #[CountryInfo(country_name: 'United Kingdom', alpha3: 'GBR', numeric: '826', useThe: true)]
    case GB = 'GB';
    #[CountryInfo(country_name: 'United States Minor Outlying Islands', alpha3: 'UMI', numeric: '581', useThe: true)]
    case UM = 'UM';
    #[CountryInfo(country_name: 'Uruguay', alpha3: 'URY', numeric: '858', useThe: false)]
    case UY = 'UY';
    #[CountryInfo(country_name: 'Uzbekistan', alpha3: 'UZB', numeric: '860', useThe: false)]
    case UZ = 'UZ';
    #[CountryInfo(country_name: 'Vanuatu', alpha3: 'VUT', numeric: '548', useThe: false)]
    case VU = 'VU';
    #[CountryInfo(country_name: 'Vatican City', alpha3: 'VAT', numeric: '336', useThe: false)]
    case VA = 'VA';
    #[CountryInfo(country_name: 'Venezuela', alpha3: 'VEN', numeric: '862', useThe: false)]
    case VE = 'VE';
    #[CountryInfo(country_name: 'Vietnam', alpha3: 'VNM', numeric: '704', useThe: false)]
    case VN = 'VN';
    #[CountryInfo(country_name: 'Virgin Islands (British)', alpha3: 'VGB', numeric: '092', useThe: true)]
    case VG = 'VG';
    #[CountryInfo(country_name: 'Virgin Islands (U.S.)', alpha3: 'VIR', numeric: '850', useThe: true)]
    case VI = 'VI';
    #[CountryInfo(country_name: 'Wallis and Futuna', alpha3: 'WLF', numeric: '876', useThe: false)]
    case WF = 'WF';
    #[CountryInfo(country_name: 'Western Sahara', alpha3: 'ESH', numeric: '732', useThe: false)]
    case EH = 'EH';
    #[CountryInfo(country_name: 'Yemen', alpha3: 'YEM', numeric: '887', useThe: false)]
    case YE = 'YE';
    #[CountryInfo(country_name: 'Zambia', alpha3: 'ZMB', numeric: '894', useThe: false)]
    case ZM = 'ZM';
    #[CountryInfo(country_name: 'Zimbabwe', alpha3: 'ZWE', numeric: '716', useThe: false)]
    case ZW = 'ZW';

    /**
     * Lazily initializes and returns the country data map.
     *
     * @return array<string, array{country_name: string, alpha3: string, numeric: string, useThe: bool}>
     */
    private static function countryData(): array
    {
        // ---
        // Define arrow function to extract country data from CountryInfo
        // attributes into an associative array
        // ---
        $dataExtractor = fn ($info, $caseValue) => [
            'country_name' => $info->country_name,
            'alpha3' => $info->alpha3,
            'numeric' => $info->numeric,
            'useThe' => $info->useThe,
        ];

        // ---
        // Build and cache the lazy data map using reflection to extract
        // CountryInfo attributes from all enum cases
        // ---
        $result = EnumDataUtils::buildLazyDataMap(
            self::class,
            CountryInfo::class,
            $dataExtractor
        );

        // Clean up variables to free up memory
        unset(
            $dataExtractor
        );

        // Return the cached country data map
        return $result;
    }

    /**
     * Get the full name of the country.
     *
     * @return string The full name
     */
    public function country_name(): string
    {
        // Retrieve the cached country data map
        $data = self::countryData();

        // Extract and return the country_name field value for this country
        return EnumDataUtils::getFieldValue(
            $data,
            $this->value,
            'country_name',
            'country'
        );
    }

    /**
     * Get the ISO 3166-1 alpha-2 code of the country.
     *
     * @return string The alpha-2 code
     */
    public function alpha2(): string
    {
        // Return the enum case value, which is the ISO 3166-1 alpha-2 code
        return $this->value;
    }

    /**
     * Get the ISO 3166-1 alpha-3 code of the country.
     *
     * @return string The alpha-3 code
     */
    public function alpha3(): string
    {
        // Retrieve the cached country data map
        $data = self::countryData();

        // Extract and return the alpha3 field value for this country
        return EnumDataUtils::getFieldValue(
            $data,
            $this->value,
            'alpha3',
            'country'
        );
    }

    /**
     * Get the ISO 3166-1 numeric code of the country.
     *
     * @return string The numeric code
     */
    public function numeric(): string
    {
        // Retrieve the cached country data map
        $data = self::countryData();

        // Extract and return the numeric field value for this country
        return EnumDataUtils::getFieldValue(
            $data,
            $this->value,
            'numeric',
            'country'
        );
    }

    /**
     * Returns true if the country name should be prefixed with 'the' in a sentence.
     */
    public function requiresThe(): bool
    {
        // Retrieve the cached country data map
        $data = self::countryData();

        // Extract and return the useThe boolean field value for this country
        return EnumDataUtils::getBooleanFieldValue(
            $data,
            $this->value,
            'useThe',
            'country',
            false
        );
    }

    /**
     * Returns the country name, prefixed with 'the' if appropriate for use in a
     * sentence.
     */
    public function sentenceName(): string
    {
        // Retrieve the country name
        $name = $this->country_name();

        // ---
        // Return the name with 'the' prefix if required, otherwise return
        // the name as-is
        // ---
        return $this->requiresThe() ? "the $name" : $name;
    }
}
