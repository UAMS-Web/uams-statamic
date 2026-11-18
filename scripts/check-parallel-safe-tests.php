<?php

declare(strict_types=1);

/**
 * Test-isolation guard (UAMS-Web/uams-statamic-archive#1149) — fail the build when a test uses a
 * parallel-unsafe, shared-state pattern without quarantining it.
 *
 * The suite runs under `pest --parallel` (paratest): ~N workers share one
 * filesystem. A test that mutates shared, file-backed state without isolating it
 * per worker flakes intermittently — a concurrent worker reads or wipes that
 * state mid-test. `tests/README.md` documents the convention that prevents this
 * (the `serial` group, or a per-worker `TEST_TOKEN` path); this script *enforces*
 * it so a new violation fails at author time instead of flaking weeks later.
 *
 * Four patterns are checked here:
 *
 *   Rule A: `Storage::persistentFake(`: reroots a disk to the shared
 *            `storage/framework/testing/disks/<name>` with no per-worker token,
 *            so every worker faking the same disk reads and writes one directory.
 *            `Storage::fake(` is not flagged: see "Why Rule A targets
 *            persistentFake" below.
 *   Rule B: `storage_path('…framework/testing…')` with no per-run uniqueness
 *            token (`ThrowawayRoot`, `uniqid`, `TEST_TOKEN`, `random_bytes`,
 *            `Str::random`, `bin2hex`) anywhere in the call: a fixed shared path
 *            two runs race.
 *   Rule E: an `assert(` or `\assert(` call anywhere under `tests/` (UAMS-Web/uams-statamic-archive#1865).
 *            Not a parallelism rule; it rides here because this is already the
 *            suite's token-level lint and its CI job. `assert()` was the suite's
 *            habit for narrowing a type for PHPStan, which is wrong three ways:
 *            `zend.assertions=-1` compiles the call out, so the narrowing is
 *            configuration-dependent; a bare failure names no cause; and larastan
 *            passes on the suppression rather than on the types. UAMS-Web/uams-statamic-archive#1733 removed the
 *            eleven unqualified narrowings but its `^\s*assert(` search never
 *            matched the dominant fully-qualified form, and shipped no guard —
 *            which is exactly why 157 of them survived. Narrow by control flow
 *            instead ({@see tests/Support/TypeNarrowing.php}).
 *   Rule F: a band test that saves or deletes an entry or a term on a base that
 *            leaves entries and terms in the shared content mirror
 *            (UAMS-Web/uams-statamic#575). Under test every Stache store reads the
 *            mirror, which every worker shares, so the save lands where a sibling's
 *            cold warm or a listing of the whole collection reads it as committed
 *            content (#538, #558). See "How Rule F tells an entry save from any
 *            other" below.
 *
 * Rules C and D (a global `const` or `function` declared in more than one
 * namespace-less file, UAMS-Web/uams-statamic-archive#1471, and a single-segment `use X;` in a file with no
 * named namespace, UAMS-Web/uams-statamic-archive#1867) moved to the shared
 * `.claude/skills/pest-testing/scripts/check-global-test-symbols.php` (UAMS-Web/uams-statamic-archive#3112),
 * which the same CI job and local-CI `isolation` job run after this script. The
 * letters are kept there, so A, B and E keep their meaning here.
 *
 * Detection runs over PHP tokens ({@see token_get_all}), not physical lines, so a
 * match inside a comment, docblock, or string literal is ignored, and a call
 * wrapped across lines (or whose uniqueness token sits on a continuation line) is
 * still resolved correctly. That is what lets Rule E's own prose — and every
 * docblock in the suite that discusses `assert()` — describe the banned form
 * without tripping the rule.
 *
 * A file is exempt when it is quarantined **whole-file** into the `serial` group
 * (a Pest `uses(...)->group('serial')` chain, or a class-level PHPUnit
 * `#[Group('serial')]` / `@group serial`). Separately, a file is exempt from
 * the rules {@see ALLOWLIST} names for it, with a reason. A per-test marker
 * (`it(...)->group('serial')` or a method-level PHPUnit attribute) does NOT
 * exempt the file — it quarantines only that test, leaving a
 * sibling `Storage::persistentFake` in the parallel pass.
 *
 * Why Rule A targets persistentFake (UAMS-Web/uams-statamic-archive#3111). Rule A used to flag `Storage::fake(`
 * on the premise that the faked root was shared between workers. It is not.
 * `Illuminate\Support\Facades\Storage::fake()` appends `_test_{token}` to the root
 * whenever `ParallelTesting::token()` (the paratest `TEST_TOKEN`) is set, so under
 * `pest --parallel` each worker fakes, and `cleanDirectory()`s, a root of its own.
 * Read in `laravel/framework` v13.35.0 (UAMS-Web/uams-claude-skills#160) and
 * v13.37.0, whose `src/Illuminate/Support/Facades/Storage.php` is the same file,
 * SHA-256 `9c35f548e60c4281b657259cc238d6dfb682a20b5a491b630f0e1f56a0a852f4`;
 * measured under `pest --parallel --processes=2` on Windows 11, where a worker's
 * `Storage::fake('probe')` resolved to `disks/probe_test_1`.
 * `Storage::persistentFake()` builds its root from `getRootPath()` alone, with no
 * token, and resolved to `disks/probe2` in the same run, so it is the per-call
 * form that still shares a disk, and the one Rule A flags. What `fake()` leaves
 * is two concurrent runs that share a worker index, and so a token: a
 * `Tests\Support\ThrowawayRoot` concern about runs, which no per-call rule sees.
 * Revisit this rule if a framework update changes `fake()` or `persistentFake()`.
 *
 * How Rule F tells an entry save from any other. A token lint cannot know a
 * receiver's type, and `save()` is also the method a tree, a collection, a
 * blueprint or a user is saved with. So the rule looks for the entry or term being
 * made or fetched in the same test: a static call on the `Statamic\Facades\Entry`
 * or `Statamic\Facades\Term` facade, under whatever alias the file imports it as,
 * or a `new` of `Statamic\Entries\Entry` or `Statamic\Taxonomies\Term`. A
 * variable assigned from an expression holding one, or from another such variable,
 * is an entry or a term for the rest of that test, and a `save()`,
 * `saveQuietly()`, `delete()` or `deleteQuietly()` on it, or at the end of a chain
 * that starts at one, is the write the rule reports. The scope is one `it()`,
 * `test()`, `beforeEach()` or `afterEach()` closure, and a test chained
 * `->group('serial')` is exempt, since it runs in the serial tail.
 *
 * What it does not see: a write inside a helper function the test calls, or on
 * an entry the test receives from a dataset, a property or a helper's return
 * value. Those name no facade in the test. Extending the rule to them means
 * resolving calls across functions, which a token lint does not do.
 *
 * The bases it applies to are {@see SHARED_MIRROR_BASES}: the plain `TestCase`,
 * and the bases that build on it while leaving the `entries` and `terms` stores on
 * the mirror. A file on any other base, or with no `uses()` at all, is not checked.
 *
 * Usage:  php scripts/check-parallel-safe-tests.php [path]   (path default: tests)
 * Exit:   0 = clean, 1 = one or more un-quarantined violations.
 *
 * Dependency-free (no Composer autoload) so its CI job needs only PHP.
 */

