<?php

declare(strict_types=1);

use App\Models\Asset;
use Statamic\Eloquent\AddonSettings\AddonSettingsModel;
use Statamic\Eloquent\Assets\AssetContainerModel;
use Statamic\Eloquent\Collections\CollectionModel;
use Statamic\Eloquent\Entries\Entry;
use Statamic\Eloquent\Entries\EntryModel;
use Statamic\Eloquent\Fields\BlueprintModel;
use Statamic\Eloquent\Fields\FieldsetModel;
use Statamic\Eloquent\Forms\FormModel;
use Statamic\Eloquent\Forms\SubmissionModel;
use Statamic\Eloquent\Globals\GlobalSetModel;
use Statamic\Eloquent\Globals\VariablesModel;
use Statamic\Eloquent\Revisions\RevisionModel;
use Statamic\Eloquent\Sites\SiteModel;
use Statamic\Eloquent\Structures\CollectionTree;
use Statamic\Eloquent\Structures\NavModel;
use Statamic\Eloquent\Structures\NavTree;
use Statamic\Eloquent\Structures\TreeModel;
use Statamic\Eloquent\Taxonomies\TaxonomyModel;
use Statamic\Eloquent\Taxonomies\TermModel;
use Statamic\Eloquent\Tokens\TokenModel;

return [

    // Database connection name for eloquent driver
    'connection' => env('STATAMIC_ELOQUENT_CONNECTION', ''),

    // Table prefix for eloquent driver tables
    'table_prefix' => env('STATAMIC_ELOQUENT_PREFIX', ''),

    // Addon settings repository configuration
    'addon_settings' => [
        'driver' => 'file',
        'model' => AddonSettingsModel::class,
    ],

    // Asset containers configuration
    'asset_containers' => [
        'driver' => 'file',
        'model' => AssetContainerModel::class,
    ],

    // Assets configuration
    'assets' => [
        'driver' => 'eloquent',
        'model' => Asset::class,
        'asset' => App\Statamic\Eloquent\Assets\Asset::class,
    ],

    // Blueprints configuration
    'blueprints' => [
        'driver' => 'file',
        'model' => BlueprintModel::class,
        'namespaces' => 'all',
    ],

    // Collections configuration
    'collections' => [
        'driver' => 'file',
        'model' => CollectionModel::class,
        'update_entry_order_queue' => 'default',
        'update_entry_order_connection' => 'default',
    ],

    // Collection trees configuration
    'collection_trees' => [
        'driver' => 'file',
        'model' => TreeModel::class,
        'tree' => CollectionTree::class,
    ],

    // Entries configuration
    'entries' => [
        'driver' => 'file',
        'model' => EntryModel::class,
        'entry' => Entry::class,
        'map_data_to_columns' => false,
    ],

    // Fieldsets configuration
    'fieldsets' => [
        'driver' => 'file',
        'model' => FieldsetModel::class,
    ],

    // Forms configuration
    'forms' => [
        'driver' => 'file',
        'model' => FormModel::class,
    ],

    // Form submissions configuration
    'form_submissions' => [
        'driver' => 'file',
        'model' => SubmissionModel::class,
    ],

    // Global sets configuration
    'global_sets' => [
        'driver' => 'file',
        'model' => GlobalSetModel::class,
    ],

    // Global set variables configuration
    'global_set_variables' => [
        'driver' => 'file',
        'model' => VariablesModel::class,
    ],

    // Navigations configuration
    'navigations' => [
        'driver' => 'file',
        'model' => NavModel::class,
    ],

    // Navigation trees configuration
    'navigation_trees' => [
        'driver' => 'file',
        'model' => TreeModel::class,
        'tree' => NavTree::class,
    ],

    // Revisions configuration
    'revisions' => [
        'driver' => 'file',
        'model' => RevisionModel::class,
    ],

    // Taxonomies configuration
    'taxonomies' => [
        'driver' => 'file',
        'model' => TaxonomyModel::class,
    ],

    // Terms configuration
    'terms' => [
        'driver' => 'file',
        'model' => TermModel::class,
    ],

    // Tokens configuration
    'tokens' => [
        'driver' => 'file',
        'model' => TokenModel::class,
    ],

    // Sites configuration
    'sites' => [
        'driver' => 'file',
        'model' => SiteModel::class,
    ],
];
