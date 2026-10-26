/*
 * Chart parts for the segmented presentation. Everything is real markup (SVG and
 * HTML) so figures stay selectable and print; depth comes from CSS 3D.
 *
 *   donut      a ring lying in perspective: the arcs are drawn on a top layer and
 *              repeated on stacked layers below it, which read as the ring's wall
 *   timeseries lines that draw themselves in, a hover probe that reads every series
 *   rods       horizontal bars as lit rods in a groove
 *   table      rows that flip up one after another when the card arrives
 */

import { h, money, moneyShort, percent } from './format.js';

const DONUT_LAYERS = 9;
const timeseriesStore = new Map();
let chartSeed = 0;

/** items: [{ key, label, value, formatted, color, note }], center: { value, label } */
export const donut = ({ items, center, caption = '', size = 'md' }) => {
    const complete = items.every((item) => item.value !== null && item.value !== undefined && Number.isFinite(Number(item.value)) && Number(item.value) >= 0);
    const total = items.reduce((sum, item) => sum + Math.max(0, Number(item.value) || 0), 0);
    let offset = 0;
    const arcs = items.map((item) => {
        const share = complete && total > 0 ? (Math.max(0, Number(item.value) || 0) / total) * 100 : 0;
        const length = Math.max(0, share - (items.length > 1 ? 0.7 : 0));
        const arc = `<circle class="sg-donut__arc" data-key="${h(item.key)}" cx="100" cy="100" r="78" pathLength="100" style="--len:${length.toFixed(3)};--off:${(-offset).toFixed(3)};--c:${h(item.color)}" transform="rotate(-90 100 100)"/>`;
        offset += share;
        return { arc, share };
    });
    const ring = arcs.map(({ arc }) => arc).join('');
    const layers = Array.from({ length: DONUT_LAYERS }, (_, index) => `<svg class="sg-donut__layer" viewBox="0 0 200 200" style="--z:${index + 1}" aria-hidden="true">${ring}</svg>`).join('');
    return `
        <figure class="sg-donut sg-donut--${h(size)}" data-sg-donut>
            <div class="sg-donut__stage">
                <div class="sg-donut__disc">
                    ${layers}
                    <svg class="sg-donut__top" viewBox="0 0 200 200" role="img" aria-label="${h(caption || center?.label || '')}">
                        <circle class="sg-donut__track" cx="100" cy="100" r="78"/>
                        ${ring}
                        <circle class="sg-donut__sheen" cx="100" cy="100" r="78"/>
                    </svg>
                </div>
            </div>
            <figcaption>
                ${center ? `<p class="sg-donut__center"><strong data-sg-count>${h(center.value)}</strong><small>${h(center.label)}</small></p>` : ''}
                <ul class="sg-legend">
                    ${items.map((item, index) => `
                        <li data-key="${h(item.key)}" style="--c:${h(item.color)};--i:${index}" tabindex="0">
                            <i aria-hidden="true"></i>
                            <span>${h(item.label)}</span>
                            <strong>${h(item.formatted)}</strong>
                            <small>${percent(complete && total > 0 ? arcs[index].share : null)}${item.note ? ` &middot; ${h(item.note)}` : ''}</small>
                        </li>`).join('')}
                </ul>
                ${!complete ? '<p class="sg-note">Komposisi belum lengkap karena sebagian nilai sumber belum tersedia.</p>' : ''}
            </figcaption>
        </figure>`;
};

/** Pointing at a legend row lifts its arc and quiets the others. */
export const bindDonuts = (root) => {
    root.querySelectorAll('[data-sg-donut]:not([data-bound])').forEach((figure) => {
        figure.dataset.bound = '1';
        const set = (key) => {
            figure.toggleAttribute('data-focus', Boolean(key));
            figure.querySelectorAll('[data-key]').forEach((node) => node.classList.toggle('is-focus', node.dataset.key === key));
        };
        figure.querySelectorAll('.sg-legend li').forEach((row) => {
            row.addEventListener('pointerenter', () => set(row.dataset.key));
            row.addEventListener('focus', () => set(row.dataset.key));
            row.addEventListener('pointerleave', () => set(null));
            row.addEventListener('blur', () => set(null));
        });
    });
};

