/*
 * Atlas parts for the Wilayah segments: one visual language for "a part of a whole".
 *
 *   vessels      glass cylinders: height is the whole (industry, potential), the
 *                liquid is our part (BRI, customers); the fill is the share
 *   slope        share then and now, one line per product, drawn in on arrival
 *   coverage     one slab: potential, the customers inside it, the gap beside it
 *   opportunity  units as spheres on penetration x potential, size = untapped gap;
 *                the large-potential, low-penetration quadrant is the priority
 *   skyline      sectors as lit prisms, height = potential, fill = penetration
 *   ledger       units x sectors, each cell coloured by penetration
 *   disclosure   the exact table, folded away under the visual
 *
 * Everything is real markup from real figures. Motion is entrance only (the
 * tile's reveal), never looping; hover and focus swap a readout in place.
 */

import { h, count, percent } from './format.js';
import { penetrationCss } from './territory-data.js';

/** A missing source value (null, empty) is not a zero. */
const finite = (value) => value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value));
const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

/* ------------------------------------------------------------------ vessels -- */

/**
 * items: [{ key, label, sub, total, part, ratio, ratioLabel, detail, color, selected, dim }]
 * ratio is a percentage; above 100 the liquid fills and a crown marks the overflow.
 */
export const vessels = ({ items, caption = '', ratioName = 'pangsa', readout = true }) => {
    const max = Math.max(1e-9, ...items.map((item) => Number(item.total) || 0));
    const selected = items.find((item) => item.selected) || items[0];
    return `
        <figure class="sg-vessels${items.some((item) => item.selected) ? ' has-selection' : ''}" data-sg-vessels style="--n:${items.length}">
            <ol class="sg-vessels__row" aria-label="${h(caption)}">
                ${items.map((item, index) => {
                    const height = finite(item.total) ? clamp((Number(item.total) / max), 0.16, 1) : 0.16;
                    const ratio = finite(item.ratio) ? Number(item.ratio) : 0;
                    return `<li class="sg-vessel${item.selected ? ' is-selected' : ''}${item.dim ? ' is-dim' : ''}${ratio > 100 ? ' is-over' : ''}"
                        style="--i:${index};--h:${height.toFixed(4)};--f:${clamp(ratio / 100, 0, 1).toFixed(4)};--c:${h(item.color || '#0058d0')}"
                        tabindex="0" data-vessel="${h(item.key)}" aria-label="${h(`${item.label}: ${ratioName} ${item.ratioLabel}. ${item.detail || ''}`)}">
                        <p class="sg-vessel__value"><strong>${h(item.ratioLabel)}</strong></p>
                        <div class="sg-vessel__body" aria-hidden="true">
                            <span class="sg-vessel__tank"><span class="sg-vessel__liquid"><i></i></span></span>
                            <span class="sg-vessel__glass"></span>
                        </div>
                        <p class="sg-vessel__label">${h(item.label)}${item.sub ? `<small>${h(item.sub)}</small>` : ''}</p>
                    </li>`;
                }).join('')}
            </ol>
            <div class="sg-vessels__floor" aria-hidden="true"></div>
            ${readout && selected ? `<figcaption class="sg-vessels__readout" data-vessel-readout aria-live="polite">${readoutHtml(selected, ratioName)}</figcaption>` : ''}
        </figure>`;
};

const readoutHtml = (item, ratioName) => `<strong>${h(item.label)}</strong><span>${h(ratioName)} ${h(item.ratioLabel)}</span>${item.detail ? `<small>${h(item.detail)}</small>` : ''}`;

/* -------------------------------------------------------------------- slope -- */

