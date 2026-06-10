import { Controller } from '@hotwired/stimulus';

/*
 * Same-page focus jump for the skip link. Without this, Turbo Drive
 * intercepts the anchor activation as a full visit: the body is swapped,
 * every controller reconnects, and in-progress form state is discarded —
 * for a link whose entire job is to move focus a few elements down.
 * The href stays in place for the no-JS path.
 */
export default class extends Controller {
  jump(event) {
    const target = document.getElementById('main-content');
    if (!target) return;
    event.preventDefault();
    target.focus();
  }
}
