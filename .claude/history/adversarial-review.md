# History: Rule — adversarially verify before it reaches anyone else

The incident history behind [`.claude/rules/adversarial-review.md`](../rules/adversarial-review.md): the measurements, observations and worked examples that justify its instructions. It was moved here from the auto-loaded file by UAMS-Web/uams-statamic-archive#2693, and each section matches the heading it came from. The wording and dates are unchanged.

**Worked example — a published claim with no diff anywhere near it.** Several concurrent sessions published measurements to a shared channel, then audited their own published output and reported it clean. Every audit was later found incomplete, and in every case the omission was found by a *different* session reading the same material — generated files, shell-state mutation, destroyed positional parameters, and an audit expression that could not match the defect it was written to find. One session missed three classes in three successive audits of the same two messages; none of the three was found by auditing harder. No diff, no suite, and no merge appeared anywhere in the sequence, so nothing triggered this rule, and its central principle was re-derived from scratch over roughly forty exchanges by people who were editing this file'"'"'s neighbors the same evening.

## How to apply

Observed here: three parallel bands were launched to satisfy a ticket's acceptance criterion while the review was still running, and the review then turned up a blocker that rewrote the file under test, voiding them.

## Tooling that raises the floor (so the review can reach the ceiling)

Observed on `UAMS-Web/wordpress-importer#739`, where two "survivors" were `perl` substitutions that never fired — re-run with an asserted replacement, both were killed.

Measured on Windows against `v5.0.2`, one class in one tree: the two forward-slashed forms reported `20 tested, 0 untested, 100.00%` while two separator-correct forms reported `14 tested, 6 untested, 70.00%` (UAMS-Web/uams-statamic-archive#2322).

Measured that way on a sibling repository's Windows checkout (`UAMS-Web/uamswp-migration-api#179`): `Auth.php` line 141 `RemoveStringCast` kept reporting killed across four invocation forms **and `composer mutate`**, with real per-mutant timing and 37 other mutants resolving correctly — a genuine run producing one fabricated kill, which is not the whole-run failure UAMS-Web/uams-statamic-archive#2322 describes.

    Measured on Windows 11 Pro 10.0.26200 / PHP 8.4.20 / v5.0.2, before and after the patch:

    | class | covering file | before | after |
    | --- | --- | --- | --- |
    | `App\Jobs\UnpublishExpiredClassifieds` | `serial` | `11 tested, 100.00%` in 0.53s | `4 untested, 1 timeout, 6 tested` |
    | `App\Modifiers\SanitizeUrl` | not `serial` | `34 tested, 100.00%` | `3 untested, 31 tested` (`untested` is Pest's word for a survivor): the three equivalents the test file already records, one confirmed by hand |
