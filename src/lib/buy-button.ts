import {
  ALREADY_PAID_CODE,
  CheckoutError,
  type CheckoutResult,
  CheckoutSession,
  type CheckoutState,
  type CheckoutTransport,
  NOT_LOADED,
  TRIAL_USED_MESSAGE,
} from './checkout-session';

/**
 * BROWSER: the Buy buttons and price lists of the page shown (docs/agent/payments.md). Everything
 * goes to THIS site's own address (the platform knows the site by it; no key, no secret):
 *
 *   GET  /api/public/site/v1/payments                    the catalogue {mode, ready, prices}
 *   GET  /api/public/site/v1/payments/gateways?price=    the ways to pay one price
 *   POST /api/public/site/v1/payments/checkout           start one → {id, mode, embedded}
 *   GET  /api/public/site/v1/payments/checkout/:id       its status {status}
 *
 * A Buy click opens the button's dialog and runs a `CheckoutSession`: the ways to pay (a radio
 * list when there are several), then the checkout, opened without leaving the page: the
 * provider's form in the dialog (`src/lib/inline-checkout.ts`), its popup, or its page in a
 * payment window. Paid → `successPath`. In the editor's Preview (the dev server) there is no
 * platform on this address, so the button says payments work on the published site.
 */
const API = '/api/public/site/v1/payments';
const PREVIEW_NOTE = 'Payments work on your published site.';

type Envelope = {data?: Record<string, unknown>; errors?: Array<{code?: string; message?: string}>} & Record<string, unknown>;

async function call(path: string, init?: RequestInit): Promise<{res: Response; data: Record<string, any>; body: Envelope}> {
  const res = await fetch(`${API}${path}`, {
    ...init,
    headers: {accept: 'application/json', ...(init?.body ? {'content-type': 'application/json'} : {})},
    signal: AbortSignal.timeout(10000),
  });
  const body = ((await res.json().catch(() => null)) as Envelope | null) ?? {};
  return {res, body, data: (body.data ?? body) as Record<string, any>};
}

const refused = (res: Response, body: Envelope) => {
  const error = body.errors?.[0];
  return new CheckoutError(
    res.status === 404 ? PREVIEW_NOTE : (error?.message ?? 'Checkout did not open. Try again.'),
    error?.code ?? `http_${res.status}`,
    res.status
  );
};

/** The static site's way to the platform: its public endpoints, on this site's address. */
export const publicCheckoutTransport: CheckoutTransport = {
  async listGateways(price) {
    const {res, body, data} = await call(`/gateways?price=${encodeURIComponent(price)}`);
    if (!res.ok) throw refused(res, body);
    return Array.isArray(data.gateways) ? data.gateways.filter((g: {slug?: unknown}) => typeof g?.slug === 'string') : [];
  },
  async createCheckout(request) {
    const {res, body, data} = await call('/checkout', {method: 'POST', body: JSON.stringify(request)});
    if (data.paid === true && typeof data.id === 'string') {
      throw new CheckoutError('This has already been paid.', ALREADY_PAID_CODE, res.status, data.id);
    }
    if (!res.ok || typeof data.id !== 'string' || !data.embedded) throw refused(res, body);
    return {id: data.id, mode: data.mode, embedded: data.embedded} as CheckoutResult;
  },
  async getStatus(id) {
    const {res, data} = await call(`/checkout/${encodeURIComponent(id)}`);
    return res.ok && typeof data.status === 'string' ? data.status : null;
  },
};

/* -------------------------------- catalogue ------------------------------- */

export interface Price {
  key: string;
  name: string;
  description: string | null;
  amountCents: number;
  currency: string;
  interval: 'one_time' | 'month' | 'year';
  intervalCount: number;
  trialDays: number;
}
export interface Catalogue {
  mode: 'test' | 'live';
  /** The site takes payments now (the platform's `checkoutOpen`, else `ready`). */
  checkoutOpen: boolean;
  prices: Price[];
}

let catalogue: Promise<Catalogue | null> | null = null;

