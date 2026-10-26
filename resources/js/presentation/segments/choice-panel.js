import { h } from './format.js';

/** An inline, searchable choice surface. Its value is still a normal form field. */
export const choicePanel = ({ name, label }) => `<div data-choice-field="${h(name)}">
    <input type="hidden" name="${h(name)}" value="all">
    <details class="sg-choice-panel">
        <summary><span>${h(label)}</span><strong data-choice-label>Menyiapkan pilihan</strong><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" stroke-width="1.6"/></svg></summary>
        <div class="sg-choice-panel__body"><label>Cari ${h(label.toLowerCase())}<input type="search" data-choice-search autocomplete="off" placeholder="Ketik nama ${h(label.toLowerCase())}"></label>
            <div class="sg-choice-strip" data-choice-options role="group" aria-label="Pilihan ${h(label.toLowerCase())}"></div><p class="sg-choice-panel__status" data-choice-status role="status"></p></div>
    </details></div>`;

export const bindChoicePanel = (host, onChange) => {
    const details = host.querySelector('details');
    const summary = host.querySelector('summary');
    const field = host.querySelector('input[type="hidden"]');
    const search = host.querySelector('[data-choice-search]');
    const results = host.querySelector('[data-choice-options]');
    const caption = host.querySelector('[data-choice-label]');
    const status = host.querySelector('[data-choice-status]');
    let options = [];
    let disabled = true;
    const paint = () => {
        const term = search.value.trim().toLocaleLowerCase('id-ID');
        const matches = options.filter((option) => option.label.toLocaleLowerCase('id-ID').includes(term));
        results.innerHTML = matches.slice(0, 60).map((option) => `<button type="button" data-choice-value="${h(option.value)}" aria-pressed="${option.value === field.value}" ${disabled ? 'disabled' : ''}>${h(option.label)}</button>`).join('');
        status.textContent = !matches.length ? 'Tidak ada pilihan yang cocok.' : matches.length > 60 ? `${matches.length} pilihan. Ketik nama untuk mempersempit hasil.` : `${matches.length} pilihan tersedia.`;
        caption.textContent = options.find((option) => option.value === field.value)?.label || 'Menyiapkan pilihan';
        summary.setAttribute('aria-disabled', String(disabled));
        search.disabled = disabled;
    };
    summary.addEventListener('click', (event) => { if (disabled) event.preventDefault(); });
    search.addEventListener('input', paint);
    results.addEventListener('click', (event) => {
        const button = event.target.closest('[data-choice-value]');
        if (!button || disabled) return;
        field.value = button.dataset.choiceValue;
        details.open = false;
        paint();
        summary.focus({ preventScroll: true });
        onChange(field.value);
    });
    return {
        setOptions(items, value) {
            options = items.map((item) => ({ value: String(item.value), label: String(item.label) }));
            field.value = String(value);
            disabled = !options.length;
            search.value = '';
            paint();
        },
        setValue(value) { field.value = String(value); paint(); },
    };
};
