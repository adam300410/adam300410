import {SITE} from '../content/site';
import {absoluteUrl, siteOrigin} from './site-url';

/**
 * Structured data (schema.org JSON-LD) for search engines, rendered by
 * `src/layouts/Base.astro` as `<script type="application/ld+json">`. That is a
 * data block, never executed, so the CSP does not apply to it; it is built
 * only from this repo's own content (`SITE`, post front matter).
 *
 * Only facts that are visible on the page (Google's rule): a name, a date, an
 * author. Absolute URLs only when the site's address is known (site-url.ts).
 */
export type JsonLd = Record<string, unknown>;

/** JSON safe inside `<script>`: no `</script>`, no `<!--`. */
export function jsonLdText(data: JsonLd): string {
  return JSON.stringify(data)
    .replace(/</g, '\\u003c')
    .replace(/>/g, '\\u003e')
    .replace(/&/g, '\\u0026');
}

const ids = () => {
  const origin = siteOrigin();
  return origin ? {website: `${origin}/#website`, organization: `${origin}/#organization`} : null;
};

/** The home page's `WebSite` + `Organization` (the site name in search results). */
export function siteJsonLd(): JsonLd {
  const home = absoluteUrl('/');
  const id = ids();
  return {
    '@context': 'https://schema.org',
    '@graph': [
      {
        '@type': 'WebSite',
        ...(id && {'@id': id.website}),
        name: SITE.name,
        description: SITE.description,
        inLanguage: SITE.lang,
        ...(home && {url: home}),
        ...(id && {publisher: {'@id': id.organization}}),
      },
      {
        '@type': 'Organization',
        ...(id && {'@id': id.organization}),
        name: SITE.name,
        ...(home && {url: home}),
      },
    ],
  };
}

/** A blog post's `BlogPosting`, from its front matter. */
export function postJsonLd(post: {
  path: string;
  title: string;
  description: string;
  publishedAt: Date;
  updatedAt?: Date;
  author?: string;
  image?: string;
}): JsonLd {
  const url = absoluteUrl(post.path);
  const image = post.image ? absoluteUrl(post.image) : null;
  const id = ids();
  const organization = {'@type': 'Organization', ...(id && {'@id': id.organization}), name: SITE.name};
  return {
    '@context': 'https://schema.org',
    '@type': 'BlogPosting',
    headline: post.title,
    description: post.description,
    datePublished: post.publishedAt.toISOString(),
    dateModified: (post.updatedAt ?? post.publishedAt).toISOString(),
    author: post.author ? {'@type': 'Person', name: post.author} : organization,
    publisher: organization,
    inLanguage: SITE.lang,
    ...(url && {url, mainEntityOfPage: url}),
    ...(image && {image}),
  };
}
