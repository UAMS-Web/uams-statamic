<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\EnumGeneration\EnumDataUtils;
use Attribute;

/**
 * Attribute for storing currency information on enum cases.
 *
 * @param  string  $name  The English name of the currency
 * @param  string  $numeric  The ISO 4217 numeric code
 * @param  string  $symbol  The currency's symbol
 * @param  string  $symbol_placement  The placement of the currency's symbol relative to the numeric amounts
 * @param  bool  $symbol_separation  Whether the currency's symbol is separated from the numeric amounts
 * @param  int  $minor_unit  The number of minor units in the currency
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class CurrencyInfo
{
    /**
     * Initializes a CurrencyInfo attribute instance with currency ISO 4217
     * codes and formatting information.
     *
     * @param  string  $name  The English name of the currency
     * @param  string  $numeric  The ISO 4217 numeric code
     * @param  string  $symbol  The currency's symbol
     * @param  string  $symbol_placement  The placement of the currency's symbol relative to the numeric amounts
     * @param  bool  $symbol_separation  Whether the currency's symbol is separated from the numeric amounts
     * @param  int  $minor_unit  The number of minor units in the currency
     */
    public function __construct(
        public string $name,
        public string $numeric,
        public string $symbol,
        public string $symbol_placement,
        public bool $symbol_separation,
        public int $minor_unit
    ) {}
}

/**
 * Currency enum with ISO 4217 codes and formatting information.
 *
 * This enum is intended for use wherever a standardized list of currencies is
 * required, such as form dropdowns, validation, or formatting for display.
 *
 * This enum provides two ways to access attribute data:
 *
 *   1. **Getter Methods** (Recommended for common use):
 *      - `name()`, `numeric()`, `symbol()`, `symbol_placement()`,
 *        `symbol_separation()`, `minor_unit()`, `symbol_with_separator()`
 *      - These methods use cached data for optimal performance
 *      - Only include the most commonly used attributes
 *      - Add new getter methods whenever an attribute becomes frequently used
 *
 *   2. **Direct Reflection** (For advanced/rare use cases):
 *      - Access any attribute directly via PHP's reflection API
 *      - Example:
 *        `$reflection = new \ReflectionEnumUnitCase(Currency::class, 'USD');`
 *      - Then:
 *        `$info = $reflection->getAttributes(CurrencyInfo::class)[0]->newInstance();`
 *      - Provides access to ALL attributes: name, numeric, symbol,
 *        symbol_placement, symbol_separation, minor_unit
 *
 * **Adding New Getter Methods:**
 *
 * When an attribute becomes commonly used, add a getter method following this
 * pattern:
 *   ```php
 *   public function attributeName(): string
 *   {
 *       $data = self::currencyData();
 *       return EnumDataUtils::getFieldValue(
 *           $data,
 *           $this->value,
 *           'attributeName',
 *           'currency'
 *       );
 *   }
 *   ```
 *
 * Also update the `currencyData()` method to include the new attribute in the
 * returned array.
 *
 * Each case's string value represents the ISO 4217 code (e.g., 'USD' for U.S.
 * Dollar). The case name is a human-readable identifier for the currency.
 *
 * Each case includes:
 *   - name // The English name of the currency
 *   - numeric // The ISO 4217 numeric code
 *   - symbol // The currency's symbol
 *   - symbol_placement // The placement of the currency's symbol relative to the numeric amounts
 *   - symbol_separation // Whether the currency's symbol is separated from the numeric amounts
 *   - minor_unit // The number of minor units in the currency
 */
enum Currency: string
{
    #[CurrencyInfo(name: 'U.S. Dollar', numeric: '840', symbol: '$', symbol_placement: 'before', symbol_separation: false, minor_unit: 2)]
    case USD = 'USD';

