# History: Rule — spend the REST quota to preserve the GraphQL one

The incident history behind [`.claude/rules/github-api-budget.md`](../rules/github-api-budget.md): the measurements, observations and worked examples that justify its instructions. It was moved here from the auto-loaded file by UAMS-Web/uams-statamic-archive#2693, and each section matches the heading it came from. The wording and dates are unchanged.

## How to apply

Carried from UAMS-Web/uams-statamic-archive#2161, which measured it; the watcher no longer reads assignees at all (UAMS-Web/uams-statamic-archive#2366), but anything that hands a pull request over still does.

Measured on `impersonal voice` in this repo — the wrapper returned issue UAMS-Web/uams-statamic-archive#2164 and never PR UAMS-Web/uams-statamic-archive#2178 (UAMS-Web/uams-statamic-archive#2205).

   **No lag is claimed here, because none survived reproduction.** One apparent stale read was recorded on 2026-09-05 — a comment absent from three query shapes at roughly two minutes old, present at six. A same-age reproduction by the same observer, three samples at 69s / 130s / 251s each paired with a working control, found it **present every time**, and four other observers found their own artifacts present. Five present against one absent, and the absent one is the one nobody can repeat. It is recorded because an observation nobody could reproduce is not a refuted one, and it is recorded as *not reproduced* so nobody builds a threshold on it.
