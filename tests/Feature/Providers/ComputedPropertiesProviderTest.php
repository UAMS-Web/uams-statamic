<?php

declare(strict_types=1);

use App\Services\ComputedFields\ProviderPeople\ProviderPeoplePersonNameComputedValues;
use App\Services\ComputedFields\Registration\IptcDigitalSourceCollectionComputedsRegistrar;
use App\Services\ComputedFields\Registration\PostsReadingMetricsCollectionComputedsRegistrar;
use App\Services\KnowledgeBase\KnowledgeBaseComputedFieldsService;
use App\Services\Posts\PostsReadingMetricsService;
use Mockery\MockInterface;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for knowledge base ancestor computeds, providers/people name
 * computeds, and related registrations.
 *
 * Verifies that topic and article entries receive computed field values from the
 * root-level knowledge_base ancestor, that knowledge_base entries use their own
 * stored values, and that providers/people person-name computeds behave as
 * expected.
 *
 * Mutation: 100% on App\Services\ComputedFields\ProviderPeople\ProviderPeoplePersonNameComputedValues
 * (123 mutants) and App\Services\KnowledgeBase\KnowledgeBaseComputedFieldsService (165 mutants), both --covered-only.
 *
 * Windows:
 *   php artisan cache:clear && echo. > storage\logs\laravel.log && php artisan test tests/Feature/Providers/ComputedPropertiesProviderTest.php
 *   php artisan cache:clear && echo. > storage\logs\laravel.log && php artisan test tests/Feature/Providers/ComputedPropertiesProviderTest.php --stop-on-failure
 *
 * Mac:
 *   php artisan cache:clear && > storage/logs/laravel.log && php artisan test tests/Feature/Providers/ComputedPropertiesProviderTest.php
 *   php artisan cache:clear && > storage/logs/laravel.log && php artisan test tests/Feature/Providers/ComputedPropertiesProviderTest.php --stop-on-failure
 */

/**
 * Resolve ancestor field value via KnowledgeBaseComputedFieldsService::getKnowledgeBaseAncestorValue.
 *
 * @param  KnowledgeBaseComputedFieldsService  $kb  Knowledge base computed fields service.
 * @param  Entry  $entry  The entry to resolve value for.
 * @param  string  $handle  The field handle.
 * @param  mixed  $default  Default value.
 * @return mixed The resolved value.
 */
function cppt_invokeGetKnowledgeBaseAncestorValue(
    KnowledgeBaseComputedFieldsService $kb,
    Entry $entry,
    string $handle,
    mixed $default = null
): mixed {
    return $kb->getKnowledgeBaseAncestorValue(
        $entry,
        $handle,
        $default
    );
}

it('returns own value for a knowledge_base entry', function (): void {
    // Create a new provider instance
    $provider = new KnowledgeBaseComputedFieldsService;

    // Mock the entry
    $entry = Mockery::mock(Entry::class);

    // Mock the blueprint method
    $entry->shouldReceive('blueprint')
        ->andReturn($mockBlueprint = Mockery::mock());

    // Mock the blueprint handle
    $mockBlueprint->shouldReceive('handle')
        ->andReturn('knowledge_base');

    // Mock the get method
    $entry->shouldReceive('get')
        ->with('topic_article_label')
        ->andReturn('Custom Articles Label');

    // Invoke the getKnowledgeBaseAncestorValue method
    $result = cppt_invokeGetKnowledgeBaseAncestorValue(
        $provider,
        $entry,
        'topic_article_label',
        'Articles'
    );

    // Verify the result matches the expected value
    expect($result)->toBe('Custom Articles Label');
});

it('returns the default when a knowledge_base entry value is null', function (): void {
    // Create a new provider instance
    $provider = new KnowledgeBaseComputedFieldsService;

    // Mock the entry
    $entry = Mockery::mock(Entry::class);

    // Mock the blueprint method
    $entry->shouldReceive('blueprint')
        ->andReturn($mockBlueprint = Mockery::mock());

    // Mock the blueprint handle
    $mockBlueprint->shouldReceive('handle')
        ->andReturn('knowledge_base');

    // Mock the get method
    $entry->shouldReceive('get')
        ->with('subtopic_label')
        ->andReturn(null);

    // Invoke the getKnowledgeBaseAncestorValue method
    $result = cppt_invokeGetKnowledgeBaseAncestorValue(
        $provider,
        $entry,
        'subtopic_label',
        'Subtopics'
    );

    // Verify the result matches the expected value
    expect($result)->toBe('Subtopics');
});

