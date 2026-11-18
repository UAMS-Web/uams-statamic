<?php

declare(strict_types=1);

use App\Enums\CredentialRegistry;
use App\Services\EnumGeneration\CredentialRegistry\CredentialRegistryEnumGenerator;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Behavior of CredentialRegistryEnumGenerator
 * (app/Services/EnumGeneration/CredentialRegistry/CredentialRegistryEnumGenerator.php):
 * generate() end to end against a stand-in reader (every branch of the tree, YAML and CTDL JSON
 * handling, the URLs read and the file written), its failure paths, the HTTP reader, a few private
 * helpers through reflection, and assertions that the committed App\Enums\CredentialRegistry
 * exposes the expected hierarchy metadata via info().
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

/**
 * Render the CredentialRegistry enum source from two sample terms, no network.
 */
function credentialRegistrySampleSource(): string
{
    $build = new ReflectionMethod(CredentialRegistryEnumGenerator::class, 'buildEnumPhp');

    return mustBeString($build->invoke(
        new CredentialRegistryEnumGenerator,
        ['ceterms:Credential', 'ceterms:Degree'],
        ['ceterms:Degree' => ['ceterms:Credential']],
        ['ceterms:Credential' => ['ceterms:Degree']],
        ['ceterms:Degree' => ['ceterms:Credential', 'ceterms:Thing']],
        ['ceterms:Credential' => ['ceterms:Degree', 'ceterms:Diploma']],
        ['ceterms:Credential' => ['ceterms:name', 'ceterms:ownedBy']],
        [],
        [
            'ceterms:Credential' => ['rdfs:label' => "Kirk's Credential", 'rdfs:comment' => 'Ends in a backslash\\'],
            'ceterms:Degree' => null,
        ]
    ), 'the generated CredentialRegistry source');
}

it('renders output that Pint reports clean with no separate pass (#721)', function (): void {
    $php = credentialRegistrySampleSource();

    $root = dirname(__DIR__, 4);
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'credential-enum-'.bin2hex(random_bytes(6)).'.php';
    file_put_contents($path, $php);

    try {
        $process = new Process([
            PHP_BINARY,
            $root.'/vendor/bin/pint',
            '--test',
            '--config',
            $root.'/pint.json',
            $path,
        ]);
        $process->run();
    } finally {
        @unlink($path);
    }

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput())
        ->and($php)->toContain("ancestors: ['ceterms:Credential', 'ceterms:Thing']");
});

it('renders the info() method exactly as the committed enum carries it (#721)', function (): void {
    $marker = "\n    /**\n     * CredentialRegistryTypeInfo attribute for this case";
    $committed = mustBeString(file_get_contents(dirname(__DIR__, 4).'/app/Enums/CredentialRegistry.php'), 'the committed enum');
    $generated = credentialRegistrySampleSource();

    expect(substr_count($committed, $marker))->toBe(1)
        ->and(substr_count($generated, $marker))->toBe(1)
        ->and(substr($generated, (int) strpos($generated, $marker)))
        ->toBe(substr($committed, (int) strpos($committed, $marker)));
});

/**
 * A stand-in for the registry fixtures and credreg.net term documents, covering each branch of the generator.
 *
 * The tree carries a redundant parent (`Credential` above `DoctoralDegree`), a repeated edge
 * (`Degree` to `DoctoralDegree` twice), a leaf given as a scalar, three unrelated parents for `Badge`,
 * case names that collide (`Foo-Bar`, `FooBar`, `Foo_Bar`) or are reserved (`Class`, `class`), and
 * prefixes with and without a term document. Each term document exercises one way CTDL JSON can be
 * present, partial or unusable.
 *
 * @param  list<string>  $requested  Receives every URL the generator reads, in order
 * @return Closure(string): ?string
 */
