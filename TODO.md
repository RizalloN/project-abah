# Fix Import Excel - CPU Only (pandas), No Chunking

## Steps

- [x] Analyze root cause (GPU/MLU exception not caught → Python crash → "Proses Terhenti")
- [x] Plan confirmed by user
- [x] Fix `scripts/excel_gpu_processor.py`:
  - [x] Set GPU env vars to empty at top of script (CUDA/MLU/ROCm/Ascend/HIP)
  - [x] Remove GPU detection block entirely (no more `import cudf`)
  - [x] Rewrite to use `pandas.read_excel()` CPU — satu proses penuh tanpa chunking
  - [x] Remove `pymysql` entirely — Python hanya output batch JSON ke stdout
  - [x] Batch size dikurangi 1000 → 200 baris per JSON line (aman untuk max_allowed_packet)
  - [x] Hapus `os.unlink(file_path)` — PHP yang handle cleanup file
- [x] Fix `app/Http/Controllers/Import/ImportExcelController.php`:
  - [x] `tryPythonGPU()`: pass `$gpuEnv` ke `proc_open()` (5th param) — cegah GPU libs crash
  - [x] `tryPythonGPU()`: tambah `$pythonProducedOutput` tracking — jika Python crash diam-diam, return `false` → PHP fallback
  - [x] `tryPythonGPU()` → `$insertBatch`: sub-batch 100 baris (bukan 1000) — fix `max_allowed_packet` error
  - [x] `tryPythonGPU()`: handle `batch` events (PHP insert ke DB), `done` events (finalize job + kirim `complete`)
  - [x] `processExcelStream()`: memory limit 512M → 2048M
  - [x] `processExcelStream()`: tambah `DB::disableQueryLog()` — cegah memory leak jutaan baris
  - [x] `processExcelStream()` → `$flushBatch`: batch size 1000 → 100 baris
  - [x] `processExcelStream()`: accumulation threshold 1000 → 500
  - [x] `processExcelChunk()`: memory limit 1024M → 2048M
  - [x] `processExcelChunk()`: tambah `DB::disableQueryLog()`
  - [x] `processExcelChunk()`: batch size 1000 → 100 baris
  - [x] PHP fallback chunk size: `max(500, ceil(totalDataRows / 4))` — maks 4 chunk

## Root Cause (Recurring "Proses Terhenti")
1. **GPU/MLU crash**: Library GPU ter-inject via `.pth`/`sitecustomize.py` → crash sebelum script jalan
   - Fix: GPU env vars di `proc_open` + di awal Python script
2. **pymysql tidak terinstall**: Python gagal koneksi DB
   - Fix: Hapus DB dari Python — Python hanya baca Excel & output JSON, PHP yang insert ke DB
3. **`max_allowed_packet` MySQL terlampaui**: INSERT 1000 baris × 50+ kolom = 5-10MB per query
   - Fix: Semua batch insert dikurangi ke 100 baris (aman untuk default MySQL 1-16MB)
4. **Memory leak dari query log**: Laravel menyimpan semua query di memori saat jutaan baris
   - Fix: `DB::disableQueryLog()` di awal setiap proses import

## Architecture (Final)
```
Browser → PHP SSE → Python (pandas baca Excel, output JSON) → PHP (insert DB 100 baris/batch) → Browser complete
                 ↓ (jika Python gagal)
              PHP ChunkReadFilter (maks 4 chunk, 100 baris/batch DB insert)
