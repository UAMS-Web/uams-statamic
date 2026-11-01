# Runbook: local CI band baselines

A band figure is only comparable to an earlier one when both name the interpreter that produced them. Since UAMS-Web/uams-statamic-archive#3235 the local CI runner prints each Pest band next to the PHP version it resolved, both in its summary block and in the commit status it posts:

```
  PHP:      /Users/<user>/Library/Application Support/Herd/bin/php84 (8.4.27)
  parallel: 12,909 passed, 1 skipped (36,638 assertions) under PHP 8.4.27
  serial: 166 passed (540 assertions) under PHP 8.4.27
```

The band lines are counted from each band's JUnit report (`junit-parallel.xml`, `junit-serial.xml` in the CI tree), summed over every `<testcase>`. When a band wrote no report the line says so instead of being left out.

To read the interpreter back out of a saved log, use the helper. It matches the line's `PHP:` prefix rather than its position, because the header's fetch section is one line shorter when the branch already exists locally:

```bash
node scripts/local-ci-bands.mjs ci.log
```

It prints `PHP <version> (<path>)` and exits `0`, exits `1` when the log has no `PHP:` line, and exits `2` on wrong arguments.

## Baseline: `32ad877cb` under PHP 8.4.27

Recorded for UAMS-Web/uams-statamic-archive#3235. Three full chains with all 14 jobs green, on `main` at `32ad877cb9c52a89c40e7d9a8d50fd53196514f5` (tree `eff27fc0d43b4f5babcbee4c792810a6d86ae426`), macOS (Darwin 25.6.0), 2026-10-22, `Parallel: 12 processes`.

| Interpreter | Samples | Parallel band | Serial | Browser | vitest |
| --- | --- | --- | --- | --- | --- |
| 8.4.27 | 2 | 1 skipped, 12,909 passed (36,638 assertions) | 166 (540) | 60 (296) | 76 files |
| 8.4.25 | 1 | 1 skipped, 12,909 passed (36,628 assertions) | 166 (540) | 60 (296) | 76 files |

**The 8.4.27 row is the baseline.** Its two samples produced byte-identical per-test tables (`classname`, `name`, `assertions` from `junit-parallel.xml`, sorted), both summing to 36,638.

**The 10-assertion difference against 8.4.25 is unattributed.** A candidate found later: before #335, `CollectionTreeIsolationTest` moved by exactly 10 with worker state. No per-test table exists for that run, so this is a question and not an attribution. Every test count matches; only the parallel assertion total moved. The 8.4.25 side has one sample, cannot be resampled (that interpreter is no longer installed), and its per-test table was overwritten by the next chain. So it is a candidate runtime effect, not a demonstrated one. The conditional assertions in [`tests/Feature/TestBootstrapCostTest.php`](../../tests/Feature/TestBootstrapCostTest.php) do not explain it: that test contributed 4 assertions in both 8.4.27 runs, which bounds its swing at 2.

## Comparing a new chain against this baseline

- Compare like with like: the same commit or tree, the same interpreter version, and the rest of the checklist in [`measurement-parity`](../../.claude/rules/measurement-parity.md).
- A different version on the `under PHP` line means the figures are a new baseline, not a regression or an improvement against this one.
- Test counts must match exactly.
- Assertion totals on one machine are compared within a bound of 2. The one source known to vary by run is the first test in [`tests/Feature/TestBootstrapCostTest.php`](../../tests/Feature/TestBootstrapCostTest.php), which contributes 4 assertions when its worker found a snapshot at boot and 2 when it did not. A seeded band normally takes the first branch. Between machines a few tests branch on the coverage driver or the operating system, so a cross-machine total is not held to this bound.
- A larger difference at an identical test count is not noise. Name the tests before attributing it to anything: `node scripts/diff-band-assertions.mjs <first junit.xml> <second junit.xml>` prints every test whose count differs and exits 1 when any does.
- This baseline predates #335, which fixed the counts of two tests. The clamp test in `PestBrowserRetryPatchTest` went from three assertions per retry attempt to 5, and `it leaves no uri row for an entry an earlier test created and deleted` went from one per cached index to 2. A chain compared against the figures above is lower by those differences, whose sizes the baseline's per-test table would give: 46 to 5 and 32 to 2 at the counts measured below.
- `StacheSnapshotCopyVerificationTest`, which `measurement-parity` names as conditional, has no conditional assertion at this revision.

### What moved the total before #335

Measured on Windows 11 with PHP 8.4.27 at `a73ab11aa`, three parallel bands (`--exclude-group=serial`) on a loaded machine: 13,915 tests each, 4 of them failing and 16 skipped in every band, and 39,132, 39,135 and 39,132 assertions. The serial band was not measured.

- `it clamps a later attempt to what is left of the retry window` reported 40, 43 and 40, the one test that differed between those bands. It asserted once per attempt of a time-bound retry loop, three assertions an attempt, and load decides how many attempts fit in the window. Alone on the same machine it reported 46 five times and 43 once.
- `it leaves no uri row for an entry an earlier test created and deleted` reported 32 in all three bands, and 22 when run alone or after a test that does not flush the cache. It asserted once per cached `uri` index, and what the worker ran first decides how many are cached. Run after `tests/Feature/Auth/FrontEndAccountFlowTest.php`, which calls `Cache::flush()`, it reported 32. The bands never showed it move, so a three-band comparison alone would not have found it.

The 12-assertion gap this drift was first reported at (36,919 and 36,907 at one tree, macOS) equals 4 retry attempts. The ticket's third total, 36,908, is 11 and 1 from those and is not a multiple of 3, so the clamp test alone does not explain that row. None of this was reproduced on macOS, so these attributions are arithmetic, not measurements.
