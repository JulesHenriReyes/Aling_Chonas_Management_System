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
            const error = document.createElement('p');
            error.id = `${field.id || 'field'}-error-${key.replaceAll('.', '-')}-${index}`;
            error.className = 'field-error';
            error.textContent = messages.join(' ');
            field.setAttribute('aria-invalid','true');
            field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'),error.id].filter(Boolean).join(' '));
            field.after(error);
            if (!firstInvalid && visible(field) && !field.disabled && field.type !== 'hidden') firstInvalid = field;
        });
    }
    if (Object.keys(errors).length) (firstInvalid || document.querySelector('[data-error-summary]'))?.focus();
});