it('returns false when a knowledge_base entry stored value is false', function (): void {
    // Create a new provider instance
    $provider = new KnowledgeBaseComputedFieldsService;

    // Mock the entry
    $entry = Mockery::mock(Entry::class);

    // Mock the blueprint method
    $entry->shouldReceive('blueprint')
        ->andReturn($mockBlueprint = Mockery::mock());

    // Mock the blueprint handle
    $mockBlueprint->shouldReceive('handle')
        ->andReturn('knowledge_base');

    // Mock the get method
    $entry->shouldReceive('get')
        ->with('search_query_topic')
        ->andReturn(false);

    // Invoke the getKnowledgeBaseAncestorValue method
    $result = cppt_invokeGetKnowledgeBaseAncestorValue(
        $provider,
        $entry,
        'search_query_topic',
        true
    );

    // Verify the result matches the expected value
    expect($result)->toBeFalse();
});

it('returns the ancestor value for a topic entry', function (): void {
    // Create a new provider instance
    $provider = new KnowledgeBaseComputedFieldsService;

    // Mock the ancestor
    $ancestor = Mockery::mock(Entry::class);

    // Mock the blueprint method
    $ancestor->shouldReceive('blueprint')
        ->andReturn($ancestorBlueprint = Mockery::mock());

    // Mock the blueprint handle
    $ancestorBlueprint->shouldReceive('handle')
        ->andReturn('knowledge_base');

    // Mock the get method
    $ancestor->shouldReceive('get')
        ->with('topic_article_label')
        ->andReturn('Ancestor Articles Label');

    // Mock the topic entry
    $topicEntry = Mockery::mock(Entry::class);

    // Mock the blueprint method
    $topicEntry->shouldReceive('blueprint')
        ->andReturn($mockBlueprint = Mockery::mock());

    // Mock the blueprint handle
    $mockBlueprint->shouldReceive('handle')
        ->andReturn('topic');

    // Mock the hasStructure method
    $topicEntry->shouldReceive('hasStructure')
        ->andReturn(true);

    // Mock the id method, used to key the per-request ancestor cache
    $topicEntry->shouldReceive('id')
        ->andReturn('topic-id');

    // Mock the page method
    $topicEntry->shouldReceive('page')
        ->andReturn($page = Mockery::mock());

    // Mock the isRoot method
    $page->shouldReceive('isRoot')
        ->andReturn(false);

    // Mock the parent method
    $page->shouldReceive('parent')
        ->andReturn($parentPage = Mockery::mock());

    // Mock the id method
    $parentPage->shouldReceive('id')
        ->andReturn('kb-123');

    // Mock the Entry facade
    Statamic\Facades\Entry::shouldReceive('find')
        ->with('kb-123')
        ->andReturn($ancestor);

    // Invoke the getKnowledgeBaseAncestorValue method
    $result = cppt_invokeGetKnowledgeBaseAncestorValue(
        $provider,
        $topicEntry,
        'topic_article_label',
        'Articles'
    );

    // Verify the result matches the expected value
    expect($result)->toBe('Ancestor Articles Label');
});

it('returns the default for an entry with no ancestor', function (): void {
    // Create a new provider instance
    $provider = new KnowledgeBaseComputedFieldsService;

    // Mock the topic entry
    $topicEntry = Mockery::mock(Entry::class);

    // Mock the blueprint method
    $topicEntry->shouldReceive('blueprint')
        ->andReturn($mockBlueprint = Mockery::mock());

    // Mock the blueprint handle
    $mockBlueprint->shouldReceive('handle')
        ->andReturn('topic');

    // Mock the hasStructure method
    $topicEntry->shouldReceive('hasStructure')
        ->andReturn(false);

    expect($provider->findKnowledgeBaseAncestor($topicEntry))->toBeNull();

    // Invoke the getKnowledgeBaseAncestorValue method
    $result = cppt_invokeGetKnowledgeBaseAncestorValue(
        $provider,
        $topicEntry,
        'topic_article_label',
        'Articles'
    );

    // Verify the result matches the expected value
    expect($result)->toBe('Articles');
});

