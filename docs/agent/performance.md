---
title: Performance: a static site that stays fast
read-when: before changing a page, an image, a font, a script, a style or a dependency
---
Every page is a prebuilt file served from the edge: there is no server time to save, only bytes
and render work in the visitor's browser. Keep it that way.

## Budgets (mobile Lighthouse, every page)
Performance ≥ 95, Accessibility ≥ 95, Best Practices ≥ 95, SEO ≥ 95; LCP < 2.0 s, CLS < 0.05,
INP < 200 ms. JavaScript: ONE file today, Astro's prefetch module (~1 KB gzipped, `prefetch` in
`astro.config.mjs`); nothing else by default, ≤ 20 KB gzipped on a page whose feature truly needs
some. HTML + CSS above the fold ≤ 50 KB gzipped.

## Navigation: instant, and still plain pages
Every page is a real HTML file at its own URL (the best case for search). Clicks feel instant
because of two things already on; keep both, add nothing in their place:
- **Prefetch** (`prefetch: {prefetchAll: true, defaultStrategy: 'hover'}`): a same-site link is
  fetched when the visitor hovers or focuses it; the header nav (`data-astro-prefetch="viewport"`
  in `Base.astro`) as soon as it is on screen. Astro skips it on slow connections and Data
  Saver. A link that must never be fetched ahead: `data-astro-prefetch="false"`. Never
  `viewport` or `load` on a long list of links (it downloads them all).
- **Client navigation**: `<ClientRouter />` (Astro's, in `Base.astro`) swaps the next page in
  without a reload, keeps history and scroll, moves focus and announces the new title. Every
  page is still a real prerendered HTML file. The header holds still across pages
  (`view-transition-name: site-header`); a `view-transition-name` is unique per page. The
  cross-document `@view-transition` in `global.css` covers full loads (inside
  `prefers-reduced-motion: no-preference`; the router follows the same preference).
- Never another router, or `fetch()` + `innerHTML` to swap pages: the ClientRouter does it.

## JavaScript
- Pages ship no JavaScript of their own (the exceptions: the Buy button's small module,
  `payments.md`, and the live prices script); the only other scripts are Astro's prefetch and client
  router (above: the exceptions to the budget, deferred modules from this host, allowed by the
  CSP). If a page ever needs a script: a bundled `<script>` runs ONCE per visit, so put its work
  in `document.addEventListener('astro:page-load', () => { … })` and return early when the
  page's own element is missing (that event fires on every page). A
  `<script>` in a page is bundled as a deferred module: add one only for real interactivity,
  keep it small and page-specific.
- No `is:inline` scripts, no scripts of your own in `<head>`, no analytics / chat / tag-manager snippets
  unless the owner insists (they also break the CSP: `security.md`).
- No framework islands unless a widget truly needs one; then `client:visible`, never
  `client:load` on content pages. Don't add React/Vue/Svelte for one interaction: a `<details>`,
  a CSS `:target` or a few lines of a module script usually do.
- No runtime data fetching. Content is in the repo and built into the HTML; a list that changes
  now and then is a content collection, rebuilt at the next Publish.

## Images
- PREFERRED: the file in `src/assets/`, rendered through `astro:assets`: it is resized and
  converted at build (AVIF + WebP + a JPEG fallback, several widths). Use `Cover`
  (`src/components/Cover.astro`, what blog covers use) or `<Picture src={img} widths={[…]}
  sizes="…" formats={['avif', 'webp']} alt="…" />`. `sizes` says how wide the slot really is
  (`(min-width: 752px) 720px, calc(100vw - 32px)`), so phones download the small file.
- `public/images/` serves a file exactly as committed: only for files that must keep a fixed
  name; resize them yourself (≤ 2× the displayed width, AVIF/WebP).
- Always `width` + `height` (or `aspect-ratio` in CSS) so nothing shifts.
- Below the fold: lazy (the default in `Cover`; `loading="lazy"` + `decoding="async"` on an
  `<img>`). The ONE LCP image (a hero, a post's cover): `eager` / `fetchpriority="high"`.
  Decorative images `alt=""` (`accessibility.md`).
- Blog covers: 1200×630 or larger source, ≤ 500 KB source in `src/assets/` (the build makes
  the small files). Never a multi-MB photo straight from a camera.
- No on-the-fly image service: everything is made at build time.

## Fonts
- The system stack is the default (no request at all).
- A web font: self-host `woff2` in `public/fonts/`, ≤ 2 weights, `font-display: swap`, and
  `<link rel="preload" as="font" type="font/woff2" crossorigin>` the one used above the fold.
  No Google Fonts link (an extra origin, and the CSP allows `font-src 'self'` only).

## CSS
- One stylesheet (`src/styles/global.css`), tokens on `:root`, a dark block beside it. It is
  inlined into every page (`build.inlineStylesheets: 'always'`): no render-blocking request.
  That only stays cheap while it is small (≤ ~15 KB): delete rules nothing uses, don't paste
  in a library. Page-only styles go in a `<style>` in that page (Astro scopes and hashes it).
- No CSS framework on top unless the owner asks; if Tailwind is asked for, use the Astro
  integration (build-time), never the CDN script.
- Animate `transform` and `opacity` only; respect `prefers-reduced-motion`.

## Dependencies and build
- Every dependency is weight and risk: prefer the platform (HTML, CSS, Astro) over a package.
- `npm run build` must stay quick and deterministic: nothing fetched from the network at build
  time unless the owner asked (it is frozen until the next Publish anyway).
- Hashed assets in `/_astro/*` are cached for a year by `public/_headers`; keep files you
  reference by a fixed name (`/images/…`) small and change their name when they change.

## Never
Blocking scripts in `<head>`, images without dimensions, an `<img>` of a big `src/assets` file
without `astro:assets`, a font from another origin, a UI kit for one component, runtime fetches
for content, autoplaying video above the fold.

## Measuring
- Core Web Vitals are judged at the 75th percentile of real visits on phones: aim well under the
  budgets above, not at them. Server time (TTFB) < 200 ms: a prebuilt file, so a slow one means a
  redirect (link with the trailing `/`) or a big HTML file.
- Measure on the LIVE site (the Preview runs a dev server: unbundled, slower): Chrome DevTools
  Lighthouse (mobile) and the Performance and Network panels.
- Script sizes after `npm run build`:
  `find dist -path '*/_astro/*.js' -exec sh -c 'echo "$(gzip -c "$1" | wc -c) $1"' _ {} \; | sort -n`.

## Check
`npm run build` (the build log lists every optimised image), then in the Preview: no layout shift
while loading, nothing in the Network panel from another origin, no JavaScript but the one
prefetch module (`/_astro/page.*.js`), and hovering a link fetches its page.

## Page progress bar
Built in (`src/lib/page-progress.ts`): a thin bar in `--accent` while the next page loads, hidden on a fast one. Nothing to add per page. Never add another.
