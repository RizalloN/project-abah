<div class="row">
    <div class="col-12">
        <div class="card mt-4 input-preview-card">
            <div class="card-header bg-white border-0 px-4 pt-4">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between">
                    <div>
                        <h5 class="card-title mb-2 text-dark">
                            <i class="fas fa-edit text-info mr-2"></i>Form Input Data
                        </h5>
                        <p class="mb-0 text-muted">Isi form berikut lalu klik preview untuk memunculkan data di tabel bawah.</p>
                    </div>
                    <span class="mt-3 mt-lg-0 px-3 py-2" style="border-radius: 999px; background: #ecfeff; color: #0f766e; font-size: 0.78rem; font-weight: 700;">
                        Preview Sebelum Simpan
                    </span>
                </div>
            </div>

            <form id="inputDataForm" autocomplete="off">
                <div class="card-body px-4 pb-4">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group mb-4">
                                <label for="perusahaan_anak" class="font-weight-bold text-dark">Perusahaan Anak</label>
                                <select id="perusahaan_anak" name="perusahaan_anak" class="form-control">
                                    <option value="" selected disabled>Pilih salah satu perusahaan anak</option>
                                    <option>BRI Life</option>
                                    <option>BRI Finance</option>
                                    <option>BRI Danareksa Sekuritas</option>
                                    <option>BRI Insurance (BRINS)</option>
                                    <option>Pegadaian</option>
                                    <option>PNM (Permodalan Nasional Madani)</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group mb-4">
                                <label for="status_nasabah" class="font-weight-bold text-dark">Status Nasabah</label>
                                <select id="status_nasabah" name="status_nasabah" class="form-control">
                                    <option value="" selected disabled>Pilih status nasabah</option>
                                    <option>Sudah Nasabah</option>
                                    <option>Belum Nasabah</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group mb-4">
                                <label for="rekanan_level_1" class="font-weight-bold text-dark">Rekanan - Level 1</label>
                                <input type="text" id="rekanan_level_1" name="rekanan_level_1" class="form-control" placeholder="Masukkan rekanan level 1">
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group mb-4">
                                <label for="rekanan_level_2" class="font-weight-bold text-dark">Rekanan - Level 2</label>
                                <input type="text" id="rekanan_level_2" name="rekanan_level_2" class="form-control" placeholder="Masukkan rekanan level 2">
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group mb-2">
                                <label for="cif" class="font-weight-bold text-dark">CIF</label>
                                <input type="text" id="cif" name="cif" class="form-control" placeholder="Masukkan CIF">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-white border-0 px-4 pb-4">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-md-between">
                        <p class="text-muted mb-3 mb-md-0" style="font-size: 0.9rem;">
                            Preview akan menambahkan data ke tabel. Kolom produk bisa diisi langsung di tabel preview.
                        </p>
                        <div class="d-flex">
                            <button type="reset" class="btn btn-light mr-2">
                                <i class="fas fa-undo mr-1"></i>Reset
                            </button>
                            <button type="button" id="btnPreviewData" class="btn btn-primary">
                                <i class="fas fa-search mr-1"></i>Preview
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