it('resolves ancestor restriction enabled false for a knowledge_base blueprint', function (): void {
    // Instantiate the real knowledge base computed fields service
    $service = new KnowledgeBaseComputedFieldsService;

    // Mock a knowledge_base blueprint entry
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')
        ->andReturn($mockBlueprint = Mockery::mock());
    $mockBlueprint->shouldReceive('handle')
        ->andReturn('knowledge_base');

    // Assert structural ancestor restriction is always false for knowledge_base roots
    expect($service->resolveAncestorRestrictionEnabled($entry))->toBeFalse();
});

it('resolves ancestor restriction enabled by reading the ancestor when present', function (): void {
    // Build a partial mock so ancestor lookup can be stubbed while real logic runs
    /** @var KnowledgeBaseComputedFieldsService&MockInterface $service */
    $service = Mockery::mock(KnowledgeBaseComputedFieldsService::class)->makePartial();

    // Mock the ancestor entry returning restriction_enabled true
    $ancestor = Mockery::mock(Entry::class);
    $ancestor->shouldReceive('get')
        ->with('restriction_enabled')
        ->andReturn(true);

    // Expect the service to resolve the structural ancestor once
    $service->shouldReceive('findKnowledgeBaseAncestor')
        ->once()
        ->andReturn($ancestor);

    // Mock a topic blueprint entry beneath a knowledge_base ancestor
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')
        ->andReturn($mockBlueprint = Mockery::mock());
    $mockBlueprint->shouldReceive('handle')
        ->andReturn('topic');

    // Assert restriction follows the ancestor toggle
    expect($service->resolveAncestorRestrictionEnabled($entry))->toBeTrue();
});

it('resolves ancestor restriction enabled false without an ancestor', function (): void {
    // Build a partial mock with no structural ancestor resolved
    /** @var KnowledgeBaseComputedFieldsService&MockInterface $service */
    $service = Mockery::mock(KnowledgeBaseComputedFieldsService::class)->makePartial();
    $service->shouldReceive('findKnowledgeBaseAncestor')
        ->once()
        ->andReturn(null);

    // Mock a topic blueprint entry without a knowledge_base ancestor
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')
        ->andReturn($mockBlueprint = Mockery::mock());
    $mockBlueprint->shouldReceive('handle')
        ->andReturn('topic');

    // Assert restriction resolves false when the ancestor chain is missing
    expect($service->resolveAncestorRestrictionEnabled($entry))->toBeFalse();
});

it('resolves has descendant articles false for a non-topic entry', function (): void {
    // Instantiate the real service for blueprint-only branching
    $service = new KnowledgeBaseComputedFieldsService;

    // Mock a non-topic entry such as an article blueprint
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')
        ->andReturn($mockBlueprint = Mockery::mock());
    $mockBlueprint->shouldReceive('handle')
        ->andReturn('article');

    // Assert descendant article computeds never run for non-topic blueprints
    expect($service->resolveHasDescendantArticles($entry))->toBeFalse();
});

it('resolves has descendant articles by delegating for topic entries', function (): void {
    // Build a partial mock so descendant article recursion can be stubbed
    /** @var KnowledgeBaseComputedFieldsService&MockInterface $service */
    $service = Mockery::mock(KnowledgeBaseComputedFieldsService::class)->makePartial();
    $service->shouldReceive('topicHasDescendantArticles')
        ->once()
        ->with('topic-uuid')
        ->andReturn(true);

    // Mock a topic entry with a stable id for expectation matching
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')
        ->andReturn($mockBlueprint = Mockery::mock());
    $mockBlueprint->shouldReceive('handle')
        ->andReturn('topic');
    $entry->shouldReceive('id')
        ->andReturn('topic-uuid');

    // Assert topics delegate to topicHasDescendantArticles for the flag
    expect($service->resolveHasDescendantArticles($entry))->toBeTrue();
});