/** The catalogue, once per page view. Null: the platform did not answer (the Preview, offline). */
export function loadCatalogue(): Promise<Catalogue | null> {
  catalogue ??= call('')
    .then(({res, data}) =>
      res.ok
        ? {
            mode: data.mode,
            checkoutOpen: typeof data.checkoutOpen === 'boolean' ? data.checkoutOpen : !!data.ready,
            prices: Array.isArray(data.prices) ? (data.prices as Price[]) : [],
          }
        : null
    )
    .catch(() => null)
    .finally(() => setTimeout(() => (catalogue = null), 30_000));
  return catalogue;
}

/** "$19.99" from minor units; a currency without cents (JPY) is not divided. */
export function formatMoney(amountCents: number, currency: string): string {
  try {
    const format = new Intl.NumberFormat(document.documentElement.lang || 'en', {style: 'currency', currency});
    return format.format(amountCents / 10 ** (format.resolvedOptions().maximumFractionDigits ?? 2));
  } catch {
    return `${(amountCents / 100).toFixed(2)} ${currency}`;
  }
}

const every = (p: Price) => {
  if (p.interval === 'one_time') return '';
  const n = p.intervalCount || 1;
  return n === 1 ? ` / ${p.interval}` : ` every ${n} ${p.interval}s`;
};

/**
 * `<PriceList>`: one card per price of the catalogue (or the keys it names), each with its own
 * Buy / Subscribe button, sharing the list's dialog. "Coming soon" while the site can't take
 * payments yet.
 */
export async function fillPriceLists(): Promise<void> {
  const lists = document.querySelectorAll<HTMLElement>('[data-price-list]');
  if (!lists.length) return;
  const found = await loadCatalogue();
  for (const list of lists) {
    if (list.dataset.filled) continue;
    list.dataset.filled = '1';
    const status = list.querySelector<HTMLElement>('[data-price-list-status]');
    if (!found) {
      if (status) status.textContent = list.dataset.preview === '1' ? PREVIEW_NOTE : 'Prices did not load. Reload the page to try again.';
      continue;
    }
    const keys = (list.dataset.keys ?? '').split(',').filter(Boolean);
    const prices = keys.length ? keys.map(k => found.prices.find(p => p.key === k)).filter((p): p is Price => !!p) : found.prices;
    const ul = list.querySelector<HTMLElement>('[data-price-list-items]')!;
    ul.replaceChildren(
      ...prices.map(p => {
        const li = document.createElement('li');
        li.className = 'price-card';
        const name = document.createElement('h3');
        name.className = 'price-name';
        name.textContent = p.name;
        li.append(name);
        if (p.description) {
          const d = document.createElement('p');
          d.className = 'price-description';
          d.textContent = p.description;
          li.append(d);
        }
        const amount = document.createElement('p');
        amount.className = 'price-amount';
        amount.textContent = formatMoney(p.amountCents, p.currency);
        const per = document.createElement('span');
        per.className = 'price-every';
        per.textContent = every(p);
        amount.append(per);
        li.append(amount);
        if (p.trialDays > 0) {
          const trial = document.createElement('p');
          trial.className = 'price-trial';
          trial.textContent = `${p.trialDays}-day free trial`;
          li.append(trial);
        }
        if (found.checkoutOpen) {
          const id = `${list.id}-${p.key}`;
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'button';
          button.textContent = p.interval === 'one_time' ? 'Buy now' : 'Subscribe';
          Object.assign(button.dataset, {
            buyPrice: p.key,
            quantity: '1',
            successPath: list.dataset.successPath || '/',
            cancelPath: window.location.pathname,
            pendingLabel: 'Opening checkout…',
            dialog: `${list.id}-dialog`,
          });
          button.setAttribute('aria-describedby', `${id}-status`);
          const note = document.createElement('p');
          note.className = 'buy-status';
          note.id = `${id}-status`;
          note.setAttribute('role', 'status');
          note.setAttribute('aria-live', 'polite');
          li.append(button, note);
        } else {
          const soon = document.createElement('p');
          soon.className = 'price-soon';
          soon.textContent = 'Coming soon';
          li.append(soon);
        }
        return li;
      })
    );
    if (status) status.textContent = prices.length ? '' : 'Nothing is for sale here yet.';
  }
  bindBuyButtons();
}

/* ------------------------------- Buy buttons ------------------------------ */

