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
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Subsection;
use UAMSWeb\Aether\Fieldtypes\Traits\BuildsPreloadConfig;
use UAMSWeb\Aether\Fieldtypes\Traits\ConfigCache;
use UAMSWeb\Aether\Fieldtypes\Traits\ConfigSectionWrapper;
use UAMSWeb\Aether\Fieldtypes\Traits\DefinesInputConfigFields;
use UAMSWeb\Aether\Fieldtypes\Traits\DynamicDefaultValue;
use UAMSWeb\Aether\Fieldtypes\Traits\FieldtypeValidationHelpers;
use UAMSWeb\Aether\Fieldtypes\Traits\FieldtypeValidationRules;
use UAMSWeb\Aether\Fieldtypes\Traits\HasSubfields;
use UAMSWeb\Aether\Fieldtypes\Traits\Input;
use UAMSWeb\Aether\Fieldtypes\Traits\InputMaskValueStorage;
use UAMSWeb\Aether\Fieldtypes\Traits\OtherOption;
use UAMSWeb\Aether\Fieldtypes\Traits\ProcessesInputValue;
use UAMSWeb\Aether\Fieldtypes\Traits\ProvidesAutocompleteOptions;
use UAMSWeb\Aether\Fieldtypes\Traits\RandomizesOptions;
use UAMSWeb\Aether\Fieldtypes\Traits\SometimesValidationRules;

/**
 * Keeps the class names that moved into `uams-web/aether-enums` and
 * `uams-web/aether-fieldtypes` resolving under their old `App\` names.
 *
 * The enums, their generators, the shared fieldtype traits and the `Subsection`
 * fieldtype left the site in UAMS-Web/uams-statamic#995. A template, a content file
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
            'App\\Fieldtypes\\Subsection' => Subsection::class,
            'App\\Fieldtypes\\Traits\\BuildsPreloadConfig' => BuildsPreloadConfig::class,
            'App\\Fieldtypes\\Traits\\ConfigCache' => ConfigCache::class,
            'App\\Fieldtypes\\Traits\\ConfigSectionWrapper' => ConfigSectionWrapper::class,
            'App\\Fieldtypes\\Traits\\DefinesInputConfigFields' => DefinesInputConfigFields::class,
            'App\\Fieldtypes\\Traits\\DynamicDefaultValue' => DynamicDefaultValue::class,
            'App\\Fieldtypes\\Traits\\FieldtypeValidationHelpers' => FieldtypeValidationHelpers::class,
            'App\\Fieldtypes\\Traits\\FieldtypeValidationRules' => FieldtypeValidationRules::class,
            'App\\Fieldtypes\\Traits\\HasSubfields' => HasSubfields::class,
            'App\\Fieldtypes\\Traits\\Input' => Input::class,
            'App\\Fieldtypes\\Traits\\InputMaskValueStorage' => InputMaskValueStorage::class,
            'App\\Fieldtypes\\Traits\\OtherOption' => OtherOption::class,
            'App\\Fieldtypes\\Traits\\ProcessesInputValue' => ProcessesInputValue::class,
            'App\\Fieldtypes\\Traits\\ProvidesAutocompleteOptions' => ProvidesAutocompleteOptions::class,
            'App\\Fieldtypes\\Traits\\RandomizesOptions' => RandomizesOptions::class,
            'App\\Fieldtypes\\Traits\\SometimesValidationRules' => SometimesValidationRules::class,
            'App\\Imports\\SpreadsheetImport' => SpreadsheetImport::class,
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
