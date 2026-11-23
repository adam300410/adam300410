import {SITE_URL} from 'astro:env/server';

import {SITE} from '../content/site';

/**
 * BUILD TIME: the site's public origin (`https://www.example.com`, no trailing
 * slash), for canonical URLs, social cards, the sitemap, robots.txt and RSS.
 *
 *   1. `SITE.url` in `src/content/site.ts` when the owner set it (a custom domain);
 *   2. else `SITE_URL`, which the platform sets during a Publish build (the
 *      site's own address);
 *   3. else '' (`npm run dev`, the Preview): pages then leave absolute URLs out.
 */
export function siteOrigin(): string {
  const fromPlatform = import.meta.env.DEV ? '' : (SITE_URL ?? '');
  const origin = (SITE.url || fromPlatform).trim().replace(/\/+$/, '');
  return /^https?:\/\/[^/\s]+$/.test(origin) ? origin : '';
}

/** `path` (or an https URL) as an absolute URL; null when the origin is not known. */
export function absoluteUrl(path: string): string | null {
  if (/^https:\/\//.test(path)) return path;
  const origin = siteOrigin();
  return origin ? new URL(path, `${origin}/`).href : null;
}