export function bindBuyButtons(): void {
  for (const button of document.querySelectorAll<HTMLButtonElement>('button[data-buy-price]')) {
    if (button.dataset.bound) continue;
    button.dataset.bound = '1';
    const status = document.getElementById(button.getAttribute('aria-describedby') ?? '');
    const say = (message: string, tone: 'note' | 'error') => {
      if (!status) return;
      status.dataset.tone = tone;
      status.textContent = message;
    };

    // Not taking payments yet, or the price is gone: say so instead of a button that fails.
    if (button.dataset.preview !== '1')
      void loadCatalogue().then(found => {
        if (!found) return;
        const known = found.prices.some(p => p.key === button.dataset.buyPrice);
        if (found.checkoutOpen && known) return;
        button.disabled = true;
        say(known ? 'Coming soon' : 'No longer for sale', 'note');
      });

    button.addEventListener('click', () => {
      if (button.disabled) return;
      if (status) status.textContent = '';
      if (button.dataset.preview === '1') return say(PREVIEW_NOTE, 'note');
      const dialog = document.getElementById(button.dataset.dialog ?? '') as HTMLDialogElement | null;
      if (!dialog) return say('Checkout did not open. Reload the page to try again.', 'error');
      new CheckoutDialog(dialog, button).open();
    });
  }
}

/**
 * The checkout `<dialog>` (src/components/CheckoutDialog.astro), driven by one
 * `CheckoutSession`. Cancel / Escape closes it and the visitor stays here; nothing is charged
 * until they pay.
 */
class CheckoutDialog {
  private mount: HTMLElement;
  private status: HTMLElement;
  private pay: HTMLButtonElement;
  private reopen: HTMLButtonElement | null;
  private close: HTMLButtonElement;
  private testNote: HTMLElement | null;
  private payLabel: string;
  private session: CheckoutSession;
  private shown: 'none' | 'ways' | 'form' = 'none';
  private formPay: (() => Promise<void>) | null = null;
  private stop: (() => void) | null = null;

  constructor(
    private dialog: HTMLDialogElement,
    button: HTMLButtonElement
  ) {
    this.mount = dialog.querySelector<HTMLElement>('[data-checkout-mount]')!;
    this.status = dialog.querySelector<HTMLElement>('[data-checkout-status]')!;
    this.pay = dialog.querySelector<HTMLButtonElement>('[data-checkout-pay]')!;
    this.reopen = dialog.querySelector<HTMLButtonElement>('[data-checkout-reopen]');
    this.close = dialog.querySelector<HTMLButtonElement>('[data-checkout-close]')!;
    this.testNote = dialog.querySelector<HTMLElement>('[data-checkout-test]');
    this.payLabel = this.pay.dataset.label ?? 'Pay now';
    const b = button.dataset;
    this.session = new CheckoutSession(publicCheckoutTransport, {
      price: b.buyPrice ?? '',
      quantity: Number(b.quantity || 1),
      successPath: b.successPath || '/',
      cancelPath: b.cancelPath || window.location.pathname,
    });
  }

  async open() {
    this.mount.replaceChildren();
    if (this.testNote) this.testNote.hidden = true;
    if (this.reopen) {
      this.reopen.hidden = true;
      this.reopen.onclick = () => void this.session.open().catch(() => undefined);
    }
    this.close.onclick = () => this.dialog.close();
    this.dialog.onclose = () => {
      this.session.cancel();
      this.stop?.();
    };
    this.pay.onclick = () => {
      if (this.pay.disabled) return;
      if (this.formPay) return void this.formPay();
      void this.session.continue().catch(() => undefined);
    };
    this.stop = this.session.subscribe(state => this.render(state));
    this.dialog.showModal();
    try {
      const ways = await this.session.load();
      // One way to pay (or none listed: the platform picks): no question, straight to it.
      if (ways.length <= 1) await this.session.continue();
    } catch {
      // The state carries the message.
    }
  }

  private say(message: string, tone?: 'error') {
    this.status.textContent = message;
    if (tone) this.status.dataset.tone = tone;
    else delete this.status.dataset.tone;
  }

  private button(label: string, {busy = false, disabled = false} = {}) {
    this.pay.textContent = label;
    this.pay.disabled = busy || disabled;
    if (busy) this.pay.setAttribute('aria-busy', 'true');
    else this.pay.removeAttribute('aria-busy');
  }

