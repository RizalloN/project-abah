@extends('layouts.admin')

@section('title', 'Preview & Filter Data - Daily Loan Dinamis')

@section('content')
<div class="row">
    <div class="col-12">

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card card-outline card-success shadow-sm mb-3">
            <div class="card-header bg-light">
                <h3 class="card-title font-weight-bold text-success">
                    <i class="fas fa-file-import mr-1"></i> Preview Data Import (Daily Loan Dinamis / Simpanan MultiPN)
                </h3>
            </div>
            <div class="card-body">
                <div class="alert alert-info border-0 bg-light text-dark">
                    <i class="fas fa-info-circle text-info"></i>
                    <strong>Smart Parser Aktif:</strong> Struktur kolom file import telah dinormalisasi dan siap difilter.
                    Anda dapat memfilter tabel secara <i>realtime</i> (menampilkan maks 100 baris pertama).
                </div>
            </div>
        </div>

        <form id="importForm" method="POST">
            @csrf
            <input type="hidden" name="path"                id="file_path"           value="{{ $path }}">
            <input type="hidden" name="active_filters_json" id="active_filters_json" value="{}">
            @if(!empty($previewStateKey))
                <input type="hidden" name="preview_state_key" value="{{ $previewStateKey }}">
            @endif

            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <div class="card-tools w-100 d-flex justify-content-between">
                        <a href="{{ route('import.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                        <button type="submit" id="btnSubmitImport" class="btn btn-success font-weight-bold">
                            <i class="fas fa-database"></i> Jalankan Import ke MySQL
                        </button>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive" style="min-height: 450px; max-height: 600px; overflow-y: auto; overflow-x: auto;">
                        <table class="table table-bordered table-hover m-0">
                            <thead class="thead-light sticky-top" style="z-index: 2;">
                                <tr>
                                    <th class="text-center align-middle bg-light" style="width: 50px;">#</th>

                                    @foreach($headers as $index => $header)
                                        <th class="align-middle bg-light" style="min-width: 250px;">
                                            <div class="d-flex justify-content-between align-items-center">

                                                <div class="font-weight-bold text-dark text-truncate" style="max-width: 180px;" title="{{ $header }}">
                                                    {{ $header }}
                                                </div>

                                                @if(isset($formattedUniqueValues[$index]) && count($formattedUniqueValues[$index]) > 0)
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
    const filterState = {};
    const searchTerms = {};
    const filterRenderLimit = 200;

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

    /* =========================================================
       DROPDOWN: klik di dalam menu tidak menutup dropdown
    ========================================================= */
    document.querySelectorAll('.dropdown-menu').forEach(function (menu) {
        menu.addEventListener('click', function (e) { e.stopPropagation(); });
    });

    Object.keys(filterOptionsMap).forEach(function (col) {
        const values = Array.isArray(filterOptionsMap[col]) ? filterOptionsMap[col].map(function (value) {
            return String(value);
        }) : [];

        filterState[col] = {
            allValues: values,
            selectedValues: new Set(values),
        };
        searchTerms[col] = '';
    });

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getFilteredValues(col) {
        const state = filterState[col];
        if (!state) {
            return [];
        }

        const term = (searchTerms[col] || '').toLowerCase();
        if (!term) {
            return state.allValues.slice();
        }

        return state.allValues.filter(function (value) {
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

        if (!filteredValues.length) {
            html = '<div class="text-center text-muted py-2 small">Tidak ada opsi yang cocok.</div>';
        } else {
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
        syncSelectAllCheckbox(col, filteredValues);
    }

    /* =========================================================
       PREVIEW TABLE FILTER
    ========================================================= */
    function updatePreviewTable() {
        // Kumpulkan filter aktif: { colIndex: [allowedValues...] }
        var activeFilters = {};
        Object.keys(filterState).forEach(function (col) {
            var state = filterState[col];
            if (!state) return;

            if (state.selectedValues.size < state.allValues.length) {
                activeFilters[col] = Array.from(state.selectedValues);
            }
        });

        // Bangun array requirement filter
        var filterReqs = [];
        for (var col in activeFilters) {
            filterReqs.push({
                index:   parseInt(col) + 1,   // +1 karena kolom pertama adalah "#"
                allowed: activeFilters[col]
            });
        }

        var matchingRows = [];
        document.querySelectorAll('.preview-row').forEach(function (row) {
            var pass = true;
            for (var i = 0; i < filterReqs.length; i++) {
                var req = filterReqs[i];
                if (req.allowed.length === 0) { pass = false; break; }
                var cell = row.children[req.index];
                if (cell) {
                    var cellVal = (cell.getAttribute('data-val') || '').trim();
                    if (req.allowed.indexOf(cellVal) === -1) { pass = false; break; }
                }
            }
            if (pass) matchingRows.push(row);
        });

        // Sembunyikan semua, lalu tampilkan yang lolos (maks 100)
        document.querySelectorAll('.preview-row').forEach(function (row) {
            row.classList.add('d-none');
        });
        matchingRows.slice(0, 100).forEach(function (row) {
            row.classList.remove('d-none');
        });

        var emptyRow = document.getElementById('empty-state-row');
        if (emptyRow) {
            emptyRow.classList.toggle('d-none', matchingRows.length > 0);
        }

        updateFilterIcons();
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
            if (state && state.selectedValues.size < state.allValues.length && state.selectedValues.size > 0) {
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

        var colIndex = e.target.getAttribute('data-col');
        var state = filterState[colIndex];
        if (!state) {
            return;
        }

        if (e.target.checked) {
            state.selectedValues.add(e.target.value.trim());
        } else {
            state.selectedValues.delete(e.target.value.trim());
        }

        syncSelectAllCheckbox(colIndex, getFilteredValues(colIndex));
        updatePreviewTable();
    });

    /* =========================================================
       EVENT: Select All checkbox
    ========================================================= */
    document.querySelectorAll('.select-all-cb').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var isChecked = this.checked;
            var colIndex  = this.getAttribute('data-col');
            var state = filterState[colIndex];
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

            renderFilterList(colIndex);
            updatePreviewTable();
        });
    });

    /* =========================================================
       EVENT: Search filter
    ========================================================= */
    document.querySelectorAll('.search-filter').forEach(function (input) {
        input.addEventListener('keyup', function () {
            var colIndex  = this.getAttribute('data-col');
            searchTerms[colIndex] = this.value || '';
            renderFilterList(colIndex);
        });
    });

    document.querySelectorAll('.dropdown').forEach(function (dropdown) {
        dropdown.addEventListener('shown.bs.dropdown', function () {
            var container = dropdown.querySelector('[id^="list_container_"]');
            if (!container) {
                return;
            }

            renderFilterList(container.getAttribute('data-col'));
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
        Object.keys(filterState).forEach(function (colIndex) {
            var state = filterState[colIndex];
            if (!state) {
                return;
            }

            if (state.selectedValues.size < state.allValues.length) {
                activeFilters[colIndex] = Array.from(state.selectedValues);
            }
        });
        var filtersJson = JSON.stringify(activeFilters);
        document.getElementById('active_filters_json').value = filtersJson;

        // ── Disable tombol ──────────────────────────────────────────────────
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
        }

        // ── Modal loading ───────────────────────────────────────────────────
        var swalHtml = `
            <div class="swal-import-shell">
                <div class="swal-import-head">
                    <span class="swal-import-badge"><i class="fas fa-circle-notch fa-spin mr-1"></i> Sedang diproses</span>
                    <div class="swal-import-title">Import Excel</div>
                    <div class="swal-import-desc">Memeriksa file dan menyiapkan data ke database.</div>
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
                        <span id="swal-rows-info" class="swal-import-stat__value">0 / 0</span>
                    </div>
                    <div class="swal-import-stat">
                        <span class="swal-import-stat__label">Kecepatan</span>
                        <span id="swal-speed-info" class="swal-import-stat__value">-</span>
                    </div>
                </div>
            </div>`;

        themedSwal({
            title: '<i class="fas fa-cloud-upload-alt text-success mr-1"></i> Import Data',
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
            if (bar) { bar.style.width = pct + '%'; bar.innerText = pct + '%'; }
            if (pp)  pp.innerText = pct + '%';
            if (st)  st.innerText = statusText || '';
            if (ri && total > 0) ri.innerText = Number(rowsDone).toLocaleString('id-ID') + ' / ' + Number(total).toLocaleString('id-ID') + ' baris';
            if (si && speed > 0) si.innerText = Number(speed).toLocaleString('id-ID') + ' baris/detik';
        }

        function resetSubmitBtn() {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-database"></i> Jalankan Import ke MySQL';
            }
        }

        // ── STEP 1: Inisialisasi (POST) ─────────────────────────────────────
        activateStep('step-init', null);
        setProgress(5, 'Menginisialisasi proses import...', 0, 0, 0);

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

            if (!resRaw.ok || resInit.status === 'error') {
                throw new Error(resInit.text || resInit.message || 'Gagal inisialisasi.');
            }

            jobId = resInit.job_id;
            activateStep('step-init', 'line-1');
            setProgress(12, 'Inisialisasi selesai. Membuka koneksi stream...', 0, 0, 0);

        } catch (err) {
            themedSwal({ icon: 'error', title: 'Gagal Inisialisasi', html: err.message, confirmButtonText: 'Tutup' });
            resetSubmitBtn();
            return;
        }

        // ── STEP 2 & 3: SSE Stream dengan auto-reconnect ───────────────────
        var streamUrl  = '{{ $streamRoute ?? route("import.excel.stream") }}?job_id=' + encodeURIComponent(jobId);
        var statusUrlTemplate = @json(route('import.jobs.status', ['jobId' => '__JOB_ID__']));
        var evtSource  = null;
        var streamDone = false;
        var reconnectAttempts = 0;
        var lastProg   = { percent: 12, message: 'Menginisialisasi...', rows_done: 0, total: 0, speed: 0 };

        function statusUrlForJob(jobId) {
            return statusUrlTemplate.replace('__JOB_ID__', encodeURIComponent(jobId));
        }

        function showImportFailure(message) {
            streamDone = true;
            if (evtSource) evtSource.close();
            themedSwal({ icon: 'error', title: 'Proses Terhenti', html: message || 'Import gagal dijalankan!', confirmButtonText: 'Tutup' });
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
            streamDone = true;
            if (evtSource) evtSource.close();

            var skippedCount = Number(d.skipped_count || 0);
            var skippedRows = Array.isArray(d.skipped_rows) ? d.skipped_rows : [];
            var skippedRowsText = skippedRows.length ? skippedRows.join(', ') : '';
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
                    }).then(function () { window.location.href = '{{ route("import.index") }}'; });
                } else {
                    themedSwal({
                        icon: d.total_failed > 0 ? 'warning' : 'success',
                        title: d.total_failed > 0 ? 'Import Memiliki Kendala' : 'Import Sukses',
                        html: 'Berhasil mengimport <b>' + Number(d.total_success).toLocaleString('id-ID') + ' baris</b> data ke database.' +
                              (d.total_failed > 0 ? '<br><small class="text-warning">' + Number(d.total_failed).toLocaleString('id-ID') + ' baris gagal saat insert atau tidak lolos proses validasi.</small>' : '') +
                              skippedHtml,
                        confirmButtonText: 'Lanjut',
                    }).then(function () { window.location.href = '{{ route("import.index") }}'; });
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
            evtSource.addEventListener('complete', function (e) {
                showImportSuccess((function () {
                    var payload = {};
                    try { payload = JSON.parse(e.data); } catch (_) {}
                    return payload;
                })());
                return;
                var d = {};
                try { d = JSON.parse(e.data); } catch (_) {}
                var skippedCount = Number(d.skipped_count || 0);
                var skippedRows = Array.isArray(d.skipped_rows) ? d.skipped_rows : [];
                var skippedRowsText = skippedRows.length ? skippedRows.join(', ') : '';
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
                        }).then(function () { window.location.href = '{{ route("import.index") }}'; });
                    } else {
                        themedSwal({
                            icon: 'success',
                            title: 'Import Sukses! 🎉',
                            html: 'Berhasil mengimport <b>' + Number(d.total_success).toLocaleString('id-ID') + ' baris</b> data ke database.' +
                                  (d.total_failed > 0 ? '<br><small class="text-warning">⚠ ' + Number(d.total_failed).toLocaleString('id-ID') + ' baris gagal saat insert atau tidak lolos proses validasi.</small>' : '') +
                                  skippedHtml,
                            confirmButtonText: 'Lanjut',
                        }).then(function () { window.location.href = '{{ route("import.index") }}'; });
                    }
                }, 600);
            });

            // ── error event (server kirim event error) ──────────────────────
            evtSource.addEventListener('error', function (e) {
                if (streamDone) return;
                var msg = lastProg.message || 'Terjadi kesalahan server.';
                try { var d = JSON.parse(e.data); if (d && d.message) msg = d.message; } catch (_) {}
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
                    if (reconnectAttempts <= 10) {
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
    Object.keys(filterState).forEach(function (col) {
        renderFilterList(col);
    });
    updatePreviewTable();
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
        gap: 0.45rem;
    }

    .swal-import-badge {
        display: inline-flex;
        align-items: center;
        width: fit-content;
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
</style>
@endsection
