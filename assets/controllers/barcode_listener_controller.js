import { Controller } from '@hotwired/stimulus';

/*
 * Document-level barcode scanner for the user detail page.
 *
 * A USB-HID barcode scanner emits keystrokes as fast as the kernel will let it
 * (typically <30ms between keys). We exploit that to distinguish a barcode
 * burst from a human typing into a field:
 *
 *   - Listen on document for `keydown`.
 *   - Ignore events whose `target` is an editable element (input, textarea,
 *     contenteditable) — the operator is typing on purpose, not scanning.
 *   - Accumulate printable characters (length 1) in a buffer.
 *   - If more than `gap` ms passes between two keys, drop the buffer (a human
 *     pressing single keys won't hit this; a HID scanner won't pause).
 *   - When `Enter` arrives and the buffer is at least `minLength` characters
 *     long, POST it to the buy endpoint as `barcode=...`.
 *
 * The endpoint resolves the barcode → article → debit transaction; on success
 * the page reloads with a success flash + ka-ching cue (already wired).
 *
 * Without JS the page works fine — there's just no barcode scanner. The BUY
 * ARTICLE tab's article pills still work via direct click.
 */
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
    this.handler = (e) => this.onKey(e);
    document.addEventListener('keydown', this.handler, { capture: true });
  }

  disconnect() {
    document.removeEventListener('keydown', this.handler, { capture: true });
  }

  onKey(e) {
    // Don't intercept typing in real form fields, [contenteditable] regions, or
    // when modifier keys are held — those are not scanner output.
    const t = e.target;
    if (t && (t.matches?.('input, textarea, select, [contenteditable="true"]'))) {
      return;
    }
    if (e.metaKey || e.ctrlKey || e.altKey) {
      return;
    }

    const now = performance.now();
    const gap = now - this.lastKeyAt;
    this.lastKeyAt = now;

    if (e.key === 'Enter') {
      // Only commit if the buffer looks like a scanner burst:
      //   - long enough to be a real barcode (>= minLength)
      //   - delivered in a tight time window (avg gap small).
      // A user pressing a single Enter on its own will have gap > 200ms and
      // empty buffer, so this is a no-op.
      const code = this.buffer;
      this.buffer = '';
      if (code.length >= this.minLengthValue) {
        this.submit(code);
        e.preventDefault();
      }
      return;
    }

    // If too much time passed since the previous key, treat this as the start
    // of a new burst (probably human typing rather than scanning).
    if (gap > this.gapValue) {
      this.buffer = '';
    }

    // Only printable single-character keys join the buffer.
    if (e.key && e.key.length === 1) {
      this.buffer += e.key;
    }
  }

  submit(barcode) {
    // Build and submit a hidden form so we go through Symfony's normal
    // CSRF/redirect/flash flow (no fetch, no JS-side error handling).
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
