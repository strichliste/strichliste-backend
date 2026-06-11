// Disables a form's submit buttons on submit so a kiosk double-tap posts once.
// Document-level on purpose: per-form opt-in drifted and left money forms
// unguarded. Turbo reads the clicked submitter before this runs, so
// name/value submit buttons (custom-form direction) still arrive.
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    form.querySelectorAll(
        'button[type="submit"], input[type="submit"]',
    ).forEach((b) => {
        b.disabled = true;
        b.setAttribute('aria-busy', 'true');
    });
});

// Turbo snapshots the page before navigating; without this reset a
// back/forward restore would bring the buttons back disabled.
document.addEventListener('turbo:before-cache', () => {
    document.querySelectorAll('[aria-busy="true"]').forEach((b) => {
        b.disabled = false;
        b.removeAttribute('aria-busy');
    });
});