function credentialRegistryFixtureReader(array &$requested): Closure
{
    $tree = [
        'ceterms:Zeta' => ['ceterms:Badge' => []],
        'ceterms:Credential' => [
            'ceterms:Degree' => ['ceterms:DoctoralDegree' => ['ceterms:ProfessionalDoctorate' => []]],
            'ceterms:DoctoralDegree' => [],
            'ceterms:Badge' => [],
            'ceterms:Apprenticeship' => [],
        ],
        'ceterms:Degree' => ['ceterms:DoctoralDegree' => []],
        'schema:Thing' => ['ceterms:Badge' => 'leaf', 'ceterms:Leaf' => []],
        'ceterms:Gamma' => [],
        'ceterms:Sub:Term' => [],
        'ceterms:Spaced ' => [],
        'asn:Statement' => [],
        'skos:Concept' => [],
        'qdata:DataSet' => [],
        'ceasn:Competency' => [],
        'ceterms:FooBar' => [],
        'ceterms:Foo-Bar' => [],
        'ceterms:Foo_Bar' => [],
        'class' => [],
        'Class' => [],
    ];

    $yaml = "---\n- :name: Header only\n  :path: x\n- :name: Owned\n  :property_path: \"> ceterms:ownedBy\"\n  :subject_types:\n  - ceterms:Degree\n  - ceterms:Credential\n  :target_types:\n  - schema:Thing\n- :name: Subject only\n  :property_path: ceterms:keyword\n  :subject_types:\n  - ceterms:Badge\n  - not a term\n- :name: No path match\n  :property_path: \"plain\"\n  :subject_types:\n  - ceterms:Badge\n- :name: Name\n  :property_path: '<ceterms:name'\n  :subject_types:\n  - ceterms:Credential\n  - ceterms:Credential\n  :target_types:\n  - schema:Thing\n  - schema:Thing";

    $graphs = [
        'Credential' => ['@graph' => [['rdfs:label' => ['es' => 'Spanish label', 'en-US' => "Credential's \\ (en)"], 'rdfs:comment' => 'A credential.', 'dct:description' => ['es' => 'Summary in Spanish', 'fr' => 'x'], 'vann:usageNote' => [5, 'Use with care.'], 'vs:term_status' => 'vs:stable', 'owl:equivalentClass' => ['wd:Q1'], '@type' => 'rdfs:Class']]],
        'ProfessionalDoctorate' => ['@graph' => [['rdfs:subClassOf' => 'ceterms:Degree', '@type' => ['owl:Class'], 'rdfs:label' => '', 'owl:equivalentClass' => [5]]]],
        'DoctoralDegree' => ['@graph' => [['rdfs:subClassOf' => 5, 'owl:equivalentClass' => 'wd:Q2', '@type' => '', 'vs:term_status' => 7]]],
        'Degree' => ['@graph' => [['rdfs:subClassOf' => [5], '@type' => [5], 'rdfs:label' => ['de' => 7], 'rdfs:comment' => 5, 'dct:description' => ['en-US' => 5, 'en' => 'Degree text'], 'owl:equivalentClass' => '']]],
        'Badge' => ['@graph' => [['rdfs:subClassOf' => ['']]]],
        'Apprenticeship' => ['@graph' => [['rdfs:subClassOf' => '']]],
        'Leaf' => ['@graph' => [['rdfs:subClassOf' => ['ceterms:Credential', 5, '', 'ceterms:Credential']]]],
        'Gamma' => ['other' => 1],
        'Foo_Bar' => ['@graph' => 'x'],
        'Thing' => ['@graph' => []],
        'DataSet' => ['@graph' => ['not a node']],
        'Competency' => 'a string',
    ];

    return static function (string $url) use (&$requested, $tree, $yaml, $graphs): ?string {
        $requested[] = $url;

        if (str_contains($url, 'subclasses_map')) {
            return json_encode($tree, JSON_THROW_ON_ERROR);
        }

        if (str_contains($url, 'description_set')) {
            return $yaml;
        }

        if (preg_match('#/terms/([^/]+)/json#', $url, $m) !== 1) {
            return null;
        }

        if ($m[1] === 'FooBar') {
            return 'not json';
        }

        return isset($graphs[$m[1]]) ? json_encode($graphs[$m[1]], JSON_THROW_ON_ERROR) : null;
    };
}

