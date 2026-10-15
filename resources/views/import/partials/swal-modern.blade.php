@once
<style id="import-swal-modern-styles">
    /* ==========================================================================
       BRI Nusantara - Modern Luxury SweetAlert2 Design System for Import Report
       ========================================================================== */

    .swal2-popup .swal2-actions button,
    .swal2-popup .swal2-close {
        min-width: 44px;
        min-height: 44px;
        touch-action: manipulation;
    }
    .swal2-popup .swal2-actions button:focus-visible,
    .swal2-popup .swal2-close:focus-visible {
        outline: 3px solid #307fe2;
        outline-offset: 3px;
    }

    /* 1. Backdrop Overlay */
    .swal2-container.swal-modern-container.swal2-backdrop-show {
        backdrop-filter: blur(4px) saturate(125%);
        -webkit-backdrop-filter: blur(4px) saturate(125%);
        background: rgba(7, 29, 65, 0.48);
        transition: opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
        z-index: 99999 !important;
    }

    /* 2. Modal Shell / Card Container */
    .swal2-popup.swal-modern-popup {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
        border-radius: 24px !important;
        padding: 1.85rem 1.75rem 1.6rem !important;
        background: linear-gradient(180deg, #ffffff 0%, #fafdff 100%) !important;
        border: 1px solid rgba(8, 87, 195, 0.14) !important;
        box-shadow: 0 25px 60px -15px rgba(7, 29, 65, 0.28), 0 0 0 1px rgba(8, 87, 195, 0.06) !important;
        width: 520px;
        max-width: calc(100vw - 32px) !important;
        max-height: calc(100dvh - 32px);
        color: #0f172a !important;
        overflow-x: hidden;
        overflow-y: auto;
        overscroll-behavior: contain;
        box-sizing: border-box !important;
    }

    /* 3. Title Styling */
    .swal-modern-popup .swal2-title,
    .swal-modern-title {
        font-size: 1.28rem !important;
        font-weight: 800 !important;
        color: #071d41 !important;
        letter-spacing: -0.025em !important;
        line-height: 1.35 !important;
        margin: 0.25rem 0 0.55rem !important;
        padding: 0 !important;
        overflow-wrap: anywhere;
    }

    /* 4. Body Content */
    .swal-modern-popup .swal2-html-container,
    .swal-modern-html {
        color: #475569 !important;
        font-size: 0.935rem !important;
        line-height: 1.62 !important;
        font-weight: 450 !important;
        margin: 0.35rem 0 1.25rem !important;
        padding: 0 !important;
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .swal-modern-popup .swal2-html-container b,
    .swal-modern-popup .swal2-html-container strong,
    .swal-modern-html b,
    .swal-modern-html strong {
        color: #071d41 !important;
        font-weight: 700 !important;
    }

    .swal-modern-popup .swal2-html-container p,
    .swal-modern-html p {
        margin: 0.4rem 0 !important;
    }

    /* 5. Modern Icons (Squircle Soft Badge) */
    .swal-modern-popup .swal2-icon {
        border: none !important;
        width: 64px !important;
        height: 64px !important;
        border-radius: 18px !important;
        margin: 0.35rem auto 1.15rem !important;
        font-size: 0.8rem;
        align-items: center !important;
        justify-content: center !important;
        box-shadow: 0 8px 24px -6px rgba(7, 29, 65, 0.12) !important;
        position: relative !important;
        box-sizing: border-box !important;
    }

    /* Success Icon */
    .swal-modern-popup .swal2-icon.swal2-success {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(5, 150, 105, 0.24) 100%) !important;
        border: 1.5px solid rgba(16, 185, 129, 0.38) !important;
        color: #047857 !important;
    }
    .swal-modern-popup .swal2-icon.swal2-success [class^='swal2-success-line'] {
        background-color: #047857 !important;
        height: 4px !important;
        border-radius: 2px !important;
    }
    .swal-modern-popup .swal2-icon.swal2-success .swal2-success-ring {
        display: none !important;
    }
    .swal-modern-popup .swal2-icon.swal2-success .swal2-success-fix,
    .swal-modern-popup .swal2-icon.swal2-success .swal2-success-circular-line-left,
    .swal-modern-popup .swal2-icon.swal2-success .swal2-success-circular-line-right {
        background: transparent !important;
    }

    /* Warning Icon */
    .swal-modern-popup .swal2-icon.swal2-warning {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.14) 0%, rgba(217, 119, 6, 0.22) 100%) !important;
        border: 1.5px solid rgba(245, 158, 11, 0.4) !important;
        color: #d97706 !important;
    }
    .swal-modern-popup .swal2-icon.swal2-warning .swal2-icon-content {
        font-size: 2.1rem !important;
        font-weight: 800 !important;
        color: #925800 !important;
        line-height: 1 !important;
    }

    /* Error Icon */
    .swal-modern-popup .swal2-icon.swal2-error {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.14) 0%, rgba(220, 38, 38, 0.22) 100%) !important;
        border: 1.5px solid rgba(239, 68, 68, 0.38) !important;
        color: #dc2626 !important;
    }
    .swal-modern-popup .swal2-icon.swal2-error [class^='swal2-x-mark-line'] {
        background-color: #dc2626 !important;
        height: 4px !important;
        border-radius: 2px !important;
    }

    /* Info & Question Icons */
    .swal-modern-popup .swal2-icon.swal2-info,
    .swal-modern-popup .swal2-icon.swal2-question {
        background: linear-gradient(135deg, rgba(8, 87, 195, 0.12) 0%, rgba(48, 127, 226, 0.22) 100%) !important;
        border: 1.5px solid rgba(8, 87, 195, 0.32) !important;
        color: #0857c3 !important;
    }
    .swal-modern-popup .swal2-icon.swal2-info .swal2-icon-content,
    .swal-modern-popup .swal2-icon.swal2-question .swal2-icon-content {
        font-size: 2rem !important;
        font-weight: 800 !important;
        color: #0857c3 !important;
        line-height: 1 !important;
    }

    /* 6. Buttons & Action Bar */
    .swal-modern-popup .swal2-actions,
    .swal-modern-actions {
        align-items: center !important;
        justify-content: center !important;
        gap: 0.75rem !important;
        margin-top: 1.35rem !important;
        padding: 0 !important;
        width: 100% !important;
        border: none !important;
        background: transparent !important;
    }

    /* Confirm Button (Fintech BRI Signature Gradient) */
    .swal-modern-popup .swal2-confirm,
    .swal-modern-confirm {
        align-items: center !important;
        justify-content: center !important;
        gap: 0.5rem !important;
        border-radius: 12px !important;
        font-size: 0.885rem !important;
        font-weight: 700 !important;
        padding: 0.68rem 1.5rem !important;
        color: #ffffff !important;
        border: none !important;
        box-shadow: 0 4px 14px -2px rgba(8, 87, 195, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.22) !important;
        transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s ease !important;
        cursor: pointer !important;
        letter-spacing: 0.01em !important;
        text-decoration: none !important;
        min-height: 44px !important;
        touch-action: manipulation;
    }
    .swal-modern-popup .swal2-confirm:not([style*="background-color"]),
    .swal-modern-confirm:not([style*="background-color"]) {
        background: linear-gradient(135deg, #0857c3 0%, #053b82 100%) !important;
    }
    .swal-modern-popup .swal2-confirm:hover,
    .swal-modern-confirm:hover {
        transform: translateY(-1.5px) !important;
        box-shadow: 0 8px 24px -3px rgba(8, 87, 195, 0.6) !important;
        color: #ffffff !important;
    }
    .swal-modern-popup .swal2-confirm:active,
    .swal-modern-confirm:active {
        transform: translateY(0) scale(0.98) !important;
    }

    /* Danger Confirm Button */
    .swal-modern-popup .swal2-confirm.swal-btn-danger,
    .swal-modern-confirm--danger {
        background: linear-gradient(135deg, #cf2e2e 0%, #b4232d 100%) !important;
        box-shadow: 0 4px 14px -2px rgba(239, 68, 68, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.22) !important;
    }
    .swal-modern-popup .swal2-confirm.swal-btn-danger:hover,
    .swal-modern-confirm--danger:hover {
        box-shadow: 0 8px 24px -3px rgba(239, 68, 68, 0.6) !important;
    }

    /* Cancel Button */
    .swal-modern-popup .swal2-cancel,
    .swal-modern-cancel {
        align-items: center !important;
        justify-content: center !important;
        border-radius: 12px !important;
        font-size: 0.885rem !important;
        font-weight: 650 !important;
        padding: 0.68rem 1.4rem !important;
        background: #f1f5f9 !important;
        color: #475569 !important;
        border: 1px solid #e2e8f0 !important;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05) !important;
        transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), background-color 0.2s ease, border-color 0.2s ease !important;
        cursor: pointer !important;
        letter-spacing: 0.01em !important;
        text-decoration: none !important;
        min-height: 44px !important;
        touch-action: manipulation;
    }
    .swal-modern-popup .swal2-cancel:hover,
    .swal-modern-cancel:hover {
        background: #e2e8f0 !important;
        color: #0f172a !important;
        border-color: #cbd5e1 !important;
        transform: translateY(-1px) !important;
    }

    .swal-modern-popup button:focus-visible,
    .swal-modern-popup input:focus-visible,
    .swal-modern-popup select:focus-visible,
    .swal-modern-popup textarea:focus-visible {
        outline: 3px solid #307fe2;
        outline-offset: 3px;
    }

    .swal-modern-popup button:disabled {
        cursor: not-allowed !important;
        opacity: 0.65;
        transform: none !important;
    }

    .swal-modern-popup .swal2-close {
        min-width: 44px;
        min-height: 44px;
    }

    /* 7. Modern Import Progress Shell & Components */
    .swal-import-shell {
        display: grid;
        gap: 0.95rem;
        text-align: left;
    }

    .swal-import-head {
        display: grid;
        justify-items: center;
        gap: 0.45rem;
        text-align: center;
    }

    .swal-import-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.38rem 0.85rem;
        border-radius: 999px;
        background: rgba(8, 87, 195, 0.08);
        color: #0857c3;
        font-size: 0.73rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        border: 1px solid rgba(8, 87, 195, 0.18);
        box-shadow: 0 2px 6px rgba(8, 87, 195, 0.06);
    }
    .swal-import-badge--success {
        background: rgba(16, 185, 129, 0.1);
        color: #047857;
        border-color: rgba(16, 185, 129, 0.25);
    }
    .swal-import-badge--warning {
        background: rgba(245, 158, 11, 0.1);
        color: #925800;
        border-color: rgba(245, 158, 11, 0.25);
    }
    .swal-import-badge--danger {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
        border-color: rgba(239, 68, 68, 0.25);
    }

    .swal-import-desc {
        color: #64748b;
        font-size: 0.885rem;
        line-height: 1.5;
    }

    .swal-import-card {
        padding: 1.05rem 1.15rem;
        border-radius: 18px !important;
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%) !important;
        border: 1px solid rgba(203, 213, 225, 0.7) !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9), 0 4px 14px -3px rgba(15, 23, 42, 0.06) !important;
    }

    .swal-import-card__top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.65rem;
    }

    .swal-import-label {
        color: #64748b;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .swal-import-percent {
        color: #0857c3;
        font-size: 0.96rem;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
    }

    .swal-import-progress,
    .progress.report-management-progress {
        height: 12px !important;
        border-radius: 999px !important;
        background: #e2e8f0 !important;
        overflow: hidden !important;
        box-shadow: inset 0 1px 3px rgba(15, 23, 42, 0.12) !important;
        margin: 0 !important;
        border: none !important;
    }

    .swal-import-progress__bar,
    .progress-bar.report-management-progress__bar,
    #swal-progress-bar,
    #force-sync-progress-bar,
    #delete-progress-bar {
        position: relative;
        background: linear-gradient(90deg, #0857c3 0%, #307fe2 50%, #00c6ff 100%) !important;
        background-size: 200% 100% !important;
        animation: swalModernProgressShimmer 2.2s linear infinite !important;
        border-radius: 999px !important;
        transition: width 280ms cubic-bezier(0.16, 1, 0.3, 1) !important;
        box-shadow: 0 0 12px rgba(48, 127, 226, 0.45) !important;
        line-height: 12px !important;
        height: 100% !important;
        border: none !important;
    }

    @keyframes swalModernProgressShimmer {
        0% { background-position: 100% 0; }
        100% { background-position: -100% 0; }
    }

    .swal-import-meta {
        margin-top: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .swal-import-meta__dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #0857c3;
        box-shadow: 0 0 0 3px rgba(8, 87, 195, 0.2);
        animation: swalPulseDot 1.8s infinite;
        flex-shrink: 0;
    }

    @keyframes swalPulseDot {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.25); opacity: 0.65; }
    }

    .swal-import-meta__status {
        color: #0857c3;
        font-weight: 700;
        font-size: 0.84rem;
        letter-spacing: 0.01em;
        display: block;
        word-break: break-word;
        white-space: normal;
        line-height: 1.4;
    }

    .swal-import-stats {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .swal-import-stat {
        padding: 0.8rem 0.95rem;
        border-radius: 14px !important;
        background: #ffffff !important;
        border: 1px solid rgba(203, 213, 225, 0.7) !important;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03) !important;
    }

    .swal-import-stat__label {
        display: block;
        margin-bottom: 0.2rem;
        color: #64748b;
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .swal-import-stat__value {
        display: block;
        color: #071d41;
        font-size: 0.95rem;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
    }

    /* 8. Mobile Adjustments */
    @media (max-width: 575.98px) {
        .swal2-popup.swal-modern-popup:not(.swal2-toast) {
            padding: 1.5rem 1.25rem 1.25rem !important;
            border-radius: 20px !important;
        }

        .swal-modern-popup:not(.swal2-toast) .swal2-actions,
        .swal-modern-actions {
            flex-direction: column-reverse !important;
            gap: 0.5rem !important;
        }

        .swal-modern-popup:not(.swal2-toast) .swal2-confirm,
        .swal-modern-confirm,
        .swal-modern-popup:not(.swal2-toast) .swal2-cancel,
        .swal-modern-cancel {
            width: 100% !important;
        }
    }

    /* 9. Toast Notifications */
    body.swal2-toast-shown .swal2-container.swal-modern-container {
        background: transparent !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
    }

    .swal2-popup.swal-modern-popup.swal2-toast {
        width: 100%;
        max-width: 100% !important;
        border-radius: 16px !important;
        padding: 0.85rem 1.15rem !important;
        background: rgba(255, 255, 255, 0.96) !important;
        backdrop-filter: blur(12px) !important;
        box-shadow: 0 12px 32px -4px rgba(7, 29, 65, 0.2), 0 0 0 1px rgba(8, 87, 195, 0.1) !important;
        border: 1px solid rgba(8, 87, 195, 0.12) !important;
    }
    .swal-modern-popup.swal2-toast .swal2-title {
        font-size: 0.92rem !important;
        margin: 0 !important;
        color: #071d41 !important;
    }
    .swal-modern-popup.swal2-toast .swal2-icon {
        width: 32px !important;
        height: 32px !important;
        margin: 0 0.5rem 0 0 !important;
        border-radius: 10px !important;
        box-shadow: none !important;
    }
    .swal-modern-popup.swal2-toast .swal2-icon.swal2-success .swal2-success-line-tip {
        left: 4px !important;
        top: 17px !important;
        width: 10px !important;
        height: 3px !important;
    }
    .swal-modern-popup.swal2-toast .swal2-icon.swal2-success .swal2-success-line-long {
        right: 3px !important;
        top: 14px !important;
        width: 17px !important;
        height: 3px !important;
    }
    .swal-modern-popup.swal2-toast .swal2-icon .swal2-icon-content {
        font-size: 1.15rem !important;
    }
    .swal-modern-popup .swal2-timer-progress-bar {
        background: linear-gradient(90deg, #0857c3, #00c6ff) !important;
        height: 3px !important;
    }

    @media (prefers-reduced-motion: reduce) {
        .swal2-container,
        .swal2-popup,
        .swal2-popup .swal2-icon,
        .swal2-popup .swal2-icon * {
            animation: none !important;
            transition: none !important;
        }
        .swal-modern-popup.swal2-show,
        .swal-modern-popup.swal2-hide,
        .swal-modern-popup .swal-import-progress__bar,
        .swal-modern-popup .swal-import-meta__dot,
        .swal-modern-popup .fa-spin {
            animation: none !important;
        }
        .swal-modern-popup .swal-import-progress__bar,
        .swal-modern-popup #swal-progress-bar,
        .swal-modern-popup #force-sync-progress-bar,
        .swal-modern-popup #delete-progress-bar {
            transition: none !important;
            animation: none !important;
        }
        .swal-modern-popup button {
            transition: none !important;
            transform: none !important;
        }
        .swal-modern-popup .swal2-icon,
        .swal-modern-popup .swal2-icon * {
            animation: none !important;
        }
    }
</style>

<script id="import-swal-modern-scripts">
    (function () {
        if (window.ImportSwal) return;

        const escapeText = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        })[character]);
        const progressPercent = (value) => {
            const number = Number(value);
            return Number.isFinite(number) ? Math.max(0, Math.min(100, number)) : 0;
        };
        const toastCleanups = new WeakMap();
        const bindToastTimer = (popup) => {
            toastCleanups.get(popup)?.();
            let hovered = false;
            let focused = false;
            const pause = () => Swal.stopTimer();
            const resume = () => {
                if (!hovered && !focused) Swal.resumeTimer();
            };
            const listeners = {
                mouseenter: () => { hovered = true; pause(); },
                mouseleave: () => { hovered = false; resume(); },
                focusin: () => { focused = true; pause(); },
                focusout: (event) => {
                    focused = popup.contains(event.relatedTarget);
                    resume();
                },
            };
            Object.entries(listeners).forEach(([event, listener]) => popup.addEventListener(event, listener));
            toastCleanups.set(popup, () => {
                Object.entries(listeners).forEach(([event, listener]) => popup.removeEventListener(event, listener));
                toastCleanups.delete(popup);
            });
        };
        const swalModernClass = {
            container: 'swal-modern-container',
            popup: 'swal-modern-popup',
            title: 'swal-modern-title',
            htmlContainer: 'swal-modern-html',
            confirmButton: 'swal-modern-confirm',
            cancelButton: 'swal-modern-cancel',
            denyButton: 'swal-modern-cancel',
            actions: 'swal-modern-actions',
            icon: 'swal-modern-icon',
        };

        const ImportSwal = {
            theme: {
                customClass: swalModernClass,
                buttonsStyling: false,
                background: '#ffffff',
                didOpen: (popup) => {
                    if (popup.classList.contains('swal2-toast')) bindToastTimer(popup);
                },
                willClose: (popup) => toastCleanups.get(popup)?.(),
            },

            fire: function (options) {
                if (typeof Swal === 'undefined') {
                    console.error('SweetAlert2 is not loaded on this page.');
                    return Promise.resolve({ isConfirmed: false, isDismissed: true });
                }
                const opts = Object.assign({}, ImportSwal.theme, options || {});
                opts.customClass = Object.assign({}, swalModernClass, options?.customClass || {});
                if (opts.isDanger) {
                    opts.customClass.confirmButton = (opts.customClass.confirmButton || 'swal-modern-confirm') + ' swal-btn-danger';
                }
                delete opts.isDanger;
                return Swal.fire(opts);
            },

            confirm: function (config = {}) {
                const options = Object.assign({
                    icon: config.icon || 'warning',
                    title: config.title || 'Konfirmasi Tindakan',
                    showCancelButton: true,
                    confirmButtonText: config.confirmButtonText || config.confirmText || 'Lanjutkan',
                    cancelButtonText: config.cancelButtonText || config.cancelText || 'Batal',
                    focusCancel: config.focusCancel ?? !!config.isDanger,
                }, config);
                delete options.confirmText;
                delete options.cancelText;
                return ImportSwal.fire(options);
            },

            success: function (title, textOrHtml, options) {
                return ImportSwal.fire(Object.assign({
                    icon: 'success',
                    titleText: title || 'Berhasil',
                    text: textOrHtml ?? '',
                    confirmButtonText: 'OK',
                }, options || {}));
            },

            error: function (title, textOrHtml, options) {
                return ImportSwal.fire(Object.assign({
                    icon: 'error',
                    titleText: title || 'Terjadi Kesalahan',
                    text: textOrHtml ?? '',
                    confirmButtonText: 'Tutup',
                }, options || {}));
            },

            warning: function (title, textOrHtml, options) {
                return ImportSwal.fire(Object.assign({
                    icon: 'warning',
                    titleText: title || 'Peringatan',
                    text: textOrHtml ?? '',
                    confirmButtonText: 'Mengerti',
                }, options || {}));
            },

            info: function (title, textOrHtml, options) {
                return ImportSwal.fire(Object.assign({
                    icon: 'info',
                    titleText: title || 'Informasi',
                    text: textOrHtml ?? '',
                    confirmButtonText: 'OK',
                }, options || {}));
            },

            progress: function (config = {}) {
                const title = config.title || 'Memproses Data';
                const desc = config.description || 'Sistem sedang memproses data ke database.';
                const badge = config.badge || '<i class="fas fa-circle-notch fa-spin mr-1"></i> Sedang Berjalan';
                const initialPercent = progressPercent(config.initialPercent);
                const initialStatus = config.initialStatus ?? 'Menyiapkan proses...';
                const showStats = config.showStats !== false;
                const stat1Label = config.stat1Label || 'Baris Selesai';
                const stat2Label = config.stat2Label || 'Kecepatan';

                const html = `
                    <div class="swal-import-shell">
                        <div class="swal-import-head">
                            <span class="swal-import-badge" id="swal-modern-badge">${badge}</span>
                            <div class="swal-import-desc" id="swal-modern-desc">${escapeText(desc)}</div>
                        </div>
                        <div class="swal-import-card">
                            <div class="swal-import-card__top">
                                <span class="swal-import-label">Progress</span>
                                <span class="swal-import-percent" id="swal-modern-percent">${initialPercent}%</span>
                            </div>
                            <div class="swal-import-progress">
                                <div id="swal-modern-bar" class="swal-import-progress__bar" role="progressbar" aria-label="Progres pemrosesan" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${initialPercent}" style="width: ${initialPercent}%;"></div>
                            </div>
                            <div class="swal-import-meta">
                                <span class="swal-import-meta__dot"></span>
                                <span class="swal-import-meta__status" id="swal-modern-status" role="status" aria-live="polite">${escapeText(initialStatus)}</span>
                            </div>
                        </div>
                        ${showStats ? `
                        <div class="swal-import-stats">
                            <div class="swal-import-stat">
                                <span class="swal-import-stat__label">${escapeText(stat1Label)}</span>
                                <span class="swal-import-stat__value" id="swal-modern-stat-1">${escapeText(config.stat1Value ?? '0')}</span>
                            </div>
                            <div class="swal-import-stat">
                                <span class="swal-import-stat__label">${escapeText(stat2Label)}</span>
                                <span class="swal-import-stat__value" id="swal-modern-stat-2">${escapeText(config.stat2Value ?? '-')}</span>
                            </div>
                        </div>` : ''}
                    </div>
                `;

                return ImportSwal.fire({
                    titleText: title,
                    html: html,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: (popup) => {
                        if (typeof config.didOpen === 'function') config.didOpen(popup);
                    }
                });
            },

            updateProgress: function (percent, statusText, meta) {
                const bar = document.getElementById('swal-modern-bar') || document.getElementById('swal-progress-bar') || document.getElementById('force-sync-progress-bar');
                const pct = document.getElementById('swal-modern-percent') || document.getElementById('swal-progress-percent');
                const st = document.getElementById('swal-modern-status') || document.getElementById('swal-status-text') || document.getElementById('force-sync-status-text');
                const val1 = document.getElementById('swal-modern-stat-1') || document.getElementById('swal-rows-info');
                const val2 = document.getElementById('swal-modern-stat-2') || document.getElementById('swal-speed-info');
                const badge = document.getElementById('swal-modern-badge');

                const p = progressPercent(percent);
                if (bar) {
                    bar.style.width = p + '%';
                    if (bar.setAttribute) bar.setAttribute('aria-valuenow', p);
                }
                if (pct) pct.textContent = p + '%';
                if (st && statusText !== undefined && statusText !== null) st.textContent = statusText;
                if (meta) {
                    if (val1 && meta.stat1 !== undefined) val1.textContent = meta.stat1;
                    if (val2 && meta.stat2 !== undefined) val2.textContent = meta.stat2;
                    if (badge && meta.badge !== undefined) badge.innerHTML = meta.badge;
                }
            },

            toast: function (options) {
                const config = typeof options === 'string' ? { title: options } : (options || {});
                return ImportSwal.fire(Object.assign({
                    toast: true,
                    position: config.position || 'top-end',
                    showConfirmButton: false,
                    timer: config.timer ?? 3500,
                    timerProgressBar: true,
                    icon: config.icon || 'success',
                    titleText: config.title || '',
                }, config, {
                    didOpen: (popup) => {
                        bindToastTimer(popup);
                        if (typeof config.didOpen === 'function') config.didOpen(popup);
                    },
                    willClose: (popup) => {
                        toastCleanups.get(popup)?.();
                        if (typeof config.willClose === 'function') config.willClose(popup);
                    },
                }));
            },

            close: function () {
                if (typeof Swal !== 'undefined' && Swal.isVisible()) {
                    Swal.close();
                }
            }
        };

        window.ImportSwal = ImportSwal;
        window.themedSwal = ImportSwal.fire;
        if (typeof Swal !== 'undefined') {
            // Native button styling remains available to pages with their own popup classes.
            window.Swal = Swal.mixin(Object.assign({}, ImportSwal.theme, { buttonsStyling: true }));
        }
    })();
</script>
@endonce
