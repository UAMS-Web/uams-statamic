<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\PodcastEpisodeAtTypeResolver;
use Tests\TestCase;

uses(TestCase::class);

covers(PodcastEpisodeAtTypeResolver::class);

/**
 * Narrow mutate coverage for PodcastEpisodeAtTypeResolver.
 *
 * @command  php artisan test --compact tests/Unit/Services/SchemaTemplates/PodcastEpisodeAtTypeResolverMutationTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

it('appends PodcastEpisode to CreativeWork nodes and subtypes', function (): void {
    $resolver = new PodcastEpisodeAtTypeResolver;

    expect($resolver->resolve(['Article']))->toBe(['Article', 'PodcastEpisode'])
        ->and($resolver->resolve(['CreativeWork']))->toBe(['CreativeWork', 'PodcastEpisode'])
        ->and($resolver->resolve(['NewsArticle']))->toBe(['NewsArticle', 'PodcastEpisode'])
        ->and($resolver->resolve(['Article', 'CreativeWork']))
        ->toBe(['Article', 'CreativeWork', 'PodcastEpisode']);
});

it('returns an empty list when PodcastEpisode is already declared', function (): void {
    $resolver = new PodcastEpisodeAtTypeResolver;

    expect($resolver->resolve(['PodcastEpisode']))->toBe([])
        ->and($resolver->resolve(['Article', 'PodcastEpisode']))->toBe([]);
});

it('returns an empty list for non-CreativeWork, empty, or unknown types', function (): void {
    $resolver = new PodcastEpisodeAtTypeResolver;

    expect($resolver->resolve(['MedicalClinic']))->toBe([])
        ->and($resolver->resolve(['Person']))->toBe([])
        ->and($resolver->resolve(['Organization']))->toBe([])
        ->and($resolver->resolve([]))->toBe([])
        ->and($resolver->resolve(['NotARealSchemaType']))->toBe([]);
});
