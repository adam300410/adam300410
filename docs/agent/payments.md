---
title: Payments: Buy and Subscribe buttons on a static site
read-when: before anything sold: a Buy or Subscribe button, a price, a checkout, a "pay" link, a thank-you page after paying
---
Payments are BUILT IN. The platform holds the site's payment account and talks to the gateway;
this site never sees a card, a key or a secret. The visitor never leaves your page: the payment
opens in a form in the Buy button's dialog (Stripe), in the provider's popup (Razorpay), or in a
small payment window (Xendit, PayPal). Use this by default whenever the site sells something (a product, a booking fee, a
donation of a set amount, a subscription). Nothing to install, no key to ask for, no checkout
script to add: the Buy button brings its own.

Not this: the platform's own plans for an owner who resells the studio (`plans-catalogue.md`:
sign-up links).

## Prices: the owner's catalogue, referenced by key
Every price lives in the site's catalogue: a `key` (slug), a name, an amount, a currency, and
one-time, monthly or yearly. The site names a price ONLY by its key; the platform charges the
catalogue's amount whatever the browser sends. A static site has no key to create prices itself:
tell the owner exactly which to add in the dashboard (the site's Payments tab), for example
"add a one-time price with key `print-a3`, name 'A3 print', 45.00 USD". Write the amount the
visitor reads into the page's copy from what the owner set, and say it must match.
A paid price is at least the currency's minimum (1 USD, 10,000 IDR, 100 JPY…) and a whole amount
in IDR or ISK: suggest prices the dashboard accepts.
The dashboard offers only the currencies the owner's payment providers take (some only one-time),
so ask the owner which currency they sell in before writing prices into the page.

## Selling, in three steps (follow them exactly)
1. **Show the prices, each with a Buy button.** `<PriceList>` reads the catalogue in the browser
   (`GET {{API_PREFIX}}/public/site/v1/payments` → `{mode, ready, prices}`; `ready` false = not
   taking payments yet → "Coming soon", no button) and draws one card per price. Or write your own
   copy and put a `<BuyButton price="<key>">` in it (it turns itself into "Coming soon" too).
2. **Let the visitor choose how to pay** when more than one way takes the price (its currency,
   and its free trial when it has one): the Buy dialog asks "How do you want to pay?" (the ways
   from `GET …/payments/gateways?price=<key>` → `{gateways: [{slug, name}]}`). One way → no
   question. Never pick a provider in code.
3. **Open it without leaving the page**, then the thank-you page. The checkout
   (`POST …/payments/checkout`, `{price, quantity, successPath, cancelPath, gateway?, noTrial?, idempotencyKey?}`
   → `{id, mode, embedded}`) answers ONE step, `embedded.kind`, and the dialog opens it:
   - `element`: the provider's form in the dialog (Stripe, `src/lib/inline-checkout.ts`).
   - `popup` (`clientCheckout`): the provider's popup over the page (Razorpay). Paid → the
     provider sends the visitor to `successPath`; closed → Pay opens the SAME checkout again.
   - `window` (`checkoutUrl`): the provider's page in a small payment window (Xendit, PayPal);
     the dialog says "Finish paying in the payment window" (Reopen, Cancel) and checks
     `GET …/payments/checkout/:id` (`{status}`) until it is `paid`, then goes to `successPath`.
     Closed without paying → back to Pay, nothing charged; a blocked window → this tab.
   A price with a free trial needs the visitor's email: pass `email` in the checkout request
   (422 `E_SITE_PAYMENTS_EMAIL_REQUIRED` without it), so ask for it before the trial starts.
   A visitor who already had the price's free trial (409 `E_SITE_PAYMENTS_TRIAL_USED`) gets
   "You've already used the free trial for this. Continue to pay now without a trial." and the
   next Continue sends `noTrial: true`.

### The complete example
```astro
---
// src/pages/pricing.astro
import PriceList from '../components/PriceList.astro';
import Base from '../layouts/Base.astro';
---
<Base title="Pricing" description="Plans and prices.">
  <section class="wrap section">
    <h1>Pricing</h1>
    <PriceList keys={['coaching-monthly', 'coaching-yearly', 'print-a3']} successPath="/thank-you/" />
  </section>
</Base>
```
One price in your own words, anywhere:
```astro
---
import BuyButton from '../components/BuyButton.astro';
---
<p>A3 print, 45.00 USD.</p>
<BuyButton price="print-a3" label="Buy the print" successPath="/thank-you/" />
```
The thank-you page (a static page cannot check the payment, so it never promises more):
```astro
---
// src/pages/thank-you.astro  (+ '/thank-you/' in EXCLUDE in src/pages/sitemap.xml.ts)
import Base from '../layouts/Base.astro';
---
<Base title="Thank you" description="Your payment went through." noindex>
  <section class="wrap section">
    <h1>Thank you</h1>
    <p>Your receipt is on its way by email.</p>
    <p><a class="button" href="/">Back to home</a></p>
  </section>
</Base>
```

### How it works
- Everything goes to THIS site's address (same origin, no key: the platform knows the site by its
  address), from `src/lib/buy-button.ts`. The dialog runs a `CheckoutSession`
  (`src/lib/checkout-session.ts`, framework-free: `load()` → `selectGateway()` → `continue()`,
  state `{status, gateways, selectedGateway, checkoutUrl, result, error, trialUsed, closed}`)
  over `publicCheckoutTransport`. Don't edit the session; restyle the dialog
  (`src/components/CheckoutDialog.astro`) and the cards (`PriceList.astro`) freely.
