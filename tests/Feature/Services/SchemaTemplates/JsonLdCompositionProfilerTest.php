<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\JsonLdCompositionProfiler;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

covers(JsonLdCompositionProfiler::class);

/**
 * Pins JsonLdCompositionProfiler: its config reads, the request bag each
 * recorder writes, and the log lines `finalize()` emits.
 *
 * Every log call is captured and compared whole with `toBe`, so a renamed
 * key, a dropped counter, a missing `round()` or an extra line fails. Each
 * recorder is also run with profiling disabled and with no `request` bound,
 * the two cases in which every recorder writes nothing. With no bag on the
 * request, `recordBucketSample()` and `recordAntlersParseSample()` write
 * nothing, while `recordAugmentUncachedSample()` starts a bag of its own.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/JsonLdCompositionProfilerTest.php
 */

/**
 * The request attribute the profiler reads and writes.
 */
const PROFILER_BAG = JsonLdCompositionProfiler::REQUEST_JSON_LD_COMPOSITION_PROFILE;

/**
 * Capture every `Log::info()` call as a `[message, context]` pair.
 *
 * @return ArrayObject<int, list<mixed>> Filled as calls arrive.
 */
function captureProfilerLogs(): ArrayObject
{
    /** @var ArrayObject<int, list<mixed>> $logged */
    $logged = new ArrayObject;

    Log::shouldReceive('info')->andReturnUsing(
        /**
         * @param  array<string, mixed>  $context  The log context.
         */
        static function (string $message, array $context) use ($logged): void {
            $logged[] = [$message, $context];
        }
    );

    return $logged;
}

/**
 * Read the profile bag off the current request.
 */
function profilerBag(): mixed
{
    return request()->attributes->get(PROFILER_BAG);
}

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    Config::set('schema_templates.json_ld_compose_profile.enabled', true);
    Config::set('schema_templates.json_ld_compose_profile.top_n', 10);
    request()->attributes->remove(PROFILER_BAG);

    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

describe('enabled()', function (): void {
    it('follows the config flag, cast to bool', function (mixed $value, bool $expected): void {
        Config::set('schema_templates.json_ld_compose_profile.enabled', $value);

        expect((new JsonLdCompositionProfiler)->enabled())->toBe($expected);
    })->with([
        'true' => [true, true],
        'false' => [false, false],
        'string 1' => ['1', true],
        'string 0' => ['0', false],
        'null' => [null, false],
    ]);

    it('is off when the key is absent', function (): void {
        Config::set('schema_templates.json_ld_compose_profile', []);

        expect((new JsonLdCompositionProfiler)->enabled())->toBeFalse();
    });
});

describe('topNLimit()', function (): void {
    it('reads the configured limit, never below 1', function (mixed $value, int $expected): void {
        Config::set('schema_templates.json_ld_compose_profile.top_n', $value);

        expect((new JsonLdCompositionProfiler)->topNLimit())->toBe($expected);
    })->with([
        'int' => [3, 3],
        'one' => [1, 1],
        'numeric string' => ['7', 7],
        'zero' => [0, 1],
        'negative' => [-4, 1],
        'null' => [null, 1],
        'true' => [true, 1],
        'array' => [[5], 10],
    ]);

    it('defaults to 10 when the key is absent', function (): void {
        Config::set('schema_templates.json_ld_compose_profile', ['enabled' => true]);

        expect((new JsonLdCompositionProfiler)->topNLimit())->toBe(10);
    });
});

