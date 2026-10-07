document.addEventListener('DOMContentLoaded', () => {
    const form=document.querySelector('[data-staff-checkout]');
    if (!form) return;
    const key=form.dataset.draftPrefix+'-checkout';
    const fields=['customer_id','pickup_date','pickup_time','notes_text'];
    let timer, sending=false, saving=false, pending=false;
    const picker=() => window.Alpine?.$data(form);
    const snapshot=() => ({...Object.fromEntries(fields.map(name => [name, form.elements.namedItem(name)?.value || ''])),
        customer_id:picker()?.selectedId || '', customer_picker:picker()?.snapshot() || {}});
    const errors=JSON.parse(document.getElementById('validation-errors')?.textContent || '{}');
    try {
        const previous=JSON.parse(sessionStorage.getItem(key) || 'null');
        if (previous && !Object.keys(errors).length) {
            for (const name of fields) {
                const field=form.elements.namedItem(name);
                if (field && typeof previous[name] === 'string') {
                    field.value=previous[name];
                    field.dispatchEvent(new Event('change', {bubbles:true}));
                }
            }
            const customer=picker();
            if (customer) {
                customer.selectedId=customer.customers.some(item => String(item.id) === previous.customer_id) ? previous.customer_id : '';
                customer.restorePicker(previous.customer_picker || {});
            }
        }
    } catch {}
    function remember() {
        try { sessionStorage.setItem(key,JSON.stringify(snapshot())); } catch {}
        clearTimeout(timer);
        timer=setTimeout(save,400);
    }
    async function save() {
        if (sending) return;
        if (saving) { pending=true; return; }
        saving=true;
        try {
            const response=await fetch(form.dataset.detailsDraftUrl,{method:'POST',credentials:'same-origin',
                headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':form.elements.namedItem('_token').value},
                body:JSON.stringify({...snapshot(), draft_id:form.elements.namedItem('draft_id').value})});
            if (!response.ok) form.querySelector('[data-draft-status]').textContent='Your details are kept in this browser. Reload if your session has expired.';
            else form.querySelector('[data-draft-status]').textContent='Details saved.';
        } catch { form.querySelector('[data-draft-status]').textContent='Your details are kept in this browser. Check your connection before creating the order.'; }
        finally { saving=false; if (pending) {pending=false; save();} }
    }
    form.addEventListener('input',remember);
    form.addEventListener('change',remember);
    form.addEventListener('staff-customer-change',remember);
    form.addEventListener('submit',event => {
        if (event.defaultPrevented) return;
        if (sending) {event.preventDefault(); return;}
        try {sessionStorage.setItem(key,JSON.stringify(snapshot()));} catch {}
        clearTimeout(timer);
        sending=true;
        setTimeout(() => form.querySelectorAll('button[type=submit]').forEach(button => button.disabled=true),0);
    });
    window.addEventListener('pageshow',() => {sending=false;form.querySelectorAll('button[type=submit]').forEach(button => button.disabled=false);});
});
