/** Small progressive enhancements used by the administrator pages. */

function ready(callback) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
        callback();
    }
}

ready(() => {
    initViewToggles();
    initAutoSubmit();
    initConfirmForms();
    initPhotoDialog();
    initCalendarPicker();
});

function initCalendarPicker() {
    const picker = document.querySelector('[data-calendar-picker]');
    const fromInput = document.querySelector('[data-calendar-from]');
    const toInput = document.querySelector('[data-calendar-to]');
    const label = document.querySelector('[data-calendar-label]');

    if (!picker || !fromInput || !toInput) return;

    const buttons = Array.from(picker.querySelectorAll('[data-calendar-date]'));
    let selectingEnd = false;

    const formatDate = (value) => new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${value}T00:00:00Z`));
    const paint = () => {
        buttons.forEach((button) => {
            const value = button.dataset.calendarDate;
            button.classList.toggle('is-selected', value === fromInput.value || value === toInput.value);
            button.classList.toggle('is-in-range', value > fromInput.value && value < toInput.value);
        });
        if (label) label.textContent = fromInput.value === toInput.value ? formatDate(fromInput.value) : `${formatDate(fromInput.value)} → ${formatDate(toInput.value)}`;
    };

    buttons.forEach((button) => button.addEventListener('click', () => {
        const value = button.dataset.calendarDate;
        if (!selectingEnd) {
            fromInput.value = value;
            toInput.value = value;
            selectingEnd = true;
        } else {
            const start = fromInput.value;
            fromInput.value = value < start ? value : start;
            toInput.value = value < start ? start : value;
            selectingEnd = false;
        }
        paint();
    }));

    paint();
}

/** Segmented List / Map switch (mobile only, both are shown on desktop). */
function initViewToggles() {
    const buttons = Array.from(document.querySelectorAll('[data-view-target]'));
    const sections = Array.from(document.querySelectorAll('[data-view]'));

    if (!buttons.length || !sections.length) {
        return;
    }

    const apply = (target) => {
        buttons.forEach((button) => {
            button.setAttribute('aria-selected', String(button.dataset.viewTarget === target));
        });

        sections.forEach((section) => {
            section.classList.toggle('is-view-hidden', section.dataset.view !== target);
        });
    };

    buttons.forEach((button) => {
        button.addEventListener('click', () => apply(button.dataset.viewTarget));
    });

    apply(buttons[0].dataset.viewTarget);
}

/** Submit the surrounding form as soon as a select / date changes. */
function initAutoSubmit() {
    document.querySelectorAll('[data-auto-submit]').forEach((field) => {
        field.addEventListener('change', () => {
            const form = field.closest('form');

            if (!form) {
                return;
            }

            // requestSubmit() keeps the submit event (and its confirm handler) intact.
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    });
}

function initConfirmForms() {
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
}

/** Full size view of an attendance photo. */
function initPhotoDialog() {
    const dialog = document.querySelector('[data-photo-dialog]');

    if (!dialog) {
        return;
    }

    const image = dialog.querySelector('[data-photo-dialog-image]');
    const original = dialog.querySelector('[data-photo-dialog-original]');
    const close = dialog.querySelector('[data-photo-dialog-close]');

    const hide = () => dialog.classList.remove('is-visible');

    document.querySelectorAll('[data-photo-thumb]').forEach((thumb) => {
        thumb.addEventListener('click', (event) => {
            event.preventDefault();

            image.src = thumb.dataset.photoSrc;
            original.href = thumb.dataset.photoOriginal || thumb.dataset.photoSrc;
            dialog.classList.add('is-visible');
        });
    });

    close?.addEventListener('click', hide);
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            hide();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            hide();
        }
    });
}
