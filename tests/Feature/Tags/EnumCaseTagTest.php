<?php

declare(strict_types=1);

use App\Tags\EnumCase;
use PHPUnit\Framework\Assert;
use Statamic\Fields\Value;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use Tests\Support\Doubles\EnumCaseBlankProbe;
use Tests\Support\Doubles\EnumCaseIntProbe;
use Tests\Support\Doubles\EnumCaseProbeBroken;
use Tests\Support\Doubles\EnumCaseProbeInfo;
use Tests\Support\Doubles\EnumCaseProbeOther;
use Tests\Support\Doubles\EnumCasePureProbe;
use Tests\Support\Doubles\EnumCaseStringProbe;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for the `enum_case` Antlers tag across every `App\Enums\*` enum.
 *
 * These tests also surface attribute/reflection problems on enum cases (for
 * example attributes that cannot be instantiated via reflection).
 *
 * Mutation: 100% on App\Tags\EnumCase (136 mutants, --covered-only), with 10 equivalents
 * annotated in the class. Three lines are not covered. One is the UnitEnum check, which every enum
 * passes. The other two are the ReflectionException catch after property_exists(), which
 * only an attribute with a dynamic property shadowing a parent's private one can reach.
 *
 * @command  php artisan test --compact tests/Feature/Tags/EnumCaseTagTest.php
 * @command  php artisan test --compact tests/Feature/Tags/EnumCaseTagTest.php --stop-on-failure
 */
it('returns null for an unknown enum class', function () {
    expect(enumCaseTag_invoke([
        'enum' => 'App\\Enums\\NonExistentEnum',
        'case' => 'X',
        'property' => 'name',
    ]))->toBeNull();
});

it('returns null for an unknown case', function () {
    expect(enumCaseTag_invoke([
        'enum' => 'App\\Enums\\Country',
        'case' => 'ZZ',
        'property' => 'name',
    ]))->toBeNull();
});

it('accepts an explicit attribute class to narrow the read', function () {
    expect(enumCaseTag_invoke([
        'enum' => 'App\\Enums\\Country',
        'case' => 'US',
        'property' => 'country_name',
        'attribute' => 'App\\Enums\\CountryInfo',
    ]))->toBe('United States of America');
});

it('resolves country_name without an attribute because it is not the reserved name shortcut', function () {
    expect(enumCaseTag_invoke([
        'enum' => 'App\\Enums\\Country',
        'case' => 'US',
        'property' => 'country_name',
    ]))->toBe('United States of America');
});

it('treats reserved property name without attribute as the case name', function () {
    expect(enumCaseTag_invoke([
        'enum' => 'App\\Enums\\Country',
        'case' => 'US',
        'property' => 'name',
    ]))->toBe('US');
});

it('resolves by case name when the backing value differs for isco08', function () {
    expect(enumCaseTag_invoke([
        'enum' => 'App\\Enums\\Isco08',
        'case' => 'ISCO08_2512',
        'property' => 'title',
        'attribute' => 'App\\Enums\\Attributes\\Isco08Info',
    ]))->toBe('Software Developers');
});

it('matches property name to the first case for every app enum', function () {
    foreach (enumCaseTag_discoverAppEnumClasses() as $class) {
        /** @var class-string<UnitEnum> $class */
        $first = $class::cases()[0];
        $identifier = $first instanceof BackedEnum
            ? $first->value
            : $first->name;

        expect(enumCaseTag_invoke([
            'enum' => $class,
            'case' => $identifier,
            'property' => 'name',
        ]))->toBe($first->name, "property=name failed for {$class}");
    }
});

it('matches property value to the first case for every backed app enum', function () {
    foreach (enumCaseTag_discoverAppEnumClasses() as $class) {
        /** @var class-string<UnitEnum> $class */
        $first = $class::cases()[0];

        if (! $first instanceof BackedEnum) {
            continue;
        }

        expect(enumCaseTag_invoke([
            'enum' => $class,
            'case' => $first->value,
            'property' => 'value',
        ]))->toBe($first->value, "property=value failed for {$class}");
    }
});