/** items: [{ key, label, prev, now, prevLabel, nowLabel, deltaLabel, color, total }] (percentages). */
export const slope = ({ items, from, to, caption = '' }) => {
    const rows = items.filter((item) => finite(item.prev) && finite(item.now));
    if (!rows.length) return '';
    const W = 560;
    const H = Math.max(260, rows.length * 46 + 80);
    const top = 44;
    const bottom = H - 26;
    const values = rows.flatMap((item) => [Number(item.prev), Number(item.now)]);
    let min = Math.min(...values);
    let max = Math.max(...values);
    const pad = Math.max(1, (max - min) * 0.12);
    min -= pad;
    max += pad;
    const y = (value) => top + (1 - (value - min) / Math.max(1e-9, max - min)) * (bottom - top);
    const left = 92;
    const right = W - 236;
    // Labels never collide: spread them 22px apart, keeping their order.
    const spread = (list) => {
        const sorted = [...list].sort((a, b) => a.y - b.y);
        for (let i = 1; i < sorted.length; i += 1) sorted[i].y = Math.max(sorted[i].y, sorted[i - 1].y + 22);
        const overflow = sorted.length ? sorted[sorted.length - 1].y - bottom : 0;
        if (overflow > 0) sorted.forEach((item) => { item.y -= overflow; });
        return list;
    };
    const leftLabels = spread(rows.map((item) => ({ item, y: y(Number(item.prev)) })));
    const rightLabels = spread(rows.map((item) => ({ item, y: y(Number(item.now)) })));
    return `
        <figure class="sg-slope" data-sg-slope>
            <svg viewBox="0 0 ${W} ${H}" role="img" aria-label="${h(caption)}">
                <text class="sg-slope__period" x="${left}" y="18" text-anchor="middle">${h(from)}</text>
                <text class="sg-slope__period" x="${right}" y="18" text-anchor="middle">${h(to)}</text>
                <line class="sg-slope__axis" x1="${left}" x2="${left}" y1="${top - 12}" y2="${bottom + 8}"/>
                <line class="sg-slope__axis" x1="${right}" x2="${right}" y1="${top - 12}" y2="${bottom + 8}"/>
                ${rows.map((item, index) => {
                    const up = Number(item.now) >= Number(item.prev);
                    const l = leftLabels[index];
                    const r = rightLabels[index];
                    return `<g class="sg-slope__row is-${up ? 'up' : 'down'}${item.total ? ' is-total' : ''}" style="--i:${index};--c:${h(item.color || '#0058d0')}" tabindex="0" aria-label="${h(`${item.label}: ${item.prevLabel} ke ${item.nowLabel}, ${item.deltaLabel}`)}">
                        <path class="sg-slope__line" d="M${left},${y(Number(item.prev)).toFixed(1)} L${right},${y(Number(item.now)).toFixed(1)}" pathLength="1"/>
                        <circle class="sg-slope__dot" cx="${left}" cy="${y(Number(item.prev)).toFixed(1)}" r="4.5"/>
                        <circle class="sg-slope__dot is-now" cx="${right}" cy="${y(Number(item.now)).toFixed(1)}" r="6"/>
                        <text class="sg-slope__label" x="${left - 14}" y="${(l.y + 4).toFixed(1)}" text-anchor="end">${h(item.prevLabel)}</text>
                        <text class="sg-slope__label is-now" x="${right + 14}" y="${(r.y + 4).toFixed(1)}"><tspan class="sg-slope__name">${h(item.label)}</tspan> ${h(item.nowLabel)} <tspan class="sg-slope__delta">${h(item.deltaLabel)}</tspan></text>
                    </g>`;
                }).join('')}
            </svg>
        </figure>`;
};

/* ----------------------------------------------------------------- coverage -- */

/** One slab: potential, the customers inside it, and the untapped remainder. */
export const coverage = ({ potential, existing, partName = 'Nasabah BRI', wholeName = 'Potensi', gapName = 'Belum tergarap' }) => {
    const ratio = potential > 0 ? existing / potential : 0;
    const fill = clamp(ratio, 0, 1);
    return `
        <figure class="sg-coverage${ratio > 1 ? ' is-over' : ''}" style="--f:${fill.toFixed(4)};--c:${penetrationCss(ratio * 100)}">
            <div class="sg-coverage__slab" aria-hidden="true">
                <span class="sg-coverage__top"></span>
                <span class="sg-coverage__front"><i class="sg-coverage__fill"></i></span>
                <span class="sg-coverage__side"></span>
            </div>
            <figcaption class="sg-coverage__key">
                <span class="is-part"><i aria-hidden="true"></i>${h(partName)}<strong data-sg-count>${count(existing)}</strong></span>
                <span class="is-gap"><i aria-hidden="true"></i>${h(gapName)}<strong data-sg-count>${count(Math.max(0, potential - existing))}</strong></span>
                <span class="is-whole">${h(wholeName)}<strong data-sg-count>${count(potential)}</strong></span>
            </figcaption>
        </figure>`;
};

/* -------------------------------------------------------------- opportunity -- */

