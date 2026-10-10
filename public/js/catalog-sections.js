// Each section saves independently; edits in neighboring sections stay in place.
document.addEventListener('DOMContentLoaded', () => {
    const bindForms = root => root.querySelectorAll('[data-catalog-save]').forEach(form => {
        if (form.dataset.catalogBound) return;
        form.dataset.catalogBound = 'true';
        const status = document.createElement('p');
        status.setAttribute('role', 'status');
        status.className = 'form-hint';
        form.append(status);
        form.addEventListener('submit', async event => {
            event.preventDefault();
            event.stopImmediatePropagation();
            if (form.dataset.saving === 'true' || !form.reportValidity()) return;
            const button = form.querySelector('[type=submit],button:not([type])');
            form.dataset.saving = 'true';
            button.disabled = true;
            status.textContent = 'Saving this section…';
            status.classList.remove('text-red-700');
            try {
                const response = await fetch(form.action, {method:'POST', body:new FormData(form), headers:{Accept:'application/json', 'X-Requested-With':'XMLHttpRequest'}});
                const data = await response.json();
                if (!response.ok) {
                    status.classList.add('text-red-700');
                    status.textContent = Object.values(data.errors || {}).flat().join(' ') || data.message || 'Could not save. Try again.';
                    return;
                }
                status.textContent = data.message;
                if (data.html) {
                    const container = document.createElement('div');
                    container.innerHTML = data.html;
                    const sections = [...container.children];
                    form.closest('.catalog-section').replaceWith(...sections);
                    for (const section of sections) {
                        window.Alpine?.initTree(section);
                        bindForms(section);
                    }
                    const savedStatus = sections[0]?.querySelector('form:not([data-catalog-toggle]) [role=status]');
                    if (savedStatus) savedStatus.textContent = data.message;
                    return;
                }
                if (form.hasAttribute('data-catalog-toggle')) {
                    button.textContent = data.available ? 'Make layer unavailable' : 'Make layer available';
                    if (data.available) {
                        button.classList.remove('catalog-toggle-enable');
                        button.classList.add('catalog-toggle-disable');
                    } else {
                        button.classList.remove('catalog-toggle-disable');
                        button.classList.add('catalog-toggle-enable');
                    }
                    const pill = form.closest('.catalog-section').querySelector('[data-option-availability]');
                    if (pill) {
                        pill.textContent = data.available ? 'Available' : 'Unavailable';
                        pill.className = 'text-xs px-2.5 py-0.5 rounded-full font-medium ' + (data.available ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200');
                    }
                }
                if (data.action) {
                    form.action = data.action;
                    let method = form.querySelector('[name=_method]');
                    if (!method) { method = document.createElement('input'); method.type='hidden'; method.name='_method'; form.append(method); }
                    method.value = 'PATCH';
                    button.textContent = 'Save layer option';
                }
                form.querySelectorAll('input[type=file]').forEach(input => { input.value=''; });
            } catch {
                status.classList.add('text-red-700');
                status.textContent = 'Could not save this section. Your edits are still here. Try again.';
            } finally {
                form.dataset.saving = 'false';
                button.disabled = false;
            }
        }, true);
    });
    bindForms(document);
});
