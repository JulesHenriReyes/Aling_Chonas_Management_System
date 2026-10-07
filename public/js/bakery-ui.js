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

    const adminTopbar = document.getElementById('admin-topbar');
    if (adminTopbar) {
        const updateTopbarHeight = () => {
            const h = adminTopbar.offsetHeight;
            if (h > 0) {
                document.documentElement.style.setProperty('--topbar-height', `${h}px`);
            }
        };
        updateTopbarHeight();
        window.addEventListener('resize', updateTopbarHeight);
    }

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

        // Intentional cancellations via Cancel buttons, back links, or data-cancel/data-discard
        // must not be blocked by unsaved changes beforeunload confirmation dialogs.
        document.addEventListener('click', event => {
            const cancelTrigger = event.target.closest('[data-cancel], [data-discard], a.back-link');
            const isStockCancel = event.target.closest('a, button') &&
                event.target.closest('a, button').textContent.trim().toLowerCase() === 'cancel' &&
                (form.matches('[x-ref="stockForm"]') || form.action?.includes('/inventory') || form.action?.includes('/supplies'));
            if (cancelTrigger || isStockCancel || window.__suppressUnload) {
                dirty = false;
                window.__suppressUnload = true;
            }
        }, true);

        if (form.hasAttribute('data-safe-form')) {
            window.addEventListener('beforeunload', event => {
                if (window.__suppressUnload) {
                    dirty = false;
                    return;
                }
                if (dirty && !submitting) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
        }
        window.addEventListener('pageshow', () => {
            submitting = false;
            window.__suppressUnload = false;
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
            const spaceBelow = window.innerHeight - rect.bottom;
            const spaceAbove = rect.top;
            const dropdownHeight = 160;
            if (spaceBelow < dropdownHeight && spaceAbove >= dropdownHeight) {
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

window.positionTooltip = function(el, refs, show, position, align) {
    if (show) {
        requestAnimationFrame(() => {
            const trigger = refs?.trigger || el.querySelector('[x-ref="trigger"]') || el.firstElementChild || el;
            const panel = refs?.panel || el.querySelector('[x-ref="panel"]');
            if (!trigger || !panel) return;

            panel.style.visibility = 'hidden';
            const originalDisplay = panel.style.display;
            if (getComputedStyle(panel).display === 'none') {
                panel.style.display = 'block';
            }

            const tRect = trigger.getBoundingClientRect();
            const pRect = panel.getBoundingClientRect();
            const pWidth = Math.max(panel.offsetWidth, pRect.width, 100);
            const pHeight = Math.max(panel.offsetHeight, pRect.height, 24);
            const vw = window.innerWidth;
            const vh = window.innerHeight;
            const gap = 8;

            let top;
            const canFitTop = tRect.top - gap - pHeight >= 8;
            const canFitBottom = tRect.bottom + gap + pHeight <= vh - 8;
            if (position === 'bottom') {
                top = (canFitBottom || !canFitTop) ? (tRect.bottom + gap) : (tRect.top - gap - pHeight);
            } else {
                top = (canFitTop || !canFitBottom) ? (tRect.top - gap - pHeight) : (tRect.bottom + gap);
            }
            if (top < 8) top = 8;
            else if (top + pHeight > vh - 8) top = Math.max(8, vh - pHeight - 8);

            let left;
            if (align === 'center') {
                left = tRect.left + (tRect.width / 2) - (pWidth / 2);
            } else if (align === 'right') {
                left = tRect.right - pWidth;
            } else if (align === 'left') {
                left = tRect.left;
            } else {
                // 'top-right' (default): spawn above and to the right of the trigger icon
                left = tRect.left + (tRect.width / 2);
            }
            if (left < 8) left = 8;
            else if (left + pWidth > vw - 8) left = Math.max(8, vw - pWidth - 8);

            panel.style.top = Math.round(top) + 'px';
            panel.style.left = Math.round(left) + 'px';
            panel.style.visibility = 'visible';
        });
    } else {
        const p = refs?.panel || el.querySelector('[x-ref="panel"]');
        if (p) {
            p.style.visibility = 'hidden';
            p.style.display = '';
        }
    }
};


