<?php

declare(strict_types=1);

return [

    'enabled' => env(
        'BOOST_ENABLED',
        true
    ),

    'browser_logs_watcher' => env(
        'BOOST_BROWSER_LOGS_WATCHER',
        true
    ),

    /*
    |--------------------------------------------------------------------------
    | Project rules
    |--------------------------------------------------------------------------
    |
    | This repo keeps its agent rules in .claude/rules, not in Boost's
    | .ai/rules. Disabling the feature drops the "Project Rules"
    | section from the generated guidelines and unregisters the record-rule
    | MCP tool, so no session is told to read a file that does not exist.
    |
    */

    'rules' => [
        'enabled' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent guideline files
    |--------------------------------------------------------------------------
    |
    | AGENTS.md is this repository's single source of agent instructions, and
    | CLAUDE.md only imports it (UAMS-Web/uams-statamic-archive#2539). Claude Code's guidelines file therefore
    | points at AGENTS.md too. Without this, `boost:update` would write a second
    | copy of the generated block into CLAUDE.md. Both agents compose the same
    | block (`Agent::transformGuidelines()` is the identity for both), so two
    | writes to one file leave it as one write would.
    |
    */

    'agents' => [
        'claude_code' => [
            'guidelines_path' => 'AGENTS.md',
        ],
    ],

    'executable_paths' => [
        'php' => env('BOOST_PHP_EXECUTABLE_PATH'),
        'composer' => env('BOOST_COMPOSER_EXECUTABLE_PATH'),
        'npm' => env('BOOST_NPM_EXECUTABLE_PATH'),
        'vendor_bin' => env('BOOST_VENDOR_BIN_EXECUTABLE_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Boost MCP Tools & Resources Exclude
    |--------------------------------------------------------------------------
    |
    | Exclude tools/resources that cause issues (e.g. ApplicationInfo can
    | exhaust memory in large apps).
    |
    */

    // 'mcp' => [
    //     'tools' => [
    //         'exclude' => [],
    //     ],
    //     'resources' => [
    //         'exclude' => [],
    //     ],
    // ],

];
