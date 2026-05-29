import { Controller } from '@hotwired/stimulus';

/*
 * Copies server-rendered flash messages into the persistent ARIA live regions
 * declared in base.html.twig (#flash-announcer-status / #flash-announcer-alert).
 *
 * Those regions already exist in the DOM and are data-turbo-permanent, so
 * writing text into them — after this controller connects on a Turbo visit —
 * is observed as a live-region change and announced. A freshly inserted,
 * already-populated live region (the previous markup) is ignored by most
 * screen readers, which is the bug this fixes.
 */
export default class extends Controller {
  connect() {
    const regions = {
      status: document.getElementById('flash-announcer-status'),
      alert: document.getElementById('flash-announcer-alert'),
    };
    const buckets = { status: [], alert: [] };
    this.element.querySelectorAll('.flash').forEach((el) => {
      const level = el.dataset.flashLevel === 'alert' ? 'alert' : 'status';
      const text = el.textContent.trim();
      if (text) buckets[level].push(text);
    });

    // Defer one frame so the write lands after this element is connected,
    // guaranteeing the mutation (not the initial content) is what's announced.
    this.frame = requestAnimationFrame(() => {
      Object.keys(buckets).forEach((level) => {
        if (regions[level] && buckets[level].length) {
          regions[level].textContent = buckets[level].join('. ');
        }
      });
    });
  }

  disconnect() {
    if (this.frame) cancelAnimationFrame(this.frame);
    // Clear on navigation away so a back/forward restore re-announces as a
    // genuine empty -> text change rather than silently keeping stale text.
    ['flash-announcer-status', 'flash-announcer-alert'].forEach((id) => {
      const region = document.getElementById(id);
      if (region) region.textContent = '';
    });
  }
}
