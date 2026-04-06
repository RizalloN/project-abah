<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('inputDataForm');
        const previewButton = document.getElementById('btnPreviewData');
        const addEmptyRowButton = document.getElementById('btnAddEmptyRow');
        const previewCard = document.getElementById('previewCard');
        const previewTableBody = document.getElementById('previewTableBody');
        const previewTableSection = document.getElementById('previewTableSection');
        const saveForm = document.getElementById('saveInputDataForm');
        const rowsPayload = document.getElementById('rowsPayload');

        const perusahaanOptions = [
            '',
            'BRI Life',
            'BRI Finance',
            'BRI Danareksa Sekuritas',
            'BRI Insurance (BRINS)',
            'Pegadaian',
            'PNM (Permodalan Nasional Madani)',
        ];

        const statusOptions = ['', 'Sudah Nasabah', 'Belum Nasabah'];

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function buildSelectOptions(options, selectedValue, placeholder) {
            return options.map(function (option, index) {
                const isSelected = option === selectedValue ? 'selected' : '';
                const label = index === 0 ? placeholder : option;
                return `<option value="${escapeHtml(option)}" ${isSelected}>${escapeHtml(label)}</option>`;
            }).join('');
        }

        function updateRowNumbers() {
            previewTableBody.querySelectorAll('tr').forEach(function (row, index) {
                const numberCell = row.querySelector('.row-number');
                if (numberCell) {
                    numberCell.textContent = index + 1;
                }
            });
        }

        function togglePreviewState() {
            const hasRows = previewTableBody.querySelectorAll('tr').length > 0;
            previewCard.classList.toggle('d-none', !hasRows);
            previewTableSection.classList.toggle('d-none', !hasRows);

            if (hasRows) {
                previewCard.classList.add('preview-appear');
            } else {
                previewCard.classList.remove('preview-appear');
            }
        }

        function appendRow(data = {}) {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="col-no row-number"></td>
                <td>
                    <select class="cell-select" data-field="perusahaan_anak">
                        ${buildSelectOptions(perusahaanOptions, data.perusahaan_anak || '', 'Pilih perusahaan anak')}
                    </select>
                </td>
                <td><input type="text" class="cell-input" data-field="rekanan_level_1" value="${escapeHtml(data.rekanan_level_1 || '')}" placeholder="Rekanan level 1"></td>
                <td><input type="text" class="cell-input" data-field="rekanan_level_2" value="${escapeHtml(data.rekanan_level_2 || '')}" placeholder="Rekanan level 2"></td>
                <td>
                    <select class="cell-select" data-field="status_nasabah">
                        ${buildSelectOptions(statusOptions, data.status_nasabah || '', 'Pilih status nasabah')}
                    </select>
                </td>
                <td><input type="text" class="cell-input" data-field="cif" value="${escapeHtml(data.cif || '')}" placeholder="CIF"></td>
                <td><input type="text" class="cell-input" data-field="produk_1" value="${escapeHtml(data.produk_1 || '')}" placeholder="Produk 1"></td>
                <td><input type="text" class="cell-input" data-field="produk_2" value="${escapeHtml(data.produk_2 || '')}" placeholder="Produk 2"></td>
                <td><input type="text" class="cell-input" data-field="produk_3" value="${escapeHtml(data.produk_3 || '')}" placeholder="Produk 3"></td>
                <td class="col-action">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;

            previewTableBody.appendChild(row);
            updateRowNumbers();
            togglePreviewState();
        }

        function collectFormData() {
            return {
                perusahaan_anak: document.getElementById('perusahaan_anak').value || '',
                rekanan_level_1: document.getElementById('rekanan_level_1').value.trim(),
                rekanan_level_2: document.getElementById('rekanan_level_2').value.trim(),
                status_nasabah: document.getElementById('status_nasabah').value || '',
                cif: document.getElementById('cif').value.trim(),
                produk_1: '',
                produk_2: '',
                produk_3: '',
            };
        }

        function hasAnyValue(row) {
            return Object.values(row).some(function (value) {
                return String(value || '').trim() !== '';
            });
        }

        function collectPreviewRows() {
            return Array.from(previewTableBody.querySelectorAll('tr')).map(function (row) {
                const result = {};
                row.querySelectorAll('[data-field]').forEach(function (field) {
                    result[field.dataset.field] = field.value.trim();
                });
                return result;
            });
        }

        previewButton.addEventListener('click', function () {
            const formData = collectFormData();

            if (!hasAnyValue(formData)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Form Masih Kosong',
                    text: 'Isi minimal satu field sebelum menambahkan preview.',
                    confirmButtonText: 'OK'
                });
                return;
            }

            appendRow(formData);
            form.reset();
            previewCard.scrollIntoView({ behavior: 'smooth', block: 'start' });

            Swal.fire({
                icon: 'success',
                title: 'Preview Ditambahkan',
                text: 'Data berhasil dimasukkan ke tabel preview dan masih bisa diedit.',
                timer: 1800,
                showConfirmButton: false
            });
        });

        addEmptyRowButton.addEventListener('click', function () {
            appendRow();
        });

        previewTableBody.addEventListener('click', function (event) {
            const removeButton = event.target.closest('.btn-remove-row');

            if (!removeButton) {
                return;
            }

            removeButton.closest('tr').remove();
            updateRowNumbers();
            togglePreviewState();
        });

        saveForm.addEventListener('submit', function (event) {
            const rows = collectPreviewRows().filter(hasAnyValue);

            if (rows.length === 0) {
                event.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Data Belum Ada',
                    text: 'Tambahkan minimal satu baris preview sebelum menyimpan.',
                    confirmButtonText: 'OK'
                });
                return;
            }

            rowsPayload.value = JSON.stringify(rows);
        });

        togglePreviewState();

        @if(session('sweet_success'))
        Swal.fire({
            icon: 'success',
            title: @json(session('sweet_success.title')),
            text: @json(session('sweet_success.text')),
            confirmButtonText: 'OK'
        });
        @endif

        @if(session('sweet_warning'))
        Swal.fire({
            icon: 'warning',
            title: @json(session('sweet_warning.title')),
            text: @json(session('sweet_warning.text')),
            confirmButtonText: 'OK'
        });
        @endif
    });
</script>