it('registers IPTC digital source computed fields for the posts collection', function (): void {
    // Instantiate the IPTC collection computeds registrar under test
    $registrar = new IptcDigitalSourceCollectionComputedsRegistrar;

    // Expect posts collection registration for the primary digital source value key
    Collection::shouldReceive('computed')
        ->withArgs(
            fn ($collection, $key, $callback): bool => $collection === 'posts'
                && $key === 'digital_source_type_value'
                && \is_callable($callback)
        )
        ->atLeast()
        ->once();

    // Expect posts collection registration for the schema URL companion key
    Collection::shouldReceive('computed')
        ->withArgs(
            fn ($collection, $key, $callback): bool => $collection === 'posts'
                && $key === 'digital_source_type_value_schema'
                && \is_callable($callback)
        )
        ->atLeast()
        ->once();

    // Allow remaining Collection::computed calls invoked by the registrar
    Collection::shouldReceive('computed')
        ->withAnyArgs()
        ->andReturnNull();

    // Run registrar registration. The Collection::computed expectations above are the
    // verification — Mockery fails the test if they go unmet, and its expectation
    // count is folded into PHPUnit's, so no placeholder assertion is needed.
    $registrar->register();
});

it('registers posts reading metrics computed fields', function (): void {
    // Build the registrar with the real posts reading metrics service from the container
    $registrar = new PostsReadingMetricsCollectionComputedsRegistrar(
        app()->make(PostsReadingMetricsService::class)
    );

    // Expect word_count registration on the posts collection with a callable callback
    Collection::shouldReceive('computed')
        ->withArgs(
            fn ($collection, $key, $callback): bool => $collection === 'posts'
                && $key === 'word_count'
                && \is_callable($callback)
        )
        ->once();

    // Expect reading_time registration on the posts collection with a callable callback
    Collection::shouldReceive('computed')
        ->withArgs(
            fn ($collection, $key, $callback): bool => $collection === 'posts'
                && $key === 'reading_time'
                && \is_callable($callback)
        )
        ->once();

    // Run registrar registration. The Collection::computed expectation above is the
    // verification — Mockery fails the test if it goes unmet.
    $registrar->register();
});

it('registers provider-style computed fields for provider and people collections', function (): void {
    $fieldHandles = [
        'display_name_full',
        'display_name_full_with_prefix',
        'display_name_full_with_credentials',
        'display_name_short_with_prefix',
        'full_name',
        'full_name_with_credentials',
        'full_name_with_prefix',
        'brand_organizations',
        'regions',
        'portal',
        'prefix',
        'asp_filter',
        'research_publication_select',
        'separated_active_query',
        'separated_unpublish_query',
        'searchable',
    ];

    foreach (['clinical_providers', 'people'] as $collectionHandle) {
        $callbacks = Collection::getComputedCallbacks($collectionHandle);

        foreach ($fieldHandles as $fieldHandle) {
            expect($callbacks->has($fieldHandle))->toBeTrue(
                "{$collectionHandle} collection should register {$fieldHandle} computed field"
            );
        }
    }
});

it('registers people-only knows_about computed fields on people but not providers', function (): void {
    $handles = [
        'knows_about_terms',
        'knows_about_entries',
        'knows_about_strings',
    ];

    $peopleCallbacks = Collection::getComputedCallbacks('people');

    foreach ($handles as $handle) {
        expect($peopleCallbacks->has($handle))->toBeTrue(
            "people collection should register {$handle} computed field"
        );
    }

    $providerCallbacks = Collection::getComputedCallbacks('clinical_providers');

    foreach ($handles as $handle) {
        expect($providerCallbacks->has($handle))->toBeFalse(
            "clinical_providers collection should not register {$handle} computed field"
        );
    }
});

