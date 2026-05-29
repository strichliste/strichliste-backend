import { Controller } from '@hotwired/stimulus';

/*
 * Auto-applies dark or light theme based on the OS preference.
 * No persistence, no toggle UI — system setting is the source of truth.
 */
export default class extends Controller {
  connect() {
    this.media = window.matchMedia('(prefers-color-scheme: dark)');
    this.apply(this.media.matches);
    this.handler = (e) => this.apply(e.matches);
    if (this.media.addEventListener) {
      this.media.addEventListener('change', this.handler);
    } else if (this.media.addListener) {
      this.media.addListener(this.handler);
    }
  }

  disconnect() {
    if (!this.media || !this.handler) return;
    if (this.media.removeEventListener) {
      this.media.removeEventListener('change', this.handler);
    } else if (this.media.removeListener) {
      this.media.removeListener(this.handler);
    }
  }

  apply(isDark) {
    document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
  }
}
