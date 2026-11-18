/*
 * CampusBridge JavaScript.
 *
 * The site works WITHOUT JavaScript (every feature is a normal link or form).
 * This file only adds small conveniences ("progressive enhancement"):
 *   1. The menu button on small screens.
 *   2. Filters on the Explore page apply as soon as you change them.
 *   3. "Are you sure?" questions before deleting things.
 *   4. Moving keyboard focus to form error messages.
 *   5. Keeping the filter panel open on large screens.
 */
document.addEventListener('DOMContentLoaded', function () {
    // 1. Menu button
    const menuButton = document.querySelector('[data-menu-button]');
    const menu = document.querySelector('[data-menu]');
    if (menuButton && menu) {
        menuButton.addEventListener('click', function () {
            const isOpen = menu.classList.toggle('is-open');
            menuButton.setAttribute('aria-expanded', String(isOpen));
        });
        // Close the menu with the Escape key.
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && menu.classList.contains('is-open')) {
                menu.classList.remove('is-open');
                menuButton.setAttribute('aria-expanded', 'false');
                menuButton.focus();
            }
        });
    }

    // 2. Auto-submit filter forms when a value changes.
    document.querySelectorAll('form[data-auto-submit]').forEach(function (form) {
        form.addEventListener('change', function () {
            form.submit();
        });
        // The "Apply filters" button is not needed when JavaScript is on.
        form.querySelectorAll('[data-hide-with-js]').forEach(function (button) {
            button.hidden = true;
        });
    });

    // 3. Confirm before destructive actions.
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    // 4. Focus the first invalid field so keyboard and screen-reader users find the problem.
    const firstInvalid = document.querySelector('[aria-invalid="true"]');
    if (firstInvalid) {
        firstInvalid.focus();
    }

    // 5. On wide screens, always show the Explore filters.
    const filters = document.querySelector('[data-filters]');
    if (filters && window.matchMedia('(min-width: 901px)').matches) {
        filters.open = true;
    }
});
