const canvasFromTarget = (target) => {
    if (!target) {
        return null;
    }
    if (target instanceof HTMLCanvasElement) {
        return target;
    }
    if (target.canvas instanceof HTMLCanvasElement) {
        return target.canvas;
    }
    return null;
};

export class PresentationChartManager {
    constructor(chartConstructor) {
        this.nativeChart = chartConstructor;
        this.charts = new Set();
        this.resizeFrame = null;
        this.resizeObserver = typeof ResizeObserver === 'function'
            ? new ResizeObserver((entries) => {
                entries.forEach((entry) => this.resizeWithin(entry.target));
            })
            : null;
    }

    install() {
        const NativeChart = this.nativeChart;
        if (!NativeChart || NativeChart.__presentationManaged) {
            return NativeChart;
        }

        const manager = this;
        class ManagedPresentationChart extends NativeChart {
            constructor(target, config) {
                const canvas = canvasFromTarget(target);
                const existing = canvas && typeof NativeChart.getChart === 'function'
                    ? NativeChart.getChart(canvas)
                    : null;
                if (existing) {
                    existing.destroy();
                }

                super(target, config);
                manager.register(this);
            }

            destroy() {
                manager.unregister(this);
                return super.destroy();
            }
        }

        Object.defineProperty(ManagedPresentationChart, '__presentationManaged', {
            value: true,
        });
        window.Chart = ManagedPresentationChart;
        this.chartConstructor = ManagedPresentationChart;

        return ManagedPresentationChart;
    }

    register(chart) {
        if (!chart) {
            return chart;
        }

        this.charts.add(chart);
        const container = chart.canvas?.parentElement;
        if (container && this.resizeObserver) {
            this.resizeObserver.observe(container);
        }
        return chart;
    }

    unregister(chart) {
        this.charts.delete(chart);
    }

    observe(root = document) {
        if (!this.resizeObserver) {
            return;
        }

        root.querySelectorAll('.pres-chart-wrap, .pres-explorer-trend-plot, .pres-trend-chart-wrap')
            .forEach((container) => this.resizeObserver.observe(container));
    }

    resizeWithin(root) {
        if (!root) {
            return;
        }

        if (this.resizeFrame !== null) {
            cancelAnimationFrame(this.resizeFrame);
        }

        this.resizeFrame = requestAnimationFrame(() => {
            const canvases = root instanceof HTMLCanvasElement
                ? [root]
                : Array.from(root.querySelectorAll?.('canvas') || []);

            canvases.forEach((canvas) => {
                const chart = this.get(canvas);
                if (!chart || !canvas.isConnected || canvas.offsetParent === null) {
                    return;
                }
                chart.resize();
                chart.render();
            });
            this.destroyDisconnected();
            this.resizeFrame = null;
        });
    }

    activate(slide) {
        this.observe(slide);
        this.resizeWithin(slide);
    }

    get(canvas) {
        const ChartConstructor = this.chartConstructor || this.nativeChart;
        return typeof ChartConstructor?.getChart === 'function'
            ? ChartConstructor.getChart(canvas)
            : Array.from(this.charts).find((chart) => chart.canvas === canvas);
    }

    destroyDisconnected() {
        Array.from(this.charts).forEach((chart) => {
            if (!chart.canvas?.isConnected) {
                chart.destroy();
            }
        });
    }

    destroyAll() {
        Array.from(this.charts).forEach((chart) => chart.destroy());
        this.charts.clear();
        this.resizeObserver?.disconnect();
    }
}

export const installPresentationChartManager = () => {
    const manager = new PresentationChartManager(window.Chart);
    manager.install();
    manager.observe(document);

    window.addEventListener('pagehide', () => manager.destroyAll(), { once: true });
    window.addEventListener('orientationchange', () => {
        window.setTimeout(() => manager.resizeWithin(document.querySelector('.apple-slide.active')), 120);
    });

    return manager;
};

