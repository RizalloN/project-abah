<style>
    .input-preview-card {
        border: 1px solid #e9ecef;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 0.5rem 1rem rgba(15, 23, 42, 0.08) !important;
    }

    .preview-shell {
        border-radius: 1.4rem;
        background:
            radial-gradient(circle at top right, rgba(45, 212, 191, 0.16), transparent 28%),
            linear-gradient(180deg, #ffffff 0%, #f8fbfc 100%);
        border: 1px solid rgba(148, 163, 184, 0.16);
        box-shadow: 0 24px 48px -34px rgba(15, 23, 42, 0.3);
    }

    .preview-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.55rem 0.9rem;
        border-radius: 999px;
        background: rgba(20, 184, 166, 0.1);
        color: #0f766e;
        font-size: 0.76rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .preview-toolbar {
        border-radius: 1rem;
        background: linear-gradient(135deg, rgba(15, 23, 42, 0.98), rgba(19, 78, 74, 0.92));
        color: #ffffff;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .input-table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .input-table {
        width: 100%;
        min-width: 1180px;
        border-collapse: collapse;
    }

    .input-table th,
    .input-table td {
        border: 1px solid #dee2e6;
        vertical-align: middle !important;
    }

    .input-table th {
        background: #f8fafc;
        color: #0f172a;
        font-size: 0.82rem;
        font-weight: 700;
        padding: 0.8rem 0.6rem;
        text-align: center;
        white-space: nowrap;
    }

    .input-table td {
        background: #ffffff;
        padding: 0.45rem;
    }

    .input-table .cell-input,
    .input-table .cell-select {
        width: 100%;
        min-width: 120px;
        border: 1px solid #dbe4ee;
        border-radius: 0.75rem;
        min-height: 40px;
        padding: 0.5rem 0.65rem;
        font-size: 0.84rem;
        color: #0f172a;
        background: #ffffff;
        box-shadow: none;
    }

    .input-table .cell-input:focus,
    .input-table .cell-select:focus {
        outline: none;
        border-color: #0f766e;
        box-shadow: 0 0 0 0.15rem rgba(15, 118, 110, 0.14);
    }

    .input-table .col-no {
        width: 60px;
        min-width: 60px;
        text-align: center;
        font-weight: 700;
        color: #334155;
        background: #f8fafc;
    }

    .input-table .col-action {
        width: 94px;
        min-width: 94px;
        text-align: center;
        background: #fcfcfd;
    }

    .preview-empty-state {
        border: 1px dashed #cbd5e1;
        border-radius: 1rem;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    }

    .preview-appear {
        animation: previewFadeIn 0.35s ease;
    }

    @keyframes previewFadeIn {
        from {
            opacity: 0;
            transform: translateY(14px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>
