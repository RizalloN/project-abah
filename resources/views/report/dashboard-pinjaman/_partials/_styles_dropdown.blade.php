<style>
    /* Premium Dropdown System - EDC Style */
    .loan-dropdown-shell {
        position: relative;
        width: 100%;
    }

    .loan-dropdown-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        height: 52px;
        padding: 0 1.25rem;
        background: #ffffff !important;
        border: 1.5px solid #e2eaf3 !important;
        border-radius: 14px !important;
        font-weight: 600;
        color: var(--loan-blue-ink);
        text-align: left;
        transition: all 0.3s ease;
        cursor: pointer;
        line-height: 1.2;
    }

    .loan-dropdown-toggle:hover {
        border-color: var(--loan-blue) !important;
        background: #f8fbff !important;
    }

    .loan-dropdown-toggle:focus {
        outline: none;
        border-color: var(--loan-blue) !important;
        box-shadow: 0 0 0 4px rgba(8, 87, 195, 0.1) !important;
    }

    .loan-dropdown-toggle[disabled] {
        background: #f4f7fa !important;
        cursor: not-allowed;
        opacity: 0.7;
    }

    .loan-dropdown-label {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        margin-right: 0.5rem;
        font-size: 0.88rem;
    }

    .loan-dropdown-menu {
        position: absolute;
        top: calc(100% + 5px);
        left: 0;
        right: 0;
        z-index: 2000;
        display: none;
        max-height: 350px;
        overflow-y: auto;
        background: #ffffff;
        border: 1px solid #dbe5ef;
        border-radius: 16px;
        box-shadow: 0 15px 45px rgba(31, 38, 135, 0.25);
        padding: 0.5rem;
        animation: fadeInDownDropdown 0.2s ease-out;
    }

    .loan-dropdown-menu.show {
        display: block;
    }

    .loan-dropdown-item {
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
        cursor: pointer;
        border-radius: 10px;
        transition: all 0.2s ease;
        margin-bottom: 2px;
    }

    .loan-dropdown-item:hover {
        background: #f0f7ff;
    }

    .loan-dropdown-item.active {
        background: #eef5ff;
        color: var(--loan-blue);
    }

    .loan-dropdown-item.active .form-check-label {
        color: var(--loan-blue);
        font-weight: 700;
    }

    .loan-dropdown-item .form-check {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 0;
        cursor: pointer;
        width: 100%;
    }

    .loan-dropdown-item input[type="checkbox"] {
        width: 1.15rem;
        height: 1.15rem;
        cursor: pointer;
        accent-color: var(--loan-blue);
        border: 2px solid #cbd5e1;
        border-radius: 4px;
    }

    .loan-dropdown-item .form-check-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #334155;
        cursor: pointer;
        user-select: none;
    }

    @keyframes fadeInDownDropdown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
