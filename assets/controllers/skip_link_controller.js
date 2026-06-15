import { Controller } from '@hotwired/stimulus';

// Turbo treats the skip link's anchor as a full visit (body swap, form
// state lost), so jump focus in place. The href stays for the no-JS path.
export default class extends Controller {
    jump(event) {
        const target = document.getElementById('main-content');
        if (!target) return;
        event.preventDefault();
        target.focus();
    }
}
