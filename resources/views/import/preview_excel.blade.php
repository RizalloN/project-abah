@extends('layouts.admin')

@section('title', $pageTitle ?? 'Preview & Filter Data Excel')

@section('content')
@php
    $filtersDisabled = !empty($filtersDisabled);
    $previewReportTitle = $previewBannerTitle ?? $pageTitle ?? 'Preview Data Import';
    $previewModeLabel = $filtersDisabled ? 'Import full' : 'Preview dengan filter';
@endphp
<div class="row">
    <div class="col-12">

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card border-0 shadow-sm mb-3 import-preview-banner">
            <div class="card-body import-preview-banner__body">
                <div class="import-preview-heading">
                    <span class="import-preview-heading__eyebrow">{{ $previewModeLabel }}</span>
                    <h3 class="import-preview-heading__title">
                        <i class="fas fa-file-import text-success mr-2"></i>{{ $previewReportTitle }}
                    </h3>
                    <div class="import-preview-heading__meta">
                        <span><i class="fas fa-columns mr-1"></i>{{ count($headers) }} kolom</span>
                        <span><i class="fas fa-eye mr-1"></i>Preview sample</span>
                        <span><i class="fas fa-filter mr-1"></i>{{ $filtersDisabled ? 'Filter nonaktif' : 'Filter tersedia' }}</span>
                    </div>
                </div>
                <div class="import-preview-notice mt-3 mb-0">
                    <i class="fas fa-info-circle text-info mt-1"></i>
                    @if($filtersDisabled)
                        <strong>Mode Import Full Aktif:</strong> Filter dinonaktifkan untuk report ini agar hasil import tidak ambigu dan selalu memproses seluruh data.
                    @else
                        <strong>Smart Parser Aktif:</strong> Struktur kolom file import telah dinormalisasi dan siap difilter.
                        Opsi filter akan dimuat dari seluruh file saat dropdown dibuka, lalu tabel tetap menampilkan maks 100 baris pertama untuk evaluasi.
                    @endif
                </div>
            </div>
        </div>

        <form id="importForm" method="POST" data-filter-options-url="{{ $filterOptionsRoute ?? route('import.preview.filter-options') }}" data-filtered-rows-url="{{ route('import.preview.filtered-rows') }}" data-no-route-loading>
            @csrf
            <input type="hidden" name="path"                id="file_path"           value="{{ $path }}">
            <input type="hidden" name="delimiter" value="{{ $currentDelimiter ?? 'auto' }}">
            <input type="hidden" name="active_filters_json" id="active_filters_json" value="{}">
            @if(!empty($previewStateKey))
                <input type="hidden" name="preview_state_key" value="{{ $previewStateKey }}">
            @endif

            <div class="card border-0 shadow-sm import-preview-card">
                <div class="card-header bg-white import-preview-actionbar">
                    <div class="card-tools w-100 d-flex justify-content-between">
                        <a href="{{ route('import.index') }}" class="btn btn-outline-secondary import-preview-back">
                            <i class="fas fa-arrow-left mr-1"></i> Kembali
                        </a>
                        <div class="d-flex align-items-center import-preview-actions">
                            @if(!$filtersDisabled)
                            <button type="button" id="btnResetAllFilters" class="btn btn-outline-warning mr-2 import-preview-secondary">
                                <i class="fas fa-undo mr-1"></i> Reset Filter
                            </button>
                            <button type="button" id="btnClearImportCache" class="btn btn-outline-danger mr-2 import-preview-secondary" title="Bersihkan cache filter browser">
                                <i class="fas fa-trash-alt mr-1"></i> Clear Cache
                            </button>
                            @endif
                            <button type="submit" id="btnSubmitImport" class="btn btn-success font-weight-bold import-preview-submit">
                                <i class="fas fa-database mr-1"></i> Jalankan Import ke MySQL
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive import-preview-table-shell">
                        <table class="table table-bordered table-hover m-0 import-preview-table">
                            <thead class="thead-light sticky-top" style="z-index: 2;">
                                <tr>
                                    <th class="text-center align-middle bg-light" style="width: 50px;">#</th>

                                    @foreach($headers as $index => $header)
                                        <th class="align-middle bg-light" style="min-width: 250px;">
                                            <div class="d-flex justify-content-between align-items-center">

                                                <div class="font-weight-bold text-dark text-truncate" style="max-width: 180px;" title="{{ $header }}">
                                                    {{ $header }}
                                                </div>

                                                @if(!$filtersDisabled && isset($formattedUniqueValues[$index]) && count($formattedUniqueValues[$index]) > 0)
                                                <div class="dropdown">
                                                    <button class="btn btn-xs btn-light border dropdown-toggle filter-btn"
                                                            type="button" data-toggle="dropdown"
                                                            aria-expanded="false" data-boundary="window">
                                                        <i class="fas fa-filter text-muted" id="icon_filter_{{ $index }}"></i>
                                                    </button>

                                                    <div class="dropdown-menu dropdown-menu-right shadow p-0"
                                                         style="width: 280px; border-radius: 8px;">
                                                        <div class="p-2 bg-light border-bottom">
                                                            <div class="input-group input-group-sm">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                                </div>
                                                                <input type="text" class="form-control search-filter"
                                                                       data-col="{{ $index }}" placeholder="Search...">
                                                            </div>
                                                        </div>
                                                        <div class="p-2 border-bottom bg-white">
                                                            <div class="custom-control custom-checkbox">
                                                                <input class="custom-control-input select-all-cb" type="checkbox"
                                                                       id="select_all_{{ $index }}" data-col="{{ $index }}" checked>
                                                                <label for="select_all_{{ $index }}"
                                                                       class="custom-control-label font-weight-bold text-dark">(Select All)</label>
                                                            </div>
                                                        </div>
                                                        <div class="p-2 bg-white" id="list_container_{{ $index }}"
                                                             style="max-height: 250px; overflow-y: auto;"
                                                             data-col="{{ $index }}">
                                                            <div class="text-center text-muted py-2 small">Memuat opsi filter...</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                @endif

                                            </div>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(array_slice($preview, 0, 100, true) as $rowIndex => $row)
                                    @php
                                        $rowData = is_array($row) ? $row : (array) $row;
                                    @endphp
                                    <tr class="preview-row d-none">
                                        <td class="text-center text-muted">{{ $rowIndex + 1 }}</td>
                                        @foreach($headers as $colIndex => $header)
                                            @php
                                                $headerKey = trim((string) $header);
                                                $rawValue = $rowData[$headerKey] ?? $rowData[$colIndex] ?? null;
                                                $cellValue = trim((string) ($rawValue ?? ''));
                                                $dataVal   = $cellValue === '' ? '(Blank)' : $cellValue;
                                            @endphp
                                            <td class="text-truncate col-data-{{ $colIndex }}"
                                                data-val="{{ $dataVal }}"
                                                style="max-width: 250px;"
                                                title="{{ $cellValue }}">
                                                {{ $cellValue === '' ? '-' : $cellValue }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach

                                <tr id="empty-state-row" class="d-none">
                                    <td colspan="{{ count($headers) + 1 }}" class="text-center py-5 bg-white text-muted">
                                        <i class="fas fa-search-minus fa-3x mb-3 text-secondary"></i><br>
                                        <h5 class="font-weight-bold text-dark">Tidak ada kecocokan di Sampel Preview.</h5>
                                        <p class="text-success font-weight-bold mt-2">
                                            Klik tombol <b>"Jalankan Import ke MySQL"</b> untuk memproses filter ini ke keseluruhan data.
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="preview-loading-overlay d-none" id="preview-loading-overlay" aria-live="polite">
                            <div class="preview-loading-panel">
                                <span class="preview-loading-spinner"></span>
                                <span id="preview-loading-text">Menyaring preview...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterOptionsMap = @json($formattedUniqueValues);
    const filtersDisabled = @json($filtersDisabled);
    const disableFilterPrefetch = @json($disableFilterPrefetch ?? false);
    const importFormElement = document.getElementById('importForm');
    const previewTbody = document.querySelector('.table-responsive tbody');
    const previewShell = document.querySelector('.import-preview-table-shell');
    const previewOverlay = document.getElementById('preview-loading-overlay');
    const previewLoadingText = document.getElementById('preview-loading-text');
    const basePreviewTbodyHtml = previewTbody ? previewTbody.innerHTML : '';
    const filterOptionsUrl = importFormElement?.dataset.filterOptionsUrl || '';
    const filteredRowsUrl = importFormElement?.dataset.filteredRowsUrl || '';
    const filePathValue = document.getElementById('file_path')?.value || '';
    const previewStateKey = document.querySelector('input[name="preview_state_key"]')?.value || '';
    const delimiterValue = document.querySelector('input[name="delimiter"]')?.value || 'auto';
    const displayFilterMap = @json(session('excel_display_filter_map', []));
    const previewHeaders = @json(array_values($headers ?? []));
    const filterState = {};
    const searchTerms = {};
    const filterRenderLimit = 200;
    let previewViewMode = 'sample';
    let previewRenderToken = 0;
    let previewRefreshTimer = null;

    const swalTheme = {
        customClass: {
            popup: 'swal-modern-popup',
            title: 'swal-modern-title',
            htmlContainer: 'swal-modern-html',
            confirmButton: 'swal-modern-confirm',
        },
        buttonsStyling: false,
        background: '#ffffff',
    };

    function themedSwal(options) {
        return Swal.fire(Object.assign({}, swalTheme, options));
    }

    function normalizeProgressStatus(message) {
        const text = String(message || '').trim();
        const speedMatch = text.match(/\(([\d.,]+)\s+baris\/detik\)$/i);

        if (!speedMatch) {
            return {
                message: text,
                speed: '',
            };
        }

        return {
            message: text,
            speed: speedMatch[1].replace(/[^\d]/g, ''),
        };
    }

    const previewBannerTitle = @json($previewBannerTitle ?? '');
    const isDailyLoanPreview = /daily loan/i.test(previewBannerTitle);

    function resolveLoadingCopy() {
        if (isDailyLoanPreview) {
            return {
                title: 'Import Data',
                description: 'Memeriksa file dan menyiapkan sanitasi CSV Daily Loan.',
                phase: 'Menyiapkan sanitasi CSV Daily Loan...',
                status: 'Menyiapkan sanitasi CSV Daily Loan...',
            };
        }

        return {
            title: 'Memproses Data',
            description: 'Sistem sedang memindahkan data ke MySQL.',
            phase: 'Fase Polars dimulai...',
            status: 'Menyiapkan batch Polars...',
        };
    }

    /* =========================================================
       DROPDOWN: klik di dalam menu tidak menutup dropdown
    ========================================================= */
    // Let controls receive their events first, then keep Bootstrap from closing the menu.
    document.querySelectorAll('.import-preview-table .dropdown-menu').forEach(function (menu) {
        menu.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    });

    /* =========================================================
       CACHE & OPTIMIZATION HELPERS
    ========================================================= */
    function stableHash(value) {
        let hash = 2166136261;
        const input = String(value || '');
        for (let i = 0; i < input.length; i++) {
            hash ^= input.charCodeAt(i);
            hash = Math.imul(hash, 16777619);
        }

        return (hash >>> 0).toString(36);
    }

    const storageKeyPrefix = 'preview_filter_excel_v6_' + stableHash(JSON.stringify({
        file: filePathValue,
        delimiter: delimiterValue,
        headers: previewHeaders,
        displayFilterMap: displayFilterMap,
    }));
    
    function getStorageKey(col) {
        return storageKeyPrefix + '_col_' + col;
    }

    function getStorageTimestampKey(col) {
        return storageKeyPrefix + '_ts_' + col;
    }

    function getFromLocalStorage(col) {
        try {
            const key = getStorageKey(col);
            const data = localStorage.getItem(key);
            const timestamp = localStorage.getItem(getStorageTimestampKey(col));
            
            if (data && timestamp) {
                const cachedAt = parseInt(timestamp);
                const now = Date.now();
                const maxAge = 24 * 60 * 60 * 1000; // 24 hours cache
                
                if (now - cachedAt < maxAge) {
                    return JSON.parse(data);
                }
            }
        } catch (e) {
        }
        return null;
    }

    function saveToLocalStorage(col, values) {
        try {
            localStorage.setItem(getStorageKey(col), JSON.stringify(values));
            localStorage.setItem(getStorageTimestampKey(col), String(Date.now()));
        } catch (e) {
        }
    }

    // Debounce render untuk menghindari multiple renders
    const debounceTimers = {};
    function debounceRender(col, fn, delay = 150) {
        if (debounceTimers[col]) {
            clearTimeout(debounceTimers[col]);
        }
        debounceTimers[col] = setTimeout(() => {
            fn();
            delete debounceTimers[col];
        }, delay);
    }

    function normalizeFilterValue(value) {
        return String(value ?? '').trim();
    }

    function normalizeFilterValues(values) {
        return Array.from(new Set(
            (Array.isArray(values) ? values : []).map(normalizeFilterValue)
        ));
    }

    function replaceFilterOptions(state, values) {
        const normalizedValues = normalizeFilterValues(values);
        const previousValues = Array.isArray(state.allValues) ? state.allValues.slice() : [];
        const previousSelection = new Set(state.selectedValues || []);
        const hadAllSelected = previousValues.length === 0 || previousSelection.size === previousValues.length;

        state.allValues = normalizedValues;
        state.selectedValues = hadAllSelected
            ? new Set(normalizedValues)
            : new Set(normalizedValues.filter(function (value) {
                return previousSelection.has(value);
            }));
    }

    function priorityFilterHeaderScore(header) {
        const compactHeader = String(header || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
        if (!compactHeader || compactHeader.includes('kode')) {
            return 0;
        }

        if (
            compactHeader.includes('namakci')
            || compactHeader.includes('namacabanginduk')
            || compactHeader.includes('namakantorcabanginduk')
            || compactHeader.includes('kantorcabanginduk')
            || compactHeader.includes('kcinduk')
        ) {
            return 120;
        }

        if (compactHeader === 'mbdesc' || compactHeader.includes('mainbranchdescription')) {
            return 115;
        }

        if (compactHeader.includes('namakanca') || compactHeader === 'kanca' || compactHeader === 'kci') {
            return 110;
        }

        if (
            compactHeader === 'namacabang'
            || compactHeader === 'cabang'
            || compactHeader === 'cabang1'
            || compactHeader === 'kantorcabang'
        ) {
            return 105;
        }

        if (compactHeader.includes('cabang')) {
            return 95;
        }

        if (compactHeader === 'brdesc' || compactHeader.includes('branchdescription')) {
            return 80;
        }

        return compactHeader === 'branch' || compactHeader === 'branchname' ? 60 : 0;
    }

    function getRenderableFilterColumns() {
        return Object.keys(filterState).filter(function (col) {
            return Boolean(document.getElementById('list_container_' + col));
        });
    }

    function resolvePriorityFilterColumns() {
        const rankedColumns = getRenderableFilterColumns()
            .map(function (col) {
                return {
                    col: String(col),
                    score: priorityFilterHeaderScore(previewHeaders[Number(col)] || ''),
                };
            })
            .filter(function (candidate) {
                return candidate.score > 0;
            })
            .sort(function (left, right) {
                return right.score - left.score || Number(left.col) - Number(right.col);
            });

        return rankedColumns.length ? [rankedColumns[0].col] : [];
    }

    Object.keys(filterOptionsMap).forEach(function (col) {
        const values = normalizeFilterValues(filterOptionsMap[col]);

        filterState[col] = {
            allValues: values,
            selectedValues: new Set(values),
            fullOptionsLoaded: false,
            isLoading: false,
            loadedSignature: '',
            pendingSignature: '',
            needsRefresh: false,
        };
        searchTerms[col] = '';
    });

    function buildActiveFilterContext(excludeCol) {
        const filters = {};
        Object.keys(filterState)
            .map(function (key) { return String(key); })
            .sort(function (a, b) { return Number(a) - Number(b); })
            .forEach(function (col) {
                if (String(col) === String(excludeCol)) {
                    return;
                }

                const state = filterState[col];
                if (!state) {
                    return;
                }

                if (state.selectedValues.size === state.allValues.length) {
                    return;
                }

                filters[col] = Array.from(state.selectedValues);
            });

        return filters;
    }

    function buildActiveFilterSignature(filters) {
        const ordered = {};
        Object.keys(filters)
            .map(function (key) { return String(key); })
            .sort(function (a, b) { return Number(a) - Number(b); })
            .forEach(function (key) {
                ordered[key] = Array.isArray(filters[key]) ? filters[key].slice() : [];
            });

        return JSON.stringify(ordered);
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function buildPreviewRowHtml(row, rowNumber) {
        const values = Array.isArray(row) ? row : [];
        let html = '<tr class="preview-row">';
        html += '<td class="text-center text-muted">' + rowNumber + '</td>';

        previewHeaders.forEach(function (_header, colIndex) {
            const rawValue = values[colIndex] === null || values[colIndex] === undefined
                ? ''
                : String(values[colIndex]);
            const safeValue = escapeHtml(rawValue);
            const dataValue = rawValue.trim() === '' ? '(Blank)' : rawValue.trim();
            const safeDataValue = escapeHtml(dataValue);
            const displayValue = rawValue.trim() === '' ? '-' : safeValue;

            html += '<td class="text-truncate col-data-' + colIndex + '" data-val="' + safeDataValue + '" style="max-width: 250px;" title="' + safeValue + '">' + displayValue + '</td>';
        });

        html += '</tr>';
        return html;
    }

    function setPreviewLoading(isLoading, message = 'Menyaring preview...') {
        if (!previewShell || !previewOverlay) {
            return;
        }

        if (previewLoadingText) {
            previewLoadingText.textContent = message;
        }

        previewShell.classList.toggle('is-preview-loading', Boolean(isLoading));
        previewOverlay.classList.toggle('d-none', !isLoading);
    }

    function getFilteredValues(col) {
        const state = filterState[col];
        if (!state) {
            return [];
        }

        const effectiveValues = state.allValues.slice();

        const term = (searchTerms[col] || '').toLowerCase();
        if (!term) {
            return effectiveValues;
        }

        return effectiveValues.filter(function (value) {
            return value.toLowerCase().includes(term);
        });
    }

    function syncSelectAllCheckbox(col, filteredValues) {
        const selectAll = document.getElementById('select_all_' + col);
        const state = filterState[col];

        if (!selectAll || !state) {
            return;
        }

        selectAll.disabled = Boolean(state.isLoading);

        const visibleCheckboxes = Array.from(document.querySelectorAll('.filter-checkbox[data-col="' + col + '"]'));
        const visibleValues = visibleCheckboxes.map(function (checkbox) {
            return String(checkbox.value || '').trim();
        });
        const comparisonValues = visibleValues.length ? visibleValues : filteredValues;

        if (!comparisonValues.length) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
            return;
        }

        let checkedCount = 0;
        comparisonValues.forEach(function (value) {
            if (state.selectedValues.has(value)) {
                checkedCount++;
            }
        });

        selectAll.checked = checkedCount === comparisonValues.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < comparisonValues.length;
    }

    function syncVisibleFilterCheckboxes(col) {
        const state = filterState[col];
        if (!state) {
            return;
        }

        document.querySelectorAll('.filter-checkbox[data-col="' + col + '"]').forEach(function (checkbox) {
            checkbox.checked = state.selectedValues.has(String(checkbox.value || '').trim());
        });
    }

    function clearDependentFilterSignatures(changedCol) {
        Object.keys(filterState).forEach(function (key) {
            if (String(key) !== String(changedCol) && filterState[key]) {
                filterState[key].loadedSignature = '';
            }
        });
    }

    function scheduleDependentFilterRefresh(changedCol) {
        debounceRender(changedCol + '_refresh', function() {
            refreshDependentFilterOptions(changedCol);
        }, 300);
    }

    function applySelectAllState(colIndex, isChecked) {
        const state = filterState[colIndex];
        if (!state) {
            return;
        }

        if (state.isLoading) {
            syncSelectAllCheckbox(colIndex, getFilteredValues(colIndex));
            return;
        }

        const values = getFilteredValues(colIndex);

        values.forEach(function (value) {
            if (isChecked) {
                state.selectedValues.add(value);
            } else {
                state.selectedValues.delete(value);
            }
        });

        renderFilterList(colIndex);

        clearDependentFilterSignatures(colIndex);
        updatePreviewTable();
        scheduleDependentFilterRefresh(colIndex);
    }

    function renderFilterList(col) {
        const container = document.getElementById('list_container_' + col);
        const state = filterState[col];

        if (!container || !state) {
            return;
        }

        const filteredValues = getFilteredValues(col);
        const visibleValues = filteredValues.slice(0, filterRenderLimit);
        let html = '';

        if (state.isLoading && !state.fullOptionsLoaded) {
            html = '<div class="text-center text-muted py-3 small">' +
                '<i class="fas fa-spinner fa-spin mr-2"></i>Memuat opsi filter lengkap...</div>';
        } else if (!filteredValues.length) {
            html = state.isLoading
                ? '<div class="text-center text-muted py-2 small">' +
                  '<i class="fas fa-spinner fa-spin mr-2"></i>Memuat opsi filter...</div>'
                : '<div class="text-center text-muted py-2 small">Tidak ada opsi yang cocok.</div>';
        } else {
            if (state.isLoading) {
                html += '<div class="small text-muted mb-2"><i class="fas fa-spinner fa-spin mr-1"></i>Memindahkan nilai dari file sumber...</div>';
            }

            if (filteredValues.length > filterRenderLimit) {
                html += '<div class="small text-muted mb-2">Menampilkan ' + filterRenderLimit + ' dari ' + filteredValues.length + ' opsi. Gunakan pencarian untuk mempersempit.</div>';
            }

            visibleValues.forEach(function (value, index) {
                const safeValue = escapeHtml(value);
                const inputId = 'filter_' + col + '_' + index;
                html += '<div class="custom-control custom-checkbox filter-item-container mb-1">';
                html += '<input class="custom-control-input filter-checkbox" type="checkbox" id="' + inputId + '" value="' + safeValue + '" data-col="' + col + '"' + (state.selectedValues.has(value) ? ' checked' : '') + '>';
                html += '<label for="' + inputId + '" class="custom-control-label font-weight-normal filter-label">' + safeValue + '</label>';
                html += '</div>';
            });
        }

        container.innerHTML = html;
        syncVisibleFilterCheckboxes(col);
        syncSelectAllCheckbox(col, filteredValues);
    }

    async function ensureFullFilterOptions(col, isInitialPrefetch = false) {
        const state = filterState[col];
        if (!state || state.isLoading || !filterOptionsUrl || !filePathValue) {
            if (state && state.isLoading) {
                const activeFilters = buildActiveFilterContext(col);
                const signature = buildActiveFilterSignature(activeFilters);
                if (state.pendingSignature !== signature) {
                    state.needsRefresh = true;
                }
            }
            return;
        }

        const activeFilters = buildActiveFilterContext(col);
        const signature = buildActiveFilterSignature(activeFilters);

        if (state.fullOptionsLoaded && state.loadedSignature === signature) {
            renderFilterList(col);
            return;
        }

        // Cek cache localStorage jika prefetch atau tidak ada active filters
        if ((isInitialPrefetch || Object.keys(activeFilters).length === 0) && !state.fullOptionsLoaded) {
            const cachedValues = getFromLocalStorage(col);
            if (cachedValues) {
                replaceFilterOptions(state, cachedValues);
                state.fullOptionsLoaded = true;
                state.loadedSignature = signature;
                renderFilterList(col);
                return;
            }
        }

        state.isLoading = true;
        state.pendingSignature = signature;
        state.needsRefresh = false;
        renderFilterList(col);
        let shouldRender = false;

        try {
            const url = new URL(filterOptionsUrl, window.location.origin);
            url.searchParams.set('file_path', filePathValue);
            url.searchParams.set('delimiter', delimiterValue);
            url.searchParams.set('column_index', String(col));
            url.searchParams.set('display_filter_map_json', JSON.stringify(displayFilterMap || {}));
            url.searchParams.set('active_filters_json', JSON.stringify(activeFilters || {}));
            if (previewStateKey) {
                url.searchParams.set('preview_state_key', previewStateKey);
            }
            url.searchParams.set('_', String(Date.now()));

            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                cache: 'no-store',
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok || payload.status !== 'success' || !Array.isArray(payload.values)) {
                throw new Error(payload.message || 'Gagal memuat opsi filter lengkap.');
            }

            const normalizedValues = normalizeFilterValues(payload.values);

            if (state.pendingSignature !== signature || state.needsRefresh) {
                return;
            }

            replaceFilterOptions(state, normalizedValues);
            state.fullOptionsLoaded = true;
            state.loadedSignature = signature;
            
            // Simpan ke cache untuk next load
            if (isInitialPrefetch || Object.keys(activeFilters).length === 0) {
                saveToLocalStorage(col, normalizedValues);
            }
            
            shouldRender = true;
        } catch (error) {
            console.error(error);
        } finally {
            state.isLoading = false;
            if (state.needsRefresh) {
                state.needsRefresh = false;
                ensureFullFilterOptions(col, isInitialPrefetch);
                return;
            }

            if (shouldRender) {
                renderFilterList(col);
                if (!isInitialPrefetch) {
                    updatePreviewTable();
                }
            }
        }
    }

    // Prefetch semua filter options secara parallel saat page load
    async function prefetchAllFilterOptions() {
        if (disableFilterPrefetch) return;

        let cols = getRenderableFilterColumns();
        if (!cols.length) return;

        // Wide reports hydrate their branch selector first. Other high-cardinality
        // columns remain available through the existing on-demand request.
        if (cols.length > 8) {
            const priorityColumns = resolvePriorityFilterColumns();
            cols = priorityColumns.length ? priorityColumns : cols.slice(0, 8);
        }

        const prefetchPromises = cols.map(col => ensureFullFilterOptions(col, true));
        try {
            await Promise.allSettled(prefetchPromises);
        } catch (e) {
            console.warn('Prefetch filter options partially failed:', e);
        }
    }

    async function prefetchPriorityFilterOptions() {
        if (disableFilterPrefetch) return;

        const cols = resolvePriorityFilterColumns();
        if (!cols.length) return;

        await Promise.allSettled(cols.map(function (col) {
            return ensureFullFilterOptions(col, true);
        }));
    }

    function refreshDependentFilterOptions(excludeCol) {
        Object.keys(filterState).forEach(function (key) {
            if (String(key) === String(excludeCol)) {
                return;
            }

            const container = document.getElementById('list_container_' + key);
            const dropdown = container ? container.closest('.dropdown') : null;
            const isOpen = dropdown && (dropdown.classList.contains('show') || dropdown.querySelector('.dropdown-menu').classList.contains('show'));

            if (isOpen) {
                ensureFullFilterOptions(key);
            }
        });
    }

    /* =========================================================
       PREVIEW TABLE FILTER
    ========================================================= */
    function renderSamplePreviewTable(activeFilters) {
        if (!previewTbody) {
            return;
        }

        if (previewViewMode !== 'sample') {
            previewTbody.innerHTML = basePreviewTbodyHtml;
            previewViewMode = 'sample';
        }
        setPreviewLoading(false);

        const filterReqs = [];
        for (const col in activeFilters) {
            filterReqs.push({
                index: parseInt(col, 10) + 1,
                allowed: activeFilters[col]
            });
        }

        let matchingCount = 0;
        document.querySelectorAll('.preview-row').forEach(function (row) {
            let pass = true;

            for (let i = 0; i < filterReqs.length; i++) {
                const req = filterReqs[i];
                if (req.allowed.length === 0) {
                    pass = false;
                    break;
                }

                const cell = row.children[req.index];
                if (cell) {
                    const cellVal = (cell.getAttribute('data-val') || '').trim();
                    if (!req.allowed.includes(cellVal)) {
                        pass = false;
                        break;
                    }
                }
            }

            if (pass) {
                if (matchingCount < 100) {
                    row.classList.remove('d-none');
                } else {
                    row.classList.add('d-none');
                }
                matchingCount++;
            } else {
                row.classList.add('d-none');
            }
        });

        const emptyRow = document.getElementById('empty-state-row');
        if (emptyRow) {
            emptyRow.classList.toggle('d-none', matchingCount > 0);
        }

        updateFilterIcons();
    }

    async function renderFilteredPreviewTable(activeFilters) {
        if (!previewTbody || !filteredRowsUrl) {
            renderSamplePreviewTable(activeFilters);
            return;
        }

        const requestToken = ++previewRenderToken;
        previewViewMode = 'filtered';
        setPreviewLoading(true, 'Mengambil baris yang cocok dari file sumber...');

        try {
            const url = new URL(filteredRowsUrl, window.location.origin);
            url.searchParams.set('file_path', filePathValue);
            url.searchParams.set('delimiter', delimiterValue);
            url.searchParams.set('display_filter_map_json', JSON.stringify(displayFilterMap || {}));
            url.searchParams.set('active_filters_json', JSON.stringify(activeFilters || {}));
            url.searchParams.set('limit', '100');
            if (previewStateKey) {
                url.searchParams.set('preview_state_key', previewStateKey);
            }
            url.searchParams.set('_', String(Date.now()));

            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                cache: 'no-store',
            });

            const payload = await response.json().catch(() => ({}));
            if (requestToken !== previewRenderToken) {
                return;
            }

            if (!response.ok || payload.status !== 'success' || !Array.isArray(payload.rows)) {
                throw new Error(payload.message || 'Gagal memuat preview hasil filter.');
            }

            const rows = payload.rows || [];
            if (!rows.length) {
                setPreviewLoading(false);
                previewTbody.innerHTML = `
                    <tr>
                        <td colspan="{{ count($headers) + 1 }}" class="text-center py-5 bg-white text-muted">
                            <i class="fas fa-search-minus fa-3x mb-3 text-secondary"></i><br>
                            <h5 class="font-weight-bold text-dark">Tidak ada baris yang cocok di file sumber</h5>
                            <p class="mb-0">Filter yang dipilih tidak menemukan baris hasil di file asli.</p>
                        </td>
                    </tr>`;
                updateFilterIcons();
                return;
            }

            let html = rows.map(function (row, index) {
                return buildPreviewRowHtml(row, index + 1);
            }).join('');

            if (payload.truncated) {
                html += `
                    <tr>
                        <td colspan="{{ count($headers) + 1 }}" class="text-center py-3 bg-light text-muted">
                            Menampilkan 100 baris pertama dari hasil yang cocok di file sumber.
                        </td>
                    </tr>`;
            }

            previewTbody.innerHTML = html;
        } catch (error) {
            if (requestToken !== previewRenderToken) {
                return;
            }

            setPreviewLoading(false);
            previewTbody.innerHTML = `
                <tr>
                    <td colspan="{{ count($headers) + 1 }}" class="text-center py-5 bg-white text-muted">
                        <i class="fas fa-exclamation-triangle fa-3x mb-3 text-warning"></i><br>
                        <h5 class="font-weight-bold text-dark">Gagal memuat preview hasil filter</h5>
                        <p class="mb-0">${escapeHtml(error.message || 'Silakan coba lagi.')}</p>
                    </td>
                </tr>`;
        } finally {
            if (requestToken === previewRenderToken) {
                setPreviewLoading(false);
            }
            updateFilterIcons();
        }
    }

    function updatePreviewTable() {
        const activeFilters = {};
        Object.keys(filterState).forEach(function (col) {
            const state = filterState[col];
            if (!state) {
                return;
            }

            if (state.selectedValues.size === state.allValues.length) {
                return;
            }

            activeFilters[col] = Array.from(state.selectedValues);
        });

        const activeFiltersInput = document.getElementById('active_filters_json');
        if (activeFiltersInput) {
            activeFiltersInput.value = JSON.stringify(activeFilters);
        }

        if (Object.keys(activeFilters).length === 0) {
            if (previewRefreshTimer) {
                clearTimeout(previewRefreshTimer);
                previewRefreshTimer = null;
            }
            previewRenderToken++;
            renderSamplePreviewTable({});
            return;
        }

        renderSamplePreviewTable(activeFilters);

        if (previewRefreshTimer) {
            clearTimeout(previewRefreshTimer);
        }

        previewRefreshTimer = setTimeout(function () {
            previewRefreshTimer = null;
            renderFilteredPreviewTable(activeFilters);
        }, 180);
    }

    /* =========================================================
       FILTER ICON COLOR (biru jika ada filter aktif)
    ========================================================= */
    function updateFilterIcons() {
        document.querySelectorAll('.dropdown').forEach(function (dropdown) {
            var container = dropdown.querySelector('[id^="list_container_"]');
            if (!container) return;
            var colIndex  = container.id.split('_')[2];
            var state     = filterState[colIndex];
            var icon      = document.getElementById('icon_filter_' + colIndex);
            if (!icon) return;
            if (state && state.selectedValues.size < state.allValues.length) {
                icon.classList.remove('text-muted');
                icon.classList.add('text-primary');
            } else {
                icon.classList.remove('text-primary');
                icon.classList.add('text-muted');
            }
        });
    }

    /* =========================================================
       EVENT: Filter checkbox change
    ========================================================= */
    document.addEventListener('change', function (e) {
        if (!e.target.classList.contains('filter-checkbox')) {
            return;
        }

        const colIndex = e.target.getAttribute('data-col');
        const state = filterState[colIndex];
        if (!state) {
            return;
        }

        const value = e.target.value.trim();
        if (e.target.checked) {
            state.selectedValues.add(value);
        } else {
            state.selectedValues.delete(value);
        }

        clearDependentFilterSignatures(colIndex);

        syncSelectAllCheckbox(colIndex, getFilteredValues(colIndex));
        syncVisibleFilterCheckboxes(colIndex);
        updatePreviewTable();
        scheduleDependentFilterRefresh(colIndex);
    });

    /* =========================================================
       EVENT: Select All checkbox
    ========================================================= */
    document.querySelectorAll('.select-all-cb').forEach(function (cb) {
        cb.addEventListener('change', function () {
            const colIndex = this.getAttribute('data-col');
            applySelectAllState(colIndex, this.checked);
        });
    });

    /* =========================================================
       EVENT: Search filter
    ========================================================= */
    document.querySelectorAll('.search-filter').forEach(function (input) {
        input.addEventListener('keyup', function () {
            var colIndex  = this.getAttribute('data-col');
            searchTerms[colIndex] = this.value || '';
            
            // Debounce render untuk smooth search experience
            debounceRender(colIndex + '_search', function() {
                renderFilterList(colIndex);
            }, 150);
        });
    });

    /* =========================================================
       EVENT: Clear Cache button
    ========================================================= */
    document.getElementById('btnClearImportCache')?.addEventListener('click', function () {
        try {
            const keysToRemove = [];
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith('preview_filter_excel_')) {
                    keysToRemove.push(key);
                }
            }
            keysToRemove.forEach(key => localStorage.removeItem(key));
        } catch (e) {}

        Object.keys(filterState).forEach(function (col) {
            const state = filterState[col];
            if (state) {
                state.fullOptionsLoaded = false;
                state.loadedSignature = '';
            }
        });

        themedSwal({ icon: 'success', title: 'Cache Dibersihkan', text: 'Opsi filter akan dimuat ulang dari sumber.', timer: 1500, showConfirmButton: false });
    });

    /* =========================================================
       EVENT: Reset Filter button
    ========================================================= */
    document.getElementById('btnResetAllFilters')?.addEventListener('click', function () {
        Object.keys(filterState).forEach(function (col) {
            const state = filterState[col];
            if (state) {
                state.selectedValues = new Set(state.allValues);
            }
        });
        document.querySelectorAll('.filter-checkbox').forEach(cb => cb.checked = true);
        document.querySelectorAll('.select-all-cb').forEach(cb => cb.checked = true);
        updatePreviewTable();
        updateFilterIcons();
    });

    document.querySelectorAll('.dropdown').forEach(function (dropdown) {
        dropdown.addEventListener('shown.bs.dropdown', async function () {
            var container = dropdown.querySelector('[id^="list_container_"]');
            if (!container) {
                return;
            }

            var col = container.getAttribute('data-col');
            renderFilterList(col);
            await ensureFullFilterOptions(col);
        });
    });

    /* =========================================================
       IMPORT FORM SUBMIT — SSE dengan auto-reconnect
    ========================================================= */
    document.getElementById('importForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        var submitBtn    = document.getElementById('btnSubmitImport');
        var csrfToken    = document.querySelector('input[name="_token"]').value;
        var pathValue    = document.getElementById('file_path').value;
        var fetchHeaders = {
            'X-CSRF-TOKEN'     : csrfToken,
            'Accept'           : 'application/json',
            'X-Requested-With' : 'XMLHttpRequest',
        };

        // ── Kumpulkan filter aktif ──────────────────────────────────────────
        var activeFilters = {};
        if (!filtersDisabled) {
            Object.keys(filterState).forEach(function (colIndex) {
                var state = filterState[colIndex];
                if (!state) {
                    return;
                }

                if (state.selectedValues.size < state.allValues.length) {
                    activeFilters[colIndex] = Array.from(state.selectedValues);
                }
            });
        }
        var filtersJson = JSON.stringify(activeFilters);
        document.getElementById('active_filters_json').value = filtersJson;

        // ── Disable tombol ──────────────────────────────────────────────────
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
        }

        // ── Modal loading ───────────────────────────────────────────────────
        var loadingCopy = resolveLoadingCopy();
        var swalHtml = `
            <div class="swal-import-shell">
                <div class="swal-import-head">
                    <span class="swal-import-badge"><i class="fas fa-circle-notch fa-spin mr-1"></i> Sedang diproses</span>
                    <div class="swal-import-desc">${loadingCopy.description}</div>
                </div>
                <div class="swal-import-card">
                    <div class="swal-import-card__top">
                        <span class="swal-import-label">Progress</span>
                        <span class="swal-import-percent" id="swal-progress-percent">0%</span>
                    </div>
                    <div class="progress swal-import-progress" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                        <div id="swal-progress-bar"
                             class="progress-bar swal-import-progress__bar progress-bar-striped progress-bar-animated bg-success"
                             style="width:0%;font-weight:700;font-size:13px;transition:width .6s ease;line-height:22px;">0%</div>
                    </div>
                    <div id="swal-status-text" class="swal-import-meta">
                        <small class="swal-import-meta__status">Memeriksa struktur file dan database...</small>
                    </div>
                </div>
                <div class="swal-import-stats swal-import-stats--compact">
                    <div class="swal-import-stat">
                        <span class="swal-import-stat__label">Baris</span>
                        <span id="swal-rows-info" class="swal-import-stat__value">0 / -</span>
                    </div>
                    <div class="swal-import-stat">
                        <span class="swal-import-stat__label">Kecepatan</span>
                        <span id="swal-speed-info" class="swal-import-stat__value">-</span>
                    </div>
                </div>
            </div>`;

        themedSwal({
            title: '<i class="fas fa-cloud-upload-alt text-success mr-1"></i> ' + loadingCopy.title,
            html: swalHtml,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            width: 560,
        });

        // ── Helper: aktifkan step indicator ────────────────────────────────
        function activateStep(stepId, lineId) {
            var el = document.getElementById(stepId);
            if (!el) return;
            var icon = el.querySelector('.step-icon');
            if (icon) {
                icon.style.background = '#28a745';
                icon.querySelectorAll('i').forEach(function (i) {
                    i.classList.remove('text-muted');
                    i.classList.add('text-white');
                });
            }
            if (lineId) {
                var line = document.getElementById(lineId);
                if (line) line.style.background = '#28a745';
            }
        }

        function setProgress(pct, statusText, rowsDone, total, speed) {
            var bar = document.getElementById('swal-progress-bar');
            var pp  = document.getElementById('swal-progress-percent');
            var st  = document.getElementById('swal-status-text');
            var ri  = document.getElementById('swal-rows-info');
            var si  = document.getElementById('swal-speed-info');
            var normalized = normalizeProgressStatus(statusText);
            var resolvedRowsDone = Number(rowsDone || 0);
            var resolvedTotal = Number(total || 0);
            if (resolvedTotal <= 0 && ri && ri.dataset.totalRows) {
                resolvedTotal = Number(ri.dataset.totalRows || 0);
            }
            var effectiveSpeed = speed > 0 ? speed : (normalized.speed ? Number(normalized.speed) : 0);
            if (bar) { bar.style.width = pct + '%'; bar.innerText = pct + '%'; }
            if (pp)  pp.innerText = pct + '%';
            if (st)  st.innerText = normalized.message || statusText || '';
            if (ri) {
                if (resolvedTotal > 0) {
                    ri.dataset.totalRows = String(resolvedTotal);
                    ri.innerText = resolvedRowsDone.toLocaleString('id-ID') + ' / ' + resolvedTotal.toLocaleString('id-ID') + ' baris';
                } else {
                    ri.innerText = resolvedRowsDone > 0 ? resolvedRowsDone.toLocaleString('id-ID') + ' / - baris' : 'Menunggu total baris...';
                }
            }
            if (si)  si.innerText = effectiveSpeed > 0 ? Number(effectiveSpeed).toLocaleString('id-ID') + ' baris/detik' : '-';
        }

        function resetSubmitBtn() {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-database"></i> Jalankan Import ke MySQL';
            }
        }

        // ── STEP 1: Inisialisasi (POST) ─────────────────────────────────────
        activateStep('step-init', null);
        setProgress(5, loadingCopy.status, 0, 0, 0);

        var jobId;
        try {
            var formData = new FormData(importForm);
            formData.set('path', pathValue);
            formData.set('active_filters_json', filtersJson);

            var resRaw  = await fetch('{{ $initRoute ?? route("import.excel.init") }}', {
                method: 'POST', body: formData, headers: fetchHeaders,
            });
            var resText = await resRaw.text();
            var resInit;
            try {
                resInit = JSON.parse(resText);
            } catch (err) {
                var titleMatch = resText.match(/<title>(.*?)<\/title>/i);
                throw new Error('<b>Server Error:</b> ' + (titleMatch ? titleMatch[1] : 'Unknown Error'));
            }

            var initDuplicate = resInit.duplicate_detected || resInit.redirect_url || isDuplicateImportMessage(resInit.text || resInit.message || resInit.title);
            if (initDuplicate) {
                await showDuplicateImportModal(resInit.text || resInit.message || resInit.title || 'Data duplikat terdeteksi.', resInit.title || 'Data Duplikat');
                resetSubmitBtn();
                return;
            }

            if (!resRaw.ok || resInit.status === 'error') {
                throw new Error(resInit.text || resInit.message || 'Gagal menyiapkan fase Polars.');
            }

            jobId = resInit.job_id;
            activateStep('step-init', 'line-1');
            setProgress(
                12,
                isDailyLoanPreview
                    ? 'Menyiapkan sanitasi CSV Daily Loan... (' + Number(resInit.total_rows || 0).toLocaleString('id-ID') + ' record)'
                    : 'Fase Polars siap. Membuka koneksi stream...',
                0,
                resInit.total_rows || 0,
                0
            );

        } catch (err) {
            var errorHtml = String((err && err.message) || '');
            var isDuplicate = isDuplicateImportMessage(errorHtml);
            var errorTitle = isDuplicate
                ? 'Data Duplikat'
                : 'Gagal Menyiapkan Polars';
            if (isDuplicate) {
                await showDuplicateImportModal(errorHtml, errorTitle);
            } else {
                themedSwal({
                    icon: 'error',
                    title: errorTitle,
                    text: plainImportMessage(errorHtml),
                    confirmButtonText: 'Tutup'
                });
            }
            resetSubmitBtn();
            return;
        }

        // ── STEP 2 & 3: SSE Stream dengan auto-reconnect ───────────────────
        var streamUrl  = '{{ $streamRoute ?? route("import.excel.stream") }}?job_id=' + encodeURIComponent(jobId);
        var statusUrlTemplate = @json(route('import.jobs.status', ['jobId' => '__JOB_ID__']));
        var forceStartUrlTemplate = @json(route('job-management.force-start', ['jobId' => '__JOB_ID__']));
        var evtSource  = null;
        var streamDone = false;
        var reconnectAttempts = 0;
        var forceStartTriggered = false;
        var terminalVerificationStarted = false;
        var lastProg   = { percent: 12, message: 'Fase Polars...', rows_done: 0, total: 0, speed: 0 };

        function statusUrlForJob(jobId) {
            return statusUrlTemplate.replace('__JOB_ID__', encodeURIComponent(jobId));
        }

        function forceStartUrlForJob(jobId) {
            return forceStartUrlTemplate.replace('__JOB_ID__', encodeURIComponent(jobId));
        }

        function isDuplicateImportMessage(message) {
            var text = String(message || '')
                .replace(/<[^>]*>/g, ' ')
                .replace(/&nbsp;/gi, ' ')
                .replace(/\s+/g, ' ')
                .toLowerCase();
            return text.indexOf('duplikat') !== -1
                || text.indexOf('sudah ada di database') !== -1
                || text.indexOf('sudah ada di tabel') !== -1
                || text.indexOf('sudah pernah diunggah') !== -1
                || text.indexOf('kombinasi periode + tid') !== -1
                || text.indexOf('mencegah data dobel') !== -1
                || text.indexOf('data dobel') !== -1
                || text.indexOf('duplicate entry') !== -1
                || text.indexOf('duplicate') !== -1;
        }

        function plainImportMessage(message) {
            var withoutTags = String(message || '')
                .replace(/<br\s*\/?>/gi, '\n')
                .replace(/<[^>]*>/g, ' ');
            var decoder = document.createElement('textarea');
            decoder.innerHTML = withoutTags;

            return decoder.value.replace(/[ \t]+/g, ' ').trim();
        }

        function redirectToSelectFile() {
            window.location.replace('{{ route("import.select") }}');
        }

        async function showDuplicateImportModal(message, title) {
            await themedSwal({
                icon: 'warning',
                title: plainImportMessage(title || 'Data Duplikat'),
                text: plainImportMessage(message || 'Data duplikat terdeteksi.'),
                confirmButtonText: 'Kembali ke Import',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didClose: function () {
                    redirectToSelectFile();
                },
            });
            redirectToSelectFile();
        }

        function showImportFailure(message) {
            streamDone = true;
            if (evtSource) evtSource.close();
            var failureHtml = message || 'Import gagal dijalankan!';
            var failureIsDuplicate = isDuplicateImportMessage(failureHtml);
            var failureTitle = failureIsDuplicate ? 'Data Duplikat' : 'Proses Terhenti';
            themedSwal({
                icon: failureIsDuplicate ? 'warning' : 'error',
                title: failureTitle,
                text: plainImportMessage(failureHtml),
                confirmButtonText: failureIsDuplicate ? 'Kembali ke Import' : 'Tutup',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didClose: failureIsDuplicate ? function () {
                    redirectToSelectFile();
                } : null,
            })
                .then(function () {
                    if (failureIsDuplicate) {
                        redirectToSelectFile();
                    }
                });
            resetSubmitBtn();
        }

        function isNonFatalProcessingMessage(message) {
            var text = String(message || '').toLowerCase();
            return text.indexOf('sedang diproses') !== -1
                || text.indexOf('import sedang diproses') !== -1
                || text.indexOf('job import ini sudah sedang diproses') !== -1
                || text.indexOf('job import masuk ke queue') !== -1
                || text.indexOf('menunggu worker queue') !== -1;
        }

        function showImportSuccess(d) {
            if (String((d || {}).status || 'completed') !== 'completed') {
                showImportFailure((d || {}).message || 'Import tidak selesai dengan status berhasil.');
                return;
            }

            streamDone = true;
            if (evtSource) evtSource.close();

            d = Object.assign({}, d || {});
            if (String(d.status || 'completed') === 'completed' && Number(d.total_success || 0) === 0) {
                var inferredSuccess = Number(d.processed_rows || d.total_rows || 0) - Number(d.total_failed || 0);
                if (inferredSuccess > 0) {
                    d.total_success = inferredSuccess;
                }
            }

            var skippedCount = Number(d.skipped_count || 0);
            var skippedRows = Array.isArray(d.skipped_rows) ? d.skipped_rows : [];
            var skippedRowsText = skippedRows.length ? skippedRows.map(escapeHtml).join(', ') : '';
            var skippedHtml = skippedCount > 0
                ? '<br><small class="text-warning">Baris rusak di-skip: <b>' + skippedCount.toLocaleString('id-ID') + '</b>' +
                  (skippedRowsText ? '<br>Contoh baris: ' + skippedRowsText : '') +
                  '</small>'
                : '';

            activateStep('step-done', 'line-3');
            setProgress(100, 'Import selesai!', d.total_rows || 0, d.total_rows || 0, 0);

            setTimeout(function () {
                if (!d.total_success || d.total_success === 0) {
                    themedSwal({
                        icon: 'warning',
                        title: 'Tidak Ada Data Masuk',
                        html: '<p>Total: <b>' + Number(d.total_rows || 0).toLocaleString('id-ID') + ' baris</b></p>' +
                              '<p>Gagal: <b>' + Number(d.total_failed || 0).toLocaleString('id-ID') + ' baris</b></p>' +
                              '<small class="text-muted">Sebagian baris gagal diproses atau terbatasi oleh filter yang aktif.</small>' +
                              skippedHtml,
                        confirmButtonText: 'Kembali ke Import',
                    }).then(function () {
                        if (typeof window.showRouteLoading === 'function') {
                            window.showRouteLoading('Memuat halaman', 'Menyiapkan tampilan berikutnya dengan data terbaru.');
                        }
                        window.location.href = '{{ route("import.index") }}';
                    });
                } else {
                    themedSwal({
                        icon: d.total_failed > 0 ? 'warning' : 'success',
                        title: d.total_failed > 0 ? 'Import Memiliki Kendala' : 'Import Sukses',
                        html: 'Berhasil mengimport <b>' + Number(d.total_success).toLocaleString('id-ID') + ' baris</b> data ke database.' +
                              (d.total_failed > 0 ? '<br><small class="text-warning">' + Number(d.total_failed).toLocaleString('id-ID') + ' baris gagal saat insert atau tidak lolos proses validasi.</small>' : '') +
                              skippedHtml,
                        confirmButtonText: 'Lanjut',
                    }).then(function () {
                        if (typeof window.showRouteLoading === 'function') {
                            window.showRouteLoading('Memuat halaman', 'Menyiapkan tampilan berikutnya dengan data terbaru.');
                        }
                        window.location.href = '{{ route("import.index") }}';
                    });
                }
            }, 600);
        }

        async function inspectImportJob(jobId) {
            var response = await fetch(statusUrlForJob(jobId), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                return null;
            }

            return await response.json();
        }

        async function triggerForceStart(jobId) {
            if (forceStartTriggered) return false;
            forceStartTriggered = true;

            var response = await fetch(forceStartUrlForJob(jobId), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({})
            });

            var payload = {};
            try { payload = await response.json(); } catch (_) {}

            if (!response.ok || payload.status === 'error') {
                forceStartTriggered = false;
                throw new Error(payload.message || 'Gagal menjalankan force start import.');
            }

            return true;
        }

        function shouldForceStartQueuedJob(statusPayload) {
            if (!statusPayload || String(statusPayload.status || '') !== 'queued') {
                return false;
            }

            return Boolean(statusPayload.is_stale_queue);
        }

        async function pollImportStatus(jobId) {
            for (;;) {
                var payload = null;

                try {
                    payload = await inspectImportJob(jobId);
                } catch (_) {
                    payload = null;
                }

                if (payload) {
                    lastProg = {
                        percent: payload.percent != null ? payload.percent : lastProg.percent,
                        message: payload.message != null ? payload.message : lastProg.message,
                        rows_done: payload.processed_rows != null ? payload.processed_rows : lastProg.rows_done,
                        total: payload.total_rows != null ? payload.total_rows : lastProg.total,
                        speed: lastProg.speed
                    };

                    if (lastProg.percent >= 5 && lastProg.percent < 22) activateStep('step-read', 'line-2');
                    if (lastProg.percent >= 22) { activateStep('step-read', 'line-2'); activateStep('step-insert', 'line-3'); }

                    setProgress(lastProg.percent, lastProg.message, lastProg.rows_done, lastProg.total, lastProg.speed);

                    if (payload.status === 'completed') {
                        showImportSuccess(payload);
                        return;
                    }

                    if (payload.status === 'failed' || payload.status === 'failed_partial' || payload.status === 'terminated' || payload.status === 'error') {
                        showImportFailure(payload.message || 'Import gagal dijalankan!');
                        return;
                    }
                }

                await new Promise(function (resolve) { setTimeout(resolve, 1000); });
            }
        }

        function connectSSE() {
            if (streamDone) return;
            evtSource = new EventSource(streamUrl);

            // ── progress event ──────────────────────────────────────────────
            evtSource.addEventListener('progress', function (e) {
                reconnectAttempts = 0;
                var d = {};
                try { d = JSON.parse(e.data); } catch (_) {}
                if (!d) return;

                lastProg = {
                    percent:  d.percent   != null ? d.percent   : lastProg.percent,
                    message:  d.message   != null ? d.message   : lastProg.message,
                    rows_done: d.rows_done != null ? d.rows_done : (d.processed_rows != null ? d.processed_rows : lastProg.rows_done),
                    total:    d.total     != null ? d.total     : (d.total_rows != null ? d.total_rows : lastProg.total),
                    speed:    d.speed     != null ? d.speed     : lastProg.speed,
                };

                if (lastProg.percent >= 5  && lastProg.percent < 22) activateStep('step-read',   'line-2');
                if (lastProg.percent >= 22) { activateStep('step-read', 'line-2'); activateStep('step-insert', 'line-3'); }

                setProgress(lastProg.percent, lastProg.message, lastProg.rows_done, lastProg.total, lastProg.speed);
            });

            // ── complete event ──────────────────────────────────────────────
            evtSource.addEventListener('complete', async function (e) {
                if (terminalVerificationStarted || streamDone) return;
                terminalVerificationStarted = true;

                var eventPayload = {};
                try { eventPayload = JSON.parse(e.data); } catch (_) {}

                if (evtSource) evtSource.close();

                var verifiedPayload = null;
                try { verifiedPayload = await inspectImportJob(jobId); } catch (_) {}

                if (verifiedPayload && verifiedPayload.status === 'completed') {
                    showImportSuccess(Object.assign({}, eventPayload, verifiedPayload));
                    return;
                }

                if (verifiedPayload && ['failed', 'failed_partial', 'terminated', 'error'].indexOf(verifiedPayload.status) !== -1) {
                    showImportFailure(verifiedPayload.message || 'Import gagal dijalankan!');
                    return;
                }

                terminalVerificationStarted = false;
                pollImportStatus(jobId);
                return;
                var d = {};
                try { d = JSON.parse(e.data); } catch (_) {}
                var skippedCount = Number(d.skipped_count || 0);
                var skippedRows = Array.isArray(d.skipped_rows) ? d.skipped_rows : [];
                var skippedRowsText = skippedRows.length ? skippedRows.map(escapeHtml).join(', ') : '';
                var skippedHtml = skippedCount > 0
                    ? '<br><small class="text-warning">Baris rusak di-skip: <b>' + skippedCount.toLocaleString('id-ID') + '</b>' +
                      (skippedRowsText ? '<br>Contoh baris: ' + skippedRowsText : '') +
                      '</small>'
                    : '';

                activateStep('step-done', 'line-3');
                setProgress(100, 'Import selesai!', d.total_rows || 0, d.total_rows || 0, 0);

                setTimeout(function () {
                    if (!d.total_success || d.total_success === 0) {
                        themedSwal({
                            icon: 'warning',
                            title: 'Tidak Ada Data Masuk',
                            html: '<p>✅ Total: <b>' + Number(d.total_rows || 0).toLocaleString('id-ID') + ' baris</b></p>' +
                                  '<p>❌ Gagal: <b>' + Number(d.total_failed || 0).toLocaleString('id-ID') + ' baris</b></p>' +
                                  '<small class="text-muted">Sebagian baris gagal diproses atau terbatasi oleh filter yang aktif.</small>' +
                                  skippedHtml,
                            confirmButtonText: 'Kembali ke Import',
                        }).then(function () {
                            if (typeof window.showRouteLoading === 'function') {
                                window.showRouteLoading('Memuat halaman', 'Menyiapkan tampilan berikutnya dengan data terbaru.');
                            }
                            window.location.href = '{{ route("import.index") }}';
                        });
                    } else {
                        themedSwal({
                            icon: 'success',
                            title: 'Import Sukses! 🎉',
                            html: 'Berhasil mengimport <b>' + Number(d.total_success).toLocaleString('id-ID') + ' baris</b> data ke database.' +
                                  (d.total_failed > 0 ? '<br><small class="text-warning">⚠ ' + Number(d.total_failed).toLocaleString('id-ID') + ' baris gagal saat insert atau tidak lolos proses validasi.</small>' : '') +
                                  skippedHtml,
                            confirmButtonText: 'Lanjut',
                        }).then(function () {
                            if (typeof window.showRouteLoading === 'function') {
                                window.showRouteLoading('Memuat halaman', 'Menyiapkan tampilan berikutnya dengan data terbaru.');
                            }
                            window.location.href = '{{ route("import.index") }}';
                        });
                    }
                }, 600);
            });

            // ── error event (server kirim event error) ──────────────────────
            evtSource.addEventListener('error', function (e) {
                if (streamDone) return;
                var rawData = typeof e.data === 'string' ? e.data.trim() : '';
                if (!rawData) return;
                var msg = lastProg.message || 'Terjadi kesalahan server.';
                try {
                    var d = JSON.parse(rawData);
                    if (d && d.message) msg = d.message;
                } catch (_) {
                    msg = rawData || msg;
                }
                if (isNonFatalProcessingMessage(msg)) return;
                showImportFailure(msg);
            });

            // ── onerror (koneksi putus / network drop) ──────────────────────
            evtSource.onerror = async function () {
                if (streamDone) return;
                evtSource.close();

                var statusPayload = null;
                try {
                    statusPayload = await inspectImportJob(jobId);
                } catch (_) {}

                var status = String(statusPayload && statusPayload.status ? statusPayload.status : '');
                if (status === 'completed') {
                    showImportSuccess(statusPayload || {});
                    return;
                }

                if (status === 'queued' && statusPayload && statusPayload.is_stale_queue) {
                    showImportFailure((statusPayload && statusPayload.message) || 'Job import terlalu lama berada di antrian.');
                    return;
                }

                        if (status === 'queued' || status === 'processing') {
                            reconnectAttempts += 1;
                            if (shouldForceStartQueuedJob(statusPayload) && !forceStartTriggered) {
                                try {
                                    setProgress(
                                        Math.max(lastProg.percent || 12, 12),
                                        'Koneksi stream gagal dibuka. Menjalankan force start import...',
                                        lastProg.rows_done || 0,
                                        lastProg.total || 0,
                                        lastProg.speed || 0
                                    );
                                    await triggerForceStart(jobId);
                                    await pollImportStatus(jobId);
                                    return;
                                } catch (forceStartError) {
                                    const refreshedStatus = await inspectImportJob(jobId).catch(function () {
                                        return null;
                                    });
                                    const refreshedState = String(refreshedStatus && refreshedStatus.status ? refreshedStatus.status : '');

                                    if (refreshedState === 'completed') {
                                        showImportSuccess(refreshedStatus || {});
                                        return;
                                    }

                                    if (refreshedState === 'queued' || refreshedState === 'processing') {
                                        reconnectAttempts = 0;
                                        setProgress(
                                            Math.max(lastProg.percent || 12, 12),
                                            (refreshedStatus && refreshedStatus.message) || 'Import sedang diproses di backend. Menyambung ulang progress...',
                                            lastProg.rows_done || 0,
                                            lastProg.total || 0,
                                            lastProg.speed || 0
                                        );
                                        setTimeout(connectSSE, 1000);
                                        return;
                                    }

                                    showImportFailure((forceStartError && forceStartError.message) || 'Gagal menjalankan force start import.');
                                    return;
                                }
                            }

                            setProgress(
                                Math.max(lastProg.percent || 12, 12),
                                (statusPayload && statusPayload.message) || 'Import sedang diproses. Menyambung ulang progress...',
                                lastProg.rows_done || 0,
                                lastProg.total || 0,
                                lastProg.speed || 0
                            );
                            setTimeout(connectSSE, 1000 * Math.min(reconnectAttempts, 5));
                            return;
                        }

                        if (status === 'failed' || status === 'failed_partial' || status === 'error') {
                            showImportFailure((statusPayload && statusPayload.message) || 'Import gagal dijalankan!');
                            return;
                        }

                reconnectAttempts += 1;
                if (reconnectAttempts <= 5) {
                    setProgress(lastProg.percent, 'Koneksi progress terputus, mencoba menyambung ulang...', lastProg.rows_done, lastProg.total, lastProg.speed);
                    setTimeout(connectSSE, 1000 * reconnectAttempts);
                    return;
                }

                showImportFailure('Gagal terhubung ke server untuk update progress import.');
            };
        }

        connectSSE();
    });

    /* =========================================================
       INIT: terapkan filter default lalu tampilkan preview
    ========================================================= */
    if (filePathValue && filterOptionsUrl) {
        prefetchPriorityFilterOptions().catch(function (error) {
            console.warn('Priority filter prefetch failed:', error);
        });
    }

    Object.keys(filterState).forEach(function (col) {
        renderFilterList(col);
    });
    updatePreviewTable();
    
    // 🚀 Prefetch semua filter options saat page load untuk instant display
    // Dilakukan secara parallel menggunakan Promise.allSettled
    if (filePathValue && filterOptionsUrl) {
        prefetchAllFilterOptions().catch(e => console.warn('Prefetch error:', e));
    }
});
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(function () {
            var rows = Array.prototype.slice.call(document.querySelectorAll('.preview-row'));
            if (!rows.length || rows.some(function (row) { return !row.classList.contains('d-none'); })) {
                return;
            }

            var activeFiltersInput = document.getElementById('active_filters_json');
            try {
                var activeFilters = JSON.parse(activeFiltersInput ? activeFiltersInput.value || '{}' : '{}');
                if (Object.keys(activeFilters).length > 0) {
                    return;
                }
            } catch (error) {
                return;
            }

            rows.slice(0, 100).forEach(function (row) {
                row.classList.remove('d-none');
            });

            var emptyRow = document.getElementById('empty-state-row');
            if (emptyRow) {
                emptyRow.classList.add('d-none');
            }
        }, 250);
    });
