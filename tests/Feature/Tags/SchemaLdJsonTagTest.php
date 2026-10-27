<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use App\Tags\SchemaLdJson;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaLdJson::class);

/**
 * Pad Pest's mutant-child timeout once per process when this file is the
 * ``--mutate`` suite (TestCase boot alone can tip covering re-runs into timeout).
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(12_000_000);
        $padded = true;
    }
});

/**
 * Feature test for the schema_ld_json Antlers tag.
 *
 * @covers \App\Tags\SchemaLdJson
 *
 * @command  php artisan test --compact tests/Feature/Tags/SchemaLdJsonTagTest.php
 */
it('index delegates to schema output service with string slot overrides', function (): void {
    $params = [
        'subject' => 's1',
        'website' => 'w1',
        'webpage' => 'p1',
        'main_content' => 'm1',
    ];

    /** @var SchemaOutputService&MockInterface $mock */
    $mock = Mockery::mock(SchemaOutputService::class);
    $mock->shouldReceive('outputForCurrentContext')
        ->once()
        ->with($params)
        ->andReturn('<script type="application/ld+json">{}</script>');
    app()->instance(SchemaOutputService::class, $mock);

    $tag = new SchemaLdJson;
    $tag->setContext([]);
    $tag->setParameters([
        'subject' => 's1',
        'website' => 'w1',
        'webpage' => 'p1',
        'main_content' => 'm1',
    ]);

    expect($tag->index())->toBe('<script type="application/ld+json">{}</script>');
});

it('omits non-string slot parameters from the service payload', function (): void {
    /** @var SchemaOutputService&MockInterface $mock */
    $mock = Mockery::mock(SchemaOutputService::class);
    $mock->shouldReceive('outputForCurrentContext')
        ->once()
        ->with([])
        ->andReturn('');
    app()->instance(SchemaOutputService::class, $mock);

    $tag = new SchemaLdJson;
    $tag->setContext([]);
    $tag->setParameters([
        'subject' => 1,
        'website' => ['w1'],
        'webpage' => null,
        'main_content' => false,
    ]);

    expect($tag->index())->toBe('');
});
