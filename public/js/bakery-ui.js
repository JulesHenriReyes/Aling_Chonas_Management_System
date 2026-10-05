/* Accessibility behavior only. Pricing, form payloads and business actions remain in their existing handlers. */
document.addEventListener('DOMContentLoaded', () => {
    const visible = element => element.getClientRects().length && getComputedStyle(element).visibility !== 'hidden';
    const focusable = root => [...root.querySelectorAll('a[href],button,input,select,textarea,[tabindex]')]
        .filter(element => !element.disabled && element.tabIndex >= 0 && visible(element));
    const overlays = [...document.querySelectorAll('[data-dialog], [data-sidebar]')];
    const mobile = matchMedia('(max-width: 1023px)');
    let activeOverlay = null;
    let returnFocus = null;
    let previousOverflow = '';
    const backgroundState = new Map();
    const restoreBackground = () => {
        for (const [element, inert] of backgroundState) element.inert = inert;
        backgroundState.clear();
    };
    const syncOverlay = () => {
        const next = overlays.find(element => visible(element) &&
            (element.hasAttribute('data-dialog') || (mobile.matches && element.dataset.open === 'true')));
        if (next === activeOverlay) return;
        restoreBackground();
        if (activeOverlay) {
            document.body.style.overflow = previousOverflow;
            if (returnFocus?.isConnected && visible(returnFocus)) returnFocus.focus();
        }
        activeOverlay = next || null;
        if (!next) return;
        returnFocus = document.activeElement;
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        let branch = next;
        while (branch.parentElement && branch !== document.body) {
            for (const sibling of branch.parentElement.children) {
                if (sibling !== branch && sibling instanceof HTMLElement && !sibling.hasAttribute('data-sidebar-backdrop') && !['SCRIPT','STYLE','LINK'].includes(sibling.tagName)) {
                    backgroundState.set(sibling, sibling.inert);
                    sibling.inert = true;
                }
            }
            branch = branch.parentElement;
        }
        (focusable(next)[0] || next).focus();
    };
    const observer = new MutationObserver(syncOverlay);
    overlays.forEach(element => observer.observe(element, {attributes:true, attributeFilter:['style','class','data-open']}));
    mobile.addEventListener('change', syncOverlay);
    document.addEventListener('keydown', event => {
        if (!activeOverlay) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            activeOverlay.querySelector('[data-dialog-close], [data-sidebar-close]')?.click();
        }
        if (event.key === 'Tab') {
            const items = focusable(activeOverlay);
            const first = items[0], last = items[items.length - 1];
            if (!first) { event.preventDefault(); return; }
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    syncOverlay();

    const errors = JSON.parse(document.getElementById('validation-errors')?.textContent || '{}');
    const validationRoot = document.querySelector('[data-validation-active="true"]') || document;
    let firstInvalid;
    for (const [key, messages] of Object.entries(errors)) {
        const name = key.split('.').map((part,index) => index ? `[${part}]` : part).join('');
        const fields = [...validationRoot.querySelectorAll('input,select,textarea')].filter(field =>
            field.name === name || field.name === `${name}[]` || (field.type === 'file' && name.startsWith(field.name.replace(/\[\]$/, '') + '[')));
        fields.forEach((field,index) => {
            const disclosure = field.closest('details');
            if (disclosure) disclosure.open = true;
            const associated = (field.getAttribute('aria-describedby') || '').split(/\s+/)
                .map(id => document.getElementById(id)).find(element => element?.matches('[role="alert"], .field-error'));
            const rendered = [...validationRoot.querySelectorAll('[data-error-for]')]
                .find(element => element.dataset.errorFor === field.name || element.dataset.errorFor === key);
            const error = associated || rendered || document.createElement('p');
            error.id ||= `${field.id || 'field'}-error-${key.replaceAll('.', '-')}-${index}`;
            if (!associated && !rendered) {
                error.className = 'field-error';
                error.textContent = messages.join(' ');
                field.after(error);
            }
            field.setAttribute('aria-invalid','true');
            field.setAttribute('aria-describedby', [...new Set([...(field.getAttribute('aria-describedby') || '').split(/\s+/),error.id].filter(Boolean))].join(' '));
            if (!firstInvalid && visible(field) && !field.disabled && field.type !== 'hidden') firstInvalid = field;
        });
    }
    if (Object.keys(errors).length) (firstInvalid || document.querySelector('[data-error-summary]'))?.focus();
});

// Dedicated editing pages retain old input on errors and warn before discarding edits.
document.addEventListener('DOMContentLoaded', () => {
    const forms = [...document.querySelectorAll('form[data-safe-form], form[data-action-form]')];
    for (const form of forms) {
        let dirty = false, submitting = false;
        const disabledBeforeSubmit = new Map();
        form.addEventListener('input', () => { dirty = true; });
        form.addEventListener('change', () => { dirty = true; });
        form.addEventListener('submit', event => {
            if (event.defaultPrevented) return;
            if (submitting) { event.preventDefault(); return; }
            if (!form.checkValidity()) return;
            submitting = true; dirty = false;
            const status = form.querySelector('[data-submit-status]');
            if (status) status.textContent = 'Saving…';
            form.querySelectorAll('button[type=submit], button:not([type])').forEach(button => disabledBeforeSubmit.set(button,button.disabled));
            setTimeout(() => form.querySelectorAll('button[type=submit], button:not([type])').forEach(button => button.disabled = true), 0);
        });
        if (form.hasAttribute('data-safe-form')) window.addEventListener('beforeunload', event => { if (dirty && !submitting) { event.preventDefault(); event.returnValue = ''; } });
        window.addEventListener('pageshow', () => {
            submitting = false;
            for (const [button,disabled] of disabledBeforeSubmit) button.disabled=disabled;
            disabledBeforeSubmit.clear();
            const status = form.querySelector('[data-submit-status]');
            if (status) status.textContent = '';
        });
    }
    const target = location.hash && document.getElementById(location.hash.slice(1));
    if (target?.tagName === 'DETAILS') target.open = true;
    // Floating row-actions popover management: click-outside, auto-flip, exclusivity, keyboard nav
    document.addEventListener('click', event => {
        const clickedAction = event.target.closest('.row-actions');
        document.querySelectorAll('.row-actions[open]').forEach(menu => {
            if (menu !== clickedAction) {
                menu.open = false;
                menu.classList.remove('drop-up');
            }
        });
        if (event.target.closest('.row-actions[open] > div a, .row-actions[open] > div button')) {
            const menu = event.target.closest('.row-actions[open]');
            if (menu) {
                menu.open = false;
                menu.classList.remove('drop-up');
            }
        }
    });

    document.addEventListener('toggle', event => {
        const menu = event.target;
        if (!menu.matches?.('.row-actions')) return;
        if (menu.open) {
            document.querySelectorAll('.row-actions[open]').forEach(other => {
                if (other !== menu) {
                    other.open = false;
                    other.classList.remove('drop-up');
                }
            });
            const rect = menu.getBoundingClientRect();
            const scrollContainer = menu.closest('.table-scroll') || document.documentElement;
            const containerRect = scrollContainer.getBoundingClientRect();
            const spaceBelow = Math.min(window.innerHeight, containerRect.bottom) - rect.bottom;
            const spaceAbove = rect.top - Math.max(0, containerRect.top);
            const dropdownHeight = 160;
            if (spaceBelow < dropdownHeight && spaceAbove > spaceBelow) {
                menu.classList.add('drop-up');
            } else {
                menu.classList.remove('drop-up');
            }
        } else {
            menu.classList.remove('drop-up');
        }
    }, true);

    document.addEventListener('focusout', event => {
        const menu = event.target.closest?.('.row-actions[open]');
        if (!menu) return;
        if (event.relatedTarget && !menu.contains(event.relatedTarget)) {
            menu.open = false;
            menu.classList.remove('drop-up');
        }
    });

    document.addEventListener('keydown', event => {
        const menu = document.activeElement?.closest?.('.row-actions[open]');
        if (!menu) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            menu.open = false;
            menu.classList.remove('drop-up');
            menu.querySelector('summary')?.focus();
            return;
        }

        const items = [...menu.querySelectorAll('div a')];
        if (!items.length) return;

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            const currentIndex = items.indexOf(document.activeElement);
            const nextIndex = currentIndex < items.length - 1 ? currentIndex + 1 : 0;
            items[nextIndex]?.focus();
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            const currentIndex = items.indexOf(document.activeElement);
            const prevIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
            items[prevIndex]?.focus();
        } else if (event.key === 'Home') {
            event.preventDefault();
            items[0]?.focus();
        } else if (event.key === 'End') {
            event.preventDefault();
            items[items.length - 1]?.focus();
        }
    });
});
