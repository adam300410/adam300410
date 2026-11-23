#!/usr/bin/env node
/**
 * Keeps this site current with the platform and the template it started from.
 * Runs on a development machine or in the editor (never deployed). Node 22+,
 * plus `tar` and `git` on the PATH. No dependencies.
 *
 *   npm run platform -- docs [--site URL]   live platform docs → .platform/docs/ (git-ignored)
 *   npm run platform -- check [--from DIR]  the template's own files untouched (scripts/managed-files.json),
 *                                            a store listing's sample data, and is a newer release out?
 *                                            Exit 1: something to fix; 10: a newer release; 0: all good.
 *   npm run platform -- upgrade [--to vX.Y.Z] [--from DIR] [--base DIR] [--dry-run]
 *   npm run platform -- plans [--write]      the owner's real client plans (owner's own website only);
 *                                            --write saves them to src/content/plans.json
 *                                            (docs/agent/plans-catalogue.md). Needs the editor's
 *                                            SITE_KEY and SITE_URL (the editor sets both).
 *
 * WHERE RELEASES COME FROM: `template.json` `repo`.
 *   - `file:<path>` (today: the platform's templates are not published yet) — a
 *     folder holding the template at its NEWEST version, e.g.
 *     `file:templates/astro-static` inside a platform checkout. A relative
 *     path resolves against `$SITE_DIR` (that checkout's root), else
 *     against this project. `--from DIR` points at the folder directly.
 *     A folder holds one version, so `upgrade` merges 3-way only when
 *     `--base DIR` gives a copy of the OLD version; without it, files you
 *     changed are left as they are and listed for you to compare.
 *   - `owner/name` — a GitHub repository with `vX.Y.Z` tags (later).
 *   In the editor, the platform provides the newest template as a folder: `upgrade --from <it>`
 *   (docs/agent/upgrade.md). A clone of the template's repository on a developer's own computer
 *   takes releases with git instead (`git fetch upstream --tags`, then `git merge v<newest>`); `check`
 *   reads the `upstream` remote's tags. Files that a `--from` folder's `.gitignore` names one by
 *   one (files that came with it, not the template) are never copied in.
 *
 * UPGRADE, file by file, from the version in `template.json` to the target:
 *   - MANAGED paths (`docs/agent/**`, this script, its `managed-files.json`, `CHANGELOG.md`) are the
 *     template's own: replaced wholesale. Do not edit them; extend them from
 *     your own code instead.
 *   - Everything else is a 3-way merge (`git merge-file`): base = the old
 *     template, ours = your file, theirs = the new template. Your changes are
 *     kept; a clash is written with conflict markers and listed at the end.
 *   - A file you deleted stays deleted; a file the template added is added
 *     (unless you already have one at that path, which is merged).
 * Then `template.json` records the new version. Review with `git diff`, solve
 * the listed conflicts, run `npm install`, `npm run build`.
 */
