<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\PodcastEpisodeAtTypeResolver;

/**
 * UAMS-Web/uams-statamic-archive#1487 — the pure @type rule: co-type a CreativeWork node as a PodcastEpisode.
 */
beforeEach(function (): void {
    $this->resolver = new PodcastEpisodeAtTypeResolver;
});

it('appends PodcastEpisode to a declared Article', function () {
    /** @var PodcastEpisodeAtTypeResolver $resolver */
    $resolver = $this->resolver;
    expect($resolver->resolve(['Article']))->toBe(['Article', 'PodcastEpisode']);
});

it('appends PodcastEpisode to the base CreativeWork type', function () {
    /** @var PodcastEpisodeAtTypeResolver $resolver */
    $resolver = $this->resolver;
    expect($resolver->resolve(['CreativeWork']))->toBe(['CreativeWork', 'PodcastEpisode']);
});

it('appends PodcastEpisode to a deeper Article subtype', function () {
    /** @var PodcastEpisodeAtTypeResolver $resolver */
    $resolver = $this->resolver;
    expect($resolver->resolve(['NewsArticle']))->toBe(['NewsArticle', 'PodcastEpisode']);
});

it('preserves multiple declared types and appends PodcastEpisode once', function () {
    /** @var PodcastEpisodeAtTypeResolver $resolver */
    $resolver = $this->resolver;
    expect($resolver->resolve(['Article', 'CreativeWork']))
        ->toBe(['Article', 'CreativeWork', 'PodcastEpisode']);
});

it('returns [] when PodcastEpisode is already declared', function () {
    /** @var PodcastEpisodeAtTypeResolver $resolver */
    $resolver = $this->resolver;
    expect($resolver->resolve(['PodcastEpisode']))->toBe([])
        ->and($resolver->resolve(['Article', 'PodcastEpisode']))->toBe([]);
});

it('returns [] for a non-CreativeWork type so it is not co-typed', function () {
    /** @var PodcastEpisodeAtTypeResolver $resolver */
    $resolver = $this->resolver;
    expect($resolver->resolve(['MedicalClinic']))->toBe([])
        ->and($resolver->resolve(['Person']))->toBe([])
        ->and($resolver->resolve(['Organization']))->toBe([]);
});

it('returns [] for an empty or unknown type list', function () {
    /** @var PodcastEpisodeAtTypeResolver $resolver */
    $resolver = $this->resolver;
    expect($resolver->resolve([]))->toBe([])
        ->and($resolver->resolve(['NotARealSchemaType']))->toBe([]);
});
