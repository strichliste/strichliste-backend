import { Controller } from '@hotwired/stimulus';

/*
 * Plays the ka-ching cue when this element is connected.
 * Mounted by base.html.twig only when the flash bag carries `transaction_success`.
 * Silently no-ops under prefers-reduced-motion or when autoplay is refused.
 */
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
    // Self-remove so a Turbo Drive re-render doesn't refire.
    this.element.remove();
  }
}
