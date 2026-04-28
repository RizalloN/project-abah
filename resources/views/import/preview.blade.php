@extends('layouts.admin')

@section('title', $pageTitle ?? 'Preview & Filter Data Excel')

@section('content')
@php
    $filtersDisabled = !empty($filtersDisabled);
@endphp
<div class="row">
    <div class="col-12">

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if(empty($hideDelimiterCard))
        <div class="card card-outline card-primary mb-3">
            <div class="card-header bg-light">
                <h3 class="card-title font-weight-bold">
                    <i class="fas fa-cogs text-primary"></i> Pengaturan Delimiter (Pemisah Kolom)
                </h3>
            </div>
            <div class="card-body py-2">
                @php
                    $manualPeriodeInputType = $manualPeriodeInputType ?? (preg_match('/^\d{4}-\d{2}$/', (string) ($manualPeriode ?? '')) ? 'month' : 'date');
                    $manualPeriodeValue = !empty($manualPeriode)
                        ? ($manualPeriodeInputType === 'month'
                            ? $manualPeriode
                            : \Carbon\Carbon::parse($manualPeriode)->format('Y-m-d'))
                        : '';
                @endphp
                <form action="{{ $previewRoute ?? (session('import_type') === 'brimo' ? route('import.brimo.preview') : route('import.preview')) }}" method="POST" class="form-inline">
                    @csrf
                    <input type="hidden" name="file_path" value="{{ $filePath }}">
                    @if(!empty($lockDelimiterSelector))
                        <input type="hidden" name="delimiter" value="{{ $currentDelimiter }}">
                    @endif
                    <label class="mr-3" for="delimiter">Jika tabel berantakan, ubah pemisah kolom di sini:</label>
                    <select name="delimiter" id="delimiter" class="form-control mr-3" style="min-width: 250px;" {{ !empty($lockDelimiterSelector) ? 'disabled' : '' }}>
                        @if(!empty($lockDelimiterSelector))
                            <option value="{{ $currentDelimiter }}" selected>{{ $fixedDelimiterLabel ?? 'Koma ( , )' }}</option>
                        @else
                            <option value="auto" {{ $currentDelimiter == 'auto' ? 'selected' : '' }}>Otomatis (Auto Detect)</option>
                            <option value="," {{ $currentDelimiter == ',' ? 'selected' : '' }}>Koma ( , )</option>
                            <option value=";" {{ $currentDelimiter == ';' ? 'selected' : '' }}>Titik Koma ( ; )</option>
                            <option value="|" {{ $currentDelimiter == '|' ? 'selected' : '' }}>Garis Lurus / Pipe ( | )</option>
                            <option value="." {{ $currentDelimiter == '.' ? 'selected' : '' }}>Titik ( . )</option>
                            <option value="\t" {{ $currentDelimiter == "\t" ? 'selected' : '' }}>Tab</option>
                        @endif
                    </select>
                    @if(!empty($manualPeriode))
                        <label class="mr-2 mt-2 mt-md-0" for="periode_preview">Periode:</label>
                        <input type="{{ $manualPeriodeInputType }}" name="periode" id="periode_preview" value="{{ $manualPeriodeValue }}" class="form-control mr-3 mt-2 mt-md-0" style="min-width: 220px;">
                    @endif
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sync-alt"></i> Terapkan Ulang
                    </button>
                </form>
            </div>
        </div>
        @endif

        <form id="importForm" action="{{ $processRoute ?? route('import.process') }}" method="POST" data-init-url="{{ $initRoute ?? '' }}" data-stream-url="{{ $streamRoute ?? '' }}" data-filter-options-url="{{ $filterOptionsRoute ?? route('import.preview.filter-options') }}" data-warm-index-url="{{ $warmIndexRoute ?? route('import.preview.warm-index') }}">
            @csrf
            <input type="hidden" name="file_path" value="{{ $filePath }}">
            <input type="hidden" name="delimiter" value="{{ $currentDelimiter }}">
            <input type="hidden" name="active_filters_json" id="active_filters_json" value="{}">
            @if(!empty($previewStateKey))
                <input type="hidden" name="preview_state_key" value="{{ $previewStateKey }}">
            @endif
            @if(!empty($manualPeriode))
                <input type="hidden" name="periode" value="{{ $manualPeriodeValue }}">
            @endif

            <div class="card card-outline card-success">
                <div class="card-header bg-light">
                    <h3 class="card-title text-success font-weight-bold">
                        <i class="fas fa-file-excel"></i> Table Data (Ala Excel Filter)
                    </h3>
                    <div class="card-tools">
                        <button type="submit" id="btnSubmitImport" class="btn btn-sm btn-success">
                            <i class="fas fa-database"></i> Jalankan Import ke MySQL
                        </button>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="alert alert-info m-3 border-0 bg-light text-dark">
                        @if($filtersDisabled)
                            <i class="fas fa-info-circle text-info"></i> <strong>Mode Import Full:</strong>
                            Filter dinonaktifkan untuk report ini agar proses import selalu memproses seluruh data tanpa ambiguitas.
                        @else
                            <i class="fas fa-info-circle text-info"></i> <strong>Petunjuk Filter:</strong>
                            Klik ikon <i class="fas fa-filter text-muted mx-1"></i> di sebelah nama kolom untuk memfilter baris data. Tabel preview menampilkan <strong>sampel dari berbagai bagian file</strong> (max 100 baris) untuk evaluasi visual. <strong>Saat Anda membuka dropdown filter, sistem akan memuat SEMUA nilai unik dari file sumber</strong> - sehingga Anda dapat memilih dengan filter data yang paling lengkap.
                        @endif
                    </div>

                    @if(!empty($manualPeriodeLabel))
                        <div class="alert alert-secondary m-3 mb-0 border-0">
                            <i class="fas fa-calendar-alt text-primary"></i> Periode import manual: <strong>{{ $manualPeriodeLabel }}</strong>
                        </div>
                    @endif

                    @if(!empty($detectedPosisi))
                        <div class="alert alert-success m-3 mb-0 border-0">
                            <i class="fas fa-map-pin text-success"></i> Posisi terdeteksi otomatis dari metadata file:
                            <strong>{{ \Carbon\Carbon::parse($detectedPosisi)->translatedFormat('d F Y') }}</strong>
                        </div>
                    @endif

                    @if(!empty($lockDelimiterSelector))
                        <div class="alert alert-secondary m-3 mb-0 border-0">
                            <i class="fas fa-columns text-primary"></i> Format kolom report ini dikunci menggunakan delimiter
                            <strong>{{ $fixedDelimiterLabel ?? 'Koma ( , )' }}</strong>, sedangkan baris metadata `posisi` di atas file tidak ikut ditampilkan di preview.
                        </div>
                    @endif

                    @if(!empty($isBrilinkSummary))
                        <div class="alert alert-warning m-3 mb-0 border-0">
                            <i class="fas fa-list-ol"></i> Kolom nomor urut manual seperti `NO` tidak ditampilkan di preview BRILink dan tidak akan ikut diimport ke database.
                        </div>
                    @endif

                    <div class="table-responsive" style="min-height: 450px; max-height: 600px; overflow-y: auto; overflow-x: auto;">
                        <table class="table table-bordered table-hover m-0">
                            <thead class="thead-light sticky-top" style="z-index: 2;">
                                <tr>
                                    <th class="text-center align-middle bg-light" style="width: 50px;">#</th>
                                    @foreach($headers as $index => $header)
                                        <th class="align-middle bg-light" style="min-width: 250px;">
                                            <div class="d-flex justify-content-between align-items-center">

                                                <div class="custom-control custom-checkbox mr-2">
                                                    <input class="custom-control-input" type="checkbox"
                                                           id="col_{{ $index }}"
                                                           name="selected_columns[]"
                                                           value="{{ $index }}" checked>
                                                    <label for="col_{{ $index }}" class="custom-control-label font-weight-bold text-dark">
                                                        {{ $header }}
                                                    </label>
                                                </div>

                                                @if(!$filtersDisabled && isset($formattedUniqueValues[$index]) && count($formattedUniqueValues[$index]) > 0)
                                                <input type="hidden" name="has_filter[]" value="{{ $index }}">

                                                <div class="dropdown">
                                                    <button class="btn btn-xs btn-light border dropdown-toggle filter-btn" type="button" data-toggle="dropdown" aria-expanded="false">
                                                        <i class="fas fa-filter text-muted" id="icon_filter_{{ $index }}"></i>
                                                    </button>

                                                    <div class="dropdown-menu dropdown-menu-right shadow p-0" style="width: 280px; border-radius: 8px;">
                                                        <div class="p-2 bg-light border-bottom">
                                                            <div class="input-group input-group-sm">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                                </div>
                                                                <input type="text" class="form-control search-filter" data-col="{{ $index }}" placeholder="Search...">
                                                            </div>
                                                        </div>
                                                        <div class="p-2 border-bottom bg-white">
                                                            <div class="custom-control custom-checkbox">
                                                                <input class="custom-control-input select-all-cb" type="checkbox" id="select_all_{{ $index }}" data-col="{{ $index }}" checked>
                                                                <label for="select_all_{{ $index }}" class="custom-control-label font-weight-bold text-dark">(Select All)</label>
                                                            </div>
                                                        </div>
                                                        <div class="p-2 bg-white" id="list_container_{{ $index }}" style="max-height: 250px; overflow-y: auto;" data-col="{{ $index }}">
                                                            <div class="text-center text-muted py-2 small">Memuat opsi filter awal...</div>
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
                                @php
                                    $hidePreviewRowsUntilJs = ($hidePreviewRowsUntilJs ?? null);
                                    if ($hidePreviewRowsUntilJs === null) {
                                        $hidePreviewRowsUntilJs = false;
                                    }
                                @endphp
                                @foreach($previewData as $rowIndex => $row)
                                    <tr class="preview-row{{ $hidePreviewRowsUntilJs ? ' d-none' : '' }}">
                                        <td class="text-center text-muted">{{ $rowIndex + 1 }}</td>
                                        @foreach($headers as $colIndex => $header)
                                            <td class="text-truncate col-data-{{ $colIndex }}"
                                                data-val="{{ trim($row[$colIndex] ?? '') }}"
                                                style="max-width: 250px;"
                                                title="{{ $row[$colIndex] ?? '' }}">
                                                {{ isset($row[$colIndex]) ? $row[$colIndex] : '-' }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach

                                <tr id="empty-state-row" class="d-none">
                                    <td colspan="{{ count($headers) + 1 }}" class="text-center py-5 bg-white text-muted">
                                        <i class="fas fa-search-minus fa-3x mb-3 text-secondary"></i><br>
                                        <h5 class="font-weight-bold text-dark">Tidak ada kecocokan di baris sampel preview</h5>
                                        <p class="mb-0">Cabang/Filter yang kamu centang berada di urutan bawah CSV dan tidak tertangkap di sampel visual ini.</p>
                                        <p class="text-success font-weight-bold mt-2">
                                            <i class="fas fa-info-circle"></i> Jangan khawatir, cukup klik tombol <b>"Jalankan Import"</b> dan sistem akan memproses seluruh CSV ke MySQL!
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer bg-light">
                    <a href="{{ $backRoute ?? route('import.select') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
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
        const filterableColumnIndices = @json($filterableColumnIndices ?? []);
        const forceAllChecked = @json(!empty($forceAllFiltersCheckedOnLoad));
        const headers = @json($headers);
        const disableArea6AutoFilter = @json(!empty($disableArea6AutoFilter));
        const importFormElement = document.getElementById('importForm');
        const previewTbody = document.querySelector('.table-responsive tbody');
        const basePreviewTbodyHtml = previewTbody ? previewTbody.innerHTML : '';
        const filterOptionsUrl = importFormElement?.dataset.filterOptionsUrl || '';
        const warmIndexUrl = importFormElement?.dataset.warmIndexUrl || '';
        const filePathValue = importFormElement?.querySelector('input[name="file_path"]')?.value || '';
        const previewStateKey = importFormElement?.querySelector('input[name="preview_state_key"]')?.value || '';
        const delimiterValue = importFormElement?.querySelector('input[name="delimiter"]')?.value || 'auto';
        const displayFilterMap = @json(session('import_display_to_source_map', []));
        const filterState = {};
        const searchTerms = {};
        const filterRenderLimit = 200;
        let previewRenderToken = 0;
        let previewViewMode = 'sample';
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

        function redirectToImportIndex() {
            window.location.replace("{{ route('import.index') }}");
        }

        function redirectToSelectFile() {
            window.location.replace("{{ route('import.select') }}");
        }

        function normalizeDuplicateMessage(message) {
            return String(message || '')
                .replace(/<[^>]*>/g, ' ')
                .replace(/&nbsp;/gi, ' ')
                .replace(/\s+/g, ' ')
                .toLowerCase();
        }

        function isDuplicateImportMessage(message) {
            const text = normalizeDuplicateMessage(message);
            return text.includes('duplikat')
                || text.includes('sudah ada di database')
                || text.includes('sudah ada di tabel')
                || text.includes('sudah pernah diunggah')
                || text.includes('kombinasi periode + tid')
                || text.includes('mencegah data dobel')
                || text.includes('data dobel')
                || text.includes('duplicate entry')
                || text.includes('duplicate');
        }

        async function showDuplicateImportModal(title, message, redirectTarget = redirectToSelectFile) {
            await themedSwal({
                icon: 'warning',
                title: title || 'Data Ditolak (Duplikat)!',
                html: message || 'Data duplikat terdeteksi.',
                confirmButtonText: 'Tutup',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didClose: function () {
                    redirectTarget();
                },
            });
            redirectTarget();
        }

        function normalizeProgressStatus(message) {
            const text = String(message || '').trim();
            const speedMatch = text.match(/\(([\d.,]+)\s+baris\/detik\)$/i);
            const recordMatch = text.match(/\(([\d.,]+)\s+record\)$/i);

            if (speedMatch) {
                return {
                    message: text,
                    speed: speedMatch[1].replace(/[^\d]/g, ''),
                    speedLabel: 'baris/detik',
                };
            }

            if (recordMatch) {
                return {
                    message: text,
                    speed: recordMatch[1].replace(/[^\d]/g, ''),
                    speedLabel: 'record',
                };
            }

            return {
                message: text,
                speed: '',
                speedLabel: '',
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

        const dropdownMenus = document.querySelectorAll('.dropdown-menu');

        dropdownMenus.forEach(menu => {
            menu.addEventListener('click', function (e) { e.stopPropagation(); });
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

        const storageKeyPrefix = 'preview_filter_v4_' + stableHash(JSON.stringify({
            file: filePathValue,
            delimiter: delimiterValue,
            headers: headers,
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

        Object.keys(filterOptionsMap).forEach(function (col) {
            const values = Array.isArray(filterOptionsMap[col]) ? filterOptionsMap[col].map(function (value) {
                return String(value).trim();
            }) : [];
            const header = String(headers[col] || '');
            let selectedValues;
            if (forceAllChecked) {
                selectedValues = new Set(values);
            } else {
                selectedValues = new Set(values);
            }

            filterState[col] = {
                allValues: values,
                selectedValues: selectedValues,
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

        function normalizeActiveFiltersForServer(activeFilters) {
            const normalized = {};
            for (const displayColStr in activeFilters) {
                const displayCol = Number(displayColStr);
                const values = activeFilters[displayColStr];
                if (!Array.isArray(values) || values.length === 0) {
                    continue;
                }
                normalized[displayCol] = values;
            }
            return normalized;
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

        function rowMatchesActiveFilters(row, activeFilters) {
            const filterEntries = Object.keys(activeFilters || {});
            if (!filterEntries.length) {
                return true;
            }

            for (let i = 0; i < filterEntries.length; i++) {
                const col = filterEntries[i];
                const allowed = Array.isArray(activeFilters[col]) ? activeFilters[col] : [];
                if (!allowed.length) {
                    return false;
                }

                const cell = row.children[parseInt(col, 10) + 1];
                if (!cell) {
                    return false;
                }

                const cellVal = (cell.getAttribute('data-val') || '').trim();
                if (!allowed.includes(cellVal)) {
                    return false;
                }
            }

            return true;
        }

        function collectPreviewValuesForColumn(col, activeFilters) {
            const values = new Set();
            const rows = document.querySelectorAll('.preview-row');
            rows.forEach(function (row) {
                if (!rowMatchesActiveFilters(row, activeFilters)) {
                    return;
                }

                const cell = row.children[parseInt(col, 10) + 1];
                if (!cell) {
                    return;
                }

                const cellVal = (cell.getAttribute('data-val') || '').trim();
                values.add(cellVal);
            });

            return values;
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

            headers.forEach(function (_header, colIndex) {
                const rawValue = values[colIndex] === null || values[colIndex] === undefined
                    ? ''
                    : String(values[colIndex]);
                const safeValue = escapeHtml(rawValue);
                const displayValue = rawValue.trim() === '' ? '-' : safeValue;

                html += '<td class="text-truncate col-data-' + colIndex + '" data-val="' + safeValue + '" style="max-width: 250px;" title="' + safeValue + '">' + displayValue + '</td>';
            });

            html += '</tr>';
            return html;
        }

        function getFilteredValues(col) {
            const state = filterState[col];
            if (!state) {
                return [];
            }

            const activeFilters = buildActiveFilterContext(col);
            const previewValues = Object.keys(activeFilters).length > 0
                ? collectPreviewValuesForColumn(col, activeFilters)
                : null;
            if (previewValues && previewValues.size === 0) {
                return [];
            }

            const effectiveValues = previewValues && previewValues.size > 0
                ? state.allValues.filter(function (value) {
                    return previewValues.has(value);
                })
                : state.allValues.slice();

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

            if (!filteredValues.length) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
                return;
            }

            let checkedCount = 0;
            filteredValues.forEach(function (value) {
                if (state.selectedValues.has(value)) {
                    checkedCount++;
                }
            });

            selectAll.checked = checkedCount === filteredValues.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < filteredValues.length;
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
                    const labelValue = value === '' ? '(Blank)' : safeValue;
                    html += '<div class="custom-control custom-checkbox filter-item-container mb-1">';
                    html += '<input class="custom-control-input filter-checkbox" type="checkbox" id="' + inputId + '" value="' + safeValue + '" data-col="' + col + '"' + (state.selectedValues.has(value) ? ' checked' : '') + '>';
                    html += '<label for="' + inputId + '" class="custom-control-label font-weight-normal filter-label">' + labelValue + '</label>';
                    html += '</div>';
                });
            }

            container.innerHTML = html;
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
                    state.allValues = cachedValues;
                    const previousSelection = new Set(state.selectedValues || []);
                    state.selectedValues = new Set(cachedValues.filter(function (value) {
                        return previousSelection.has(value) || previousSelection.size === 0;
                    }));
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
                const normalizedActiveFilters = normalizeActiveFiltersForServer(activeFilters || {});
                url.searchParams.set('active_filters_json', JSON.stringify(normalizedActiveFilters));
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

                const previousValues = Array.isArray(state.allValues) ? state.allValues.slice() : [];
                const previousSelection = new Set(state.selectedValues || []);
                const hadAllSelected = previousValues.length === 0 || previousSelection.size === previousValues.length;
                const normalizedValues = payload.values.map(function (value) {
                    return String(value).trim();
                });

                if (state.pendingSignature !== signature || state.needsRefresh) {
                    return;
                }

                state.allValues = normalizedValues;
                state.selectedValues = hadAllSelected
                    ? new Set(normalizedValues)
                    : new Set(normalizedValues.filter(function (value) {
                        return previousSelection.has(value);
                    }));
                state.fullOptionsLoaded = true;
                state.loadedSignature = signature;
                // Simpan ke cache untuk next load
                if (isInitialPrefetch || Object.keys(activeFilters).length === 0) {
                    saveToLocalStorage(col, normalizedValues);
                }

                shouldRender = true;

            } catch (error) {
                console.error('Error loading filter options:', error);
                shouldRender = true;
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

        async function prefetchAllFilterOptions() {
            // Prefetch semua filter options secara parallel saat page load
            const cols = Object.keys(filterState);
            if (!cols.length) {
                return;
            }
            if (!cols.length) {
                return;
            }

            const prefetchPromises = cols.map(col => ensureFullFilterOptions(col, true));
            try {
                await Promise.allSettled(prefetchPromises);
            } catch (e) {
                console.warn('Prefetch filter options partially failed:', e);
            }
        }

        function refreshDependentFilterOptions(excludeCol) {
            Object.keys(filterState).forEach(function (key) {
                if (String(key) === String(excludeCol)) {
                    return;
                }

                ensureFullFilterOptions(key);
            });
        }

        function renderSamplePreviewTable(activeFilters) {
            if (!previewTbody) {
                return;
            }

            if (previewViewMode !== 'sample') {
                previewTbody.innerHTML = basePreviewTbodyHtml;
                previewViewMode = 'sample';
            }

            let filterReqs = [];
            for (let col in activeFilters) {
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
                if (matchingCount === 0) {
                    emptyRow.classList.remove('d-none');
                    const titleEl = emptyRow.querySelector('h5');
                    const bodyEl = emptyRow.querySelector('p.mb-0');
                    if (titleEl) {
                        titleEl.textContent = 'Tidak ada kecocokan di baris sampel preview';
                    }
                    if (bodyEl) {
                        bodyEl.textContent = 'Sampel preview tidak memuat baris yang cocok dengan filter ini.';
                    }
                } else {
                    emptyRow.classList.add('d-none');
                }
            }

            updateIconsColor();
        }

        async function renderFilteredPreviewTable(activeFilters) {
            if (!previewTbody) {
                return;
            }

            const requestToken = ++previewRenderToken;
            previewViewMode = 'filtered';
            previewTbody.innerHTML = `
                <tr>
                    <td colspan="${headers.length + 1}" class="text-center py-5 bg-white text-muted">
                        <i class="fas fa-spinner fa-spin fa-2x mb-3 text-primary"></i><br>
                        <h5 class="font-weight-bold text-dark">Memuat preview hasil filter</h5>
                        <p class="mb-0">Mengambil baris yang cocok langsung dari file sumber...</p>
                    </td>
                </tr>`;

            try {
                const url = new URL('{{ route("import.preview.filtered-rows") }}', window.location.origin);
                url.searchParams.set('file_path', filePathValue);
                url.searchParams.set('delimiter', delimiterValue);
                url.searchParams.set('display_filter_map_json', JSON.stringify(displayFilterMap || {}));
                const normalizedActiveFiltersForRows = normalizeActiveFiltersForServer(activeFilters || {});
                url.searchParams.set('active_filters_json', JSON.stringify(normalizedActiveFiltersForRows));
                url.searchParams.set('limit', '100');
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
                    previewTbody.innerHTML = `
                        <tr>
                            <td colspan="${headers.length + 1}" class="text-center py-5 bg-white text-muted">
                                <i class="fas fa-search-minus fa-3x mb-3 text-secondary"></i><br>
                                <h5 class="font-weight-bold text-dark">Tidak ada baris yang cocok di file sumber</h5>
                                <p class="mb-0">Filter yang dipilih tidak menemukan baris hasil di file asli.</p>
                            </td>
                        </tr>`;
                    updateIconsColor();
                    return;
                }

                let html = rows.map(function (row, index) {
                    return buildPreviewRowHtml(row, index + 1);
                }).join('');

                if (payload.truncated) {
                    const matchText = payload.total_matched
                        ? Number(payload.total_matched).toLocaleString('id-ID')
                        : 'lebih dari ' + rows.length.toLocaleString('id-ID');
                    html += `
                        <tr>
                            <td colspan="${headers.length + 1}" class="text-center py-3 bg-light text-muted">
                                Menampilkan 100 baris pertama dari ${matchText} baris yang cocok.
                            </td>
                        </tr>`;
                }

                previewTbody.innerHTML = html;
            } catch (error) {
                if (requestToken !== previewRenderToken) {
                    return;
                }

                previewTbody.innerHTML = `
                    <tr>
                        <td colspan="${headers.length + 1}" class="text-center py-5 bg-white text-muted">
                            <i class="fas fa-exclamation-triangle fa-3x mb-3 text-warning"></i><br>
                            <h5 class="font-weight-bold text-dark">Gagal memuat preview hasil filter</h5>
                            <p class="mb-0">${escapeHtml(error.message || 'Silakan coba lagi.')}</p>
                        </td>
                    </tr>`;
            } finally {
                updateIconsColor();
            }
        }

        function prewarmPreviewIndex() {
            if (!filePathValue || !warmIndexUrl) {
                return;
            }

            try {
                const url = new URL(warmIndexUrl, window.location.origin);
                url.searchParams.set('file_path', filePathValue);
                url.searchParams.set('delimiter', delimiterValue);
                url.searchParams.set('display_filter_map_json', JSON.stringify(displayFilterMap || {}));
                url.searchParams.set('filterable_column_indices_json', JSON.stringify(filterableColumnIndices || []));
                url.searchParams.set('_', String(Date.now()));

                fetch(url.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    cache: 'no-store',
                    keepalive: true,
                }).catch(function () {});
            } catch (error) {
            }
        }

        function updatePreviewTable() {
            let activeFilters = {};
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

            if (Object.keys(activeFilters).length === 0) {
                if (previewRefreshTimer) {
                    clearTimeout(previewRefreshTimer);
                    previewRefreshTimer = null;
                }
                previewRenderToken++;
                renderSamplePreviewTable({});
                return;
            }

            if (previewRefreshTimer) {
                clearTimeout(previewRefreshTimer);
            }

            previewRefreshTimer = setTimeout(function () {
                previewRefreshTimer = null;
                renderFilteredPreviewTable(activeFilters);
            }, 180);
        }

        function updatePreviewTableLegacy() {
            let activeFilters = {};
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

            let filterReqs = [];
            for (let col in activeFilters) {
                filterReqs.push({
                    index: parseInt(col) + 1,
                    allowed: activeFilters[col]
                });
            }

            let matchingCount = 0;

            document.querySelectorAll('.preview-row').forEach(row => {
                let pass = true;

                for (let i = 0; i < filterReqs.length; i++) {
                    let req = filterReqs[i];
                    if (req.allowed.length === 0) { pass = false; break; }

                    let cell = row.children[req.index];
                    if (cell) {
                        let cellVal = (cell.getAttribute('data-val') || '').trim();
                        if (!req.allowed.includes(cellVal)) { pass = false; break; }
                    }
                }

                if (pass) {
                    // 🔥 PERBAIKAN: Menampilkan 100 baris pertama untuk crosscheck visual
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
                if (matchingCount === 0) {
                    emptyRow.classList.remove('d-none');
                } else {
                    emptyRow.classList.add('d-none');
                }
            }

            updateIconsColor();
        }

        function updateIconsColor() {
            const dropdowns = document.querySelectorAll('.dropdown');
            dropdowns.forEach(dropdown => {
                const container = dropdown.querySelector('[id^="list_container_"]');
                if (container) {
                    const colIndexStr = container.getAttribute('data-col');
                    if (!colIndexStr) {
                        return;
                    }

                    const colIndex = String(colIndexStr);
                    const state = filterState[colIndex];
                    const icon = document.getElementById('icon_filter_' + colIndex);

                    if (icon && state) {
                        if (state.selectedValues.size < state.allValues.length) {
                            icon.classList.remove('text-muted');
                            icon.classList.add('text-primary');
                        } else {
                            icon.classList.remove('text-primary');
                            icon.classList.add('text-muted');
                        }
                    }
                }
            });
        }

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

            Object.keys(filterState).forEach(function (key) {
                if (String(key) !== String(colIndex) && filterState[key]) {
                    filterState[key].loadedSignature = '';
                }
            });

            syncSelectAllCheckbox(colIndex, getFilteredValues(colIndex));
            updatePreviewTable();

            // Debounce refresh dependent filters untuk menghindari multiple requests
            debounceRender(colIndex + '_refresh', function() {
                refreshDependentFilterOptions(colIndex);
            }, 300);
        });

        const selectAllCbs = document.querySelectorAll('.select-all-cb');
        selectAllCbs.forEach(cb => {
            cb.addEventListener('change', function () {
                const isChecked = this.checked;
                const colIndex = this.getAttribute('data-col');
                const state = filterState[colIndex];
                if (!state) {
                    return;
                }

                getFilteredValues(colIndex).forEach(function (value) {
                    if (isChecked) {
                        state.selectedValues.add(value);
                    } else {
                        state.selectedValues.delete(value);
                    }
                });

                Object.keys(filterState).forEach(function (key) {
                    if (String(key) !== String(colIndex) && filterState[key]) {
                        filterState[key].loadedSignature = '';
                    }
                });

                renderFilterList(colIndex);
                updatePreviewTable();

                // Debounce refresh dependent filters
                debounceRender(colIndex + '_refresh', function() {
                    refreshDependentFilterOptions(colIndex);
                }, 300);
            });
        });

        const searchInputs = document.querySelectorAll('.search-filter');
        searchInputs.forEach(input => {
            input.addEventListener('keyup', function () {
                const colIndex = this.getAttribute('data-col');
                searchTerms[colIndex] = this.value || '';

                // Debounce render untuk smooth search experience
                debounceRender(colIndex + '_search', function() {
                    renderFilterList(colIndex);
                }, 150);
            });
        });

        document.querySelectorAll('.dropdown').forEach(function (dropdown) {
            dropdown.addEventListener('shown.bs.dropdown', function () {
                const container = dropdown.querySelector('[id^="list_container_"]');
                if (!container) {
                    return;
                }

                const col = container.getAttribute('data-col');
                ensureFullFilterOptions(col);
            });
        });

        let importProgressStartedAt = null;
        let importProgressTicker = null;
        let importProgressSnapshot = {
            percent: 0,
            message: '',
            rowsDone: 0,
            totalRows: 0,
            speed: 0
        };

        function formatDuration(totalSeconds) {
            const seconds = Math.max(0, Math.round(totalSeconds || 0));
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;

            if (hours > 0) {
                return hours + 'j ' + String(minutes).padStart(2, '0') + 'm';
            }

            if (minutes > 0) {
                return minutes + 'm ' + String(secs).padStart(2, '0') + ' dtk';
            }

            return secs + ' dtk';
        }

        function refreshImportTimeInfo() {
            const elapsedInfo = document.getElementById('swal-elapsed-info');
            const etaInfo = document.getElementById('swal-eta-info');

            if (elapsedInfo && importProgressStartedAt) {
                const elapsedSeconds = (Date.now() - importProgressStartedAt) / 1000;
                elapsedInfo.innerText = 'Durasi: ' + formatDuration(elapsedSeconds);
            }

            if (etaInfo) {
                const rowsDone = Number(importProgressSnapshot.rowsDone || 0);
                const totalRows = Number(importProgressSnapshot.totalRows || 0);
                const speed = Number(importProgressSnapshot.speed || 0);
                const percent = Number(importProgressSnapshot.percent || 0);
                const speedLabel = String(importProgressSnapshot.speedLabel || '');

                if (speedLabel === 'record') {
                    etaInfo.innerText = 'Estimasi sisa: tahap sanitasi sedang memproses batch data...';
                } else if (totalRows <= 0) {
                    etaInfo.innerText = 'Estimasi sisa: menunggu total baris terkonfirmasi...';
                } else if (speed > 0 && totalRows > rowsDone) {
                    const remainingRows = totalRows - rowsDone;
                    etaInfo.innerText = 'Estimasi sisa: ' + formatDuration(remainingRows / speed);
                } else if (percent >= 96 && percent < 100) {
                    etaInfo.innerText = 'Estimasi sisa: menunggu proses MySQL menyelesaikan tahap akhir...';
                } else {
                    etaInfo.innerText = '';
                }
            }
        }

        function startImportProgressTicker() {
            importProgressStartedAt = Date.now();
            if (importProgressTicker) {
                clearInterval(importProgressTicker);
            }
            importProgressTicker = setInterval(refreshImportTimeInfo, 1000);
            refreshImportTimeInfo();
        }

        function stopImportProgressTicker() {
            if (importProgressTicker) {
                clearInterval(importProgressTicker);
                importProgressTicker = null;
            }
        }

        function setImportProgress(percent, message, rowsDone, totalRows, speed, speedLabel = '') {
            const progressBar = document.getElementById('swal-progress-bar');
            const progressText = document.getElementById('swal-progress-text');
            const rowsInfo = document.getElementById('swal-rows-info');
            const speedInfo = document.getElementById('swal-speed-info');
            const speedDetail = document.getElementById('swal-speed-detail');
            const normalized = normalizeProgressStatus(message);
            const hasRowsDone = rowsDone !== null && rowsDone !== undefined && rowsDone !== '';
            const hasTotalRows = totalRows !== null && totalRows !== undefined && totalRows !== '';
            const hasSpeed = speed !== null && speed !== undefined && speed !== '';
            let resolvedRowsDone = hasRowsDone ? Number(rowsDone || 0) : Number(importProgressSnapshot.rowsDone || 0);
            const resolvedTotalRows = hasTotalRows
                ? Number(totalRows || importProgressSnapshot.totalRows || 0)
                : Number(importProgressSnapshot.totalRows || 0);
            if (resolvedTotalRows > 0 && resolvedRowsDone > resolvedTotalRows) {
                resolvedRowsDone = resolvedTotalRows;
            }
            const effectiveSpeed = hasSpeed && Number(speed) > 0
                ? speed
                : (normalized.speed ? Number(normalized.speed) : Number(importProgressSnapshot.speed || 0));
            const resolvedPercent = Math.max(0, Math.min(100, Number(percent ?? importProgressSnapshot.percent ?? 0)));
            importProgressSnapshot = {
                percent: resolvedPercent,
                message: normalized.message || message || importProgressSnapshot.message || '',
                rowsDone: resolvedRowsDone,
                totalRows: resolvedTotalRows > 0 ? resolvedTotalRows : Number(importProgressSnapshot.totalRows || 0),
                speed: Number(effectiveSpeed || 0),
                speedLabel: speedLabel || normalized.speedLabel || importProgressSnapshot.speedLabel || ''
            };

            if (progressBar) {
                progressBar.style.width = resolvedPercent + '%';
                progressBar.innerText = resolvedPercent + '%';
                progressBar.setAttribute('aria-valuenow', resolvedPercent);
            }

            const progressPercent = document.getElementById('swal-progress-percent');
            if (progressPercent) {
                progressPercent.innerText = resolvedPercent + '%';
            }

            if (progressText) {
                progressText.innerText = normalized.message || message || importProgressSnapshot.message || '';
            }

            if (rowsInfo) {
                if (resolvedTotalRows > 0) {
                    rowsInfo.innerText = resolvedRowsDone.toLocaleString('id-ID') + ' / ' + resolvedTotalRows.toLocaleString('id-ID') + ' baris';
                } else {
                    rowsInfo.innerText = resolvedRowsDone > 0
                        ? resolvedRowsDone.toLocaleString('id-ID') + ' / - baris'
                        : 'Menunggu total baris...';
                }
            }

            if (speedInfo) {
                const displayLabel = normalized.speedLabel || 'baris/detik';
                speedInfo.innerText = effectiveSpeed > 0 ? Number(effectiveSpeed).toLocaleString('id-ID') + ' ' + displayLabel : '-';
            }

            if (speedDetail) {
                speedDetail.innerText = effectiveSpeed > 0
                    ? (normalized.speedLabel === 'record'
                        ? 'Tahap sanitasi sedang memproses batch data'
                        : 'Rata-rata proses saat ini')
                    : 'Menunggu data kecepatan pertama';
            }

            refreshImportTimeInfo();
        }

        function resetImportButton() {
            const submitBtn = document.getElementById('btnSubmitImport');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-database"></i> Jalankan Import ke MySQL';
            }
        }

        function collectActiveFilters() {
            let activeFilters = {};
            if (!filtersDisabled) {
                Object.keys(filterState).forEach(function (colIndex) {
                    const state = filterState[colIndex];
                    if (!state) {
                        return;
                    }

                    if (state.selectedValues.size < state.allValues.length) {
                        activeFilters[colIndex] = Array.from(state.selectedValues);
                    }
                });
            }

            document.getElementById('active_filters_json').value = JSON.stringify(activeFilters);
        }

        document.getElementById('importForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const form = this;
            const submitBtn = document.getElementById('btnSubmitImport');
            const csrfToken = document.querySelector('input[name="_token"]').value;
            const initUrl = form.dataset.initUrl;
            const streamUrlBase = form.dataset.streamUrl;

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            }

            collectActiveFilters();

            if (!initUrl || !streamUrlBase) {
                const formData = new FormData(form);
                const loadingCopy = resolveLoadingCopy();
                const loadingHtml = `
                    <div class="swal-import-shell">
                        <div class="swal-import-head">
                            <span class="swal-import-badge"><i class="fas fa-circle-notch fa-spin mr-1"></i> Sedang diproses</span>
                            <div class="swal-import-title">${loadingCopy.title}</div>
                            <div class="swal-import-desc">${loadingCopy.description}</div>
                        </div>
                        <div class="swal-import-card">
                            <div class="swal-import-card__top">
                                <span class="swal-import-label">Status</span>
                                <span class="swal-import-percent">0%</span>
                            </div>
                            <div class="progress swal-import-progress" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar swal-import-progress__bar progress-bar-striped progress-bar-animated" style="width: 100%;">Memproses</div>
                            </div>
                            <div class="swal-import-meta">
                                <small class="swal-import-meta__status">Mohon tunggu sebentar.</small>
                            </div>
                        </div>
                </div>`;

                themedSwal({
                    title: '<i class="fas fa-cloud-upload-alt mr-2 text-success"></i> ' + loadingCopy.title,
                    html: loadingHtml,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    width: 560,
                });

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const result = await response.json();

                    const duplicateDetected = result.duplicate_detected
                        || result.redirect_url
                        || isDuplicateImportMessage(result.title || result.text || result.message);

                    if (duplicateDetected) {
                        await showDuplicateImportModal(
                            result.title || 'Data Ditolak (Duplikat)!',
                            result.text || result.message || 'Data duplikat terdeteksi.'
                        );
                        resetImportButton();
                        return;
                    }

                    themedSwal({
                        icon: result.status || (response.ok ? 'success' : 'error'),
                        title: result.title || 'Selesai',
                        html: result.text || result.message || '',
                        confirmButtonText: 'Tutup'
                    }).then(() => {
                        redirectToImportIndex();
                    });
                } catch (err) {
                    themedSwal({
                        icon: 'error',
                        title: 'Terjadi Kesalahan',
                        text: 'Import gagal dijalankan!',
                        confirmButtonText: 'Tutup'
                    });
                    resetImportButton();
                }

                return;
            }

            const loadingCopy = resolveLoadingCopy();
            const progressHtml = `
                <div class="swal-import-shell">
                    <div class="swal-import-head">
                        <span class="swal-import-badge"><i class="fas fa-circle-notch fa-spin mr-1"></i> Sedang diproses</span>
                        <div class="swal-import-title">${loadingCopy.title}</div>
                        <div class="swal-import-desc">${loadingCopy.description}</div>
                        <div class="swal-import-phase">${loadingCopy.phase}</div>
                    </div>
                    <div class="swal-import-card">
                        <div class="swal-import-card__top">
                            <span class="swal-import-label">Progress</span>
                            <span class="swal-import-percent" id="swal-progress-percent">0%</span>
                        </div>
                        <div class="progress swal-import-progress" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                            <div id="swal-progress-bar" class="progress-bar swal-import-progress__bar progress-bar-striped progress-bar-animated"
                                 style="width: 0%;">0%</div>
                        </div>
                        <div class="swal-import-meta">
                            <small id="swal-progress-text" class="swal-import-meta__status">Menyiapkan batch Polars...</small>
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
                            <span id="swal-speed-detail" class="swal-import-stat__detail">Menunggu proses berjalan...</span>
                        </div>
                        <div class="swal-import-stat">
                            <span class="swal-import-stat__label">Durasi</span>
                            <span id="swal-elapsed-info" class="swal-import-stat__value">-</span>
                            <span class="swal-import-stat__detail">Waktu berjalan</span>
                        </div>
                        <div class="swal-import-stat">
                            <span class="swal-import-stat__label">Sisa</span>
                            <span id="swal-eta-info" class="swal-import-stat__value">-</span>
                            <span class="swal-import-stat__detail">Estimasi otomatis</span>
                        </div>
                    </div>
                </div>
            `;

            themedSwal({
                title: '<i class="fas fa-cloud-upload-alt mr-2 text-success"></i> ' + loadingCopy.title,
                html: progressHtml,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                width: 560,
            });

            startImportProgressTicker();
            setImportProgress(5, loadingCopy.status, 0, 0, 0, isDailyLoanPreview ? 'record' : '');

            try {
                let pollStarted = false;
                const initFormData = new FormData(form);
                const initResponse = await fetch(initUrl, {
                    method: 'POST',
                    body: initFormData,
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const initResult = await initResponse.json();

                const initDuplicate = initResult.duplicate_detected
                    || initResult.redirect_url
                    || isDuplicateImportMessage(initResult.title || initResult.text || initResult.message);

                if (initDuplicate) {
                    await showDuplicateImportModal(
                        initResult.title || 'Data Ditolak (Duplikat)!',
                        initResult.text || initResult.message || 'Data duplikat terdeteksi.'
                    );
                    resetImportButton();
                    return;
                }

                if (!initResponse.ok || initResult.status !== 'success') {
                        themedSwal({
                            icon: initResult.status || 'error',
                            title: initResult.title || 'Import Dibatalkan',
                            html: initResult.text || initResult.message || 'Persiapan fase Polars gagal.',
                            confirmButtonText: 'Tutup'
                        });
                    resetImportButton();
                    return;
                }

                setImportProgress(
                    12,
                    isDailyLoanPreview
                        ? 'Menyiapkan sanitasi CSV Daily Loan... (' + Number(initResult.total_rows || 0).toLocaleString('id-ID') + ' record)'
                        : 'Fase Polars siap. Membuka koneksi progress...',
                    0,
                    initResult.total_rows || 0,
                    0,
                    isDailyLoanPreview ? 'record' : ''
                );

                if (!pollStarted) {
                    pollStarted = true;
                    startIndependentPolling(initResult.job_id);
                }

                const streamUrl = streamUrlBase + '?job_id=' + encodeURIComponent(initResult.job_id);
                const statusUrlTemplate = @json(route('import.jobs.status', ['jobId' => '__JOB_ID__']));
                const forceStartUrlTemplate = @json(route('job-management.force-start', ['jobId' => '__JOB_ID__']));
                let streamDone = false;
                let reconnectAttempts = 0;
                let evtSource = null;
                let forceStartTriggered = false;

                const showImportError = function (message) {
                    stopImportProgressTicker();
                    if (independentPollingTimer) {
                        clearInterval(independentPollingTimer);
                        independentPollingTimer = null;
                    }
                    const errorMessage = message || 'Import gagal dijalankan!';
                    if (isDuplicateImportMessage(errorMessage)) {
                        showDuplicateImportModal(
                            'Data Ditolak (Duplikat)!',
                            errorMessage,
                            redirectToSelectFile
                        );
                        resetImportButton();
                        return;
                    }

                    themedSwal({
                        icon: 'error',
                        title: 'Proses Terhenti',
                        html: errorMessage,
                        confirmButtonText: 'Tutup'
                    });
                    resetImportButton();
                };

                const isNonFatalProcessingMessage = function (message) {
                    const text = String(message || '').toLowerCase();
                    return text.includes('sedang diproses')
                        || text.includes('import sedang diproses')
                        || text.includes('job import ini sudah sedang diproses')
                        || text.includes('job import masuk ke queue')
                        || text.includes('menunggu worker queue');
                };

                const showImportComplete = function (data) {
                    streamDone = true;
                    if (evtSource) {
                        evtSource.close();
                    }
                    if (independentPollingTimer) {
                        clearInterval(independentPollingTimer);
                        independentPollingTimer = null;
                    }

                    data = Object.assign({}, data || {});
                    if (String(data.status || 'completed') === 'completed' && Number(data.total_success || 0) === 0) {
                        const inferredSuccess = Number(data.processed_rows || data.total_rows || 0) - Number(data.total_failed || 0);
                        if (inferredSuccess > 0) {
                            data.total_success = inferredSuccess;
                        }
                    }

                    const skippedCount = Number(data.skipped_count || 0);
                    const skippedRows = Array.isArray(data.skipped_rows) ? data.skipped_rows : [];
                    const skippedHtml = skippedCount > 0
                        ? '<br><small class="text-warning">Baris di-skip: <b>' + skippedCount.toLocaleString('id-ID') + '</b>' +
                          (skippedRows.length ? '<br>Contoh baris: ' + skippedRows.join(', ') : '') +
                          '</small>'
                        : '';

                    stopImportProgressTicker();
                    setImportProgress(100, 'Import selesai!', data.total_rows || 0, data.total_rows || 0, 0, '');

                    setTimeout(() => {
                        if (!data.total_success || data.total_success === 0) {
                            themedSwal({
                                icon: data.total_failed > 0 ? 'warning' : 'info',
                                title: 'Import Selesai',
                                html: '<p>Berhasil: <b>' + Number(data.total_success || 0).toLocaleString('id-ID') + ' baris</b></p>' +
                                      '<p>Gagal: <b>' + Number(data.total_failed || 0).toLocaleString('id-ID') + ' baris</b></p>' +
                                      (data.error_message ? '<small class="text-danger">' + data.error_message + '</small>' : '') +
                                      skippedHtml,
                                confirmButtonText: 'Tutup'
                            }).then(() => {
                                window.location.href = "{{ route('import.index') }}";
                            });
                            return;
                        }

                        themedSwal({
                            icon: data.total_failed > 0 ? 'warning' : 'success',
                            title: data.total_failed > 0 ? 'Import Memiliki Kendala!' : 'Berhasil!',
                            html: 'Sebanyak <b>' + Number(data.total_success).toLocaleString('id-ID') + ' baris</b> data berhasil diimport.' +
                                  (data.total_failed > 0
                                    ? '<br><small class="text-warning">' + Number(data.total_failed).toLocaleString('id-ID') + ' baris gagal diproses.</small>'
                                    : '') +
                                  skippedHtml,
                            confirmButtonText: 'Tutup'
                        }).then(() => {
                            window.location.href = "{{ route('import.index') }}";
                        });
                    }, 500);
                };

                const statusUrlForJob = function (jobId) {
                    return statusUrlTemplate.replace('__JOB_ID__', encodeURIComponent(jobId));
                };

                const forceStartUrlForJob = function (jobId) {
                    return forceStartUrlTemplate.replace('__JOB_ID__', encodeURIComponent(jobId));
                };

                const inspectJobStatus = async function (jobId) {
                    const response = await fetch(statusUrlForJob(jobId), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) {
                        return null;
                    }

                    return await response.json();
                };

                const triggerForceStart = async function (jobId) {
                    if (forceStartTriggered) {
                        return false;
                    }

                    forceStartTriggered = true;

                    const response = await fetch(forceStartUrlForJob(jobId), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({})
                    });

                    let payload = {};
                    try { payload = await response.json(); } catch (_) {}

                    if (!response.ok || payload.status === 'error') {
                        forceStartTriggered = false;
                        throw new Error(payload.message || 'Gagal menjalankan force start import.');
                    }

                    return true;
                };

                const shouldForceStartQueuedJob = function (statusPayload) {
                    if (!statusPayload || String(statusPayload.status || '') !== 'queued') {
                        return false;
                    }

                    return Boolean(statusPayload.is_stale_queue);
                };

                const pollImportStatus = async function (jobId) {
                    for (;;) {
                        let payload = null;

                        try {
                            payload = await inspectJobStatus(jobId);
                        } catch (_) {
                            payload = null;
                        }

                        if (payload) {
                            setImportProgress(
                                payload.percent || 0,
                                payload.message || '',
                                payload.processed_rows || 0,
                                payload.total_rows || 0,
                                importProgressSnapshot.speed || 0,
                                importProgressSnapshot.speedLabel || ''
                            );

                            if (payload.status === 'completed') {
                                showImportComplete(payload);
                                return;
                            }

                            if (payload.status === 'failed' || payload.status === 'failed_partial' || payload.status === 'terminated' || payload.status === 'error') {
                                showImportError(payload.message || 'Import gagal dijalankan!');
                                return;
                            }
                        }

                        await new Promise((resolve) => setTimeout(resolve, 1000));
                    }
                };

                let independentPollingTimer = null;
                const startIndependentPolling = function (jobId) {
                    if (independentPollingTimer) {
                        clearInterval(independentPollingTimer);
                    }

                    independentPollingTimer = setInterval(async function () {
                        if (streamDone) {
                            clearInterval(independentPollingTimer);
                            independentPollingTimer = null;
                            return;
                        }

                        try {
                            const payload = await inspectJobStatus(jobId);
                            if (payload && !streamDone) {
                                setImportProgress(
                                    payload.percent || importProgressSnapshot.percent || 0,
                                    payload.message || importProgressSnapshot.message || '',
                                    payload.processed_rows || importProgressSnapshot.rowsDone || 0,
                                    payload.total_rows || importProgressSnapshot.totalRows || 0,
                                    payload.speed || importProgressSnapshot.speed || 0,
                                    importProgressSnapshot.speedLabel || ''
                                );

                                if (payload.status === 'completed' && !streamDone) {
                                    streamDone = true;
                                    if (evtSource) {
                                        evtSource.close();
                                    }
                                    showImportComplete(payload);
                                    clearInterval(independentPollingTimer);
                                    independentPollingTimer = null;
                                }
                            }
                        } catch (_) {
                        }
                    }, 4000);
                };

                const connectSSE = function () {
                    if (streamDone) {
                        return;
                    }

                    evtSource = new EventSource(streamUrl);

                    evtSource.addEventListener('progress', function (event) {
                        reconnectAttempts = 0;
                        let data = {};
                        try { data = JSON.parse(event.data); } catch (_) {}
                        setImportProgress(data.percent || 0, data.message || '', data.rows_done || data.processed_rows || 0, data.total || data.total_rows || 0, data.speed || 0, data.speed_label || normalizeProgressStatus(data.message || '').speedLabel || '');
                    });

                    evtSource.addEventListener('complete', function (event) {
                        let data = {};
                        try { data = JSON.parse(event.data); } catch (_) {}
                        showImportComplete(data);
                    });

                    evtSource.addEventListener('error', function (event) {
                        if (streamDone) {
                            return;
                        }

                        const rawData = typeof event.data === 'string' ? event.data.trim() : '';
                        if (rawData === '') {
                            return;
                        }

                        let data = {};
                        try { data = JSON.parse(rawData); } catch (_) { data = { message: rawData }; }
                        if (isNonFatalProcessingMessage(data.message)) {
                            return;
                        }

                        showImportError(data.message || 'Import gagal dijalankan!');
                    });

                    evtSource.onerror = async function () {
                        if (streamDone) {
                            return;
                        }

                        evtSource.close();

                        let statusPayload = null;
                        try {
                            statusPayload = await inspectJobStatus(initResult.job_id);
                        } catch (_) {}

                        const status = String(statusPayload && statusPayload.status ? statusPayload.status : '');
                        if (status === 'completed') {
                            showImportComplete(statusPayload || {});
                            return;
                        }

                        if (status === 'queued' && statusPayload && statusPayload.is_stale_queue) {
                            showImportError((statusPayload && statusPayload.message) || 'Job import terlalu lama berada di antrian.');
                            return;
                        }

                        if (status === 'queued' || status === 'processing') {
                            reconnectAttempts += 1;
                            if (shouldForceStartQueuedJob(statusPayload) && !forceStartTriggered) {
                                try {
                                    setImportProgress(
                                        Math.max(importProgressSnapshot.percent || 12, 12),
                                        'Koneksi stream gagal dibuka. Menjalankan force start import...',
                                        importProgressSnapshot.rowsDone || 0,
                                        importProgressSnapshot.totalRows || 0,
                                        importProgressSnapshot.speed || 0,
                                        importProgressSnapshot.speedLabel || ''
                                    );

                                    await triggerForceStart(initResult.job_id);
                                    await pollImportStatus(initResult.job_id);
                                    return;
                                } catch (forceStartError) {
                                    const refreshedStatus = await inspectJobStatus(initResult.job_id).catch(function () {
                                        return null;
                                    });
                                    const refreshedState = String(refreshedStatus && refreshedStatus.status ? refreshedStatus.status : '');

                                    if (refreshedState === 'completed') {
                                        showImportComplete(refreshedStatus || {});
                                        return;
                                    }

                                    if (refreshedState === 'queued' || refreshedState === 'processing') {
                                        reconnectAttempts = 0;
                                        setImportProgress(
                                            Math.max(importProgressSnapshot.percent || 12, 12),
                                            (refreshedStatus && refreshedStatus.message) || 'Import sedang diproses di backend. Menyambung ulang progress...',
                                            importProgressSnapshot.rowsDone || 0,
                                            importProgressSnapshot.totalRows || 0,
                                            importProgressSnapshot.speed || 0,
                                            importProgressSnapshot.speedLabel || ''
                                        );
                                        setTimeout(connectSSE, 1000);
                                        return;
                                    }

                                    showImportError((forceStartError && forceStartError.message) || 'Gagal menjalankan force start import.');
                                    return;
                                }
                            }

                            setImportProgress(
                                Math.max(importProgressSnapshot.percent || 12, 12),
                                (statusPayload && statusPayload.message) || 'Import sedang diproses. Menyambung ulang progress...',
                                importProgressSnapshot.rowsDone || 0,
                                importProgressSnapshot.totalRows || 0,
                                importProgressSnapshot.speed || 0,
                                importProgressSnapshot.speedLabel || ''
                            );

                            setTimeout(connectSSE, 1000 * Math.min(reconnectAttempts, 5));
                            return;
                        }

                        if (status === 'failed' || status === 'failed_partial' || status === 'error') {
                            showImportError((statusPayload && statusPayload.message) || 'Import gagal dijalankan!');
                            return;
                        }

                        reconnectAttempts += 1;
                        if (reconnectAttempts <= 5) {
                            setImportProgress(
                                importProgressSnapshot.percent || 12,
                                'Koneksi progress terputus, mencoba menyambung ulang...',
                                importProgressSnapshot.rowsDone || 0,
                                importProgressSnapshot.totalRows || 0,
                                importProgressSnapshot.speed || 0,
                                importProgressSnapshot.speedLabel || ''
                            );

                            setTimeout(connectSSE, 1000 * reconnectAttempts);
                            return;
                        }

                        showImportError('Gagal terhubung ke server untuk update progress import.');
                    };
                };

                connectSSE();
            } catch (err) {
                stopImportProgressTicker();
                themedSwal({
                    icon: 'error',
                    title: 'Terjadi Kesalahan',
                    text: 'Import gagal dijalankan!',
                    confirmButtonText: 'Tutup'
                });
                resetImportButton();
            }
        });

        Object.keys(filterState).forEach(function (col) {
            renderFilterList(col);
        });
        updatePreviewTable();
        setTimeout(prefetchAllFilterOptions, 0);
        setTimeout(prewarmPreviewIndex, 50);
        if (filePathValue && filterOptionsUrl) {
            prefetchAllFilterOptions().catch(function (error) {
                console.warn('Prefetch filter options failed:', error);
            });
        }
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(function () {
            const rows = Array.from(document.querySelectorAll('.preview-row'));
            if (!rows.length || rows.some(function (row) { return !row.classList.contains('d-none'); })) {
                return;
            }

            rows.slice(0, 100).forEach(function (row) {
                row.classList.remove('d-none');
            });

            const emptyRow = document.getElementById('empty-state-row');
            if (emptyRow) {
                emptyRow.classList.add('d-none');
            }
        }, 250);
    });
</script>
<style>
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

    .swal-import-phase {
        color: #0f766e;
        font-size: 0.76rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
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
        height: 15px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
        box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.08);
    }

    .swal-import-progress__bar {
        position: relative;
        background: linear-gradient(90deg, #0f766e 0%, #14b8a6 48%, #2dd4bf 100%);
        background-size: 200% 100%;
        font-weight: 800;
        font-size: 11px;
        line-height: 14px;
        transition: width 220ms cubic-bezier(0.22, 1, 0.36, 1);
        animation: swalImportShine 1.8s linear infinite;
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
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    @keyframes swalImportShine {
        0% {
            background-position: 0% 50%;
        }

        100% {
            background-position: 200% 50%;
        }
    }

    .swal-import-stat {
        min-height: 94px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
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

    .swal-import-stat__detail {
        display: block;
        margin-top: 0.35rem;
        color: #64748b;
        font-size: 0.72rem;
        line-height: 1.35;
    }
</style>
@endsection
