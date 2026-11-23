import {loadStripe, type Stripe, type StripeElements} from '@stripe/stripe-js';

import type {PaymentStep} from './checkout-session';

/**
 * BROWSER: the payment provider's form ON the page (`kind: 'element'`), mounted in
 * `<BuyButton>`'s dialog (docs/agent/payments.md "How a payment opens"). Card details go from the
 * provider's own frame to the provider, never through this site. The provider's hosts are allowed
 * only on pages with a Buy button (`src/lib/checkout-hosts.ts`). Paid → the platform's
 * `returnUrl`. A popup or a payment window needs no form here (`src/lib/checkout-session.ts`).
 */
export type ElementStep = Extract<PaymentStep, {kind: 'element'}>;

/** What the dialog gives a driver: where to mount, and how to report a problem. */
export interface CheckoutView {
  mount: HTMLElement;
  /** The payment did not go through (the dialog stays open to try again). */
  fail: (message: string) => void;
}

/** One provider's form: load and mount it, then `pay()` on the Pay button. */
type Driver = (step: ElementStep, view: CheckoutView) => Promise<{pay: () => Promise<void>}>;

const FAILED = 'The payment did not go through. Try again.';
const NOT_LOADED = 'The payment form did not load. Reload the page to try again.';

/** The site's accent colour, so the provider's form matches (falls back to the provider's own). */
const accent = () => getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || undefined;

const stripeDriver: Driver = async (step, view) => {
  const stripe: Stripe | null = await loadStripe(
    step.publishableKey,
    step.gatewayAccountId ? {stripeAccount: step.gatewayAccountId} : undefined
  );
  if (!stripe) throw new Error(NOT_LOADED);
  const color = accent();
  const elements: StripeElements = stripe.elements({
    clientSecret: step.clientSecret,
    appearance: color ? {variables: {colorPrimary: color}} : undefined,
  });
  const payment = elements.create('payment');
  await new Promise<void>(resolve => {
    payment.on('ready', () => resolve());
    payment.mount(view.mount);
  });
  return {
    async pay() {
      const confirm = step.clientSecret.startsWith('seti_')
        ? stripe.confirmSetup.bind(stripe)
        : stripe.confirmPayment.bind(stripe);
      // Success leaves the page for `returnUrl`; only a problem comes back here.
      const result = await confirm({elements, confirmParams: {return_url: step.returnUrl}});
      view.fail(result.error?.message ?? FAILED);
    },
  };
};

/**
 * The in-page forms, one per provider. The platform answers `element` only for a provider whose
 * form exists here; when another one gains an in-page form, its driver plugs in HERE (and its
 * hosts in `src/lib/checkout-hosts.ts`). A provider with no driver shows a clear message in the
 * dialog, never a blank form.
 */
const DRIVERS: Record<string, Driver> = {
  stripe: stripeDriver,
};

export const hasDriver = (gateway: string) => Object.hasOwn(DRIVERS, gateway);

/** Load and mount the form; throws an Error with a message for the buyer when it can't. */
export async function prepareInlineCheckout(step: ElementStep, view: CheckoutView) {
  if (!hasDriver(step.gateway)) throw new Error('This payment method cannot be shown here. Try again later.');
  try {
    return await DRIVERS[step.gateway]!(step, view);
  } catch {
    throw new Error(NOT_LOADED);
  }
}
