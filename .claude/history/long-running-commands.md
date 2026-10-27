# History: Rule — bound every long-running command, and resolve what you kill

The incident history behind [`.claude/rules/long-running-commands.md`](../rules/long-running-commands.md): the measurements, observations and worked examples that justify its instructions. It was moved here from the auto-loaded file by UAMS-Web/uams-statamic-archive#2693, and each section matches the heading it came from. The wording and dates are unchanged.

Four processes were stranded this way in a single session here — a prior session's abandoned run, an import test that "timed out" into the background, a stale waiter, and a monitor — and between them they poisoned enough timings to send an afternoon chasing a performance problem that did not exist.

## How to apply

Measured on **macOS 26.4**, a 25s bound on `pest --parallel tests/Unit`: the parent died on schedule while **one `pest/bin/worker.php` survived every settle point out to +15s** — reproduced twice from a clean slot. Under the forking form, zero survivors.

The bare `perl -e 'alarm …'` form is a silent NO-OP under Git Bash: its Perl has no real `exec`, so the pending alarm dies with the emulated one and the command runs unbounded. Verified on Windows: a **10s alarm on a 30s sleep ran the full 31s**, and a soak band "bounded" at 5400s ran **6670s**. The failure is invisible — no error, just an unbounded run. **The forking form is a different matter from the bare one, and the reason to avoid it on Windows is not that it fails there.** It has been run under Git Bash — 30 controlled trials with exact-command-line filtering and 0 survivors, recorded as `UAMS-Web/uamswp-migration-api#215`, which was filed as a Windows defect in that fallback and refuted by its own re-measurement: the two survivors it reported were unrelated recurring `sleep.exe` processes caught by an unfiltered `tasklist` count. **That result is cited here rather than reproduced** — it was measured in a sibling repository, and no first-party Windows measurement of the forking form exists in this one.
>
> **Use `timeout` there anyway, and the ground is availability rather than failure**: Git Bash ships GNU `timeout` at `/usr/bin/timeout`, verified above, so the fallback is simply unnecessary. Stating it that way is deliberate — the previous wording rested the prescription on "has not been run", which a single measurement elsewhere was able to dissolve, and a rule whose reason can evaporate takes its instruction with it.

Observed here: a `Stop-Process` filtered on `*plainband.sh*` killed the very shell issuing it, because the command also wrote a file by that name.

   ```
   session's work        …/uams-statamic-b     <- the slot, where the competing run is
   ambient cwd           …/uams-statamic       <- reverted to the primary between calls
   MY_TREE=$(pwd)        …/uams-statamic       ->  no match, "clear", exit 0   WRONG
   MY_TREE carried       …/uams-statamic-b     ->  match, ABORTED, exit 3      correct
   ```

Measured here: three watcher processes at one path, indistinguishable.

   **Observed, and the cost fell on everyone except its owner.** An ad-hoc waiter armed during a coordinated quiet window carried no label. It could not be *resolved at all* — every sweep saw a `TARGET=<epoch>` string and a cwd shared by every session in the checkout. Its owner had declared it on the channel minutes earlier; the declaration and the process were both on the record and nothing joined them. Resolving it took several rounds across three sessions and finally a read of the shell-snapshot path in `argv`.

Five orphaned `sleep`s were observed in succession on one machine, re-arming at roughly the sleep's own interval, each already orphaned before it was visible — so the spawner never appeared in any sampled lineage. Every individual reading was correct and the generalization over them was wrong: a sweep that finds orphans, confirms each inert, and reports the machine clear is reporting on corpses while the thing producing them is untouched. Repeated instances at intervals near the sleep's own duration are the tell. *(Bound: opportunistic samples, so the period is inferred from spacing rather than measured.)*

## Validate the sweep before you trust its silence

Measured hunting a process known to be alive: `4` matches before the filter, `0` after, with all four true matches containing the word.

Two probes on one machine at one instant, counting the same class: `pgrep -f` returned **3**, `ps -Aww … | grep` returned **7**. The `pgrep` form passed its positive control — it located a known watcher, and truncation was excluded at 624 against 629 characters — and still reported fewer than half. Whether the extra four are distinct processes or the same shells matched twice is unresolved; the defensible statement is *"at least three, possibly seven"*, not *"seven exist"*. An instrument that finds the one process you told it to look for can still miss most of the population, and nothing in its output distinguishes those two cases.

Every error made about these counts ran in the **reassuring** direction — a live process reported gone, three reported where there may be seven. For a figure gathered to keep a window clear, a bias toward under-reporting is the worst available.

**On the shell-snapshot path in `argv`, and why it is not adopted here.** Every background shell this harness starts carries `~/.claude/shell-snapshots/snapshot-bash-<id>.sh` in its command line; the id is unique per session, lasts the process's lifetime, and is readable as a single field — the properties step 4 asks for, and it is what finally attributed the unlabeled waiter. It is **not** prescribed, for one reason: it has been observed on macOS via `ps -o command=` in this harness and is **untested under Git Bash**, and the **Supported development platforms** rule in [`AGENTS.md`](../../AGENTS.md) forbids writing a portable-sounding remedy that has been measured on one platform. It is recorded here as a fallback worth testing rather than a method to reach for — and it does not replace step 4's obligation either way, since it identifies a *session* and not an *intent*.

## The same blindness on an outward-facing write

Measured here: six sessions surveyed, four had piped every post through `tail -N` for a whole working session, reading the filter's status rather than the tool's; one session had done it 428 times.

Observed three times in one evening on this fleet, and the only reason one instance failed loudly rather than silently is that the scratch directory had been deleted, so the stale file was *absent* rather than *old*.
