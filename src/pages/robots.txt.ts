import type {APIRoute} from 'astro';

import {absoluteUrl} from '../lib/site-url';

/**
 * `/robots.txt`, built with the site: every crawler may read every page, and
 * learns where the sitemap is. Only block search engines when the owner asks
 * for exactly that (`docs/agent/seo.md`).
 */
export const GET: APIRoute = () => {
  const sitemap = absoluteUrl('/sitemap.xml');
  const body = ['User-agent: *', 'Allow: /', ...(sitemap ? ['', `Sitemap: ${sitemap}`] : []), ''].join('\n');
  return new Response(body, {headers: {'Content-Type': 'text/plain; charset=utf-8'}});
};