/**
 * Files exempt from named rules despite matching them. Keyed by repo-relative
 * path (forward slashes); each row lists the rules it exempts and a reason that
 * argues for each of them. A row suppresses only the rules it names: the file is
 * still scanned for every other rule, so a later change that trips one of those
 * is reported (UAMS-Web/uams-statamic-archive#2939). Only the parallelism rules A and B can be named;
 * Rule E runs before this lookup, and a row naming any other rule is refused.
 * An entry that no longer matches its rules is a harmless no-op — prune it when
 * its reason is resolved.
 *
 * @var array<string, array{rules: list<string>, reason: string}>
 */
const ALLOWLIST = [
    // Kept short on purpose. `tests/Support/StacheIsolatedTestCase.php` sat here from
    // UAMS-Web/uams-statamic-archive#1149 with the retirement condition "remove this entry when UAMS-Web/uams-statamic-archive#1136 lands" —
    // UAMS-Web/uams-statamic-archive#1136 landed, but the file kept hoisting its token to a `$token` local,
    // which Rule B cannot see, so the exemption outlived its reason by long
    // enough for a sibling class to call it out in a comment. UAMS-Web/uams-statamic-archive#1957 routed all
    // four throwaway roots through {@see UNIQUENESS_HELPERS}, called inline, and
    // the entry became a no-op. Prefer fixing the call shape to adding a row here,
    // and give every row a retirement condition.

    // The fixed mirror path IS the contract under test: ContentRoot::path('testing')
    // returns exactly this shared path, so a per-worker token on the expected
    // side would only break the assertion. The test compares two strings and
    // touches no file, so Rule B's race on a shared path cannot happen here.
    // Retire this row when ContentRoot::path() or this test reads or writes the
    // filesystem, or when the mirror path becomes per-worker (#255, UAMS-Web/uams-statamic-archive#2919).
    'tests/Unit/Support/ContentRootTest.php' => [
        'rules' => ['B'],
        'reason' => 'Asserts the fixed testing mirror and file cache paths as strings; no filesystem access.',
    ],
];

/**
 * The rules an {@see ALLOWLIST} row may name: the parallelism rules. Rule E is not
 * among them, because an `assert()` narrowing is a typing defect no parallelism
 * exemption excuses.
 *
 * @var list<string>
 */
const ALLOWLIST_RULES = ['A', 'B', 'F'];

/**
 * The test bases Rule F applies to: each leaves the `entries` and `terms` stores
 * reading the shared content mirror. `TestCase` itself;
 * `CollectionTreeIsolatedTestCase`, which moves only the collection trees; and
 * `StacheCacheOnlyIsolatedTestCase`, which moves only the Stache cache and leaves
 * every store on the real content.
 *
 * @var list<string>
 */
