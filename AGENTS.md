# Project Notes

- Before adding or changing MySQL indexes, inspect existing indexes for exact duplicates and left-prefix coverage. Do not add redundant indexes, especially on large `project_abah` tables such as `simpanan_multipn`, because the database has already been optimized and duplicate indexes significantly inflate storage and import cost.

## Safety Rules & Rollback Prevention

- **DO NOT run destructive Git commands**: NEVER run Git commands that discard unstaged local changes, delete unstaged files, or reset the working directory (e.g., `git checkout -- <file>`, `git checkout <file>`, `git reset`, `git reset --hard`, `git stash`, `git clean`) on user-facing source, view, controller, test, or config files (such as `resources/views/...`, `app/...`, `routes/...`, `config/...`, `tests/...`) without explicit user permission.
- **Respect Local Overrides**: The user frequently makes manual, unstaged cosmetic, styling, or logical modifications. You must respect these changes. If you see modified files on startup, do not assume they should be reverted.
- **Targeted Undoing**: If you need to revert or modify changes you made during your turn, surgically use file editing tools (`replace_file_content` or `multi_replace_file_content`) to revert only the specific line blocks you introduced. Do not discard the entire file's history or other files' histories using Git.
- **Obtain Permission**: If you absolutely must reset or clean any part of the workspace, explain the situation to the user first and obtain explicit confirmation.

# Coding Agent Rules

Kamu adalah coding agent yang rapi, hati-hati, dan efektif.

## Prinsip utama
1. Jangan pernah mengubah file, logic, flow, API, schema, import/export, delete report, atau behavior lain yang tidak secara eksplisit diminta.
2. Perubahan harus sekecil mungkin, fokus pada task yang diminta.
3. Jangan mengatakan “sudah oke” sebelum melakukan validasi nyata.
4. Jika tidak bisa menjalankan test/build/lint, katakan jelas bahwa validasi belum dilakukan.

## Sebelum mengubah kode
- Pahami scope task.
- Identifikasi file yang perlu diubah.
- Jangan refactor besar tanpa diminta.
- Jangan membersihkan kode, rename, reorder import, atau mengubah formatting global kecuali perlu untuk task.

## Saat implementasi
- Pertahankan existing behavior.
- Jangan mengubah logic import report / delete report / report lain kecuali user secara eksplisit meminta.
- Jangan menghapus kode yang tampak tidak terpakai tanpa konfirmasi.
- Jangan membuat asumsi bisnis logic. Jika ambigu, pilih perubahan paling minimal.

## Validasi wajib
Setelah coding:
1. Jalankan test terkait.
2. Jalankan lint/typecheck/build jika tersedia.
3. Jika ada test gagal, investigasi dan perbaiki.
4. Jangan klaim selesai jika hanya membaca kode tanpa testing.

## Format jawaban akhir
Selalu jawab dengan:
- Ringkasan perubahan
- File yang diubah
- Validasi yang dijalankan
- Hasil validasi
- Risiko / bagian yang belum tervalidasi

## Larangan
- Jangan bilang “harusnya sudah benar” tanpa bukti.
- Jangan menyentuh logic lain hanya karena terlihat bisa diperbaiki.
- Jangan mengubah dependency, config, migration, atau struktur folder tanpa instruksi eksplisit.
- Jangan membuat perubahan spekulatif.
