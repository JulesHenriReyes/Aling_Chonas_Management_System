(() => {
    const pad = value => String(value).padStart(2, '0');
    const label = time => {
        const [hour, minute] = time.split(':');
        return `${Number(hour) % 12 || 12}:${minute} ${Number(hour) < 12 ? 'AM' : 'PM'}`;
    };

    function initialize(root) {
        const options = JSON.parse(root.dataset.options);
        const input = root.querySelector('[data-time-value]');
        const trigger = root.querySelector('[data-time-trigger]');
        const display = root.querySelector('[data-time-display]');
        const panel = root.querySelector('[data-time-panel]');
        const period = root.querySelector('[data-time-period]');
        const hour = root.querySelector('[data-time-hour]');
        const minute = root.querySelector('[data-time-minute]');
        const preview = root.querySelector('[data-time-preview]');
        const done = root.querySelector('[data-time-done]');
        const error = root.querySelector('[data-time-error]');
        const valid = time => {
            if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(time)) return false;
            const [h, m] = time.split(':');
            return (options[Number(h) < 12 ? 'AM' : 'PM'] || []).some(option => option.value === h && option.minutes.includes(m));
        };
        const showError = message => {
            error.textContent = message;
            error.hidden = !message;
            trigger.setAttribute('aria-invalid', message ? 'true' : 'false');
        };
        const sync = () => {
            display.textContent = valid(input.value) ? label(input.value) : 'Select pickup time';
            trigger.classList.toggle('pickup-time-empty', !valid(input.value));
            if (input.value && !valid(input.value)) showError(root.dataset.message);
            else if (valid(input.value)) showError('');
            else trigger.setAttribute('aria-invalid', error.hidden ? 'false' : 'true');
        };
        const populate = (select, rows, previous) => {
            select.replaceChildren(new Option('Select', ''));
            for (const row of rows) select.add(new Option(row.label, row.value));
            select.disabled = !rows.length;
            select.value = rows.some(row => row.value === previous) ? previous : '';
        };
        const draft = () => hour.value && minute.value ? `${hour.value}:${minute.value}` : '';
        const updateMinutes = previous => {
            const selectedHour = (options[period.value] || []).find(option => option.value === hour.value);
            populate(minute, (selectedHour?.minutes || []).map(value => ({ value, label: value })), previous);
            done.disabled = !valid(draft());
            preview.textContent = valid(draft()) ? label(draft()) : (period.value ? 'Choose an hour and minute.' : 'Choose AM or PM to begin.');
        };
        const updateHours = (previousHour, previousMinute) => {
            // Keep the displayed hour across AM/PM only if its new counterpart is allowed.
            populate(hour, options[period.value] || [], previousHour);
            updateMinutes(previousMinute);
        };
        const position = () => {
            if (panel.hidden) return;
            const anchor = trigger.getBoundingClientRect();
            if (anchor.bottom < 0 || anchor.top > innerHeight) { close(false); return; }
            panel.style.width = `${Math.min(320, innerWidth - 24)}px`;
            const height = panel.getBoundingClientRect().height;
            const top = anchor.bottom + 8 + height <= innerHeight - 12 ? anchor.bottom + 8 : anchor.top - height - 8;
            panel.style.top = `${Math.max(12, Math.min(top, innerHeight - height - 12))}px`;
            panel.style.left = `${Math.max(12, Math.min(anchor.left, innerWidth - panel.getBoundingClientRect().width - 12))}px`;
        };
        const close = (returnFocus = true) => {
            if (panel.hidden) return;
            panel.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            if (returnFocus) trigger.focus({ preventScroll: true });
        };
        const open = () => {
            const anchor = trigger.getBoundingClientRect();
            if (anchor.top < 80 || anchor.bottom > innerHeight - 12) {
                trigger.scrollIntoView({ behavior: 'instant', block: 'center' });
            }
            if (valid(input.value)) {
                const [h, m] = input.value.split(':');
                period.value = Number(h) < 12 ? 'AM' : 'PM';
                updateHours(h, m);
            } else {
                period.value = '';
                updateHours('', '');
            }
            panel.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            position();
            period.focus({ preventScroll: true });
        };
        period.addEventListener('change', () => {
            const equivalent = hour.value ? pad((Number(hour.value) % 12) + (period.value === 'PM' ? 12 : 0)) : '';
            updateHours(equivalent, minute.value);
        });
        hour.addEventListener('change', () => updateMinutes(minute.value));
        minute.addEventListener('change', () => updateMinutes(minute.value));
        trigger.addEventListener('click', () => panel.hidden ? open() : close());
        root.querySelector('[data-time-close]').addEventListener('click', () => close());
        done.addEventListener('click', () => {
            if (!valid(draft())) return;
            input.value = draft();
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            close();
        });
        root.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !panel.hidden) {
                event.preventDefault();
                event.stopPropagation();
                close();
            }
        });
        root.addEventListener('focusout', event => {
            if (event.relatedTarget && !root.contains(event.relatedTarget)) close(false);
        });
        document.addEventListener('click', event => {
            if (!root.contains(event.target)) close(false);
        });
        window.addEventListener('resize', position);
        window.addEventListener('scroll', position, true);
        input.addEventListener('input', sync);
        input.addEventListener('change', sync);
        window.addEventListener('pageshow', event => {
            // Initial page load may finish after the customer already opens the clock.
            if (event.persisted) { close(false); sync(); }
        });
        input.form?.addEventListener('reset', () => queueMicrotask(() => { close(false); sync(); }));
        input.form?.addEventListener('submit', event => {
            // Back to packages intentionally saves incomplete details without validation.
            if (event.submitter?.formNoValidate || input.form.noValidate) return;
            if (!valid(input.value)) {
                event.preventDefault();
                showError(root.dataset.message);
                open();
            } else close(false);
        }, true);

        const original = input.value || input.getAttribute('value') || '';
        const hadFocus = document.activeElement === input;
        input.type = 'hidden';
        input.required = false;
        input.value = original;
        root.querySelector('label').htmlFor = trigger.id;
        trigger.hidden = false;
        sync();
        if (input.getAttribute('aria-invalid') === 'true') showError(error.textContent || root.dataset.message);
        if (hadFocus) trigger.focus({ preventScroll: true });
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-pickup-time]').forEach(initialize);
    });
})();