describe('reset()', function (): void {
    it('puts every counter on the request at zero', function (): void {
        request()->attributes->set(PROFILER_BAG, ['dedupe_calls' => 9]);

        (new JsonLdCompositionProfiler)->reset();

        expect(profilerBag())->toBe([
            'augment_uncached_calls' => 0,
            'augment_uncached_ms' => 0.0,
            'schema_object_node_build_calls' => 0,
            'schema_object_node_build_ms' => 0.0,
            'antlers_parse_calls' => 0,
            'antlers_parse_ms' => 0.0,
            'dedupe_calls' => 0,
            'dedupe_ms' => 0.0,
            'inflate_calls' => 0,
            'inflate_ms' => 0.0,
            'json_encode_calls' => 0,
            'json_encode_ms' => 0.0,
            'relationship_resolve_calls' => 0,
            'relationship_resolve_ms' => 0.0,
            'relationship_collect_direct_calls' => 0,
            'relationship_collect_direct_ms' => 0.0,
            'relationship_collect_secondary_calls' => 0,
            'relationship_collect_secondary_ms' => 0.0,
            'relationship_collect_tertiary_calls' => 0,
            'relationship_collect_tertiary_ms' => 0.0,
            'relationship_collect_global_calls' => 0,
            'relationship_collect_global_ms' => 0.0,
            'relationship_targets_direct' => 0,
            'relationship_targets_secondary' => 0,
            'relationship_targets_tertiary' => 0,
            'relationship_targets_global' => 0,
            'relationship_compact_ref_hits' => 0,
            'relationship_full_build_calls' => 0,
            'relationship_full_build_ms' => 0.0,
            'relationship_full_build_top' => [],
            'antlers_parse_top' => [],
        ]);
    });

    it('leaves the request alone when profiling is disabled', function (): void {
        Config::set('schema_templates.json_ld_compose_profile.enabled', false);
        request()->attributes->set(PROFILER_BAG, ['dedupe_calls' => 9]);

        (new JsonLdCompositionProfiler)->reset();

        expect(profilerBag())->toBe(['dedupe_calls' => 9]);
    });

    it('does nothing when no request is bound', function (): void {
        $request = request();
        app()->offsetUnset('request');

        (new JsonLdCompositionProfiler)->reset();

        expect(app()->bound('request'))->toBeFalse()
            ->and($request->attributes->has(PROFILER_BAG))->toBeFalse();
    });
});

describe('recordBucketSample()', function (): void {
    it('adds one call and the elapsed time to the named bucket', function (): void {
        request()->attributes->set(PROFILER_BAG, ['dedupe_calls' => 2, 'dedupe_ms' => 1.5, 'other' => 'kept']);

        (new JsonLdCompositionProfiler)->recordBucketSample('dedupe_calls', 'dedupe_ms', 2.25);

        expect(profilerBag())->toBe(['dedupe_calls' => 3, 'dedupe_ms' => 3.75, 'other' => 'kept']);
    });

    it('starts a missing bucket at zero', function (): void {
        request()->attributes->set(PROFILER_BAG, []);

        (new JsonLdCompositionProfiler)->recordBucketSample('inflate_calls', 'inflate_ms', 4.0);

        expect(profilerBag())->toBe(['inflate_calls' => 1, 'inflate_ms' => 4.0]);
    });

    it('casts scalar counters and treats non-scalar ones as zero', function (): void {
        request()->attributes->set(PROFILER_BAG, [
            'a_calls' => '5',
            'a_ms' => '0.5',
            'b_calls' => ['x'],
            'b_ms' => ['y'],
            'c_calls' => '5.7',
            'c_ms' => 'abc',
        ]);

        $profiler = new JsonLdCompositionProfiler;
        $profiler->recordBucketSample('a_calls', 'a_ms', 1.0);
        $profiler->recordBucketSample('b_calls', 'b_ms', 2.0);
        $profiler->recordBucketSample('c_calls', 'c_ms', 3.0);

        expect(profilerBag())->toBe([
            'a_calls' => 6,
            'a_ms' => 1.5,
            'b_calls' => 1,
            'b_ms' => 2.0,
            'c_calls' => 6,
            'c_ms' => 3.0,
        ]);
    });

    it('writes nothing without a bag, when disabled, or with no request', function (): void {
        $profiler = new JsonLdCompositionProfiler;

        $profiler->recordBucketSample('dedupe_calls', 'dedupe_ms', 1.0);
        expect(request()->attributes->has(PROFILER_BAG))->toBeFalse();

        request()->attributes->set(PROFILER_BAG, 'not an array');
        $profiler->recordBucketSample('dedupe_calls', 'dedupe_ms', 1.0);
        expect(profilerBag())->toBe('not an array');

        request()->attributes->set(PROFILER_BAG, ['dedupe_calls' => 1]);
        Config::set('schema_templates.json_ld_compose_profile.enabled', false);
        $profiler->recordBucketSample('dedupe_calls', 'dedupe_ms', 1.0);
        expect(profilerBag())->toBe(['dedupe_calls' => 1]);

        Config::set('schema_templates.json_ld_compose_profile.enabled', true);
        $request = request();
        app()->offsetUnset('request');
        $profiler->recordBucketSample('dedupe_calls', 'dedupe_ms', 1.0);
        expect($request->attributes->get(PROFILER_BAG))->toBe(['dedupe_calls' => 1]);
    });
});

