/*
 * Turbo renders are visually instant but silent for assistive tech, and they
 * drop focus to <body>. After every Turbo visit:
 *  - validation re-renders: move focus to the first invalid field (Symfony's
 *    form theme wires aria-invalid + aria-describedby, so landing there reads
 *    the error)
 *  - plain navigations: announce the new page title through the polite live
 *    region — flash messages, when present, are announced by the flash
 *    controller instead
 * The initial full page load is excluded: the browser announces it natively.
 */
let turboVisited = false;
document.addEventListener('turbo:visit', () => {
  turboVisited = true;
});

document.addEventListener('turbo:load', () => {
  if (!turboVisited) return;

  const invalid = document.querySelector('[aria-invalid="true"]');
  if (invalid) {
    invalid.focus();
    return;
  }

  // One frame later: the outgoing page's flash controller disconnect()
  // (which clears the regions) is processed after turbo:load — writing
  // synchronously here would be wiped by it.
  requestAnimationFrame(() => {
    // :not([hidden]) — a hidden flash container (none today, but cheap to
    // guard) must not suppress the title announcement.
    if (!document.querySelector('.flashes:not([hidden]) .flash')) {
      const region = document.getElementById('flash-announcer-status');
      if (region) region.textContent = document.title;
    }
  });
});
