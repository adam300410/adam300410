# Rules for AI agents working in this site

The single source of rules for EVERY coding agent (Claude Code, Codex, Cursor…).
The other rule files at the root (`CLAUDE.md`…) only import this file: edit here, never there.

This repo is ONE **static** website: Astro 7, every page prerendered to plain files
(`output: 'static'`, no adapter). No server code, no backend, no database, no secrets, no client
JavaScript but Astro's prefetch module (and the small live-prices script of a pricing
section, `plans-catalogue.md`, and the Buy button's script, `payments.md`). It stands alone: its pages, words and posts all live in this repo. It already has a
home, about, contact, a Markdown blog (list, posts, tag pages, RSS), a sitemap, robots.txt and
a 404 page. Extend it; don't rebuild it.

Placeholders written as double braces around an UPPER_CASE name are filled in by the platform.
The filled-in copy, which wins over this file when they differ, is what the editor
gives you (`/etc/claude-code/CLAUDE.md`, `{{DOCS_DIR}}/`); outside the editor,
`npm run platform -- docs --site <a hosted site's address>` saves it to `.platform/docs/` (start with `core.md`).
**A placeholder still in double braces is not filled in** (on a developer's own computer, or any copy
outside the editor): never fetch, open or paste it as a URL, path or value, and never fill it in (this file
is compared byte for byte). Read `docs/agent/<topic>.md` in this folder instead of `{{DOCS_DIR}}` or
`{{DOCS_URL}}`; for any other one, `DEVELOPERS.md` or `STORE-LISTING.md`, if present, says what it is on
that computer; otherwise it isn't available there: do the task without it and say so in one line.

## First: is this site on the required template version? (0.7.17)
Read `version` in `template.json` at the start of a session, new or resumed, before anything else. **If it's below 0.7.17 (below it, its AI may sell through something other than the built-in payments and build video features the platform's video doesn't allow; below 0.7.16 Wistia embeds are blocked; below 0.7.15 `npm run platform -- check`
puts a half-done merge back with the wrong file, and on a developer's computer its AI can open a placeholder as a link; below 0.7.14 it can't tell when the template's own files were changed), upgrade it now, as your first step, without asking.** The owner won't know what to answer, and nothing reaches visitors until they publish. Say one
plain line, such as "I'm updating your site first. Hold off publishing until I'm done, then I'll do what you asked", follow `upgrade.md`, then do
their task. A question from the owner that changes nothing can be answered first. Upgrade before anything that changes a file. No
`template.json` means this site wasn't built from a template: skip this. A `.review/REVIEW.md` means this is a read-only review copy: never upgrade it. If `.platform/upgrade-failed` holds this required version, an upgrade already failed: skip it, do their task, and say so in one line. If the owner has said not to upgrade, do their task, and upgrade next session unless they say no again. If it can't finish,
`upgrade.md` says how to put everything back. Never publish a half-done upgrade.


