<?php

declare(strict_types=1);

use App\Providers\KnowledgeBaseServiceProvider;
use App\Services\KnowledgeBase\KnowledgeBaseMountUriRefresher;
use Tests\TestCase;

uses(TestCase::class);

covers(KnowledgeBaseServiceProvider::class);

/**
 * `KnowledgeBaseServiceProvider` shares one mounted-knowledge-base URI refresher
 * per application, so its re-entrancy guard spans every listener call and the
 * entry save hook (UAMS-Web/uams-statamic#871, UAMS-Web/uams-statamic-archive#826).
 *
 * @command  php artisan test --compact tests/Feature/Providers/KnowledgeBaseServiceProviderTest.php
 */
it('shares one URI refresher across every resolution', function (): void {
    $first = $this->app->make(KnowledgeBaseMountUriRefresher::class);

    expect($this->app->isShared(KnowledgeBaseMountUriRefresher::class))->toBeTrue()
        ->and($this->app->make(KnowledgeBaseMountUriRefresher::class))->toBe($first);
});