it('matches the first attribute property to reflection for every app enum', function () {
    $instantiationFailures = [];

    foreach (enumCaseTag_discoverAppEnumClasses() as $class) {
        /** @var class-string<UnitEnum> $class */
        $first = $class::cases()[0];
        $reflectionEnum = new ReflectionEnum($class);
        $refCase = $reflectionEnum->getCase($first->name);

        $picked = enumCaseTag_pickFirstAttributePublicProperty($refCase, $instantiationFailures, $class);

        if ($picked === null) {
            Assert::fail(
                "No readable public attribute property on first case of {$class}; fix enum metadata or adjust discovery rules."
            );
        }

        $caseIdentifier = $first instanceof BackedEnum
            ? $first->value
            : $first->name;

        $params = [
            'enum' => $class,
            'case' => $caseIdentifier,
            'property' => $picked['property'],
        ];

        expect(enumCaseTag_invoke($params))->toEqual(
            $picked['expected'],
            "enum_case (auto attribute) failed for {$class} property {$picked['property']}"
        );

        $params['attribute'] = $picked['attributeClass'];

        expect(enumCaseTag_invoke($params))->toEqual(
            $picked['expected'],
            "enum_case (explicit attribute) failed for {$class} property {$picked['property']}"
        );
    }

    expect($instantiationFailures)->toBe(
        [],
        'One or more enum case attributes failed ReflectionAttribute::newInstance(): '
        .json_encode($instantiationFailures, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
    );
});

/**
 * Discover every `App\Enums\*` enum class on disk, sorted.
 *
 * @return list<class-string<UnitEnum>>
 */
function enumCaseTag_discoverAppEnumClasses(): array
{
    $classes = [];

    foreach (glob(base_path('app/Enums/*.php')) ?: [] as $path) {
        $base = basename($path, '.php');
        $fqcn = 'App\\Enums\\'.$base;

        if (enum_exists($fqcn)) {
            $classes[] = $fqcn;
        }
    }

    sort($classes);

    return $classes;
}

/**
 * Invoke the `enum_case` tag's `index()` with the given tag parameters.
 *
 * @param  array<mixed>  $params
 */
function enumCaseTag_invoke(array $params): mixed
{
    $tag = new EnumCase;
    $tag->setProperties([
        'parser' => null,
        'content' => '',
        'context' => [],
        'params' => Parameters::make($params, Context::make([])),
        'tag' => 'enum_case',
        'tag_method' => 'index',
    ]);

    return $tag->index();
}

/**
 * Choose the first attribute on the case that instantiates and exposes at
 * least one public property declared on that attribute class.
 *
 * @param  list<string>  $instantiationFailures  Collect attribute names that
 *                                               failed `newInstance()`
 * @param  class-string<UnitEnum>  $enumClass
 * @return ?array{attributeClass: class-string, property: string, expected: mixed}
 */
function enumCaseTag_pickFirstAttributePublicProperty(
    ReflectionEnumUnitCase $refCase,
    array &$instantiationFailures,
    string $enumClass
): ?array {
    foreach ($refCase->getAttributes() as $attribute) {
        try {
            $instance = $attribute->newInstance();
        } catch (Throwable $e) {
            $instantiationFailures[] = sprintf(
                '%s::%s attribute %s: %s',
                $enumClass,
                $refCase->getName(),
                $attribute->getName(),
                $e->getMessage()
            );

            continue;
        }

        $instanceClass = new ReflectionClass($instance);

        $propertyNames = [];

        foreach ($instanceClass->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->getDeclaringClass()->getName() !== $instanceClass->getName()) {
                continue;
            }

            $propertyNames[$prop->getName()] = $prop->getValue($instance);
        }

        if ($propertyNames === []) {
            continue;
        }

        ksort($propertyNames);
        $name = array_key_first($propertyNames);

        return [
            'attributeClass' => $attribute->getName(),
            'property' => $name,
            'expected' => $propertyNames[$name],
        ];
    }

    return null;
}

/*
|--------------------------------------------------------------------------
| Probe enums and attributes
|--------------------------------------------------------------------------
|
| The app's own enums exercise the happy paths above. The EnumCase probes in
| tests/Support/Doubles reach what they cannot: a pure enum, an int-backed enum
| with negative and zero values, an attribute that refuses to instantiate, and
| methods that are private, static, variadic, by-reference or throwing.
*/

/**
 * Run the tag over params built directly, without Parameters::make().
 *
 * make() already unwraps a top-level Value, so the tag's own unwrap is reached
 * only by params built this way.
 *
 * @param  array<string, mixed>  $params
 */
function enumCaseTag_invokeRaw(array $params): mixed
{
    $tag = new EnumCase;
    $tag->setProperties([
        'parser' => null,
        'content' => '',
        'context' => [],
        'params' => [],
        'tag' => 'enum_case',
        'tag_method' => 'index',
    ]);
    // Assigned after setProperties(), which passes params through Parameters::make().
    $tag->params = new Parameters($params);

    return $tag->index();
}

it('returns null for a missing, blank, non-string or non-enum enum class', function (mixed $enum) {
    expect(enumCaseTag_invoke(['enum' => $enum, 'case' => 'Solo', 'property' => 'name']))->toBeNull();
})->with([
    'missing' => [null],
    'blank' => ['   '],
    'false' => [false],
    'true' => [true],
    'array' => [[EnumCasePureProbe::class]],
    'a class that is not an enum' => [EnumCaseProbeInfo::class],
]);

