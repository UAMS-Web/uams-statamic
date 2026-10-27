<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @covers \App\Services\SchemaTemplates\SchemaTemplatesCascadeService::getMultisiteSchemaOverrides
 *
 * Mutation: not recorded. An earlier figure here (100% on App\Services\SchemaTemplates\SchemaTemplatesCascadeService,
 * 5 mutants, --covered-only) named no command or revision and was removed (UAMS-Web/uams-statamic-archive#2915).
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaTemplatesCascadeServiceRequestMemoTest.php
 */
it('returns the same memoized array instance for repeated reads of the same site', function () {
    $service = app(SchemaTemplatesCascadeService::class);
    $method = new ReflectionMethod(
        SchemaTemplatesCascadeService::class,
        'getMultisiteSchemaOverrides'
    );
    $method->setAccessible(true);

    $first = $method->invoke($service, 'default');
    $second = $method->invoke($service, 'default');

    expect($first)->toBeArray();
    expect($first)->toHaveKey('schema_subject');
    expect($first)->toHaveKey('schema_website');

    // Repeated reads for the same site handle reuse one memoized array instance.
    expect($first === $second)->toBeTrue();
});