    #[CurrencyInfo(name: 'Afghani', numeric: '971', symbol: '؋', symbol_placement: 'after', symbol_separation: false, minor_unit: 2)]
    case AFN = 'AFN';
    #[CurrencyInfo(name: 'Algerian Dinar', numeric: '012', symbol: '.د.ج', symbol_placement: 'after', symbol_separation: false, minor_unit: 2)]
    case DZD = 'DZD';
    #[CurrencyInfo(name: 'Argentine Peso', numeric: '032', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case ARS = 'ARS';
    #[CurrencyInfo(name: 'Armenian Dram', numeric: '051', symbol: '֏', symbol_placement: 'after', symbol_separation: false, minor_unit: 2)]
    case AMD = 'AMD';
    #[CurrencyInfo(name: 'Aruban Florin', numeric: '533', symbol: 'ƒ', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case AWG = 'AWG';
    #[CurrencyInfo(name: 'Australian Dollar', numeric: '036', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case AUD = 'AUD';
    #[CurrencyInfo(name: 'Azerbaijan Manat', numeric: '944', symbol: '₼', symbol_placement: 'after', symbol_separation: false, minor_unit: 2)]
    case AZN = 'AZN';
    #[CurrencyInfo(name: 'Bahamian Dollar', numeric: '044', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case BSD = 'BSD';
    #[CurrencyInfo(name: 'Bahraini Dinar', numeric: '048', symbol: '.د.ب', symbol_placement: 'after', symbol_separation: false, minor_unit: 3)]
    case BHD = 'BHD';
    #[CurrencyInfo(name: 'Baht', numeric: '764', symbol: '฿', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case THB = 'THB';
    #[CurrencyInfo(name: 'Balboa', numeric: '590', symbol: 'B/.', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case PAB = 'PAB';
    #[CurrencyInfo(name: 'Barbados Dollar', numeric: '052', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case BBD = 'BBD';
    #[CurrencyInfo(name: 'Belarusian Ruble', numeric: '933', symbol: 'Br', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case BYN = 'BYN';
    #[CurrencyInfo(name: 'Belize Dollar', numeric: '084', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case BZD = 'BZD';
    #[CurrencyInfo(name: 'Bermudian Dollar', numeric: '060', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case BMD = 'BMD';
    #[CurrencyInfo(name: 'Bolivar Soberano', numeric: '928', symbol: 'Bs', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case VES = 'VES';
    #[CurrencyInfo(name: 'Boliviano', numeric: '068', symbol: 'Bs', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case BOB = 'BOB';
    #[CurrencyInfo(name: 'Brazilian Real', numeric: '986', symbol: 'R$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case BRL = 'BRL';
    #[CurrencyInfo(name: 'Brunei Dollar', numeric: '096', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case BND = 'BND';
    #[CurrencyInfo(name: 'Bulgarian Lev', numeric: '975', symbol: 'лев', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case BGN = 'BGN';
    #[CurrencyInfo(name: 'Burundi Franc', numeric: '108', symbol: 'FBu', symbol_placement: 'after', symbol_separation: true, minor_unit: 0)]
    case BIF = 'BIF';
    #[CurrencyInfo(name: 'CFP franc', numeric: '953', symbol: 'F', symbol_placement: 'after', symbol_separation: true, minor_unit: 0)]
    case XPF = 'XPF';
    #[CurrencyInfo(name: 'Cabo Verde Escudo', numeric: '132', symbol: 'Esc', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case CVE = 'CVE';
    #[CurrencyInfo(name: 'Canadian Dollar', numeric: '124', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case CAD = 'CAD';
    #[CurrencyInfo(name: 'Caribbean Guilder', numeric: '532', symbol: 'ƒ', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case XCG = 'XCG';
    #[CurrencyInfo(name: 'Cayman Islands Dollar', numeric: '136', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case KYD = 'KYD';
    #[CurrencyInfo(name: 'Central African CFA franc', numeric: '950', symbol: 'Fr', symbol_placement: 'after', symbol_separation: true, minor_unit: 0)]
    case XAF = 'XAF';
    #[CurrencyInfo(name: 'Chilean Peso', numeric: '152', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 0)]
    case CLP = 'CLP';
    #[CurrencyInfo(name: 'Colombian Peso', numeric: '170', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case COP = 'COP';
    #[CurrencyInfo(name: 'Comorian Franc', numeric: '174', symbol: 'Fr', symbol_placement: 'after', symbol_separation: true, minor_unit: 0)]
    case KMF = 'KMF';
    #[CurrencyInfo(name: 'Congolese Franc', numeric: '976', symbol: 'FC', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case CDF = 'CDF';
    #[CurrencyInfo(name: 'Convertible Mark', numeric: '977', symbol: 'KM', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case BAM = 'BAM';
    #[CurrencyInfo(name: 'Cordoba Oro', numeric: '558', symbol: 'C$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case NIO = 'NIO';
    #[CurrencyInfo(name: 'Costa Rican Colon', numeric: '188', symbol: '₡', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case CRC = 'CRC';
    #[CurrencyInfo(name: 'Cuban Peso', numeric: '192', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case CUP = 'CUP';
    #[CurrencyInfo(name: 'Czech Koruna', numeric: '203', symbol: 'Kč', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case CZK = 'CZK';
    #[CurrencyInfo(name: 'Dalasi', numeric: '270', symbol: 'D', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case GMD = 'GMD';
    #[CurrencyInfo(name: 'Danish Krone', numeric: '208', symbol: 'kr', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case DKK = 'DKK';
    #[CurrencyInfo(name: 'Denar', numeric: '807', symbol: 'ден', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case MKD = 'MKD';
    #[CurrencyInfo(name: 'Djibouti Franc', numeric: '262', symbol: 'Fdj', symbol_placement: 'after', symbol_separation: true, minor_unit: 0)]
    case DJF = 'DJF';
    #[CurrencyInfo(name: 'Dobra', numeric: '930', symbol: 'Db', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case STN = 'STN';
    #[CurrencyInfo(name: 'Dominican Peso', numeric: '214', symbol: 'RD$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case DOP = 'DOP';
    #[CurrencyInfo(name: 'Dong', numeric: '704', symbol: '₫', symbol_placement: 'after', symbol_separation: false, minor_unit: 0)]
    case VND = 'VND';
    #[CurrencyInfo(name: 'East Caribbean Dollar', numeric: '951', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case XCD = 'XCD';
    #[CurrencyInfo(name: 'Egyptian Pound', numeric: '818', symbol: '£', symbol_placement: 'after', symbol_separation: false, minor_unit: 2)]
    case EGP = 'EGP';
    #[CurrencyInfo(name: 'El Salvador Colon', numeric: '222', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case SVC = 'SVC';
    #[CurrencyInfo(name: 'Ethiopian Birr', numeric: '230', symbol: 'Br', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case ETB = 'ETB';
    #[CurrencyInfo(name: 'Euro', numeric: '978', symbol: '€', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case EUR = 'EUR';
    #[CurrencyInfo(name: 'Falkland Islands Pound', numeric: '238', symbol: '£', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case FKP = 'FKP';
    #[CurrencyInfo(name: 'Fiji Dollar', numeric: '242', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case FJD = 'FJD';
    #[CurrencyInfo(name: 'Forint', numeric: '348', symbol: 'Ft', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case HUF = 'HUF';
    #[CurrencyInfo(name: 'Ghana Cedi', numeric: '936', symbol: '₵', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case GHS = 'GHS';
    #[CurrencyInfo(name: 'Gibraltar Pound', numeric: '292', symbol: '£', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case GIP = 'GIP';
    #[CurrencyInfo(name: 'Gourde', numeric: '332', symbol: 'G', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case HTG = 'HTG';
    #[CurrencyInfo(name: 'Guarani', numeric: '600', symbol: '₲', symbol_placement: 'before', symbol_separation: true, minor_unit: 0)]
    case PYG = 'PYG';
    #[CurrencyInfo(name: 'Guinean Franc', numeric: '324', symbol: 'Fr', symbol_placement: 'after', symbol_separation: true, minor_unit: 0)]
    case GNF = 'GNF';
    #[CurrencyInfo(name: 'Guyana Dollar', numeric: '328', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case GYD = 'GYD';
    #[CurrencyInfo(name: 'Hong Kong Dollar', numeric: '344', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case HKD = 'HKD';
    #[CurrencyInfo(name: 'Hryvnia', numeric: '980', symbol: '₴', symbol_placement: 'after', symbol_separation: false, minor_unit: 2)]
    case UAH = 'UAH';
    #[CurrencyInfo(name: 'Iceland Krona', numeric: '352', symbol: 'kr', symbol_placement: 'after', symbol_separation: true, minor_unit: 0)]
    case ISK = 'ISK';
    #[CurrencyInfo(name: 'Indian Rupee', numeric: '356', symbol: '₹', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case INR = 'INR';
    #[CurrencyInfo(name: 'Iranian Rial', numeric: '364', symbol: '﷼', symbol_placement: 'after', symbol_separation: false, minor_unit: 2)]
    case IRR = 'IRR';
    #[CurrencyInfo(name: 'Iraqi Dinar', numeric: '368', symbol: '.د.ع', symbol_placement: 'after', symbol_separation: false, minor_unit: 3)]
    case IQD = 'IQD';
    #[CurrencyInfo(name: 'Jamaican Dollar', numeric: '388', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case JMD = 'JMD';
    #[CurrencyInfo(name: 'Jordanian Dinar', numeric: '400', symbol: '.د.أ', symbol_placement: 'after', symbol_separation: false, minor_unit: 3)]
    case JOD = 'JOD';
    #[CurrencyInfo(name: 'Kenyan Shilling', numeric: '404', symbol: 'KSh', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case KES = 'KES';
    #[CurrencyInfo(name: 'Kina', numeric: '598', symbol: 'K', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case PGK = 'PGK';
    #[CurrencyInfo(name: 'Kuwaiti Dinar', numeric: '414', symbol: '.د.ك', symbol_placement: 'after', symbol_separation: false, minor_unit: 3)]
    case KWD = 'KWD';
    #[CurrencyInfo(name: 'Kwanza', numeric: '973', symbol: 'Kz', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case AOA = 'AOA';
    #[CurrencyInfo(name: 'Kyat', numeric: '104', symbol: 'K', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case MMK = 'MMK';
    #[CurrencyInfo(name: 'Lao Kip', numeric: '418', symbol: '₭', symbol_placement: 'after', symbol_separation: false, minor_unit: 2)]
    case LAK = 'LAK';
    #[CurrencyInfo(name: 'Lari', numeric: '981', symbol: '₾', symbol_placement: 'after', symbol_separation: false, minor_unit: 2)]
    case GEL = 'GEL';
    #[CurrencyInfo(name: 'Lebanese Pound', numeric: '422', symbol: '£', symbol_placement: 'after', symbol_separation: false, minor_unit: 2)]
    case LBP = 'LBP';
    #[CurrencyInfo(name: 'Lek', numeric: '008', symbol: 'Lek', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case ALL = 'ALL';
    #[CurrencyInfo(name: 'Lempira', numeric: '340', symbol: 'L', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case HNL = 'HNL';
    #[CurrencyInfo(name: 'Leone', numeric: '925', symbol: 'Le', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case SLE = 'SLE';
    #[CurrencyInfo(name: 'Liberian Dollar', numeric: '430', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case LRD = 'LRD';
    #[CurrencyInfo(name: 'Libyan Dinar', numeric: '434', symbol: '.د.ل', symbol_placement: 'after', symbol_separation: false, minor_unit: 3)]
    case LYD = 'LYD';
    #[CurrencyInfo(name: 'Lilangeni', numeric: '748', symbol: 'L', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case SZL = 'SZL';
    #[CurrencyInfo(name: 'Loti', numeric: '426', symbol: 'L', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case LSL = 'LSL';
    #[CurrencyInfo(name: 'Malagasy Ariary', numeric: '969', symbol: 'Ar', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case MGA = 'MGA';
    #[CurrencyInfo(name: 'Malawi Kwacha', numeric: '454', symbol: 'K', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case MWK = 'MWK';
    #[CurrencyInfo(name: 'Malaysian Ringgit', numeric: '458', symbol: 'RM', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case MYR = 'MYR';
    #[CurrencyInfo(name: 'Mauritius Rupee', numeric: '480', symbol: '₨', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case MUR = 'MUR';
    #[CurrencyInfo(name: 'Mexican Peso', numeric: '484', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case MXN = 'MXN';
    #[CurrencyInfo(name: 'Moldovan Leu', numeric: '498', symbol: 'L', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case MDL = 'MDL';
    #[CurrencyInfo(name: 'Moroccan Dirham', numeric: '504', symbol: '.د.م', symbol_placement: 'after', symbol_separation: false, minor_unit: 2)]
    case MAD = 'MAD';
    #[CurrencyInfo(name: 'Mozambique Metical', numeric: '943', symbol: 'Mt', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case MZN = 'MZN';
    #[CurrencyInfo(name: 'Naira', numeric: '566', symbol: '₦', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case NGN = 'NGN';
    #[CurrencyInfo(name: 'Nakfa', numeric: '232', symbol: 'Nfk', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case ERN = 'ERN';
    #[CurrencyInfo(name: 'Namibia Dollar', numeric: '516', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case NAD = 'NAD';
    #[CurrencyInfo(name: 'Nepalese Rupee', numeric: '524', symbol: '₨', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case NPR = 'NPR';
    #[CurrencyInfo(name: 'New Israeli Sheqel', numeric: '376', symbol: '₪', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case ILS = 'ILS';
    #[CurrencyInfo(name: 'New Taiwan Dollar', numeric: '901', symbol: '元', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case TWD = 'TWD';
    #[CurrencyInfo(name: 'New Zealand Dollar', numeric: '554', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case NZD = 'NZD';
    #[CurrencyInfo(name: 'Ngultrum', numeric: '064', symbol: 'Nu', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case BTN = 'BTN';
    #[CurrencyInfo(name: 'Norwegian Krone', numeric: '578', symbol: 'kr', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case NOK = 'NOK';
    #[CurrencyInfo(name: 'Ouguiya', numeric: '929', symbol: 'UM', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case MRU = 'MRU';
    #[CurrencyInfo(name: 'Pa\'anga', numeric: '776', symbol: 'T$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case TOP = 'TOP';
    #[CurrencyInfo(name: 'Pakistan Rupee', numeric: '586', symbol: '₨', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case PKR = 'PKR';
    #[CurrencyInfo(name: 'Pataca', numeric: '446', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case MOP = 'MOP';
    #[CurrencyInfo(name: 'Peso Uruguayo', numeric: '858', symbol: '$U', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case UYU = 'UYU';
    #[CurrencyInfo(name: 'Philippine Peso', numeric: '608', symbol: '₱', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case PHP = 'PHP';
    #[CurrencyInfo(name: 'Pound Sterling', numeric: '826', symbol: '£', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case GBP = 'GBP';
    #[CurrencyInfo(name: 'Pula', numeric: '072', symbol: 'P', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case BWP = 'BWP';
    #[CurrencyInfo(name: 'Qatari Rial', numeric: '634', symbol: '﷼', symbol_placement: 'after', symbol_separation: false, minor_unit: 2)]
    case QAR = 'QAR';
    #[CurrencyInfo(name: 'Quetzal', numeric: '320', symbol: 'Q', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case GTQ = 'GTQ';
    #[CurrencyInfo(name: 'Rand', numeric: '710', symbol: 'R', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case ZAR = 'ZAR';
    #[CurrencyInfo(name: 'Rial Omani', numeric: '512', symbol: '.ر.ع', symbol_placement: 'after', symbol_separation: false, minor_unit: 3)]
    case OMR = 'OMR';
    #[CurrencyInfo(name: 'Riel', numeric: '116', symbol: '៛', symbol_placement: 'after', symbol_separation: false, minor_unit: 2)]
    case KHR = 'KHR';
    #[CurrencyInfo(name: 'Romanian Leu', numeric: '946', symbol: 'leu', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case RON = 'RON';
    #[CurrencyInfo(name: 'Rufiyaa', numeric: '462', symbol: 'Rf', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case MVR = 'MVR';
    #[CurrencyInfo(name: 'Rupiah', numeric: '360', symbol: 'Rp', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case IDR = 'IDR';
    #[CurrencyInfo(name: 'Russian Ruble', numeric: '643', symbol: '₽', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case RUB = 'RUB';
    #[CurrencyInfo(name: 'Rwanda Franc', numeric: '646', symbol: 'Fr', symbol_placement: 'after', symbol_separation: true, minor_unit: 0)]
    case RWF = 'RWF';
    #[CurrencyInfo(name: 'Saint Helena Pound', numeric: '654', symbol: '£', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case SHP = 'SHP';
    #[CurrencyInfo(name: 'Saudi Riyal', numeric: '682', symbol: '﷼', symbol_placement: 'after', symbol_separation: false, minor_unit: 2)]
    case SAR = 'SAR';
    #[CurrencyInfo(name: 'Serbian Dinar', numeric: '941', symbol: 'дин', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case RSD = 'RSD';
    #[CurrencyInfo(name: 'Seychelles Rupee', numeric: '690', symbol: '₨', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case SCR = 'SCR';
    #[CurrencyInfo(name: 'Singapore Dollar', numeric: '702', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case SGD = 'SGD';
    #[CurrencyInfo(name: 'Sol', numeric: '604', symbol: 'S/.', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case PEN = 'PEN';
    #[CurrencyInfo(name: 'Solomon Islands Dollar', numeric: '090', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case SBD = 'SBD';
    #[CurrencyInfo(name: 'Som', numeric: '417', symbol: 'с', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case KGS = 'KGS';
    #[CurrencyInfo(name: 'Somali Shilling', numeric: '706', symbol: 'Sh', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case SOS = 'SOS';
    #[CurrencyInfo(name: 'Somoni', numeric: '972', symbol: 'с.', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case TJS = 'TJS';
    #[CurrencyInfo(name: 'South Sudanese Pound', numeric: '728', symbol: '£', symbol_placement: 'after', symbol_separation: false, minor_unit: 2)]
    case SSP = 'SSP';
    #[CurrencyInfo(name: 'Sri Lanka Rupee', numeric: '144', symbol: '₨', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case LKR = 'LKR';
    #[CurrencyInfo(name: 'Sudanese Pound', numeric: '938', symbol: '£', symbol_placement: 'after', symbol_separation: false, minor_unit: 2)]
    case SDG = 'SDG';
    #[CurrencyInfo(name: 'Surinam Dollar', numeric: '968', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case SRD = 'SRD';
    #[CurrencyInfo(name: 'Swedish Krona', numeric: '752', symbol: 'kr', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case SEK = 'SEK';
    #[CurrencyInfo(name: 'Swiss Franc', numeric: '756', symbol: 'Fr.', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case CHF = 'CHF';
    #[CurrencyInfo(name: 'Syrian Pound', numeric: '760', symbol: '£', symbol_placement: 'after', symbol_separation: false, minor_unit: 2)]
    case SYP = 'SYP';
    #[CurrencyInfo(name: 'Taka', numeric: '050', symbol: '৳', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case BDT = 'BDT';
    #[CurrencyInfo(name: 'Tala', numeric: '882', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case WST = 'WST';
    #[CurrencyInfo(name: 'Tanzanian Shilling', numeric: '834', symbol: 'Sh', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case TZS = 'TZS';
    #[CurrencyInfo(name: 'Tenge', numeric: '398', symbol: '₸', symbol_placement: 'after', symbol_separation: false, minor_unit: 2)]
    case KZT = 'KZT';
    #[CurrencyInfo(name: 'Trinidad and Tobago Dollar', numeric: '780', symbol: '$', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case TTD = 'TTD';
    #[CurrencyInfo(name: 'Tugrik', numeric: '496', symbol: '₮', symbol_placement: 'after', symbol_separation: false, minor_unit: 2)]
    case MNT = 'MNT';
    #[CurrencyInfo(name: 'Tunisian Dinar', numeric: '788', symbol: '.د.ت', symbol_placement: 'after', symbol_separation: false, minor_unit: 3)]
    case TND = 'TND';
    #[CurrencyInfo(name: 'Turkish Lira', numeric: '949', symbol: '₺', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case TRY = 'TRY';
    #[CurrencyInfo(name: 'Turkmenistan New Manat', numeric: '934', symbol: 'm', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case TMT = 'TMT';
    #[CurrencyInfo(name: 'UAE Dirham', numeric: '784', symbol: 'د.إ', symbol_placement: 'after', symbol_separation: false, minor_unit: 2)]
    case AED = 'AED';
    #[CurrencyInfo(name: 'Uganda Shilling', numeric: '800', symbol: 'Sh', symbol_placement: 'before', symbol_separation: true, minor_unit: 0)]
    case UGX = 'UGX';
    #[CurrencyInfo(name: 'Uzbekistan Sum', numeric: '860', symbol: 'сўм', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case UZS = 'UZS';
    #[CurrencyInfo(name: 'Vatu', numeric: '548', symbol: 'Vt', symbol_placement: 'after', symbol_separation: true, minor_unit: 0)]
    case VUV = 'VUV';
    #[CurrencyInfo(name: 'West African CFA franc', numeric: '952', symbol: 'Fr', symbol_placement: 'after', symbol_separation: true, minor_unit: 0)]
    case XOF = 'XOF';
    #[CurrencyInfo(name: 'Won', numeric: '410', symbol: '₩', symbol_placement: 'before', symbol_separation: true, minor_unit: 0)]
    case KRW = 'KRW';
    #[CurrencyInfo(name: 'Yemeni Rial', numeric: '886', symbol: '﷼', symbol_placement: 'after', symbol_separation: false, minor_unit: 2)]
    case YER = 'YER';
    #[CurrencyInfo(name: 'Yen', numeric: '392', symbol: '¥', symbol_placement: 'before', symbol_separation: true, minor_unit: 0)]
    case JPY = 'JPY';
    #[CurrencyInfo(name: 'Yuan Renminbi', numeric: '156', symbol: '¥', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case CNY = 'CNY';
    #[CurrencyInfo(name: 'Zambian Kwacha', numeric: '967', symbol: 'K', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case ZMW = 'ZMW';
    #[CurrencyInfo(name: 'Zimbabwe Gold', numeric: '924', symbol: 'ZiG', symbol_placement: 'before', symbol_separation: true, minor_unit: 2)]
    case ZWG = 'ZWG';
    #[CurrencyInfo(name: 'Zloty', numeric: '985', symbol: 'zł', symbol_placement: 'after', symbol_separation: true, minor_unit: 2)]
    case PLN = 'PLN';