it('trims the enum class and the property', function () {
    expect(enumCaseTag_invoke(['enum' => '  '.EnumCasePureProbe::class.'  ', 'case' => 'Solo', 'property' => '  name  ']))->toBe('Solo');
});

it('returns null when neither a method nor a property is requested', function (array $params) {
    expect(enumCaseTag_invoke(['enum' => EnumCasePureProbe::class, 'case' => 'Solo', ...$params]))->toBeNull();
})->with([
    'neither' => [[]],
    'both blank' => [['method' => '', 'property' => '  ']],
    'objects' => [['method' => new stdClass, 'property' => new stdClass]],
]);

it('returns the name of a pure case, and no value for it', function () {
    expect(enumCaseTag_invoke(['enum' => EnumCasePureProbe::class, 'case' => 'Solo', 'property' => 'name']))->toBe('Solo')
        ->and(enumCaseTag_invoke(['enum' => EnumCasePureProbe::class, 'case' => 'Solo', 'property' => 'value']))->toBeNull();
});

it('resolves an int-backed case from an int, a digit string, a negative string, or its name', function (mixed $case, string $name) {
    expect(enumCaseTag_invoke(['enum' => EnumCaseIntProbe::class, 'case' => $case, 'property' => 'name']))->toBe($name);
})->with([
    'int' => [7, 'Seven'],
    'digit string' => ['7', 'Seven'],
    'zero string' => ['0', 'Zero'],
    'negative string' => ['-2', 'MinusTwo'],
    'case name' => ['Seven', 'Seven'],
]);

it('finds no int-backed case for an unknown value, a non-numeric string, a float or a bool', function (mixed $case) {
    expect(enumCaseTag_invoke(['enum' => EnumCaseIntProbe::class, 'case' => $case, 'property' => 'name']))->toBeNull();
})->with([
    'unknown int' => [99],
    'unknown digit string' => ['99'],
    'dash only' => ['-'],
    'dash then letters' => ['-x'],
    'letters' => ['abc'],
    'float' => [7.0],
    'bool' => [true],
    'empty string' => [''],
    'null' => [null],
]);

it('resolves a string-backed case from its value or its name, and nothing from other input', function () {
    $read = fn (mixed $case): mixed => enumCaseTag_invoke(['enum' => EnumCaseStringProbe::class, 'case' => $case, 'property' => 'name']);

    expect($read('a'))->toBe('Alpha')
        ->and($read('B-1'))->toBe('Beta')
        ->and($read('Beta'))->toBe('Beta')
        ->and($read(5))->toBeNull()
        ->and($read(['a']))->toBeNull();
});

it('returns the backing value of an int-backed case', function () {
    expect(enumCaseTag_invoke(['enum' => EnumCaseIntProbe::class, 'case' => 'MinusTwo', 'property' => 'value']))->toBe(-2);
});

it('reads the first attribute exposing the property, past one that cannot be instantiated', function () {
    expect(enumCaseTag_invoke(['enum' => EnumCaseIntProbe::class, 'case' => 7, 'property' => 'label']))->toBe('Seven');
});

it('keeps scanning past an attribute whose property is null', function () {
    expect(enumCaseTag_invoke(['enum' => EnumCaseIntProbe::class, 'case' => 7, 'property' => 'note']))->toBe('other note');
});

it('returns null for a property no attribute has, a protected one, or a case with no attributes', function (mixed $case, string $property) {
    expect(enumCaseTag_invoke(['enum' => EnumCaseIntProbe::class, 'case' => $case, 'property' => $property]))->toBeNull();
})->with([
    'unknown property' => [7, 'nope'],
    'protected property' => [7, 'secret'],
    'case without attributes' => [0, 'label'],
]);

it('reads only the named attribute when one is given, even for name', function () {
    $read = fn (array $params): mixed => enumCaseTag_invoke(['enum' => EnumCaseIntProbe::class, 'case' => 7, ...$params]);

    expect($read(['attribute' => EnumCaseProbeOther::class, 'property' => 'label']))->toBe('Other seven')
        ->and($read(['attribute' => EnumCaseProbeInfo::class, 'property' => 'name']))->toBeNull()
        ->and($read(['attribute' => EnumCaseProbeInfo::class, 'property' => 'secret']))->toBeNull()
        ->and($read(['attribute' => EnumCaseProbeBroken::class, 'property' => 'label']))->toBeNull()
        ->and($read(['attribute' => 'App\\NoSuchAttribute', 'property' => 'label']))->toBeNull();
});

