import {type CollectionEntry, getCollection} from 'astro:content';

/** BUILD TIME helpers for the blog collection (`src/content.config.ts`). */

export type BlogPost = CollectionEntry<'blog'>;

/** Published posts, newest first. Drafts appear in `npm run dev` only. */
export async function publishedPosts(): Promise<BlogPost[]> {
  const posts = await getCollection('blog', post => import.meta.env.DEV || !post.data.draft);
  return posts.sort((a, b) => b.data.publishedAt.getTime() - a.data.publishedAt.getTime());
}

export const postHref = (post: BlogPost) => `/blog/${post.id}/`;

/** A tag's URL name: `Hair care` → `hair-care`. */
export const tagSlug = (tag: string) =>
  tag
    .toLowerCase()
    .replace(/[^\p{L}\p{N}]+/gu, '-')
    .replace(/^-+|-+$/g, '') || 'tag';

export const tagHref = (tag: string) => `/blog/tags/${tagSlug(tag)}/`;

/** Every tag in use, with its label (first spelling seen) and its posts (newest first). */
export function tagsOf(posts: BlogPost[]): {slug: string; label: string; posts: BlogPost[]}[] {
  const tags = new Map<string, {slug: string; label: string; posts: BlogPost[]}>();
  for (const post of posts) {
    for (const tag of new Set(post.data.tags.map(tagSlug))) {
      const label = post.data.tags.find(t => tagSlug(t) === tag)!;
      const entry = tags.get(tag) ?? {slug: tag, label, posts: []};
      entry.posts.push(post);
      tags.set(tag, entry);
    }
  }
  return [...tags.values()].sort((a, b) => a.label.localeCompare(b.label));
}

/** "9 September 2026", in UTC so the build machine's timezone never shifts it. */
export function formatDate(date: Date): string {
  return new Intl.DateTimeFormat('en-GB', {day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC'}).format(date);
}

/** About 230 words a minute, from the Markdown source. */
export function readingMinutes(markdown: string | undefined): number {
  const words = (markdown ?? '').split(/\s+/).filter(Boolean).length;
  return Math.max(1, Math.round(words / 230));
}
