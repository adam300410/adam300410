---
title: Upgrading a site to the current template
read-when: this site's template.json is older than the required version in AGENTS.md, or the owner asks to upgrade
---
A site's code stays as it was built. Rules reach it at once, but fixes only reach it through an upgrade and a
Publish. Below the required version, upgrade as your first step without asking (`AGENTS.md`). Nothing reaches visitors until they publish.

## Steps
Start with a clean git tree: commit what's there. Never stash: Publish ships the working copy, so stashed work would be left out of it.
1. Apply the template. In the editor, the platform puts it in `{{TEMPLATE_ZIP}}` when the editor opens. If it isn't there yet, wait a few seconds and look again:
   ```bash
   rm -rf /tmp/site-template
   mkdir /tmp/site-template && unzip -q {{TEMPLATE_ZIP}} -d /tmp/site-template
   npm run platform -- upgrade --from /tmp/site-template
   ```
   If its `template.json` is still below the required version, or the file never arrives, treat it as "If it can't finish" below. Never ask the owner to download or find anything: they have nowhere to get it. Plain `npm run platform -- upgrade` fails with `no template folder`: always pass `--from`.
   A developer on their own machine (a clone of the template's repository, its `upstream` remote): merge the newest release tag instead (`AGENTS.md` "On a developer's own computer"), then continue at step 2. No `upstream` remote: add the template's repository as `upstream` (`DEVELOPERS.md` names it) and merge; there is no copy to download. `npm run platform -- check` says whether a newer release is out.
2. It replaces the managed files, adds new ones, and lists what it left as it was. Go through that list, compare each file with
   `/tmp/site-template/`, and bring over what the template added. Keep this site's own words, pages and look. Often on that list, so
   skip what's already there:
   - `package.json`: move the versions of its dependencies up to the template's.
   - Checkout keys, so a double click or a retry can't start a second checkout: `src/lib/checkout-session.ts`, `src/lib/buy-button.ts`.
     If this site changed one, bring the `idempotency` and `paid` lines into its copy.
   - The page progress bar: the `installPageProgress` lines in `src/layouts/Base.astro`, and the `.page-progress` block at the end of `src/styles/global.css`.
   - `AGENTS.md` (and `CLAUDE.md`, `GEMINI.md`): the upgrade leaves them, but they are the template's rules. Copy the template's. If this site added rules of its own to `AGENTS.md`, add those lines back at the end. An app built for the store keeps all three exactly as the template has them (its own notes go in `README.md`).
   - `package.json` → `scripts` (0.7.13): add the template's `check` line if this site has none. `.nvmrc` and `.editorconfig` arrive as new files: keep them.
   - `README.md` (0.7.13): if this site still has the template's, copy the template's "Building it on your own computer" part (setup, and how to update a copy on your own computer). A site that rewrote it: leave it.
3. **Check the `added` list.** A page or content file there is one this site deleted or never had. Delete anything added under `src/pages/`, `src/content/`, `src/assets/` or `public/`, unless a step below names it or the build needs it. A visitor must never see a page the owner didn't ask for.
4. **Coming from below 0.7.14** (the template's own files are checked). The upgrade adds `scripts/managed-files.json` (this
   release's list of the template's own files) and `.gitattributes` (LF line endings, also on Windows): keep both. `AGENTS.md`: the
   upgrade leaves it; copy the template's (`cp /tmp/site-template/AGENTS.md AGENTS.md`) and add this site's own rules back at its
   end, if it had any. Then `npm run platform -- check --from /tmp/site-template` lists any of the template's own files that still
   differ (`docs/agent/`, `AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, `template.json`, `CHANGELOG.md`, `scripts/`): copy each one from
   `/tmp/site-template/`. "Windows line endings": `git add --renormalize .`, then check again.
5. **Coming from below 0.7.15** (clearer steps on a developer's own computer). The upgrade leaves these; skip what's already there:
   `AGENTS.md`: copy the template's (`cp /tmp/site-template/AGENTS.md AGENTS.md`) and add this site's own rules back at its end, if it
   had any (an app built for the store keeps it exactly as the template has it; `CLAUDE.md` and `GEMINI.md` are unchanged).
   `README.md`: if this site still has the template's, copy the template's "Staying up to date" and "Troubleshooting" parts.
   A site that rewrote it: leave it.
6. **Coming from below 0.7.16** (Wistia embeds). The upgrade leaves these: add ` https://fast.wistia.net` at the end of the
   `frame-src` line in `astro.config.mjs`, and `'https://fast.wistia.net'` at the end of `SITE_FRAMES` in `src/lib/checkout-hosts.ts`.
7. **Coming from below 0.7.17** (rules only: selling goes through the built-in payments; what the platform's video is for). Nothing to change in the files the upgrade leaves.
   **Coming from below 0.7.18** (rules only, optional: the Preview and git in the editor). `docs/agent/preview.md` arrives with the
   upgrade. `AGENTS.md`: copy the template's (`cp /tmp/site-template/AGENTS.md AGENTS.md`) and add this site's own rules back at its
   end, if it had any. Nothing in the site's pages changes.
8. Check it: `npm install`, then `npm run build`, then look at the pages this site changed in the Preview.
9. Commit, do the owner's task, and at the end tell them in plain words what changed for this site, and that it goes live when they publish.

## If it can't finish
Stop before committing, restore with `git checkout .` and `git clean -fd` on what the upgrade added, do the owner's task, and tell them it didn't finish. The site keeps
working as it did. Then write the required version to `.platform/upgrade-failed` (git-ignored, so it survives the restore), so the next session doesn't spend the owner's AI plan on the same failure. A newer required version, or the owner asking, tries again. Never publish a half-done upgrade.
