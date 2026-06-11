import { Controller } from '@hotwired/stimulus';

// Document-level barcode scanner for the user detail page. A USB-HID scanner
// types in a fast burst (<30ms between keys) ending with Enter; buffered keys
// are dropped once more than `gap` ms passes, which is what separates a scan
// from a human typing. On Enter with enough buffered, POST to the buy endpoint.
export default class extends Controller {
  static values = {
    action: String,                // POST URL for `transactions_buy`
    token: String,                 // CSRF token, scoped to this user
    gap: { type: Number, default: 200 },
    minLength: { type: Number, default: 3 },
  };

  connect() {
    this.buffer = '';
    this.lastKeyAt = 0;
    this.submitted = false;
    this.handler = (e) => this.onKey(e);
    document.addEventListener('keydown', this.handler, { capture: true });
  }

  disconnect() {
    document.removeEventListener('keydown', this.handler, { capture: true });
  }

  onKey(e) {
    // ignore typing in fields and on interactive elements (Enter on a focused
    // button must press the button, not commit a barcode); autorepeat fires
    // every ~30ms — inside the scanner gap — so it has to be excluded too
    const t = e.target;
    if (t && (t.matches?.('input, textarea, select, button, a, summary, [role="button"], [contenteditable="true"]'))) {
      return;
    }
    if (e.metaKey || e.ctrlKey || e.altKey || e.repeat || !e.isTrusted) {
      return;
    }

    const now = performance.now();
    const gap = now - this.lastKeyAt;
    this.lastKeyAt = now;

    // too slow to be a scanner burst — drop it; this also keeps a late
    // Enter from committing a stale buffer
    if (gap > this.gapValue) {
      this.buffer = '';
    }

    if (e.key === 'Enter') {
      const code = this.buffer;
      this.buffer = '';
      if (code.length >= this.minLengthValue && !this.submitted) {
        // a double-trigger scan before the page reloads must buy exactly once
        this.submitted = true;
        this.submit(code);
        e.preventDefault();
      }
      return;
    }

    // barcode alphabet only — random punctuation must not accumulate
    if (e.key && /^[0-9A-Za-z._-]$/.test(e.key)) {
      this.buffer += e.key;
    }
  }

  submit(barcode) {
    // hidden form keeps this on Symfony's normal CSRF/redirect/flash path
    const form = document.createElement('form');
    form.method = 'post';
    form.action = this.actionValue;
    form.style.display = 'none';

    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = this.tokenValue;
    form.appendChild(csrf);

    const bc = document.createElement('input');
    bc.type = 'hidden';
    bc.name = 'barcode';
    bc.value = barcode;
    form.appendChild(bc);

    document.body.appendChild(form);
    form.submit();
  }
}
