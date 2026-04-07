@extends('layouts.admin')

@section('title', 'Preview Import Nasabah Prioritas BOD BOC')

@section('content')
<div class="row">
    <div class="col-12">
        <form id="bodBocImportForm" method="POST" action="{{ route('bod-boc.store') }}">
            @csrf
            <input type="hidden" name="rows_payload" id="rowsPayload">

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
                        <i class="fas fa-info-circle text-info"></i>
                        <strong>Petunjuk:</strong> Klik ikon <i class="fas fa-filter text-muted mx-1"></i> di sebelah nama kolom untuk memfilter baris data. Tabel akan bereaksi secara realtime dan menampilkan <strong>maksimal 100 baris teratas</strong> sebagai bahan evaluasi.
                    </div>

                    <div class="alert alert-secondary m-3 mb-0 border-0">
                        <i class="fas fa-file-import text-primary"></i>
                        Sumber file: <strong>{{ $sourceName }}</strong>
                    </div>

                    <div class="table-responsive" style="min-height: 450px; max-height: 600px; overflow-y: auto; overflow-x: auto;">
                        <table class="table table-bordered table-hover m-0">
                            <thead class="thead-light sticky-top" style="z-index: 2;">
                                <tr>
                                    <th class="text-center align-middle bg-light" style="width: 50px;">#</th>
                                    @php
                                        $headers = [
                                            'instansi' => 'INSTANSI',
                                            'bod_boc' => 'BOD/BOC',
                                            'nama_nasabah' => 'NAMA_NASABAH',
                                            'ket_nasabah' => 'KET_NASABAH',
                                            'cif' => 'CIF',
                                            'fasilitas_1' => 'FASILITAS_1',
                                            'fasilitas_2' => 'FASILITAS_2',
                                            'fasilitas_3' => 'FASILITAS_3',
                                        ];
                                        $uniqueValues = [];
                                        foreach ($headers as $field => $label) {
                                            $uniqueValues[$field] = collect($previewRows)
                                                ->map(fn ($row) => trim((string) ($row[$field] ?? '')))
                                                ->unique()
                                                ->values()
                                                ->all();
                                        }
                                    @endphp

                                    @foreach($headers as $field => $label)
                                        <th class="align-middle bg-light" style="min-width: 220px;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="font-weight-bold text-dark">{{ $label }}</div>

                                                @if(!empty($uniqueValues[$field]))
                                                    <div class="dropdown">
                                                        <button class="btn btn-xs btn-light border dropdown-toggle filter-btn" type="button" data-toggle="dropdown" aria-expanded="false" data-boundary="window">
                                                            <i class="fas fa-filter text-muted" id="icon_filter_{{ $field }}"></i>
                                                        </button>

                                                        <div class="dropdown-menu dropdown-menu-right shadow p-0" style="width: 280px; border-radius: 8px;">
                                                            <div class="p-2 bg-light border-bottom">
                                                                <div class="input-group input-group-sm">
                                                                    <div class="input-group-prepend">
                                                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                                    </div>
                                                                    <input type="text" class="form-control search-filter" data-col="{{ $field }}" placeholder="Search...">
                                                                </div>
                                                            </div>
                                                            <div class="p-2 border-bottom bg-white">
                                                                <div class="custom-control custom-checkbox">
                                                                    <input class="custom-control-input select-all-cb" type="checkbox" id="select_all_{{ $field }}" data-col="{{ $field }}" checked>
                                                                    <label for="select_all_{{ $field }}" class="custom-control-label font-weight-bold text-dark">(Select All)</label>
                                                                </div>
                                                            </div>
                                                            <div class="p-2 bg-white" id="list_container_{{ $field }}" style="max-height: 250px; overflow-y: auto;" data-col="{{ $field }}">
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
                                @foreach($previewRows as $rowIndex => $row)
                                    <tr class="preview-row d-none">
                                        <td class="text-center text-muted">{{ $rowIndex + 1 }}</td>
                                        @foreach($headers as $field => $label)
                                            @php
                                                $cellValue = trim((string) ($row[$field] ?? ''));
                                            @endphp
                                            <td class="text-truncate"
                                                data-field="{{ $field }}"
                                                data-val="{{ $cellValue }}"
                                                style="max-width: 220px;"
                                                title="{{ $cellValue }}">
                                                {{ $cellValue === '' ? '-' : $cellValue }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach

                                <tr id="empty-state-row" class="d-none">
                                    <td colspan="{{ count($headers) + 1 }}" class="text-center py-5 bg-white text-muted">
                                        <i class="fas fa-search-minus fa-3x mb-3 text-secondary"></i><br>
                                        <h5 class="font-weight-bold text-dark">Tidak ada kecocokan di baris preview</h5>
                                        <p class="mb-0">Ubah filter atau import sesuai hasil filter yang aktif.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer bg-light">
                    <a href="{{ route('import.index') }}" class="btn btn-secondary">
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
        const headers = @json(array_keys($headers));
        const previewRows = @json($previewRows);
        const filterOptionsMap = @json($uniqueValues);
        const filterState = {};
        const searchTerms = {};
        const filterRenderLimit = 200;

        document.querySelectorAll('.dropdown-menu').forEach(function (menu) {
            menu.addEventListener('click', function (e) { e.stopPropagation(); });
        });

        headers.forEach(function (field) {
            const values = Array.isArray(filterOptionsMap[field]) ? filterOptionsMap[field].map(function (value) {
                return String(value).trim();
            }) : [];

            filterState[field] = {
                allValues: values,
                selectedValues: new Set(values),
            };
            searchTerms[field] = '';
        });

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function getFilteredValues(field) {
            const state = filterState[field];
            if (!state) return [];

            const term = (searchTerms[field] || '').toLowerCase();
            if (!term) return state.allValues.slice();

            return state.allValues.filter(function (value) {
                return value.toLowerCase().includes(term);
            });
        }

        function syncSelectAllCheckbox(field, filteredValues) {
            const selectAll = document.getElementById('select_all_' + field);
            const state = filterState[field];

            if (!selectAll || !state) return;

            if (!filteredValues.length) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
                return;
            }

            let checkedCount = 0;
            filteredValues.forEach(function (value) {
                if (state.selectedValues.has(value)) checkedCount++;
            });

            selectAll.checked = checkedCount === filteredValues.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < filteredValues.length;
        }

        function renderFilterList(field) {
            const container = document.getElementById('list_container_' + field);
            const state = filterState[field];
            if (!container || !state) return;

            const filteredValues = getFilteredValues(field);
            const visibleValues = filteredValues.slice(0, filterRenderLimit);
            let html = '';

            if (!filteredValues.length) {
                html = '<div class="text-center text-muted py-2 small">Tidak ada opsi yang cocok.</div>';
            } else {
                if (filteredValues.length > filterRenderLimit) {
                    html += '<div class="small text-muted mb-2">Menampilkan ' + filterRenderLimit + ' dari ' + filteredValues.length + ' opsi.</div>';
                }

                visibleValues.forEach(function (value, index) {
                    const safeValue = escapeHtml(value);
                    const inputId = 'filter_' + field + '_' + index;
                    const labelValue = value === '' ? '(Blank)' : safeValue;
                    html += '<div class="custom-control custom-checkbox filter-item-container mb-1">';
                    html += '<input class="custom-control-input filter-checkbox" type="checkbox" id="' + inputId + '" value="' + safeValue + '" data-col="' + field + '"' + (state.selectedValues.has(value) ? ' checked' : '') + '>';
                    html += '<label for="' + inputId + '" class="custom-control-label font-weight-normal filter-label">' + labelValue + '</label>';
                    html += '</div>';
                });
            }

            container.innerHTML = html;
            syncSelectAllCheckbox(field, filteredValues);
        }

        function updateIconsColor() {
            headers.forEach(function (field) {
                const state = filterState[field];
                const icon = document.getElementById('icon_filter_' + field);
                if (!state || !icon) return;

                if (state.selectedValues.size < state.allValues.length && state.selectedValues.size > 0) {
                    icon.classList.remove('text-muted');
                    icon.classList.add('text-primary');
                } else {
                    icon.classList.remove('text-primary');
                    icon.classList.add('text-muted');
                }
            });
        }

        function rowMatchesFilters(row) {
            return headers.every(function (field) {
                const state = filterState[field];
                if (!state || state.selectedValues.size === state.allValues.length) {
                    return true;
                }

                return state.selectedValues.has(String(row[field] || '').trim());
            });
        }

        function updatePreviewTable() {
            let matchingCount = 0;
            const domRows = document.querySelectorAll('.preview-row');

            domRows.forEach(function (rowEl, index) {
                const row = previewRows[index] || {};
                if (rowMatchesFilters(row)) {
                    if (matchingCount < 100) {
                        rowEl.classList.remove('d-none');
                    } else {
                        rowEl.classList.add('d-none');
                    }
                    matchingCount++;
                    return;
                }

                rowEl.classList.add('d-none');
            });

            const emptyRow = document.getElementById('empty-state-row');
            if (emptyRow) {
                emptyRow.classList.toggle('d-none', matchingCount > 0);
            }

            updateIconsColor();
        }

        function collectFilteredRows() {
            return previewRows.filter(function (row) {
                return rowMatchesFilters(row);
            });
        }

        document.addEventListener('change', function (e) {
            if (!e.target.classList.contains('filter-checkbox')) return;

            const field = e.target.getAttribute('data-col');
            const state = filterState[field];
            if (!state) return;

            const value = e.target.value.trim();
            if (e.target.checked) {
                state.selectedValues.add(value);
            } else {
                state.selectedValues.delete(value);
            }

            syncSelectAllCheckbox(field, getFilteredValues(field));
            updatePreviewTable();
        });

        document.querySelectorAll('.select-all-cb').forEach(function (cb) {
            cb.addEventListener('change', function () {
                const isChecked = this.checked;
                const field = this.getAttribute('data-col');
                const state = filterState[field];
                if (!state) return;

                getFilteredValues(field).forEach(function (value) {
                    if (isChecked) {
                        state.selectedValues.add(value);
                    } else {
                        state.selectedValues.delete(value);
                    }
                });

                renderFilterList(field);
                updatePreviewTable();
            });
        });

        document.querySelectorAll('.search-filter').forEach(function (input) {
            input.addEventListener('keyup', function () {
                const field = this.getAttribute('data-col');
                searchTerms[field] = this.value || '';
                renderFilterList(field);
            });
        });

        document.querySelectorAll('.dropdown').forEach(function (dropdown) {
            dropdown.addEventListener('shown.bs.dropdown', function () {
                const container = dropdown.querySelector('[id^="list_container_"]');
                if (!container) return;
                renderFilterList(container.getAttribute('data-col'));
            });
        });

        document.getElementById('bodBocImportForm').addEventListener('submit', function (e) {
            const filteredRows = collectFilteredRows();

            if (!filteredRows.length) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Data Tidak Ada',
                    text: 'Tidak ada baris yang lolos filter untuk diimport ke database.',
                    confirmButtonText: 'OK'
                });
                return;
            }

            document.getElementById('rowsPayload').value = JSON.stringify(filteredRows);
        });

        headers.forEach(function (field) {
            renderFilterList(field);
        });

        updatePreviewTable();
    });
</script>
@endsection
