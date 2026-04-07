@extends('layouts.admin')

@section('title', 'Report Management')

@section('content')
<div class="card shadow-sm border-0" id="report-management-card"
     data-fetch-url="{{ route('import.report-management.data') }}"
     data-delete-url="{{ route('import.report-management.delete') }}">
    <div class="card-header bg-white border-0">
        <span class="import-upload-card__eyebrow">Report Management</span>
        <h5 class="card-title font-weight-bold text-dark mb-1">
            <i class="fas fa-database text-danger mr-2"></i> Kelola Data Report
        </h5>
        <p class="text-muted mb-0">Filter data berdasarkan report lalu hapus per kombinasi periode dan kanca. Snapshot terkait akan ikut disinkronkan.</p>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-8 mb-2">
                <label class="font-weight-bold text-dark">Pilih Report</label>
                <select id="management-report-select" class="form-control select2">
                    <option value="">-- Pilih Report --</option>
                    @foreach($reports as $report)
                        <option value="{{ $report->id_report }}">{{ $report->nama_report }} ({{ $report->table_name }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 mb-2 d-flex align-items-end">
                <button type="button" id="btn-management-filter" class="btn btn-outline-primary btn-block">
                    <i class="fas fa-filter mr-1"></i> Filter
                </button>
            </div>
        </div>

        <div class="table-responsive mt-3">
            <table class="table table-sm table-bordered mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 25%;">Periode</th>
                        <th style="width: 35%;">Kanca</th>
                        <th style="width: 20%;" class="text-right">Jumlah Baris</th>
                        <th style="width: 20%;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="management-table-body">
                    <tr>
                        <td colspan="4" class="text-center text-muted">Pilih report lalu klik Filter.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const reportManagementCard = document.getElementById('report-management-card');
        const managementReportSelect = document.getElementById('management-report-select');
        const btnManagementFilter = document.getElementById('btn-management-filter');
        const managementTableBody = document.getElementById('management-table-body');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || '{{ csrf_token() }}';

        function themedSwal(options) {
            return Swal.fire(Object.assign({
                customClass: {
                    popup: 'swal-modern-popup',
                    title: 'swal-modern-title',
                    htmlContainer: 'swal-modern-html',
                    confirmButton: 'swal-modern-confirm',
                },
                buttonsStyling: false,
                background: '#ffffff',
            }, options));
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function renderManagementRows(rows) {
            if (!managementTableBody) {
                return;
            }

            if (!Array.isArray(rows) || rows.length === 0) {
                managementTableBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center text-muted">Tidak ada data untuk kriteria ini.</td>
                    </tr>
                `;
                return;
            }

            managementTableBody.innerHTML = rows.map(function(row) {
                const period = row.period ?? '(Blank)';
                const kanca = row.kanca ?? '(Blank)';
                const total = Number(row.row_count || 0).toLocaleString('id-ID');
                const periodIsNull = row.period_is_null ? '1' : '0';
                const kancaIsNull = row.kanca_is_null ? '1' : '0';
                const periodEncoded = encodeURIComponent(String(period));
                const kancaEncoded = encodeURIComponent(String(kanca));

                return `
                    <tr>
                        <td>${escapeHtml(period)}</td>
                        <td>${escapeHtml(kanca)}</td>
                        <td class="text-right">${total}</td>
                        <td class="text-center">
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger btn-management-delete"
                                    data-period="${periodEncoded}"
                                    data-kanca="${kancaEncoded}"
                                    data-period-is-null="${periodIsNull}"
                                    data-kanca-is-null="${kancaIsNull}">
                                <i class="fas fa-trash-alt mr-1"></i> Delete
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        async function fetchManagementData() {
            if (!managementReportSelect || !managementReportSelect.value) {
                themedSwal({
                    icon: 'warning',
                    title: 'Pilih Report',
                    text: 'Silakan pilih report terlebih dahulu.'
                });
                return;
            }

            const fetchUrl = reportManagementCard?.dataset.fetchUrl;
            if (!fetchUrl) {
                return;
            }

            managementTableBody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center text-muted">Memuat data...</td>
                </tr>
            `;

            const response = await fetch(fetchUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    id_report: managementReportSelect.value
                })
            });

            const payload = await response.json();
            if (!response.ok || payload.status !== 'success') {
                throw new Error(payload.message || 'Gagal memuat data report management.');
            }

            renderManagementRows(payload.rows || []);
        }

        async function deleteManagedRow(button) {
            const deleteUrl = reportManagementCard?.dataset.deleteUrl;
            if (!deleteUrl || !managementReportSelect || !managementReportSelect.value) {
                return;
            }

            const period = decodeURIComponent(button.getAttribute('data-period') || '');
            const kanca = decodeURIComponent(button.getAttribute('data-kanca') || '');
            const periodIsNull = button.getAttribute('data-period-is-null') === '1';
            const kancaIsNull = button.getAttribute('data-kanca-is-null') === '1';

            const confirm = await themedSwal({
                icon: 'warning',
                title: 'Hapus Data?',
                html: `Data akan dihapus untuk <b>Periode:</b> ${escapeHtml(period)}<br><b>Kanca:</b> ${escapeHtml(kanca)}`,
                showCancelButton: true,
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            });

            if (!confirm.isConfirmed) {
                return;
            }

            const response = await fetch(deleteUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    id_report: managementReportSelect.value,
                    period: periodIsNull ? '' : period,
                    kanca: kancaIsNull ? '' : kanca,
                    period_is_null: periodIsNull,
                    kanca_is_null: kancaIsNull
                })
            });

            const payload = await response.json();
            if (!response.ok || (payload.status !== 'success' && payload.status !== 'warning')) {
                throw new Error(payload.message || 'Gagal menghapus data report.');
            }

            const isWarning = payload.status === 'warning';
            await themedSwal({
                icon: isWarning ? 'warning' : 'success',
                title: isWarning ? 'Selesai dengan Catatan' : 'Berhasil',
                text: isWarning
                    ? (payload.message || 'Data sumber terhapus tetapi sinkronisasi snapshot bermasalah.')
                    : `Data terhapus ${Number(payload.deleted_rows || 0).toLocaleString('id-ID')} baris.`
            });

            await fetchManagementData();
        }

        btnManagementFilter?.addEventListener('click', async function () {
            try {
                await fetchManagementData();
            } catch (error) {
                themedSwal({
                    icon: 'error',
                    title: 'Gagal Memuat Data',
                    text: error.message || 'Terjadi kesalahan saat memuat data.'
                });
            }
        });

        managementTableBody?.addEventListener('click', async function (event) {
            const button = event.target.closest('.btn-management-delete');
            if (!button) {
                return;
            }

            button.disabled = true;
            try {
                await deleteManagedRow(button);
            } catch (error) {
                themedSwal({
                    icon: 'error',
                    title: 'Delete Gagal',
                    text: error.message || 'Terjadi kesalahan saat menghapus data.'
                });
            } finally {
                button.disabled = false;
            }
        });
    });
</script>
@endsection
