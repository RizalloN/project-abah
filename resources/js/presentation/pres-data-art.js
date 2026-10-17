/*
 * Source-backed data illustrations for the loan presentation.
 * Geometry is derived from the raw values; formatted source text is preserved.
 * The host presentation owns layout, reveal timing and reduced-motion styles.
 */

let instanceSequence = 0;

const h = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const finite = (value) => {
    if (typeof value !== 'number' && typeof value !== 'string') return null;
    if (typeof value === 'string' && value.trim() === '') return null;
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
};

const numberText = (value) => value === null ? 'Belum tersedia' : value.toLocaleString('id-ID', {
    maximumFractionDigits: 2,
});

const suppliedText = (text, fallback) => text === null || text === undefined || text === ''
    ? fallback
    : String(text);

const MATERIALS = {
    blue: { front: '#35b8ff', base: '#10548f', side: '#09355e', top: '#a1e7ff', line: '#71d5ff' },
    cyan: { front: '#36e0ed', base: '#087f9d', side: '#075068', top: '#a9f5ff', line: '#73ecf3' },
    silver: { front: '#b9d2e6', base: '#517896', side: '#31495e', top: '#edfaff', line: '#d8ebf7' },
    teal: { front: '#50dcbe', base: '#147e7b', side: '#0c535b', top: '#b1ffec', line: '#72efd1' },
    amber: { front: '#ffc666', base: '#ac681c', side: '#724815', top: '#ffe7ae', line: '#ffd390' },
    red: { front: '#ff858c', base: '#a73555', side: '#70223e', top: '#ffc8cb', line: '#ffadb0' },
    violet: { front: '#a6a2ff', base: '#5552a8', side: '#393b77', top: '#d8d3ff', line: '#c0bfff' },
};

const materialFor = (tone, index = 0) => {
    const aliases = { green: 'teal', orange: 'amber', navy: 'blue', danger: 'red', warning: 'amber' };
    const key = aliases[tone] || tone;
    return MATERIALS[key] || MATERIALS[['blue', 'cyan', 'teal', 'violet'][index % 4]];
};

const rowsFrom = (items) => (Array.isArray(items) ? items : [])
    .filter((item) => item && typeof item === 'object')
    .map((item, index) => ({
        ...item,
        index,
        label: suppliedText(item.label, suppliedText(item.key, 'Data')),
        amount: finite(item.value),
        material: materialFor(item.tone, index),
    }));

const figureCaption = (title, totalLabel = '') => title || totalLabel ? `
    <figcaption class="rp-art-caption">
        ${title ? `<span>${h(title)}</span>` : ''}
        ${totalLabel ? `<strong>${h(totalLabel)}</strong>` : ''}
    </figcaption>
` : '';

const portfolioLegend = (rows) => `
    <ul class="rp-art-legend">
        ${rows.map((row) => `
            <li class="rp-art-legend-item${row.amount === null ? ' is-missing' : ''}${row.amount !== null && row.amount < 0 ? ' is-negative' : ''}" style="--art-color:${row.material.front}">
                <i class="rp-art-swatch" aria-hidden="true"></i>
                <span class="rp-art-legend-copy">
                    <span>${h(row.label)}</span>
                    <strong>${h(suppliedText(row.formatted, numberText(row.amount)))}</strong>
                    ${row.amount === null ? '<small class="rp-art-status">Data belum tersedia</small>' : ''}
                    ${row.amount !== null && row.amount < 0 ? '<small class="rp-art-status">Nilai negatif</small>' : ''}
                </span>
            </li>
        `).join('')}
    </ul>
`;

const columnMaterial = (id, row) => `
    <linearGradient id="${id}-front-${row.index}" x1="0" y1="0" x2="0.2" y2="1">
        <stop offset="0" stop-color="${row.material.front}"/>
        <stop offset="1" stop-color="${row.material.base}"/>
    </linearGradient>
    <linearGradient id="${id}-side-${row.index}" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0" stop-color="${row.material.base}"/>
        <stop offset="1" stop-color="${row.material.side}"/>
    </linearGradient>
    <linearGradient id="${id}-top-${row.index}" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0" stop-color="${row.material.top}"/>
        <stop offset="1" stop-color="${row.material.front}"/>
    </linearGradient>
`;