    /**
     * Lazily initializes and returns the currency data map.
     *
     * @return array<string, array{name: string, numeric: string, symbol: string, symbol_placement: string, symbol_separation: bool, minor_unit: int}>
     */
    private static function currencyData(): array
    {
        // ---
        // Define arrow function to extract currency data from CurrencyInfo
        // attributes into an associative array
        // ---
        $dataExtractor = fn ($info, $caseValue) => [
            'name' => $info->name,
            'numeric' => $info->numeric,
            'symbol' => $info->symbol,
            'symbol_placement' => $info->symbol_placement,
            'symbol_separation' => $info->symbol_separation,
            'minor_unit' => $info->minor_unit,
        ];

        // ---
        // Build and cache the lazy data map using reflection to extract
        // CurrencyInfo attributes from all enum cases
        // ---
        $result = EnumDataUtils::buildLazyDataMap(
            self::class,
            CurrencyInfo::class,
            $dataExtractor
        );

        // Clean up variables to free up memory
        unset(
            $dataExtractor
        );

        // Return the cached currency data map
        return $result;
    }

    /**
     * Get the English name of the currency.
     *
     * @return string The English name of the currency
     */
    public function name(): string
    {
        // Retrieve the cached currency data map
        $data = self::currencyData();

        // Extract and return the name field value for this currency
        return EnumDataUtils::getFieldValue(
            $data,
            $this->value,
            'name',
            'currency'
        );
    }

