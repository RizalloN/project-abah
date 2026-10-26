/*
 * Pure state helpers for the segmented presentation launcher gate.
 *
 * Entering from the dashboard landing page (/dashboard/presentation?scope=...&periode=...)
 * carries no ?segmen= query, so the selection starts empty and the deck stays locked
 * until the presenter explicitly chooses at least one segment and starts the presentation.
 */

export const normalizeSegments = (rawSegments, order = []) => {
    const allowed = new Set(order);
    const unique = new Set();
    for (const item of rawSegments || []) {
        const key = String(item || '').trim();
        if (allowed.has(key)) unique.add(key);
    }
    return order.filter((key) => unique.has(key));
};

export const readInitialChoice = ({ search = '', scopes = [], defaultScope = '', order = [] } = {}) => {
    const params = new URLSearchParams(search);
    const validScopes = scopes.map((scope) => (typeof scope === 'string' ? scope : scope?.key)).filter(Boolean);
    const scope = [params.get('scope'), defaultScope].find((key) => validScopes.includes(key)) || validScopes[0] || '';
    const rawSegmen = params.has('segmen') ? String(params.get('segmen') || '').split(',') : [];
    const segments = normalizeSegments(rawSegmen, order);
    return {
        segments,
        scope,
        hasUrlSegments: segments.length > 0,
    };
};

export const isSameChoice = (left, right) => Boolean(
    left
    && right
    && left.scope === right.scope
    && Array.isArray(left.segments)
    && Array.isArray(right.segments)
    && left.segments.length === right.segments.length
    && left.segments.every((key, index) => key === right.segments[index]),
);

export const shouldKeepDeckOpen = ({ unlocked = false, activeChoice = null, draftChoice = null } = {}) => Boolean(
    unlocked
    && draftChoice?.segments?.length > 0
    && isSameChoice(activeChoice, draftChoice),
);

export const describeLauncherState = ({ choice, scopeLabel = '', unlocked = false, activeChoice = null } = {}) => {
    const count = choice?.segments?.length || 0;
    const detail = choice?.scope === 'area6' ? 'rincian per cabang' : 'rincian per unit';
    const scopePart = scopeLabel ? `, ${scopeLabel}` : '';

    if (count === 0) {
        return {
            canStart: false,
            keepOpen: false,
            summary: 'Pilih paling sedikit satu segmen terlebih dahulu untuk memulai presentasi.',
            nudgeMessage: 'Pilih minimal satu segmen di atas sebelum lanjut ke bawah.',
        };
    }

    const keepOpen = shouldKeepDeckOpen({ unlocked, activeChoice, draftChoice: choice });
    if (!keepOpen) {
        return {
            canStart: true,
            keepOpen: false,
            summary: `${count} segmen dipilih${scopePart}, ${detail}. Klik Mulai presentasi untuk membuka materi.`,
            nudgeMessage: 'Klik Mulai presentasi untuk membuka segmen pilihan Anda.',
        };
    }

    return {
        canStart: true,
        keepOpen: true,
        summary: `${count} segmen aktif${scopePart}, ${detail}.`,
        nudgeMessage: '',
    };
};