/** rows: [{ key, name, sub, potential, existing, penetration, gap }], average: the scope's penetration. */
export const opportunity = ({ rows, average, caption = '' }) => {
    const points = rows.filter((row) => row.potential > 0 && finite(row.penetration));
    if (!points.length) return '';
    const maxPotential = Math.max(...points.map((row) => row.potential));
    const roughStep = maxPotential * 1.12 / 4;
    const stepBase = 10 ** Math.floor(Math.log10(roughStep));
    const yStep = ([1, 2, 2.5, 5, 10].find((step) => step >= roughStep / stepBase) || 10) * stepBase;
    const yMax = Math.ceil(maxPotential * 1.12 / yStep) * yStep;
    const yTicks = Array.from({ length: Math.round(yMax / yStep) + 1 }, (_, index) => index * yStep);
    const maxGap = Math.max(1, ...points.map((row) => row.gap));
    const highest = Math.max(...points.map((row) => row.penetration));
    const xMax = clamp(Math.ceil(Math.max(100, Math.min(highest, 200)) / 20) * 20, 100, 200);
    const potentials = points.map((row) => row.potential).sort((a, b) => a - b);
    const median = potentials[Math.floor(potentials.length / 2)];
    const avg = finite(average) ? Number(average) : 50;
    const xPos = (value) => clamp(value / xMax, 0, 1) * 100;
    const yPos = (value) => (1 - value / yMax) * 100;
    const priority = points.filter((row) => row.penetration < avg && row.potential >= median).sort((a, b) => b.gap - a.gap);
    const ranked = (priority.length ? priority : [...points].sort((a, b) => b.gap - a.gap)).slice(0, 6);
    const priorityKeys = new Set(priority.map((row) => row.key));
    const byGap = [...points].sort((a, b) => b.gap - a.gap);
    const ticks = Array.from({ length: xMax / 20 + 1 }, (_, index) => index * 20).filter((tick) => tick % (xMax > 120 ? 40 : 20) === 0);
    return `
        <div class="sg-opportunity" data-sg-opportunity>
            <figure class="sg-opportunity__plot" style="--avg:${xPos(avg).toFixed(2)}%;--median:${yPos(median).toFixed(2)}%">
                <div class="sg-opportunity__chart">
                <div class="sg-opportunity__field" style="--grid-y:${(100 / (yTicks.length - 1)).toFixed(4)}%" role="group" aria-label="${h(caption)}. Sumbu X: penetrasi nasabah BRI dalam persen. Sumbu Y: jumlah potensi nasabah. Ukuran bola: jumlah nasabah belum tergarap.">
                    <div class="sg-opportunity__y" aria-hidden="true">${yTicks.map((tick) => `<span style="--y:${yPos(tick).toFixed(2)}%">${count(tick)}</span>`).join('')}</div>
                    <span class="sg-opportunity__zone" aria-hidden="true"><small>Prioritas garap</small></span>
                    <span class="sg-opportunity__avg" aria-hidden="true"><small>Rata-rata ${percent(avg, 1)}</small></span>
                    <span class="sg-opportunity__median" aria-hidden="true"></span>
                    ${byGap.map((row, index) => {
                        const size = 12 + Math.sqrt(row.gap / maxGap) * 46;
                        return `<button type="button" class="sg-orb${priorityKeys.has(row.key) ? ' is-priority' : ''}${row.penetration > xMax ? ' is-clipped' : ''}"
                            style="--x:${xPos(row.penetration).toFixed(2)}%;--y:${yPos(row.potential).toFixed(2)}%;--s:${size.toFixed(1)}px;--c:${penetrationCss(row.penetration)};--i:${Math.min(index, 40)}"
                            data-motion-bubble data-orb="${h(row.key)}" data-name="${h(row.name)}" data-sub="${h(row.sub || '')}" data-pot="${count(row.potential)}" data-ex="${count(row.existing)}" data-pen="${percent(row.penetration, 1)}" data-gap="${count(row.gap)}"
                            aria-label="${h(`${row.name}: potensi ${count(row.potential)}, nasabah ${count(row.existing)}, penetrasi ${percent(row.penetration, 1)}, belum tergarap ${count(row.gap)}`)}"><span aria-hidden="true"></span></button>`;
                    }).join('')}
                </div>
                <div class="sg-opportunity__x" aria-hidden="true">${ticks.map((tick) => `<span style="--x:${xPos(tick).toFixed(2)}%">${tick}%</span>`).join('')}</div>
                <p class="sg-opportunity__axis-x">Sumbu X <strong>Penetrasi nasabah BRI (%)</strong><small>Semakin ke kanan, semakin tinggi penetrasi</small></p>
                <p class="sg-opportunity__axis-y">Sumbu Y <strong>Potensi nasabah (jumlah)</strong></p>
                </div>
                <div class="sg-opportunity__guide"><span><i aria-hidden="true"></i><strong>Ukuran bola</strong> Jumlah nasabah yang belum tergarap</span><span><strong>Kiri atas</strong> Potensi besar, penetrasi masih rendah</span></div>
                <figcaption class="sg-opportunity__readout" data-orb-readout aria-live="polite">
                    <small>Arahkan ke bola untuk membaca unitnya</small>
                    <strong>${h(ranked[0]?.name || '')}</strong>
                    <span>${ranked[0] ? `Potensi ${count(ranked[0].potential)} &middot; Nasabah ${count(ranked[0].existing)} &middot; ${percent(ranked[0].penetration, 1)}` : ''}</span>
                </figcaption>
            </figure>
            <ol class="sg-opportunity__list" aria-label="Unit dengan potensi belum tergarap terbesar">
                ${ranked.map((row, index) => `<li style="--i:${index};--c:${penetrationCss(row.penetration)}" data-orb-link="${h(row.key)}" tabindex="0">
                    <span class="sg-opportunity__rank">${index + 1}</span>
                    <span class="sg-opportunity__name">${h(row.name)}${row.sub ? `<small>${h(row.sub)}</small>` : ''}</span>
                    <span class="sg-opportunity__gap"><strong>${count(row.gap)}</strong><small>belum tergarap &middot; ${percent(row.penetration, 1)}</small></span>
                </li>`).join('')}
            </ol>
        </div>`;
};

