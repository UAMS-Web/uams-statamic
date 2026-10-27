<?php

declare(strict_types=1);

namespace App\Tags;

use BackedEnum;
use ReflectionAttribute;
use ReflectionEnum;
use Statamic\Fields\Value;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;
use UnitEnum;

/**
 * Antlers tag: read something from a PHP enum case—either the case’s own
 * `name` / backing `value`, a public property from a PHP attribute on that
 * case (for example `#[Isco08Info(...)]` or `#[IcdInfo(...)]`), or the result
 * of invoking a public, non-static, non-magic instance method on the case.
 *
 * Parameters:
 *   - enum: fully-qualified enum class name (required)
 *   - case: for backed enums, the backing value (string or int) or the case
 *     name; for pure enums, the case name (required)
 *   - method: optional public, non-static, non-magic instance method name to
 *     invoke on the resolved case; takes precedence over `property` /
 *     `attribute` when set
 *   - property: which field to return; required when `method` is not set,
 *     meaning depends on `attribute`, see below
 *   - attribute: optional fully-qualified attribute class name
 *   - arg_<paramName>: optional method-argument bindings; see below
 *
 * Either `method` or `property` MUST be set; if both are set, `method` wins
 * and the property/attribute branch is skipped.
 *
 * How `property` and `attribute` work together (illustrated with a backed case
 * where the **case name** and **backing value** differ; pass either as `case`):
 *
 *   ```php
 *   #[IcdInfo(id: '0090', name: 'Internet IP addressing - ISO 6523 ICD encoding', description: 'Issuing agency: Internet Assigned Numbers Authority, USA.')]
 *   case ICD_0090 = '0090';
 *   ```
 *
 *   1. **`attribute` omitted**
 *
 *      - `property="name"` — PHP case name (`ICD_0090`), not `IcdInfo::$name`.
 *        `{{ enum_case enum="App\Enums\InternationalCodeDesignator" case="0090" property="name" }}` → `ICD_0090`
 *        (same with `case="ICD_0090"`.)
 *
 *      - `property="value"` — backing value.
 *        `{{ enum_case enum="App\Enums\InternationalCodeDesignator" case="0090" property="value" }}` → `0090`
 *
 *      - any other `property` — first attribute on the case with that public field.
 *        `{{ enum_case enum="App\Enums\InternationalCodeDesignator" case="0090" property="id" }}` → `0090`
 *        `{{ enum_case enum="App\Enums\InternationalCodeDesignator" case="0090" property="description" }}` → `Issuing agency: Internet Assigned Numbers Authority, USA.`
 *
 *   2. **`attribute` set** — every `property` is read from that attribute only
 *      (so `property="name"` is `IcdInfo::$name`, not the PHP case name).
 *      `{{ enum_case enum="App\Enums\InternationalCodeDesignator" case="0090" attribute="App\Enums\IcdInfo" property="name" }}` → `Internet IP addressing - ISO 6523 ICD encoding`
 *
 *   3. **`method` set** — invoke a public, non-static, non-magic instance
 *      method on the resolved case. Method arguments are bound via an
 *      always-on `arg_*` prefix on tag parameter names: for each PHP
 *      parameter `$foo` on the reflected method, the tag reads `arg_foo`
 *      from its params. When a prefixed key is absent, the entry is skipped
 *      so the method's PHP-level default applies. Bare names (e.g.
 *      `separator=…`) are NOT bound to method parameters, which keeps
 *      reserved tag params (`enum`, `case`, `property`, `attribute`,
 *      `method`) from colliding with method arg names. Variadic and
 *      by-reference parameters are not supported (the tag returns null).
 *      `{{ enum_case enum="App\Enums\IscedA2011" case="85" method="labelsWithAncestors" arg_separator=", " }}` → `Doctoral or equivalent level, Professional`
 *
 * Further usage:
 *   `{{ enum_case enum="App\Enums\Isco08" case="2512" property="title" }}` → `Software Developers`
 *   `{{ enum_case enum="App\Enums\Isco08" case="ISCO08_2512" property="name" }}` → `ISCO08_2512`
 */
class EnumCase extends Tags
{
    /**
     * Antlers tag handle used in templates as `{{ enum_case }}`.
     *
     * @var string
     */
    protected static $handle = 'enum_case';

