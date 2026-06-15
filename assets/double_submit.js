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

const reEnable = () => {
    document.querySelectorAll('[aria-busy="true"]').forEach((b) => {
        b.disabled = false;
        b.removeAttribute('aria-busy');
    });
};

// Turbo snapshots the page before navigating; without this reset a
// back/forward restore would bring the buttons back disabled.
document.addEventListener('turbo:before-cache', reEnable);

// On the happy path (PRG redirect) the navigation replaces the page anyway,
// but a failed submit — validation 422, or a network drop on an offline
// kiosk — would otherwise leave the buttons disabled forever. Re-enable
// once the submit settles, and on a fetch-layer error.
document.addEventListener('turbo:submit-end', reEnable);
document.addEventListener('turbo:fetch-request-error', reEnable);
