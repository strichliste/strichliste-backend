import { Controller } from '@hotwired/stimulus';

// Ka-ching cue. Mounted by base.html.twig when the flash bag carries
// `transaction_success`.
export default class extends Controller {
  static values = { asset: String };

  connect() {
    try {
      if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
      }
      const url = this.assetValue;
      if (!url) return;
      const audio = new Audio(url);
      audio.volume = 0.6;
      const p = audio.play();
      if (p && typeof p.catch === 'function') {
        p.catch(() => { /* autoplay refused — silent */ });
      }
      this.element.dataset.played = '1';
    } catch (e) {
      /* ignore */
    }
    // self-remove so a Turbo re-render doesn't refire
    this.element.remove();
  }
}