**On a developer's own computer.** If `DEVELOPERS.md` exists at the top of this project, read it first: it says how this
copy is set up and kept up to date. If `git remote -v` lists `upstream` (the template repository it was cloned from), an
upgrade is a merge, not `upgrade.md` step 1: commit everything, `git fetch upstream --tags`, then
`git merge "$(git describe --tags --abbrev=0 upstream/main)"` (the newest release, at least the required version), solve conflicts (`package-lock.json`: `git checkout --theirs package-lock.json`, then `npm install`;
the template's managed files and `AGENTS.md`: take the template's), then follow `upgrade.md` from step 2 and check it. A
copy with no shared history merges once with `--allow-unrelated-histories`. No `upstream` and no editor: add the template's
repository as `upstream` (`DEVELOPERS.md` names it) and merge; there is no copy to download. Never edit the template's own
files: `docs/agent/`, `AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, `template.json`, `CHANGELOG.md`, `scripts/platform.mjs`,
`scripts/managed-files.json`. They are compared byte for byte with the release `template.json` names, and a store
submission that changed or deleted one is refused; put one back with `git checkout v<the version in template.json> -- <file>`.
`npm run platform -- check` compares them. Before saying a store listing is ready: `DEVELOPERS.md` "For your AI".

## Every task: the current rules first, then the owner's words
These rules are kept up to date by the platform and can be newer than this site's code, which stays
as it was built. The owner is not an engineer: they say WHAT they want; HOW is yours, by these rules.
1. Before each task, re-read the current rules (`/etc/claude-code/CLAUDE.md` in the editor, for every
   agent; else `{{DOCS_URL}}` on this site's address) and the topic doc for that work (below), even
   mid-session: they may have changed since you last read them. Never work from memory of them.
2. Then do what the owner asked, the way these rules say.
3. Code the task touches or depends on that breaks a current rule: bring it up to the rule in the same
   task when it matters (security, a broken page or link, a slow page, accessibility, SEO).
   Something minor: leave it and say so in one short line. Never rewrite parts of the site the task
   does not need.
4. A doc names something this site does not have yet (a component, a script command): the site is
   older than the doc. Do the task with what is there, following the doc's pattern in your own files.
5. Before saying it's done, check it works: `npm run build`, and the page in the Preview.
   Asked for a security check, or asked to go live for the first time: run "The full security check" in `security.md`.
6. **Then review it, every task**: run the "Before you say done" list in `security.md` (no secret or
   key in the site's files, links and forms safe, no outside script you didn't need, nothing
   private in a page) and the checks in `performance.md` (image sizes, no blocking script, page
   weight), fix what they find, and end your answer with a short report: what you checked, what
   you fixed, what is left.

## How changes go live (details: `publishing.md`)
You edit a working copy; the OWNER publishes from the editor (Prepare → Build:
`npm ci`, `npm run build` → Deploy of `dist/` as plain files). You cannot deploy. Keep
`npm run dev` working at `/`; before saying "done", `npm run build` passes and the Preview shows
every changed page. No wrangler config, no `base`, no `.github/workflows`, npm with one
committed lockfile.

## The Preview, git and the machine in the editor (NON-NEGOTIABLE)
- The editor runs the Preview's dev server on ONE fixed port: Astro 4321, a Static site 8788 (TanStack Start 3000), or
  the port the project's own config names. Never start your own (`npm run dev`, `astro dev`, `npm run preview`,
  `wrangler dev`), never pass `--port`, never kill it: each Preview start stops whatever holds that port.
- See it and restart it with `{{PREVIEW_COMMAND}} status`, `{{PREVIEW_COMMAND}} logs [-n N]` (the end of `{{PREVIEW_LOG}}`) and
  `{{PREVIEW_COMMAND}} restart` (the owner's Restart button). No `{{PREVIEW_COMMAND}}` (an older editor): read `{{PREVIEW_LOG}}` and ask
  the owner to press Restart. **Preview broken: follow `preview.md`** (status, logs, then the usual causes: port taken,
  missing packages, crashed server, stale generated file, merge conflict).
- Generated files (`.astro/`, `dist/`, `src/routeTree.gen.ts`) are never edited by hand: change their source and regenerate.
- `git status` first: a platform import can leave the project mid-merge. Unmerged files or `<<<<<<<` markers: finish that
  merge before anything else (`preview.md`).
- `origin` is the platform's git proxy: `git push` works only while the editor session is open and answers 403 after it
  ended. Never change the remote or ask for a token.
- A setting the owner changes reaches the Preview only at the next Open, and that Open stops every process in the
  sandbox, yours included: commit first, then ask the owner to reopen the editor.
- You share CPU and memory with the Preview and the editor: check `nproc` and `free -m` before heavy work; kill what
  you start; one heavy command at a time; bounded commands (no `find /`, no grep over `node_modules`).

## Topic docs: read the one that fits BEFORE that work
Read the current copy: `{{DOCS_DIR}}/<topic>.md` in the editor, else `{{DOCS_URL}}/<topic>.md` on this
site's address. `docs/agent/<topic>.md` in this repo is the copy from when the site was built or last
upgraded and can be older; when they differ, the current copy wins:

| Doc | Read it before |
| --- | --- |
| `performance.md` | any page, image, font, script, dependency or style change |
| `accessibility.md` | any markup, colour, link, button, form or motion change |
| `seo.md` | a new page, a title or description, URLs, social cards, a blog post |
| `security.md` | any script, embed, external link, header, CSP or dependency; before "done" |
| `upgrade.md` | this site's `template.json` is below the required version, or the owner asks to upgrade |
| `preview.md` | the Preview is broken, blank or stale; the dev server, its port or log; a half-done merge |
| `publishing.md` | git, scripts, dependencies, config, redirects, going live |
| `payments.md` | anything sold: a Buy or Subscribe button, a price, a checkout, a thank-you page after paying |
| `plans-catalogue.md` | the platform's plans an owner resells (sign-up links to their studio), not the site's own products; their real plans: `npm run platform -- plans --write` |

**Selling anything → `payments.md`**: a built-in Buy / Subscribe button (`src/components/BuyButton.astro`;
the payment form opens on the page), prices by key from the owner's catalogue, sandbox until the owner goes live. Use it by default;
memberships and member pages need the app template. A static site sells ONLY through these built-in payments: the owner's own
Ezypay, Stripe or PayPal account needs server code and webhooks, so it needs the app template.

## What is where
| Path | What | Code |
| --- | --- | --- |
| `/` | home, in sections | `src/pages/index.astro` |
| `/about/`, `/contact/` | content pages | `src/pages/about.astro`, `contact.astro` |
| `/blog/`, `/blog/<slug>/` | blog list and posts, from Markdown | `src/content/blog/*.md`, `src/pages/blog/`, `src/content.config.ts` |
| `/blog/tags/<tag>/` | one page per tag in use | `src/pages/blog/tags/[tag].astro` |
| `/rss.xml` | the blog's RSS feed (20 newest) | `src/pages/rss.xml.ts` |
| `/sitemap.xml`, `/robots.txt` | for search engines; every page is listed automatically | `src/pages/sitemap.xml.ts`, `robots.txt.ts` |
| `404.html` | every missing path | `src/pages/404.astro` |
| — | all words, nav, contact email, `SITE.url` | `src/content/site.ts` |
| — | `<head>` (title, description, canonical, Open Graph, Twitter), header, footer | `src/layouts/Base.astro` |
| — | the site's public address at build time | `src/lib/site-url.ts` |
| — | blog helpers (posts, tags, dates) | `src/lib/posts.ts` |
| — | optimised images (covers): AVIF/WebP, widths, lazy | `src/components/Cover.astro`, files in `src/assets/` |
| — | styles (tokens, light + dark), inlined into every page | `src/styles/global.css` |
| — | headers for every file | `public/_headers` |

Never create anything under `{{API_PREFIX}}` (the platform serves it on this host) or `/site-api`.

## Static rules (NON-NEGOTIABLE)
- Every page is built ONCE and served as a file to everyone. No `export const prerender =
  false`, no adapter, no middleware that runs per request, no `Astro.cookies`,
  `Astro.session`, `Astro.request.headers` beyond build time. A `src/pages/**/*.ts` endpoint
  is allowed ONLY as a build-time file (`GET` returning a fixed body, like `rss.xml.ts`):
  never one that reads the request, takes a POST or expects to run per visit.
- No secrets anywhere: everything in this repo and in `dist/` is public. Never put a key,
  token or password in code, a committed `.env`, `PUBLIC_*` vars or HTML. You cannot add secrets.
- Content lives in the repo and is built into pages: words in `src/content/site.ts`, posts in
  `src/content/blog/`. There is no CMS or blog API: never fetch posts or pages at runtime.
- No sign-in links, account pages or member areas: a static site has no visitors' accounts.
- A missing page is `404.html`; keep `src/pages/404.astro`.
- **Redirects**: exact ones may stay in astro.config `redirects`; a catch-all (`[...rest]`, `[...slug]`)
  never goes there (the publish fails): put it in `public/_redirects` as `/old/* /new/:splat 301`
  (`publishing.md` "Redirects").

## The blog: to add a post, add a Markdown file
1. Create `src/content/blog/<slug>.md` (lowercase, dashes): the file name is the URL
   (`/blog/<slug>/`). Never rename a published post's file (its URL would break).
2. Front matter (checked at build by `src/content.config.ts`; a mistake fails the build):
   ```md
   ---
   title: A clear title            # required, ≤ 120
   description: One or two sentences.  # required, ≤ 200 (list, meta description)
   publishedAt: 2026-10-19         # required
   author: Jane Doe                # optional
   cover: ../../assets/blog/my-post.jpg  # optional, PREFERRED: a file in src/assets/ (optimised
                                   #   at build); or /images/x.webp in public/, or an https URL
   coverAlt: What the image shows  # optional, "" for decorative
   tags: [guide]                   # optional: each gets /blog/tags/<tag>/
   draft: true                     # optional: only in `npm run dev`, never published
   ---
   ```
3. Write Markdown below it. Headings start at `##` (the title is the page's `<h1>`). Images in
   the post: `![Alt text](../../assets/blog/photo.jpg)` (optimised at build) or `/images/…` in
   `public/` (resized before committing), always with alt text. An `<iframe>` only from the
   hosts in `frame-src`.
4. The list, the post page, its tag pages, the RSS feed, the sitemap and the home page's
   "latest" update at the next Publish.
Markdown is trusted repo content and rendered as HTML: never paste HTML from an untrusted source
(a visitor, an email, a third-party widget) into a post.

## What a static site cannot do (then propose the app template)
Sign-in or accounts, member-only pages, storing anything (form submissions, bookings, orders,
comments, likes), sending email, memberships or anything a payment unlocks on the site (a plain
Buy / Subscribe button works here: `payments.md`), search over private data, per-visitor pages, an admin
dashboard, webhooks, anything that needs a secret, and the platform's own video (uploads and playback links
are made per viewer by a server; a public YouTube, Vimeo or Wistia embed works here). Never offer, suggest or scaffold
the owner's own storage (a bucket, S3, R2, a video host's account) or a direct link to a video file: files and video
are the platform's own, on the app template. When the owner asks for one of these, say
plainly that this site is static and that switching to the **Astro app** template (the database
toggle, "make it an app", in the dashboard) adds a backend, a Site Database and accounts; do not
fake it in the browser (no localStorage "accounts", no third-party form scripts, no keys in JS).
A contact form today = a `mailto:` link or `CONTACT.formUrl` (a form the owner hosts elsewhere).
The platform's video (on the app template) is for teaching, coaching, talks and group or business video:
never build movie, film, TV-drama or music-video features on it, here or there, and refuse such a
request in one plain line. A "series" is a learning or coaching show, never a TV drama.

## The short version of each topic
- **Navigation** (`performance.md`): every page is a prerendered file reached by a plain
  `<a href>`; `<ClientRouter />` (in `Base.astro`, keep it) swaps the next page in without a
  reload and prefetch (on hover, header nav on view) fetches it first. Any script you add runs
  on `astro:page-load` (`performance.md` "JavaScript"). Never a SPA router or `fetch()` +
  `innerHTML` pages.
- **Performance** (`performance.md`): no JavaScript but Astro's prefetch and client router, images through
  `astro:assets` (`Cover`, `<Picture>`/`<Image>` from `src/assets/`) with `sizes` and lazy loading, system
  fonts, one small inlined stylesheet. Mobile Lighthouse ≥ 95 in all four categories.
- **Accessibility** (`accessibility.md`): one `<h1>`, headings in order, landmarks, the skip
  link, keyboard and focus, contrast ≥ 4.5:1 in light AND dark, `prefers-reduced-motion`.
- **SEO** (`seo.md`): a unique `title` + `description` per page through `<Base>`; canonical,
  Open Graph and sitemap URLs come from `src/lib/site-url.ts` (`SITE.url`, else the address
  the platform sets at Publish); real text in the HTML; `noindex` on the 404; JSON-LD through
  `src/lib/json-ld.ts` (`<Base jsonLd>`).
- **Security** (`security.md`): no secrets (there are none to have), the CSP in
  `astro.config.mjs` stays strict (no `'unsafe-inline'`, no inline handlers, no third-party
  scripts), `public/_headers` stays, no `innerHTML`/`set:html` with outside data. There is no
  input to validate and no session to protect: anything that needs input validation, CSRF
  protection or rate limits (forms that store, sign-in) is the app template's job.

## Recipes
- **A new page**: `src/pages/<name>.astro` (lowercase, dashes) with `<Base title description>`,
  words in `src/content/site.ts`, add it to `NAV` if it belongs in the menu. It is static,
  prefetched and listed in `/sitemap.xml` automatically; link to it with a plain `<a href>`. A page that must stay out of search: `<Base noindex>`
  AND its path in `EXCLUDE` in `src/pages/sitemap.xml.ts`.
- **A blog post**: a Markdown file (see "The blog" above).
- **A list of things that changes now and then** (team, services, FAQ, menu items): another
  content collection, rendered at build time like the blog. Not a fetch:
  ```ts
  // src/content.config.ts: add beside `blog`, and to `collections`
  const team = defineCollection({
    loader: file('src/content/team.json'),          // [{"id": "maya", "name": "Maya", …}]
    schema: ({image}) => z.object({name: z.string().max(80), role: z.string().max(80), photo: image().optional()}),
  });
  export const collections = {blog, team};
  ```
  (`import {file, glob} from 'astro/loaders'`.) Create the data file (`src/content/team.json`)
  FIRST, then the collection: one added while the Preview runs, before its file exists, stays
  empty there until the dev server restarts. Then `await getCollection('team')` in a page's
  frontmatter. A wrong field fails the build with the file name: fix the data, never loosen the
  schema to `z.any()`. An `image()` field takes a path relative to the data file
  (`"photo": "../assets/maya.jpg"` for `src/content/team.json`).
- **An image on a page**: put it in `src/assets/`, `import photo from '../assets/photo.jpg'`,
  render `<Picture src={photo} formats={['avif', 'webp']} widths={[…]} sizes="…" alt="…" />`
  from `astro:assets` (or `Cover`). Eager + `fetchpriority="high"` for the one hero image only.
- **Contact**: set `CONTACT.email`; for a hosted form set `CONTACT.formUrl` (https only).
- **Restyle**: change tokens in `:root` (and the dark block) first; `DESIGN.md` at the root, when
  present, is the design system: read it before UI work.

## Never
- Put a password (in any form) or another person's email address in the site: not in a page, a
  form's hidden field, a link, a script or a file. An email is treasure: show a name instead.
- An adapter, `prerender = false`, middleware, or an endpoint that runs per request.
- A secret, key or token anywhere (code, Markdown, `.env`, `PUBLIC_*`, HTML).
- Client-side "features" that fake a backend: localStorage accounts, third-party form or chat
  scripts, keys in JavaScript. Propose the app template instead.
- `'unsafe-inline'`, inline handlers, `style=""`, third-party `<script src>`, `set:html` with
  outside content.
- Removing `<ClientRouter />`, another client-side router, or your own JavaScript that swaps
  pages (the ClientRouter already does it, with focus, titles and history right).
- A runtime fetch for content (the exceptions: the owner's live plans and prices, exactly as
  `plans-catalogue.md` says, and the Buy button's checkout call, `payments.md`), a UI kit or framework for one widget, a web font from another
  origin, an image without `width`/`height`, a multi-MB photo.
- Renaming a published post's file, deleting `404.astro`, `sitemap.xml.ts` or `robots.txt.ts`,
  `Disallow: /` or `noindex` on real pages unless the owner asks for exactly that.
- Anything under `{{API_PREFIX}}` or `/site-api`; a second lockfile or package manager.
