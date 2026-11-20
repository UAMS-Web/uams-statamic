<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;

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
// a wrong suggestion into a change that *must* be applied or explicitly
// skipped. They stay fully active under the sweep profile.
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
    // `php-documentation` skill, which wants the tags this rule deletes. What
    // it reaches is past what Pint owns: Pint's docblock fixers already strip a
    // superfluous `@var` from a *typed property*, so what is left for this rule
    // is inline `@var` on local variables (pinning a value that arrives
    // untyped). Those are deliberate annotations, not leftovers.
    RemoveUselessVarTagRector::class,
];

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
    ])
    ->withSkip([
        ...($isSweepProfile ? [] : $sweepOnlyRules),
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
    // Laravel-specific rules. `withComposerBased(laravel:, phpunit:)` activates
    // the version-aware sets whose composer constraints the installed packages
    // satisfy (laravel/framework v13, phpunit/phpunit v12). As of Rector 2.8.4 it
    // loads the rector-laravel set provider itself, so no `withSetProviders()`
    // line is needed.
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
