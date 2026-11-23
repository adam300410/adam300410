import {defineConfig, envField} from 'astro/config';

/**
 * A STATIC site: every page is prerendered to plain files in `dist/` (no
 * adapter, no server code). The platform serves them as they are; a root
 * `404.html` answers every miss. The blog is Markdown in `src/content/blog/`,
 * built into pages like everything else.
 *
 * Search and feeds are files too: `/sitemap.xml`, `/robots.txt` and
 * `/rss.xml` are built from `src/pages/*.xml.ts` / `robots.txt.ts`.
 *
 * Navigation is client-side (`<ClientRouter />` in Base.astro) between real
 * prerendered pages (best for search): the next page is fetched ahead by
 * `prefetch` (below) and swapped in without a reload.
 */

export default defineConfig({
  output: 'static',
  // Fetch a page before the click: every same-site link when the visitor
  // hovers or focuses it (`hover`: only pages someone is about to open, so no
  // wasted bytes), the header nav as soon as it is on screen
  // (`data-astro-prefetch="viewport"` in Base.astro: phones have no hover).
  // Skipped on slow connections and with Data Saver. With the client router,
  // the site's only scripts: small deferred modules from 'self' (performance.md).
  prefetch: {prefetchAll: true, defaultStrategy: 'hover'},
  // Directory URLs (`/about/`) with trailing slashes, as a static host serves them.
  trailingSlash: 'ignore',
  build: {
    format: 'directory',
    // The stylesheet is small: inline it in every page so nothing blocks the
    // first paint (no extra request; the CSP hashes it).
    inlineStylesheets: 'always',
  },
  env: {
    schema: {
      // The site's own address, set by the platform during a Publish build. Read
      // only through src/lib/site-url.ts (canonical URLs, sitemap, RSS).
      SITE_URL: envField.string({context: 'server', access: 'public', optional: true}),
    },
  },
  // No Markdown code blocks here; Shiki's inline styles would break the CSP.
  markdown: {syntaxHighlight: false},
  security: {
    // A Content-Security-Policy in every page, with a hash for each script and
    // style Astro emits. The browser talks only to this host (`connect-src
    // 'self'`); images may come from any https CDN (logos, blog covers).
    csp: {
      directives: [
        "default-src 'self'",
        "img-src 'self' https: data:",
        "font-src 'self'",
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self' mailto:",
        // Video players a post may embed (an <iframe> in its Markdown).
        'frame-src https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com https://fast.wistia.net',
      ],
    },
  },
});
