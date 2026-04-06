Template download import disimpan sebagai file fisik di folder ini.

Flow:
- User memilih template di halaman import.
- Request masuk ke `ImportIndexController::downloadTemplate()`.
- Controller mengambil file `.xlsx` dari `resources/templates/import/`.
- Laravel mengirim file tersebut langsung ke browser sebagai download.

File yang dipakai saat ini:
- `template-input-rekanan.xlsx`
- `template-nasabah-prioritas-bod-boc.xlsx`
