import { Controller } from '@hotwired/stimulus';

// Copies flash text into the data-turbo-permanent live regions in
// base.html.twig. Screen readers ignore a freshly inserted, already-populated
// live region — only a write into an existing one gets announced.
export default class extends Controller {
  connect() {
    const buckets = { status: [], alert: [] };
    this.element.querySelectorAll('.flash').forEach((el) => {
      const level = el.dataset.flashLevel === 'alert' ? 'alert' : 'status';
      const text = el.textContent.trim();
      if (text) buckets[level].push(text);
    });

    // wait a frame, and resolve the regions inside the callback: connect()
    // fires mid permanent-element swap, where the announcer regions are
    // placeholder-replaced and getElementById returns null
    this.frame = requestAnimationFrame(() => {
      const regions = {
        status: document.getElementById('flash-announcer-status'),
        alert: document.getElementById('flash-announcer-alert'),
      };
      Object.keys(buckets).forEach((level) => {
        if (regions[level] && buckets[level].length) {
          regions[level].textContent = buckets[level].join('. ');
        }
      });
    });
  }

  disconnect() {
    if (this.frame) cancelAnimationFrame(this.frame);
    // clear so a back/forward restore re-announces instead of keeping stale text
    ['flash-announcer-status', 'flash-announcer-alert'].forEach((id) => {
      const region = document.getElementById(id);
      if (region) region.textContent = '';
    });
  }
}
