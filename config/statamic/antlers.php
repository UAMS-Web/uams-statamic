<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Debugbar
    |--------------------------------------------------------------------------
    |
    | Here you may specify whether the Antlers profiler should be added
    | to the Laravel Debugbar. This is incredibly useful for finding
    | performance impacts within any of your Antlers templates.
    |
    */

    'debugbar' => env('STATAMIC_ANTLERS_DEBUGBAR', true),

    /*
    |--------------------------------------------------------------------------
    | Guarded Variables
    |--------------------------------------------------------------------------
    |
    | Any variable pattern that appears in this list will not be allowed
    | in any Antlers template, including any user-supplied values.
    |
    */

    'guardedVariables' => [
        'config.app.key',
    ],

    /*
    |--------------------------------------------------------------------------
    | Guarded Tags
    |--------------------------------------------------------------------------
    |
    | Any tag pattern that appears in this list will not be allowed
    | in any Antlers template, including any user-supplied values.
    |
    */

    'guardedTags' => [

    ],

    /*
    |--------------------------------------------------------------------------
    | Guarded Modifiers
    |--------------------------------------------------------------------------
    |
    | Any modifier pattern that appears in this list will not be allowed
    | in any Antlers template, including any user-supplied values.
    |
    */

    'guardedModifiers' => [

    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Content Tags (User Content / Antlers in Content Fields)
    |--------------------------------------------------------------------------
    |
    | When Antlers parses user content (e.g. Bard with antlers: true, form
    | emails, entry title_format), only tags matching these patterns are
    | allowed.
    |
    | The "@default" entry merges Statamic's built-in safe tag patterns with
    | every tag registered from App\Tags (each handle and "handle:*").
    |
    | Omitting this config key uses the same merged defaults. An explicit
    | array without "@default" replaces them entirely; put "@default" first,
    | then any extra patterns (e.g. addon tags outside App\Tags).
    |
    | @see https://github.com/statamic/cms/pull/14092
    | @see vendor/statamic/cms/src/Providers/ViewServiceProvider.php
    */

    'allowedContentTags' => [
        '@default',
        'partial:*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Content Modifiers (User Content / Antlers in Content Fields)
    |--------------------------------------------------------------------------
    |
    | When Antlers parses user content, only modifiers in this list are
    | allowed.
    |
    | The "@default" entry merges Statamic's built-in safe modifier list with
    | every modifier registered from App\Modifiers.
    |
    | Add handles after "@default" only for modifiers needed in user content
    | that are not in that set (e.g. extra core modifiers such as bard_html,
    | or modifiers from addons outside App\Modifiers).
    |
    | Omitting this config key uses the same merged defaults. An explicit
    | array without "@default" replaces them entirely; put "@default" first
    | when extending.
    |
    | @see https://github.com/statamic/cms/pull/14092
    | @see vendor/statamic/cms/src/Providers/ViewServiceProvider.php
    */

    'allowedContentModifiers' => [
        '@default',

        // Extra core modifiers not included in Statamic's default content allowlist
        'add',
        'antlers',
        'as',
        'attribute',
        'bard_html',
        'bard_items',
        'bard_text',
        'compact',
        'console_log',
        'contains',
        'contains_all',
        'dump',
        'get',
        'gravatar',
        'group_by',
        'image',
        'in_array',
        'is_after',
        'is_before',
        'is_between',
        'is_numberwang',
        'link',
        'list',
        'macro',
        'mailto',
        'mark',
        'output',
        'pad',
        'partial',
        'piped',
        'plural',
        'raw',
        'rawurlencode_except_slashes',
        'ray',
        'regex_mark',
        'regex_replace',
        'repeat',
        'segment',
        'to_json',
        'ul',
        'url',
        'urlencode_except_slashes',
        'where-in',
        'where_in',
        'wrap',
    ],

];
