<?php

declare(strict_types=1);

namespace App\Support;

use UAMSWeb\Aether\Enums\ApStyleGregorianMonth;
use UAMSWeb\Aether\Enums\ApStyleMonthInfo;
use UAMSWeb\Aether\Enums\Attributes\CredentialRegistryTypeInfo;
use UAMSWeb\Aether\Enums\Attributes\IscedInfo;
use UAMSWeb\Aether\Enums\Attributes\Isco08Info;
use UAMSWeb\Aether\Enums\Attributes\SchemaDataTypeInfo;
use UAMSWeb\Aether\Enums\Attributes\SchemaEnumerationMemberInfo;
use UAMSWeb\Aether\Enums\Attributes\SchemaPropertyInfo;
use UAMSWeb\Aether\Enums\Attributes\SchemaTypeInfo;
use UAMSWeb\Aether\Enums\CanadianProvince;
use UAMSWeb\Aether\Enums\CanadianProvinceInfo;
use UAMSWeb\Aether\Enums\Console\GenerateCountyEnum;
use UAMSWeb\Aether\Enums\Console\GenerateCredentialRegistryEnum;
use UAMSWeb\Aether\Enums\Console\GenerateInternationalCodeDesignatorEnum;
use UAMSWeb\Aether\Enums\Console\GenerateIscedEnums;
use UAMSWeb\Aether\Enums\Console\GenerateIsco08Enum;
use UAMSWeb\Aether\Enums\Console\GenerateMimeTypeEnum;
use UAMSWeb\Aether\Enums\Console\GenerateSchemaTypeEnum;
use UAMSWeb\Aether\Enums\Console\GenerateStateEnum;
use UAMSWeb\Aether\Enums\Console\GenerateZipCodeEnum;
use UAMSWeb\Aether\Enums\Country;
use UAMSWeb\Aether\Enums\CountryInfo;
use UAMSWeb\Aether\Enums\County;
use UAMSWeb\Aether\Enums\CountyInfo;
use UAMSWeb\Aether\Enums\CredentialRegistry;
use UAMSWeb\Aether\Enums\Currency;
use UAMSWeb\Aether\Enums\CurrencyInfo;
use UAMSWeb\Aether\Enums\Generation\County\CountyEnumGenerator;
use UAMSWeb\Aether\Enums\Generation\CredentialRegistry\CredentialRegistryEnumGenerator;
use UAMSWeb\Aether\Enums\Generation\EnumDataUtils;
use UAMSWeb\Aether\Enums\Generation\GeneratorUtils;
use UAMSWeb\Aether\Enums\Generation\InternationalCodeDesignator\IcdXmlParser;
use UAMSWeb\Aether\Enums\Generation\Isced\IscedA2011EnumGenerator;
use UAMSWeb\Aether\Enums\Generation\Isced\IscedAnnexLoader;
use UAMSWeb\Aether\Enums\Generation\Isced\IscedEnumConfig;
use UAMSWeb\Aether\Enums\Generation\Isced\IscedEnumsGenerator;
use UAMSWeb\Aether\Enums\Generation\Isced\IscedEnumWriter;
use UAMSWeb\Aether\Enums\Generation\Isced\IscedF2013EnumGenerator;
use UAMSWeb\Aether\Enums\Generation\Isced\IscedFieldsLoader;
use UAMSWeb\Aether\Enums\Generation\Isced\IscedHierarchy;
use UAMSWeb\Aether\Enums\Generation\Isced\IscedP2011EnumGenerator;
use UAMSWeb\Aether\Enums\Generation\Isco08\Isco08EnumGenerator;
use UAMSWeb\Aether\Enums\Generation\MimeType\MimeTypeEnumGenerator;
use UAMSWeb\Aether\Enums\Generation\Schema\SchemaJsonLdStructureAnalyzer;
use UAMSWeb\Aether\Enums\Generation\Schema\SchemaTypeEnumGenerator;
use UAMSWeb\Aether\Enums\Generation\State\StateEnumGenerator;
use UAMSWeb\Aether\Enums\Generation\TigerWebUtils;
use UAMSWeb\Aether\Enums\Generation\ZipCode\ZipCodeEnumGenerator;
use UAMSWeb\Aether\Enums\IcdInfo;
use UAMSWeb\Aether\Enums\Imports\SpreadsheetImport;
use UAMSWeb\Aether\Enums\InternationalCodeDesignator;
use UAMSWeb\Aether\Enums\IscedA2011;
use UAMSWeb\Aether\Enums\IscedF2013;
use UAMSWeb\Aether\Enums\IscedP2011;
use UAMSWeb\Aether\Enums\Isco08;
use UAMSWeb\Aether\Enums\Language;
use UAMSWeb\Aether\Enums\LanguageBCP47;
use UAMSWeb\Aether\Enums\LanguageBCP47Info;
use UAMSWeb\Aether\Enums\LanguageInfo;
use UAMSWeb\Aether\Enums\MimeType;
use UAMSWeb\Aether\Enums\MimeTypeInfo;
use UAMSWeb\Aether\Enums\SchemaDataType;
use UAMSWeb\Aether\Enums\SchemaEnumerationMember;
use UAMSWeb\Aether\Enums\SchemaProperty;
use UAMSWeb\Aether\Enums\SchemaType;
use UAMSWeb\Aether\Enums\State;
use UAMSWeb\Aether\Enums\StateInfo;
use UAMSWeb\Aether\Enums\ZipCode;
use UAMSWeb\Aether\Enums\ZipCodeInfo;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Address;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Assets;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Audio;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Checkboxes;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Consent;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\CssSelector;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Date;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\DateFieldSelect;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Email;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Entries;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Floatval;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Gps;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Hidden;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Html;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Identifier;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Integer;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Lists;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\MaskField;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Month;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Name;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Option;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\PartialDate;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Password;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Phone;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Product;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Radio;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Replicator;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Section;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Select;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Signature;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Subsection;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Terms;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\TermsSearchIndex;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Text;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Time;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Total;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Trigger;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\TrueFalse;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Url;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Username;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Video;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Week;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Xpath;
use UAMSWeb\Aether\Fieldtypes\Listeners\EnforceChoiceSubmissionLimits;
use UAMSWeb\Aether\Fieldtypes\Rules\AssetMinimumDimensions;
use UAMSWeb\Aether\Fieldtypes\Rules\DateBoundary;
use UAMSWeb\Aether\Fieldtypes\Rules\FieldRequiredHelper;
use UAMSWeb\Aether\Fieldtypes\Rules\IdentifierRegex;
use UAMSWeb\Aether\Fieldtypes\Rules\InternationalPhone;
use UAMSWeb\Aether\Fieldtypes\Rules\Latitude;
use UAMSWeb\Aether\Fieldtypes\Rules\Longitude;
use UAMSWeb\Aether\Fieldtypes\Rules\NationalPhone;
use UAMSWeb\Aether\Fieldtypes\Rules\ReplicatorMinSets;
use UAMSWeb\Aether\Fieldtypes\Rules\ReplicatorRequireAtLeastOneEnabledSet;
use UAMSWeb\Aether\Fieldtypes\Rules\SmsShortCode;
use UAMSWeb\Aether\Fieldtypes\Scopes\AssetsByFileSize;
use UAMSWeb\Aether\Fieldtypes\Services\PhoneUtil;
use UAMSWeb\Aether\Fieldtypes\Support\DateBoundaryEngine;
use UAMSWeb\Aether\Fieldtypes\Support\Forms\ChoiceSubmissionLimits;
use UAMSWeb\Aether\Fieldtypes\Traits\BuildsPreloadConfig;
use UAMSWeb\Aether\Fieldtypes\Traits\ConfigCache;
use UAMSWeb\Aether\Fieldtypes\Traits\ConfigSectionWrapper;
use UAMSWeb\Aether\Fieldtypes\Traits\DefinesInputConfigFields;
use UAMSWeb\Aether\Fieldtypes\Traits\DynamicDefaultValue;
use UAMSWeb\Aether\Fieldtypes\Traits\FieldtypeValidationHelpers;
use UAMSWeb\Aether\Fieldtypes\Traits\FieldtypeValidationRules;
use UAMSWeb\Aether\Fieldtypes\Traits\HasChoiceSubmissionLimits;
use UAMSWeb\Aether\Fieldtypes\Traits\HasSubfields;
use UAMSWeb\Aether\Fieldtypes\Traits\Input;
use UAMSWeb\Aether\Fieldtypes\Traits\InputMaskValueStorage;
use UAMSWeb\Aether\Fieldtypes\Traits\OtherOption;
use UAMSWeb\Aether\Fieldtypes\Traits\PresetOptions;
use UAMSWeb\Aether\Fieldtypes\Traits\ProcessesInputValue;
use UAMSWeb\Aether\Fieldtypes\Traits\ProvidesAutocompleteOptions;
use UAMSWeb\Aether\Fieldtypes\Traits\RandomizesOptions;
use UAMSWeb\Aether\Fieldtypes\Traits\SometimesValidationRules;
use UAMSWeb\Aether\People\Fieldtypes\Isco_08;
use UAMSWeb\Aether\People\Fieldtypes\RoleTitle;
use UAMSWeb\Aether\People\Fieldtypes\TextPhoneticPair;
use UAMSWeb\Aether\People\Rules\Isco08FieldtypeStoredRule;
use UAMSWeb\Aether\People\Rules\OnetSocOccupationCode;
use UAMSWeb\Aether\People\Rules\RoleTitlePerConfigurationOptionRule;
use UAMSWeb\Aether\People\Rules\RoleTitleScopeJoinerRule;
use UAMSWeb\Aether\People\Rules\RoleTitleScopeUnitRule;
use UAMSWeb\Aether\People\Support\Isco08FieldtypeStorage;
use UAMSWeb\Aether\People\Support\ReplicatorSetMeta;
use UAMSWeb\Aether\People\Support\ReplicatorVueSet;
use UAMSWeb\Aether\People\Support\RoleTitleDefinition;
use UAMSWeb\Aether\People\Support\RoleTitleSchemaOutputComputer;
use UAMSWeb\Aether\People\Support\RoleTitleScopeUnit;
use UAMSWeb\Aether\People\Support\RoleTitleUnstructuredCpBridge;
use UAMSWeb\Aether\People\Support\RoleTitleUnstructuredReplicatorSupport;
use UAMSWeb\Aether\People\Support\RoleTitleValue;