it('registers every knowledge_bases structural and ancestor-inherited computed field', function (): void {
    // The structural flags registered directly by the registrar.
    $structuralHandles = [
        'ancestor_restriction_enabled',
        'has_descendant_articles',
        'has_displayable_subtopics',
        'direct_subtopic_count',
        'direct_article_count',
    ];

    // The ancestor-inherited configuration computeds from registerKnowledgeBaseComputedFieldsFromAncestor.
    $ancestorHandles = [
        'knowledge_base_title',
        'knowledge_base_restriction_enabled',
        'knowledge_base_restriction_global_query',
        'knowledge_base_restriction_message',
        'knowledge_base_restriction_category',
        'knowledge_base_restriction_users',
        'knowledge_base_restriction_user_roles',
        'knowledge_base_restriction_user_groups',
        'topic_label',
        'topic_label_singular',
        'article_rating_query',
        'topic_search_query',
        'topic_search_bar_title',
        'topic_search_placeholder',
        'subtopic_label',
        'subtopic_label_singular',
        'subtopic_limit',
        'subtopic_description_query',
        'subtopic_image_query',
        'show_all_subtopics_label',
        'topic_article_label',
        'topic_article_label_singular',
        'topic_article_count',
        'topic_article_show_all_label',
        'topic_article_last_modified_query',
        'topic_article_description_query',
        'topic_article_image_query',
        'topic_article_view_count_query',
        'topic_article_like_count_query',
        'topic_article_dislike_count_query',
        'article_last_modified_query',
        'article_last_modified_label',
        'article_creator_query',
        'article_estimated_reading_time_query',
        'toc_query',
        'toc_heading_exclude',
        'toc_label',
        'attachments_label',
        'related_articles_query',
        'related_articles_label',
    ];

    // The registered computed callbacks for the knowledge_bases collection.
    $callbacks = Collection::getComputedCallbacks('knowledge_bases');

    // Every structural and ancestor-inherited handle must be registered.
    foreach (array_merge($structuralHandles, $ancestorHandles) as $handle) {
        expect($callbacks->has($handle))->toBeTrue(
            "knowledge_bases collection should register {$handle} computed field"
        );
    }
});

it('returns empty name computeds when person_name is incomplete', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'person_name' => [
                        'first_name' => [
                            'text' => 'Steven',
                            'phonetic_text' => '',
                        ],
                        'no_middle_name' => false,
                        'middle_name' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'no_nickname' => false,
                        'nickname' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'last_name' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'no_generational_suffix' => false,
                        'generational_suffix' => null,
                        'display_format' => null,
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $display = $reflection->getMethod('getDisplayNameFull');
    $full = $reflection->getMethod('getFullName');

    expect($display->invoke($provider, $entry))->toBe([
        'text' => '',
        'phonetic_text' => null,
    ]);
    expect($full->invoke($provider, $entry))->toBe([
        'text' => '',
        'phonetic_text' => null,
    ]);
});

it('uses the text subfield for group name fields in getDisplayNameFull', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'person_name' => [
                        'first_name' => [
                            'text' => 'Jane',
                            'phonetic_text' => '',
                        ],
                        'no_middle_name' => true,
                        'middle_name' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'no_nickname' => true,
                        'nickname' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'last_name' => [
                            'text' => 'Doe',
                            'phonetic_text' => '',
                        ],
                        'no_generational_suffix' => true,
                        'generational_suffix' => null,
                        'display_format' => null,
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('getDisplayNameFull');
    $result = $method->invoke(
        $provider,
        $entry
    );

    expect($result)->toBe([
        'text' => 'Jane Doe',
        'phonetic_text' => null,
    ]);
});

it('ignores initial display formats for first and middle in getFullName', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'person_name' => [
                        'first_name' => [
                            'text' => 'John Paul',
                            'phonetic_text' => '',
                        ],
                        'no_middle_name' => false,
                        'middle_name' => [
                            'text' => 'Robert',
                            'phonetic_text' => '',
                        ],
                        'no_nickname' => true,
                        'nickname' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'last_name' => [
                            'text' => 'Doe',
                            'phonetic_text' => '',
                        ],
                        'no_generational_suffix' => true,
                        'generational_suffix' => null,
                        'display_format' => 'first_initial_full_middle@ns',
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('getFullName');
    $result = $method->invoke(
        $provider,
        $entry
    );

    expect($result)->toBe([
        'text' => 'John Paul Robert Doe',
        'phonetic_text' => null,
    ]);
});