/* ------------------------------------------------------------------ skyline -- */

/** sectors: [{ key, label, color, potential, existing, penetration }]; clicking a prism chooses that sector. */
export const skyline = ({ sectors, selected, caption = '' }) => {
    const max = Math.max(1, ...sectors.map((sector) => sector.potential));
    return `
        <figure class="sg-skyline" data-sg-skyline>
            <div class="sg-skyline__row" role="tablist" aria-label="${h(caption)}">
                ${sectors.map((sector, index) => {
                    const height = clamp(sector.potential / max, 0.035, 1);
                    const ratio = finite(sector.penetration) ? Number(sector.penetration) : 0;
                    return `<button type="button" role="tab" class="sg-prism${ratio > 100 ? ' is-over' : ''}" aria-selected="${sector.key === selected}" data-sector="${h(sector.key)}"
                        style="--i:${index};--h:${height.toFixed(4)};--f:${clamp(ratio / 100, 0, 1).toFixed(4)};--c:${h(sector.color)}"
                        aria-label="${h(`${sector.label}: potensi ${count(sector.potential)}, debitur ${count(sector.existing)}, penetrasi ${percent(sector.penetration, 1)}`)}">
                        <span class="sg-prism__value">${percent(sector.penetration, 1)}</span>
                        <span class="sg-prism__solid" aria-hidden="true">
                            <i class="sg-prism__top"></i><i class="sg-prism__front"><b></b></i><i class="sg-prism__side"><b></b></i>
                        </span>
                        <span class="sg-prism__label">${h(sector.label)}<small>${count(sector.potential)}</small></span>
                    </button>`;
                }).join('')}
            </div>
            <figcaption class="sg-skyline__key"><span class="is-whole">Tinggi: potensi debitur</span><span class="is-part">Isi: debitur BRI</span></figcaption>
        </figure>`;
};

/* ------------------------------------------------------------------- ledger -- */