/**
 * Keeps the class names that moved into `uams-web/aether-enums`,
 * `uams-web/aether-fieldtypes` and `uams-web/aether-people` resolving under their old
 * `App\` names.
 *
 * The enums, their generators, the shared fieldtype traits and the `Subsection`
 * fieldtype left the site in UAMS-Web/uams-statamic#995, the `TextPhoneticPair` fieldtype in
 * UAMS-Web/uams-statamic#1000, and the `RoleTitle` and `Isco_08` fieldtypes with their rules
 * and support classes in UAMS-Web/uams-statamic#1143, and the four fieldtype families with
 * their rules, scopes, services and support classes in UAMS-Web/uams-statamic#996,
 * #997, #998 and #999. A template, a content file
 * or a configured string that still names an old class keeps working: the first time
 * a process asks the autoloader for an old name, {@see self::load()} aliases it to the
 * add-on's class with `class_alias()`, so both names are the same class and enum cases
 * are identical under either. Nothing is loaded for a name until it is used.
 *
 * Only an autoloading lookup reaches the shims. `instanceof` and a parameter or return
 * type never autoload, so an old name there fails until something else has aliased it.
 * A reflection `getAttributes()` call with a name filter matches the name the attribute
 * is declared under, so an old name matches nothing even once aliased. Site code
 * therefore names the add-on classes directly; the shims are for names held as strings.
 *
 * `bootstrap/moved-class-aliases.php`, loaded through `autoload.files` in
 * `composer.json`, registers an autoloader that calls {@see self::load()}. It runs
 * before the application boots, so the shims also hold for unit tests that never boot
 * Laravel. It names this class only inside the closure, so the class itself loads on
 * the first old name a process asks for, not at startup.
 *
 * The shims are for the transition decided in UAMS-Web/uams-statamic#767. A
 * family slice that moves a caller onto the new name leaves its entry here until
 * no old name remains.
 */