/* ---------------------------------------------------------------- timeseries -- */

const W = 760;
const H = 300;
const PAD = { top: 22, right: 22, bottom: 34, left: 64 };

const scaleOf = (series, labels) => {
    const values = series.flatMap((item) => item.values).filter((value) => value !== null && Number.isFinite(value));
    let min = Math.min(...values);
    let max = Math.max(...values);
    if (!values.length) { min = 0; max = 1; }
    if (min === max) { min -= Math.abs(min) * 0.1 || 1; max += Math.abs(max) * 0.1 || 1; }
    const room = (max - min) * 0.12;
    min -= room;
    max += room;
    const x = (index) => PAD.left + (labels.length <= 1 ? 0 : (index / (labels.length - 1)) * (W - PAD.left - PAD.right));
    const y = (value) => PAD.top + (1 - (value - min) / (max - min)) * (H - PAD.top - PAD.bottom);
    return { min, max, x, y };
};

const linePath = (values, scale) => {
    let path = '';
    let open = false;
    values.forEach((value, index) => {
        if (value === null || !Number.isFinite(value)) { open = false; return; }
        path += `${open ? 'L' : 'M'}${scale.x(index).toFixed(1)},${scale.y(value).toFixed(1)}`;
        open = true;
    });
    return path;
};

/** Close each available run separately so the fill cannot bridge missing source periods. */
const areaPath = (values, scale) => {
    let path = '';
    let first = null;
    let last = null;
    const close = () => {
        if (first !== null) path += `L${scale.x(last).toFixed(1)},${H - PAD.bottom}L${scale.x(first).toFixed(1)},${H - PAD.bottom}Z`;
        first = null;
    };
    values.forEach((value, index) => {
        if (value === null || !Number.isFinite(value)) { close(); return; }
        path += `${first === null ? 'M' : 'L'}${scale.x(index).toFixed(1)},${scale.y(value).toFixed(1)}`;
        if (first === null) first = index;
        last = index;
    });
    close();
    return path;
};

const plot = (state) => {
    const visible = state.series.filter((item) => !state.hidden.has(item.key));
    const scale = scaleOf(visible.length ? visible : state.series, state.labels);
    state.scale = scale;
    const format = state.unit === 'percent' ? (value) => percent(value) : moneyShort;
    const ticks = Array.from({ length: 4 }, (_, index) => scale.min + ((scale.max - scale.min) * (index + 0.5)) / 4);
    const first = visible[0];
    const area = first ? areaPath(first.values, scale) : '';
    return `
        <defs>
            <linearGradient id="${state.id}-fill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="${h(first?.color || '#0058d0')}" stop-opacity="0.28"/>
                <stop offset="1" stop-color="${h(first?.color || '#0058d0')}" stop-opacity="0"/>
            </linearGradient>
        </defs>
        <g class="sg-ts__grid">${ticks.map((tick) => `<line x1="${PAD.left}" x2="${W - PAD.right}" y1="${scale.y(tick).toFixed(1)}" y2="${scale.y(tick).toFixed(1)}"/><text x="${PAD.left - 10}" y="${(scale.y(tick) + 4).toFixed(1)}" text-anchor="end">${h(format(tick))}</text>`).join('')}</g>
        <g class="sg-ts__axis">${state.labels.map((label, index) => (index % 2 === state.labels.length % 2 || index === state.labels.length - 1 ? `<text x="${scale.x(index).toFixed(1)}" y="${H - 10}" text-anchor="middle">${h(label)}</text>` : '')).join('')}</g>
        ${area ? `<path class="sg-ts__area" d="${area}" fill="url(#${state.id}-fill)"/>` : ''}
        ${visible.map((item, index) => `<path class="sg-ts__line" d="${linePath(item.values, scale)}" pathLength="1" style="--c:${h(item.color)};--i:${index}"/>`).join('')}
        ${visible.map((item) => {
            const last = item.values.length - 1 - [...item.values].reverse().findIndex((value) => value !== null);
            return last >= 0 && last < item.values.length ? `<circle class="sg-ts__end" cx="${scale.x(last).toFixed(1)}" cy="${scale.y(item.values[last]).toFixed(1)}" r="4.5" style="--c:${h(item.color)}"/>` : '';
        }).join('')}
        <g class="sg-ts__probe" data-probe><line y1="${PAD.top}" y2="${H - PAD.bottom}"/>${visible.map((item) => `<circle r="5" data-dot="${h(item.key)}" style="--c:${h(item.color)}"/>`).join('')}</g>
        <rect class="sg-ts__hit" x="${PAD.left}" y="0" width="${W - PAD.left - PAD.right}" height="${H}"/>`;
};

