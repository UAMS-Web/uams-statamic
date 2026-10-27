<?php

declare(strict_types=1);

use Mockery\MockInterface;
use Statamic\Contracts\Taxonomies\TermRepository;

if (! function_exists('mockTermRepository')) {
    /**
     * Bind a Mockery TermRepository into the container so the Statamic Term facade
     * resolves the mock. Shared by the taxonomy-term validation-rule tests
     * (credential / clinical / specialty / board rules) that stub `Term::find()`.
     *
     * @return TermRepository&MockInterface
     */
    function mockTermRepository(): TermRepository
    {
        $repository = Mockery::mock(TermRepository::class);
        app()->instance(TermRepository::class, $repository);

        return $repository;
    }
}