final class MovedClassAliases
{
    /**
     * Each moved class, keyed by its old name, mapped to the class that replaced it.
     *
     * A method rather than a constant: a constant holds no executable line, so coverage
     * would report every entry as untested and mutation testing could not reach them.
     *
     * @return array<string, class-string>
     */
    public static function map(): array
    {
        return [
            'App\\Console\\Commands\\GenerateCountyEnum' => GenerateCountyEnum::class,
            'App\\Console\\Commands\\GenerateCredentialRegistryEnum' => GenerateCredentialRegistryEnum::class,
            'App\\Console\\Commands\\GenerateInternationalCodeDesignatorEnum' => GenerateInternationalCodeDesignatorEnum::class,
            'App\\Console\\Commands\\GenerateIscedEnums' => GenerateIscedEnums::class,
            'App\\Console\\Commands\\GenerateIsco08Enum' => GenerateIsco08Enum::class,
            'App\\Console\\Commands\\GenerateMimeTypeEnum' => GenerateMimeTypeEnum::class,
            'App\\Console\\Commands\\GenerateSchemaTypeEnum' => GenerateSchemaTypeEnum::class,
            'App\\Console\\Commands\\GenerateStateEnum' => GenerateStateEnum::class,
            'App\\Console\\Commands\\GenerateZipCodeEnum' => GenerateZipCodeEnum::class,
            'App\\Enums\\ApStyleGregorianMonth' => ApStyleGregorianMonth::class,
            'App\\Enums\\ApStyleMonthInfo' => ApStyleMonthInfo::class,
            'App\\Enums\\Attributes\\CredentialRegistryTypeInfo' => CredentialRegistryTypeInfo::class,
            'App\\Enums\\Attributes\\IscedInfo' => IscedInfo::class,
            'App\\Enums\\Attributes\\Isco08Info' => Isco08Info::class,
            'App\\Enums\\Attributes\\SchemaDataTypeInfo' => SchemaDataTypeInfo::class,
            'App\\Enums\\Attributes\\SchemaEnumerationMemberInfo' => SchemaEnumerationMemberInfo::class,
            'App\\Enums\\Attributes\\SchemaPropertyInfo' => SchemaPropertyInfo::class,
            'App\\Enums\\Attributes\\SchemaTypeInfo' => SchemaTypeInfo::class,
            'App\\Enums\\CanadianProvince' => CanadianProvince::class,
            'App\\Enums\\CanadianProvinceInfo' => CanadianProvinceInfo::class,
            'App\\Enums\\Country' => Country::class,
            'App\\Enums\\CountryInfo' => CountryInfo::class,
            'App\\Enums\\County' => County::class,
            'App\\Enums\\CountyInfo' => CountyInfo::class,
            'App\\Enums\\CredentialRegistry' => CredentialRegistry::class,
            'App\\Enums\\Currency' => Currency::class,
            'App\\Enums\\CurrencyInfo' => CurrencyInfo::class,
            'App\\Enums\\IcdInfo' => IcdInfo::class,
            'App\\Enums\\InternationalCodeDesignator' => InternationalCodeDesignator::class,
            'App\\Enums\\IscedA2011' => IscedA2011::class,
            'App\\Enums\\IscedF2013' => IscedF2013::class,
            'App\\Enums\\IscedP2011' => IscedP2011::class,
            'App\\Enums\\Isco08' => Isco08::class,
            'App\\Enums\\Language' => Language::class,
            'App\\Enums\\LanguageBCP47' => LanguageBCP47::class,
            'App\\Enums\\LanguageBCP47Info' => LanguageBCP47Info::class,
            'App\\Enums\\LanguageInfo' => LanguageInfo::class,
            'App\\Enums\\MimeType' => MimeType::class,
            'App\\Enums\\MimeTypeInfo' => MimeTypeInfo::class,
            'App\\Enums\\SchemaDataType' => SchemaDataType::class,
            'App\\Enums\\SchemaEnumerationMember' => SchemaEnumerationMember::class,
            'App\\Enums\\SchemaProperty' => SchemaProperty::class,
            'App\\Enums\\SchemaType' => SchemaType::class,
            'App\\Enums\\State' => State::class,
            'App\\Enums\\StateInfo' => StateInfo::class,
            'App\\Enums\\ZipCode' => ZipCode::class,
            'App\\Enums\\ZipCodeInfo' => ZipCodeInfo::class,
            'App\\Fieldtypes\\Address' => Address::class,
            'App\\Fieldtypes\\Assets' => Assets::class,
            'App\\Fieldtypes\\Audio' => Audio::class,
            'App\\Fieldtypes\\Checkboxes' => Checkboxes::class,
            'App\\Fieldtypes\\Consent' => Consent::class,
            'App\\Fieldtypes\\CssSelector' => CssSelector::class,
            'App\\Fieldtypes\\Currency' => \UAMSWeb\Aether\Fieldtypes\Fieldtypes\Currency::class,
            'App\\Fieldtypes\\Date' => Date::class,
            'App\\Fieldtypes\\DateFieldSelect' => DateFieldSelect::class,
            'App\\Fieldtypes\\Email' => Email::class,
            'App\\Fieldtypes\\Entries' => Entries::class,
            'App\\Fieldtypes\\Floatval' => Floatval::class,
            'App\\Fieldtypes\\Gps' => Gps::class,
            'App\\Fieldtypes\\Hidden' => Hidden::class,
            'App\\Fieldtypes\\Html' => Html::class,
            'App\\Fieldtypes\\Identifier' => Identifier::class,
            'App\\Fieldtypes\\Integer' => Integer::class,
            'App\\Fieldtypes\\Isco_08' => Isco_08::class,
            'App\\Fieldtypes\\Lists' => Lists::class,
            'App\\Fieldtypes\\MaskField' => MaskField::class,
            'App\\Fieldtypes\\Month' => Month::class,
            'App\\Fieldtypes\\Name' => Name::class,
            'App\\Fieldtypes\\Option' => Option::class,
            'App\\Fieldtypes\\PartialDate' => PartialDate::class,
            'App\\Fieldtypes\\Password' => Password::class,
            'App\\Fieldtypes\\Phone' => Phone::class,
            'App\\Fieldtypes\\Product' => Product::class,
            'App\\Fieldtypes\\Radio' => Radio::class,
            'App\\Fieldtypes\\Replicator' => Replicator::class,
            'App\\Fieldtypes\\RoleTitle' => RoleTitle::class,
            'App\\Fieldtypes\\Section' => Section::class,
            'App\\Fieldtypes\\Select' => Select::class,
            'App\\Fieldtypes\\Signature' => Signature::class,
            'App\\Fieldtypes\\Subsection' => Subsection::class,
            'App\\Fieldtypes\\Terms' => Terms::class,
            'App\\Fieldtypes\\TermsSearchIndex' => TermsSearchIndex::class,
            'App\\Fieldtypes\\Text' => Text::class,
            'App\\Fieldtypes\\TextPhoneticPair' => TextPhoneticPair::class,
            'App\\Fieldtypes\\Time' => Time::class,
            'App\\Fieldtypes\\Total' => Total::class,
            'App\\Fieldtypes\\Traits\\BuildsPreloadConfig' => BuildsPreloadConfig::class,
            'App\\Fieldtypes\\Traits\\ConfigCache' => ConfigCache::class,
            'App\\Fieldtypes\\Traits\\ConfigSectionWrapper' => ConfigSectionWrapper::class,
            'App\\Fieldtypes\\Traits\\DefinesInputConfigFields' => DefinesInputConfigFields::class,
            'App\\Fieldtypes\\Traits\\DynamicDefaultValue' => DynamicDefaultValue::class,
            'App\\Fieldtypes\\Traits\\FieldtypeValidationHelpers' => FieldtypeValidationHelpers::class,
            'App\\Fieldtypes\\Traits\\FieldtypeValidationRules' => FieldtypeValidationRules::class,
            'App\\Fieldtypes\\Traits\\HasChoiceSubmissionLimits' => HasChoiceSubmissionLimits::class,
            'App\\Fieldtypes\\Traits\\HasSubfields' => HasSubfields::class,
            'App\\Fieldtypes\\Traits\\Input' => Input::class,
            'App\\Fieldtypes\\Traits\\InputMaskValueStorage' => InputMaskValueStorage::class,
            'App\\Fieldtypes\\Traits\\OtherOption' => OtherOption::class,
            'App\\Fieldtypes\\Traits\\PresetOptions' => PresetOptions::class,
            'App\\Fieldtypes\\Traits\\ProcessesInputValue' => ProcessesInputValue::class,
            'App\\Fieldtypes\\Traits\\ProvidesAutocompleteOptions' => ProvidesAutocompleteOptions::class,
            'App\\Fieldtypes\\Traits\\RandomizesOptions' => RandomizesOptions::class,
            'App\\Fieldtypes\\Traits\\SometimesValidationRules' => SometimesValidationRules::class,
            'App\\Fieldtypes\\Trigger' => Trigger::class,
            'App\\Fieldtypes\\TrueFalse' => TrueFalse::class,
            'App\\Fieldtypes\\Url' => Url::class,
            'App\\Fieldtypes\\Username' => Username::class,
            'App\\Fieldtypes\\Video' => Video::class,
            'App\\Fieldtypes\\Week' => Week::class,
            'App\\Fieldtypes\\Xpath' => Xpath::class,
            'App\\Fieldtypes\\ZipCode' => \UAMSWeb\Aether\Fieldtypes\Fieldtypes\ZipCode::class,
            'App\\Imports\\SpreadsheetImport' => SpreadsheetImport::class,
            'App\\Listeners\\EnforceChoiceSubmissionLimits' => EnforceChoiceSubmissionLimits::class,
            'App\\Rules\\AssetMinimumDimensions' => AssetMinimumDimensions::class,
            'App\\Rules\\DateBoundary' => DateBoundary::class,
            'App\\Rules\\FieldRequiredHelper' => FieldRequiredHelper::class,
            'App\\Rules\\IdentifierRegex' => IdentifierRegex::class,
            'App\\Rules\\InternationalPhone' => InternationalPhone::class,
            'App\\Rules\\Isco08FieldtypeStoredRule' => Isco08FieldtypeStoredRule::class,
            'App\\Rules\\Latitude' => Latitude::class,
            'App\\Rules\\Longitude' => Longitude::class,
            'App\\Rules\\NationalPhone' => NationalPhone::class,
            'App\\Rules\\OnetSocOccupationCode' => OnetSocOccupationCode::class,
            'App\\Rules\\ReplicatorMinSets' => ReplicatorMinSets::class,
            'App\\Rules\\ReplicatorRequireAtLeastOneEnabledSet' => ReplicatorRequireAtLeastOneEnabledSet::class,
            'App\\Rules\\RoleTitlePerConfigurationOptionRule' => RoleTitlePerConfigurationOptionRule::class,
            'App\\Rules\\RoleTitleScopeJoinerRule' => RoleTitleScopeJoinerRule::class,
            'App\\Rules\\RoleTitleScopeUnitRule' => RoleTitleScopeUnitRule::class,
            'App\\Rules\\SmsShortCode' => SmsShortCode::class,
            'App\\Rules\\ZipCode' => \UAMSWeb\Aether\Fieldtypes\Rules\ZipCode::class,
            'App\\Scopes\\AssetsByFileSize' => AssetsByFileSize::class,
            'App\\Services\\EnumGeneration\\County\\CountyEnumGenerator' => CountyEnumGenerator::class,
            'App\\Services\\EnumGeneration\\CredentialRegistry\\CredentialRegistryEnumGenerator' => CredentialRegistryEnumGenerator::class,
            'App\\Services\\EnumGeneration\\EnumDataUtils' => EnumDataUtils::class,
            'App\\Services\\EnumGeneration\\GeneratorUtils' => GeneratorUtils::class,
            'App\\Services\\EnumGeneration\\InternationalCodeDesignator\\IcdXmlParser' => IcdXmlParser::class,
            'App\\Services\\EnumGeneration\\Isced\\IscedA2011EnumGenerator' => IscedA2011EnumGenerator::class,
            'App\\Services\\EnumGeneration\\Isced\\IscedAnnexLoader' => IscedAnnexLoader::class,
            'App\\Services\\EnumGeneration\\Isced\\IscedEnumConfig' => IscedEnumConfig::class,
            'App\\Services\\EnumGeneration\\Isced\\IscedEnumWriter' => IscedEnumWriter::class,
            'App\\Services\\EnumGeneration\\Isced\\IscedEnumsGenerator' => IscedEnumsGenerator::class,
            'App\\Services\\EnumGeneration\\Isced\\IscedF2013EnumGenerator' => IscedF2013EnumGenerator::class,
            'App\\Services\\EnumGeneration\\Isced\\IscedFieldsLoader' => IscedFieldsLoader::class,
            'App\\Services\\EnumGeneration\\Isced\\IscedHierarchy' => IscedHierarchy::class,
            'App\\Services\\EnumGeneration\\Isced\\IscedP2011EnumGenerator' => IscedP2011EnumGenerator::class,
            'App\\Services\\EnumGeneration\\Isco08\\Isco08EnumGenerator' => Isco08EnumGenerator::class,
            'App\\Services\\EnumGeneration\\MimeType\\MimeTypeEnumGenerator' => MimeTypeEnumGenerator::class,
            'App\\Services\\EnumGeneration\\Schema\\SchemaJsonLdStructureAnalyzer' => SchemaJsonLdStructureAnalyzer::class,
            'App\\Services\\EnumGeneration\\Schema\\SchemaTypeEnumGenerator' => SchemaTypeEnumGenerator::class,
            'App\\Services\\EnumGeneration\\State\\StateEnumGenerator' => StateEnumGenerator::class,
            'App\\Services\\EnumGeneration\\TigerWebUtils' => TigerWebUtils::class,
            'App\\Services\\EnumGeneration\\ZipCode\\ZipCodeEnumGenerator' => ZipCodeEnumGenerator::class,
            'App\\Services\\PhoneUtil' => PhoneUtil::class,
            'App\\Support\\DateBoundaryEngine' => DateBoundaryEngine::class,
            'App\\Support\\Forms\\ChoiceSubmissionLimits' => ChoiceSubmissionLimits::class,
            'App\\Support\\Isco08FieldtypeStorage' => Isco08FieldtypeStorage::class,
            'App\\Support\\ReplicatorSetMeta' => ReplicatorSetMeta::class,
            'App\\Support\\ReplicatorVueSet' => ReplicatorVueSet::class,
            'App\\Support\\RoleTitleDefinition' => RoleTitleDefinition::class,
            'App\\Support\\RoleTitleSchemaOutputComputer' => RoleTitleSchemaOutputComputer::class,
            'App\\Support\\RoleTitleScopeUnit' => RoleTitleScopeUnit::class,
            'App\\Support\\RoleTitleUnstructuredCpBridge' => RoleTitleUnstructuredCpBridge::class,
            'App\\Support\\RoleTitleUnstructuredReplicatorSupport' => RoleTitleUnstructuredReplicatorSupport::class,
            'App\\Support\\RoleTitleValue' => RoleTitleValue::class,
        ];
    }

    /**
     * Alias one old class name to its replacement, if it is one of the moved names.
     *
     * Returns whether the name was aliased, so a caller can tell a moved name from any
     * other class the autoloader chain is asked for.
     */
    public static function load(string $class): bool
    {
        $target = self::map()[$class] ?? null;

        if ($target === null) {
            return false;
        }

        return class_alias($target, $class);
    }
}