import {execFileSync} from 'node:child_process';
import {createHash} from 'node:crypto';
import {existsSync, mkdirSync, mkdtempSync, readdirSync, readFileSync, rmSync, statSync, writeFileSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {dirname, isAbsolute, join, relative, resolve} from 'node:path';

const ROOT = process.cwd();
const MANIFEST = join(ROOT, 'template.json');
const MANAGED = [/^docs\/agent\//, /^scripts\/platform\.mjs$/, /^scripts\/managed-files\.json$/, /^CHANGELOG\.md$/];
/** This release's managed files and their sha256 (`check` compares them; never edit it). */
const MANAGED_LIST_REL = 'scripts/managed-files.json';
const MANAGED_LIST = join(ROOT, MANAGED_LIST_REL);
/** A store listing of this template must ship sample data (prisma/seed.sql). */
const SEED_REQUIRED = false;
const NEVER = [/^\.git\//, /^node_modules\//, /^dist\//, /^\.astro\//, /^\.wrangler\//, /^\.platform\//, /(^|\/)\.DS_Store$/, /^src\/generated\//, /^template\.json$/, /^\.env$/, /^\.dev\.vars/];
const SKIP_IN_MERGE = [/^package-lock\.json$/];

const [command = 'help', ...args] = process.argv.slice(2);
const flag = name => args.includes(name);
const option = name => {
  const i = args.indexOf(name);
  return i >= 0 ? args[i + 1] : undefined;
};

function readManifest() {
  if (!existsSync(MANIFEST)) throw new Error('template.json not found: run this from the project root.');
  return JSON.parse(readFileSync(MANIFEST, 'utf8'));
}

const semver = tag => tag.replace(/^v/, '').split('.').map(Number);
const newer = (a, b) => {
  const [x, y] = [semver(a), semver(b)];
  for (let i = 0; i < 3; i++) if ((x[i] ?? 0) !== (y[i] ?? 0)) return (x[i] ?? 0) > (y[i] ?? 0);
  return false;
};

async function github(path) {
  const res = await fetch(`https://api.github.com${path}`, {
    headers: {accept: 'application/vnd.github+json', 'user-agent': 'site-platform-script'},
  });
  if (!res.ok) throw new Error(`GitHub answered ${res.status} for ${path}`);
  return res;
}

/** `file:` source: the folder that holds the template's newest version. */
function localSource(repo) {
  const from = option('--from');
  if (from) return resolve(ROOT, from);
  if (!repo?.startsWith('file:')) return null;
  const path = repo.slice('file:'.length);
  if (isAbsolute(path)) return path;
  return resolve(process.env.SITE_DIR || ROOT, path);
}

const DOWNLOADED_COPY_HELP = [
  'This copy has no newer template next to it to compare with.',
  'A clone of the template\'s repository: keep it as the "upstream" remote (git remote -v), then',
  '  git fetch upstream --tags',
  '  git merge "$(git describe --tags --abbrev=0 upstream/main)"',
  'In the editor, the platform provides the newest template: follow docs/agent/upgrade.md (it passes --from).',
  'In a platform checkout, set SITE_DIR to its root.',
].join('\n');

function localVersion(dir) {
  const file = join(dir, 'template.json');
  if (!existsSync(file)) {
    if (!option('--from') && !process.env.SITE_DIR) throw new Error(`no template folder at ${dir}.\n${DOWNLOADED_COPY_HELP}`);
    throw new Error(`no template.json in ${dir}: pass --from <the unzipped template folder> or set SITE_DIR.`);
  }
  const {version} = JSON.parse(readFileSync(file, 'utf8'));
  if (!/^\d+\.\d+\.\d+$/.test(version ?? '')) throw new Error(`${file} has no X.Y.Z version.`);
  return `v${version}`;
}

async function latestTag(repo) {
  const local = localSource(repo);
  if (local) return localVersion(local);
  const tags = await (await github(`/repos/${repo}/tags?per_page=100`)).json();
  const versions = tags.map(t => t.name).filter(n => /^v\d+\.\d+\.\d+$/.test(n));
  if (!versions.length) throw new Error(`no release tags on ${repo}`);
  return versions.reduce((best, tag) => (newer(tag, best) ? tag : best));
}

/** Downloads and unpacks `repo@tag`; returns the folder holding the project. */
async function fetchTemplate(repo, tag, into) {
  const res = await github(`/repos/${repo}/tarball/${tag}`);
  const file = join(into, `${tag}.tar.gz`);
  writeFileSync(file, Buffer.from(await res.arrayBuffer()));
  const dir = join(into, tag);
  mkdirSync(dir, {recursive: true});
  execFileSync('tar', ['-xzf', file, '-C', dir, '--strip-components=1']);
  return dir;
}

function listFiles(dir, base = dir, out = []) {
  for (const name of readdirSync(dir)) {
    const full = join(dir, name);
    const rel = relative(base, full).split('\\').join('/');
    if (NEVER.some(re => re.test(rel + (statSync(full).isDirectory() ? '/' : '')))) continue;
    if (statSync(full).isDirectory()) listFiles(full, base, out);
    else out.push(rel);
  }
  return out;
}

/**
 * Files a downloaded copy carries that are not the template's: its `.gitignore` names them one by
 * one (a plain file name on its own line, no wildcard). They are never copied into the project.
 */
function downloadOnlyFiles(dir) {
  const file = join(dir, '.gitignore');
  if (!existsSync(file)) return new Set();
  const names = readFileSync(file, 'utf8')
    .split(/\r?\n/)
    .map(line => line.trim().replace(/^\//, ''))
    .filter(line => line && !line.startsWith('#') && !line.startsWith('!') && !/[*?[\]\/]/.test(line) && /\.[A-Za-z0-9]+$/.test(line));
  return new Set(names.filter(name => existsSync(join(dir, name)) && statSync(join(dir, name)).isFile()));
}

const read = path => (existsSync(path) ? readFileSync(path) : null);
const same = (a, b) => a !== null && b !== null && Buffer.compare(a, b) === 0;

function write(path, data, dryRun) {
  if (dryRun) return;
  mkdirSync(dirname(path), {recursive: true});
  writeFileSync(path, data);
}

async function docs() {
  const site = (option('--site') ?? process.env.SITE_URL ?? '').replace(/\/+$/, '');
  if (!site) throw new Error('pass --site https://<your site host> (or set SITE_URL).');
  const out = join(ROOT, '.platform', 'docs');
  mkdirSync(out, {recursive: true});
  const get = url => fetch(url, {headers: {'user-agent': 'site-platform-script'}}).catch(() => null);

  // The platform's rules for sites (core + topics). The template's own copy is
  // docs/agent/; the live one wins when they differ.
  const base = `${site}/api/public/site/v1/agent-docs`;
  const index = await get(base);
  if (!index?.ok) throw new Error(`nothing saved: ${base} answered ${index?.status ?? 'no response'}.`);
  const core = await index.text();
  writeFileSync(join(out, 'core.md'), core);
  const topics = [...new Set([...core.matchAll(/([a-z0-9-]+(?:\/[a-z0-9-]+)?)\.md/g)].map(m => m[1]))];
  let saved = 1;
  for (const topic of topics) {
    const res = await get(`${base}/${topic}.md`);
    if (!res?.ok) continue;
    write(join(out, `${topic}.md`), await res.text(), false);
    saved++;
  }
  const version = core.match(/\(v([0-9a-f]{6,})\)/)?.[1] ?? 'unknown';
  writeFileSync(join(out, 'VERSION'), `${version}\n`);
  console.log(`saved ${saved} docs (platform rules v${version}) to .platform/docs/ — start with core.md.`);
}

/**
 * The template's own files (`scripts/managed-files.json`, this release's list: path → sha256),
 * compared byte for byte with this project. Changed, missing and (under its folders) added files,
 * with the git command that puts the template's copies back.
 */
function managedProblems(manifest) {
  const restoreHint = rels => `put back the template's copies: git checkout v${manifest.version} -- ${rels.join(' ')}`;
  if (!existsSync(MANAGED_LIST)) return {problems: [`${MANAGED_LIST_REL}: missing`], restore: [MANAGED_LIST_REL], restoreHint};
  let list;
  try {
    list = JSON.parse(readFileSync(MANAGED_LIST, 'utf8'));
  } catch {
    return {problems: [`${MANAGED_LIST_REL}: not valid JSON`], restore: [MANAGED_LIST_REL], restoreHint};
  }
  if (list.version !== manifest.version) {
    return {
      problems: [`template.json says ${manifest.version}, but ${MANAGED_LIST_REL} (like the rest of the template's files) is for ${list.version}: a merge was left half done, or template.json was edited`],
      restore: ['template.json'],
      // The release the other files are on: managed-files.json comes with them, template.json is one small file.
      restoreHint: rels => `finish the merge (git merge v${list.version}), or put back the template's copy: git checkout v${list.version} -- ${rels.join(' ')}`,
    };
  }
  const problems = [];
  const restore = [];
  const hash = buf => createHash('sha256').update(buf).digest('hex');
  for (const [rel, want] of Object.entries(list.files ?? {})) {
    const got = read(join(ROOT, rel));
    if (got === null) {
      problems.push(`${rel}: missing`);
      restore.push(rel);
    } else if (hash(got) !== want) {
      const crlf = got.includes(13) && hash(Buffer.from(got.toString('utf8').replace(/\r\n/g, '\n'))) === want;
      problems.push(`${rel}: ${crlf ? 'Windows line endings (CRLF); the template uses LF (git add --renormalize .)' : 'changed'}`);
      restore.push(rel);
    }
  }
  for (const dir of list.dirs ?? []) {
    const full = join(ROOT, dir);
    if (!existsSync(full)) continue;
    for (const rel of listFiles(full, ROOT)) if (!(rel in (list.files ?? {}))) problems.push(`${rel}: added (move your code out of ${dir}, then git rm it)`);
  }
  return {problems, restore, restoreHint, list};
}

/** A store listing (this project holds the store's own check, store-check.mjs) of a web app ships sample data. */
function seedProblems(list) {
  if (!SEED_REQUIRED || !existsSync(join(ROOT, 'store-check.mjs'))) return {problems: [], notes: []};
  const seed = read(join(ROOT, 'prisma', 'seed.sql'));
  const text = seed ? seed.toString('utf8').replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/--[^\n]*/g, ' ').trim() : '';
  if (!text) return {problems: ['prisma/seed.sql: missing or empty (seed_missing). Add sample data so the store preview shows a working app.'], notes: []};
  if (!/\binsert\s+into\b/i.test(text)) return {problems: ['prisma/seed.sql: adds no rows (seed_empty). Give it at least one INSERT of sample data.'], notes: []};
  const example = list?.exampleSeed;
  if (example && createHash('sha256').update(seed).digest('hex') === example) {
    return {problems: [], notes: ['prisma/seed.sql is still the template\'s example data: replace it with sample data for your app.']};
  }
  return {problems: [], notes: []};
}

/** The newest `vX.Y.Z` tag of the `upstream` git remote: {tag}, {error}, or null without that remote. */
function upstreamLatest() {
  let remotes = '';
  try {
    remotes = execFileSync('git', ['remote'], {cwd: ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore']});
  } catch {
    return null;
  }
  if (!remotes.split('\n').includes('upstream')) return null;
  try {
    const out = execFileSync('git', ['ls-remote', '--tags', '--refs', 'upstream'], {cwd: ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 20000});
    const tags = out.split('\n').map(line => line.trim().split(/\s+/)[1]?.replace('refs/tags/', '')).filter(t => /^v\d+\.\d+\.\d+$/.test(t ?? ''));
    if (!tags.length) return {error: 'the upstream remote has no release tags'};
    return {tag: tags.reduce((best, t) => (newer(t, best) ? t : best))};
  } catch {
    return {error: 'the upstream remote did not answer (offline?)'};
  }
}

async function check() {
  const manifest = readManifest();
  const current = `v${manifest.version}`;
  let failed = false;

  // 1. The template's own files: untouched (a store listing is refused otherwise).
  const managed = managedProblems(manifest);
  // A site's own rules may follow the template's at the end of AGENTS.md (upgrade.md); a store
  // listing (it holds store-check.mjs) keeps AGENTS.md, CLAUDE.md and GEMINI.md exactly.
  const store = existsSync(join(ROOT, 'store-check.mjs'));
  const ownRules = rel => !store && /^(AGENTS|CLAUDE|GEMINI)\.md$/.test(rel);
  const soft = managed.problems.filter(p => ownRules(p.split(':')[0]));
  const hard = managed.problems.filter(p => !soft.includes(p));
  const restore = managed.restore.filter(rel => !ownRules(rel));
  for (const line of soft) console.log(`note: ${line} (fine if this site added its own rules at its end; the template's part stays as it is)`);
  if (hard.length) {
    failed = true;
    console.log(`template files changed (never edit them):\n  ${hard.join('\n  ')}`);
    if (restore.length) console.log(`fix: ${managed.restoreHint(restore)}`);
  } else if (soft.length) {
    console.log(`template files: the rest untouched (release ${manifest.version}).`);
  } else {
    console.log(`template files: untouched (${Object.keys(managed.list?.files ?? {}).length} files, release ${manifest.version}).`);
  }

  // 2. A store listing's sample data.
  const seed = seedProblems(managed.list);
  for (const line of seed.problems) console.log(line);
  for (const line of seed.notes) console.log(`note: ${line}`);
  if (seed.problems.length) failed = true;

  // 3. Is a newer release out? The upstream remote (a clone of the template's repository), else
  //    the folder holding the newest template (`--from`, a `file:` repo, the editor's copy).
  let latest = null;
  const upstream = option('--from') ? null : upstreamLatest();
  if (upstream?.tag) latest = upstream.tag;
  else if (upstream?.error) console.log(`note: couldn't check for a newer release: ${upstream.error}.`);
  else {
    try {
      latest = await latestTag(manifest.repo);
    } catch (error) {
      if (option('--from') || process.env.SITE_DIR) throw error;
      console.log(`note: couldn't check for a newer release.\n${DOWNLOADED_COPY_HELP}`);
    }
  }
  if (latest && newer(latest, current)) {
    console.log(
      upstream?.tag
        ? `Your template is ${manifest.version}; the current one is ${latest.replace(/^v/, '')}. Merge it before you submit: git fetch upstream --tags, then git merge ${latest}, then npm install, npm run check and node store-check.mjs.`
        : `template ${current} → ${latest} is available. Read its CHANGELOG, then: npm run platform -- upgrade`
    );
    if (!failed) process.exitCode = 10;
  } else if (latest) {
    console.log(`up to date (template ${current}).`);
  }
  if (failed) process.exitCode = 1;
}

async function upgrade() {
  const manifest = readManifest();
  const dryRun = flag('--dry-run');
  const from = `v${manifest.version}`;
  const to = option('--to') ?? (await latestTag(manifest.repo));
  if (!newer(to, from)) return console.log(`nothing to do: ${from} is not older than ${to}.`);
  if (!dryRun) {
    try {
      const dirty = execFileSync('git', ['status', '--porcelain'], {cwd: ROOT}).toString().trim();
      if (dirty && !flag('--force')) {
        throw new Error('commit or stash your changes first (or pass --force), so the upgrade is one reviewable diff.');
      }
    } catch (error) {
      if (error.message.startsWith('commit')) throw error;
    }
  }

  const work = mkdtempSync(join(tmpdir(), 'site-upgrade-'));
  try {
    const local = localSource(manifest.repo);
    let oldDir, newDir;
    if (local) {
      if (to !== localVersion(local)) throw new Error(`${local} holds ${localVersion(local)}, not ${to}.`);
      newDir = local;
      oldDir = option('--base') ? resolve(ROOT, option('--base')) : null;
      if (oldDir && localVersion(oldDir) !== from) throw new Error(`--base holds ${localVersion(oldDir)}, not ${from}.`);
    } else {
      [oldDir, newDir] = await Promise.all([
        fetchTemplate(manifest.repo, from, work),
        fetchTemplate(manifest.repo, to, work),
      ]);
    }
    const extras = downloadOnlyFiles(newDir);
    const files = new Set([...(oldDir ? listFiles(oldDir) : []), ...listFiles(newDir)].filter(rel => !extras.has(rel)));
    const report = {replaced: [], merged: [], added: [], removed: [], conflicts: [], kept: []};

    for (const rel of [...files].sort()) {
      const base = oldDir ? read(join(oldDir, rel)) : null;
      const theirs = read(join(newDir, rel));
      const oursPath = join(ROOT, rel);
      const ours = read(oursPath);
      if (same(base, theirs)) continue; // the template did not change this file
      if (!oldDir && same(ours, theirs)) continue; // already the new version

      if (MANAGED.some(re => re.test(rel))) {
        if (theirs === null) {
          if (ours !== null && !dryRun) rmSync(oursPath);
          report.removed.push(rel);
        } else {
          write(oursPath, theirs, dryRun);
          report.replaced.push(rel);
        }
        continue;
      }
      if (SKIP_IN_MERGE.some(re => re.test(rel))) {
        report.kept.push(`${rel} (regenerate with npm install)`);
        continue;
      }
      if (theirs === null) {
        report.kept.push(`${rel} (removed upstream; delete it yourself if unused)`);
        continue;
      }
      if (!oldDir) {
        if (ours === null) {
          write(oursPath, theirs, dryRun);
          report.added.push(rel);
        } else {
          report.kept.push(`${rel} (differs from ${to}; no --base to merge against: compare it yourself)`);
        }
        continue;
      }
      if (ours === null) {
        if (base === null) {
          write(oursPath, theirs, dryRun);
          report.added.push(rel);
        } else {
          report.kept.push(`${rel} (you deleted it; upstream changed it)`);
        }
        continue;
      }
      if (same(ours, base) || same(ours, theirs)) {
        write(oursPath, theirs, dryRun);
        report.merged.push(rel);
        continue;
      }
      // 3-way merge: ours ← (base → theirs)
      const tmpOurs = join(work, 'ours'), tmpBase = join(work, 'base'), tmpTheirs = join(work, 'theirs');
      writeFileSync(tmpOurs, ours);
      writeFileSync(tmpBase, base ?? Buffer.alloc(0));
      writeFileSync(tmpTheirs, theirs);
      let conflicts = 0;
      try {
        execFileSync('git', ['merge-file', '-L', 'yours', '-L', from, '-L', to, tmpOurs, tmpBase, tmpTheirs]);
      } catch (error) {
        conflicts = error.status ?? 1;
      }
      write(oursPath, readFileSync(tmpOurs), dryRun);
      (conflicts ? report.conflicts : report.merged).push(conflicts ? `${rel} (${conflicts})` : rel);
    }

    if (!dryRun) writeFileSync(MANIFEST, `${JSON.stringify({...manifest, version: to.replace(/^v/, '')}, null, 2)}\n`);

    const show = (title, list) => list.length && console.log(`\n${title}:\n  ${list.join('\n  ')}`);
    console.log(`${dryRun ? '[dry run] ' : ''}template ${from} → ${to}`);
    show('replaced (managed)', report.replaced);
    show('merged', report.merged);
    show('added', report.added);
    show('removed (managed)', report.removed);
    show('left as is', report.kept);
    show('CONFLICTS — search for <<<<<<< and resolve', report.conflicts);
    console.log('\nnext: read CHANGELOG.md, resolve conflicts, npm install, npm run build, git diff.');
    if (report.conflicts.length) process.exitCode = 2;
  } finally {
    rmSync(work, {recursive: true, force: true});
  }
}

/*
 * `plans` (docs/agent/plans-catalogue.md): the owner's REAL client plans, read with the editor's
 * Site Key (never printed), so a pricing section's build-time fallback is never invented.
 * `--write` saves the answer to src/content/plans.json, the shape the live script reads.
 */
async function plans() {
  const site = (process.env.SITE_URL ?? '').replace(/\/+$/, '');
  const key = process.env.SITE_KEY ?? '';
  if (!site || !key) throw new Error('needs SITE_URL and SITE_KEY (the editor sets both).');
  const res = await fetch(`${site}/api/site/v1/plans`, {
    headers: {accept: 'application/json', authorization: `Bearer ${key}`, 'user-agent': 'site-platform-script'},
    signal: AbortSignal.timeout(15000),
  }).catch(() => null);
  if (!res) throw new Error('the platform did not answer.');
  if (res.status === 404) {
    console.log("no plans to show: this site isn't the owner's own website (plans-catalogue.md is not for it).");
    return;
  }
  const answer = await res.json().catch(() => null);
  if (!res.ok) {
    const error = answer?.errors?.[0];
    throw new Error(`${error?.code ?? res.status}: ${error?.message ?? 'refused.'}`);
  }
  const catalogue = answer?.data ?? {};
  const closed = catalogue.checkoutOpen === false ? ` (not open yet: ${catalogue.opensLaterReason ?? 'payments'}; paid buttons show "Coming soon")` : '';
  console.log(`sign-up: ${catalogue.signupUrl}${closed}`);
  if (!catalogue.plans?.length) console.log('no plans yet: the owner adds them in the dashboard. Keep a plain sign-up link.');
  for (const p of [...(catalogue.plans ?? []), ...(catalogue.addons ?? [])]) {
    const shown = (p.prices ?? [])
      .filter(price => price.availability === 'open')
      .map(price => `${price.amount} ${price.currency} ${price.interval ? `/${price.interval}` : price.priceType}${price.trialDays ? ` (${price.trialDays}-day trial)` : ''}`);
    console.log(`  ${p.kind === 'addon' ? 'add-on ' : ''}${String(p.code).padEnd(20)} ${p.name}: ${shown.join(', ') || 'no open price (leave it out)'}`);
  }
  if (flag('--write')) {
    const file = join(ROOT, 'src', 'content', 'plans.json');
    mkdirSync(dirname(file), {recursive: true});
    writeFileSync(file, `${JSON.stringify(catalogue, null, 2)}\n`);
    console.log('wrote src/content/plans.json: build the pricing section from it. Run this again when the owner changes plans.');
  }
}

const commands = {docs, check, upgrade, plans};
if (!commands[command]) {
  console.log('usage: npm run platform -- docs [--site URL] | check [--from DIR] | upgrade [--to vX.Y.Z] [--from DIR] [--base DIR] [--dry-run] [--force] | plans [--write]');
} else {
  commands[command]().catch(error => {
    console.error(`platform ${command}: ${error.message}`);
    process.exitCode = 1;
  });
}
