const clamp = (value) => Math.min(1, Math.max(0, value));

/** Read layout coordinates only while this view actually participates in layout. */
export function measureReadingBox(element) {
    if (!element?.isConnected || element.closest('[hidden], [inert], .is-panel-transition, .is-room-transition') || !element.getClientRects().length) return null;
    const width = element.offsetWidth;
    const height = element.offsetHeight;
    if (![width, height].every(Number.isFinite) || width <= 0 || height <= 0) return null;
    let top = 0;
    let left = 0;
    for (let node = element; node; node = node.offsetParent) {
        if (![node.offsetTop, node.offsetLeft].every(Number.isFinite)) return null;
        top += node.offsetTop;
        left += node.offsetLeft;
    }
    return { top, left, width, height };
}

/** Entry belongs to data and latches; passage belongs only to reversible decoration. */
export function readingFrame({ top, height, viewport, ceiling = 0, settled = false, reduced = false, focused = false, active = true }) {
    if (!active || ![top, height, viewport, ceiling].every(Number.isFinite) || height <= 0 || viewport <= 0) {
        return { enter: settled ? 1 : 0, pass: 0, phase: 'before', settled: Boolean(settled), usable: false };
    }
    const readingTop = Math.min(viewport - 1, Math.max(0, ceiling));
    const distance = Math.min(260, Math.max(64, (viewport - readingTop) * .34));
    const start = viewport - Math.min(24, (viewport - readingTop) * .05);
    const progress = clamp((start - top) / distance);
    const complete = Boolean(settled || reduced || focused || progress >= 1);
    const phase = top >= viewport ? 'before' : top + height <= readingTop ? 'after'
        : top < readingTop ? 'leave' : complete ? 'hold' : 'enter';
    return {
        enter: complete ? 1 : progress * progress * (3 - 2 * progress),
        pass: clamp((viewport - top) / (viewport + height)),
        phase,
        settled: complete,
        usable: true,
    };
}
