import { Controller } from '@hotwired/stimulus';

/*
 * Redirects to a configured URL after `timeoutValue` ms of inactivity.
 * Activity = scroll / click / touchstart / keydown anywhere in document.
 * timeoutValue <= 0 → disabled (no listeners attached, no timer).
 */
export default class extends Controller {
  static values = { timeout: Number, redirect: { type: String, default: '/' } };

  connect() {
    if (!this.timeoutValue || this.timeoutValue <= 0) return;
    this.events = ['scroll', 'click', 'touchstart', 'keydown'];
    this.handler = () => this.reset();
    this.events.forEach((e) =>
      document.addEventListener(e, this.handler, { passive: true, capture: true })
    );
    this.reset();
  }

  disconnect() {
    this.clear();
    if (!this.events || !this.handler) return;
    this.events.forEach((e) =>
      document.removeEventListener(e, this.handler, { capture: true })
    );
  }

  reset() {
    this.clear();
    this.timer = setTimeout(() => {
      // Prefer Turbo.visit when available so we stay inside the Turbo session;
      // fall back to a full navigation otherwise.
      if (typeof window.Turbo?.visit === 'function') {
        window.Turbo.visit(this.redirectValue);
      } else {
        window.location.href = this.redirectValue;
      }
    }, this.timeoutValue);
  }

  clear() {
    if (this.timer) {
      clearTimeout(this.timer);
      this.timer = null;
    }
  }
}
