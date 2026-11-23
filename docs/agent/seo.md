---
title: SEO: pages search engines and social apps understand
read-when: before adding a page or a post, changing a title, a description, a URL or a social card
---
A static site is the best case for search: every page is real HTML at a real URL. Keep it so.

## Every page
- A unique `title` and `description` (~150 characters, says what the page is for), passed to
  `<Base title description>`. The tab shows "<title> · <site name>"; the home page omits `title`.
- The site's address (`src/lib/site-url.ts`): `SITE.url` in `src/content/site.ts` when the
  owner set it (their custom domain), else the address the platform passes to every Publish build
  (`SITE_URL`, the free `{{SITE_ORIGIN_PATTERN}}` address). From it every page gets a
  canonical URL and `og:url`, and the sitemap, robots.txt and RSS get absolute links. Set
  `SITE.url` once the owner has a custom domain (`https://www.example.com`, no trailing slash);
  never a guess. In `npm run dev` / the Preview the address is unknown and those tags are left
  out: that is expected.
- `<Base>` writes `og:site_name`, `og:type`, `og:title`, `og:description`, `og:url`,
  `og:image` (+ `og:image:alt`), `twitter:card` / `twitter:title` / `twitter:description` /
  `twitter:image`, and on posts (`type="article"`) the published and modified times.
- A social card image when the page is shared: `image` on `<Base>` (a `/path` on this site or
  an https URL; made absolute for you), 1200×630, under ~300 KB. For a `src/assets` image pass
  `(await getImage({src: img, width: 1200, format: 'jpg'})).src`, as the post page does.
- Real text in the HTML for everything that should rank. Never render words with JavaScript.
- Every page is its own prerendered HTML file, reached by a plain `<a href>`. Instant clicks come
  from prefetch + Astro's ClientRouter (`performance.md`), which swaps in those same pages;
  never render a page's words with your own JavaScript.

## Structured data (JSON-LD)
- `src/lib/json-ld.ts` builds it; `<Base>` writes it as `<script type="application/ld+json">`
  (a data block: never executed, so the CSP allows it). The home page gets `WebSite` +
  `Organization` (the site name in search results); a post passes `jsonLd={postJsonLd(…)}`
  (`BlogPosting`: headline, dates, author, cover).
- A new kind of page with a schema.org type (an event, a product, an FAQ): add a builder in
  `json-ld.ts` and pass it to `<Base jsonLd>`. Only facts the page itself shows, from this
  repo's content; absolute URLs through `absoluteUrl()`. Never on a `noindex` page.
- Always through `jsonLdText()` (escapes `<`), never a hand-written `<script>` or `set:html` of
  anything else. Check it with Google's Rich Results Test on the live page.

## Posts
- The post's `description` front matter is its meta description and the blog list's summary.
- `cover` is its social image (1200×630, a JPEG is made for you). `publishedAt` (and `updatedAt`
  when you change a post) keep dates honest; they feed `article:*` tags, the sitemap's
  `lastmod` and the RSS `pubDate`.
- `tags` make `/blog/tags/<tag>/` pages (listed in the sitemap). Few, reused tags beat many
  one-off ones.
- The file name is the URL. Never rename a published post (old links and rankings break); if one
  must move, keep the old file as a short post linking to the new one.

## URLs and links
- Clean directory URLs with trailing slashes (`/about/`, `/blog/my-post/`), lowercase, dashes.
- Internal links use those exact paths. Descriptive link text (`accessibility.md`).
- `alt` on content images; headings in order, one `<h1>` per page.

## Sitemap, robots.txt, RSS (built with the site)
- `/sitemap.xml` (`src/pages/sitemap.xml.ts`) lists every page in `src/pages/` automatically,
  every post (with `lastmod`) and every tag page. A new dynamic route (`[param].astro`) is NOT
  found automatically: add its URLs there, as posts and tags are.
- `/robots.txt` (`src/pages/robots.txt.ts`) allows everything and names the sitemap.
- `/rss.xml` (`src/pages/rss.xml.ts`): the 20 newest posts; every page links it in `<head>`.
- Keep all three; don't add static `public/robots.txt` or `public/sitemap.xml` copies (they
  would clash). `/.well-known/` files only when the owner asks (domain verification).

## Utility pages
- `noindex` on utility pages: the 404 has it (`<Base noindex>`); add it to any thank-you or test
  page, AND put its path in `EXCLUDE` in `sitemap.xml.ts`. (Lighthouse's SEO score on a
  `noindex` page is low by design; check SEO on real pages.)
- Never block the site from search engines (`noindex` on real pages, `Disallow: /`) unless the
  owner asks for exactly that.
