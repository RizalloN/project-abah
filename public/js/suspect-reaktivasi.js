(() => {
    'use strict';
    const form = document.getElementById('suspect-filters');
    if (!form || !window.fetch || !window.AbortController) return;
    const results = document.getElementById('sr-results');
    const status = document.getElementById('sr-status');
    const error = document.getElementById('sr-error');
    const unit = document.getElementById('sr-unit');
    const frequency = document.getElementById('sr-frequency');
    const widgets = new Map();
    let openWidget = null;
    let pending = null;
    let sequence = 0;

    // Keep native selects as the GET fallback; enhance only this report's controls.
    form.querySelectorAll('select').forEach(select => {
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'sr-select-trigger';
        trigger.id = select.id + '-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-labelledby', select.id + '-label ' + select.id + '-value');
        const value = document.createElement('span');
        value.id = select.id + '-value';
        const chevron = document.createElement('i');
        chevron.className = 'fas fa-chevron-down';
        chevron.setAttribute('aria-hidden', 'true');
        trigger.append(value, chevron);
        select.after(trigger);
        select.hidden = true;
        form.querySelector(`label[for="${select.id}"]`).htmlFor = trigger.id;
        const menu = document.createElement('div');
        menu.className = 'sr-select-menu';
        menu.hidden = true;
        const search = document.createElement('input');
        search.type = 'search';
        search.className = 'sr-select-search';
        search.placeholder = 'Cari pilihan…';
        search.setAttribute('aria-label', 'Cari ' + document.getElementById(select.id + '-label').textContent);
        search.setAttribute('role', 'combobox');
        search.setAttribute('aria-autocomplete', 'list');
        search.setAttribute('aria-expanded', 'false');
        search.autocomplete = 'off';
        const list = document.createElement('ul');
        list.className = 'sr-select-list';
        list.id = select.id + '-options';
        list.setAttribute('role', 'listbox');
        list.setAttribute('aria-labelledby', select.id + '-label');
        trigger.setAttribute('aria-controls', list.id);
        search.setAttribute('aria-controls', list.id);
        const empty = document.createElement('div');
        empty.className = 'sr-select-no-match';
        empty.textContent = 'Pilihan tidak ditemukan';
        menu.append(search, list, empty);
        document.body.append(menu);
        let active = -1;
        let visible = [];
        const sync = () => { value.textContent = select.selectedOptions[0]?.textContent || 'Pilih'; };
        const close = (focus = false) => {
            menu.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            search.setAttribute('aria-expanded', 'false');
            search.removeAttribute('aria-activedescendant');
            if (openWidget === widget) openWidget = null;
            if (focus) trigger.focus();
        };
        const highlight = index => {
            active = index;
            [...list.children].forEach((item, i) => item.classList.toggle('sr-option-active', i === active));
            if (list.children[active]) {
                search.setAttribute('aria-activedescendant', list.children[active].id);
                list.children[active].scrollIntoView({ block: 'nearest' });
            } else search.removeAttribute('aria-activedescendant');
        };
        const choose = option => {
            const changed = select.value !== option.value;
            select.value = option.value;
            sync();
            close(true);
            if (changed) select.dispatchEvent(new Event('change', { bubbles: true }));
        };
        const render = () => {
            const term = search.value.trim().toLocaleLowerCase('id');
            visible = [...select.options].filter(option => !option.disabled && option.textContent.toLocaleLowerCase('id').includes(term));
            list.replaceChildren();
            visible.forEach((option, i) => {
                const item = document.createElement('li');
                item.id = list.id + '-' + i;
                item.role = 'option';
                item.setAttribute('aria-selected', String(option.selected));
                item.textContent = option.textContent;
                item.addEventListener('click', () => choose(option));
                list.append(item);
            });
            empty.hidden = visible.length > 0;
            highlight(visible.length ? Math.max(0, visible.findIndex(option => option.selected)) : -1);
        };
        const position = () => {
            const rect = trigger.getBoundingClientRect();
            const width = Math.min(Math.max(rect.width, 260), window.innerWidth - 24);
            menu.style.width = width + 'px';
            menu.style.left = Math.max(12, Math.min(rect.left, window.innerWidth - width - 12)) + 'px';
            const roomBelow = window.innerHeight - rect.bottom - 16;
            const above = roomBelow < 180 && rect.top > roomBelow;
            list.style.maxHeight = Math.max(70, Math.min(230, (above ? rect.top - 16 : roomBelow) - 66)) + 'px';
            menu.style.top = above ? 'auto' : rect.bottom + 6 + 'px';
            menu.style.bottom = above ? window.innerHeight - rect.top + 6 + 'px' : 'auto';
        };
        const open = () => {
            openWidget?.close();
            openWidget = widget;
            menu.hidden = false;
            search.value = '';
            position();
            trigger.setAttribute('aria-expanded', 'true');
            search.setAttribute('aria-expanded', 'true');
            render();
            search.focus({ preventScroll: true });
        };
        const widget = { close, sync, position, menu, trigger };
        widgets.set(select, widget);
        sync();
        trigger.addEventListener('click', () => menu.hidden ? open() : close());
        trigger.addEventListener('keydown', event => {
            if (['ArrowDown', 'ArrowUp'].includes(event.key)) { event.preventDefault(); open(); }
        });
        search.addEventListener('input', render);
        search.addEventListener('keydown', event => {
            if (event.key === 'Escape') { event.preventDefault(); close(true); }
            if (event.key === 'Tab') {
                // The popover lives at body level; continue tabbing beside its trigger.
                event.preventDefault(); close(true);
                const controls = [...form.querySelectorAll('button,a[href]')];
                controls[controls.indexOf(trigger) + (event.shiftKey ? -1 : 1)]?.focus();
            }
            if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
                event.preventDefault();
                highlight(visible.length ? (active + (event.key === 'ArrowDown' ? 1 : -1) + visible.length) % visible.length : -1);
            }
            if (event.key === 'Enter') { event.preventDefault(); if (visible[active]) choose(visible[active]); }
        });
    });
    document.addEventListener('pointerdown', event => {
        if (openWidget && !openWidget.menu.contains(event.target) && !openWidget.trigger.contains(event.target)) openWidget.close();
    });
    window.addEventListener('resize', () => openWidget?.position());
    window.addEventListener('scroll', event => {
        if (openWidget && !openWidget.menu.contains(event.target)) openWidget.close();
    }, true);

    const replaceOptions = (select, options, selected) => {
        select.replaceChildren(...options.map(option => new Option(option.label, String(option.value))));
        select.value = selected == null ? '' : String(selected);
        widgets.get(select).sync();
    };
    const filterUrl = () => {
        const url = new URL(form.action);
        new FormData(form).forEach((value, key) => { if (value !== '') url.searchParams.set(key, value); });
        return url;
    };
    const load = async (url, push = true, focusResults = false) => {
        const current = ++sequence;
        pending?.abort();
        const controller = new AbortController();
        pending = controller;
        const timeout = setTimeout(() => controller.abort(), 30000);
        error.hidden = true;
        results.setAttribute('aria-busy', 'true');
        status.textContent = 'Memperbarui ringkasan dan nominatif…';
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();
            if (current !== sequence) return;
            if (typeof data.html !== 'string' || !data.filters || !Array.isArray(data.units) || !Array.isArray(data.frequencies)) throw new Error('Respons tidak lengkap');
            results.innerHTML = data.html;
            if (focusResults) {
                const heading = document.getElementById('sr-table-title');
                heading.tabIndex = -1;
                heading.focus({ preventScroll: true });
                heading.scrollIntoView({ block: 'center' });
            }
            replaceOptions(unit, [{ value: '', label: 'Semua uker' }, ...data.units.map(label => ({ value: label, label }))], data.filters.nama_uker);
            replaceOptions(frequency, [{ value: '', label: 'Semua frekuensi' }, ...data.frequencies.map(item => ({ value: item.value, label: `${item.label} · ${item.count} rekening` }))], data.filters.reaktivasi);
            ['periode', 'cabang'].forEach(name => {
                form.elements[name].value = data.filters[name] ?? '';
                widgets.get(form.elements[name]).sync();
            });
            const canonical = new URL(url);
            Object.entries(data.filters).forEach(([key, value]) => {
                if (value === null || value === '') canonical.searchParams.delete(key);
                else canonical.searchParams.set(key, value);
            });
            if (push && canonical.href !== location.href) history.pushState(null, '', canonical);
            status.textContent = `${Number(data.accounts).toLocaleString('id-ID')} rekening ditampilkan sesuai filter.`;
        } catch (reason) {
            if (current !== sequence) return;
            error.textContent = 'Filter belum berhasil diterapkan. Hasil sebelumnya tetap ditampilkan. Periksa koneksi lalu klik Terapkan filter untuk mencoba lagi.';
            error.hidden = false;
            status.textContent = '';
        } finally {
            clearTimeout(timeout);
            if (current === sequence) { results.removeAttribute('aria-busy'); pending = null; }
        }
    };
    form.addEventListener('submit', event => { event.preventDefault(); openWidget?.close(); load(filterUrl()); });
    form.addEventListener('change', event => {
        if (['periode', 'cabang'].includes(event.target.name)) replaceOptions(unit, [{ value: '', label: 'Semua uker' }], '');
        load(filterUrl());
    });
    form.querySelector('.sr-reset').addEventListener('click', event => {
        event.preventDefault();
        // Retain the selected report period and the user's authorized branch scope.
        if (form.elements.cabang.querySelector('option[value=""]')) form.elements.cabang.value = '';
        unit.value = ''; frequency.value = '';
        widgets.forEach(widget => widget.sync());
        openWidget?.close();
        load(filterUrl());
    });
    results.addEventListener('click', event => {
        const link = event.target.closest('.sr-footer a[href]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        event.preventDefault(); load(new URL(link.href), true, true);
    });
    window.addEventListener('popstate', () => load(new URL(location.href), false));

    const dialog = document.getElementById('sr-history-dialog');
    results.addEventListener('click', event => {
        const button = event.target.closest('button.sr-history');
        if (!button) return;
        document.getElementById('sr-dialog-name').textContent = button.dataset.name + ' · ' + button.dataset.account;
        const grid = document.getElementById('sr-history-grid');
        grid.replaceChildren();
        JSON.parse(button.dataset.history).forEach(item => {
            const box = document.createElement('div'); box.className = 'sr-date-item';
            const label = document.createElement('span'); label.textContent = item.label;
            const date = document.createElement('strong'); date.textContent = item.date;
            box.append(label, date); grid.append(box);
        });
        dialog.showModal();
    });
    dialog.querySelector('.sr-close').addEventListener('click', () => dialog.close());
})();