</script>

@endsection

@section('styles')
<style>
    .import-preview-banner,
    .import-preview-card {
        border: 1px solid rgba(226, 232, 240, 0.95) !important;
        border-radius: 8px;
        overflow: hidden;
        background: #ffffff;
        box-shadow: 0 18px 36px -30px rgba(15, 23, 42, 0.28) !important;
    }

    .import-preview-banner__body {
        padding: 1.2rem 1.35rem;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    }

    .import-preview-heading {
        min-width: 0;
    }

    .import-preview-heading__eyebrow {
        display: inline-flex;
        align-items: center;
        min-height: 26px;
        padding: 0.25rem 0.6rem;
        border-radius: 999px;
        background: #ecfdf5;
        border: 1px solid #bbf7d0;
        color: #047857;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .import-preview-heading__title {
        margin: 0.5rem 0 0.35rem;
        color: #0f172a;
        font-size: 1.22rem;
        font-weight: 800;
        line-height: 1.3;
        word-break: break-word;
    }

    .import-preview-heading__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .import-preview-heading__meta span {
        display: inline-flex;
        align-items: center;
        min-height: 28px;
        padding: 0.28rem 0.55rem;
        border-radius: 999px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        color: #475569;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .import-preview-notice {
        display: flex;
        align-items: flex-start;
        gap: 0.55rem;
        padding: 0.85rem 1rem;
        border-radius: 8px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1e293b;
        line-height: 1.55;
    }

    .import-preview-actionbar {
        padding: 1rem 1.35rem;
        border-bottom: 1px solid #e2e8f0 !important;
        background: #ffffff !important;
    }

    .import-preview-actionbar .card-tools {
        align-items: center;
    }

    .import-preview-actions {
        flex-wrap: wrap;
        gap: 0.5rem;
        justify-content: flex-end;
        margin-left: auto;
    }

    .import-preview-back,
    .import-preview-secondary,
    .import-preview-submit {
        min-height: 42px;
        border-radius: 8px;
        padding: 0.62rem 0.9rem;
        font-weight: 700;
    }

    .import-preview-submit {
        border: 0;
        background: #047857;
        box-shadow: 0 14px 24px -20px rgba(4, 120, 87, 0.55);
    }

    .import-preview-submit:hover,
    .import-preview-submit:focus {
        background: #065f46;
        box-shadow: 0 16px 28px -20px rgba(4, 120, 87, 0.6);
    }

    .import-preview-table-shell {
        position: relative;
        min-height: clamp(320px, 52dvh, 450px);
        max-height: min(68dvh, 680px);
        overflow-x: auto;
        overflow-y: auto;
        background: #ffffff;
    }

    .import-preview-table-shell.is-preview-loading .import-preview-table {
        opacity: 0.58;
        filter: saturate(0.92);
        transition: opacity 180ms ease, filter 180ms ease;
    }

    .preview-loading-overlay {
        position: absolute;
        inset: 0;
        z-index: 5;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding-top: 86px;
        pointer-events: none;
        background: rgba(248, 250, 252, 0.46);
        backdrop-filter: blur(1.5px);
    }

    .preview-loading-panel {
        display: inline-flex;
        align-items: center;
        gap: 0.65rem;
        min-height: 42px;
        padding: 0.65rem 0.95rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.96);
        border: 1px solid #dbeafe;
        box-shadow: 0 18px 42px -28px rgba(15, 23, 42, 0.45);
        color: #0f172a;
        font-size: 0.84rem;
        font-weight: 800;
    }

    .preview-loading-spinner {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        border: 2px solid #bfdbfe;
        border-top-color: #2563eb;
        animation: previewSpin 0.72s linear infinite;
    }

    @keyframes previewSpin {
        to { transform: rotate(360deg); }
    }

    .import-preview-table {
        font-size: 0.88rem;
    }

    .import-preview-table thead th {
        background: #f8fafc !important;
        border-color: #e2e8f0 !important;
        color: #0f172a;
        font-size: 0.82rem;
    }

    .import-preview-table tbody td {
        border-color: #edf2f7;
        vertical-align: middle;
    }

    .import-preview-table .filter-btn {
        border-radius: 8px;
        min-width: 30px;
        min-height: 30px;
    }

    .import-preview-table .dropdown-menu {
        border: 1px solid #e2e8f0;
        border-radius: 8px !important;
    }

    .import-preview-table .dropdown-menu .custom-control-label {
        display: block;
        min-height: 1.5rem;
        cursor: pointer;
        user-select: none;
    }

    .import-preview-table .dropdown-menu .custom-control-input:disabled ~ .custom-control-label {
        cursor: wait;
        opacity: 0.58;
    }

    @media (max-width: 767.98px) {
        .import-preview-actionbar .card-tools,
        .import-preview-actions {
            flex-direction: column;
            align-items: stretch !important;
        }

        .import-preview-table-shell {
            min-height: 360px;
            max-height: max(420px, calc(100dvh - 150px));
        }

        .import-preview-back,
        .import-preview-secondary,
        .import-preview-submit {
            width: 100%;
        }
    }

    .swal-modern-popup {
        border: 1px solid rgba(226, 232, 240, 0.95);
        border-radius: 28px;
        padding: 1.4rem 1.4rem 1.2rem;
        box-shadow: 0 30px 80px -35px rgba(15, 23, 42, 0.35);
    }

    .swal-modern-title {
        color: #0f172a;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .swal-modern-html {
        color: #475569;
        font-size: 0.95rem;
        line-height: 1.65;
    }

    .swal-modern-confirm {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 16px;
        background: linear-gradient(135deg, #0f766e, #115e59);
        color: #ffffff;
        font-weight: 700;
        padding: 0.8rem 1.3rem;
        box-shadow: 0 16px 34px -22px rgba(15, 23, 42, 0.45);
    }

    .swal-import-shell {
        display: grid;
        gap: 1rem;
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
        justify-content: center;
        width: fit-content;
        margin-inline: auto;
        padding: 0.4rem 0.72rem;
        border-radius: 999px;
        background: rgba(15, 118, 110, 0.1);
        color: #0f766e;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .swal-import-title {
        color: #0f172a;
        font-size: 1.08rem;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .swal-import-desc {
        color: #64748b;
        font-size: 0.92rem;
        line-height: 1.5;
    }

    .swal-import-card {
        padding: 1rem;
        border-radius: 20px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid rgba(148, 163, 184, 0.18);
        box-shadow: 0 18px 42px -32px rgba(15, 23, 42, 0.28);
    }

    .swal-import-card__top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.6rem;
    }

    .swal-import-label {
        color: #64748b;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .swal-import-percent {
        color: #0f172a;
        font-size: 0.92rem;
        font-weight: 800;
    }

    .swal-import-progress {
        height: 14px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
        box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
    }

    .swal-import-progress__bar {
        background: linear-gradient(135deg, #0f766e, #14b8a6);
        font-weight: 800;
        font-size: 11px;
        line-height: 14px;
    }

    .swal-import-meta {
        margin-top: 0.7rem;
    }

    .swal-import-meta__status {
        color: #0f766e;
        font-weight: 700;
        letter-spacing: 0.02em;
        display: block;
        word-break: break-word;
        white-space: normal;
    }

    .swal-import-stats {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .swal-import-stats--compact {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .swal-import-stat {
        padding: 0.85rem 0.9rem;
        border-radius: 16px;
        background: #f8fafc;
        border: 1px solid rgba(148, 163, 184, 0.15);
    }

    .swal-import-stat__label {
        display: block;
        margin-bottom: 0.25rem;
        color: #64748b;
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .swal-import-stat__value {
        display: block;
        color: #0f172a;
        font-size: 0.94rem;
        font-weight: 800;
    }

    .swal-modern-popup {
        width: min(520px, calc(100vw - 24px)) !important;
        padding: 0.95rem !important;
        border-radius: 8px;
    }

    .swal-import-shell {
        gap: 0.7rem;
    }

    .swal-import-head {
        gap: 0.3rem;
    }

    .swal-import-desc {
        font-size: 0.84rem;
        line-height: 1.4;
    }

    .swal-import-card,
    .swal-import-stat {
        padding: 0.75rem;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        background: #f8fafc;
        box-shadow: none;
    }

    .swal-import-progress__bar {
        background: #0b5cab;
    }
</style>
@endsection
