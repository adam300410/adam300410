import type {APIRoute} from 'astro';

import {postHref, publishedPosts, tagHref, tagsOf} from '../lib/posts';
import {absoluteUrl} from '../lib/site-url';

/**
 * `/sitemap.xml`, built with the site: every page in `src/pages/` (found
 * automatically), every post and every tag page. A page that must stay out of
 * search (it has `<Base noindex>`) goes in `EXCLUDE` too.
 *
 * Search engines need absolute URLs: without a known address (`npm run dev`,
 * the Preview) the list is empty. A Publish always knows it (src/lib/site-url.ts).
 */
const EXCLUDE = new Set(['/404/']);

/** `./about.astro` → `/about/`, `./blog/index.astro` → `/blog/`; dynamic (`[slug]`) pages are listed below. */
const pagePaths = Object.keys(import.meta.glob('./**/*.astro'))
  .filter(file => !file.includes('['))
  .map(file => `/${file.slice(2).replace(/\.astro$/, '').replace(/(^|\/)index$/, '')}/`.replace(/\/+/g, '/'))
  .filter(path => !EXCLUDE.has(path))
  .sort();

const escape = (value: string) => value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

export const GET: APIRoute = async () => {
  const posts = await publishedPosts();
  const entries: {path: string; lastmod?: Date}[] = [
    ...pagePaths.map(path => ({path})),
    ...posts.map(post => ({path: postHref(post), lastmod: post.data.updatedAt ?? post.data.publishedAt})),
    ...tagsOf(posts).map(tag => ({path: tagHref(tag.label)})),
  ];
  const urls = entries
    .map(({path, lastmod}) => ({loc: absoluteUrl(path), lastmod}))
    .filter((entry): entry is {loc: string; lastmod: Date | undefined} => entry.loc !== null)
    .map(
      ({loc, lastmod}) =>
        `  <url><loc>${escape(loc)}</loc>${lastmod ? `<lastmod>${lastmod.toISOString().slice(0, 10)}</lastmod>` : ''}</url>`
    );
  const body = `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls.join('\n')}${urls.length ? '\n' : ''}</urlset>\n`;
  return new Response(body, {headers: {'Content-Type': 'application/xml; charset=utf-8'}});
};
