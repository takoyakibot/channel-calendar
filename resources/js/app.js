import './bootstrap';

/**
 * Vanilla JS replacements for the interactive behaviors that Breeze's
 * default templates implement with Alpine.js. Alpine.js is intentionally
 * not used in this project.
 */

// Dropdown menus (e.g. the user menu in the navigation bar).
document.addEventListener('click', (event) => {
    document.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
        const trigger = dropdown.querySelector('[data-dropdown-trigger]');
        const content = dropdown.querySelector('[data-dropdown-content]');

        if (!trigger || !content) {
            return;
        }

        if (trigger.contains(event.target)) {
            content.classList.toggle('hidden');
        } else {
            content.classList.add('hidden');
        }
    });
});

// Mobile navigation hamburger toggle.
document.querySelectorAll('[data-mobile-nav-toggle]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        const nav = toggle.closest('[data-mobile-nav]');
        if (!nav) {
            return;
        }

        const menu = nav.querySelector('[data-mobile-nav-menu]');
        const iconOpen = nav.querySelector('[data-mobile-nav-icon-open]');
        const iconClosed = nav.querySelector('[data-mobile-nav-icon-closed]');

        menu?.classList.toggle('hidden');
        iconOpen?.classList.toggle('hidden');
        iconOpen?.classList.toggle('inline-flex');
        iconClosed?.classList.toggle('hidden');
        iconClosed?.classList.toggle('inline-flex');
    });
});

// Modals (e.g. the "confirm account deletion" dialog).
function openModal(name) {
    const modal = document.querySelector(`[data-modal][data-modal-name="${name}"]`);
    if (!modal) {
        return;
    }

    modal.classList.remove('hidden');
    document.body.classList.add('overflow-y-hidden');

    const focusable = modal.querySelector('input, textarea, select, button, a[href]');
    focusable?.focus();
}

function closeModal(name) {
    document.querySelectorAll('[data-modal]').forEach((modal) => {
        if (!name || modal.dataset.modalName === name) {
            modal.classList.add('hidden');
        }
    });

    document.body.classList.remove('overflow-y-hidden');
}

window.addEventListener('open-modal', (event) => openModal(event.detail));
window.addEventListener('close-modal', (event) => closeModal(event.detail));

document.addEventListener('click', (event) => {
    const backdrop = event.target.closest('[data-modal-backdrop]');
    if (backdrop) {
        closeModal(backdrop.closest('[data-modal]')?.dataset.modalName);
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeModal();
    }
});

// Transient "Saved." style messages that should disappear after a delay.
document.querySelectorAll('[data-auto-hide]').forEach((el) => {
    const delay = parseInt(el.dataset.autoHide, 10) || 2000;
    setTimeout(() => el.classList.add('hidden'), delay);
});