const SHARED_MIRROR_BASES = [
    'Tests\\TestCase',
    'Tests\\Support\\CollectionTreeIsolatedTestCase',
    'Tests\\Support\\StacheCacheOnlyIsolatedTestCase',
];

/**
 * The facades whose static calls make or fetch an entry or a term (Rule F).
 *
 * @var list<string>
 */
const ENTRY_TERM_FACADES = ['Statamic\\Facades\\Entry', 'Statamic\\Facades\\Term'];

/**
 * The entry and term classes a `new` makes one of (Rule F).
 *
 * @var list<string>
 */
const ENTRY_TERM_CLASSES = ['Statamic\\Entries\\Entry', 'Statamic\\Taxonomies\\Term'];

/**
 * The methods that write an entry or a term to its store (Rule F), matched without
 * regard to case as PHP matches method names.
 *
 * @var list<string>
 */
const ENTRY_TERM_WRITES = ['save', 'saveQuietly', 'delete', 'deleteQuietly'];

/**
 * The Pest functions whose closure Rule F scans as one test body. A hook runs for
 * every test in the file, so it has no `serial` exemption of its own.
 *
 * @var list<string>
 */
const TEST_FUNCTIONS = ['it', 'test', 'beforeEach', 'afterEach'];

/**
 * The hooks among {@see TEST_FUNCTIONS}.
 *
 * @var list<string>
 */
const HOOK_FUNCTIONS = ['beforeEach', 'afterEach'];

/**
 * Identifiers whose presence inside a `storage_path()` call makes the path
 * per-run unique (parallel-safe): `uniqid()`, `random_bytes()`, `bin2hex()`, and
 * `Str::random()` (via its `random` method name). `TEST_TOKEN` (as a string
 * literal, e.g. `env('TEST_TOKEN')`) is matched separately.
 *
 * @var list<string>
 */
const UNIQUENESS_IDENTIFIERS = ['uniqid', 'random_bytes', 'bin2hex', 'random'];

/**
 * Class names whose presence inside a `storage_path()` call makes the path
 * per-run unique because the call routes through the suite's shared
 * throwaway-root helper (UAMS-Web/uams-statamic-archive#1957).
 *
 * `Tests\Support\ThrowawayRoot::segment()` resolves the paratest worker's
 * `TEST_TOKEN` when there is one and a process-unique `seq-<pid>` otherwise, so a
 * call that uses it is isolated on both axes this guard cares about — strictly
 * more than the bare `getenv('TEST_TOKEN') ?: 'seq'` it replaced, which was
 * per-worker but shared across concurrent serial runs.
 *
 * Matched on the class name rather than the method, because `segment` is a common
 * enough word to false-positive on an unrelated call.
 *
 * @var list<string>
 */
const UNIQUENESS_HELPERS = ['ThrowawayRoot'];

$repoRoot = str_replace('\\', '/', dirname(__DIR__));

foreach (ALLOWLIST as $allowlistedPath => $row) {
    $rules = $row['rules'] ?? null;
    $reason = $row['reason'] ?? null;

    if (! is_array($rules) || $rules === [] || array_diff($rules, ALLOWLIST_RULES) !== [] || ! is_string($reason) || trim($reason) === '') {
        fwrite(STDERR, sprintf(
            "check-parallel-safe-tests: ALLOWLIST row %s must name one or more of rules %s and give a reason.\n",
            $allowlistedPath,
            implode(', ', ALLOWLIST_RULES),
        ));
        exit(1);
    }
}

$scanArg = $argv[1] ?? 'tests';
$scanRoot = preg_match('#^(?:[A-Za-z]:)?/#', str_replace('\\', '/', $scanArg)) === 1
    ? str_replace('\\', '/', $scanArg)
    : $repoRoot.'/'.$scanArg;

if (! is_dir($scanRoot)) {
    fwrite(STDERR, "check-parallel-safe-tests: path not found: {$scanRoot}\n");
    exit(1);
}

/** @var list<array{file: string, line: int, rule: string, snippet: string}> $violations */
$violations = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $fileInfo) {
    if (! $fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
        continue;
    }

    $absolute = str_replace('\\', '/', $fileInfo->getPathname());

    // Case-insensitive prefix strip: on Windows dirname(__DIR__) and an absolute
    // scan argument can disagree on drive-letter case (`C:` vs `c:`), which would
    // otherwise leave $relative as the full path and defeat the ALLOWLIST match.
    $relative = str_starts_with(strtolower($absolute), strtolower($repoRoot).'/')
        ? substr($absolute, strlen($repoRoot) + 1)
        : $absolute;

    $contents = (string) file_get_contents($absolute);

    // Rule E runs BEFORE the ALLOWLIST and serial-quarantine skips below. Those
    // exempt a file from the *parallelism* rules; an `assert()` narrowing is a
    // typing defect that a `serial` group or an allowlist entry does nothing to
    // excuse, so neither may hide one.
    foreach (scanForAssertNarrowings($contents) as $violation) {
        $violations[] = ['file' => $relative] + $violation;
    }

    if (isWholeFileSerial($contents)) {
        continue;
    }

    // An ALLOWLIST row exempts its file from the rules it names, and only those.
    $exemptRules = ALLOWLIST[$relative]['rules'] ?? [];

    foreach ([...scanForViolations($contents), ...scanForMirrorWrites($contents)] as $violation) {
        if (in_array(ruleLetter($violation['rule']), $exemptRules, true)) {
            continue;
        }

        $violations[] = ['file' => $relative] + $violation;
    }
}

