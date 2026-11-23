# Site — Astro static

A fast, simple **website**: every page is built once into plain files and served from the edge.
No server, no database, nothing to maintain.

- Home (sections), About, Contact (email link, or a link to a hosted form), a 404 page;
- a **Blog**: one Markdown file per post in `src/content/blog/`, each with its own page, tag
  pages and an RSS feed (`/rss.xml`);
- search-ready: a title, description, canonical URL and social card on every page,
  `/sitemap.xml` and `/robots.txt` built with the site;
- instant page-to-page navigation (Astro's ClientRouter + prefetch, the only JavaScript, over
  real prerendered pages), system fonts, one inlined stylesheet, images optimised at build
  (AVIF/WebP, several sizes, lazy), a strict Content-Security-Policy and security headers;
- it stands alone: every page, word and post lives in this repo and is built into plain HTML.

Need sign-in, a database, forms that store answers, bookings or payments? That is an **app**:
pick the **Astro app** template (the database toggle in the dashboard) instead.

## Contents

- [Quick start](#quick-start)
- [Requirements](#requirements)
- [Get the code](#get-the-code)
- [Scripts](#scripts)
- [Project structure](#project-structure)
- [Building on it](#building-on-it) (what not to edit)
- [Staying up to date](#staying-up-to-date)
- [Troubleshooting](#troubleshooting)
- [FAQ](#faq)
- [On the platform](#on-the-platform) (publishing, posts, words)

## Quick start

With [Node.js 22.12+](#requirements):

```bash
git clone <the template repository's address> my-site
cd my-site
npm install
npm run dev                         # http://localhost:4321
```

No database, no settings: a website needs neither.

## Requirements

| Tool | Version | Check |
| --- | --- | --- |
| Node.js | 22.12 or later (`.nvmrc` says 22: `nvm use`) | `node -v` |
| npm | the one that comes with Node.js | `npm -v` |
| git | any | `git --version` |

## Get the code

Clone the template repository, so later releases can be merged into your work
([Staying up to date](#staying-up-to-date)):

```bash
git clone <the template repository's address> my-site
cd my-site
git remote rename origin upstream             # the template: releases come from here
git remote add origin <your own repository's address>
git push -u origin main
```

Don't use a "Use this template" button or copy the files: that makes a copy with no shared
history, and `git merge` can no longer tell your changes from the template's. Already did? See
[Started from a copy](#started-from-a-copy).

## Scripts

| Command | What it does |
| --- | --- |
| `npm run dev` | the dev server, http://localhost:4321 |
| `npm run build` | the site into `dist/` (what the platform builds) |
| `npm run check` | the same build, as the check to run before you push |
| `npm run preview` | serves the last `npm run build` |
| `npm run platform -- check` | the template's own files untouched, and is a newer release out ([Staying up to date](#staying-up-to-date)) |
| `npm run platform -- docs --site <a hosted site's address>` | the platform's latest rules for AI agents, into `.platform/docs/` |

## Project structure

```
AGENTS.md              rules for AI agents (CLAUDE.md, GEMINI.md import it)
docs/agent/            the rules by topic: performance, accessibility, SEO, security, publishing
template.json          which template version this is
scripts/platform.mjs   template updates and the platform's docs
public/                files served as they are (_headers: security and cache headers)
src/content/site.ts    every word on the site
src/content/blog/      the blog posts (Markdown); schema in src/content.config.ts
src/assets/            images optimised at build (blog covers in src/assets/blog/)
src/pages/             index, about, contact, blog/ (+ tags/), 404, rss.xml, sitemap.xml, robots.txt
src/layouts/Base.astro <head> (SEO tags), header, footer
src/components/        PostCard, Cover (optimised images)
src/lib/posts.ts       blog helpers (build time)
src/lib/site-url.ts    the site's public address (canonical URLs, sitemap, RSS)
src/styles/global.css  the stylesheet (inlined into every page)
```

## Building on it

- **Your files**: `src/content/site.ts` (every word), `src/content/blog/` (posts), `src/pages/`,
  `src/components/`, `src/layouts/`, `src/styles/`, `src/assets/`, `public/`. `AGENTS.md` and
  `docs/agent/` have the rules: read them, or have your AI read them, before every change.
- **Never edit the template's own files**: `docs/agent/`, `AGENTS.md`, `CLAUDE.md`, `GEMINI.md`,
  `template.json`, `CHANGELOG.md`, `scripts/platform.mjs` and `scripts/managed-files.json`. Releases
  replace them. Each is compared byte for byte with the release `template.json` names:
  `npm run platform -- check` lists any that changed, and a store listing that changed or deleted one
  is refused. Put one back with `git checkout v<your version> -- <file>`. `.gitattributes` keeps every
  text file LF, so a Windows checkout doesn't change them.
- **Before you push**: `npm run check` and `npm run platform -- check`. If this project has a
  `DEVELOPERS.md`, follow its "For your AI" steps (and its ready-to-submit checklist) too.

## Staying up to date

Template releases are tagged `vX.Y.Z` in the template repository you cloned from (`upstream`).
`CHANGELOG.md` says what each changed and whether it is required. With your work committed:

```bash
git fetch upstream --tags
git describe --tags --abbrev=0 upstream/main   # the newest release
git merge "$(git describe --tags --abbrev=0 upstream/main)"
npm install
npm run check
```

Then follow the new `docs/agent/upgrade.md` for anything that release asks of files you changed.

**Conflicts** (git lists them; search for `<<<<<<<`):
- `package-lock.json`: `git checkout --theirs package-lock.json`, then `npm install` (it rebuilds the
  lock for the merged `package.json`), then commit.
- The template's own files (`docs/agent/`, `scripts/`, `template.json`, `CHANGELOG.md`, `AGENTS.md`,
  `CLAUDE.md`, `GEMINI.md`): take the template's (`git checkout --theirs <file>`). Never keep changes of
  your own in them: describe your project in `README.md` instead.
- Your files (pages, `src/content/site.ts`, styles): keep your words and look, and bring over what
  the template changed.
- `git merge --abort` puts everything back as it was.

### Started from a copy

No shared history with the template: connect it once, then merge releases as above.

```bash
git remote add upstream <the template repository's address>
git fetch upstream --tags
git merge v<the version in your template.json> --allow-unrelated-histories   # once
git merge "$(git describe --tags --abbrev=0 upstream/main)"                # then the newest release
```

The first merge lists every file that differs from that release (usually only the ones you
changed): keep your side of your own files (`git checkout --ours <file>`) and the template's side
of its own files (`git checkout --theirs <file>`), then commit.

## Troubleshooting

| What you see | Why | Fix |
| --- | --- | --- |
| `npm install` warns `EBADENGINE`, or Astro refuses to start | Node.js below 22.12 | `nvm install 22`, then `nvm use 22` (or install 22.12+), then `npm install` again |
| `npm run dev` says port 4321 is in use, or opens another port | another dev server is running | use the address it prints, or stop the other one (`npx astro dev stop`) |
| http://localhost:4321 shows a different project | another app already listens on 127.0.0.1:4321, so this one only got the IPv6 address (`[::1]:4321`) and your browser reaches the other app | stop the other app, or run this one on a free port: `npx astro dev stop && npx astro dev --port 4322` |
| `npm run dev` returns to the prompt with "Dev server running at…" | it runs in the background | `npx astro dev logs` shows its output, `npx astro dev stop` stops it |
| the build fails on a blog post | its front matter is missing `title`, `description` or `publishedAt`, or a date is malformed | fix the post's top block (`src/content/blog/how-to-add-a-post.md` shows one) |

## FAQ

**Does it need a database or a `.env`?** No. Every page is built into plain files.

**I need sign-in, stored forms or bookings.** That is an app: start from the Astro app template instead.

**Can I deploy it somewhere else?** It is built to run on the platform it came from; there is no
other deploy config.

---

## On the platform

### Put it live

Pick **Website** when you create a site in the dashboard, change what you like in the editor (or ask
your AI agent), then **Publish**. The platform installs with `npm ci`, runs `npm run build` and
serves `dist/`.

### Write a post

Add a Markdown file to `src/content/blog/` (the file name is the address: `my-post.md` →
`/blog/my-post/`) with a `title`, `description` and `publishedAt` at the top. A cover image
goes in `src/assets/blog/`. See `src/content/blog/how-to-add-a-post.md`. Publish, and it is
live, with the blog list, tag pages, RSS feed and sitemap.

### Change the words

Everything you read on the site is in `src/content/site.ts`: the name, navigation, home sections,
about text and the contact email. Colours are tokens at the top of `src/styles/global.css`.
Once you connect your own domain, set `SITE.url` there too (search engines then see that
address).
