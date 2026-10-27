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

**The 10-assertion difference against 8.4.25 is unattributed.** Every test count matches; only the parallel assertion total moved. The 8.4.25 side has one sample, cannot be resampled (that interpreter is no longer installed), and its per-test table was overwritten by the next chain. So it is a candidate runtime effect, not a demonstrated one. The conditional assertions in [`tests/Feature/TestBootstrapCostTest.php`](../../tests/Feature/TestBootstrapCostTest.php) do not explain it: that test contributed 4 assertions in both 8.4.27 runs, which bounds its swing at 2.

## Comparing a new chain against this baseline

- Compare like with like: the same commit or tree, the same interpreter version, and the rest of the checklist in [`measurement-parity`](../../.claude/rules/measurement-parity.md).
- A different version on the `under PHP` line means the figures are a new baseline, not a regression or an improvement against this one.
- Test counts should match exactly. A small assertion difference at an identical test count is the conditional-assertion drift that rule describes; a difference of tens needs a per-test comparison of the two JUnit reports before it is attributed to anything.