    /**
     * Resolve the enum case field and return it for the template.
     *
     * Tag parameters (`enum`, `case`, `method`, `property`, `attribute`, and
     * any `arg_*` method-argument bindings) are read from `$this->params`;
     * there are no PHP method parameters.
     *
     * @return mixed Method return value, attribute scalar or array, enum
     *               case `name` / `value`, or null when inputs are invalid
     *               or neither a method nor a property is requested
     */
    public function index(): mixed
    {
        // Coerce the enum class to a non-empty string
        $enumClass = $this->coerceNonEmptyString($this->unwrapParam('enum'));

        // Check if the enum class is null
        if ($enumClass === null) {
            // Return null to signal that the enum class is null
            return null;
        }

        // Check if the enum class exists
        if (! enum_exists($enumClass)) {
            // Return null to signal that the enum class does not exist
            return null;
        }

        // Create a reflection of the enum class
        $reflectionEnum = new ReflectionEnum($enumClass);

        // Check if the enum class implements the UnitEnum interface
        if (! $reflectionEnum->implementsInterface(UnitEnum::class)) {
            // Return null to signal that the enum class does not implement the UnitEnum interface
            return null;
        }

        // Unwrap the case identifier
        $caseIdentifier = $this->unwrapParam('case');

        // Coerce the method name to a non-empty string (optional; takes precedence over property/attribute)
        $methodName = $this->coerceNonEmptyString($this->unwrapParam('method'));

        // Coerce the property to a non-empty string (required only when no method is requested)
        $property = $this->coerceNonEmptyString($this->unwrapParam('property'));

        // Check if neither a method nor a property was requested
        if (
            $methodName === null
            && $property === null
        ) {
            // Return null to signal that there is nothing to read or call
            return null;
        }

        // Resolve the case
        $unitCase = $this->resolveCase($enumClass, $reflectionEnum, $caseIdentifier);

        // Check if the case is null
        if (! $unitCase instanceof UnitEnum) {
            // Return null to signal that the case is null
            return null;
        }

        // Check if a method invocation was requested
        if ($methodName !== null) {
            // Dispatch to the method-call branch (takes precedence over property/attribute)
            return $this->callCaseMethod(
                $reflectionEnum,
                $unitCase,
                $methodName
            );
        }

        // Coerce the attribute class to a non-empty string
        $attributeClass = $this->coerceNonEmptyString($this->unwrapParam('attribute'));

        // Check if the attribute class is null
        if ($attributeClass === null) {
            // Check if the property is 'name'
            if ($property === 'name') {
                // Return the name of the case
                return $unitCase->name;
            }

            // Check if the property is 'value'
            if ($property === 'value') {
                // Check if the case is a backed enum
                if (! $unitCase instanceof BackedEnum) {
                    // Return null to signal that the case is not a backed enum
                    return null;
                }

                // Return the value of the case
                return $unitCase->value;
            }
        }

        // Read the attribute property
        return $this->readAttributeProperty(
            $reflectionEnum,
            $unitCase,
            $property,
            $attributeClass
        );
    }

    /**
     * Invoke a public, non-static, non-magic instance method on the resolved case.
     *
     * Tag-side method arguments are bound via an always-on `arg_*` prefix:
     * for each `ReflectionParameter` named `$foo` on the reflected method,
     * the helper reads `arg_foo` from `$this->params`. When the prefixed
     * key is absent, the entry is skipped so the method's PHP-level default
     * applies. Variadic and by-reference parameters are not supported and
     * cause the helper to return null. Any throwable raised by the method
     * itself is also swallowed into a null return so a misconfigured
     * template does not crash the surrounding render.
     *
     * @param  ReflectionEnum<UnitEnum>  $reflectionEnum  Reflection instance for the enum containing `$case`
     * @param  UnitEnum  $case  Resolved enum case the method is invoked on
     * @param  string  $methodName  Public, non-static, non-magic instance method name
     * @return mixed The method's return value, or null when the method is not callable or raises
     */
    private function callCaseMethod(
        ReflectionEnum $reflectionEnum,
        UnitEnum $case,
        string $methodName
    ): mixed {
        // Reject magic methods up front (e.g., __toString must not be invoked through this tag)
        if (str_starts_with($methodName, '__')) {
            // Return null to signal that magic methods are not callable through this tag
            return null;
        }

        // Check if the method exists on the enum class
        if (! $reflectionEnum->hasMethod($methodName)) {
            // Return null to signal that the method does not exist on the enum class
            return null;
        }

        // Reflect the method on the enum class
        $refMethod = $reflectionEnum->getMethod($methodName);

        // Check if the method is public
        if (! $refMethod->isPublic()) {
            // Return null to signal that the method is not public
            return null;
        }

        // Check if the method is static (this branch dispatches on a case instance)
        if ($refMethod->isStatic()) {
            // Return null to signal that the method is static
            return null;
        }

        // Initialize the args map for named-arg unpacking
        $args = [];

        // Walk reflected parameters to bind tag params by name with the always-on `arg_` prefix
        foreach ($refMethod->getParameters() as $refParam) {
            // Check if the parameter is variadic
            if ($refParam->isVariadic()) {
                // Return null to signal that variadic parameters are not supported
                return null;
            }

            // Check if the parameter is passed by reference
            if ($refParam->isPassedByReference()) {
                // Return null to signal that by-reference parameters are not supported
                return null;
            }

            // Build the tag-side parameter key
            $tagKey = 'arg_'.$refParam->getName();

            // Check if the prefixed key is set on the tag params
            /** @var Parameters $params */
            $params = $this->params;
            if (! $params->has($tagKey)) {
                // Skip so PHP's default value applies
                continue;
            }

            // Bind the unwrapped value under the PHP parameter name for named-arg unpacking
            $args[$refParam->getName()] = $this->unwrapParam($tagKey);
        }

        // Attempt to invoke the method on the case using named-arg unpacking
        try {
            // Invoke the method on the case
            return $case->{$methodName}(...$args);
        } catch (\Throwable) {
            // Return null to signal that the call failed
            return null;
        }
    }

