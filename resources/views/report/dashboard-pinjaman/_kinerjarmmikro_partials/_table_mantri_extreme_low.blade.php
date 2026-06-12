@php
    $mantriExtremeDetailUrl = route('report.dashboard-pinjaman.kinerjarmmikro.mantri-extreme-low-detail', [], false);
    $mantriExtremeDetailPeriod = $selectedPeriod ?? request('periode');
@endphp

@once
    <style>
        .mantri-extreme-detail-row {
            cursor: zoom-in;
        }

        .mantri-extreme-detail-row:hover td:nth-child(3) {
            color: #0857c3;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .mantri-extreme-modal .modal-dialog {
            max-width: min(1180px, calc(100vw - 2rem));
        }

        .mantri-extreme-modal .modal-content {
            border: 0;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(15, 23, 42, .24);
        }

        .mantri-extreme-modal .modal-header {
            background: linear-gradient(135deg, #075aa9, #174e92);
            color: #fff;
            border-bottom: 0;
        }

        .mantri-extreme-modal .close {
            color: #fff;
            opacity: .9;
            text-shadow: none;
        }

        .mantri-extreme-detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(245px, 1fr));
            gap: .85rem;
        }

        .mantri-extreme-detail-card {
            border: 1px solid rgba(15, 23, 42, .09);
            border-radius: 8px;
            background: #fff;
            overflow: hidden;
        }

        .mantri-extreme-detail-card__head {
            display: flex;
            justify-content: space-between;
            gap: .75rem;
            padding: .7rem .85rem;
            background: #f8fafc;
            border-bottom: 1px solid rgba(15, 23, 42, .08);
            color: #0f172a;
            font-size: .8rem;
            font-weight: 900;
        }

        .mantri-extreme-detail-card__body {
            max-height: 260px;
            overflow: auto;
        }

        .mantri-extreme-detail-card table {
            width: 100%;
            border-collapse: collapse;
            font-size: .76rem;
        }

        .mantri-extreme-detail-card td {
            padding: .48rem .65rem;
            border-bottom: 1px solid rgba(15, 23, 42, .06);
            vertical-align: top;
        }

        .mantri-extreme-detail-card .amount {
            color: #065f46;
            font-weight: 800;
            text-align: right;
            white-space: nowrap;
        }

        .mantri-extreme-detail-empty,
        .mantri-extreme-detail-loading {
            padding: 1.2rem;
            color: #64748b;
            text-align: center;
            font-weight: 700;
        }
    </style>
@endonce

