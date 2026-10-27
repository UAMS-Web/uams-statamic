<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\CompleteDynamicPropertiesRector;
use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveDuplicatedReturnSelfDocblockRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;
use RectorLaravel\Set\LaravelSetProvider;

// Two profiles over one rule corpus (UAMS-Web/uams-statamic-archive#1720). This file is the single source of
// truth for the sets both share; the profile only changes which rules are
// skipped.
//
//   Gating profile (default) — the rules safe to make mandatory. This is what
//   `composer test:refactor` reports on and what the `rector` CI job enforces.
//
//   Sweep profile — every rule enabled here, including the sweep-only ones
//   below. Run it deliberately and review the diff:
//     composer test:refactor:sweep   # preview
//     composer refactor:sweep        # apply
//
// `rector-sweep.php` is a two-line shim that defines the constant below and
// requires this file, so neither profile can drift from the other.
$isSweepProfile = defined('UAMS_RECTOR_SWEEP') && UAMS_RECTOR_SWEEP;

// Rules excluded from the gating profile. Each is a rule whose suggestion is a
// judgment call rather than a mechanical one, so making it mandatory would turn
// a wrong suggestion into a change that *must* be applied or explicitly skipped
// — the same reasoning behind the per-file `CompleteDynamicPropertiesRector`
// carve-out below. They stay fully active under the sweep profile.
$sweepOnlyRules = [
    // `readonly` promotion is a semantic change, not a style one: it interacts
    // badly with framework code that mutates after construction, with cloning,
    // and with mocking in tests. Promotion is worth doing — deliberately, with
    // the runtime consequences reviewed — not on pain of a red gate.
    ReadOnlyClassRector::class,
    ReadOnlyPropertyRector::class,

    // Unsound at this config's path scope. `withPaths()` is `app/` only, so
    // Rector cannot see a static call from `tests/`, an Antlers template, or
    // `vendor/uams-web/*`, and will happily demote a method that is still
    // called statically from outside `app/`.
    LocallyCalledStaticMethodToNonStaticRector::class,

    // Docblock removal conflicts with the PHPDoc-coverage convention in the
    // `php-documentation` skill, which wants the tags these rules delete. What
    // they reach is past what Pint owns: Pint's docblock fixers already strip a
    // superfluous `@var` from a *typed property*, so what is left for these two
    // is inline `@var` on local variables (pinning a value that arrives untyped
    // — every hit in the current backlog is one of these) and `@return $this`
    // beside a `: static`. Those are deliberate annotations, not leftovers.
    RemoveUselessVarTagRector::class,
    RemoveDuplicatedReturnSelfDocblockRector::class,
];

// Large machine-generated reference-data enums — same list as phpstan.neon.dist
// `excludePaths.analyse` and phpunit.xml coverage excludes. Rector would spend
// minutes on files with no hand-written control flow to improve.
$generatedEnumSkips = [
    __DIR__.'/app/Enums/CanadianProvince.php',
    __DIR__.'/app/Enums/Country.php',
    __DIR__.'/app/Enums/County.php',
    __DIR__.'/app/Enums/CredentialRegistry.php',
    __DIR__.'/app/Enums/Currency.php',
    __DIR__.'/app/Enums/InternationalCodeDesignator.php',
    __DIR__.'/app/Enums/IscedA2011.php',
    __DIR__.'/app/Enums/IscedF2013.php',
    __DIR__.'/app/Enums/IscedP2011.php',
    __DIR__.'/app/Enums/Isco08.php',
    __DIR__.'/app/Enums/Language.php',
    __DIR__.'/app/Enums/LanguageBCP47.php',
    __DIR__.'/app/Enums/MimeType.php',
    __DIR__.'/app/Enums/SchemaDataType.php',
    __DIR__.'/app/Enums/SchemaEnumerationMember.php',
    __DIR__.'/app/Enums/SchemaProperty.php',
    __DIR__.'/app/Enums/SchemaType.php',
    __DIR__.'/app/Enums/State.php',
    __DIR__.'/app/Enums/ZipCode.php',
];

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
    ])
    ->withSkip([
        ...$generatedEnumSkips,
        ...($isSweepProfile ? [] : $sweepOnlyRules),
        // MaskField reads `presetInputMask`/`presetTransformCase` reflectively as
        // a subclass extension point but never *writes* them, so the PHP 8.2
        // dynamic-property deprecation this rule guards against cannot fire. The
        // properties are documented with class-level `@property` tags instead;
        // declaring them for real makes `property_exists()` statically always-true
        // (a Larastan error). Skip the rule for this one file.
        CompleteDynamicPropertiesRector::class => [
            __DIR__.'/app/Fieldtypes/MaskField.php',
        ],
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
    )
    ->withPhpSets()
    // Laravel-specific rules. `withSetProviders()` registers the package's
    // version-aware set group; `withComposerBased(laravel:, phpunit:)` then
    // activates the sets whose composer constraints the installed packages
    // satisfy (laravel/framework v13, phpunit/phpunit v12). Without the
    // provider line, `laravel: true` is a silent no-op.
    ->withSetProviders(LaravelSetProvider::class)
    ->withComposerBased(
        laravel: true,
        phpunit: true,
    )
    // Promote inline FQCNs to `use` imports (matches the project's import-FQCN
    // convention). Leave global/short classes alone to avoid needless churn.
    //
    // `removeUnusedImports: false` disables Rector's unused-import removal, which
    // — unlike Pint's docblock-aware `no_unused_imports` — is not `{@see}`-aware
    // and silently strips imports referenced only inside a `{@see ClassName}`
    // docblock tag (it defaults to `true`; see UAMS-Web/uams-statamic-archive#1131). Rector still *promotes*
    // FQCNs to `use` (code and docblock); Pint remains the single owner of
    // unused-import *removal*, so `{@see}`-only imports survive `composer refactor`.
    ->withImportNames(importShortClasses: false, removeUnusedImports: false);