it('returns null for a named attribute the case does not carry', function () {
    expect(enumCaseTag_invoke(['enum' => EnumCaseIntProbe::class, 'case' => 0, 'attribute' => EnumCaseProbeInfo::class, 'property' => 'label']))->toBeNull();
});

it('calls a public instance method with its defaults, binding only arg_ parameters', function () {
    $call = fn (array $params): mixed => enumCaseTag_invoke(['enum' => EnumCaseIntProbe::class, 'case' => 7, 'method' => 'greet', ...$params]);

    expect($call([]))->toBe('Hello Seven!')
        ->and($call(['arg_greeting' => 'Hi']))->toBe('Hi Seven!')
        ->and($call(['arg_punctuation' => '?', 'greeting' => 'ignored']))->toBe('Hello Seven?');
});

it('prefers the method over the property', function () {
    expect(enumCaseTag_invoke(['enum' => EnumCaseIntProbe::class, 'case' => 7, 'method' => 'greet', 'property' => 'name']))->toBe('Hello Seven!');
});

it('returns null for a method it must not call, and for one that throws', function (string $method, array $args) {
    expect(enumCaseTag_invoke(['enum' => EnumCaseIntProbe::class, 'case' => 7, 'method' => $method, ...$args]))->toBeNull();
})->with([
    'magic, declared on the enum' => ['__invoke', []],
    'magic, not declared' => ['__toString', []],
    'missing' => ['noSuchMethod', []],
    'private' => ['hidden', []],
    'static' => ['make', []],
    'variadic' => ['sum', ['arg_numbers' => 1]],
    'by reference' => ['fill', ['arg_into' => []]],
    'throws' => ['fails', []],
    'argument of the wrong type' => ['greet', ['arg_greeting' => ['not', 'a', 'string']]],
]);

it('returns null when the case for a method call does not resolve', function () {
    expect(enumCaseTag_invoke(['enum' => EnumCaseIntProbe::class, 'case' => 99, 'method' => 'greet']))->toBeNull();
});

it('unwraps Value-wrapped parameters, method arguments included', function () {
    expect(enumCaseTag_invokeRaw([
        'enum' => new Value(EnumCaseIntProbe::class),
        'case' => new Value('7'),
        'method' => new Value('greet'),
        'arg_greeting' => new Value('Hey'),
    ]))->toBe('Hey Seven!');
});

it('treats a parameter that is neither scalar, array nor object as blank', function () {
    // A resource is the one value that reaches coerceNonEmptyString()'s non-scalar fallback.
    $stream = fopen('php://memory', 'r');

    try {
        expect(enumCaseTag_invoke(['enum' => EnumCasePureProbe::class, 'case' => 'Solo', 'property' => $stream]))->toBeNull();
    } finally {
        if (is_resource($stream)) {
            fclose($stream);
        }
    }
});

it('finds no case for an empty identifier, even when a case is backed by the empty string', function () {
    expect(enumCaseTag_invoke(['enum' => EnumCaseBlankProbe::class, 'case' => '', 'property' => 'name']))->toBeNull()
        ->and(enumCaseTag_invoke(['enum' => EnumCaseBlankProbe::class, 'case' => null, 'property' => 'name']))->toBeNull()
        ->and(enumCaseTag_invoke(['enum' => EnumCaseBlankProbe::class, 'case' => 'x', 'property' => 'name']))->toBe('Filled');
});

it('looks a non-scalar identifier up as the empty backing value of a string enum', function () {
    // Pinned as it stands: an array identifier reaches tryFrom(''), so it resolves to
    // a case backed by the empty string.
    expect(enumCaseTag_invoke(['enum' => EnumCaseBlankProbe::class, 'case' => ['x'], 'property' => 'name']))->toBe('Blank');
});

it('casts a numeric parameter to a string before trimming it', function () {
    // Under strict types, trim() rejects an int, so a missing cast fails loudly here.
    expect(enumCaseTag_invoke(['enum' => EnumCasePureProbe::class, 'case' => 'Solo', 'property' => 5]))->toBeNull();
});

it('ignores a blank method and reads the property instead', function () {
    expect(enumCaseTag_invoke(['enum' => EnumCasePureProbe::class, 'case' => 'Solo', 'method' => '   ', 'property' => 'name']))->toBe('Solo');
});

it('resolves a float identifier by its string form as a case name', function () {
    expect(enumCaseTag_invoke(['enum' => EnumCasePureProbe::class, 'case' => NAN, 'property' => 'name']))->toBe('NAN');
});

it('refuses a private method even when the enum would route the call through __call', function () {
    expect(enumCaseTag_invoke(['enum' => EnumCaseIntProbe::class, 'case' => 7, 'method' => 'hidden']))->toBeNull();
});
