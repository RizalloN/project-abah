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
                <form action="{{ route('import.preview') }}" method="POST" class="form-inline">
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

        <form action="{{ route('import.process') }}" method="POST">
            @csrf
            <input type="hidden" name="file_path" value="{{ $filePath }}">
            <input type="hidden" name="delimiter" value="{{ $currentDelimiter }}">

            <div class="card card-outline card-success">
                <div class="card-header bg-light">
                    <h3 class="card-title text-success font-weight-bold">
                        <i class="fas fa-file-excel"></i> Table Data (Ala Excel Filter)
                    </h3>
                    <div class="card-tools">
                        <button type="submit" class="btn btn-sm btn-success">
                            <i class="fas fa-database"></i> Jalankan Import ke MySQL
                        </button>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <div class="alert alert-info m-3 border-0 bg-light text-dark">
                        <i class="fas fa-info-circle text-info"></i> <strong>Petunjuk:</strong> 
                        Klik ikon <i class="fas fa-filter text-muted mx-1"></i> di sebelah nama kolom untuk memfilter baris data layaknya Microsoft Excel. Tabel di bawah akan bereaksi secara realtime sesuai filtermu.
                    </div>

                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
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

                                                @if(isset($formattedUniqueValues[$index]) && count($formattedUniqueValues[$index]) > 0)
                                                <!-- Penanda bahwa kolom ini memiliki UI Filter -->
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
                                                                <!-- Select All dicentang otomatis -->
                                                                <input class="custom-control-input select-all-cb" type="checkbox" id="select_all_{{ $index }}" data-col="{{ $index }}" checked>
                                                                <label for="select_all_{{ $index }}" class="custom-control-label font-weight-bold text-dark">(Select All)</label>
                                                            </div>
                                                        </div>
                                                        <div class="p-2 bg-white" id="list_container_{{ $index }}" style="max-height: 250px; overflow-y: auto;">
                                                            @foreach($formattedUniqueValues[$index] as $val)
                                                                <div class="custom-control custom-checkbox filter-item-container mb-1">
                                                                    <!-- Diperbaiki: Hapus htmlspecialchars ganda karena Blade sudah mengatasinya -->
                                                                    <input class="custom-control-input filter-checkbox" type="checkbox" 
                                                                           id="filter_{{ $index }}_{{ $loop->index }}" 
                                                                           name="filters[{{ $index }}][]" 
                                                                           value="{{ trim($val) }}" 
                                                                           data-col="{{ $index }}" checked>
                                                                    <label for="filter_{{ $index }}_{{ $loop->index }}" class="custom-control-label font-weight-normal filter-label">
                                                                        {{ trim($val) === '' ? '(Blank)' : trim($val) }}
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
                                    <!-- Tambahkan class preview-row untuk target JavaScript -->
                                    <tr class="preview-row">
                                        <td class="text-center text-muted">{{ $rowIndex + 1 }}</td>
                                        @foreach($headers as $colIndex => $header)
                                            <!-- Diperbaiki: Hapus htmlspecialchars ganda agar identik dengan filter dropdown -->
                                            <td class="text-truncate col-data-{{ $colIndex }}" 
                                                data-val="{{ trim($row[$colIndex] ?? '') }}"
                                                style="max-width: 250px;" 
                                                title="{{ $row[$colIndex] ?? '' }}">
                                                {{ isset($row[$colIndex]) ? $row[$colIndex] : '-' }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
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
<script>
    document.addEventListener('DOMContentLoaded', function () {
        
        // 1. Mencegah Dropdown tertutup saat diklik di dalamnya
        const dropdownMenus = document.querySelectorAll('.dropdown-menu');
        dropdownMenus.forEach(menu => {
            menu.addEventListener('click', function (e) { e.stopPropagation(); });
        });

        // 2. Fungsi Update Tabel Preview secara REAL-TIME (Diperbaiki performanya)
        function updatePreviewTable() {
            let activeFilters = {};
            
            // Kumpulkan hanya value dari checkbox yang aktif
            document.querySelectorAll('.filter-checkbox').forEach(cb => {
                let col = cb.getAttribute('data-col');
                if (!activeFilters[col]) activeFilters[col] = [];
                if (cb.checked) {
                    activeFilters[col].push(cb.value.trim()); // Tambahkan trim() untuk keamanan
                }
            });

            // Evaluasi baris per baris
            document.querySelectorAll('.preview-row').forEach(row => {
                let pass = true;
                
                for (let col in activeFilters) {
                    let cell = row.querySelector('.col-data-' + col);
                    if (cell) {
                        let cellVal = (cell.getAttribute('data-val') || '').trim();
                        
                        // Jika tidak ada filter yang dicentang di kolom ini, langsung sembunyikan baris
                        if (activeFilters[col].length === 0) {
                            pass = false;
                            break;
                        }
                        
                        // Cek apakah nilai cell (baris) ada di dalam array filter yang dicentang
                        if (!activeFilters[col].includes(cellVal)) {
                            pass = false;
                            break;
                        }
                    }
                }
                
                row.style.display = pass ? '' : 'none';
            });
        }

        // 3. Tambahkan event listener ke setiap checkbox
        const filterCheckboxes = document.querySelectorAll('.filter-checkbox');
        filterCheckboxes.forEach(cb => {
            cb.addEventListener('change', function () {
                const colIndex = this.getAttribute('data-col');
                const container = document.getElementById('list_container_' + colIndex);
                const allChecked = container.querySelectorAll('.filter-checkbox:checked');
                const totalCbs = container.querySelectorAll('.filter-checkbox');
                const icon = document.getElementById('icon_filter_' + colIndex);
                const selectAll = document.getElementById('select_all_' + colIndex);

                // Update warna ikon dan properti Select All (Tanpa Trigger Event!)
                if (allChecked.length < totalCbs.length) {
                    icon.classList.remove('text-muted');
                    icon.classList.add('text-primary');
                    selectAll.checked = false; 
                } else {
                    icon.classList.remove('text-primary');
                    icon.classList.add('text-muted');
                    selectAll.checked = true;
                }

                updatePreviewTable(); // Panggil fungsi realtime
            });
        });

        // 4. Select All Logika (Diperbaiki agar tidak freeze browser)
        const selectAllCbs = document.querySelectorAll('.select-all-cb');
        selectAllCbs.forEach(cb => {
            cb.addEventListener('change', function () {
                const isChecked = this.checked;
                const colIndex = this.getAttribute('data-col');
                const container = document.getElementById('list_container_' + colIndex);
                const icon = document.getElementById('icon_filter_' + colIndex);
                
                const visibleCheckboxes = container.querySelectorAll('.filter-item-container[style*="block"] .filter-checkbox, .filter-item-container:not([style*="none"]) .filter-checkbox');
                
                // Ubah properti checked saja, jangan dispatch event agar tidak freeze
                visibleCheckboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                });

                // Cek ulang ikon setelah di-centang semua
                const allChecked = container.querySelectorAll('.filter-checkbox:checked');
                const totalCbs = container.querySelectorAll('.filter-checkbox');
                if (allChecked.length < totalCbs.length) {
                    icon.classList.remove('text-muted');
                    icon.classList.add('text-primary');
                } else {
                    icon.classList.remove('text-primary');
                    icon.classList.add('text-muted');
                }

                // Panggil proses render tabel hanya 1 kali setelah perulangan selesai
                updatePreviewTable();
            });
        });

        // 5. Search List Filter
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
    });
</script>
@endsection