describe('recordAntlersParseSample()', function (): void {
    it('keeps samples slowest first, rounded to two places', function (): void {
        request()->attributes->set(PROFILER_BAG, ['antlers_parse_top' => [], 'other' => 1]);

        $profiler = new JsonLdCompositionProfiler;
        $profiler->recordAntlersParseSample(1.234, 'entry-a', 'name');
        $profiler->recordAntlersParseSample(9.876, 'entry-b', null);
        $profiler->recordAntlersParseSample(5.555, null, 'url');

        expect(profilerBag())->toBe([
            'antlers_parse_top' => [
                ['ms' => 9.88, 'template_entry_id' => 'entry-b', 'property_key' => null],
                ['ms' => 5.56, 'template_entry_id' => null, 'property_key' => 'url'],
                ['ms' => 1.23, 'template_entry_id' => 'entry-a', 'property_key' => 'name'],
            ],
            'other' => 1,
        ]);
    });

    it('keeps only the top N samples', function (): void {
        Config::set('schema_templates.json_ld_compose_profile.top_n', 2);
        request()->attributes->set(PROFILER_BAG, []);

        $profiler = new JsonLdCompositionProfiler;
        $profiler->recordAntlersParseSample(3.0, 'c', null);
        $profiler->recordAntlersParseSample(1.0, 'a', null);
        $profiler->recordAntlersParseSample(2.0, 'b', null);

        expect(profilerBag())->toBe([
            'antlers_parse_top' => [
                ['ms' => 3.0, 'template_entry_id' => 'c', 'property_key' => null],
                ['ms' => 2.0, 'template_entry_id' => 'b', 'property_key' => null],
            ],
        ]);
    });

    it('keeps exactly N samples when N are recorded', function (): void {
        Config::set('schema_templates.json_ld_compose_profile.top_n', 2);
        request()->attributes->set(PROFILER_BAG, []);

        $profiler = new JsonLdCompositionProfiler;
        $profiler->recordAntlersParseSample(1.0, 'a', null);
        $profiler->recordAntlersParseSample(2.0, 'b', null);

        expect(profilerBag())->toBe([
            'antlers_parse_top' => [
                ['ms' => 2.0, 'template_entry_id' => 'b', 'property_key' => null],
                ['ms' => 1.0, 'template_entry_id' => 'a', 'property_key' => null],
            ],
        ]);
    });

    it('replaces a non-array top list and sorts past non-array entries', function (): void {
        request()->attributes->set(PROFILER_BAG, ['antlers_parse_top' => 'junk']);
        (new JsonLdCompositionProfiler)->recordAntlersParseSample(2.0, 'a', null);

        expect(profilerBag())->toBe([
            'antlers_parse_top' => [
                ['ms' => 2.0, 'template_entry_id' => 'a', 'property_key' => null],
            ],
        ]);

        request()->attributes->set(PROFILER_BAG, ['antlers_parse_top' => [['ms' => 5.0], 'junk', []]]);
        (new JsonLdCompositionProfiler)->recordAntlersParseSample(3.0, 'b', null);

        expect(profilerBag())->toBe([
            'antlers_parse_top' => [
                ['ms' => 5.0],
                ['ms' => 3.0, 'template_entry_id' => 'b', 'property_key' => null],
                'junk',
                [],
            ],
        ]);
    });

    it('sorts entries with no ms, or that are not arrays, as zero', function (): void {
        request()->attributes->set(PROFILER_BAG, ['antlers_parse_top' => [
            ['ms' => -0.5],
            [],
            'junk',
            ['ms' => 0.5],
        ]]);

        (new JsonLdCompositionProfiler)->recordAntlersParseSample(0.0, 'zero', null);

        expect(profilerBag())->toBe(['antlers_parse_top' => [
            ['ms' => 0.5],
            [],
            'junk',
            ['ms' => 0.0, 'template_entry_id' => 'zero', 'property_key' => null],
            ['ms' => -0.5],
        ]]);
    });

    it('writes nothing without a bag, when disabled, or with no request', function (): void {
        $profiler = new JsonLdCompositionProfiler;

        $profiler->recordAntlersParseSample(1.0, 'a', 'b');
        expect(request()->attributes->has(PROFILER_BAG))->toBeFalse();

        request()->attributes->set(PROFILER_BAG, 'not an array');
        $profiler->recordAntlersParseSample(1.0, 'a', 'b');
        expect(profilerBag())->toBe('not an array');

        request()->attributes->set(PROFILER_BAG, []);
        Config::set('schema_templates.json_ld_compose_profile.enabled', false);
        $profiler->recordAntlersParseSample(1.0, 'a', 'b');
        expect(profilerBag())->toBe([]);

        Config::set('schema_templates.json_ld_compose_profile.enabled', true);
        $request = request();
        app()->offsetUnset('request');
        $profiler->recordAntlersParseSample(1.0, 'a', 'b');
        expect($request->attributes->get(PROFILER_BAG))->toBe([]);
    });
});