- `BuyButton` props: `price` (key), `label`, `quantity` (1–99), `successPath` (a path on this
  site, default `/`), `cancelPath` (default this page), `variant`. `PriceList` props: `keys`
  (default all, in catalogue order), `successPath`. Paths only, never a URL.
- The page the provider sends the visitor back to INSIDE the payment window tells the Buy
  button's page and closes itself (`notifyPaymentOpener()` in `src/layouts/Base.astro`): keep it.
- A page with a Buy button or a price list allows the providers' form and popup hosts in its own
  CSP (the components do it, `src/lib/checkout-hosts.ts`); a payment window needs none; other
  pages keep `connect-src 'self'`. Don't edit that list.
- The button needs JavaScript (one small module from this site, bundled; the provider's own
  script loads only when a visitor pays; it is the payments exception to "no JavaScript", like
  the live prices script). Without it, the button says so.

## Sandbox and live
- The Preview is the dev server: the platform does not answer there, so the button says
  "Payments work on your published site." That is expected.
- The published site takes TEST payments (sandbox; the checkout says so) until the owner connects
  a live gateway in the dashboard; then it takes real payments by itself. Nothing in the code
  changes between the two.
- `404` from the endpoint means payments are not on for this site (or the Preview): the button
  shows the same note. Other errors show their message next to the button.

## What a static site cannot do (then propose the app template)
Memberships that open member-only pages, accounts, a list of orders on the site, anything that
happens on the site after a payment (a booking saved, a download unlocked): those need the
**Astro app** template (the dashboard's "make it an app"), whose payments add orders and
memberships in its database and gated pages. A subscription button here
is fine; what it unlocks cannot live on this site. The owner still sees every payment, refund
and subscription in the dashboard.

## Never
- An amount, a gateway key or a price id in a request: the price KEY only.
- A card field, or a checkout or payment script of your own (Stripe.js, PayPal buttons): the Buy
  button's built-in form is the only one.
- A "members area" faked in the browser after a payment (`localStorage`, a secret URL).
- `innerHTML` with anything from the answer; `textContent` only.

## Once per attempt
The Buy button sends an idempotency key: one per attempt, the same on every retry, so a double click, a retry or a
reload gets the same checkout, and an attempt already paid goes to the thanks page (`src/lib/idempotency.ts`). Keep it.
A script of your own posting to the checkout endpoint sends `idempotencyKey` too (16 to 64 letters, digits, - or _).
This site has no database, so it can't count stock or hold a booking: anything limited needs the app template.