/** An isometric portfolio with one shared, true zero baseline. */
export const portfolioSculpture = ({ items = [], title = 'Komposisi portofolio', totalLabel = '', compact = false } = {}) => {
    const rows = rowsFrom(items);
    const valid = rows.filter((row) => row.amount !== null);
    const caption = figureCaption(title, totalLabel);

    if (!valid.length) {
        return `<figure class="rp-portfolio${compact ? ' is-compact' : ''} is-empty">${caption}<p class="rp-art-empty">Komposisi portofolio belum tersedia pada posisi ini.</p>${rows.length ? portfolioLegend(rows) : ''}</figure>`;
    }

    const id = `rp-portfolio-${++instanceSequence}`;
    const maximum = Math.max(0, ...valid.map((row) => row.amount));
    const width = Math.max(800, rows.length * 155 + 180);
    const base = 352;
    const columnWidth = rows.length <= 3 ? 96 : 82;
    const depth = 42;
    const rise = 24;
    const availableWidth = width - 260 - depth;
    const step = rows.length > 1 ? availableWidth / (rows.length - 1) : 0;
    const left = rows.length === 1 ? (width - columnWidth - depth) / 2 : 115;
    const floorLeft = 54;
    const floorRight = width - 160;
    const floorFront = 390;
    const floorBack = 300;
    const floorDepth = 144;

    const grid = Array.from({ length: 7 }, (_, index) => {
        const fraction = index / 6;
        const x = floorLeft + (floorRight - floorLeft) * fraction;
        return `<path d="M${x} ${floorFront}l${floorDepth} ${floorBack - floorFront}"/>`;
    }).join('') + Array.from({ length: 4 }, (_, index) => {
        const fraction = index / 3;
        const x = floorLeft + floorDepth * fraction;
        const y = floorFront + (floorBack - floorFront) * fraction;
        return `<path d="M${x} ${y}H${floorRight + floorDepth * fraction}"/>`;
    }).join('');

    const columns = rows.map((row, index) => {
        const x = left + index * step;
        const height = row.amount !== null && row.amount > 0 && maximum > 0 ? (row.amount / maximum) * 238 : 0;
        const top = base - height;
        const center = x + (columnWidth + depth) / 2;
        const value = suppliedText(row.formatted, numberText(row.amount));
        const labelY = height > 0 ? top - rise - 18 : base - 35;
        const geometry = height > 0 ? `
            <g class="rp-art-column" data-art-bar style="--bar-order:${index}">
                <path class="rp-art-plane rp-art-plane-front" d="M${x} ${top}h${columnWidth}V${base}h-${columnWidth}Z" fill="url(#${id}-front-${index})"/>
                <path class="rp-art-plane rp-art-plane-side" d="M${x + columnWidth} ${top}l${depth} -${rise}V${base - rise}l-${depth} ${rise}Z" fill="url(#${id}-side-${index})"/>
                <path class="rp-art-plane rp-art-plane-top" d="M${x} ${top}l${depth} -${rise}h${columnWidth}l-${depth} ${rise}Z" fill="url(#${id}-top-${index})"/>
                <path d="M${x} ${top}h${columnWidth}V${base}" fill="none" stroke="${row.material.line}" stroke-opacity=".7"/>
                <path d="M${x + 9} ${top + 7}V${base - 5}" fill="none" stroke="${row.material.top}" stroke-opacity=".28"/>
            </g>
        ` : `
            <path class="rp-art-zero-marker${row.amount === null ? ' is-missing' : ''}${row.amount !== null && row.amount < 0 ? ' is-negative' : ''}" d="M${x} ${base}h${columnWidth}" fill="none" stroke="${row.amount !== null && row.amount < 0 ? MATERIALS.red.front : row.material.line}" stroke-width="2"${row.amount === null ? ' stroke-dasharray="4 5"' : ''}/>
        `;

        return `
            <g class="rp-art-segment" data-art-key="${h(row.key || index)}">
                ${geometry}
                <text class="rp-art-column-value" x="${center}" y="${labelY}" text-anchor="middle" fill="#e7f6ff">${h(value)}</text>
                <text class="rp-art-column-label" x="${center}" y="426" text-anchor="middle" fill="#aec5dc">${h(row.label)}</text>
            </g>
        `;
    }).join('');

    const notes = [];
    if (valid.some((row) => row.amount < 0)) notes.push('Nilai negatif tetap dicantumkan dan tidak digambarkan sebagai kolom positif.');
    if (maximum === 0 && valid.every((row) => row.amount === 0)) notes.push('Seluruh nilai yang tersedia bernilai nol.');

    return `
        <figure class="rp-portfolio${compact ? ' is-compact' : ''}">
            ${caption}
            <svg class="rp-art-canvas rp-portfolio-canvas" viewBox="0 0 ${width} 454" role="img" aria-labelledby="${id}-title ${id}-desc" xmlns="http://www.w3.org/2000/svg">
                <title id="${id}-title">${h(title || 'Komposisi portofolio')}</title>
                <desc id="${id}-desc">${h(rows.map((row) => `${row.label}: ${suppliedText(row.formatted, numberText(row.amount))}`).join('; '))}. Tinggi kolom sebanding dengan nilai positif pada satu garis nol; nilai nol dan data belum tersedia dibedakan pada keterangan.</desc>
                <defs>
                    <linearGradient id="${id}-floor" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" stop-color="#17618a" stop-opacity=".08"/>
                        <stop offset="1" stop-color="#36c7e9" stop-opacity=".12"/>
                    </linearGradient>
                    ${rows.map((row) => columnMaterial(id, row)).join('')}
                </defs>
                <g class="rp-art-stage" aria-hidden="true">
                    <path d="M${floorLeft} ${floorFront}H${floorRight}l${floorDepth} ${floorBack - floorFront}H${floorLeft + floorDepth}Z" fill="url(#${id}-floor)"/>
                    <g class="rp-art-grid" fill="none" stroke="#6fc8f5" stroke-opacity=".15" stroke-width="1">${grid}</g>
                    <path class="rp-art-zero" d="M${left - 32} ${base}H${width - 74}" fill="none" stroke="#8dcce8" stroke-opacity=".38"/>
                    <text x="${left - 42}" y="${base + 4}" text-anchor="end" fill="#91adc6" font-size="13">0</text>
                </g>
                ${columns}
            </svg>
            ${portfolioLegend(rows)}
            ${notes.length ? `<p class="rp-art-note">${h(notes.join(' '))}</p>` : ''}
        </figure>
    `;
};