it('joins phonetic subfields in getDisplayNameFull per display format', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'person_name' => [
                        'first_name' => [
                            'text' => 'Leslie',
                            'phonetic_text' => 'ˈlɛsli',
                        ],
                        'no_middle_name' => true,
                        'middle_name' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'no_nickname' => true,
                        'nickname' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'last_name' => [
                            'text' => 'Taylor',
                            'phonetic_text' => 'ˈteɪlər',
                        ],
                        'no_generational_suffix' => true,
                        'generational_suffix' => null,
                        'display_format' => 'full_first_only@ns',
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('getDisplayNameFull');
    $result = $method->invoke(
        $provider,
        $entry
    );

    expect($result)->toBe([
        'text' => 'Leslie Taylor',
        'phonetic_text' => 'ˈlɛsli ˈteɪlər',
    ]);
});

it('uses letter IPA for the initial first-name segment in getDisplayNameFull phonetic text', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'person_name' => [
                        'first_name' => [
                            'text' => 'John Paul',
                            'phonetic_text' => 'ignored_for_this_pattern',
                        ],
                        'no_middle_name' => false,
                        'middle_name' => [
                            'text' => 'Robert',
                            'phonetic_text' => 'ˈrɑbərt',
                        ],
                        'no_nickname' => true,
                        'nickname' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'last_name' => [
                            'text' => 'Doe',
                            'phonetic_text' => 'doʊ',
                        ],
                        'no_generational_suffix' => true,
                        'generational_suffix' => null,
                        'display_format' => 'first_initial_full_middle@ns',
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('resolveDisplayNameFullPhoneticText');
    $result = $method->invoke(
        $provider,
        $entry
    );

    expect($result)->toBe('ʤeɪ pi ˈrɑbərt doʊ');
});

it('uses stored subfield phonetics in getFullNamePhoneticText for initial formats', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'person_name' => [
                        'first_name' => [
                            'text' => 'John Paul',
                            'phonetic_text' => 'ˈʤɑn pɔl',
                        ],
                        'no_middle_name' => true,
                        'middle_name' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'no_nickname' => true,
                        'nickname' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'last_name' => [
                            'text' => 'Doe',
                            'phonetic_text' => 'doʊ',
                        ],
                        'no_generational_suffix' => true,
                        'generational_suffix' => null,
                        'display_format' => 'first_initial_full_middle@ns',
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('resolveFullNamePhoneticText');
    $result = $method->invoke(
        $provider,
        $entry
    );

    expect($result)->toBe('ˈʤɑn pɔl doʊ');
});

it('maps the pedigree Jr. label in getDisplayNameFull phonetic text', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'person_name' => [
                        'first_name' => [
                            'text' => 'John',
                            'phonetic_text' => 'ʤɑn',
                        ],
                        'no_middle_name' => true,
                        'middle_name' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'no_nickname' => true,
                        'nickname' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'last_name' => [
                            'text' => 'Doe',
                            'phonetic_text' => 'doʊ',
                        ],
                        'no_generational_suffix' => false,
                        'generational_suffix' => 'Jr.',
                        'display_format' => 'full_first_only@ws',
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('resolveDisplayNameFullPhoneticText');
    $result = $method->invoke(
        $provider,
        $entry
    );

    expect($result)->toBe('ʤɑn doʊ ˈʤunjər');
});

it('prefers the pedigree label map IPA in getDisplayNameFull phonetic text', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'person_name' => [
                        'first_name' => [
                            'text' => 'John',
                            'phonetic_text' => 'ʤɑn',
                        ],
                        'no_middle_name' => true,
                        'middle_name' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'no_nickname' => true,
                        'nickname' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'last_name' => [
                            'text' => 'Doe',
                            'phonetic_text' => 'doʊ',
                        ],
                        'no_generational_suffix' => false,
                        'generational_suffix' => 'Jr.',
                        'display_format' => 'full_first_only@ws',
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('resolveDisplayNameFullPhoneticText');
    $result = $method->invoke(
        $provider,
        $entry
    );

    expect($result)->toBe('ʤɑn doʊ ˈʤunjər');
});