/** rows: [{ label, sub, cells: { sectorKey: { penetration, potential, existing } } }] */
export const ledger = ({ rows, sectors, selected, caption = '', headLabel = 'Unit', bounded = false }) => `
    <div class="sg-ledger-wrap" tabindex="0" role="region" aria-label="${h(caption)}">
        <table class="sg-ledger">
            <caption class="kn-sr">${h(caption)}</caption>
            <thead><tr><th scope="col">${h(headLabel)}</th>${sectors.map((sector) => `<th scope="col" class="${sector.key === selected ? 'is-current' : ''}"><button type="button" data-sector="${h(sector.key)}" style="--c:${h(sector.color)}">${h(sector.label)}</button></th>`).join('')}</tr></thead>
            <tbody>${rows.map((row, r) => `<tr style="--r:${Math.min(r, 24)}"><th scope="row">${h(row.label)}${row.sub ? `<small>${h(row.sub)}</small>` : ''}</th>${sectors.map((sector, c) => {
                const cell = row.cells[sector.key];
                if (!cell || !(cell.potential > 0)) return `<td class="is-empty" style="--col:${c}"><span>-</span></td>`;
                return `<td class="${sector.key === selected ? 'is-current' : ''}" style="--col:${c};--tone:${penetrationCss(cell.penetration)}" title="${h(`${row.label}, ${sector.label}: ${count(cell.existing)} dari ${count(cell.potential)}, ${percent(cell.penetration, 1)}${cell.normalization_applied ? `. Rasio sumber ${percent(cell.penetration_raw, 2)}, tampilan dinormalisasi maksimum 100%.` : ''}`)}"><span>${cell.penetration > 300 ? '300%+' : percent(cell.penetration, 0)}</span></td>`;
            }).join('')}</tr>`).join('')}</tbody>
        </table>
    </div>
    <p class="sg-ledger__ramp" aria-hidden="true"><span>0%</span><i></i><span>${bounded ? '100%' : '100%+'}</span></p>`;

/* --------------------------------------------------------------- disclosure -- */

export const disclosure = (title, body) => `
    <details class="sg-disclosure">
        <summary><span>${h(title)}</span><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m5 8 5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></summary>
        <div class="sg-disclosure__body">${body}</div>
    </details>`;

/* --------------------------------------------------------------------- bind -- */

/** Hover and focus swap the readouts in place; nothing moves. */
export const bindAtlas = (root) => {
    root.querySelectorAll('[data-sg-vessels]:not([data-bound])').forEach((figure) => {
        figure.dataset.bound = '1';
        const readout = figure.querySelector('[data-vessel-readout]');
        if (!readout) return;
        const initial = readout.innerHTML;
        const show = (vessel) => {
            if (!vessel) { readout.innerHTML = initial; return; }
            const [title, detail] = vessel.getAttribute('aria-label').split('. ');
            const [label, ratio] = title.split(': ');
            readout.innerHTML = `<strong>${h(label)}</strong><span>${h(ratio)}</span>${detail ? `<small>${h(detail)}</small>` : ''}`;
        };
        figure.querySelectorAll('[data-vessel]').forEach((vessel) => {
            vessel.addEventListener('pointerenter', () => show(vessel));
            vessel.addEventListener('focus', () => show(vessel));
        });
        figure.addEventListener('pointerleave', () => show(null));
    });
    root.querySelectorAll('[data-sg-opportunity]:not([data-bound])').forEach((host) => {
        host.dataset.bound = '1';
        const readout = host.querySelector('[data-orb-readout]');
        const show = (orb) => {
            host.querySelectorAll('.sg-orb.is-focus, [data-orb-link].is-focus').forEach((node) => node.classList.remove('is-focus'));
            if (!orb) return;
            orb.classList.add('is-focus');
            host.querySelector(`[data-orb-link="${CSS.escape(orb.dataset.orb)}"]`)?.classList.add('is-focus');
            readout.innerHTML = `<small>${h(orb.dataset.sub || 'Unit')}</small><strong>${h(orb.dataset.name)}</strong><span>Potensi ${h(orb.dataset.pot)} &middot; Nasabah ${h(orb.dataset.ex)} &middot; Penetrasi ${h(orb.dataset.pen)} &middot; Belum tergarap ${h(orb.dataset.gap)}</span>`;
        };
        host.querySelectorAll('.sg-orb').forEach((orb) => {
            orb.addEventListener('pointerenter', () => show(orb));
            orb.addEventListener('focus', () => show(orb));
        });
        host.querySelectorAll('[data-orb-link]').forEach((item) => {
            const orb = host.querySelector(`.sg-orb[data-orb="${CSS.escape(item.dataset.orbLink)}"]`);
            item.addEventListener('pointerenter', () => show(orb));
            item.addEventListener('focus', () => show(orb));
        });
        host.addEventListener('pointerleave', () => show(null));
    });
};
