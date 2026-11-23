/**
 * BUILD TIME: the hosts the in-page payment form and the provider's popup need, per gateway
 * (docs/agent/payments.md). A payment window is another window: it needs nothing here.
 * `<BuyButton>` adds them to the Content-Security-Policy of the page it is on, and only that page
 * (`allowInlineCheckout(Astro.csp)`): the rest of the site keeps `connect-src 'self'`. The
 * gateway is only known at the click, so a page with a Buy button allows every gateway that has a
 * form in `src/lib/inline-checkout.ts` or a popup the platform opens (Razorpay). A new one adds
 * its hosts here.
 */
export const CHECKOUT_HOSTS = {
  stripe: {
    script: ['https://js.stripe.com', 'https://*.js.stripe.com'],
    frame: ['https://js.stripe.com', 'https://*.js.stripe.com', 'https://hooks.stripe.com'],
    connect: ['https://api.stripe.com', 'https://merchant-ui-api.stripe.com'],
  },
  razorpay: {
    script: ['https://checkout.razorpay.com'],
    frame: ['https://api.razorpay.com', 'https://checkout.razorpay.com'],
    connect: ['https://api.razorpay.com', 'https://lumberjack.razorpay.com'],
  },
} as const;

/** The site-wide frame hosts (`astro.config.mjs`), kept when this page widens `frame-src`. */
const SITE_FRAMES = ['https://www.youtube.com', 'https://www.youtube-nocookie.com', 'https://player.vimeo.com', 'https://fast.wistia.net'];

interface CspApi {
  insertDirective: (directive: string) => void;
  insertScriptResource: (resource: string) => void;
}

export function allowInlineCheckout(csp: CspApi | undefined): void {
  if (!csp) return;
  const all = Object.values(CHECKOUT_HOSTS);
  const uniq = (list: readonly string[]) => [...new Set(list)];
  // A script resource replaces Astro's default `'self'`: keep it.
  csp.insertScriptResource("'self'");
  for (const host of uniq(all.flatMap(h => h.script))) csp.insertScriptResource(host);
  csp.insertDirective(`frame-src ${uniq([...SITE_FRAMES, ...all.flatMap(h => h.frame)]).join(' ')}`);
  csp.insertDirective(`connect-src 'self' ${uniq(all.flatMap(h => h.connect)).join(' ')}`);
}
