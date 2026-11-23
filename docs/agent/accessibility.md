---
title: Accessibility: every visitor can use every page
read-when: before changing markup, colours, links, buttons, forms, images or motion
---
Target: WCAG 2.2 AA, Lighthouse Accessibility ≥ 95 on every page, in light AND dark mode.

## Structure
- One `<h1>` per page (a post's title), headings in order (`##` first inside Markdown). In a
  list of cards the card titles are one level below the heading above them: `PostCard as="h2"`
  directly under a page's `<h1>`, the default `h3` under a section's `<h2>`.
- Landmarks from `src/layouts/Base.astro`: `header`, `nav` (with `aria-label`), `main id="main"`,
  `footer`. Keep the skip link ("Skip to content") as the first focusable element.
- `lang` on `<html>` comes from `SITE.lang`; a passage in another language gets its own `lang`.
- The current page in the nav has `aria-current="page"` (Base does it; keep it on new links).

## Links and buttons
- Navigation is a real `<a href>`; an action is a `<button type="button">`. Never a clickable
  `<div>` or `<span>`, never `href="#"` or `javascript:`.
- Link text says where it goes ("Read the pricing guide", not "click here"). A link that opens a
  new tab says so and has `rel="noopener noreferrer"`.
- Keyboard: everything reachable with Tab in visual order, a visible focus ring (`:focus-visible`,
  never `outline: none` without a replacement). Touch targets ≥ 44×44 px.
- Icon-only controls have `aria-label`; decorative icons `aria-hidden="true"`.
- A button that starts work (rare on a static site) shows it: present-tense label ("Loading…"),
  `aria-busy="true"`, `disabled` until done, then the result or the error in words.

## Colour and motion
- Text contrast ≥ 4.5:1 (large text ≥ 3:1), UI borders and focus rings ≥ 3:1, in BOTH colour
  schemes: change the light and dark tokens in `global.css` together and check both.
- Never colour alone to carry meaning (add a word or an icon).
- Respect `prefers-reduced-motion`: no parallax, auto-scrolling or large animations when set.
  The page-to-page cross-fade (`@view-transition` in `global.css`) sits inside
  `prefers-reduced-motion: no-preference`; keep any new transition there too, short (≤ 300 ms)
  and opacity/transform only.
- Navigation is client-side (`<ClientRouter />`, `performance.md`): it moves focus to the new
  page and announces its title, as a full load does. Keep the ClientRouter; never swap pages
  with your own JavaScript.
- No autoplaying sound; video starts paused, with controls and captions.

## Images and media
- Content images: `alt` that says what the image shows or does. Decorative: `alt=""`.
- A blog cover's `coverAlt` front matter is its alt text ("" when decorative).
- Embedded video (`<iframe>`): a `title` attribute naming it.

## Forms (if a page ever has one)
A static site cannot receive a form (see AGENTS.md); a search box or a filter in the browser may
still be one. Then: `novalidate`, a visible `<label>` per field (placeholders are examples, never
labels), errors under the field in words with `aria-invalid` + `aria-describedby`, never browser
bubbles or `alert()`. Status messages in a `role="status"` region, errors `role="alert"`.

## Check before "done"
Tab through every changed page; zoom to 200 % (nothing overlaps or scrolls sideways at 320 px
wide); switch to dark mode; read the heading outline.
