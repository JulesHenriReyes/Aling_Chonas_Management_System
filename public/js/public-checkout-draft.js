document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.order-details-grid');
    const token = form?.querySelector('[name=submission_key]')?.value;
    if (!form || !token) return;
    const key = 'bakery-contact-' + token;
    const fields = [...form.querySelectorAll('input:not([type=hidden]), input[name=pickup_time], textarea, select')].filter(field => field.name && field.type !== 'file');
    try {
        const previous = JSON.parse(sessionStorage.getItem(key) || '{}');
        const errors = JSON.parse(document.getElementById('validation-errors')?.textContent || '{}');
        if (!Object.keys(errors).length) for (const field of fields) if (previous[field.name] !== undefined) {
            field.value = previous[field.name];
            field.dispatchEvent(new Event('change', { bubbles: true }));
        }
    } catch {}
    form.addEventListener('input', () => {
        try { sessionStorage.setItem(key, JSON.stringify(Object.fromEntries(fields.map(field => [field.name, field.value])))); } catch {}
    });
    let sending = false;
    form.addEventListener('submit', event => {
        if (event.defaultPrevented) return;
        if (sending) { event.preventDefault(); return; }
        sending = true;
        setTimeout(() => form.querySelectorAll('button[type=submit]').forEach(button => button.disabled = true), 0);
    });
    window.addEventListener('pageshow', () => { sending=false; form.querySelectorAll('button[type=submit]').forEach(button => button.disabled=false); });
});