/**
 * series: [{ key, label, color, values }], unit: 'money' | 'percent'.
 * shown: the series visible at first (default all); the rest wait behind the legend, so a
 * large total does not flatten its own components on a shared axis.
 */
export const timeseries = ({ title, labels, series, unit = 'money', note = '', shown = null }) => {
    chartSeed += 1;
    const id = `sg-ts-${chartSeed}`;
    const hidden = new Set(shown ? series.filter((item) => !shown.includes(item.key)).map((item) => item.key) : []);
    const state = { id, labels, series, unit, hidden };
    timeseriesStore.set(id, state);
    return `
        <figure class="sg-tile sg-ts" data-sg-ts="${id}">
            <header class="sg-tile__head">
                <h3>${h(title)}</h3>
                <div class="sg-ts__legend" role="group" aria-label="Seri ${h(title)}">
                    ${series.map((item) => `<button type="button" aria-pressed="${!hidden.has(item.key)}" data-series="${h(item.key)}" style="--c:${h(item.color)}"><i aria-hidden="true"></i>${h(item.label)}</button>`).join('')}
                </div>
            </header>
            <div class="sg-ts__plot">
                <svg viewBox="0 0 ${W} ${H}" role="img" aria-label="${h(title)}, ${h(labels[0] || '')} sampai ${h(labels[labels.length - 1] || '')}">${plot(state)}</svg>
                <div class="sg-ts__tip" data-tip hidden></div>
            </div>
            <details class="sg-ts__data"><summary>Lihat angka per periode</summary>${table({
                caption: title,
                columns: [{ label: 'Periode', cell: (row) => h(row.label) }, ...series.map((item) => ({ label: item.label, align: 'end', cell: (row) => h(unit === 'percent' ? percent(item.values[row.index]) : money(item.values[row.index])) }))],
                rows: labels.map((label, index) => ({ label, index })),
            })}</details>
            ${note ? `<figcaption class="sg-note">${h(note)}</figcaption>` : ''}
        </figure>`;
};

export const disposeCharts = (root) => root.querySelectorAll('[data-sg-ts]').forEach((figure) => timeseriesStore.delete(figure.dataset.sgTs));

export const bindTimeseries = (root) => {
    root.querySelectorAll('[data-sg-ts]:not([data-bound])').forEach((figure) => {
        figure.dataset.bound = '1';
        const state = timeseriesStore.get(figure.dataset.sgTs);
        if (!state) return;
        const svg = figure.querySelector('svg');
        const tip = figure.querySelector('[data-tip]');
        const format = state.unit === 'percent' ? (value) => percent(value) : (value) => money(value);
        figure.querySelectorAll('[data-series]').forEach((button) => button.addEventListener('click', () => {
            const key = button.dataset.series;
            if (state.hidden.has(key)) state.hidden.delete(key);
            else if (state.hidden.size < state.series.length - 1) state.hidden.add(key);
            button.setAttribute('aria-pressed', String(!state.hidden.has(key)));
            svg.innerHTML = plot(state);
            figure.classList.add('is-redrawn');
        }));
        const move = (event) => {
            const box = svg.getBoundingClientRect();
            const x = ((event.clientX - box.left) / box.width) * W;
            const span = (W - PAD.left - PAD.right) / Math.max(1, state.labels.length - 1);
            const index = Math.max(0, Math.min(state.labels.length - 1, Math.round((x - PAD.left) / span)));
            const probe = svg.querySelector('[data-probe]');
            const px = state.scale.x(index);
            probe.querySelector('line').setAttribute('x1', px);
            probe.querySelector('line').setAttribute('x2', px);
            probe.classList.add('is-on');
            const rows = state.series.filter((item) => !state.hidden.has(item.key)).map((item) => {
                const value = item.values[index];
                const dot = probe.querySelector(`[data-dot="${CSS.escape(item.key)}"]`);
                if (dot) {
                    dot.style.opacity = value === null ? '0' : '1';
                    if (value !== null) { dot.setAttribute('cx', px); dot.setAttribute('cy', state.scale.y(value)); }
                }
                return `<li style="--c:${h(item.color)}"><i></i>${h(item.label)}<strong>${value === null ? '-' : h(format(value))}</strong></li>`;
            }).join('');
            tip.innerHTML = `<small>${h(state.labels[index])}</small><ul>${rows}</ul>`;
            tip.hidden = false;
            const left = (px / W) * box.width;
            tip.style.transform = `translate(${Math.min(box.width - 190, Math.max(0, left + 14)).toFixed(0)}px, 8px)`;
        };
        svg.addEventListener('pointermove', move);
        svg.addEventListener('pointerleave', () => {
            tip.hidden = true;
            svg.querySelector('[data-probe]')?.classList.remove('is-on');
        });
    });
};