    /**
     * Get the ISO 4217 numeric code of the currency.
     *
     * @return string The ISO 4217 numeric code
     */
    public function numeric(): string
    {
        // Retrieve the cached currency data map
        $data = self::currencyData();

        // Extract and return the numeric field value for this currency
        return EnumDataUtils::getFieldValue(
            $data,
            $this->value,
            'numeric',
            'currency'
        );
    }

    /**
     * Get the currency's symbol.
     *
     * @return string The currency's symbol
     */
    public function symbol(): string
    {
        // Retrieve the cached currency data map
        $data = self::currencyData();

        // Extract and return the symbol field value for this currency
        return EnumDataUtils::getFieldValue(
            $data,
            $this->value,
            'symbol',
            'currency'
        );
    }

    /**
     * Get the placement of the currency's symbol relative to the numeric amounts.
     *   - before: The symbol is placed before the numeric amounts.
     *   - after: The symbol is placed after the numeric amounts.
     *   - between: The symbol is placed between the numeric amounts.
     *
     * @return string The placement of the currency's symbol relative to the numeric amounts.
     */
    public function symbol_placement(): string
    {
        // Retrieve the cached currency data map
        $data = self::currencyData();

        // ---
        // Extract and return the symbol_placement field value for this
        // currency
        // ---
        return EnumDataUtils::getFieldValue(
            $data,
            $this->value,
            'symbol_placement',
            'currency'
        );
    }