<div class="rm-mikro-table-wrap">
    <table class="table table-sm rm-mikro-table mantri-monitoring-table">
        <thead>
            <tr>
                <th class="head-base" rowspan="3">No</th>
                <th class="head-base" rowspan="3">Branch Office</th>
                <th class="head-base" rowspan="3">Nama Uker</th>
                <th class="head-base" rowspan="3">Total Mantri</th>
                <th class="head-extreme" colspan="6">Extreme Low</th>
                <th class="head-low" colspan="4">Low</th>
                <th class="head-under" colspan="2">Total Under 800 Juta</th>
                <th class="head-mid" colspan="4">Mid</th>
                <th class="head-high" colspan="2">High</th>
            </tr>
            <tr>
                @foreach (['el_0_100', 'el_100_200', 'el_200_400'] as $bucketKey)
                    <th class="head-extreme" colspan="2">{{ $total['buckets'][$bucketKey]['label'] ?? '-' }}</th>
                @endforeach
                @foreach (['low_400_600', 'low_600_800'] as $bucketKey)
                    <th class="head-low" colspan="2">{{ $total['buckets'][$bucketKey]['label'] ?? '-' }}</th>
                @endforeach
                <th class="head-under" colspan="2">{{ $total['under_800']['label'] ?? 'Total Under 800 Juta' }}</th>
                @foreach (['mid_800_1000', 'mid_1000_1200'] as $bucketKey)
                    <th class="head-mid" colspan="2">{{ $total['buckets'][$bucketKey]['label'] ?? '-' }}</th>
                @endforeach
                <th class="head-high" colspan="2">{{ $total['buckets']['high_1200']['label'] ?? '-' }}</th>
            </tr>
            <tr>
                @for ($i = 0; $i < 9; $i++)
                    <th>Org</th>
                    <th>%</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="mantri-extreme-detail-row"
                    data-mantri-extreme-detail="1"
                    data-branch="{{ $row['branch_office'] ?? '' }}"
                    data-unit="{{ $row['nama_uker'] ?? '' }}"
                    title="Klik 2 kali untuk melihat rincian Mantri per bucket">
                    <td class="text-center">{{ $row['no'] ?? $loop->iteration }}</td>
                    <td class="strong">{{ $row['branch_office'] ?? '-' }}</td>
                    <td>{{ $row['nama_uker'] ?? '-' }}</td>
                    <td class="text-right strong">{{ $formatAmount($row['total_mantri'] ?? 0) }}</td>

                    @foreach (['el_0_100', 'el_100_200', 'el_200_400'] as $bucketKey)
                        <td class="text-right cell-extreme">{{ $formatAmount($row['buckets'][$bucketKey]['deb'] ?? 0) }}</td>
                        <td class="text-right cell-extreme">{{ $formatPercent($row['buckets'][$bucketKey]['pct'] ?? 0, 2) }}</td>
                    @endforeach
                    @foreach (['low_400_600', 'low_600_800'] as $bucketKey)
                        <td class="text-right cell-low">{{ $formatAmount($row['buckets'][$bucketKey]['deb'] ?? 0) }}</td>
                        <td class="text-right cell-low">{{ $formatPercent($row['buckets'][$bucketKey]['pct'] ?? 0, 2) }}</td>
                    @endforeach
                    <td class="text-right cell-under">{{ $formatAmount($row['under_800']['deb'] ?? 0) }}</td>
                    <td class="text-right cell-under">{{ $formatPercent($row['under_800']['pct'] ?? 0, 2) }}</td>
                    @foreach (['mid_800_1000', 'mid_1000_1200'] as $bucketKey)
                        <td class="text-right cell-mid">{{ $formatAmount($row['buckets'][$bucketKey]['deb'] ?? 0) }}</td>
                        <td class="text-right cell-mid">{{ $formatPercent($row['buckets'][$bucketKey]['pct'] ?? 0, 2) }}</td>
                    @endforeach
                    <td class="text-right cell-high">{{ $formatAmount($row['buckets']['high_1200']['deb'] ?? 0) }}</td>
                    <td class="text-right cell-high">{{ $formatPercent($row['buckets']['high_1200']['pct'] ?? 0, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="22">
                        <div class="rm-mikro-empty">
                            Data Extreme Low Mantri belum tersedia untuk periode ini.
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="rm-mikro-total">
                <td class="text-center" colspan="3">AREA 6</td>
                <td class="text-right">{{ $formatAmount($total['total_mantri'] ?? 0) }}</td>

                @foreach (['el_0_100', 'el_100_200', 'el_200_400'] as $bucketKey)
                    <td class="text-right cell-extreme">{{ $formatAmount($total['buckets'][$bucketKey]['deb'] ?? 0) }}</td>
                    <td class="text-right cell-extreme">{{ $formatPercent($total['buckets'][$bucketKey]['pct'] ?? 0, 2) }}</td>
                @endforeach
                @foreach (['low_400_600', 'low_600_800'] as $bucketKey)
                    <td class="text-right cell-low">{{ $formatAmount($total['buckets'][$bucketKey]['deb'] ?? 0) }}</td>
                    <td class="text-right cell-low">{{ $formatPercent($total['buckets'][$bucketKey]['pct'] ?? 0, 2) }}</td>
                @endforeach
                <td class="text-right cell-under">{{ $formatAmount($total['under_800']['deb'] ?? 0) }}</td>
                <td class="text-right cell-under">{{ $formatPercent($total['under_800']['pct'] ?? 0, 2) }}</td>
                @foreach (['mid_800_1000', 'mid_1000_1200'] as $bucketKey)
                    <td class="text-right cell-mid">{{ $formatAmount($total['buckets'][$bucketKey]['deb'] ?? 0) }}</td>
                    <td class="text-right cell-mid">{{ $formatPercent($total['buckets'][$bucketKey]['pct'] ?? 0, 2) }}</td>
                @endforeach
                <td class="text-right cell-high">{{ $formatAmount($total['buckets']['high_1200']['deb'] ?? 0) }}</td>
                <td class="text-right cell-high">{{ $formatPercent($total['buckets']['high_1200']['pct'] ?? 0, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

@once
    <div class="modal fade mantri-extreme-modal" id="mantriExtremeDetailModal" tabindex="-1" role="dialog" aria-labelledby="mantriExtremeDetailTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <div class="small font-weight-bold text-uppercase" style="opacity:.76;">Rincian Extreme Low Mantri</div>
                        <h5 class="modal-title mb-0" id="mantriExtremeDetailTitle">Detail Unit Kerja</h5>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="mantriExtremeDetailBody" class="mantri-extreme-detail-loading">Memuat rincian Mantri...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const endpoint = @json($mantriExtremeDetailUrl);
            const period = @json($mantriExtremeDetailPeriod);
            const modal = document.getElementById('mantriExtremeDetailModal');
            const title = document.getElementById('mantriExtremeDetailTitle');
            const body = document.getElementById('mantriExtremeDetailBody');
            let activeRequest = null;
            let requestSequence = 0;
            const bucketOrder = [
                'el_0_100',
                'el_100_200',
                'el_200_400',
                'low_400_600',
                'low_600_800',
                'under_800',
                'mid_800_1000',
                'mid_1000_1200',
                'high_1200',
            ];

            if (!modal || !body || !endpoint || !period) {
                return;
            }

            const escapeHtml = function (value) {
                return String(value ?? '').replace(/[&<>"']/g, function (char) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    }[char];
                });
            };

            const formatPercent = function (value) {
                return Number(value || 0).toLocaleString('id-ID', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }) + '%';
            };

            const formatJuta = function (value) {
                return Number(value || 0).toLocaleString('id-ID', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 1
                });
            };

            const showModal = function () {
                if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
                    const $modal = window.jQuery(modal);
                    if (!$modal.hasClass('show')) {
                        $modal.modal('show');
                    }
                    return;
                }

                modal.classList.add('show');
                modal.style.display = 'block';
                modal.removeAttribute('aria-hidden');
            };

            const renderBucket = function (bucket) {
                const rows = Array.isArray(bucket.rows) ? bucket.rows : [];
                const rowHtml = rows.length
                    ? rows.map(function (row) {
                        return `
                            <tr>
                                <td>
                                    <strong>${escapeHtml(row.nama_mantri || '-')}</strong>
                                    <div class="text-muted">${escapeHtml(row.pn_mantri || row.mantri_key || '-')}</div>
                                </td>
                                <td class="amount">${formatJuta(row.realisasi_juta)} Jt</td>
                            </tr>
                        `;
                    }).join('')
                    : '<tr><td colspan="2" class="mantri-extreme-detail-empty">Tidak ada Mantri pada bucket ini.</td></tr>';

                return `
                    <section class="mantri-extreme-detail-card">
                        <div class="mantri-extreme-detail-card__head">
                            <span>${escapeHtml(bucket.label || '-')}</span>
                            <span>${Number(bucket.deb || 0).toLocaleString('id-ID')} Org | ${formatPercent(bucket.pct)}</span>
                        </div>
                        <div class="mantri-extreme-detail-card__body">
                            <table>
                                <tbody>${rowHtml}</tbody>
                            </table>
                        </div>
                    </section>
                `;
            };

            const renderDetail = function (payload) {
                const buckets = Object.assign({}, payload.buckets || {}, {
                    under_800: payload.under_800 || null
                });

                body.innerHTML = `
                    <div class="mb-3">
                        <div class="font-weight-bold">${escapeHtml(payload.branch_office)} - ${escapeHtml(payload.nama_uker)}</div>
                        <div class="text-muted">Total Mantri: ${Number(payload.total_mantri || 0).toLocaleString('id-ID')} Org</div>
                    </div>
                    <div class="mantri-extreme-detail-grid">
                        ${bucketOrder.map(function (key) {
                            return buckets[key] ? renderBucket(buckets[key]) : '';
                        }).join('')}
                    </div>
                `;
            };

            document.querySelectorAll('[data-mantri-extreme-detail="1"]').forEach(function (row) {
                row.addEventListener('dblclick', function () {
                    if (activeRequest) {
                        activeRequest.abort();
                    }
                    activeRequest = new AbortController();
                    const currentRequest = ++requestSequence;
                    const branch = row.getAttribute('data-branch') || '';
                    const unit = row.getAttribute('data-unit') || '';
                    const url = new URL(endpoint, window.location.origin);
                    url.searchParams.set('periode', period);
                    url.searchParams.set('branch', branch);
                    url.searchParams.set('unit', unit);

                    if (title) {
                        title.textContent = `${branch} - ${unit}`;
                    }
                    body.className = 'mantri-extreme-detail-loading';
                    body.textContent = 'Memuat rincian Mantri...';
                    showModal();

                    fetch(url.toString(), {
                        signal: activeRequest.signal,
                        headers: {
                            'Accept': 'application/json'
                        }
                    })
                        .then(function (response) {
                            if (!response.ok) {
                                throw new Error('HTTP ' + response.status);
                            }
                            return response.json();
                        })
                        .then(function (payload) {
                            if (currentRequest !== requestSequence) {
                                return;
                            }
                            body.className = '';
                            renderDetail(payload);
                        })
                        .catch(function (error) {
                            if (error && error.name === 'AbortError') {
                                return;
                            }
                            if (currentRequest !== requestSequence) {
                                return;
                            }
                            body.className = 'mantri-extreme-detail-empty';
                            body.textContent = 'Rincian Mantri gagal dimuat.';
                        });
                });
            });

            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
                window.jQuery(modal).on('hidden.bs.modal', function () {
                    if (activeRequest) {
                        activeRequest.abort();
                        activeRequest = null;
                    }
                    requestSequence++;
                    body.className = 'mantri-extreme-detail-loading';
                    body.textContent = 'Memuat rincian Mantri...';
                });
            }
        });
    </script>
@endonce
