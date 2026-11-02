---
name: wcag-contrast
description: >-
  Compute the WCAG 2.x contrast ratio between two colors and report AA/AAA pass-fail for normal
  and large text. Bundles `check-contrast.mjs`, a dependency-free Node CLI that parses `oklch()`,
  hex, and `rgb()` colors (converting OKLCH to sRGB via the OKLab matrix), so it works directly on
  the values DevTools reports for a Tailwind v4 OKLCH theme. Activate whenever checking color
  contrast or accessibility compliance, reviewing dark-mode or light-mode text legibility, picking
  an accessible text/border color against a known background, verifying a WCAG AA criterion, or
  when the user mentions contrast ratio, WCAG, AA/AAA, `oklch`, or whether text is readable.
---
<!--
  Synced from UAMS-Web/uams-claude-skills: shared/.claude/skills/wcag-contrast/SKILL.md
  Edit it there, not here; the next sync overwrites this copy.
  Delivered to this repository through the profile(s): frontend.
-->
<!-- cspell:ignore oklch oklab -->

# WCAG Contrast Checker

A dependency-free script for checking whether a foreground/background color pair meets WCAG 2.x
contrast thresholds. Built because a Tailwind v4 theme reports colors as `oklch(...)` in
DevTools, which is awkward to eyeball: paste the two values straight in and get a verdict.

## When to use it

- A reviewer (or you) flags low-contrast text: paste the DevTools `color` and `background-color`
  to get the ratio and AA/AAA verdicts.
- Choosing a dark-mode (or light-mode) text/border token against a known card background.
- Verifying an `## Acceptance criteria` item like "label text meets WCAG AA" before claiming it.

## How to run

```bash
node .claude/skills/wcag-contrast/check-contrast.mjs "<foreground>" "<background>"
```

Colors may be `oklch(L C H)` (lightness as `0-1` or `%`), `#rgb` / `#rrggbb`, or `rgb(r g b)` /
`rgb(r,g,b)`. Order does not matter: the ratio is symmetric. Example (a dark label on a dark
card):

```bash
node .claude/skills/wcag-contrast/check-contrast.mjs "oklch(0.37 0.013 285.805)" "oklch(0.236 0.006 286.015)"
# ratio 1.59:1, AA normal FAIL (too dark a label on the dark card)
```

The script exits non-zero when the pair fails AA for normal text (`< 4.5:1`), so it doubles as a
gate in a shell loop or CI step.

## Thresholds it reports

- **AA normal text**: `>= 4.5:1` (body copy, small labels, the usual default).
- **AA large text**: `>= 3.0:1` (>= 24px, or >= 18.66px bold).
- **AAA normal / large**: `>= 7.0:1` / `>= 4.5:1`.

Match the bold/size of the actual element to the right row: a small uppercase label is "normal
text" and needs `4.5:1`, not the `3.0:1` large-text bar.

The OKLCH to sRGB path uses the standard OKLab matrices; out-of-gamut channels are clamped to
`[0, 1]` before computing luminance, which matches how a browser would render them.

## Notes for this project

- The worked example above is the GPS fieldtype label bug (UAMS-Web/uams-statamic-archive#961).
- The CP dark-mode field card background is `dark:bg-gray-850`, which resolves to the theme's
  `zinc-850` = `oklch(0.236 0.006 286.015)`. Readable dark-mode text on it uses the core `gray`
  scale: `dark:text-gray-300` (about 11:1) or `dark:text-gray-200` (about 13:1), matching the
  convention in `resources/js/components/fieldtypes/ProductFieldtype.vue`. Avoid inventing
  `dark-*` shades; they are not defined and silently fail.