describe('recordAugmentUncachedSample()', function (): void {
    it('adds one call and the elapsed time to an existing bag', function (): void {
        request()->attributes->set(PROFILER_BAG, [
            'augment_uncached_calls' => 4,
            'augment_uncached_ms' => 1.5,
            'other' => 'kept',
        ]);

        (new JsonLdCompositionProfiler)->recordAugmentUncachedSample(0.25);

        expect(profilerBag())->toBe([
            'augment_uncached_calls' => 5,
            'augment_uncached_ms' => 1.75,
            'other' => 'kept',
        ]);
    });

    it('starts a bag of its own when the request has none', function (): void {
        (new JsonLdCompositionProfiler)->recordAugmentUncachedSample(2.5);

        expect(profilerBag())->toBe([
            'augment_uncached_calls' => 1,
            'augment_uncached_ms' => 2.5,
        ]);
    });

    it('starts missing counters at zero in an existing bag', function (): void {
        request()->attributes->set(PROFILER_BAG, ['other' => 'kept']);

        (new JsonLdCompositionProfiler)->recordAugmentUncachedSample(2.5);

        expect(profilerBag())->toBe([
            'other' => 'kept',
            'augment_uncached_calls' => 1,
            'augment_uncached_ms' => 2.5,
        ]);
    });

    it('writes nothing when disabled or with no request', function (): void {
        $profiler = new JsonLdCompositionProfiler;

        Config::set('schema_templates.json_ld_compose_profile.enabled', false);
        $profiler->recordAugmentUncachedSample(1.0);
        expect(request()->attributes->has(PROFILER_BAG))->toBeFalse();

        Config::set('schema_templates.json_ld_compose_profile.enabled', true);
        $request = request();
        app()->offsetUnset('request');
        $profiler->recordAugmentUncachedSample(1.0);
        expect($request->attributes->has(PROFILER_BAG))->toBeFalse();
    });
});

