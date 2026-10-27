# Spelling dictionary entries in this repository

The convention and the reasons that justify an exception are in the shared [`american-english-prose`](../.claude/rules/american-english-prose.md) rule. This note records how that rule applies here: the commands, and the entries in [`project-words.txt`](../project-words.txt) whose reasons this repository has already established.

## The check

- `npm run spell` runs `cspell` against [`cspell.json`](../cspell.json). The `spell` job is not diff-scoped: it reads the whole tree on every run, so an entry added or removed in one branch changes the result for every other branch.
- The word list below the header comment is sorted by code point on the lowercased word, and [`scripts/project-words-sorted.test.mjs`](../scripts/project-words-sorted.test.mjs) fails on an unsorted entry or on a comment line below the header (UAMS-Web/uams-statamic-archive#1922, UAMS-Web/uams-statamic-archive#2284).
- The reason for an entry goes in the pull request that adds it. `git log -S'<word>' --oneline -- project-words.txt` finds that commit.

## Entries with an established reason

| Entry | Category in the shared rule | Why it stays |
| --- | --- | --- |
| `analyse` | A name that cannot be reworded | `phpstan analyse` is the command the `phpstan` job runs, and `excludePaths.analyse` is a key in [`phpstan.neon.dist`](../phpstan.neon.dist). Neither is free text. |
| `Centreville` | A name that cannot be reworded | A place name. Its entry dates from the commit that adopted cspell. |

The British spelling of `defense` was removed on UAMS-Web/uams-statamic-archive#2318. The shared rule records why as its worked example, with the counts measured in this repository: the standardized data it was meant to protect is covered by `ignorePaths` under `app/Enums/`, or sits in files cspell does not scan under `app/Services/EnumGeneration/Schema/`.