if ($violations === []) {
    echo "check-parallel-safe-tests: OK — no un-quarantined parallel-unsafe test patterns.\n";
    exit(0);
}

usort($violations, fn (array $a, array $b): int => [$a['file'], $a['line']] <=> [$b['file'], $b['line']]);

fwrite(STDERR, "check-parallel-safe-tests: parallel-unsafe test pattern(s) found:\n\n");

foreach ($violations as $violation) {
    fwrite(STDERR, sprintf(
        "  %s:%d\n    Rule %s\n    %s\n\n",
        $violation['file'],
        $violation['line'],
        $violation['rule'],
        $violation['snippet'],
    ));
}

fwrite(STDERR, <<<'HINT'
Fix each by making the test parallel-safe, then (if it still matches) quarantining it:
  - For Rule A, use Storage::fake() instead of Storage::persistentFake(): fake()
    appends _test_{TEST_TOKEN} to the disk root, so each parallel worker gets
    its own; persistentFake() does not, so every worker shares one root. Or
  - Add ->group('serial') / #[Group('serial')] so it runs in the serial tail, or
  - Make the shared path per-run by importing Tests\Support\ThrowawayRoot and
    appending ThrowawayRoot::segment() INSIDE the storage_path() call. Both
    halves matter: this rule matches the bare class name, so a fully-qualified
    \Tests\Support\ThrowawayRoot::segment() does NOT satisfy it, and neither
    does a result hoisted to a local on a previous line. Or
  - For Rule F, move the file onto EntriesAndTermsIsolatedTestCase (or
    EntriesTermsAndTreesIsolatedTestCase when it also saves a tree), which gives
    each process its own copy of the entries and terms, or StacheIsolatedTestCase
    when it needs no real content. Or
  - If it is provably safe, add it to ALLOWLIST in this script, naming the rule it
    exempts and a reason.
Rule E is the one rule nothing silences. Neither `serial` nor the ALLOWLIST
exempts it, because it is a typing defect rather than a parallelism one:
  - Narrow by control flow with the tests/Support/TypeNarrowing.php helpers
    (mustBe / mustBeString / mustBeArray / mustExist / mustNotBeFalse), which
    fail by name and let PHPStan verify the type instead of assuming it.
  - For a genuine runtime invariant (not a type narrowing), write an explicit
    `if (! …) { throw new RuntimeException("…"); }` naming what went wrong.
See tests/README.md "Parallel-fragile tests" for the convention.

HINT);

exit(1);

/**
 * The rule letter a violation's `rule` label opens with: `B` for
 * `B (hardcoded framework/testing path, …)`.
 */
function ruleLetter(string $rule): string
{
    return substr($rule, 0, 1);
}

/**
 * Whether the whole file is quarantined into the `serial` group — the only
 * exemption that makes *every* test in the file run in the single-process serial
 * tail. A per-test marker qualifies only that test, so it is deliberately not
 * accepted here.
 */
function isWholeFileSerial(string $contents): bool
{
    // Pest: a `uses(...)` chain ending in `->group('serial'[, ...])` applies the
    // group to every test in the file. `serial` may be any argument (the group
    // list is variadic), and the chain may include other calls (`->beforeEach()`).
    // A bare `it(...)->group('serial')` has no `uses(` prefix, so it does not match.
    if (preg_match(
        '/\buses\s*\([^;]*?\)\s*(?:->\s*\w+\s*\([^;]*?\)\s*)*->\s*group\s*\(\s*[^;)]*?([\'"])serial\1/s',
        $contents,
    ) === 1) {
        return true;
    }

    // PHPUnit class-level `#[Group('serial')]` — the attribute must sit
    // immediately before the class declaration (a method-level attribute is
    // followed by `function`, not `class`, so it correctly does not match).
    if (preg_match(
        '/#\[\s*Group\s*\(\s*([\'"])serial\1\s*\)\s*\](?:\s*#\[[^\]]*\])*\s*(?:abstract\s+|final\s+|readonly\s+)*class\b/',
        $contents,
    ) === 1) {
        return true;
    }

    // PHPUnit class-level `@group serial` docblock immediately before the class.
    // `serial` is anchored with a negative lookahead so `@group serial-slow` (a
    // different group that is neither excluded from the parallel pass nor added to
    // the serial tail) does not match.
    if (preg_match(
        '/@group\s+serial(?![\w-])[\s\S]*?\*\/\s*(?:#\[[^\]]*\]\s*)*(?:abstract\s+|final\s+|readonly\s+)*class\b/',
        $contents,
    ) === 1) {
        return true;
    }

    return false;
}

