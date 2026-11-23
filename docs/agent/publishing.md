---
title: How changes go live, and the Preview
read-when: before git push, when the owner asks how changes go live, or before changing scripts, dependencies or config (a broken Preview: `preview.md`)
---
## Going live
- You edit a working copy. Nothing visitors see changes until the OWNER publishes from the
  the editor (typed "publish"). You cannot publish or deploy: there are no platform
  credentials here, and you never ask for any.
- Publish → Deployment: Prepare (detect the framework: Astro, no adapter = a static site) →
  Build (`npm ci`, `npm run build`) → Deploy (`dist/` served as plain files, `404.html` for
  every miss) → Live. Redeploy of an earlier deployment is the rollback.
- The site answers on its free address (`{{SITE_ORIGIN_PATTERN}}`) and on any custom domain the
  owner connects in the site's Domains tab. `{{API_PREFIX}}/*` on every one of those hosts is the
  platform's, never this site's.
- Before telling the owner it is ready: `npm run build` passes, the Preview shows every changed
  page, and `security.md`'s checklist holds.

## What the platform expects (Prepare refuses otherwise)
- `astro` in `package.json` with NO adapter and `output: 'static'`. `@astrojs/node|vercel|
  netlify` are refused; `@astrojs/cloudflare` turns the site into a server app, which is the
  Astro app template's job, not this one's. Never another framework beside Astro.
- npm with ONE lockfile (`package-lock.json`, committed); `allowScripts` lists approved install
  scripts. No `wrangler.*` files (the platform writes the deploy config), no `.github/workflows`.
- No `base` path, no per-request endpoints (build-time ones like `rss.xml.ts`,
  `sitemap.xml.ts`, `robots.txt.ts` are files and fine), no secrets in files.
- The Build gets ONE variable, `SITE_URL` (the site's address, read only through
  `src/lib/site-url.ts` for canonical URLs, the sitemap and RSS). Nothing else from the
  environment reaches a static build, and nothing secret ever does.
- The project sits at the repository root. `node_modules`, `dist`, `.astro`, `.env` are never
  committed (`.gitignore`).

## Redirects
- An exact redirect (one old address to one new one) may stay in astro.config `redirects`:
  `redirects: {'/old-page': '/new-page/'}`.
- NEVER a catch-all there: no `[...rest]` or `[...slug]` in a `redirects` source or
  destination. Astro turns it into a `_redirects` rule the hosting refuses (a destination with `*`,
  or one ending in `/index.html`, read as an endless loop), and the publish fails.
- Catch-all redirects go in `public/_redirects`, one rule per line, in Cloudflare's format:
  `/old/* /new/:splat 301` (`*` in the source, `:splat` in the destination; a named part:
  `/posts/:slug /blog/:slug/ 301`). Never a destination ending in `.html` or `/index.html`: link to
  the page's address (`/new/`).
- Limits: 2,000 exact and 100 catch-all rules, 1,000 characters a rule. The publish checks
  `_redirects` before it goes live and names the line to fix.

## Preview (the editor)
- The editor runs `npm run dev` for the Preview pane, on its one fixed port: keep it working and serving
  at `/`. Never start a second dev server, never `npm run preview`. How it runs, `{{PREVIEW_COMMAND}}` and the
  fix-it checklist: `preview.md`.
- The Preview is the dev server, not the built files: drafts (`draft: true`) show there and
  never in a Publish. Only `npm run build` proves the build.
- The Preview is framed by the editor; the live site sends `X-Frame-Options: DENY`. Never rely on
  being framed.

## Template upgrades
- `template.json` records the template and its version, and `npm run platform -- check` says if a
  newer one exists. To upgrade: `upgrade.md`.
- `npm run platform -- docs` (or `--site <this site's address>`) saves the platform's live
  agent docs to `.platform/docs/`; when they differ from `docs/agent/`, the live ones win.

## Git
- Commit small, reviewable changes with clear messages. Push with plain `git push` to `origin`
  only when the owner set up a repository; never `--force`, `--all` or `--tags`.
- In the editor `origin` is the platform's git proxy, not GitHub: a push works only while the editor
  session is open and answers 403 after it ended. That is not a password problem: never change the
  remote, add a token or ask for one; commit, and the push waits for the next Open (`preview.md`).