it('prepends doctor IPA in display_name_full_with_prefix phonetic text when md credential', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'person_name' => [
                        'first_name' => [
                            'text' => 'Jane',
                            'phonetic_text' => 'ʤeɪn',
                        ],
                        'no_middle_name' => true,
                        'middle_name' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'no_nickname' => true,
                        'nickname' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'last_name' => [
                            'text' => 'Doe',
                            'phonetic_text' => 'doʊ',
                        ],
                        'no_generational_suffix' => true,
                        'generational_suffix' => null,
                        'display_format' => 'full_first_only@ns',
                    ],
                    'credentials' => [
                        'md',
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('getDisplayNameFullWithPrefix');
    $method->setAccessible(true);
    $result = $method->invoke($provider, $entry);

    expect(data_get($result, 'phonetic_text'))->toBe('ˈdɑktər ʤeɪn doʊ');
});

it('prepends doctor IPA with last phonetic in display_name_short_with_prefix phonetic text', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'person_name' => [
                        'first_name' => [
                            'text' => 'Jane',
                            'phonetic_text' => '',
                        ],
                        'no_middle_name' => true,
                        'middle_name' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'no_nickname' => true,
                        'nickname' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'last_name' => [
                            'text' => 'Taylor',
                            'phonetic_text' => 'ˈteɪlər',
                        ],
                        'no_generational_suffix' => true,
                        'generational_suffix' => null,
                        'display_format' => null,
                    ],
                    'credentials' => [
                        'md',
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('getDisplayNameShortWithPrefix');
    $method->setAccessible(true);
    $result = $method->invoke($provider, $entry);

    expect(data_get($result, 'phonetic_text'))->toBe('ˈdɑktər ˈteɪlər');
});

it('returns doctor IPA in prefix phonetic text when md credential', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'credentials' => [
                        'md',
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('getPrefixValue');
    $method->setAccessible(true);
    $result = $method->invoke($provider, $entry);

    expect(data_get($result, 'phonetic_text'))->toBe('ˈdɑktər');
});

it('returns empty prefix phonetic text without a doctor credential', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'credentials' => [],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('getPrefixValue');
    $method->setAccessible(true);
    $result = $method->invoke($provider, $entry);

    expect(data_get($result, 'phonetic_text') ?? '')->toBe('');
});

it('omits doctor IPA in display_name_full_with_credentials phonetic text even with md credential', function (): void {
    $provider = new ProviderPeoplePersonNameComputedValues;
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->andReturnUsing(
            function (string $key, mixed $default = null): mixed {
                $map = [
                    'person_name' => [
                        'first_name' => [
                            'text' => 'Jane',
                            'phonetic_text' => 'ʤeɪn',
                        ],
                        'no_middle_name' => true,
                        'middle_name' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'no_nickname' => true,
                        'nickname' => [
                            'text' => '',
                            'phonetic_text' => '',
                        ],
                        'last_name' => [
                            'text' => 'Doe',
                            'phonetic_text' => 'doʊ',
                        ],
                        'no_generational_suffix' => true,
                        'generational_suffix' => null,
                        'display_format' => 'full_first_only@ns',
                    ],
                    'credentials' => [
                        'md',
                    ],
                ];

                return $map[$key] ?? $default;
            }
        );

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('getDisplayNameFullWithCredentials');
    $method->setAccessible(true);
    $result = $method->invoke($provider, $entry);
    $phonetic = data_get($result, 'phonetic_text');

    expect($phonetic)->toBeString();
    /** @var string $phonetic */
    expect(\str_starts_with(
        $phonetic,
        'ˈdɑktər'
    ))->toBeFalse();
    expect($phonetic)->toContain('ʤeɪn');
    expect($phonetic)->toContain('doʊ');
    expect($phonetic)->not->toContain(', ');
});