/**
 * Scan a file's PHP tokens for Rule A / Rule B violations. Tokenizing (rather than
 * scanning physical lines) means a pattern inside a comment or string literal is
 * ignored, and a call wrapped across lines is resolved as one unit.
 *
 * @return list<array{line: int, rule: string, snippet: string}>
 */
function scanForViolations(string $contents): array
{
    $tokens = token_get_all($contents);
    $lines = explode("\n", $contents);
    $count = count($tokens);

    /** @var list<array{line: int, rule: string, snippet: string}> $violations */
    $violations = [];

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (! is_array($token)) {
            continue;
        }

        // Rule A: a real `Storage::persistentFake(` static call: `Storage` `::`
        // `persistentFake` `(`. A plain `Storage::fake(` is per worker (UAMS-Web/uams-statamic-archive#3111).
        if (isStorageFacadeName($token)) {
            $colon = nextMeaningful($tokens, $i + 1);
            $method = $colon !== null && is_array($tokens[$colon]) && $tokens[$colon][0] === T_DOUBLE_COLON
                ? nextMeaningful($tokens, $colon + 1)
                : null;

            // PHP method names are case-insensitive, so `PersistentFake(` is the same call.
            if ($method !== null && strcasecmp(tokenText($tokens[$method]), 'persistentFake') === 0) {
                $paren = nextMeaningful($tokens, $method + 1);

                if ($paren !== null && $tokens[$paren] === '(') {
                    $violations[] = [
                        'line' => $token[2],
                        'rule' => 'A (Storage::persistentFake shares a non-tokenized testing disk)',
                        'snippet' => trim($lines[$token[2] - 1] ?? ''),
                    ];

                    continue;
                }
            }
        }

        if ($token[0] !== T_STRING) {
            continue;
        }

        // Rule B: `storage_path( … )` whose argument hardcodes a framework/testing
        // path with no per-run uniqueness token anywhere in the call.
        if ($token[1] === 'storage_path') {
            $open = nextMeaningful($tokens, $i + 1);

            if ($open !== null && $tokens[$open] === '(') {
                [$callTokens, $end] = collectCallTokens($tokens, $open);

                if (callHardcodesFrameworkTesting($callTokens) && ! callHasUniquenessToken($callTokens)) {
                    $violations[] = [
                        'line' => $token[2],
                        'rule' => 'B (hardcoded framework/testing path, no per-worker token)',
                        'snippet' => trim($lines[$token[2] - 1] ?? ''),
                    ];
                }

                $i = $end;
            }
        }
    }

    return $violations;
}

/**
 * Scan a file's PHP tokens for `assert(` / `\assert(` calls (Rule E).
 *
 * Both spellings are one construct, and UAMS-Web/uams-statamic-archive#1733's line-anchored `^\s*assert(`
 * search matched only the first — which is how 157 fully-qualified narrowings
 * survived a sweep that reported the suite clean. Matching on tokens catches
 * both regardless of leading whitespace, wrapping, or a preceding `(`.
 *
 * PHP 8 tokenizes the qualified call as a single `T_NAME_FULLY_QUALIFIED`
 * (`\assert`) and the bare one as `T_STRING` (`assert`), so both are checked.
 * A method or static call that merely ends in `assert` — `$this->assert(`,
 * `Foo::assert(` — is NOT the language construct and is skipped by looking at
 * the preceding meaningful token. `assertSomething(` never matches, because the
 * token's text must equal `assert` exactly.
 *
 * @param  string  $contents  The PHP source to scan.
 * @return list<array{line: int, rule: string, snippet: string}>
 */
function scanForAssertNarrowings(string $contents): array
{
    $tokens = token_get_all($contents);
    $lines = explode("\n", $contents);
    $count = count($tokens);

    /** @var list<array{line: int, rule: string, snippet: string}> $violations */
    $violations = [];

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (! is_array($token)) {
            continue;
        }

        // PHP function names are case-insensitive, so `ASSERT(` / `Assert(` is the
        // same construct and narrows types identically. A case-sensitive match
        // would leave exactly the kind of hole that lets the form return.
        $isBare = $token[0] === T_STRING && strcasecmp($token[1], 'assert') === 0;
        $isQualified = defined('T_NAME_FULLY_QUALIFIED')
            && $token[0] === T_NAME_FULLY_QUALIFIED
            && strcasecmp($token[1], '\\assert') === 0;

        if (! $isBare && ! $isQualified) {
            continue;
        }

        // `$x->assert(`, `Foo::assert(`, `function assert(` and `new assert(` are
        // each something other than the language construct.
        $previous = previousMeaningful($tokens, $i - 1);

        if ($previous !== null && is_array($tokens[$previous]) && in_array(
            $tokens[$previous][0],
            [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW],
            true,
        )) {
            continue;
        }

        $open = nextMeaningful($tokens, $i + 1);

        if ($open === null || $tokens[$open] !== '(') {
            continue;
        }

        $violations[] = [
            'line' => $token[2],
            'rule' => 'E (assert() type-narrowing; narrow by control flow instead)',
            'snippet' => trim($lines[$token[2] - 1] ?? ''),
        ];
    }

    return $violations;
}