    /**
     * Find the enum case from a backing value or case name.
     *
     * @param  class-string<UnitEnum>  $enumClass  Fully-qualified enum class name
     * @param  ReflectionEnum<UnitEnum>  $reflectionEnum  Reflection instance for `$enumClass`
     * @param  mixed  $identifier  Backing value, case name, or other scalar used to locate the case
     * @return ?UnitEnum Matched case, or null when not found
     */
    private function resolveCase(
        string $enumClass,
        ReflectionEnum $reflectionEnum,
        mixed $identifier
    ): ?UnitEnum {
        // Check if the identifier is null or empty
        if (
            $identifier === null
            || $identifier === ''
        ) {
            // Return null to signal that the identifier is null or empty
            return null;
        }

        // Check if the enum is backed
        if ($reflectionEnum->isBacked()) {
            // Get the backing type of the enum
            /** @var \ReflectionNamedType $backingType */
            $backingType = $reflectionEnum->getBackingType();

            // Get the backed enum class
            /** @var class-string<BackedEnum> $backedEnumClass */
            $backedEnumClass = $enumClass;

            // Get the backing name of the enum
            $backingName = $backingType->getName();

            // Check if the backing name is 'int'
            if ($backingName === 'int') {
                // Check if the identifier is an integer
                if (\is_int($identifier)) {
                    // Try to get the case from the backed enum class
                    $fromBacking = $backedEnumClass::tryFrom($identifier);
                } elseif ( // Check if the identifier is a string and is a digit
                    \is_string($identifier)
                    && ctype_digit($identifier)
                ) {
                    // Try to get the case from the backed enum class
                    $fromBacking = $backedEnumClass::tryFrom((int) $identifier);
                } elseif ( // Check if the identifier is a string and starts with a '-' and is a digit
                    \is_string($identifier)
                    && str_starts_with($identifier, '-')
                    && ctype_digit(substr($identifier, 1))
                ) {
                    // Try to get the case from the backed enum class
                    $fromBacking = $backedEnumClass::tryFrom((int) $identifier);
                } else {
                    // Return null to signal that the case is not found
                    $fromBacking = null;
                }

                // Check if the case is found
                if ($fromBacking !== null) {
                    // Return the case
                    return $fromBacking;
                }
            }

            // Check if the backing name is 'string'
            if ($backingName === 'string') {
                // Try to get the case from the backed enum class
                $fromBacking = $backedEnumClass::tryFrom(\is_string($identifier) ? $identifier : (\is_scalar($identifier) ? (string) $identifier : ''));

                // Check if the case is found
                if ($fromBacking !== null) {
                    // Return the case
                    return $fromBacking;
                }
            }
        }

        // Coerce the identifier to a string
        $asName = \is_string($identifier)
            ? $identifier
            : (\is_int($identifier) || \is_float($identifier) ? (string) $identifier : null);

        // Check if the identifier is null
        if ($asName === null) {
            // Return null to signal that the identifier is null
            return null;
        }

        // Check if the enum has a case with the identifier
        if (! $reflectionEnum->hasCase($asName)) {
            // Return null to signal that the enum does not have a case with the identifier
            return null;
        }

        // Return the value of the case
        return $reflectionEnum->getCase($asName)->getValue();
    }

