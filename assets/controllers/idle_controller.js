import { Controller } from '@hotwired/stimulus';

/*
 * Kiosk inactivity redirect with WCAG 2.2.1 guardrails:
 *  - typing and choosing count as activity (input/change are reset events —
 *    picking in a native <select> previously did not extend the session)
 *  - never fires while someone is mid-form (focused field or unsaved input):
 *    screen-reader users reading a page generate no DOM events at all
 *  - before redirecting, announces a warning through the assertive live
 *    region and waits a grace period; any activity cancels. The warning is
 *    deliberately screen-reader-only (operator preference): sighted kiosk
 *    users just see the start screen and tap again, while a silent redirect
 *    mid-listen is disorienting for SR users.
 * timeoutValue <= 0 → disabled.
 */
export default class extends Controller {
  static values = {
    timeout: Number,
    redirect: { type: String, default: '/' },
    grace: { type: Number, default: 20000 },
    warning: { type: String, default: '' },
  };

  connect() {
    if (!this.timeoutValue || this.timeoutValue <= 0) return;
    this.events = ['scroll', 'click', 'touchstart', 'keydown', 'input', 'change'];
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
    this.timer = setTimeout(() => this.warn(), this.timeoutValue);
  }

  clear() {
    if (this.timer) {
      clearTimeout(this.timer);
      this.timer = null;
    }
    if (this.warned) {
      this.warned = false;
      const region = document.getElementById('flash-announcer-alert');
      if (region) region.textContent = '';
    }
  }

  warn() {
    // Mid-form? Check again after a full timeout period instead of nagging.
    if (this.isMidForm()) {
      this.reset();
      return;
    }
    this.warned = true;
    const region = document.getElementById('flash-announcer-alert');
    if (region && this.warningValue) region.textContent = this.warningValue;
    this.timer = setTimeout(() => this.redirect(), this.graceValue);
  }

  isMidForm() {
    const active = document.activeElement;
    if (active && active.matches('input, select, textarea, [contenteditable="true"]')) {
      return true;
    }
    return Array.from(
      document.querySelectorAll('input:not([type="hidden"]), textarea')
    ).some((el) => el.value !== el.defaultValue);
  }

  redirect() {
    // Already on the idle target (or a page it redirects to)? Re-visiting
    // would loop the kiosk through an endless reload cycle while idle.
    const target = new URL(this.redirectValue, window.location.origin);
    const here = window.location.pathname;
    if (here === target.pathname || (target.pathname === '/' && here === '/user/active')) {
      this.clear();
      this.reset();
      return;
    }
    // Prefer Turbo.visit when available so we stay inside the Turbo session;
    // fall back to a full navigation otherwise.
    if (typeof window.Turbo?.visit === 'function') {
      window.Turbo.visit(this.redirectValue);
    } else {
      window.location.href = this.redirectValue;
    }
  }
}