it('writes the enum from the fixtures, preferring CTDL JSON over fixture parents', function (): void {
    $requested = [];
    $written = (object) ['path' => null, 'php' => null];
    File::partialMock()->shouldReceive('ensureDirectoryExists')->once()->with(base_path('app/Enums'));
    File::partialMock()->shouldReceive('put')->once()->andReturnUsing(static function (string $path, string $php) use ($written): int {
        $written->path = $path;
        $written->php = $php;

        return \strlen($php);
    });

    $relative = (new CredentialRegistryEnumGenerator(credentialRegistryFixtureReader($requested)))->generate();

    $expected = str_replace('@DATE@', gmdate('Y-m-d'), <<<'EXPECTED'
<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Attributes\CredentialRegistryTypeInfo;

/**
 * Credential Registry / CTDL class terms from CredentialEngine fixtures.
 *
 * Hierarchy is derived from fixtures/subclasses_map.json. Property domain and
 * range hints come from fixtures/description_set_item_maps.yml. Labels,
 * definitions, and subclass_of are merged from credreg.net CTDL JSON when
 * available. Empty strings mark fields not present in those sources.
 *
 * Generated on @DATE@ using
 * App\Services\EnumGeneration\CredentialRegistry\CredentialRegistryEnumGenerator.
 *
 * @see https://github.com/CredentialEngine/CredentialRegistry
 */
enum CredentialRegistry: string
{
    #[CredentialRegistryTypeInfo(slug: 'Class', uri: 'Class', shorthand_uri: 'Class', label: 'Class', definition: '', comment: '', usage_note: '', term_type: 'rdfs:Class', status: '', same_as: '', subclass_of: '', children: [], ancestors: [], descendants: [], property_names: [], in_range_of: [])]
    case ClassTerm = 'Class';
    #[CredentialRegistryTypeInfo(slug: 'Statement', uri: 'http://purl.org/ASN/schema/core#Statement', shorthand_uri: 'asn:Statement', label: 'Statement', definition: '', comment: '', usage_note: '', term_type: 'rdfs:Class', status: '', same_as: '', subclass_of: '', children: [], ancestors: [], descendants: [], property_names: [], in_range_of: [])]
    case AsnStatement = 'asn:Statement';
    #[CredentialRegistryTypeInfo(slug: 'Competency', uri: 'https://purl.org/ctdlasn/terms/Competency', shorthand_uri: 'ceasn:Competency', label: 'Competency', definition: '', comment: '', usage_note: '', term_type: 'rdfs:Class', status: '', same_as: '', subclass_of: '', children: [], ancestors: [], descendants: [], property_names: [], in_range_of: [])]
    case CeasnCompetency = 'ceasn:Competency';
    #[CredentialRegistryTypeInfo(slug: 'Apprenticeship', uri: 'https://purl.org/ctdl/terms/Apprenticeship', shorthand_uri: 'ceterms:Apprenticeship', label: 'Apprenticeship', definition: '', comment: '', usage_note: '', term_type: 'rdfs:Class', status: '', same_as: '', subclass_of: 'ceterms:Credential', children: [], ancestors: ['ceterms:Credential'], descendants: [], property_names: [], in_range_of: [])]
    case CetermsApprenticeship = 'ceterms:Apprenticeship';
    #[CredentialRegistryTypeInfo(slug: 'Badge', uri: 'https://purl.org/ctdl/terms/Badge', shorthand_uri: 'ceterms:Badge', label: 'Badge', definition: '', comment: '', usage_note: '', term_type: 'rdfs:Class', status: '', same_as: '', subclass_of: 'ceterms:Credential,ceterms:Zeta,schema:Thing', children: [], ancestors: ['ceterms:Credential', 'ceterms:Zeta', 'schema:Thing'], descendants: [], property_names: ['ceterms:keyword'], in_range_of: [])]
    case CetermsBadge = 'ceterms:Badge';
    #[CredentialRegistryTypeInfo(slug: 'Credential', uri: 'https://purl.org/ctdl/terms/Credential', shorthand_uri: 'ceterms:Credential', label: 'Credential\'s \\ (en)', definition: 'A credential.', comment: 'Summary in Spanish', usage_note: 'Use with care.', term_type: 'rdfs:Class', status: 'vs:stable', same_as: 'wd:Q1', subclass_of: '', children: ['ceterms:Apprenticeship', 'ceterms:Badge', 'ceterms:Degree', 'ceterms:Leaf'], ancestors: [], descendants: ['ceterms:Apprenticeship', 'ceterms:Badge', 'ceterms:Degree', 'ceterms:DoctoralDegree', 'ceterms:ProfessionalDoctorate'], property_names: ['ceterms:name', 'ceterms:ownedBy'], in_range_of: [])]
    case CetermsCredential = 'ceterms:Credential';
    #[CredentialRegistryTypeInfo(slug: 'Degree', uri: 'https://purl.org/ctdl/terms/Degree', shorthand_uri: 'ceterms:Degree', label: 'Degree', definition: '', comment: 'Degree text', usage_note: '', term_type: 'rdfs:Class', status: '', same_as: '', subclass_of: 'ceterms:Credential', children: ['ceterms:DoctoralDegree', 'ceterms:ProfessionalDoctorate'], ancestors: ['ceterms:Credential'], descendants: ['ceterms:DoctoralDegree', 'ceterms:ProfessionalDoctorate'], property_names: ['ceterms:ownedBy'], in_range_of: [])]
    case CetermsDegree = 'ceterms:Degree';
    #[CredentialRegistryTypeInfo(slug: 'DoctoralDegree', uri: 'https://purl.org/ctdl/terms/DoctoralDegree', shorthand_uri: 'ceterms:DoctoralDegree', label: 'Doctoral Degree', definition: '', comment: '', usage_note: '', term_type: 'rdfs:Class', status: '', same_as: 'wd:Q2', subclass_of: 'ceterms:Degree', children: [], ancestors: ['ceterms:Credential', 'ceterms:Degree'], descendants: ['ceterms:ProfessionalDoctorate'], property_names: [], in_range_of: [])]
    case CetermsDoctoralDegree = 'ceterms:DoctoralDegree';
    #[CredentialRegistryTypeInfo(slug: 'Foo-Bar', uri: 'https://purl.org/ctdl/terms/Foo-Bar', shorthand_uri: 'ceterms:Foo-Bar', label: 'Foo- Bar', definition: '', comment: '', usage_note: '', term_type: 'rdfs:Class', status: '', same_as: '', subclass_of: '', children: [], ancestors: [], descendants: [], property_names: [], in_range_of: [])]
    case CetermsFooBar = 'ceterms:Foo-Bar';
    #[CredentialRegistryTypeInfo(slug: 'FooBar', uri: 'https://purl.org/ctdl/terms/FooBar', shorthand_uri: 'ceterms:FooBar', label: 'Foo Bar', definition: '', comment: '', usage_note: '', term_type: 'rdfs:Class', status: '', same_as: '', subclass_of: '', children: [], ancestors: [], descendants: [], property_names: [], in_range_of: [])]
    case CetermsFooBar2 = 'ceterms:FooBar';
    #[CredentialRegistryTypeInfo(slug: 'Foo_Bar', uri: 'https://purl.org/ctdl/terms/Foo_Bar', shorthand_uri: 'ceterms:Foo_Bar', label: 'Foo_ Bar', definition: '', comment: '', usage_note: '', term_type: 'rdfs:Class', status: '', same_as: '', subclass_of: '', children: [], ancestors: [], descendants: [], property_names: [], in_range_of: [])]
    case CetermsFooBar3 = 'ceterms:Foo_Bar';
    #[CredentialRegistryTypeInfo(slug: 'Gamma', uri: 'https://purl.org/ctdl/terms/Gamma', shorthand_uri: 'ceterms:Gamma', label: 'Gamma', definition: '', comment: '', usage_note: '', term_type: 'rdfs:Class', status: '', same_as: '', subclass_of: '', children: [], ancestors: [], descendants: [], property_names: [], in_range_of: [])]
    case CetermsGamma = 'ceterms:Gamma';
    #[CredentialRegistryTypeInfo(slug: 'Leaf', uri: 'https://purl.org/ctdl/terms/Leaf', shorthand_uri: 'ceterms:Leaf', label: 'Leaf', definition: '', comment: '', usage_note: '', term_type: 'rdfs:Class', status: '', same_as: '', subclass_of: 'ceterms:Credential', children: [], ancestors: ['schema:Thing'], descendants: [], property_names: [], in_range_of: [])]
    case CetermsLeaf = 'ceterms:Leaf';
    #[CredentialRegistryTypeInfo(slug: 'ProfessionalDoctorate', uri: 'https://purl.org/ctdl/terms/ProfessionalDoctorate', shorthand_uri: 'ceterms:ProfessionalDoctorate', label: 'Professional Doctorate', definition: '', comment: '', usage_note: '', term_type: 'owl:Class', status: '', same_as: '', subclass_of: 'ceterms:Degree', children: [], ancestors: ['ceterms:Credential', 'ceterms:Degree', 'ceterms:DoctoralDegree'], descendants: [], property_names: [], in_range_of: [])]
    case CetermsProfessionalDoctorate = 'ceterms:ProfessionalDoctorate';
    #[CredentialRegistryTypeInfo(slug: 'Spaced ', uri: 'https://purl.org/ctdl/terms/Spaced ', shorthand_uri: 'ceterms:Spaced ', label: 'Spaced', definition: '', comment: '', usage_note: '', term_type: 'rdfs:Class', status: '', same_as: '', subclass_of: '', children: [], ancestors: [], descendants: [], property_names: [], in_range_of: [])]
    case CetermsSpaced = 'ceterms:Spaced ';
    #[CredentialRegistryTypeInfo(slug: 'Sub:Term', uri: 'https://purl.org/ctdl/terms/Sub:Term', shorthand_uri: 'ceterms:Sub:Term', label: 'Sub: Term', definition: '', comment: '', usage_note: '', term_type: 'rdfs:Class', status: '', same_as: '', subclass_of: '', children: [], ancestors: [], descendants: [], property_names: [], in_range_of: [])]
    case CetermsSubTerm = 'ceterms:Sub:Term';
    #[CredentialRegistryTypeInfo(slug: 'Zeta', uri: 'https://purl.org/ctdl/terms/Zeta', shorthand_uri: 'ceterms:Zeta', label: 'Zeta', definition: '', comment: '', usage_note: '', term_type: 'rdfs:Class', status: '', same_as: '', subclass_of: '', children: ['ceterms:Badge'], ancestors: [], descendants: ['ceterms:Badge'], property_names: [], in_range_of: [])]
    case CetermsZeta = 'ceterms:Zeta';
    #[CredentialRegistryTypeInfo(slug: 'class', uri: 'class', shorthand_uri: 'class', label: 'Class', definition: '', comment: '', usage_note: '', term_type: 'rdfs:Class', status: '', same_as: '', subclass_of: '', children: [], ancestors: [], descendants: [], property_names: [], in_range_of: [])]
    case ClassTerm2 = 'class';
    #[CredentialRegistryTypeInfo(slug: 'DataSet', uri: 'https://credreg.net/qdata/terms/DataSet', shorthand_uri: 'qdata:DataSet', label: 'Data Set', definition: '', comment: '', usage_note: '', term_type: 'rdfs:Class', status: '', same_as: '', subclass_of: '', children: [], ancestors: [], descendants: [], property_names: [], in_range_of: [])]
    case QdataDataSet = 'qdata:DataSet';
    #[CredentialRegistryTypeInfo(slug: 'Thing', uri: 'https://schema.org/Thing', shorthand_uri: 'schema:Thing', label: 'Thing', definition: '', comment: '', usage_note: '', term_type: 'rdfs:Class', status: '', same_as: '', subclass_of: '', children: ['ceterms:Badge'], ancestors: [], descendants: ['ceterms:Badge', 'ceterms:Leaf'], property_names: [], in_range_of: ['ceterms:name', 'ceterms:ownedBy'])]
    case SchemaThing = 'schema:Thing';
    #[CredentialRegistryTypeInfo(slug: 'Concept', uri: 'http://www.w3.org/2004/02/skos/core#Concept', shorthand_uri: 'skos:Concept', label: 'Concept', definition: '', comment: '', usage_note: '', term_type: 'rdfs:Class', status: '', same_as: '', subclass_of: '', children: [], ancestors: [], descendants: [], property_names: [], in_range_of: [])]
    case SkosConcept = 'skos:Concept';

    /**
     * CredentialRegistryTypeInfo attribute for this case (via reflection).
     */
    public function info(): ?CredentialRegistryTypeInfo
    {
        $ref = (new \ReflectionEnum(self::class))->getCase($this->name);
        $attrs = $ref->getAttributes(CredentialRegistryTypeInfo::class);

        return isset($attrs[0]) ? $attrs[0]->newInstance() : null;
    }
}

EXPECTED);

    expect($relative)->toBe('app/Enums/CredentialRegistry.php')
        ->and($written->path)->toBe(base_path('app/Enums/CredentialRegistry.php'))
        ->and($written->php)->toBe($expected)
        ->and($requested)->toBe([
            'https://raw.githubusercontent.com/CredentialEngine/CredentialRegistry/refs/heads/master/fixtures/subclasses_map.json',
            'https://credreg.net/ctdlasn/terms/Competency/json',
            'https://credreg.net/ctdl/terms/Apprenticeship/json?releaseID=20260327',
            'https://credreg.net/ctdl/terms/Badge/json?releaseID=20260327',
            'https://credreg.net/ctdl/terms/Credential/json?releaseID=20260327',
            'https://credreg.net/ctdl/terms/Degree/json?releaseID=20260327',
            'https://credreg.net/ctdl/terms/DoctoralDegree/json?releaseID=20260327',
            'https://credreg.net/ctdl/terms/Foo-Bar/json?releaseID=20260327',
            'https://credreg.net/ctdl/terms/FooBar/json?releaseID=20260327',
            'https://credreg.net/ctdl/terms/Foo_Bar/json?releaseID=20260327',
            'https://credreg.net/ctdl/terms/Gamma/json?releaseID=20260327',
            'https://credreg.net/ctdl/terms/Leaf/json?releaseID=20260327',
            'https://credreg.net/ctdl/terms/ProfessionalDoctorate/json?releaseID=20260327',
            'https://credreg.net/ctdl/terms/Spaced /json?releaseID=20260327',
            'https://credreg.net/ctdl/terms/Sub:Term/json?releaseID=20260327',
            'https://credreg.net/ctdl/terms/Zeta/json?releaseID=20260327',
            'https://credreg.net/qdata/terms/DataSet/json',
            'https://credreg.net/ctdl/terms/Thing/json?releaseID=20260327',
            'https://raw.githubusercontent.com/CredentialEngine/CredentialRegistry/refs/heads/master/fixtures/description_set_item_maps.yml',
        ]);
});

