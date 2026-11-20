<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Data;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaOutputService::class);

/**
 * Pins how {@see SchemaOutputService} reads the variables a schema Antlers
 * template refers to: the placeholders `fillMissingSimpleTemplateVariablesWithNull()`
 * adds for variables the context lacks, the leaked relationship field values it
 * clears, and the identifier set `antlersTemplateReferencedIdentifierSet()` builds.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaOutputServiceTemplateVariablesTest.php
 */

/**
 * Call a non-public method of the service.
 */
function sosVarsCall(string $method, mixed ...$args): mixed
{
    return (new ReflectionMethod(SchemaOutputService::class, $method))->invoke(app(SchemaOutputService::class), ...$args);
}

/**
 * Fill the missing variables of a template into a context.
 *
 * @param  array<array-key, mixed>  $context
 * @param  list<string>  $termsHandles
 * @return array<array-key, mixed>
 */
function sosVarsFill(string $template, array $context = [], array $termsHandles = []): array
{
    return mustBeArray(sosVarsCall('fillMissingSimpleTemplateVariablesWithNull', $template, $context, $termsHandles), 'the filled context');
}

/**
 * Make `Data::find()` answer one entity id with the given entity.
 */
function sosVarsFindsEntity(string $id, mixed $entity): void
{
    Data::partialMock()->shouldReceive('find')->once()->with($id)->andReturn($entity);
}

/**
 * An entry double whose stored value for every field is the given value.
 */
function sosVarsEntry(mixed $stored): EntryModel&MockInterface
{
    /** @var EntryModel&MockInterface $entry */
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('get')->andReturn($stored);

    return $entry;
}

it('adds a placeholder of the right kind for each variable the context lacks', function (): void {
    $template = '{{ caption | upper }}{{ title }}{{ name.first }}{{ if email }}x{{ /if }}'
        .'{{ schema:relationship current_field="credentials | other||" }}';

    expect(sosVarsFill($template, ['unrelated' => 'x']))->toBe([
        'unrelated' => 'x',
        'caption' => null,
        'title' => null,
        'name' => '',
        'email' => null,
        'credentials' => false,
        'other' => false,
    ]);
});

it('keeps a variable the context already holds', function (): void {
    expect(sosVarsFill('{{ title }}{{ name.first }}', ['title' => 'Kept', 'name' => ['first' => 'A']]))
        ->toBe(['title' => 'Kept', 'name' => ['first' => 'A']]);
});

it('adds no placeholder for an Antlers keyword, and still fills the variable after it', function (string $keyword): void {
    expect(sosVarsFill('{{ '.$keyword.' }}{{ title }}'))->toBe(['title' => null]);
})->with(['if', 'elseif', 'else', 'unless', 'switch', 'case', 'default', 'true', 'false', 'null', 'and', 'or', 'not']);

it('clears a relationship field the context entry does not store', function (mixed $stored, mixed $expected): void {
    sosVarsFindsEntity('entry-1', sosVarsEntry($stored));

    $out = sosVarsFill('{{ schema:relationship current_field="credentials" }}', ['id' => 'entry-1', 'credentials' => 'leaked']);

    expect($out['credentials'])->toBe($expected);
})->with([
    'null' => [null, false],
    'an empty list' => [[], false],
    'an empty string' => ['', false],
    'a stored value' => ['stored', 'leaked'],
]);

it('keeps a relationship field when the context entity is not an entry', function (mixed $entity): void {
    sosVarsFindsEntity('entry-1', $entity);

    $out = sosVarsFill('{{ schema:relationship current_field="credentials" }}', ['id' => 'entry-1', 'credentials' => 'leaked']);

    expect($out['credentials'])->toBe('leaked');
})->with([
    'nothing' => [null],
    'a localized term' => fn (): array => [Mockery::mock(LocalizedTerm::class)],
]);

it('looks up no context entity without a string id', function (mixed $id): void {
    Data::partialMock()->shouldReceive('find')->never();

    $out = sosVarsFill('{{ schema:relationship current_field="credentials" }}', ['id' => $id, 'credentials' => 'leaked']);

    expect($out['credentials'])->toBe('leaked');
})->with([
    'an empty string' => [''],
    'a number' => [5],
]);

it('keeps a terms field handle the template pairs, matched strictly', function (): void {
    sosVarsFindsEntity('entry-1', sosVarsEntry(null));

    $out = sosVarsFill('{{ schema:relationship current_field="10|organization" }}', ['id' => 'entry-1', '10' => 'leaked', 'organization' => 'kept'], ['1e1', 'organization']);

    expect($out[10])->toBeFalse();
    expect($out['organization'])->toBe('kept');
});

it('collects the identifiers a template reads', function (string $template, array $expected): void {
    expect(sosVarsCall('antlersTemplateReferencedIdentifierSet', $template))->toBe($expected);
})->with([
    'a variable' => ['{{ a }}', ['a' => true]],
    'a piped variable' => ['{{ a | upper }}', ['a' => true]],
    'a closing tag' => ['{{ /b }}', ['b' => true]],
    'an if' => ['{{ if c }}', ['c' => true]],
    'an unless' => ['{{ unless c }}', ['c' => true]],
    'an elseif' => ['{{ elseif d }}', ['d' => true]],
    'an else_if' => ['{{ else_if d }}', ['d' => true]],
    'nothing' => ['plain text', []],
]);

it('collects no Antlers keyword as an identifier', function (string $keyword): void {
    expect(sosVarsCall('antlersTemplateReferencedIdentifierSet', '{{ '.$keyword.' }}{{ a }}'))->toBe(['a' => true]);
})->with(['true', 'false', 'null', 'if', 'elseif', 'else_if', 'else', 'unless', 'endif', 'endunless']);

it('clears only relationship field handles, never another variable the context holds', function (): void {
    sosVarsFindsEntity('entry-1', sosVarsEntry(null));

    $out = sosVarsFill('{{ title }}{{ schema:relationship current_field="credentials" }}', ['id' => 'entry-1', 'title' => 'Kept', 'credentials' => 'leaked']);

    expect($out['title'])->toBe('Kept')
        ->and($out['credentials'])->toBeFalse();
});