const ratioState = (row) => {
    const ratio = finite(row.ratio);
    const valid = ratio !== null && ratio >= 0 && ratio <= 100;
    const exactRatio = ratio === null ? 'Belum tersedia' : `${ratio.toLocaleString('id-ID', { maximumSignificantDigits: 21 })}%`;
    return {
        ratio,
        valid,
        text: suppliedText(row.ratioFormatted, exactRatio),
        status: ratio === null ? 'Rasio belum tersedia' : (!valid ? 'Rasio di luar skala 0–100%' : ''),
    };
};

const ringPair = (rows, id, pairIndex) => {
    const centerX = 250;
    const centerY = 205;
    const radii = [144, 107];
    const labels = rows.map((row) => `${row.label}: ${ratioState(row).text}`).join('; ');

    return `
        <svg class="rp-art-canvas rp-lens-orbit" viewBox="0 0 500 410" role="img" aria-labelledby="${id}-pair-${pairIndex}-title ${id}-pair-${pairIndex}-desc" xmlns="http://www.w3.org/2000/svg">
            <title id="${id}-pair-${pairIndex}-title">${h(labels)}</title>
            <desc id="${id}-pair-${pairIndex}-desc">Panjang busur menunjukkan rasio pada skala 0 sampai 100 persen. Tiap cincin memiliki skala sendiri; rasio tidak dijumlahkan. ${h(rows.filter((row) => !ratioState(row).valid).map((row) => `${row.label}: ${ratioState(row).status}`).join('; '))}</desc>
            <defs>
                ${rows.map((row, index) => `<linearGradient id="${id}-ring-${pairIndex}-${index}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="${row.material.top}"/><stop offset="1" stop-color="${row.material.front}"/></linearGradient>`).join('')}
            </defs>
            <g class="rp-art-ring-guides" fill="none" stroke="#75b8d8" stroke-opacity=".22" aria-hidden="true">
                <circle cx="${centerX}" cy="${centerY}" r="169" stroke-dasharray="1 9"/>
                <path d="M250 28v14M250 368v14M73 205h14M413 205h14"/>
            </g>
            <g class="rp-art-ring-scale" fill="#9cbbd3" text-anchor="middle" font-size="11" aria-hidden="true">
                <text x="250" y="18">0 / 100%</text>
                <text x="453" y="209">25%</text>
                <text x="250" y="402">50%</text>
                <text x="47" y="209">75%</text>
            </g>
            ${rows.map((row, index) => {
                const state = ratioState(row);
                const radius = radii[index];
                const length = 2 * Math.PI * radius;
                const arc = state.valid ? length * (state.ratio / 100) : 0;
                return `
                    <circle class="rp-art-ring-track${state.valid ? '' : ' is-missing'}" cx="${centerX}" cy="${centerY}" r="${radius}" fill="none" stroke="#71a9cc" stroke-opacity="${state.valid ? '.17' : '.1'}" stroke-width="17"${state.valid ? '' : ' stroke-dasharray="3 7"'}/>
                    ${state.valid ? `<circle class="rp-art-ring" data-art-ring style="--ring-order:${index};--ring-length:${length};--ring-offset:${length - arc}" cx="${centerX}" cy="${centerY}" r="${radius}" fill="none" stroke="url(#${id}-ring-${pairIndex}-${index})" stroke-width="17" stroke-linecap="butt" stroke-dasharray="${length} ${length}" stroke-dashoffset="${length - arc}" transform="rotate(-90 ${centerX} ${centerY})"/>` : ''}
                `;
            }).join('')}
            <text class="rp-art-ring-label" x="250" y="202" text-anchor="middle" fill="#e4f4ff">Rasio kredit</text>
            <text class="rp-art-ring-subtitle" x="250" y="226" text-anchor="middle" fill="#9cbbd3">skala 0–100%</text>
        </svg>
    `;
};

/** Concentric, independently calibrated quality ratios; no enlarged arcs. */
export const qualityLens = ({ items = [], title = 'Kualitas kredit' } = {}) => {
    const rows = rowsFrom(items);
    const id = `rp-lens-${++instanceSequence}`;
    const hasData = rows.some((row) => row.amount !== null || ratioState(row).ratio !== null);

    if (!hasData) {
        return `<figure class="rp-lens is-empty">${figureCaption(title)}<p class="rp-art-empty">Data kualitas kredit belum tersedia pada posisi ini.</p></figure>`;
    }

    const pairs = [];
    for (let index = 0; index < rows.length; index += 2) pairs.push(rows.slice(index, index + 2));

    return `
        <figure class="rp-lens">
            ${figureCaption(title)}
            <div class="rp-lens-layout">
                <div class="rp-lens-orbits">${pairs.map((pair, index) => ringPair(pair, id, index)).join('')}</div>
                <ul class="rp-art-legend rp-lens-metrics">
                    ${rows.map((row) => {
                        const state = ratioState(row);
                        return `
                            <li class="rp-art-legend-item rp-lens-metric${state.valid ? '' : ' is-missing'}" style="--art-color:${row.material.front}">
                                <i class="rp-art-swatch" aria-hidden="true"></i>
                                <span class="rp-art-legend-copy">
                                    <span>${h(row.label)}</span>
                                    <strong class="rp-lens-ratio">${h(state.text)}</strong>
                                    <small class="rp-lens-amount">${h(suppliedText(row.formatted, numberText(row.amount)))}</small>
                                    ${state.status ? `<small class="rp-art-status">${h(state.status)}</small>` : ''}
                                </span>
                            </li>
                        `;
                    }).join('')}
                </ul>
            </div>
            <p class="rp-art-note">Setiap rasio memakai skala 0–100% tersendiri.</p>
        </figure>
    `;
};
