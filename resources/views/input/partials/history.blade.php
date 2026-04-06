<div class="row">
    <div class="col-12">
        <div class="card mt-4 input-history-card">
            <div class="card-header bg-white border-0 px-4 pt-4">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between">
                    <div>
                        <h5 class="card-title mb-2 text-dark">
                            <i class="fas fa-history text-secondary mr-2"></i>Data Tersimpan Terbaru
                        </h5>
                        <p class="mb-0 text-muted">Menampilkan maksimal 10 data terakhir dari tabel <code>input_rekanan</code>.</p>
                    </div>
                </div>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="input-table-wrap">
                    <table class="table table-hover input-table mb-0">
                        <thead>
                            <tr>
                                <th class="col-no">No</th>
                                <th>Perusahaan Anak</th>
                                <th>Rekanan - Level 1</th>
                                <th>Rekanan - Level 2</th>
                                <th>Status Nasabah</th>
                                <th>CIF</th>
                                <th>Produk 1</th>
                                <th>Produk 2</th>
                                <th>Produk 3</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentInputs as $index => $item)
                                <tr>
                                    <td class="col-no">{{ $index + 1 }}</td>
                                    <td>{{ $item->perusahaan_anak }}</td>
                                    <td>{{ $item->rekanan_level_1 }}</td>
                                    <td>{{ $item->rekanan_level_2 }}</td>
                                    <td>{{ $item->status_nasabah }}</td>
                                    <td>{{ $item->cif }}</td>
                                    <td>{{ $item->produk_1 }}</td>
                                    <td>{{ $item->produk_2 }}</td>
                                    <td>{{ $item->produk_3 }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">Belum ada data tersimpan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
