/* Formatting shared by the segment renderers. Server figures arrive pre-formatted; these cover axes, labels and derived values. */

export const h = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));

const number = (value, decimals) => Number(value).toLocaleString('id-ID', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

/** Rupiah in the house style: Rp14,35 T, Rp319,13 M, Rp2,96 Jt. */
export const money = (value, { signed = false } = {}) => {
    if (value === null || value === undefined || !Number.isFinite(Number(value))) return '-';
    const raw = Number(value);
    const absolute = Math.abs(raw);
    const [divisor, suffix, decimals] = absolute >= 1e12 ? [1e12, ' T', 2] : absolute >= 1e9 ? [1e9, ' M', 2] : absolute >= 1e6 ? [1e6, ' Jt', 2] : [1, '', 0];
    const sign = raw < 0 ? '-' : signed && raw > 0 ? '+' : '';
    return `${sign}Rp${number(absolute / divisor, decimals)}${suffix}`;
};

/** Axis ticks: same units, fewer digits. */
export const moneyShort = (value) => {
    const absolute = Math.abs(Number(value) || 0);
    const [divisor, suffix] = absolute >= 1e12 ? [1e12, 'T'] : absolute >= 1e9 ? [1e9, 'M'] : absolute >= 1e6 ? [1e6, 'Jt'] : [1, ''];
    return `${number(value / divisor, absolute / divisor >= 100 ? 0 : 1)}${suffix ? ` ${suffix}` : ''}`;
};

export const percent = (value, decimals = 2) => (value === null || value === undefined || !Number.isFinite(Number(value)) ? '-' : `${number(value, decimals)}%`);

export const count = (value) => (value !== null && value !== undefined && Number.isFinite(Number(value)) ? number(value, 0) : '-');

/** Up, down or flat, for a value where higher is better (invert for risk). */
export const tone = (value, { invert = false } = {}) => {
    const raw = Number(value);
    if (!Number.isFinite(raw) || raw === 0) return 'flat';
    return (raw > 0) !== invert ? 'up' : 'down';
};

export const deltaChip = (label, delta, { invert = false } = {}) => (delta
    ? `<span class="sg-delta is-${tone(delta.value, { invert })}"><small>${h(label)}</small>${h(delta.formatted)}</span>`
    : '');

/** Name tokens for matching snapshot outlets with REKAP units: "UNIT Aloon - Aloon Madiun" and "ALOON - ALOON" both become "aloonaloon". */
export const outletToken = (label) => String(label || '')
    .toLowerCase()
    .replace(/\(kantor cabang\)/g, '')
    .replace(/^\s*(unit|kcp|kc)\s+/, '')
    .replace(/\s+(kota\s+)?(madiun|magetan|ngawi|ponorogo)\s*$/, '')
    .replace(/[^a-z0-9]/g, '');