/**
 * Index of the nearest preceding token that is not whitespace or a comment.
 *
 * The mirror of {@see nextMeaningful()}, used by Rule E to tell the `assert`
 * language construct from a method call that happens to share its name.
 *
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 * @param  int  $index  Index to search back from, inclusive.
 * @return ?int The token index, or null when none precedes it.
 */
function previousMeaningful(array $tokens, int $index): ?int
{
    for ($i = $index; $i >= 0; $i--) {
        $token = $tokens[$i];

        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return $i;
    }

    return null;
}

/**
 * Index of the next token that is not whitespace or a comment, or null at end.
 *
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 */
function nextMeaningful(array $tokens, int $index): ?int
{
    $count = count($tokens);

    for (; $index < $count; $index++) {
        $token = $tokens[$index];

        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return $index;
    }

    return null;
}

/**
 * Whether a name token is the `Storage` facade: the bare `Storage`, or a qualified
 * or fully qualified name ending in `\Storage` (`\Illuminate\Support\Facades\Storage`).
 * Class names are case-insensitive in PHP. An alias (`use … Storage as Disk`) is not
 * resolved, which no test in this repository uses.
 *
 * @param  array{0: int, 1: string, 2: int}  $token
 */
function isStorageFacadeName(array $token): bool
{
    if ($token[0] === T_STRING) {
        return strcasecmp($token[1], 'Storage') === 0;
    }

    if ($token[0] !== T_NAME_QUALIFIED && $token[0] !== T_NAME_FULLY_QUALIFIED) {
        return false;
    }

    $last = strrchr($token[1], '\\');

    return $last !== false && strcasecmp($last, '\\Storage') === 0;
}

/**
 * The source text of a token (its literal for a single-char token).
 *
 * @param  array{0: int, 1: string, 2: int}|string  $token
 */
function tokenText(array|string $token): string
{
    return is_array($token) ? $token[1] : $token;
}

/**
 * Collect the tokens inside a parenthesised call, starting at its opening `(`, up
 * to the matching close paren (tracking nesting). Returns the inner tokens and the
 * index of the closing paren.
 *
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 * @return array{0: list<array{0: int, 1: string, 2: int}|string>, 1: int}
 */
function collectCallTokens(array $tokens, int $openIndex): array
{
    $count = count($tokens);
    $depth = 0;

    /** @var list<array{0: int, 1: string, 2: int}|string> $call */
    $call = [];

    for ($i = $openIndex; $i < $count; $i++) {
        $token = $tokens[$i];

        if ($token === '(') {
            $depth++;

            if ($depth === 1) {
                continue;
            }
        } elseif ($token === ')') {
            $depth--;

            if ($depth === 0) {
                return [$call, $i];
            }
        }

        $call[] = $token;
    }

    return [$call, $count - 1];
}

/**
 * Whether any string literal within the call hardcodes a `framework/testing` path.
 *
 * @param  list<array{0: int, 1: string, 2: int}|string>  $callTokens
 */
function callHardcodesFrameworkTesting(array $callTokens): bool
{
    foreach ($callTokens as $token) {
        if (is_array($token)
            && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)
            && str_contains($token[1], 'framework/testing')
        ) {
            return true;
        }
    }

    return false;
}

/**
 * Whether the call contains a per-run uniqueness token — a uniqueness function
 * call, a `TEST_TOKEN` reference, or the shared throwaway-root helper — which
 * makes the path unique per run.
 *
 * @param  list<array{0: int, 1: string, 2: int}|string>  $callTokens
 */
function callHasUniquenessToken(array $callTokens): bool
{
    foreach ($callTokens as $token) {
        if (! is_array($token)) {
            continue;
        }

        if ($token[0] === T_STRING
            && (in_array($token[1], UNIQUENESS_IDENTIFIERS, true)
                || in_array($token[1], UNIQUENESS_HELPERS, true)
                || $token[1] === 'TEST_TOKEN')
        ) {
            return true;
        }

        if (in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)
            && str_contains($token[1], 'TEST_TOKEN')
        ) {
            return true;
        }
    }

    return false;
}

/**
 * Scan a file's PHP tokens for Rule F: a band test that writes an entry or a term
 * while the file's base leaves them in the shared content mirror. The class
 * docblock says how a write is recognized and what is out of reach.
 *
 * @param  string  $contents  The PHP source to scan.
 * @return list<array{line: int, rule: string, snippet: string}>
 */