/* ---------------------------------------------------------------------- rods -- */

/** items: [{ label, value, formatted, note, color, tone }] scaled to the largest |value|. */
export const rods = (items, { signed = false } = {}) => {
    const max = Math.max(1e-9, ...items.map((item) => Math.abs(Number(item.value) || 0)));
    return `<ol class="sg-rods${signed ? ' sg-rods--signed' : ''}">${items.map((item, index) => {
        const width = (Math.abs(Number(item.value) || 0) / max) * 100;
        return `<li style="--i:${index};--w:${width.toFixed(2)}%;--c:${h(item.color || 'var(--sg-accent)')}" class="${item.tone ? `is-${h(item.tone)}` : ''}${Number(item.value) < 0 ? ' is-negative' : ''}">
            <span class="sg-rods__label">${h(item.label)}${item.note ? `<small>${h(item.note)}</small>` : ''}</span>
            <strong>${h(item.formatted)}</strong>
            <span class="sg-rods__track" aria-hidden="true"><i></i></span>
        </li>`;
    }).join('')}</ol>`;
};

/* --------------------------------------------------------------------- table -- */

/** columns: [{ label, align, cell(row) -> html, className }] */
export const table = ({ columns, rows, caption = '', footer = null, className = '' }) => `
    <div class="sg-table-wrap" tabindex="0" role="region" aria-label="${h(caption || 'Tabel data')}">
        <table class="sg-table ${h(className)}">
            ${caption ? `<caption class="kn-sr">${h(caption)}</caption>` : ''}
            <thead><tr>${columns.map((column) => `<th scope="col" class="${column.align === 'end' ? 'is-num' : ''}">${h(column.label)}</th>`).join('')}</tr></thead>
            <tbody>${rows.map((row, index) => `<tr style="--r:${Math.min(index, 20)}" class="${row.className || ''}">${columns.map((column, columnIndex) => `<${columnIndex === 0 ? 'th scope="row"' : 'td'} class="${column.align === 'end' ? 'is-num' : ''} ${column.className || ''}">${column.cell(row)}</${columnIndex === 0 ? 'th' : 'td'}>`).join('')}</tr>`).join('')}</tbody>
            ${footer ? `<tfoot><tr>${columns.map((column, columnIndex) => `<${columnIndex === 0 ? 'th scope="row"' : 'td'} class="${column.align === 'end' ? 'is-num' : ''}">${column.cell(footer)}</${columnIndex === 0 ? 'th' : 'td'}>`).join('')}</tr></tfoot>` : ''}
        </table>
    </div>`;

/** A thin meter inside a table cell: share of the scope, or a ratio on a fixed scale. */
export const meter = (value, { max = 100, color = 'var(--sg-accent)' } = {}) => `<span class="sg-meter" aria-hidden="true" style="--w:${Math.max(0, Math.min(100, (Number(value) || 0) / max * 100)).toFixed(2)}%;--c:${h(color)}"><i></i></span>`;