    /**
     * Get whether the currency's symbol is separated from the numeric amounts.
     *
     * @return bool Whether the currency's symbol is separated from the numeric amounts.
     */
    public function symbol_separation(): bool
    {
        // Retrieve the cached currency data map
        $data = self::currencyData();

        // ---
        // Extract and return the symbol_separation boolean field value for
        // this currency
        // ---
        return EnumDataUtils::getBooleanFieldValue(
            $data,
            $this->value,
            'symbol_separation',
            'currency',
            false
        );
    }

    /**
     * Get the number of minor units in the currency.
     *
     * @return int The number of minor units in the currency
     */
    public function minor_unit(): int
    {
        // Retrieve the cached currency data map
        $data = self::currencyData();

        // ---
        // Extract and return the minor_unit integer field value for this
        // currency
        // ---
        return EnumDataUtils::getIntegerFieldValue(
            $data,
            $this->value,
            'minor_unit',
            'currency'
        );
    }

    /**
     * Construct the currency's symbol with the space separator, if relevant.
     */
    public function symbol_with_separator(): string
    {
        // Get the currency's symbol
        $symbol = $this->symbol();

        // Get the currency's symbol placement
        $symbol_placement = $this->symbol_placement();

        // Get whether the currency's symbol is separated from the numeric amounts
        $symbol_separation = $this->symbol_separation();

        // ---
        // If the currency's symbol is separated from the numeric amounts, add a space
        // separator
        // ---
        if ($symbol_separation) {
            // Check if the symbol placement is 'before'
            if ($symbol_placement === 'before') {
                // ---
                // If the currency symbol is placed before the numeric amounts, add a space
                // separator before the symbol
                // ---
                return " $symbol";
            } elseif ($symbol_placement === 'after') { // Check if the symbol placement is 'after'
                // ---
                // If the currency symbol is placed after the numeric amounts, add a space
                // separator after the symbol
                // ---
                return "$symbol ";
            } else { // Otherwise
                // ---
                // If the currency's symbol is placed between the numeric numeric amounts, return
                // the unmodified symbol
                // ---
                return $symbol;
            }
        } else { // Otherwise
            // ---
            // If the currency's symbol is not separated from the numeric amounts, return the
            // unmodified symbol
            // ---
            return $symbol;
        }
    }
}
