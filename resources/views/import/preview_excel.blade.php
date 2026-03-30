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
                    <i class="fas fa-info-circle text-info"></i> <strong>Smart Parser Aktif:</strong> Metadata pada Excel telah dihapus dan struktur kolom telah dinormalisasi. Anda dapat memfilter tabel secara <i>realtime</i> (menampilkan maks 100 baris pertama).
                </div>
            </div>
        </div>

        <form id="importForm" method="POST">
            @csrf
            <input type="hidden" name="path" id="file_path" value="{{ $path }}">
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
                                        @php
                                            $isCabang = stripos($header, 'CABANG') !== false || stripos($header, 'KCI') !== false || stripos($header, 'KANCA') !== false;
                                        @endphp

                                        <th class="align-middle bg-light" style="min-width: 250px;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                
                                                <div class="font-weight-bold text-dark text-truncate" style="max-width: 180px;" title="{{ $header }}">
                                                    {{ $header }}
                                                </div>

                                                @if(isset($formattedUniqueValues[$index]) && count($formattedUniqueValues[$index]) > 0)
                                                <div class="dropdown">
                                                    <button class="btn btn-xs btn-light border dropdown-toggle filter-btn" type="button" data-toggle="dropdown" aria-expanded="false" data-boundary="window">
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
                                                        <div class="p-2 bg-white" id="list_container_{{ $index }}" style="max-height: 250px; overflow-y: auto;">
                                                            @foreach($formattedUniqueValues[$index] as $cleanVal)
                                                                <div class="custom-control custom-checkbox filter-item-container mb-1">
                                                                    <input class="custom-control-input filter-checkbox" type="checkbox" 
                                                                           id="filter_{{ $index }}_{{ $loop->index }}" 
                                                                           value="{{ $cleanVal }}" 
                                                                           data-col="{{ $index }}" checked>
                                                                    <label for="filter_{{ $index }}_{{ $loop->index }}" class="custom-control-label font-weight-normal filter-label">
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
                                <!-- 🔥 Slicing di Server PHP (Beban Browser akan turun drastis) -->
                                @foreach(array_slice($preview, 0, 100, true) as $rowIndex => $row)
                                    <tr class="preview-row d-none"> 
                                        <td class="text-center text-muted">{{ $rowIndex + 1 }}</td>
                                        
                                        @foreach($headers as $colIndex => $header)
                                            @php 
                                                $cellValue = trim($row[$header] ?? ''); 
                                                $dataVal = $cellValue === '' ? '(Blank)' : $cellValue;
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
        
        const dropdownMenus = document.querySelectorAll('.dropdown-menu');
        dropdownMenus.forEach(menu => {
            menu.addEventListener('click', function (e) { e.stopPropagation(); });
        });

        function updatePreviewTable() {
            let activeFilters = {};
            
            document.querySelectorAll('.filter-checkbox').forEach(cb => {
                let col = cb.getAttribute('data-col');
                if (!activeFilters[col]) activeFilters[col] = [];
                if (cb.checked) {
                    activeFilters[col].push(cb.value.trim()); 
                }
            });

            let filterReqs = [];
            for (let col in activeFilters) {
                filterReqs.push({
                    index: parseInt(col) + 1,
                    allowed: activeFilters[col]
                });
            }

            let matchingRows = []; 
            
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
                    matchingRows.push(row);
                }
            });

            document.querySelectorAll('.preview-row').forEach(row => row.classList.add('d-none'));

            matchingRows.slice(0, 100).forEach(row => {
                row.classList.remove('d-none');
            });

            const emptyRow = document.getElementById('empty-state-row');
            if (emptyRow) {
                if (matchingRows.length === 0) {
                    emptyRow.classList.remove('d-none');
                } else {
                    emptyRow.classList.add('d-none');
                }
            }
            
            updateIconsColor();
        }

        function applyDefaultArea6() {
            let cabangColIndex = null;

            document.querySelectorAll('th').forEach((th, i) => {
                let text = th.innerText.toUpperCase();
                if (text.includes('CABANG') || text.includes('KANCA') || text.includes('KCI')) {
                    cabangColIndex = i - 1; 
                }
            });

            if (cabangColIndex !== null) {
                document.querySelectorAll(`.filter-checkbox[data-col="${cabangColIndex}"]`).forEach(cb => {
                    let val = cb.value.toUpperCase();
                    if (!['KC MADIUN','KC MAGETAN','KC NGAWI','KC PONOROGO'].includes(val)) {
                        cb.checked = false;
                    }
                });
                
                let selectAllArea = document.getElementById('select_all_' + cabangColIndex);
                if (selectAllArea) {
                    selectAllArea.checked = false;
                }
            }
        }

        function updateIconsColor() {
            const dropdowns = document.querySelectorAll('.dropdown');
            dropdowns.forEach(dropdown => {
                const container = dropdown.querySelector('[id^="list_container_"]');
                if (container) {
                    const colIndex = container.id.split('_')[2];
                    const allChecked = container.querySelectorAll('.filter-checkbox:checked');
                    const totalCbs = container.querySelectorAll('.filter-checkbox');
                    const icon = document.getElementById('icon_filter_' + colIndex);
                    
                    if (icon) {
                        if (allChecked.length < totalCbs.length && allChecked.length > 0) {
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

        const filterCheckboxes = document.querySelectorAll('.filter-checkbox');
        filterCheckboxes.forEach(cb => {
            cb.addEventListener('change', function () {
                const colIndex = this.getAttribute('data-col');
                const container = document.getElementById('list_container_' + colIndex);
                const allChecked = container.querySelectorAll('.filter-checkbox:checked');
                const totalCbs = container.querySelectorAll('.filter-checkbox');
                const selectAll = document.getElementById('select_all_' + colIndex);

                if (allChecked.length < totalCbs.length) {
                    selectAll.checked = false; 
                } else {
                    selectAll.checked = true;
                }
                updatePreviewTable(); 
            });
        });

        const selectAllCbs = document.querySelectorAll('.select-all-cb');
        selectAllCbs.forEach(cb => {
            cb.addEventListener('change', function () {
                const isChecked = this.checked;
                const colIndex = this.getAttribute('data-col');
                const container = document.getElementById('list_container_' + colIndex);
                
                const visibleCheckboxes = container.querySelectorAll('.filter-item-container[style*="block"] .filter-checkbox, .filter-item-container:not([style*="none"]) .filter-checkbox');
                
                visibleCheckboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                });

                updatePreviewTable();
            });
        });

        const searchInputs = document.querySelectorAll('.search-filter');
        searchInputs.forEach(input => {
            input.addEventListener('keyup', function () {
                const term = this.value.toLowerCase();
                const colIndex = this.getAttribute('data-col');
                const container = document.getElementById('list_container_' + colIndex);
                const items = container.querySelectorAll('.filter-item-container');

                items.forEach(item => {
                    const label = item.querySelector('.filter-label').innerText.toLowerCase();
                    if (label.includes(term)) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });

        document.getElementById('importForm').addEventListener('submit', async function(e) {
            e.preventDefault(); 
            
            let submitBtn = document.getElementById('btnSubmitImport');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses Excel...';
            }
            
            let activeFilters = {};
            const dropdowns = document.querySelectorAll('.dropdown');
            dropdowns.forEach(dropdown => {
                const container = dropdown.querySelector('[id^="list_container_"]');
                if (container) {
                    const colIndex = container.id.split('_')[2];
                    const allCbs = container.querySelectorAll('.filter-checkbox');
                    const checkedCbs = container.querySelectorAll('.filter-checkbox:checked');

                    if (checkedCbs.length < allCbs.length) {
                        activeFilters[colIndex] = Array.from(checkedCbs).map(cb => cb.value.trim());
                    }
                }
            });
            
            const filtersJson = JSON.stringify(activeFilters);
            const csrfToken = document.querySelector('input[name="_token"]').value;
            const pathValue = document.getElementById('file_path').value;

            const fetchHeaders = {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };

            Swal.fire({
                title: 'Inisialisasi Import...',
                html: `
                    <div class="text-left mb-2 text-sm text-muted" id="swal-status-text">Memeriksa struktur tabel dan database...</div>
                    <div class="progress" style="height: 18px; border-radius: 10px; background-color: #e9ecef;">
                        <div id="swal-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 0%; font-weight:bold;">0%</div>
                    </div>
                `,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
            });

            try {
                let formData = new FormData();
                formData.append('path', pathValue);

                let resInitRaw = await fetch('{{ route("import.excel.init") }}', { method: 'POST', body: formData, headers: fetchHeaders });
                let textInit = await resInitRaw.text();
                let resInit;

                try {
                    resInit = JSON.parse(textInit);
                } catch(err) {
                    console.error("RAW INIT HTML RESPONSE:", textInit);
                    let titleMatch = textInit.match(/<title>(.*?)<\/title>/i);
                    let errorTitle = titleMatch ? titleMatch[1] : 'Internal Server Error';
                    throw new Error(`<b>Gagal Inisialisasi!</b><br>Server mengalami Fatal Crash: <i>${errorTitle}</i>.<br><small>Buka Inspect Element (F12) -> Console untuk melihat error aslinya.</small>`);
                }

                if (!resInitRaw.ok || resInit.status === 'error' || resInit.message) {
                    throw new Error(resInit.text || resInit.message || 'Gagal merespons inisialisasi.');
                }

                let jobId = resInit.job_id;
                let totalRows = resInit.total_rows;
                let headerIndex = resInit.header_index;
                let tableName = resInit.table_name;
                let excelFilePath = resInit.file_path;
                // Single-request full-run (sesuai kebutuhan: no frontend chunk loop)
                document.getElementById('swal-progress-bar').style.width = '20%';
                document.getElementById('swal-progress-bar').innerText = '20%';
                document.getElementById('swal-status-text').innerText = 'Mempersiapkan mapping kolom dan filter...';

                let chunkData = new FormData();
                chunkData.append('job_id', jobId);
                chunkData.append('start_row', headerIndex + 1);
                chunkData.append('chunk_size', Math.max(1, totalRows - (headerIndex + 1)));
                chunkData.append('header_index', headerIndex);
                chunkData.append('table_name', tableName);
                chunkData.append('active_filters_json', filtersJson);
                chunkData.append('file_path', excelFilePath);

                // Progress simulasi tahap proses agar loading lebih jelas
                const stagedProgress = [
                    { p: 35, text: 'Membaca file Excel...' },
                    { p: 60, text: 'Menyimpan data ke MySQL (batch insert)...' },
                    { p: 85, text: 'Finalisasi proses import...' },
                ];
                let stageIdx = 0;
                const progressTimer = setInterval(() => {
                    if (stageIdx < stagedProgress.length) {
                        document.getElementById('swal-progress-bar').style.width = stagedProgress[stageIdx].p + '%';
                        document.getElementById('swal-progress-bar').innerText = stagedProgress[stageIdx].p + '%';
                        document.getElementById('swal-status-text').innerText = stagedProgress[stageIdx].text;
                        stageIdx++;
                    }
                }, 1200);

                let resChunkRaw = await fetch('{{ route("import.excel.chunk") }}', {
                    method: 'POST',
                    body: chunkData,
                    headers: fetchHeaders
                });

                clearInterval(progressTimer);

                let textChunk = await resChunkRaw.text();
                let resChunk;

                try {
                    resChunk = JSON.parse(textChunk);
                } catch (err) {
                
                let finData = new FormData();
                finData.append('job_id', jobId);
                finData.append('file_path', excelFilePath); 
                
                let resFinRaw = await fetch('{{ route("import.excel.finish") }}', { method: 'POST', body: finData, headers: fetchHeaders });
                let textFin = await resFinRaw.text();
                let resFin;

                try {
                    resFin = JSON.parse(textFin);
                } catch(err) {
                    console.error("RAW FINISH HTML RESPONSE:", textFin);
                    throw new Error(`<b>Gagal Penyelesaian Akhir!</b><br>Server HTML Crash.<br><small>Cek Console (F12) untuk detail.</small>`);
                }

                if (!resFinRaw.ok || resFin.status === 'error' || resFin.message) {
                    throw new Error(resFin.text || resFin.message || 'Terjadi kegagalan saat menutup Sesi Import.');
                }

                if (resFin.total_success === 0) {
                    // Ambil debug info dari chunk response terakhir jika ada
                    let debugHtml = '';
                    if (window._lastChunkDebug) {
                        const d = window._lastChunkDebug;
                        debugHtml = `
                            <hr class="my-2">
                            <div class="text-left" style="font-size:12px;">
                                <b>🔍 Debug Info:</b><br>
                                • Tabel tujuan: <code>${d.table || '-'}</code><br>
                                • Baris terbaca: <b>${d.rows_read ?? 0}</b><br>
                                • Baris lolos filter: <b>${d.rows_passed ?? 0}</b><br>
                                • Kolom DB tersedia: <code>${(d.table_columns || []).slice(0,6).join(', ')}${(d.table_columns||[]).length > 6 ? '...' : ''}</code><br>
                                • Header Excel: <code>${(d.excel_headers || []).slice(0,6).join(', ')}${(d.excel_headers||[]).length > 6 ? '...' : ''}</code><br>
                                ${d.active_filters && Object.keys(d.active_filters).length > 0 ? `• Filter aktif: <code>${JSON.stringify(d.active_filters).substring(0,120)}</code><br>` : '• Tidak ada filter aktif<br>'}
                                ${d.sample_row ? `• Sample row: <code>${JSON.stringify(d.sample_row).substring(0,150)}</code>` : '• Tidak ada baris yang lolos mapping'}
                            </div>`;
                    }
                    Swal.fire({
                        icon: 'warning',
                        title: 'Tidak Ada Data Masuk',
                        html: `0 baris terimport. Kemungkinan semua data terfilter, baris kosong, atau kolom Excel tidak cocok dengan kolom tabel DB.${debugHtml}`,
                        width: 650,
                    }).then(() => { window.location.href = "{{ route('import.index') }}"; });
                } else if (resFin.total_failed > 0) {
                     Swal.fire({
                        icon: 'warning',
                        title: 'Import Selesai dengan Catatan',
                        html: `Berhasil: ${resFin.total_success} baris.<br>Gagal: ${resFin.total_failed} baris (Kemungkinan Data Duplikat / Kolom Mismatch).`
                    }).then(() => { window.location.href = "{{ route('import.index') }}"; });
                } else {
                    Swal.fire({
                        icon: 'success',
                        title: 'Import Sukses!',
                        text: `Berhasil mengimport ${resFin.total_success} baris data tanpa kendala.`
                    }).then(() => { window.location.href = "{{ route('import.index') }}"; });
                }

            } catch (e) {
                Swal.fire({
                    icon: 'error',
                    title: 'Proses Terhenti',
                    html: e.message || 'Koneksi terputus atau terjadi kesalahan server saat Chunking.'
                });
                
                if (submitBtn) { 
                    submitBtn.disabled = false; 
                    submitBtn.innerHTML = '<i class="fas fa-database"></i> Jalankan Import ke MySQL'; 
                }
            }
        });

        applyDefaultArea6();
        updatePreviewTable();
    });
</script>
@endsection