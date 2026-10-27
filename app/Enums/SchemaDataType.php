<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Attributes\SchemaDataTypeInfo;

/**
 * Schema.org DataType values from schema.org JSON-LD vocabulary.
 *
 * Each case carries optional metadata via the SchemaDataTypeInfo attribute
 * (rdfs_comment). Cases are a fixed list per schema.org.
 *
 * Generated from schema.org on 2026-05-20 using
 * app/Services/EnumGeneration/Schema/SchemaTypeEnumGenerator.php.
 *
 * @see https://schema.org/docs/developers.html
 */
enum SchemaDataType: string
{
    #[SchemaDataTypeInfo(rdfs_comment: 'Boolean: True or False.')]
    case Boolean = 'Boolean';
    #[SchemaDataTypeInfo(rdfs_comment: 'Text representing a CSS selector.')]
    case CssSelectorType = 'CssSelectorType';
    #[SchemaDataTypeInfo(rdfs_comment: 'The basic data types such as Integers, Strings, etc.')]
    case DataType = 'DataType';
    #[SchemaDataTypeInfo(rdfs_comment: 'A date value in <a href="http://en.wikipedia.org/wiki/ISO_8601" target="_blank" rel="noopener noreferrer">ISO 8601 date format</a>.')]
    case Date = 'Date';
    #[SchemaDataTypeInfo(rdfs_comment: 'A combination of date and time of day in the form [-]CCYY-MM-DDThh:mm:ss[Z|(+|-)hh:mm] (see Chapter 5.4 of ISO 8601).')]
    case DateTime = 'DateTime';
    #[SchemaDataTypeInfo(rdfs_comment: 'Properties that take Distances as values are of the form \'<Number> <Length unit of measure>\'. E.g., \'7 ft\'.')]
    case Distance = 'Distance';
    #[SchemaDataTypeInfo(rdfs_comment: 'Quantity: Duration (use <a href="http://en.wikipedia.org/wiki/ISO_8601" target="_blank" rel="noopener noreferrer">ISO 8601 duration format</a>).')]
    case Duration = 'Duration';
    #[SchemaDataTypeInfo(rdfs_comment: 'Properties that take Energy as values are of the form \'<Number> <Energy unit of measure>\'.')]
    case Energy = 'Energy';
    #[SchemaDataTypeInfo(rdfs_comment: 'The boolean value false.')]
    case DataTypeFalse = 'False';
    #[SchemaDataTypeInfo(rdfs_comment: 'Data type: Floating number.')]
    case Float = 'Float';
    #[SchemaDataTypeInfo(rdfs_comment: 'Data type: Integer.')]
    case Integer = 'Integer';
    #[SchemaDataTypeInfo(rdfs_comment: 'Properties that take Mass as values are of the form \'<Number> <Mass unit of measure>\'. E.g., \'7 kg\'.')]
    case Mass = 'Mass';
    #[SchemaDataTypeInfo(rdfs_comment: 'Data type: Number.\\n\\nUsage guidelines:\\n\\n* Use values from 0123456789 (Unicode \'DIGIT ZERO\' (U+0030) to \'DIGIT NINE\' (U+0039)) rather than superficially similar Unicode symbols.\\n* Use \'.\' (Unicode \'FULL STOP\' (U+002E)) rather than \',\' to indicate a decimal point. Avoid using these symbols as a readability separator.')]
    case Number = 'Number';
    #[SchemaDataTypeInfo(rdfs_comment: 'Data type: PronounceableText.')]
    case PronounceableText = 'PronounceableText';
    #[SchemaDataTypeInfo(rdfs_comment: 'Quantities such as distance, time, mass, weight, etc. Particular instances of say Mass are strings like \'3 kg\' or \'4 milligrams\'.')]
    case Quantity = 'Quantity';
    #[SchemaDataTypeInfo(rdfs_comment: 'Data type: Text.')]
    case Text = 'Text';
    #[SchemaDataTypeInfo(rdfs_comment: 'A point in time recurring on multiple days in the form hh:mm:ss[Z|(+|-)hh:mm] (see <a href="http://www.w3.org/TR/xmlschema-2/#time" target="_blank" rel="noopener noreferrer">XML schema for details</a>).')]
    case Time = 'Time';
    #[SchemaDataTypeInfo(rdfs_comment: 'The boolean value true.')]
    case DataTypeTrue = 'True';
    #[SchemaDataTypeInfo(rdfs_comment: 'Data type: URL.')]
    case URL = 'URL';
    #[SchemaDataTypeInfo(rdfs_comment: 'Text representing an XPath (typically but not necessarily version 1.0).')]
    case XPathType = 'XPathType';

    /**
     * SchemaDataTypeInfo attribute for this case (read via reflection).
     */
    public function info(): ?SchemaDataTypeInfo
    {
        $ref = (new \ReflectionEnum(self::class))->getCase($this->name);
        $attrs = $ref->getAttributes(SchemaDataTypeInfo::class);

        return isset($attrs[0]) ? $attrs[0]->newInstance() : null;
    }

    /**
     * DataType description (rdfs:comment) as HTML with links.
     */
    public function description(): ?string
    {
        return $this->info()?->rdfs_comment;
    }

    /**
     * Human-readable label (the DataType name).
     */
    public function label(): string
    {
        return $this->value;
    }
}
