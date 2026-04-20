<script>
    const intlNumberFormat = new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });

    function formatNumber(value) {
        if (value === null || value === undefined || value === '') {
            return '-';
        }
        const n = Number(value);
        return Number.isNaN(n) ? '0' : intlNumberFormat.format(n);
    }

    function formatDate(value) {
        if (!value) return '-';
        const date = new Date(`${value}T00:00:00`);
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleDateString('id-ID', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    }

    function initMultiSelect(element, placeholder) {
        if (window.jQuery && window.jQuery.fn.select2) {
            window.jQuery(element).select2({
                theme: 'bootstrap4',
                placeholder: placeholder,
                allowClear: true,
                width: '100%',
                closeOnSelect: false,
                templateResult: formatSelectOption,
                templateSelection: (selection) => selection.text,
            });
        }
    }

    function formatSelectOption(state) {
        if (!state.id) return state.text;
        const $state = window.jQuery(
            `<div class="loan-select2-option">
                <input type="checkbox" ${state.selected ? 'checked' : ''}>
                <span>${state.text}</span>
            </div>`
        );
        return $state;
    }

    function parseSelectedDataset(element) {
        try {
            return JSON.parse(element.dataset.selected || '[]');
        } catch (_) {
            return [];
        }
    }

    function syncSelectedDataset(element) {
        const values = window.jQuery(element).val() || [];
        element.dataset.selected = JSON.stringify(values);
    }
</script>
