# History: Rule — no emoji in durable records

The incident history behind [`.claude/rules/no-emoji-in-durable-records.md`](../rules/no-emoji-in-durable-records.md): the measurements, observations and worked examples that justify its instructions. It was moved here from the auto-loaded file by UAMS-Web/uams-statamic-archive#2693, and each section matches the heading it came from. The wording and dates are unchanged.

## How to apply

   **The script above is measured against that failure rather than assumed immune.** Run over a fixture carrying `U+274C`, under a stdout forced to `cp1252` (`PYTHONIOENCODING=cp1252`, Python 3.12, Windows), it reports the finding and exits cleanly:

   ```
   stdout      1 ['U+274C']
   stderr      (empty)
   exit code   0
   ```

   That is this rule's own negative control, and it is the reason the code-point form is prescribed rather than merely preferred: the same probe printing the matched **line** dies on that character with an empty stdout.
