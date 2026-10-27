# "Opens in new window" link indicators (WCAG 3.2.5)

Every link that opens in a new window (`target="_blank"`) must tell the user so
*before* activation — a visible cue for sighted users and a programmatic one for
screen-reader users. This is the codebase-side implementation of the UAMS-wide
convention (UAMS-Web/uams-statamic-archive#701, UAMS-Web/uams-statamic-archive#702).

- **Convention & wording:** <https://communications.uams.edu/web/kb/links-opening-in-new-windows/>
  and the [UAMS-Web/uams-statamic-archive#701 resolution comment](https://github.com/UAMS-Web/uams-statamic-archive/issues/701#issuecomment-4521981999).
  Canonical screen-reader string: `opens in new window` (no parentheses).

## Where the shared pieces live

- **[`resources/views/components/_new_window_indicator.antlers.html`](../../resources/views/components/_new_window_indicator.antlers.html)**
  — the indicator itself: a decorative Phosphor `arrow-square-out` glyph
  (`0.85em`, `currentColor`, `margin-left: 0.25em`, `aria-hidden`,
  `focusable="false"`) followed by `<span class="sr-only">opens in new window</span>`.
  Drop it immediately after the link text, **inside** the `target="_blank"`
  anchor.
- **[`resources/views/components/_external_link.antlers.html`](../../resources/views/components/_external_link.antlers.html)**
  — a convenience wrapper that renders a full text link
  (`<a target="_blank" rel="noopener noreferrer">` + indicator). Use
  `{{ partial:components/external_link url="…" text="…" }}` for a plain new-window
  text link.

## How to apply it

- **Text links:** render `{{ partial:components/new_window_indicator }}` just
  before `</a>` (gate it on the same condition that adds `target="_blank"`), or
  use the `external_link` wrapper.
- **Icon-only links** (social icons, chevrons): do **not** add the visible glyph.
  Instead make the accessible name end with `opens in new window` — append it to
  the `sr-only` label or the `aria-label` (e.g. `aria-label="Facebook, opens in new window"`).
- **Links with an `aria-label`:** the label overrides inner text, so append
  `, opens in new window` to the label (the visible glyph can still be added
  decoratively).

## Related

- `rel="noopener noreferrer"` on Bard-authored new-window links is backstopped at
  render by the `rel_noopener` modifier — see [`app/Modifiers/RelNoopener.php`](../../app/Modifiers/RelNoopener.php) (UAMS-Web/uams-statamic-archive#724).
- Rel-attribute template sweep: UAMS-Web/uams-statamic-archive#603 / UAMS-Web/uams-statamic-archive#723.
