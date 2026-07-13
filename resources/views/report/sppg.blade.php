@extends('layouts.admin')

@section('title', 'SPPG')

@section('content')
<style>
    .sppg-page {
        padding: 1.25rem;
    }

    .sppg-hero,
    .sppg-card {
        border: 1px solid #dbe7f3;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 18px 42px -30px rgba(15, 23, 42, 0.35);
    }

    .sppg-hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.25rem 1.35rem;
        margin-bottom: 1rem;
        background:
            linear-gradient(135deg, rgba(0, 82, 156, 0.08), rgba(25, 183, 232, 0.04)),
            #ffffff;
    }

    .sppg-eyebrow {
        margin: 0 0 0.4rem;
        color: #0f5fb8;
        font-size: 0.74rem;
        font-weight: 850;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    .sppg-title {
        margin: 0;
        color: #0f172a;
        font-size: 1.45rem;
        font-weight: 850;
        letter-spacing: 0;
    }

    .sppg-subtitle {
        margin: 0.35rem 0 0;
        color: #64748b;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .sppg-stat {
        min-width: 150px;
        border: 1px solid #cfe1f5;
        border-radius: 16px;
        padding: 0.85rem 1rem;
        background: linear-gradient(180deg, #f8fbff, #ffffff);
        text-align: right;
    }

    .sppg-stat span {
        display: block;
        color: #64748b;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .sppg-stat strong {
        display: block;
        color: #0f5fb8;
        font-size: 1.6rem;
        font-weight: 900;
        line-height: 1.05;
    }

    .sppg-toolbar {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) minmax(220px, 320px);
        gap: 0.9rem;
        padding: 1rem;
        border-bottom: 1px solid #e6eef7;
        background: #fbfdff;
    }

    .sppg-control-label {
        display: block;
        margin-bottom: 0.35rem;
        color: #516b91;
        font-size: 0.72rem;
        font-weight: 850;
        letter-spacing: 0.07em;
        text-transform: uppercase;
    }

    .sppg-control {
        width: 100%;
        min-height: 42px;
        border: 1px solid #cbd8e8;
        border-radius: 13px;
        background: linear-gradient(180deg, #eaf2ff 0%, #ffffff 78%);
        color: #0f172a;
        font-weight: 700;
        padding: 0.65rem 0.8rem;
        outline: none;
    }

    .sppg-control:focus {
        border-color: #0f5fb8;
        box-shadow: 0 0 0 0.18rem rgba(15, 95, 184, 0.12);
        background: #ffffff;
    }

    .sppg-table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .sppg-table {
        width: 100%;
        min-width: 960px;
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .sppg-table th {
        padding: 0.82rem 0.8rem;
        color: #ffffff;
        background: #0f5fb8;
        border-right: 1px solid rgba(255, 255, 255, 0.16);
        font-size: 0.76rem;
        font-weight: 850;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .sppg-table th:first-child {
        border-top-left-radius: 14px;
    }

    .sppg-table th:last-child {
        border-top-right-radius: 14px;
        border-right: none;
    }

    .sppg-table td {
        padding: 0.78rem 0.8rem;
        border-bottom: 1px solid #e6eef7;
        color: #334155;
        font-size: 0.9rem;
        vertical-align: top;
        background: #ffffff;
    }

    .sppg-table tbody tr:nth-child(even) td {
        background: #f9fcff;
    }

    .sppg-table tbody tr:hover td {
        background: #eef6ff;
    }

    .sppg-no {
        width: 64px;
        color: #64748b;
        font-weight: 850;
        text-align: center;
    }

    .sppg-branch {
        display: inline-flex;
        align-items: center;
        gap: 0.42rem;
        border-radius: 999px;
        padding: 0.28rem 0.62rem;
        background: #eaf3ff;
        color: #075aaf;
        font-size: 0.78rem;
        font-weight: 850;
        white-space: nowrap;
    }

    .sppg-empty,
    .sppg-alert {
        margin: 1rem;
        border-radius: 16px;
        padding: 1rem 1.1rem;
        font-weight: 700;
    }

    .sppg-empty {
        border: 1px dashed #bfd4ee;
        background: #f8fbff;
        color: #64748b;
        text-align: center;
    }

    .sppg-alert {
        border: 1px solid #fed7aa;
        background: #fff7ed;
        color: #9a3412;
    }

    @media (max-width: 900px) {
        .sppg-page {
            padding: 1rem;
        }

        .sppg-hero {
            align-items: flex-start;
            flex-direction: column;
        }

        .sppg-stat {
            width: 100%;
            text-align: left;
        }

        .sppg-toolbar {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="sppg-page">
    <section class="sppg-hero">
        <div>
            <p class="sppg-eyebrow">Business Cluster</p>
            <h1 class="sppg-title">SPPG</h1>
            <p class="sppg-subtitle">Sheet {{ $link['sheet_name'] ?? 'Area 6' }} · update {{ $lastFetchedAt->format('d M Y H:i') }}</p>
        </div>
        <div class="sppg-stat">
            <span>Total Data</span>
            <strong id="sppgVisibleCount">{{ number_format($totalRows, 0, ',', '.') }}</strong>
        </div>
    </section>

    @if($errors->isNotEmpty())
        <div class="sppg-alert">
            @foreach($errors as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <section class="sppg-card">
        <div class="sppg-toolbar">
            <div>
                <label for="sppgSearch" class="sppg-control-label">Pencarian</label>
                <input type="search" id="sppgSearch" class="sppg-control" placeholder="Cari yayasan, kepala SPPG, atau PIC...">
            </div>
            <div>
                <label for="sppgBranchFilter" class="sppg-control-label">Branch Office</label>
                <select id="sppgBranchFilter" class="sppg-control">
                    <option value="">Semua Branch Office</option>
                    @foreach($branchOptions as $branch)
                        <option value="{{ $branch }}">{{ $branch }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if($rows->isEmpty())
            <div class="sppg-empty">
                Belum ada data SPPG yang bisa ditampilkan.
            </div>
        @else
            <div class="sppg-table-wrap">
                <table class="sppg-table" id="sppgTable">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Branch Office</th>
                            <th>Nama Yayasan</th>
                            <th>Nama Kepala SPPG</th>
                            <th>Nama PIC SPPG</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr
                                data-branch="{{ \Illuminate\Support\Str::lower($row['branch_office']) }}"
                                data-search="{{ \Illuminate\Support\Str::lower(collect($row)->implode(' ')) }}"
                            >
                                <td class="sppg-no">{{ $loop->iteration }}</td>
                                <td><span class="sppg-branch">{{ $row['branch_office'] ?: '-' }}</span></td>
                                <td>{{ $row['nama_yayasan'] ?: '-' }}</td>
                                <td>{{ $row['nama_kepala_sppg'] ?: '-' }}</td>
                                <td>{{ $row['nama_pic_sppg'] ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('sppgSearch');
    const branchSelect = document.getElementById('sppgBranchFilter');
    const visibleCount = document.getElementById('sppgVisibleCount');
    const rows = Array.from(document.querySelectorAll('#sppgTable tbody tr'));

    function normalize(value) {
        return String(value || '').trim().toLowerCase();
    }

    function applyFilters() {
        const query = normalize(searchInput?.value);
        const branch = normalize(branchSelect?.value);
        let count = 0;

        rows.forEach(function (row) {
            const matchesBranch = !branch || row.dataset.branch === branch;
            const matchesSearch = !query || (row.dataset.search || '').includes(query);
            const visible = matchesBranch && matchesSearch;

            row.style.display = visible ? '' : 'none';
            if (visible) {
                count += 1;
                const numberCell = row.querySelector('.sppg-no');
                if (numberCell) {
                    numberCell.textContent = count.toLocaleString('id-ID');
                }
            }
        });

        if (visibleCount) {
            visibleCount.textContent = count.toLocaleString('id-ID');
        }
    }

    searchInput?.addEventListener('input', applyFilters);
    branchSelect?.addEventListener('change', applyFilters);
});
</script>
@endsection
