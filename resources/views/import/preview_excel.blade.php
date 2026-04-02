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
                    <i class="fas fa-file-excel mr-1"></i> Preview Excel Data (Daily Loan Dinamis / Simpanan MultiPN)
                </h3>
            </div>
            <div class="card-body">
                <div class="alert alert-info border-0 bg-light text-dark">
                    <i class="fas fa-info-circle text-info"></i>
                    <strong>Smart Parser Aktif:</strong> Metadata pada Excel telah dihapus dan struktur kolom telah dinormalisasi.
                    Anda dapat memfilter tabel secara <i>realtime</i> (menampilkan maks 100 baris pertama).
                </div>
            </div>
        </div>

        <form id="importForm" method="POST">
            @csrf
            <input type="hidden" name="path"                id="file_path"           value="{{ $path }}">
            <input type="hidden" name="active_filters_json" id="active_filters_json" value="{}">

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
                                                             style="max-height: 250px; overflow-y: auto;">
                                                            @foreach($formattedUniqueValues[$index] as $cleanVal)
                                                                <div class="custom-control custom-checkbox filter-item-container mb-1">
                                                                    <input class="custom-control-input filter-checkbox" type="checkbox"
                                                                           id="filter_{{ $index }}_{{ $loop->index }}"
                                                                           value="{{ $cleanVal }}"
                                                                           data-col="{{ $index }}" checked>
                                                                    <label for="filter_{{ $index }}_{{ $loop->index }}"
                                                                           class="custom-control-label font-weight-normal filter-label">
                                                                        {{ $cleanVal }}
                                                                    </label>
                                                                </div>
                                                            @endforeach
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
                                    <tr class="preview-row d-none">
                                        <td class="text-center text-muted">{{ $rowIndex + 1 }}</td>
                                        @foreach($headers as $colIndex => $header)
                                            @php
                                                $cellValue = trim($row[$header] ?? '');
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
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

    /* =========================================================
       PREVIEW TABLE FILTER
    ========================================================= */
    function updatePreviewTable() {
        // Kumpulkan filter aktif: { colIndex: [allowedValues...] }
        var activeFilters = {};
        document.querySelectorAll('.filter-checkbox').forEach(function (cb) {
            var col = cb.getAttribute('data-col');
            if (!activeFilters[col]) activeFilters[col] = [];
            if (cb.checked) activeFilters[col].push(cb.value.trim());
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
            var checked   = container.querySelectorAll('.filter-checkbox:checked').length;
            var total     = container.querySelectorAll('.filter-checkbox').length;
            var icon      = document.getElementById('icon_filter_' + colIndex);
            if (!icon) return;
            if (checked < total && checked > 0) {
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
    document.querySelectorAll('.filter-checkbox').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var colIndex  = this.getAttribute('data-col');
            var container = document.getElementById('list_container_' + colIndex);
            var checked   = container.querySelectorAll('.filter-checkbox:checked').length;
            var total     = container.querySelectorAll('.filter-checkbox').length;
            var selectAll = document.getElementById('select_all_' + colIndex);
            if (selectAll) selectAll.checked = (checked === total);
            updatePreviewTable();
        });
    });

    /* =========================================================
       EVENT: Select All checkbox
    ========================================================= */
    document.querySelectorAll('.select-all-cb').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var isChecked = this.checked;
            var colIndex  = this.getAttribute('data-col');
            var container = document.getElementById('list_container_' + colIndex);
            // Hanya toggle checkbox yang sedang terlihat (tidak di-hide oleh search)
            container.querySelectorAll('.filter-item-container').forEach(function (item) {
                if (item.style.display !== 'none') {
                    var checkbox = item.querySelector('.filter-checkbox');
                    if (checkbox) checkbox.checked = isChecked;
                }
            });
            updatePreviewTable();
        });
    });

    /* =========================================================
       EVENT: Search filter
    ========================================================= */
    document.querySelectorAll('.search-filter').forEach(function (input) {
        input.addEventListener('keyup', function () {
            var term      = this.value.toLowerCase();
            var colIndex  = this.getAttribute('data-col');
            var container = document.getElementById('list_container_' + colIndex);
            container.querySelectorAll('.filter-item-container').forEach(function (item) {
                var label = item.querySelector('.filter-label');
                item.style.display = (label && label.innerText.toLowerCase().includes(term)) ? 'block' : 'none';
            });
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
        document.querySelectorAll('.dropdown').forEach(function (dropdown) {
            var container = dropdown.querySelector('[id^="list_container_"]');
            if (!container) return;
            var colIndex  = container.id.split('_')[2];
            var allCbs    = container.querySelectorAll('.filter-checkbox');
            var checked   = container.querySelectorAll('.filter-checkbox:checked');
            if (checked.length < allCbs.length) {
                activeFilters[colIndex] = Array.from(checked).map(function (cb) { return cb.value.trim(); });
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
            <div style="font-family:inherit;">
                <div class="d-flex justify-content-between mb-3 px-1">
                    <div class="text-center" id="step-init" style="flex:1;">
                        <div class="step-icon mx-auto mb-1" style="width:36px;height:36px;border-radius:50%;background:#e9ecef;display:flex;align-items:center;justify-content:center;font-size:14px;transition:all .4s;">
                            <i class="fas fa-cog text-muted"></i>
                        </div>
                        <small class="text-muted" style="font-size:10px;">Inisialisasi</small>
                    </div>
                    <div style="flex:0.3;display:flex;align-items:center;padding-bottom:18px;">
                        <div style="height:2px;width:100%;background:#e9ecef;" id="line-1"></div>
                    </div>
                    <div class="text-center" id="step-read" style="flex:1;">
                        <div class="step-icon mx-auto mb-1" style="width:36px;height:36px;border-radius:50%;background:#e9ecef;display:flex;align-items:center;justify-content:center;font-size:14px;transition:all .4s;">
                            <i class="fas fa-file-excel text-muted"></i>
                        </div>
                        <small class="text-muted" style="font-size:10px;">Baca File</small>
                    </div>
                    <div style="flex:0.3;display:flex;align-items:center;padding-bottom:18px;">
                        <div style="height:2px;width:100%;background:#e9ecef;" id="line-2"></div>
                    </div>
                    <div class="text-center" id="step-insert" style="flex:1;">
                        <div class="step-icon mx-auto mb-1" style="width:36px;height:36px;border-radius:50%;background:#e9ecef;display:flex;align-items:center;justify-content:center;font-size:14px;transition:all .4s;">
                            <i class="fas fa-database text-muted"></i>
                        </div>
                        <small class="text-muted" style="font-size:10px;">Insert DB</small>
                    </div>
                    <div style="flex:0.3;display:flex;align-items:center;padding-bottom:18px;">
                        <div style="height:2px;width:100%;background:#e9ecef;" id="line-3"></div>
                    </div>
                    <div class="text-center" id="step-done" style="flex:1;">
                        <div class="step-icon mx-auto mb-1" style="width:36px;height:36px;border-radius:50%;background:#e9ecef;display:flex;align-items:center;justify-content:center;font-size:14px;transition:all .4s;">
                            <i class="fas fa-check text-muted"></i>
                        </div>
                        <small class="text-muted" style="font-size:10px;">Selesai</small>
                    </div>
                </div>
                <div class="progress mb-2" style="height:22px;border-radius:12px;background:#e9ecef;overflow:hidden;">
                    <div id="swal-progress-bar"
                         class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                         style="width:0%;font-weight:700;font-size:13px;transition:width .6s ease;line-height:22px;">0%</div>
                </div>
                <div class="d-flex justify-content-between mb-1" style="font-size:12px;">
                    <span id="swal-rows-info" class="text-muted">0 / 0 baris</span>
                    <span id="swal-speed-info" class="text-muted"></span>
                </div>
                <div id="swal-status-text"
                     class="text-center py-2 px-3 rounded"
                     style="background:#f8f9fa;font-size:13px;color:#495057;min-height:38px;line-height:1.4;">
                    Memeriksa struktur file dan database...
                </div>
            </div>`;

        themedSwal({
            title: '<i class="fas fa-file-import text-success mr-1"></i> Import Data Excel',
            html: swalHtml,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            width: 520,
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
            var st  = document.getElementById('swal-status-text');
            var ri  = document.getElementById('swal-rows-info');
            var si  = document.getElementById('swal-speed-info');
            if (bar) { bar.style.width = pct + '%'; bar.innerText = pct + '%'; }
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
            var formData = new FormData();
            formData.append('path', pathValue);
            formData.append('active_filters_json', filtersJson);

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
        var evtSource  = null;
        var streamDone = false;
        var lastProg   = { percent: 12, message: 'Menginisialisasi...', rows_done: 0, total: 0, speed: 0 };

        function connectSSE() {
            if (streamDone) return;
            evtSource = new EventSource(streamUrl);

            // ── progress event ──────────────────────────────────────────────
            evtSource.addEventListener('progress', function (e) {
                var d = {};
                try { d = JSON.parse(e.data); } catch (_) {}
                if (!d) return;

                lastProg = {
                    percent:  d.percent   != null ? d.percent   : lastProg.percent,
                    message:  d.message   != null ? d.message   : lastProg.message,
                    rows_done: d.rows_done != null ? d.rows_done : lastProg.rows_done,
                    total:    d.total     != null ? d.total     : lastProg.total,
                    speed:    d.speed     != null ? d.speed     : lastProg.speed,
                };

                if (lastProg.percent >= 5  && lastProg.percent < 22) activateStep('step-read',   'line-2');
                if (lastProg.percent >= 22) { activateStep('step-read', 'line-2'); activateStep('step-insert', 'line-3'); }

                setProgress(lastProg.percent, lastProg.message, lastProg.rows_done, lastProg.total, lastProg.speed);
            });

            // ── complete event ──────────────────────────────────────────────
            evtSource.addEventListener('complete', function (e) {
                streamDone = true;
                evtSource.close();

                var d = {};
                try { d = JSON.parse(e.data); } catch (_) {}

                activateStep('step-done', 'line-3');
                setProgress(100, 'Import selesai!', d.total_rows || 0, d.total_rows || 0, 0);

                setTimeout(function () {
                    if (!d.total_success || d.total_success === 0) {
                        themedSwal({
                            icon: 'warning',
                            title: 'Tidak Ada Data Masuk',
                            html: '<p>✅ Total: <b>' + Number(d.total_rows || 0).toLocaleString('id-ID') + ' baris</b></p>' +
                                  '<p>❌ Gagal: <b>' + Number(d.total_failed || 0).toLocaleString('id-ID') + ' baris</b></p>' +
                                  '<small class="text-muted">Sebagian baris gagal diproses atau terbatasi oleh filter yang aktif.</small>',
                            confirmButtonText: 'Kembali ke Import',
                        }).then(function () { window.location.href = '{{ route("import.index") }}'; });
                    } else {
                        themedSwal({
                            icon: 'success',
                            title: 'Import Sukses! 🎉',
                            html: 'Berhasil mengimport <b>' + Number(d.total_success).toLocaleString('id-ID') + ' baris</b> data ke database.' +
                                  (d.total_failed > 0 ? '<br><small class="text-warning">⚠ ' + Number(d.total_failed).toLocaleString('id-ID') + ' baris gagal saat insert atau tidak lolos proses validasi.</small>' : ''),
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
                evtSource.close();
                streamDone = true;
                themedSwal({ icon: 'error', title: 'Proses Terhenti', html: msg, confirmButtonText: 'Tutup' });
                resetSubmitBtn();
            });

            // ── onerror (koneksi putus / network drop) ──────────────────────
            evtSource.onerror = function () {
                if (streamDone) return;
                evtSource.close();
                streamDone = true;
                themedSwal({
                    icon: 'error',
                    title: 'Koneksi Stream Terputus',
                    html: 'Koneksi import dihentikan untuk mencegah proses berjalan ganda dan data menjadi dobel.<br>' +
                          '<small>Silakan cek hasil import terlebih dahulu sebelum menjalankan ulang.</small>',
                    confirmButtonText: 'Tutup'
                });
                resetSubmitBtn();
            };
        }

        connectSSE();
    });

    /* =========================================================
       INIT: terapkan filter default lalu tampilkan preview
    ========================================================= */
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
</style>
@endsection
