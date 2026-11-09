/** Rendering fidelity is independent of chart data, motion count and input behavior. */
export function deviceProfile({ width = 1440, coarse = false, memory = 0, cores = 0, saveData = false, reduced = false } = {}) {
    const compact = width <= 760 || coarse;
    const constrained = saveData || (memory > 0 && memory <= 4) || (cores > 0 && cores <= 4);
    return {
        maxPixelRatio: constrained ? 1.25 : compact ? 1.5 : 1.75,
        maxPixels: constrained ? 1_200_000 : compact ? 1_800_000 : 4_200_000,
        shadowSize: compact || constrained ? 1024 : 2048,
        dustCount: constrained ? 240 : compact ? 420 : 900,
        activeFps: 60,
        ambientFps: reduced ? 0 : constrained ? 12 : compact ? 18 : 24,
    };
}

export function deviceEnvironment() {
    return {
        width: window.innerWidth,
        coarse: window.matchMedia('(pointer: coarse)').matches,
        reduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        memory: navigator.deviceMemory || 0,
        cores: navigator.hardwareConcurrency || 0,
        saveData: Boolean(navigator.connection?.saveData),
    };
}

/** Observe only requested moving frames, excluding authored ambient sleeps and hidden tabs. */
export class FrameBudget {
    constructor() { this.scale = 1; this.reset(); }
    reset() { this.samples = 0; this.slow = 0; }
    observe(delay, active) {
        if (!active || !Number.isFinite(delay) || delay < 0) { this.reset(); return false; }
        this.samples++;
        if (delay > 34) this.slow++;
        if (this.samples < 12) return false;
        const reduce = this.slow >= 8 && this.scale > .76;
        this.reset();
        if (reduce) this.scale = Math.max(.75, this.scale * .85);
        return reduce;
    }
}
