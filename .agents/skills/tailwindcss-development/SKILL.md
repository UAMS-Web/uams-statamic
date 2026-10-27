---
name: tailwindcss-development
description: >-
  Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling
  components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode,
  colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind,
  restyle, hero section, cards, buttons, or any visual/UI changes.
---
<!--
  Synced from UAMS-Web/uams-claude-skills: shared/.claude/skills/tailwindcss-development/SKILL.md
  Edit it there, not here; the next sync overwrites this copy.
  Delivered to this repository through the profile(s): tailwindcss.
-->
<!-- cspell:ignore oklch -->

# Tailwind CSS Development

## When to Apply

Activate this skill when:

- Adding styles to components or pages.
- Working with responsive design.
- Implementing dark mode.
- Extracting repeated patterns into components.
- Debugging spacing or layout issues.

## Documentation

Use the Laravel Boost `search-docs` tool for detailed Tailwind CSS v4 patterns and version-
specific documentation. Never rely on training-data knowledge of Tailwind APIs.

## Basic Usage

- Use Tailwind CSS classes to style HTML. Check existing Tailwind conventions in the project
  **before** introducing new patterns.
- Offer to extract repeated patterns into components matching the project's conventions
  (Blade, JSX, Vue).
- Consider class placement, order, priority, and defaults. Remove redundant classes, add
  classes to parent or child elements carefully to reduce repetition, and group elements
  logically.

## Tailwind CSS v4 Specifics

- Always use Tailwind CSS v4 and avoid deprecated utilities.
- `corePlugins` is **not supported** in Tailwind v4.

### CSS-First Configuration

In Tailwind v4, configuration is CSS-first using the `@theme` directive: **no separate
`tailwind.config.js` file is needed**:

```css
@theme {
  --color-brand: oklch(0.72 0.11 178);
}
```

### Import Syntax

In Tailwind v4, import Tailwind with a regular CSS `@import` statement instead of v3's
`@tailwind` directives:

```diff
- @tailwind base;
- @tailwind components;
- @tailwind utilities;
+ @import "tailwindcss";
```

### Replaced Utilities

Tailwind v4 removed deprecated utilities. Use the replacements below. Opacity values remain
numeric.

| Deprecated | Replacement |
|------------|-------------|
| `bg-opacity-*` | `bg-black/*` |
| `text-opacity-*` | `text-black/*` |
| `border-opacity-*` | `border-black/*` |
| `divide-opacity-*` | `divide-black/*` |
| `ring-opacity-*` | `ring-black/*` |
| `placeholder-opacity-*` | `placeholder-black/*` |
| `flex-shrink-*` | `shrink-*` |
| `flex-grow-*` | `grow-*` |
| `overflow-ellipsis` | `text-ellipsis` |
| `decoration-slice` | `box-decoration-slice` |
| `decoration-clone` | `box-decoration-clone` |

## Spacing

Use `gap` utilities instead of margins for spacing between siblings:

```html
<div class="flex gap-8">
    <div>Item 1</div>
    <div>Item 2</div>
</div>
```

## Dark Mode

If existing pages and components support dark mode, new pages and components must support it the
same way, typically using the `dark:` variant:

```html
<div class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
    Content adapts to color scheme
</div>
```

## Common Patterns

### Flexbox Layout

```html
<div class="flex items-center justify-between gap-4">
    <div>Left content</div>
    <div>Right content</div>
</div>
```

### Grid Layout

```html
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <div>Card 1</div>
    <div>Card 2</div>
    <div>Card 3</div>
</div>
```

## Working to the Existing Design

Front-end work in this repository is migration work. The brief is the existing UAMS design, meaning the page as the WordPress site renders it today and the markup and classes this repository already uses. It is never a new visual identity. Match the source rather than redesigning it: a ticket that is not about design leaves palette, typefaces, spacing and motion as they are.

### Plan, Check Against the Source, Build, Critique

1. Before writing markup, name what is being reproduced: the source page or component, and the audience it serves. If the ticket does not say which page that is, find it and confirm it before building.
2. Write a short plan covering its colors, type sizes and weights, layout and alignment, and every state: hover, focus, open, empty and error. Take each value from the source or from classes already in use, not from preference.
3. Check the plan against the source before building. Each difference from the existing design is either required by the ticket or a mistake, and the pull request says which.
4. Build with the page's real content, not placeholder copy.
5. Critique the result against the source side by side. Where the environment can take screenshots, capture both at a mobile and a desktop width, and fix the differences before calling the work done.

### Restraint

- Add no decoration the source does not have: no new gradients, shadows, borders, all-caps labels above headings, or arrows appended to link text.
- Borders, dividers, labels and numbering carry meaning about the content. Use numbered markers only when the content really is a sequence, such as steps or a timeline.
- Add motion only where the source has it, or where it answers a person's action, such as opening, expanding or confirming. Respect `prefers-reduced-motion` (Tailwind's `motion-reduce:` variant).
- Watch selector specificity. A component class and an element selector that set the same padding or margin cancel each other out, most often between sections. Prefer utilities on the element itself.

Where the source falls short of the quality floor below, the floor wins over matching the source, and the pull request names the difference.

### Quality Floor

Every change meets these without being asked, and the pull request does not need to announce them:

- Responsive down to mobile widths.
- Visible keyboard focus on everything interactive.
- Reduced-motion preferences respected.
- Text contrast meeting WCAG AA, checked with the [`wcag-contrast`](../wcag-contrast/SKILL.md) skill rather than judged by eye.

### Interface Copy

These apply to the words the work adds: button and link labels, form labels, empty states, error messages, and Control Panel instructions.

- Name things by what a person understands, not by how the system is built.
- A button says what happens when it is used ("Save changes", not "Submit"), and an action keeps one name through the flow: a "Publish" button produces a "Published" message.
- An error says what went wrong and how to fix it, without apologizing and without vagueness. An empty state says what to do next.
- Use sentence case and plain verbs, with no filler, and give each piece of text one job.

Copy migrated from the WordPress source is content, and is carried over unchanged unless the ticket changes it.

## Common Pitfalls

- Using deprecated v3 utilities (`bg-opacity-*`, `flex-shrink-*`, etc.).
- Using `@tailwind` directives instead of `@import "tailwindcss"`.
- Trying to use `tailwind.config.js` instead of the CSS `@theme` directive.
- Using margins for spacing between siblings instead of `gap` utilities.
- Forgetting `dark:` variants when the project supports dark mode.

## Frontend bundling

If the user doesn't see a styling change in the UI, they may need to run `npm run build`,
`npm run dev`, or `composer run dev`. Ask.
