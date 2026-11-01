/** One cancellable gesture owns its delays, animation handles and layout cleanup. */
export class PanelTransition {
    constructor() {
        this.controller = new AbortController();
        this.cleanups = new Set();
    }

    get active() { return !this.controller.signal.aborted; }

    cleanup(callback) {
        if (this.active) this.cleanups.add(callback);
        else callback();
    }

    wait(milliseconds) {
        if (!this.active) return Promise.resolve(false);
        return new Promise((resolve) => {
            const signal = this.controller.signal;
            const finish = (result) => {
                clearTimeout(timer);
                signal.removeEventListener('abort', abort);
                resolve(result);
            };
            const abort = () => finish(false);
            const timer = setTimeout(() => finish(this.active), milliseconds);
            signal.addEventListener('abort', abort, { once: true });
        });
    }

    animate(element, frames, options) {
        if (!this.active || !element?.animate) return Promise.resolve(this.active);
        const animation = element.animate(frames, options);
        this.cleanup(() => animation.cancel());
        return animation.finished.then(() => this.active, () => false);
    }

    cancel() {
        if (!this.active) return;
        this.controller.abort();
        this.cleanups.forEach((callback) => callback());
        this.cleanups.clear();
    }
}
