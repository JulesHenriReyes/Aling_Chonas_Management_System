(() => {
    const container = document.querySelector('[data-order-live]');
    if (!container) return;
    let checking = false;
    let dirty = false;
    container.addEventListener('input', () => { dirty = true; });
    container.querySelector('[data-live-reload]')?.addEventListener('click', () => location.reload());
    const check = async () => {
        if (document.hidden || checking) return;
        checking = true;
        try {
            const res = await fetch(container.dataset.liveUrl, {headers:{Accept:'application/json'}, cache:'no-store'});
            if (!res.ok) return;
            const data = await res.json();
            if (data.revision === container.dataset.liveRevision) return;
            const openDialog = Array.from(document.querySelectorAll('[data-dialog]')).some(el => el.getClientRects().length > 0);
            const editing = dirty || container.contains(document.activeElement) && document.activeElement.matches('input,textarea,select')
                || container.querySelector('[data-order-cancellation][open], [data-design-decline][open]') || openDialog;
            if (editing) {
                container.querySelector('[data-live-notice]').hidden = false;
            } else location.reload();
        } catch {} finally { checking = false; }
    };
    const timer = setInterval(check, 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) check(); });
    window.addEventListener('pagehide', () => clearInterval(timer), {once:true});
})();
