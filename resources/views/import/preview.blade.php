@extends('layouts.admin')

@section('title', 'Preview & Filter Data Excel')

@section('content')
<div class="row">
    <div class="col-12">
        
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card card-outline card-primary mb-3">
            <div class="card-header bg-light">
                <h3 class="card-title font-weight-bold">
                    <i class="fas fa-cogs text-primary"></i> Pengaturan Delimiter (Pemisah Kolom)
                </h3>
            </div>
            <div class="card-body py-2">
                <form action="{{ session('import_type') === 'brimo' ? route('import.brimo.preview') : route('import.preview') }}" method="POST" class="form-inline">
                    @csrf
                    <input type="hidden" name="file_path" value="{{ $filePath }}">
                    <label class="mr-3" for="delimiter">Jika tabel berantakan, ubah pemisah kolom di sini:</label>
                    <select name="delimiter" id="delimiter" class="form-control mr-3" style="min-width: 250px;">
                        <option value="auto" {{ $currentDelimiter == 'auto' ? 'selected' : '' }}>Otomatis (Auto Detect)</option>
                        <option value="," {{ $currentDelimiter == ',' ? 'selected' : '' }}>Koma ( , )</option>
                        <option value=";" {{ $currentDelimiter == ';' ? 'selected' : '' }}>Titik Koma ( ; )</option>
                        <option value="|" {{ $currentDelimiter == '|' ? 'selected' : '' }}>Garis Lurus / Pipe ( | )</option>
                        <option value="." {{ $currentDelimiter == '.' ? 'selected' : '' }}>Titik ( . )</option>
                        <option value="\t" {{ $currentDelimiter == "\t" ? 'selected' : '' }}>Tab</option>
                    </select>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sync-alt"></i> Terapkan Ulang
                    </button>
                </form>
            </div>
        </div>

        <form id="importForm" action="{{ $processRoute ?? route('import.process') }}" method="POST">
            @csrf
            <input type="hidden" name="file_path" value="{{ $filePath }}">
            <input type="hidden" name="delimiter" value="{{ $currentDelimiter }}">
            <input type="hidden" name="active_filters_json" id="active_filters_json" value="{}">

            @php 
                $area6 = ['KC PONOROGO', 'KC NGAWI', 'KC MAGETAN', 'KC MADIUN', 'PONOROGO', 'NGAWI', 'MAGETAN', 'MADIUN']; 
            @endphp

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
                        <i class="fas fa-info-circle text-info"></i> <strong>Petunjuk:</strong> 
                        Klik ikon <i class="fas fa-filter text-muted mx-1"></i> di sebelah nama kolom untuk memfilter baris data. Tabel akan bereaksi secara realtime dan menampilkan <strong>maksimal 100 baris teratas</strong> sebagai bahan evaluasi.
                    </div>

                    <div class="table-responsive" style="min-height: 450px; max-height: 600px; overflow-y: auto; overflow-x: auto;">
                        <table class="table table-bordered table-hover m-0">
                            <thead class="thead-light sticky-top" style="z-index: 2;">
                                <tr>
                                    <th class="text-center align-middle bg-light" style="width: 50px;">#</th>
                                    @foreach($headers as $index => $header)
                                        @php
                                            $isKancaCol = (stripos($header, 'KANCA') !== false || stripos($header, 'KCI') !== false) && stripos($header, 'KODE') === false;
                                        @endphp

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

                                                @if(isset($formattedUniqueValues[$index]) && count($formattedUniqueValues[$index]) > 0)
                                                <input type="hidden" name="has_filter[]" value="{{ $index }}">

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
                                                                <input class="custom-control-input select-all-cb" type="checkbox" id="select_all_{{ $index }}" data-col="{{ $index }}" {{ $isKancaCol ? '' : 'checked' }}>
                                                                <label for="select_all_{{ $index }}" class="custom-control-label font-weight-bold text-dark">(Select All)</label>
                                                            </div>
                                                        </div>
                                                        <div class="p-2 bg-white" id="list_container_{{ $index }}" style="max-height: 250px; overflow-y: auto;">
                                                            @foreach($formattedUniqueValues[$index] as $val)
                                                                @php
                                                                    $cleanVal = trim($val);
                                                                    $isArea6 = in_array(strtoupper($cleanVal), $area6);
                                                                    $isChecked = $isKancaCol ? $isArea6 : true;
                                                                @endphp
                                                                <div class="custom-control custom-checkbox filter-item-container mb-1">
                                                                    <input class="custom-control-input filter-checkbox" type="checkbox" 
                                                                           id="filter_{{ $index }}_{{ $loop->index }}" 
                                                                           value="{{ $cleanVal }}" 
                                                                           data-col="{{ $index }}" {{ $isChecked ? 'checked' : '' }}>
                                                                    <label for="filter_{{ $index }}_{{ $loop->index }}" class="custom-control-label font-weight-normal filter-label">
                                                                        {{ $cleanVal === '' ? '(Blank)' : $cleanVal }}
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
                                @foreach($previewData as $rowIndex => $row)
                                    <tr class="preview-row d-none"> 
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
                                        <h5 class="font-weight-bold text-dark">Tidak ada kecocokan di 2500 Baris Sampel Preview</h5>
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
                    <a href="{{ route('import.select') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
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

        document.getElementById('importForm').addEventListener('submit', function(e) {
            e.preventDefault(); 
            
            let form = this;
            let submitBtn = document.getElementById('btnSubmitImport');
            
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            }
            
            // 🔥 FILTER (TIDAK DIUBAH)
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
            document.getElementById('active_filters_json').value = JSON.stringify(activeFilters);

            let formData = new FormData(form);

            Swal.fire({
                title: 'Sedang Memproses Data...',
                html: 'Sistem sedang memindahkan baris data ke MySQL.<br><br><b>Mohon tunggu dan jangan tutup halaman ini!</b>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                    'Accept': 'application/json' // 🔥 WAJIB MINTA JSON KE CONTROLLER
                }
            })
            .then(res => res.json())
            .then(res => {
                Swal.fire({
                    icon: res.status || 'success',
                    title: res.title || 'Selesai',
                    html: res.text || ''
                }).then(() => {
                    window.location.href = "{{ route('import.index') }}";
                });
            })
            .catch(err => {
                Swal.fire({
                    icon: 'error',
                    title: 'Terjadi Kesalahan',
                    text: 'Import gagal dijalankan!'
                });

                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-database"></i> Jalankan Import ke MySQL';
                }
            });
        });

        updatePreviewTable();
    });
</script>
@endsection