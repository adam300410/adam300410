---
title: Security: a static site with nothing to steal
read-when: before adding a script, an embed, an external link or image host, a header, a CSP change or a dependency; and before telling the owner a change is done
---
There is no server, database or session here, so most attacks have nothing to reach. What is
left is the browser: keep scripts out, keep the headers on, keep secrets out of the repo.

## No secrets, ever
- Everything in this repo and in `dist/` is public: anyone can read it. Never put a key, token,
  password or private URL in code, Markdown, `PUBLIC_*` env vars, `.env` (git-ignored, and not
  used by the build) or HTML. You cannot add secrets to a static site; a feature that needs one
  needs the Astro app template (AGENTS.md).

## Content-Security-Policy (`security.csp` in `astro.config.mjs`)
- Astro writes the policy into every page with a hash for each script and style it emits.
  Never add `'unsafe-inline'` or `'unsafe-eval'`, never weaken `default-src 'self'`.
- So: no inline event handlers (`onclick=`), no `style=""` attributes (use classes), no
  `javascript:` links, no third-party `<script src>` tags, no `is:inline` scripts.
- `connect-src 'self'`: the browser calls only this host (the live prices, the Buy button's
  checkout under the platform's path). Leave it. The one exception is built in: a page with a
  `<BuyButton>` allows the payment form's hosts (Stripe, Razorpay) on that page only
  (`src/lib/checkout-hosts.ts`, `payments.md`); never add a payment host or script yourself.
- A new embed host (a video player) goes into `frame-src` explicitly, by exact origin; images
  from https CDNs are already allowed (`img-src 'self' https: data:`). `form-action` allows this
  host and `mailto:` only.
- Markdown code blocks are not highlighted (`syntaxHighlight: false`): Shiki's inline styles
  would break the CSP. Keep it off.

## Headers (`public/_headers`)
`X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`,
`Permissions-Policy` (camera, microphone, geolocation, payment off), `Cross-Origin-Opener-Policy:
same-origin`, `X-Frame-Options: DENY`, and a year of immutable caching for `/_astro/*`. Keep them;
add, never remove. HTTPS and HSTS are the platform's. Never add `Access-Control-Allow-Origin`
(nothing here needs to be read by another site) or a header that loosens framing.

## HTML and URLs
- Astro escapes `{value}`. Never `set:html` / `innerHTML` with anything not written in this repo.
  Build DOM from data with `textContent`. HTML from outside the repo (pasted from a widget, an
  email) never goes into a page or a post; if the owner insists, sanitize it at build time.
- Markdown posts are trusted repo content and rendered as HTML: review what goes in.
- URLs from content: `https:` (and `mailto:` for email) only. `target="_blank"` always with
  `rel="noopener noreferrer"`.
- An `<iframe>` only from a host already in `frame-src`, with a `title`, and `sandbox` when the
  embed allows it.

## Dependencies and the supply chain
- Few dependencies, each one needed, versions pinned by the committed `package-lock.json`;
  `npm ci` installs exactly that. `allowScripts` in `package.json` lists the install scripts that
  may run: never widen it without the owner.
- Before adding one: is it needed at all (HTML, CSS and Astro cover most)? The exact name
  (typosquats like `astro-js`), maintained, widely used, no install script. Install a version
  (`npm install <name>@<version>`) and commit `package.json` with `package-lock.json`; no git or
  tarball URLs, no `latest`, no second lockfile, never edit `node_modules`.
- `npm audit --omit=dev` before done: report high/critical findings to the owner; never
  `npm audit fix --force` (it jumps major versions).
- No CDN scripts, styles or fonts at runtime, no `eval`, no `new Function`.

## Third-party embeds and links
- Each embed (video, map, form) is another company's code in a frame: only when the owner asks,
  from the privacy-friendly host where one exists (`youtube-nocookie.com`), lazy
  (`loading="lazy"`), never one that needs `'unsafe-inline'`.
- Links to other sites from content: `https:` only; `rel="noopener noreferrer"` with
  `target="_blank"`. Never a link that carries a visitor's data in its query string.

## Before you say "done"
No secret in the diff · no `'unsafe-*'`, inline handler, `style=""` or third-party script · new
hosts added to the exact CSP directive · `public/_headers` unchanged or stricter · `npm run build`
passes (a CSP or content-schema mistake fails it) · a new dependency is pinned in the lockfile
and `npm audit --omit=dev` was read.

## The full security check
Not part of every task. Run it when the owner asks for a security check, and when they first ask to go live. Follow the machine
limits in `AGENTS.md`. Only ever against the Preview, never the live site.
1. List every form, script, embed, external link, payment button and file this site's own code adds.
2. Test each by running it in the Preview, with bad input too. Reading the code isn't enough.
3. Fix holes in the site's own code in the same task.
4. A hole in what the template manages: never patch it. Check for a newer template and upgrade, once. If there is none, give the owner a
   short description in plain words to send to support: what goes wrong, not how to do it.
5. End with a short report: what was tested, what was fixed, what is left. Remind the owner that fixes reach visitors only when they publish.
