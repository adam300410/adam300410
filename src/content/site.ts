/**
 * The site's own words: name, navigation, home, about and contact copy.
 * Edit freely. Every string is plain text (rendered escaped).
 */

export const SITE = {
  name: 'My site',
  /** One sentence: the home page's meta description and the footer line. */
  description: 'A fast, simple website.',
  /** `en`, `fr`, … : the page language (screen readers, search engines). */
  lang: 'en',
  /**
   * The site's public address, for canonical URLs and social cards
   * (`https://www.example.com`, no trailing slash). Leave empty until known.
   */
  url: '',
};

export interface NavItem {
  label: string;
  href: string;
}

/** Top navigation: pages of THIS site only, plain links. */
export const NAV: NavItem[] = [
  {label: 'Home', href: '/'},
  {label: 'Blog', href: '/blog/'},
  {label: 'About', href: '/about/'},
  {label: 'Contact', href: '/contact/'},
];

/** The home page, top to bottom. Reorder or drop sections freely. */
export const HOME = {
  hero: {
    eyebrow: 'Welcome',
    title: 'A clear headline that says what you do',
    body: 'One or two sentences on who this is for and why it matters. Replace this text with your own.',
    primary: {label: 'Get in touch', href: '/contact/'},
    secondary: {label: 'Read the blog', href: '/blog/'},
  },
  features: {
    title: 'What you get',
    items: [
      {title: 'Fast', body: 'Every page is a prebuilt file, served from the edge in milliseconds.'},
      {title: 'Simple', body: 'Plain pages, clear words, nothing in the way.'},
      {title: 'Yours', body: 'Change any text in one file, or ask your AI agent to.'},
    ],
  },
  cta: {
    title: 'Ready to talk?',
    body: 'Send us a message and we will get back to you within a day.',
  },
};

export const ABOUT = {
  title: 'About us',
  lede: 'Who we are, and why we do this.',
  sections: [
    {heading: 'Our story', body: 'How it started and what drives you. Replace this text with your own story.'},
    {heading: 'What we do', body: 'The services or products you offer, in plain words.'},
    {heading: 'Who we work with', body: 'The people you help, and how you help them.'},
  ],
};

export const CONTACT = {
  title: 'Contact',
  lede: 'Questions, ideas or a project in mind? We read every message.',
  /** Shown as a mailto: link. Use an address you read. */
  email: 'hello@example.com',
  /** Prefilled subject for the email. */
  subject: 'Hello from the website',
  /**
   * Optional: an https:// link to a form hosted elsewhere (a form service the
   * owner uses). Empty = the email link only. A static site cannot receive posts.
   */
  formUrl: '',
};