  private render(state: CheckoutState) {
    const step = state.result?.embedded ?? null;
    if (this.testNote) this.testNote.hidden = state.result?.mode !== 'test';
    if (this.reopen) this.reopen.hidden = !(step?.kind === 'window' && state.status === 'redirecting');

    if (state.status === 'loading' || state.status === 'idle') {
      this.say('Loading the ways to pay…');
      return this.button(this.payLabel, {disabled: true});
    }
    if (state.status === 'submitting') {
      this.say('');
      return this.button('Opening checkout…', {busy: true});
    }
    if (!step && state.gateways.length > 1 && this.shown !== 'ways') this.showWays(state);
    if (step?.kind === 'element') return void this.showForm(state.result!);
    if (step && state.status === 'redirecting') {
      if (step.kind === 'window') {
        this.say('Finish paying in the payment window. This page goes on by itself once your payment is through.');
        return this.button('Waiting for your payment…', {busy: true});
      }
      this.say('');
      return this.button('Finishing in the payment window…', {busy: true});
    }
    if (state.status === 'error') {
      this.say(state.error?.message ?? 'Checkout did not open. Try again.', state.error?.status === 404 ? undefined : 'error');
      return this.button(step ? this.payLabel : 'Try again', {disabled: state.error?.status === 404});
    }
    // ready
    if (state.trialUsed && !step) {
      this.say(TRIAL_USED_MESSAGE);
      return this.button('Continue without the trial');
    }
    if (!step) {
      this.say('');
      return this.button('Continue', {disabled: state.gateways.length > 1 && !state.selectedGateway});
    }
    this.say(
      state.closed === 'failed'
        ? 'The payment did not go through. Nothing was charged. Try again.'
        : state.closed
          ? 'You closed the payment window. Nothing was charged.'
          : '',
      state.closed === 'failed' ? 'error' : undefined
    );
    this.button(this.payLabel);
  }

  /** Several ways to pay: radio buttons; the choice goes to the session. */
  private showWays(state: CheckoutState) {
    this.shown = 'ways';
    const fieldset = document.createElement('fieldset');
    fieldset.className = 'checkout-ways';
    const legend = document.createElement('legend');
    legend.textContent = 'How do you want to pay?';
    fieldset.append(legend);
    for (const g of state.gateways) {
      const label = document.createElement('label');
      label.className = 'checkout-way';
      const input = document.createElement('input');
      input.type = 'radio';
      input.name = `${this.dialog.id}-way`;
      input.value = g.slug;
      input.checked = g.slug === state.selectedGateway;
      input.addEventListener('change', () => this.session.selectGateway(g.slug));
      const text = document.createElement('span');
      text.textContent = g.name;
      label.append(input, text);
      fieldset.append(label);
    }
    this.mount.replaceChildren(fieldset);
    if (!state.selectedGateway && state.gateways[0]) this.session.selectGateway(state.gateways[0].slug);
    fieldset.querySelector<HTMLInputElement>('input')?.focus();
  }

  /** The provider's form in the dialog (element): mounted once, then Pay confirms it. */
  private async showForm(result: CheckoutResult) {
    if (this.shown === 'form') return;
    this.shown = 'form';
    this.mount.replaceChildren();
    this.say('Loading the payment form…');
    this.button(this.payLabel, {disabled: true});
    const step = result.embedded;
    if (step.kind !== 'element') return;
    try {
      const {prepareInlineCheckout} = await import('./inline-checkout');
      const form = await prepareInlineCheckout(step, {
        mount: this.mount,
        fail: message => {
          this.button(this.payLabel);
          this.say(message, 'error');
        },
      });
      this.formPay = async () => {
        this.say('');
        this.button('Paying…', {busy: true});
        try {
          await form.pay();
        } catch {
          this.button(this.payLabel);
          this.say('The payment did not go through. Try again.', 'error');
        }
      };
      this.say('');
      this.button(this.payLabel);
      this.pay.focus();
    } catch (err) {
      this.say(err instanceof Error && err.message ? err.message : NOT_LOADED, 'error');
    }
  }
}
