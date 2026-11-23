import type {APIRoute} from 'astro';

import {SITE} from '../content/site';
import {postHref, publishedPosts} from '../lib/posts';
import {absoluteUrl} from '../lib/site-url';

/**
 * `/rss.xml`: the blog as an RSS 2.0 feed (the 20 newest posts: title,
 * description, date, tags), built with the site. Every page links to it from
 * `<head>` (src/layouts/Base.astro). No dependency: the XML is written here
 * and every value is escaped.
 */
const LIMIT = 20;

const escape = (value: string) =>
  value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

export const GET: APIRoute = async () => {
  const posts = (await publishedPosts()).slice(0, LIMIT);
  // Feed readers need absolute links; without a known address (dev, Preview) they stay site-relative.
  const link = (path: string) => absoluteUrl(path) ?? path;
  const items = posts.map(post => {
    const url = link(postHref(post));
    return [
      '    <item>',
      `      <title>${escape(post.data.title)}</title>`,
      `      <link>${escape(url)}</link>`,
      `      <guid isPermaLink="true">${escape(url)}</guid>`,
      `      <description>${escape(post.data.description)}</description>`,
      `      <pubDate>${post.data.publishedAt.toUTCString()}</pubDate>`,
      ...post.data.tags.map(tag => `      <category>${escape(tag)}</category>`),
      '    </item>',
    ].join('\n');
  });
  const self = absoluteUrl('/rss.xml');
  const body = [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">',
    '  <channel>',
    `    <title>${escape(SITE.name)}</title>`,
    `    <link>${escape(link('/blog/'))}</link>`,
    `    <description>${escape(SITE.description)}</description>`,
    `    <language>${escape(SITE.lang)}</language>`,
    ...(self ? [`    <atom:link href="${escape(self)}" rel="self" type="application/rss+xml" />`] : []),
    ...(posts[0] ? [`    <lastBuildDate>${posts[0].data.publishedAt.toUTCString()}</lastBuildDate>`] : []),
    ...items,
    '  </channel>',
    '</rss>',
    '',
  ].join('\n');
  return new Response(body, {headers: {'Content-Type': 'application/rss+xml; charset=utf-8'}});
};
