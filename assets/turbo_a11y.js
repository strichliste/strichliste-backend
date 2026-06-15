// Turbo renders are silent for screen readers and drop focus to <body>.
// After a visit: focus the first invalid field, otherwise announce the new
// page title (flashes are announced by the flash controller instead).
// Initial full page loads are skipped — the browser announces those itself.
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

    // wait a frame: the outgoing page's flash disconnect() runs after
    // turbo:load and would wipe a synchronous write
    requestAnimationFrame(() => {
        if (!document.querySelector('.flashes:not([hidden]) .flash')) {
            const region = document.getElementById('flash-announcer-status');
            if (region) region.textContent = document.title;
        }
    });
});