function scanForMirrorWrites(string $contents): array
{
    $tokens = token_get_all($contents);
    $imports = importedNames($tokens);

    if (! usesSharedMirrorBase($tokens, $imports)) {
        return [];
    }

    $lines = explode("\n", $contents);
    $count = count($tokens);

    /** @var list<array{line: int, rule: string, snippet: string}> $violations */
    $violations = [];

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (! is_array($token) || $token[0] !== T_STRING || ! inListIgnoringCase($token[1], TEST_FUNCTIONS)) {
            continue;
        }

        // A method, a static call or a declaration that shares the name is not Pest's
        $previous = previousMeaningful($tokens, $i - 1);

        if ($previous !== null && is_array($tokens[$previous]) && in_array($tokens[$previous][0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], true)) {
            continue;
        }

        $open = nextMeaningful($tokens, $i + 1);

        if ($open === null || $tokens[$open] !== '(') {
            continue;
        }

        [$callTokens, $end] = collectCallTokens($tokens, $open);

        // A test chained ->group('serial') runs in the serial tail, not the band
        if (! inListIgnoringCase($token[1], HOOK_FUNCTIONS) && chainHasSerialGroup($tokens, $end)) {
            $i = $end;

            continue;
        }

        foreach (entryTermWrites($callTokens, $imports) as $line) {
            $violations[] = [
                'line' => $line,
                'rule' => 'F (band test writes an entry or term to the shared content mirror)',
                'snippet' => trim($lines[$line - 1] ?? ''),
            ];
        }

        $i = $end;
    }

    return $violations;
}

/**
 * The lines in one test body where an entry or a term is written: a write method
 * called on a variable that holds one, or at the end of a chain in the statement
 * that makes or fetches one.
 *
 * @param  list<array{0: int, 1: string, 2: int}|string>  $tokens  The test call's tokens.
 * @param  array<string, string>  $imports  Lowercased alias to fully qualified name.
 * @return list<int>
 */
