import { Controller } from '@hotwired/stimulus';

// Kiosk inactivity redirect. timeoutValue <= 0 disables it.
export default class extends Controller {
    static values = {
        timeout: Number,
        redirect: { type: String, default: '/' },
    };

    connect() {
        if (!this.timeoutValue || this.timeoutValue <= 0) return;
        this.events = [
            'scroll',
            'click',
            'touchstart',
            'keydown',
            'input',
            'change',
        ];
        this.handler = () => this.reset();
        this.events.forEach((e) =>
            document.addEventListener(e, this.handler, {
                passive: true,
                capture: true,
            }),
        );
        this.reset();
    }

    disconnect() {
        this.clear();
        if (!this.events || !this.handler) return;
        this.events.forEach((e) =>
            document.removeEventListener(e, this.handler, { capture: true }),
        );
    }

    reset() {
        this.clear();
        this.timer = setTimeout(() => this.redirect(), this.timeoutValue);
    }

    clear() {
        if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
        }
    }

    // Don't pull the page away from someone mid-form (focused field or unsaved input).
    isMidForm() {
        const active = document.activeElement;
        if (
            active &&
            active.matches('input, select, textarea, [contenteditable="true"]')
        ) {
            return true;
        }
        return Array.from(
            document.querySelectorAll('input:not([type="hidden"]), textarea'),
        ).some((el) => el.value !== el.defaultValue);
    }

    redirect() {
        if (this.isMidForm()) {
            this.reset();
            return;
        }
        // Already on the target? Re-visiting would reload-loop the idle kiosk.
        const target = new URL(this.redirectValue, window.location.origin);
        if (window.location.pathname === target.pathname) {
            this.reset();
            return;
        }
        if (typeof window.Turbo?.visit === 'function') {
            window.Turbo.visit(this.redirectValue);
        } else {
            window.location.href = this.redirectValue;
        }
    }
}