describe('finalize()', function (): void {
    it('logs every counter, rounded, and both top lists', function (): void {
        $logged = captureProfilerLogs();

        request()->attributes->set(PROFILER_BAG, [
            'augment_uncached_calls' => 1,
            'augment_uncached_ms' => 1.111,
            'schema_object_node_build_calls' => 2,
            'schema_object_node_build_ms' => 2.226,
            'antlers_parse_calls' => 3,
            'antlers_parse_ms' => 3.333,
            'dedupe_calls' => 4,
            'dedupe_ms' => 4.444,
            'inflate_calls' => 5,
            'inflate_ms' => 5.555,
            'json_encode_calls' => 6,
            'json_encode_ms' => 6.666,
            'relationship_resolve_calls' => 7,
            'relationship_resolve_ms' => 7.777,
            'relationship_collect_direct_calls' => 8,
            'relationship_collect_direct_ms' => 8.888,
            'relationship_collect_secondary_calls' => 9,
            'relationship_collect_secondary_ms' => 9.991,
            'relationship_collect_tertiary_calls' => 10,
            'relationship_collect_tertiary_ms' => 10.146,
            'relationship_collect_global_calls' => 11,
            'relationship_collect_global_ms' => 11.116,
            'relationship_targets_direct' => 12,
            'relationship_targets_secondary' => 13,
            'relationship_targets_tertiary' => 14,
            'relationship_targets_global' => 15,
            'relationship_compact_ref_hits' => 16,
            'relationship_full_build_calls' => 17,
            'relationship_full_build_ms' => 17.171,
            'antlers_parse_top' => [['ms' => 3.0]],
            'relationship_full_build_top' => [['ms' => 4.0]],
        ]);

        (new JsonLdCompositionProfiler)->finalize(12.345);

        expect($logged->getArrayCopy())->toBe([
            ['schema_json_ld_composition_profile', [
                'compose_wall_ms' => 12.35,
                'augment_uncached_calls' => 1,
                'augment_uncached_ms' => 1.11,
                'schema_object_node_build_calls' => 2,
                'schema_object_node_build_ms' => 2.23,
                'antlers_parse_calls' => 3,
                'antlers_parse_ms' => 3.33,
                'dedupe_calls' => 4,
                'dedupe_ms' => 4.44,
                'inflate_calls' => 5,
                'inflate_ms' => 5.56,
                'json_encode_calls' => 6,
                'json_encode_ms' => 6.67,
                'relationship_resolve_calls' => 7,
                'relationship_resolve_ms' => 7.78,
                'relationship_collect_direct_calls' => 8,
                'relationship_collect_direct_ms' => 8.89,
                'relationship_collect_secondary_calls' => 9,
                'relationship_collect_secondary_ms' => 9.99,
                'relationship_collect_tertiary_calls' => 10,
                'relationship_collect_tertiary_ms' => 10.15,
                'relationship_collect_global_calls' => 11,
                'relationship_collect_global_ms' => 11.12,
                'relationship_targets_direct' => 12,
                'relationship_targets_secondary' => 13,
                'relationship_targets_tertiary' => 14,
                'relationship_targets_global' => 15,
                'relationship_compact_ref_hits' => 16,
                'relationship_full_build_calls' => 17,
                'relationship_full_build_ms' => 17.17,
            ]],
            ['schema_json_ld_antlers_parse_top', ['top' => [['ms' => 3.0]]]],
            ['schema_json_ld_relationship_full_build_top', ['top' => [['ms' => 4.0]]]],
        ]);
    });

    it('logs zeros for missing and non-scalar counters, and skips empty top lists', function (): void {
        $logged = captureProfilerLogs();

        request()->attributes->set(PROFILER_BAG, [
            'augment_uncached_calls' => ['x'],
            'augment_uncached_ms' => ['y'],
            'dedupe_calls' => '3',
            'dedupe_ms' => '1.239',
            'antlers_parse_top' => [],
            'relationship_full_build_top' => 'junk',
        ]);

        (new JsonLdCompositionProfiler)->finalize(1.0);

        $zeros = [
            'compose_wall_ms' => 1.0,
            'augment_uncached_calls' => 0,
            'augment_uncached_ms' => 0.0,
            'schema_object_node_build_calls' => 0,
            'schema_object_node_build_ms' => 0.0,
            'antlers_parse_calls' => 0,
            'antlers_parse_ms' => 0.0,
            'dedupe_calls' => 3,
            'dedupe_ms' => 1.24,
            'inflate_calls' => 0,
            'inflate_ms' => 0.0,
            'json_encode_calls' => 0,
            'json_encode_ms' => 0.0,
            'relationship_resolve_calls' => 0,
            'relationship_resolve_ms' => 0.0,
            'relationship_collect_direct_calls' => 0,
            'relationship_collect_direct_ms' => 0.0,
            'relationship_collect_secondary_calls' => 0,
            'relationship_collect_secondary_ms' => 0.0,
            'relationship_collect_tertiary_calls' => 0,
            'relationship_collect_tertiary_ms' => 0.0,
            'relationship_collect_global_calls' => 0,
            'relationship_collect_global_ms' => 0.0,
            'relationship_targets_direct' => 0,
            'relationship_targets_secondary' => 0,
            'relationship_targets_tertiary' => 0,
            'relationship_targets_global' => 0,
            'relationship_compact_ref_hits' => 0,
            'relationship_full_build_calls' => 0,
            'relationship_full_build_ms' => 0.0,
        ];

        expect($logged->getArrayCopy())->toBe([
            ['schema_json_ld_composition_profile', $zeros],
        ]);
    });

    it('logs zero for every counter missing from the bag', function (): void {
        $logged = captureProfilerLogs();
        request()->attributes->set(PROFILER_BAG, []);

        (new JsonLdCompositionProfiler)->finalize(2.0);

        /** @var array<string, mixed> $context */
        $context = $logged->getArrayCopy()[0][1];
        $counters = $context;
        unset($counters['compose_wall_ms']);

        expect($logged)->toHaveCount(1)
            ->and($context['compose_wall_ms'])->toBe(2.0)
            ->and($counters)->toHaveCount(29)
            ->and(array_filter(
                $counters,
                static fn (mixed $value, string $key): bool => $value !== (str_ends_with($key, '_ms') ? 0.0 : 0),
                ARRAY_FILTER_USE_BOTH,
            ))->toBe([]);
    });

    it('logs only the wall time and augment zeros when the request has no bag', function (): void {
        $logged = captureProfilerLogs();
        request()->attributes->set(PROFILER_BAG, 'not an array');

        (new JsonLdCompositionProfiler)->finalize(3.456);

        expect($logged->getArrayCopy())->toBe([
            ['schema_json_ld_composition_profile', [
                'compose_wall_ms' => 3.46,
                'augment_uncached_calls' => 0,
                'augment_uncached_ms' => 0.0,
            ]],
        ]);
    });

    it('logs nothing when disabled or with no request', function (): void {
        $logged = captureProfilerLogs();
        request()->attributes->set(PROFILER_BAG, []);

        Config::set('schema_templates.json_ld_compose_profile.enabled', false);
        (new JsonLdCompositionProfiler)->finalize(1.0);

        Config::set('schema_templates.json_ld_compose_profile.enabled', true);
        app()->offsetUnset('request');
        (new JsonLdCompositionProfiler)->finalize(1.0);

        expect($logged->getArrayCopy())->toBe([]);
    });
});
