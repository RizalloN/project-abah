<div class="row">
    <div class="col-12">
        <div id="previewCard" class="card mt-4 input-preview-card d-none">
            <div class="card-body p-4 p-lg-4 preview-shell">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between mb-4">
                    <div>
                        <span class="preview-badge mb-3">
                            <i class="fas fa-sparkles"></i>
                            Review Sebelum Simpan
                        </span>
                        <h5 class="card-title mb-2 text-dark">
                            <i class="fas fa-table text-primary mr-2"></i>Preview Data
                        </h5>
                        <p class="mb-0 text-muted">Tabel baru muncul setelah tombol <strong>Preview</strong> diklik dan semua sel tetap bisa diedit.</p>
                    </div>
                    <div class="mt-3 mt-lg-0">
                        <button type="button" id="btnAddEmptyRow" class="btn btn-light mr-2">
                            <i class="fas fa-plus mr-1"></i>Tambah Baris
                        </button>
                    </div>
                </div>

                <div id="previewTableSection">
                    <div class="preview-toolbar d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between px-4 py-3 mb-3">
                        <div>
                            <div class="font-weight-bold">Tabel Preview Editable</div>
                            <div style="font-size: 0.85rem; color: rgba(226, 232, 240, 0.78);">Perbaiki isi tabel langsung di sini sebelum data masuk ke database.</div>
                        </div>
                        <div class="mt-3 mt-lg-0" style="font-size: 0.82rem; color: rgba(226, 232, 240, 0.78);">
                            Kolom produk bisa langsung diisi pada tahap preview.
                        </div>
                    </div>

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
                                    <th class="col-action">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="previewTableBody"></tbody>
                        </table>
                    </div>

                    <form id="saveInputDataForm" method="POST" action="{{ route('input.store') }}" class="mt-4">
                        @csrf
                        <input type="hidden" name="rows_payload" id="rowsPayload">
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-md-between">
                            <p class="text-muted mb-3 mb-md-0" style="font-size: 0.9rem;">
                                Pastikan data pada tabel sudah benar. Baris kosong tidak akan ikut disimpan.
                            </p>
                            <button type="submit" id="btnSaveData" class="btn btn-success">
                                <i class="fas fa-save mr-1"></i>Simpan Data
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