it('stops before writing when a registry fixture cannot be read', function (string $missing, string $url): void {
    File::partialMock()->shouldReceive('put')->never();
    $requested = [];
    $reader = credentialRegistryFixtureReader($requested);

    $generator = new CredentialRegistryEnumGenerator(static fn (string $read): ?string => str_contains($read, $missing) ? null : $reader($read));

    expect(fn () => $generator->generate())->toThrow(RuntimeException::class, 'Failed to fetch: '.$url);
})->with([
    'subclass tree' => ['subclasses_map', 'https://raw.githubusercontent.com/CredentialEngine/CredentialRegistry/refs/heads/master/fixtures/subclasses_map.json'],
    'description set maps' => ['description_set', 'https://raw.githubusercontent.com/CredentialEngine/CredentialRegistry/refs/heads/master/fixtures/description_set_item_maps.yml'],
]);

it('rejects a subclass tree that is not a JSON object', function (): void {
    File::partialMock()->shouldReceive('put')->never();

    expect(fn () => (new CredentialRegistryEnumGenerator(static fn (string $url): string => '"a string"'))->generate())
        ->toThrow(RuntimeException::class, 'Invalid subclasses_map.json: expected object');
});

it('rejects a subclass tree that is not JSON', function (): void {
    File::partialMock()->shouldReceive('put')->never();

    expect(fn () => (new CredentialRegistryEnumGenerator(static fn (string $url): string => '{'))->generate())
        ->toThrow(JsonException::class);
});

it('reads over HTTP(S) by default, returning the body or null when the read fails', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'credential-read-');
    file_put_contents($path, 'fixture body');

    try {
        $read = (new ReflectionProperty(CredentialRegistryEnumGenerator::class, 'readUrl'))->getValue(new CredentialRegistryEnumGenerator);

        expect($read)->toBeInstanceOf(Closure::class);

        /** @var Closure(string): ?string $read */
        expect($read($path))->toBe('fixture body')
            ->and($read($path.'.missing'))->toBeNull();
    } finally {
        @unlink($path);
    }
});

it('reads with a two-minute timeout and follows redirects over both schemes', function (): void {
    $options = (new ReflectionMethod(CredentialRegistryEnumGenerator::class, 'httpContextOptions'))->invoke(null);

    expect($options)->toBe([
        'http' => ['timeout' => 120, 'follow_location' => true],
        'https' => ['timeout' => 120, 'follow_location' => true],
    ]);
});
