<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\BardContentSanitizerServiceProvider;
use App\Providers\BardMutatorServiceProvider;
use App\Providers\ComputedPropertiesProvider;
use App\Providers\CustomEntryServiceProvider;
use App\Providers\CustomFieldComposerServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\HealthTalkServiceProvider;
use App\Providers\KnowledgeBaseServiceProvider;
use App\Providers\MapsServiceProvider;
use App\Providers\MarketingRedirectServiceProvider;
use App\Providers\PeopleCollectionServiceProvider;
use App\Providers\PeopleMergeServiceProvider;
use App\Providers\SchemaObjectServiceProvider;
use App\Providers\SchemaTemplatesServiceProvider;
use App\Providers\SearchServiceProvider;
use App\Providers\SecurityDefaultsServiceProvider;
use App\Providers\SiteClearGuardServiceProvider;
use App\Providers\StillatRelationshipsServiceProvider;
use App\Providers\ValidationServiceProvider;

return [
    AppServiceProvider::class,
    PeopleCollectionServiceProvider::class,
    ComputedPropertiesProvider::class,
    BardMutatorServiceProvider::class,
    BardContentSanitizerServiceProvider::class,
    EventServiceProvider::class,
    CustomEntryServiceProvider::class,
    KnowledgeBaseServiceProvider::class,
    MapsServiceProvider::class,
    MarketingRedirectServiceProvider::class,
    ValidationServiceProvider::class,
    CustomFieldComposerServiceProvider::class,
    PeopleMergeServiceProvider::class,
    HealthTalkServiceProvider::class,
    SchemaObjectServiceProvider::class,
    SchemaTemplatesServiceProvider::class,
    SearchServiceProvider::class,
    StillatRelationshipsServiceProvider::class,
    SecurityDefaultsServiceProvider::class,
    SiteClearGuardServiceProvider::class,
];
