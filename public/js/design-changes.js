(() => {
    const form = document.querySelector('[data-design-editor]');
    if (!form) return;
    const status = form.querySelector('[data-draft-status]');
    const error = form.querySelector('[data-design-error]');
    let queue = Promise.resolve();
    let timer;
    let dirty = false;
    let version = 0;
    let submitting = false;
    const json = async (url, data) => {
        const response = await fetch(url, {method:'POST', body:data, headers:{Accept:'application/json'}});
        if (response.status === 413) throw new Error('These photos are too large for one upload. Choose fewer photos and try again.');
        if (response.status >= 500) throw new Error('The bakery could not save this right now. Your fields are kept here; please try again.');
        let result;
        try { result = await response.json(); } catch { throw new Error('Could not save. Check your connection and try again.'); }
        if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Could not save. Please try again.');
        return result;
    };
    const showError = message => { error.textContent = message; error.hidden = false; };
    const save = async () => {
        const savingVersion = version;
        const data = new FormData(form);
        status.textContent = 'Saving draft…';
        await json(form.dataset.saveUrl, data);
        dirty = version !== savingVersion;
        status.textContent = dirty ? 'Unsaved changes…' : 'Draft saved in this browser session.';
    };
    const enqueue = task => {
        const work = queue.catch(() => {}).then(task);
        queue = work;
        work.catch(err => { status.textContent = 'Draft not saved.'; showError(err.message); });
        return work;
    };
    form.addEventListener('input', event => {
        if (event.target.matches('[type=file]')) return;
        dirty = true;
        version++;
        clearTimeout(timer);
        timer = setTimeout(() => enqueue(save), 700);
    });
    form.querySelectorAll('[data-design-upload]').forEach(input => input.addEventListener('change', () => {
        if (!input.files.length) return;
        const files = Array.from(input.files);
        const line = input.closest('[data-design-line]');
        const message = line.querySelector('[data-upload-status]');
        const selected = line.querySelectorAll('input[type=checkbox]:checked').length;
        if (selected + files.length > 5 || files.some(file => file.size > 5 * 1024 * 1024 || !['image/jpeg','image/png','image/webp'].includes(file.type))) {
            message.textContent = 'Choose up to five photos in total, JPG, PNG or WebP, up to 5 MB each.';
            input.value = ''; return;
        }
        clearTimeout(timer);
        input.disabled = true;
        line.setAttribute('aria-busy', 'true');
        message.textContent = 'Uploading reference photos…';
        enqueue(async () => {
            try {
                await save();
                const data = new FormData();
                data.append('_token', form.elements._token.value);
                data.append('draft_id', form.elements.draft_id.value);
                data.append('line_id', line.dataset.designLine);
                files.forEach(file => data.append('images[]', file));
                const result = await json(form.dataset.uploadUrl, data);
                const grid = line.querySelector('[data-staged-images]');
                const omitted = new Set(Array.from(grid.querySelectorAll('input:not(:checked)')).map(box => box.value));
                grid.replaceChildren();
                result.images.forEach(image => {
                    const label = document.createElement('label'); label.className = 'design-photo-choice';
                    const photo = document.createElement('img'); photo.src = image.url; photo.alt = image.name; photo.width = photo.height = 96;
                    const name = document.createElement('span'); name.textContent = image.name;
                    const check = document.createElement('span'); check.className = 'design-check';
                    const box = document.createElement('input'); box.type = 'checkbox'; box.name = `items[${line.dataset.designLine}][staged_images][]`; box.value = image.key; box.checked = !omitted.has(image.key);
                    check.append(box, document.createTextNode(' Include uploaded photo'));
                    label.append(photo, name, check); grid.append(label);
                });
                message.textContent = 'Photos uploaded. Submit your request to send them for review.';
                status.textContent = 'Draft saved in this browser session.';
            } catch (err) { message.textContent = err.message; throw err; }
            finally { input.value = ''; input.disabled = false; line.removeAttribute('aria-busy'); }
        });
    }));
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (submitting || !form.reportValidity()) return;
        submitting = true;
        clearTimeout(timer);
        const button = form.querySelector('button[type=submit]');
        button.disabled = true; button.textContent = 'Submitting request…';
        try {
            await queue.catch(() => {});
            await save();
            dirty = false;
            HTMLFormElement.prototype.submit.call(form);
        } catch (err) { showError(err.message); button.disabled = false; button.textContent = 'Submit change request'; submitting = false; }
    });
    window.addEventListener('beforeunload', event => { if (dirty && !submitting) { event.preventDefault(); event.returnValue = ''; } });
})();
