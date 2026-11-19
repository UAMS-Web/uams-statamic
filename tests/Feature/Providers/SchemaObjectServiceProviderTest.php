<?php

declare(strict_types=1);

use App\Http\Controllers\CP\SchemaObjectReplicatorDataController;
use App\Providers\SchemaObjectServiceProvider;
use Illuminate\Routing\Route;
use Tests\TestCase;

uses(TestCase::class);

covers(SchemaObjectServiceProvider::class);

/**
 * `SchemaObjectServiceProvider` adds the Control Panel route the `schema_object`
 * fieldtype posts to when it lazy-loads replicator data (UAMS-Web/uams-statamic#872).
 *
 * @command  php artisan test --compact tests/Feature/Providers/SchemaObjectServiceProviderTest.php
 */
it('adds the Control Panel route that lazy-loads replicator data', function (): void {
    /** @var Route|null $route */
    $route = app('router')->getRoutes()->getByName('statamic.cp.schema-object.replicator-data');

    expect($route)->not->toBeNull()
        ->and($route?->uri())->toBe(config()->string('statamic.cp.route').'/schema-object/replicator-data')
        ->and($route?->methods())->toBe(['POST'])
        ->and($route?->getActionName())->toBe(SchemaObjectReplicatorDataController::class);
});
