/* Accessibility behavior only. Pricing, form payloads and business actions remain in their existing handlers. */
document.addEventListener('DOMContentLoaded', () => {
    const visible = element => element.getClientRects().length && getComputedStyle(element).visibility !== 'hidden';
    const focusable = root => [...root.querySelectorAll('a[href],button,input,select,textarea,[tabindex]')]
        .filter(element => !element.disabled && element.tabIndex >= 0 && visible(element));
    const mobile = matchMedia('(max-width: 1023px)');
    let activeOverlay = null;
    const returnTargets = new WeakMap();
    let previousOverflow = '';
    const backgroundState = new Map();
    const restoreBackground = () => {
        for (const [element, inert] of backgroundState) element.inert = inert;
        backgroundState.clear();
    };
    const syncOverlay = () => {
        const next = [...document.querySelectorAll('[data-dialog], [data-sidebar]')].reverse().find(element => visible(element) &&
            (element.hasAttribute('data-dialog') || (mobile.matches && element.dataset.open === 'true'))) || null;
        if (next === activeOverlay) return;
        const focusedBeforeChange = document.activeElement;
        const previous = activeOverlay;
        restoreBackground();
        if (previous && !visible(previous)) {
            const target = returnTargets.get(previous);
            if (target?.isConnected && visible(target)) target.focus();
            returnTargets.delete(previous);
        }
        activeOverlay = next || null;
        if (!next) {
            document.body.style.overflow = previousOverflow;
            return;
        }
        if (!returnTargets.has(next)) returnTargets.set(next, focusedBeforeChange);
        if (!previous) previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        let branch = next;
        while (branch.parentElement && branch !== document.body) {
            for (const sibling of branch.parentElement.children) {
                if (sibling !== branch && sibling instanceof HTMLElement && !sibling.hasAttribute('data-sidebar-backdrop') && !sibling.hasAttribute('data-dialog-backdrop') && !['SCRIPT','STYLE','LINK'].includes(sibling.tagName)) {
                    backgroundState.set(sibling, sibling.inert);
                    sibling.inert = true;
                }
            }
            branch = branch.parentElement;
        }
        if (!next.contains(document.activeElement)) (focusable(next)[0] || next).focus();
    };
    const observer = new MutationObserver(syncOverlay);
    // Alpine teleports and AJAX can create dialogs after DOMContentLoaded.
    observer.observe(document.body, {childList:true, subtree:true, attributes:true, attributeFilter:['style','class','data-open']});
    mobile.addEventListener('change', syncOverlay);
    document.addEventListener('keydown', event => {
        if (!activeOverlay) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            const close = activeOverlay.querySelector('[data-dialog-close], [data-sidebar-close], [aria-label="Close image preview"], [aria-label="Close detail panel"]');
            if (close) { event.stopPropagation(); close.click(); }
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

    document.addEventListener('click', (event) => {
        const collapseTrigger = event.target.closest('[data-sidebar-collapse]');
        if (collapseTrigger) {
            event.preventDefault();
            const collapsed = document.body.classList.toggle('sidebar-collapsed');
            const isExpanded = !collapsed;
            document.querySelectorAll('[data-sidebar-collapse]').forEach(btn => {
                btn.setAttribute('aria-expanded', String(isExpanded));
                btn.setAttribute('aria-label', isExpanded ? 'Collapse navigation' : 'Expand navigation');
            });
            window.dispatchEvent(new Event('resize'));
        }
    });
    document.addEventListener('change', event => {
        const targetId = event.target.dataset.reasonTarget;
        if (!targetId || !event.target.value) return;
        const field = document.getElementById(targetId);
        if (!field) return;
        field.value = event.target.value;
        field.dispatchEvent(new Event('input', {bubbles:true}));
        field.focus();
    });

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
        new ResizeObserver(updateTopbarHeight).observe(adminTopbar);
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




/* Instant live filtering without full page reloads */
document.addEventListener('DOMContentLoaded', () => {
    // 1. Orders page AJAX filtering
    const ordersForm = document.getElementById('orders-filter-form');
    const ordersTableContainer = document.getElementById('orders-table-container');
    const ordersStatusTabs = document.getElementById('orders-status-tabs');

    if (ordersForm && ordersTableContainer) {
        let debounceTimer = null;
        let activeAbort = null;

        const refreshPaused = () => document.hidden ||
            ordersForm.contains(document.activeElement) ||
            ordersTableContainer.contains(document.activeElement) ||
            [...document.querySelectorAll('[data-dialog]')].some(dialog => dialog.getClientRects().length > 0);

        const updateOrdersView = async (url, pushHistory = true, background = false) => {
            if (background && (activeAbort || debounceTimer || refreshPaused())) return;
            clearTimeout(debounceTimer);
            debounceTimer = null;
            if (activeAbort) {
                activeAbort.abort();
            }
            const controller = new AbortController();
            activeAbort = controller;

            const loadingEl = document.getElementById('orders-filter-loading');
            if (!background && loadingEl) {
                loadingEl.classList.remove('hidden');
                loadingEl.classList.add('inline-flex');
            }
            if (!background) {
                ordersTableContainer.classList.add('opacity-50', 'pointer-events-none');
                ordersTableContainer.setAttribute('aria-busy', 'true');
            }

            try {
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal,
                    cache: 'no-store'
                });

                if (!response.ok) throw new Error('Filter response status ' + response.status);
                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                if (controller !== activeAbort || controller.signal.aborted || (background && refreshPaused())) return;

                // 1. Swap table container
                const newTable = doc.getElementById('orders-table-container');
                if (!newTable) throw new Error('Orders table missing from response');
                if (newTable.innerHTML !== ordersTableContainer.innerHTML) {
                    const scroll = ordersTableContainer.querySelector('.orders-scroll');
                    const scrollLeft = scroll?.scrollLeft || 0;
                    const scrollTop = scroll?.scrollTop || 0;
                    if (window.Alpine) window.Alpine.destroyTree(ordersTableContainer);
                    ordersTableContainer.innerHTML = newTable.innerHTML;
                    if (!background) {
                        ordersTableContainer.classList.remove('tab-content-enter');
                        void ordersTableContainer.offsetWidth;
                        ordersTableContainer.classList.add('tab-content-enter');
                    }
                    if (window.Alpine) {
                        window.Alpine.initTree(ordersTableContainer);
                    }
                    const nextScroll = ordersTableContainer.querySelector('.orders-scroll');
                    if (nextScroll) {
                        nextScroll.scrollLeft = scrollLeft;
                        nextScroll.scrollTop = scrollTop;
                    }
                }

                // 2. Swap status tabs
                const newTabs = doc.getElementById('orders-status-tabs');
                if (!background && newTabs && ordersStatusTabs) {
                    ordersStatusTabs.innerHTML = newTabs.innerHTML;
                }

                // 3. Swap results meta (order count & Reset all filters link)
                const newMeta = doc.getElementById('orders-results-meta');
                const currentMeta = document.getElementById('orders-results-meta');
                if (newMeta && currentMeta && newMeta.innerHTML !== currentMeta.innerHTML) {
                    currentMeta.innerHTML = newMeta.innerHTML;
                }

                // 4. Sync form inputs with new doc form
                const newForm = doc.getElementById('orders-filter-form');
                if (!background && newForm) {
                    const statusInput = ordersForm.querySelector('input[name="status"]');
                    const newStatusInput = newForm.querySelector('input[name="status"]');
                    if (statusInput && newStatusInput) statusInput.value = newStatusInput.value;

                    let queueInput = ordersForm.querySelector('input[name="queue"]');
                    const newQueueInput = newForm.querySelector('input[name="queue"]');
                    if (newQueueInput) {
                        if (!queueInput) {
                            queueInput = document.createElement('input');
                            queueInput.type = 'hidden';
                            queueInput.name = 'queue';
                            ordersForm.appendChild(queueInput);
                        }
                        queueInput.value = newQueueInput.value;
                    } else if (queueInput) {
                        queueInput.remove();
                    }

                    // Sync inputs from URL parameters
                    const parsedUrl = new URL(url, window.location.origin);
                    const sInput = ordersForm.querySelector('input[name="search"]');
                    const clearBtn = ordersForm.querySelector('button[data-clear-search]');
                    if (sInput) {
                        if (!parsedUrl.searchParams.has('search')) {
                            sInput.value = '';
                            if (clearBtn) clearBtn.classList.add('hidden');
                        } else {
                            sInput.value = parsedUrl.searchParams.get('search');
                            if (clearBtn) clearBtn.classList.remove('hidden');
                        }
                    }
                    const oSelect = ordersForm.querySelector('select[name="origin"]');
                    if (oSelect) {
                        oSelect.value = parsedUrl.searchParams.get('origin') || '';
                    }
                    const dFrom = ordersForm.querySelector('input[name="date_from"]');
                    if (dFrom) {
                        dFrom.value = parsedUrl.searchParams.get('date_from') || '';
                    }
                    const dTo = ordersForm.querySelector('input[name="date_to"]');
                    if (dTo) {
                        dTo.value = parsedUrl.searchParams.get('date_to') || '';
                    }
                }

                if (pushHistory) {
                    window.history.pushState({ url }, '', url);
                }
            } catch (err) {
                if (err.name !== 'AbortError' && !background && controller === activeAbort) {
                    console.error('Orders AJAX filter error, falling back to full navigation:', err);
                    window.location.href = url;
                }
            } finally {
                if (controller !== activeAbort) return;
                activeAbort = null;
                const loadingElAfter = document.getElementById('orders-filter-loading');
                if (loadingElAfter) {
                    loadingElAfter.classList.remove('inline-flex');
                    loadingElAfter.classList.add('hidden');
                }
                ordersTableContainer.classList.remove('opacity-50', 'pointer-events-none');
                ordersTableContainer.removeAttribute('aria-busy');
            }
        };

        const triggerFormFilter = () => {
            const formData = new FormData(ordersForm);
            const params = new URLSearchParams();
            for (const [key, val] of formData.entries()) {
                if (val !== '') {
                    params.append(key, val);
                }
            }
            const targetUrl = ordersForm.action + (params.toString() ? '?' + params.toString() : '');
            updateOrdersView(targetUrl);
        };

        // Text search input with debounce & inline clear button
        const searchInput = ordersForm.querySelector('input[name="search"]');
        const clearSearchBtn = ordersForm.querySelector('button[data-clear-search]');
        if (searchInput) {
            const toggleClearBtn = () => {
                if (clearSearchBtn) {
                    clearSearchBtn.classList.toggle('hidden', !searchInput.value.trim());
                }
            };
            searchInput.addEventListener('input', () => {
                toggleClearBtn();
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(triggerFormFilter, 250);
            });
            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(debounceTimer);
                    triggerFormFilter();
                }
            });
            if (clearSearchBtn) {
                clearSearchBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    searchInput.value = '';
                    toggleClearBtn();
                    clearTimeout(debounceTimer);
                    triggerFormFilter();
                    searchInput.focus();
                });
            }
        }

        // Origin select dropdown
        const originSelect = ordersForm.querySelector('select[name="origin"]');
        if (originSelect) {
            originSelect.addEventListener('change', triggerFormFilter);
        }

        // Date picker inputs
        const dateFromInput = ordersForm.querySelector('input[name="date_from"]');
        const dateToInput = ordersForm.querySelector('input[name="date_to"]');
        if (dateFromInput) dateFromInput.addEventListener('change', triggerFormFilter);
        if (dateToInput) dateToInput.addEventListener('change', triggerFormFilter);

        // Prevent default submit
        ordersForm.addEventListener('submit', (e) => {
            e.preventDefault();
            clearTimeout(debounceTimer);
            triggerFormFilter();
        });

        // Intercept clicks on status tabs, reset button, and table pagination
        document.addEventListener('click', (e) => {
            // Status tab clicked (e.g., "Pending Review", "Awaiting Deposit")
            const tabLink = e.target.closest('#orders-status-tabs a');
            if (tabLink) {
                e.preventDefault();
                updateOrdersView(tabLink.href);
                return;
            }

            // Reset button clicked
            const resetLink = e.target.closest('#orders-results-meta a[data-filter-reset], #orders-table-container a[data-filter-reset]');
            if (resetLink) {
                e.preventDefault();
                if (searchInput) {
                    searchInput.value = '';
                    const clearBtn = ordersForm.querySelector('button[data-clear-search]');
                    if (clearBtn) clearBtn.classList.add('hidden');
                }
                if (originSelect) originSelect.value = '';
                if (dateFromInput) dateFromInput.value = '';
                if (dateToInput) dateToInput.value = '';
                const sInput = ordersForm.querySelector('input[name="status"]');
                if (sInput) sInput.value = '';
                const qInput = ordersForm.querySelector('input[name="queue"]');
                if (qInput) qInput.remove();
                updateOrdersView(resetLink.href);
                return;
            }

            // Table pagination link clicked
            const pageLink = e.target.closest('#orders-table-container nav a');
            if (pageLink) {
                e.preventDefault();
                updateOrdersView(pageLink.href);
                return;
            }
        });

        // Popstate for browser back/forward buttons
        window.addEventListener('popstate', (e) => {
            if (window.location.pathname.includes('/orders')) {
                updateOrdersView(window.location.href, false);
            }
        });

        // Refresh only the list, retaining the current filters and pagination.
        // Failed background requests leave the current list usable and retry later.
        const refreshOrders = () => updateOrdersView(window.location.href, false, true);
        let refreshTimer = setInterval(refreshOrders, 5000);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) refreshOrders();
        });
        window.addEventListener('pageshow', () => {
            if (refreshTimer === null) refreshTimer = setInterval(refreshOrders, 5000);
            refreshOrders();
        });
        window.addEventListener('pagehide', () => {
            clearInterval(refreshTimer);
            refreshTimer = null;
            activeAbort?.abort();
        });
    }

    // 2. Pickup Schedule page AJAX filtering
    const scheduleForm = document.getElementById('schedule-filter-form');
    const scheduleContainer = document.getElementById('schedule-container');

    if (scheduleForm && scheduleContainer) {
        const pickupInput = scheduleForm.querySelector('input[name="pickup_date"]');
        const clearDateBtn = scheduleForm.querySelector('button[data-clear-date]');
        let activeScheduleAbort = null;

        const updateScheduleView = async (url, pushHistory = true) => {
            if (activeScheduleAbort) {
                activeScheduleAbort.abort();
            }
            activeScheduleAbort = new AbortController();

            scheduleContainer.classList.add('opacity-50', 'pointer-events-none');
            scheduleContainer.setAttribute('aria-busy', 'true');

            try {
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: activeScheduleAbort.signal
                });
                if (!response.ok) throw new Error('Schedule response status ' + response.status);
                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                const newContainer = doc.getElementById('schedule-container');
                if (newContainer) {
                    scheduleContainer.innerHTML = newContainer.innerHTML;
                    scheduleContainer.classList.remove('tab-content-enter');
                    void scheduleContainer.offsetWidth;
                    scheduleContainer.classList.add('tab-content-enter');
                    if (window.Alpine) {
                        window.Alpine.initTree(scheduleContainer);
                    }
                }

                // Sync date input and inline clear button in header
                const parsedUrl = new URL(url, window.location.origin);
                const dateParam = parsedUrl.searchParams.get('pickup_date') || '';
                if (pickupInput) {
                    pickupInput.value = dateParam;
                }
                if (clearDateBtn) {
                    clearDateBtn.classList.toggle('hidden', !dateParam);
                }

                if (pushHistory) {
                    window.history.pushState({ url }, '', url);
                }
            } catch (err) {
                if (err.name !== 'AbortError') {
                    console.error('Schedule AJAX filter error, falling back to full navigation:', err);
                    window.location.href = url;
                }
            } finally {
                scheduleContainer.classList.remove('opacity-50', 'pointer-events-none');
                scheduleContainer.removeAttribute('aria-busy');
            }
        };

        if (pickupInput) {
            pickupInput.addEventListener('change', () => {
                const val = pickupInput.value;
                if (clearDateBtn) clearDateBtn.classList.toggle('hidden', !val);
                const url = scheduleForm.action + (val ? '?pickup_date=' + encodeURIComponent(val) : '');
                updateScheduleView(url);
            });
        }

        document.addEventListener('click', (e) => {
            const clearBtn = e.target.closest('#schedule-filter-form button[data-clear-date]');
            if (clearBtn && pickupInput) {
                e.preventDefault();
                pickupInput.value = '';
                clearBtn.classList.add('hidden');
                updateScheduleView(scheduleForm.action);
                return;
            }

            const scheduleLink = e.target.closest('#schedule-container a[href*="pickup_date"], #schedule-container a[data-schedule-filter], #schedule-container a[data-filter-reset], #schedule-container a[href$="/pickup-schedule"]');
            if (scheduleLink && scheduleContainer) {
                e.preventDefault();
                updateScheduleView(scheduleLink.href);
            }
        });

        window.addEventListener('popstate', (e) => {
            if (window.location.pathname.includes('/pickup-schedule')) {
                updateScheduleView(window.location.href, false);
            }
        });
    }

    // 3. Auto-application for workspace filters (Expenses and Supplies)
    const workspaceForms = document.querySelectorAll('form.workspace-filters:not(.report-filters)');
    workspaceForms.forEach(form => {
        let inputTimer = null;
        const submitForm = () => {
            form.submit();
        };

        form.querySelectorAll('select, input[type="date"]').forEach(el => {
            el.addEventListener('change', submitForm);
        });

        form.querySelectorAll('input:not([type="date"]):not([type="hidden"])').forEach(el => {
            el.addEventListener('input', () => {
                clearTimeout(inputTimer);
                inputTimer = setTimeout(submitForm, 400);
            });
        });
    });
});