    /**
     * Read a public property from a case attribute instance.
     *
     * @param  ReflectionEnum<UnitEnum>  $reflectionEnum  Reflection instance for the enum containing `$case`
     * @param  UnitEnum  $case  Resolved enum case
     * @param  string  $property  Attribute property name (or `name` / `value` when `$attributeClass` is set)
     * @param  ?string  $attributeClass  Optional attribute FQCN; when null, scan all attributes on the case
     * @return mixed The property value, or null when not present
     */
    private function readAttributeProperty(
        ReflectionEnum $reflectionEnum,
        UnitEnum $case,
        string $property,
        ?string $attributeClass
    ): mixed {
        // Get the case from the reflection enum
        $refCase = $reflectionEnum->getCase($case->name);

        // Check if the attribute class is not null
        if ($attributeClass !== null) {
            // Check if the attribute class exists. Equivalent without the return:
            // getAttributes() with an unknown class returns [], handled just below.
            // @pest-mutate-ignore: RemoveEarlyReturn
            if (! class_exists($attributeClass)) {
                // Return null to signal that the attribute class does not exist
                return null;
            }

            // Get the attributes from the case
            $attributes = $refCase->getAttributes($attributeClass);

            // Check if the attributes are empty
            if ($attributes === []) {
                // Return null to signal that the attributes are empty
                return null;
            }

            // Return the value of the attribute property
            return $this->attributePropertyValue($attributes[0], $property);
        }

        // Loop through the attributes
        foreach ($refCase->getAttributes() as $attribute) {
            // Get the value of the attribute property
            $value = $this->attributePropertyValue($attribute, $property);

            // Check if the value is not null
            if ($value !== null) {
                // Return the value
                return $value;
            }
        }

        // Return null to signal that the value is null
        return null;
    }

    /**
     * Instantiate a reflected attribute and read one property, if it exists.
     *
     * @param  ReflectionAttribute<object>  $attribute  Attribute instance from the enum case constant
     * @param  string  $property  Public property name on the attribute class
     * @return mixed Property value, or null when missing or not readable
     */
    private function attributePropertyValue(
        ReflectionAttribute $attribute,
        string $property
    ): mixed {
        // Attempt to instantiate the attribute
        try {
            // Instantiate the attribute
            $instance = $attribute->newInstance();
        } catch (\Throwable) {
            // Return null to signal that the attribute is not instantiable
            return null;
        }

        // Check if the property exists on the instance. Equivalent without the return:
        // ReflectionProperty then throws, and the catch below returns null.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! property_exists($instance, $property)) {
            // Return null to signal that the property does not exist on the instance
            return null;
        }

        // Attempt to get the reflection property
        try {
            // Get the reflection property
            $refProp = new \ReflectionProperty($instance, $property);
        } catch (\ReflectionException) {
            // Return null to signal that the reflection property is not found
            return null;
        }

        // Check if the reflection property is public
        if (! $refProp->isPublic()) {
            // Return null to signal that the reflection property is not public
            return null;
        }

        // Return the value of the reflection property
        return $refProp->getValue($instance);
    }

    /**
     * Trim a scalar tag parameter to a non-empty string, or null.
     *
     * @param  mixed  $raw  Unwrapped parameter value
     * @return ?string Non-empty trimmed string, or null when not representable as usable text
     */
    private function coerceNonEmptyString(
        mixed $raw
    ): ?string {
        // Check if the value is null, an array, an object or a resource
        if (! \is_scalar($raw)) {
            // Return null to signal that the value is not a usable scalar
            return null;
        }

        // Coerce the scalar to a string (false to '', true to '1') and trim outer
        // whitespace only
        $trimmed = trim((string) $raw);

        // Check if the trimmed string is empty
        if ($trimmed === '') {
            // Return null to signal that the trimmed string is empty
            return null;
        }

        // Return the usable non-empty string for enum FQCNs, property names, etc.
        return $trimmed;
    }

    /**
     * Read a tag parameter and unwrap Statamic Value instances.
     *
     * @param  string  $key  Parameter name on `$this->params`
     * @return mixed Raw parameter value after optional `Value` unwrap
     */
    private function unwrapParam(
        string $key
    ): mixed {
        // Read the parameter value from the tag parameters
        /** @var Parameters $params */
        $params = $this->params;
        $raw = $params->get($key);

        // Check if the value is a Statamic Value instance
        if ($raw instanceof Value) {
            // Return the value of the Statamic Value instance
            return $raw->value();
        }

        // Return the raw value
        return $raw;
    }
}