function entryTermWrites(array $tokens, array $imports): array
{
    /** @var array<string, true> $held */
    $held = [];

    /** @var list<int> $lines */
    $lines = [];

    foreach (statements($tokens) as $statement) {
        $makesOne = false;

        foreach ($statement as $index => $token) {
            if (isEntryTermSource($statement, $index, $imports)) {
                $makesOne = true;
            }
        }

        $holdsOne = $makesOne;

        foreach ($statement as $token) {
            if (is_array($token) && $token[0] === T_VARIABLE && isset($held[$token[1]])) {
                $holdsOne = true;
            }
        }

        // `$x = <an entry or term, or a variable holding one>` makes $x hold one
        $first = nextMeaningful($statement, 0);
        $assign = $first !== null ? nextMeaningful($statement, $first + 1) : null;

        if ($holdsOne && $first !== null && $assign !== null && is_array($statement[$first]) && $statement[$first][0] === T_VARIABLE && $statement[$assign] === '=') {
            $held[$statement[$first][1]] = true;
        }

        foreach ($statement as $index => $token) {
            if (! is_array($token) || $token[0] !== T_STRING || ! inListIgnoringCase($token[1], ENTRY_TERM_WRITES)) {
                continue;
            }

            $arrow = previousMeaningful($statement, $index - 1);
            $paren = nextMeaningful($statement, $index + 1);

            if ($arrow === null || $paren === null || $statement[$paren] !== '(' || ! is_array($statement[$arrow]) || ! in_array($statement[$arrow][0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)) {
                continue;
            }

            $receiver = previousMeaningful($statement, $arrow - 1);
            $onHeld = $receiver !== null && is_array($statement[$receiver]) && $statement[$receiver][0] === T_VARIABLE && isset($held[$statement[$receiver][1]]);

            if ($onHeld || $makesOne) {
                $lines[] = $token[2];
            }
        }
    }

    return $lines;
}

/**
 * Split a token list into statements at `;`, `{` and `}`, so a statement inside a
 * block or a closure is its own unit.
 *
 * @param  list<array{0: int, 1: string, 2: int}|string>  $tokens
 * @return list<list<array{0: int, 1: string, 2: int}|string>>
 */
function statements(array $tokens): array
{
    $statements = [];
    $current = [];
    $depth = 0;

    foreach ($tokens as $token) {
        // Parentheses keep a closure argument or a call's arguments with its statement
        if ($token === '(') {
            $depth++;
        } elseif ($token === ')') {
            $depth--;
        }

        if ($depth <= 0 && in_array($token, [';', '{', '}'], true)) {
            if ($current !== []) {
                $statements[] = $current;
            }

            $current = [];

            continue;
        }

        $current[] = $token;
    }

    if ($current !== []) {
        $statements[] = $current;
    }

    return $statements;
}

/**
 * Whether the token at `$index` starts a static call on the entry or term facade,
 * or follows a `new` of the entry or term class.
 *
 * @param  list<array{0: int, 1: string, 2: int}|string>  $tokens
 * @param  array<string, string>  $imports  Lowercased alias to fully qualified name.
 */
function isEntryTermSource(array $tokens, int $index, array $imports): bool
{
    $token = $tokens[$index];

    if (! is_array($token) || ! in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
        return false;
    }

    $name = resolveName($token, $imports);
    $next = nextMeaningful($tokens, $index + 1);

    if (in_array($name, ENTRY_TERM_FACADES, true) && $next !== null && is_array($tokens[$next]) && $tokens[$next][0] === T_DOUBLE_COLON) {
        $member = nextMeaningful($tokens, $next + 1);

        // `Entry::class` names the class; it makes or fetches nothing
        return $member !== null && strcasecmp(tokenText($tokens[$member]), 'class') !== 0;
    }

    $previous = previousMeaningful($tokens, $index - 1);

    return in_array($name, ENTRY_TERM_CLASSES, true) && $previous !== null && is_array($tokens[$previous]) && $tokens[$previous][0] === T_NEW;
}

/**
 * Whether the file's `uses()` binds it to a base in {@see SHARED_MIRROR_BASES}.
 *
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 * @param  array<string, string>  $imports  Lowercased alias to fully qualified name.
 */
function usesSharedMirrorBase(array $tokens, array $imports): bool
{
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (! is_array($token) || $token[0] !== T_STRING || strcasecmp($token[1], 'uses') !== 0) {
            continue;
        }

        $open = nextMeaningful($tokens, $i + 1);

        if ($open === null || $tokens[$open] !== '(') {
            continue;
        }

        [$callTokens] = collectCallTokens($tokens, $open);

        foreach ($callTokens as $index => $argument) {
            if (! is_array($argument) || ! in_array($argument[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }

            if (in_array(resolveName($argument, $imports), SHARED_MIRROR_BASES, true)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * The file's top-level `use` imports, as lowercased alias to fully qualified name.
 *
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 * @return array<string, string>
 */
function importedNames(array $tokens): array
{
    $imports = [];
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (! is_array($token) || $token[0] !== T_USE) {
            continue;
        }

        $name = nextMeaningful($tokens, $i + 1);

        // A closure's `use (` and a `use function` or `use const` import no class
        if ($name === null || ! is_array($tokens[$name]) || ! in_array($tokens[$name][0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            continue;
        }

        $fqcn = ltrim($tokens[$name][1], '\\');
        $alias = substr((string) strrchr('\\'.$fqcn, '\\'), 1);
        $after = nextMeaningful($tokens, $name + 1);

        if ($after !== null && is_array($tokens[$after]) && $tokens[$after][0] === T_AS) {
            $aliasToken = nextMeaningful($tokens, $after + 1);
            $alias = $aliasToken !== null ? tokenText($tokens[$aliasToken]) : $alias;
        }

        $imports[strtolower($alias)] = $fqcn;
    }

    return $imports;
}

/**
 * A class name as written, resolved through the file's imports. The suite's test
 * files declare no namespace, so an unimported name resolves to itself.
 *
 * @param  array{0: int, 1: string, 2: int}  $token
 * @param  array<string, string>  $imports  Lowercased alias to fully qualified name.
 */
function resolveName(array $token, array $imports): string
{
    if ($token[0] === T_NAME_FULLY_QUALIFIED) {
        return ltrim($token[1], '\\');
    }

    $parts = explode('\\', $token[1], 2);
    $head = $imports[strtolower($parts[0])] ?? $parts[0];

    return isset($parts[1]) ? $head.'\\'.$parts[1] : $head;
}

/**
 * Whether the method chain that follows a call's closing paren includes
 * `->group(...)` with `serial` among its arguments.
 *
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 * @param  int  $closeIndex  The index of the call's closing paren.
 */
function chainHasSerialGroup(array $tokens, int $closeIndex): bool
{
    $index = nextMeaningful($tokens, $closeIndex + 1);

    while ($index !== null && is_array($tokens[$index]) && $tokens[$index][0] === T_OBJECT_OPERATOR) {
        $method = nextMeaningful($tokens, $index + 1);
        $open = $method !== null ? nextMeaningful($tokens, $method + 1) : null;

        if ($method === null || $open === null || $tokens[$open] !== '(') {
            return false;
        }

        [$arguments, $close] = collectCallTokens($tokens, $open);

        if (strcasecmp(tokenText($tokens[$method]), 'group') === 0) {
            foreach ($arguments as $argument) {
                if (is_array($argument) && $argument[0] === T_CONSTANT_ENCAPSED_STRING && trim($argument[1], '\'"') === 'serial') {
                    return true;
                }
            }
        }

        $index = nextMeaningful($tokens, $close + 1);
    }

    return false;
}

/**
 * Whether a name is in a list, compared without regard to case, as PHP compares
 * function and method names.
 *
 * @param  string  $name  The name as written.
 * @param  list<string>  $list  The names to look for.
 */
function inListIgnoringCase(string $name, array $list): bool
{
    foreach ($list as $candidate) {
        if (strcasecmp($name, $candidate) === 0) {
            return true;
        }
    }

    return false;
}
