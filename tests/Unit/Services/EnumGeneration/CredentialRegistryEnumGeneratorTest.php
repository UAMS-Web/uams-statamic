<?php

declare(strict_types=1);

use App\Enums\CredentialRegistry;
use App\Services\EnumGeneration\CredentialRegistry\CredentialRegistryEnumGenerator;

/**
 * Behavior of CredentialRegistryEnumGenerator
 * (app/Services/EnumGeneration/CredentialRegistry/CredentialRegistryEnumGenerator.php):
 * the private tree/YAML/URI/parent-collapse helpers (exercised via reflection), plus a
 * few assertions that the GENERATED App\Enums\CredentialRegistry exposes the expected
 * hierarchy metadata via info().
 *
 * Mutation (of the generator's reflection-covered helpers): every killable mutant is
 * dead (score ~92%). The seven residual survivors are equivalents — the `(string)`
 * tree-key cast (terms are non-numeric), the recurse guard `is_array && !== []` (a
 * subtree is always an array), `$m[1]`->`$m[0]` in the YAML extractor (both feed the
 * same `- term` regex), the strict-flag `in_array` and the break-vs-continue in the
 * redundant-parent loop (the flag is already set), and the `count($minimal) === 1`
 * fast-path / its early return (`implode(',', [x])` equals `x`). Left clean rather
 * than annotated with `@pest-mutate-ignore`.
 *
 * @command  php artisan test --compact tests/Unit/Services/EnumGeneration/CredentialRegistryEnumGeneratorTest.php
 */
it('collects parent/child edges from a nested tree', function () {
    $generator = new CredentialRegistryEnumGenerator;
    $method = (new ReflectionClass($generator))->getMethod('collectEdgesFromTree');
    $method->setAccessible(true);

    $tree = [
        'ceterms:Root' => [
            'ceterms:Child' => [
                'ceterms:Leaf' => [],
            ],
        ],
    ];

    $edges = [];
    $method->invokeArgs($generator, [null, $tree, &$edges]);

    expect($edges)->toBe([
        ['ceterms:Root', 'ceterms:Child'],
        ['ceterms:Child', 'ceterms:Leaf'],
    ]);
});

it('extracts property + subject/target types from description-set-maps YAML', function () {
    $generator = new CredentialRegistryEnumGenerator;
    $method = (new ReflectionClass($generator))->getMethod('parseDescriptionSetMapsYaml');
    $method->setAccessible(true);

    $yaml = <<<'YAML'
    ---
    - :name: Sample
      :path: "> ceterms:ownedBy > ceterms:Agent"
      :property_path: "> ceterms:ownedBy"
      :subject_types:
      - ceterms:Credential
      :target_types:
      - ceterms:Organization
    YAML;

    expect($method->invoke($generator, $yaml))->toBe([
        [
            'property' => 'ceterms:ownedBy',
            'subject_types' => ['ceterms:Credential'],
            'target_types' => ['ceterms:Organization'],
        ],
    ]);
});

it('maps known prefixes to stable namespace URIs', function () {
    $generator = new CredentialRegistryEnumGenerator;
    $method = (new ReflectionClass($generator))->getMethod('shorthandToUri');
    $method->setAccessible(true);

    expect($method->invoke($generator, 'ceterms:Credential'))->toBe('https://purl.org/ctdl/terms/Credential');
    expect($method->invoke($generator, 'schema:QuantitativeValue'))->toBe('https://schema.org/QuantitativeValue');
    // Unknown prefix uses the default fallback URI shape.
    expect($method->invoke($generator, 'foo:Bar'))->toBe('https://credreg.net/unknown/foo/Bar');
    // Only the first colon splits prefix from local; the remainder stays in local.
    expect($method->invoke($generator, 'ceterms:Sub:Term'))->toBe('https://purl.org/ctdl/terms/Sub:Term');
});

it('drops a redundant broader parent, keeping the narrowest', function () {
    $generator = new CredentialRegistryEnumGenerator;
    $method = (new ReflectionClass($generator))->getMethod('subclassOfStringFromFixtureParents');
    $method->setAccessible(true);

    $ancestorsOf = [
        'ceterms:DoctoralDegree' => ['ceterms:Credential', 'ceterms:Degree'],
    ];
    $directParents = ['ceterms:Credential', 'ceterms:DoctoralDegree'];

    expect($method->invoke($generator, $directParents, $ancestorsOf))->toBe('ceterms:DoctoralDegree');

    // Two unrelated parents both stay minimal and are sorted, then comma-joined.
    expect($method->invoke($generator, ['ceterms:Zebra', 'ceterms:Alpha'], []))
        ->toBe('ceterms:Alpha,ceterms:Zebra');
});

it('exposes hierarchy metadata for the generated Credential case', function () {
    expect(enum_exists(CredentialRegistry::class))->toBeTrue();

    $info = CredentialRegistry::CetermsCredential->info();

    expect($info)->not->toBeNull();
    expect($info?->slug)->toBe('Credential');
    expect($info?->uri)->toBe('https://purl.org/ctdl/terms/Credential');
    expect($info?->shorthand_uri)->toBe('ceterms:Credential');
    expect($info?->children)->toContain('ceterms:Badge');
    expect($info?->term_type)->toBe('rdfs:Class');
});

it('lists only minimal-parent direct children for Credential', function () {
    $info = CredentialRegistry::CetermsCredential->info();

    expect($info?->children)->toBe([
        'ceterms:Badge',
        'ceterms:Certificate',
        'ceterms:Certification',
        'ceterms:Degree',
        'ceterms:Diploma',
        'ceterms:License',
        'ceterms:MicroCredential',
        'ceterms:QualityAssuranceCredential',
    ]);
});

it('matches CTDL authority for ProfessionalDoctorate', function () {
    $info = CredentialRegistry::CetermsProfessionalDoctorate->info();

    expect($info)->not->toBeNull();
    expect($info?->slug)->toBe('ProfessionalDoctorate');
    expect($info?->uri)->toBe('https://purl.org/ctdl/terms/ProfessionalDoctorate');
    expect($info?->subclass_of)->toBe('ceterms:DoctoralDegree');
    expect($info?->status)->toBe('vs:stable');
    expect($info?->same_as)->toBe('wd:Q15978610');

    $definition = $info?->definition;
    expect(strtolower($definition ?? ''))->toContain('doctoral degree');
});
