# Status Sync Validation Testing Guide

## Overview

Dokumentasi ini menjelaskan cara menjalankan testing untuk memvalidasi perbaikan sinkronisasi status import antara Modal Preview (SSE) dan Job Management Dashboard.

## Perbaikan yang Diimplementasikan

### 1. **Fallback Message Heartbeat Gap** (ImportExecutionService.php:167)
- Fallback message di inline fallback kini tersinkronisasi ke cache
- Dashboard dan Modal Preview melihat pesan yang sama secara real-time

### 2. **Standarisasi Pesan Default** (ImportProgressService.php:1169-1182)
- Menambahkan `resolveDefaultMessageForStatus()` method
- Pesan default berbasis status database, bukan generik

### 3. **Preservasi Pesan Detail** (ImportProgressService.php:593)
- Cache message diprioritaskan terlebih dahulu
- Jika cache hilang, fallback ke database message
- Jika database juga kosong, gunakan status-based default message

---

## Quick Start: Automated Testing

### Option 1: Run PHPUnit Test Suite

```bash
# Run status sync validation tests
php artisan test tests/Feature/Import/StatusSyncValidationTest.php

# Run dengan verbose output
php artisan test tests/Feature/Import/StatusSyncValidationTest.php -v

# Run specific test
php artisan test tests/Feature/Import/StatusSyncValidationTest.php --filter test_inline_fallback_message_synced_to_cache
```

### Test Cases Included:
- ✅ `test_inline_fallback_message_synced_to_cache` - Skenario 1
- ✅ `test_message_fallback_to_database_after_cache_expiry` - Skenario 2
- ✅ `test_default_message_per_status` - Skenario 3
- ✅ `test_realtime_progress_message_consistency` - Skenario 4

---

## Advanced: Manual Testing via Tinker

Untuk testing interaktif dengan output visual, gunakan manual test script:

### Step 1: Launch Tinker

```bash
php artisan tinker
```

### Step 2: Load dan Run Testing Script

```php
include('tests/Manual/StatusSyncManualTestScript.php');

// Run semua skenario
$tester->runAllScenarios();

// atau jalankan skenario tertentu
$tester->runScenario1();  // Inline Fallback
$tester->runScenario2();  // Cache Expiry
$tester->runScenario3();  // Real-Time Progress
```

---

## Manual Testing: Production Scenario

Untuk testing di environment development dengan data real:

### Skenario 1: Inline Fallback (Worker Sibuk)

**Setup:**
1. Buka Dashboard Job Management: `http://localhost/admin/import-jobs`
2. Buka Modal Preview di tab/window terpisah: `http://localhost/admin/import-excel`

**Eksekusi:**
1. Simulasi worker sibuk:
   ```bash
   # Pause queue workers
   # Option A: Stop supervisor service
   # Option B: Disable in Dashboard → Import Settings
   ```

2. Upload file CSV besar (50MB+) via Modal Preview
3. Jalankan import

**Verifikasi:**
- [ ] Modal Preview menampilkan message: "Worker queue belum aktif. Fase Polars dijalankan langsung dari request ini."
- [ ] Dashboard Job Management menampilkan message yang SAMA
- [ ] Kedua interface update progress secara real-time
- [ ] Status berubah dari queued → processing → completed secara konsisten

---

### Skenario 2: Cache Expiry

**Setup:**
1. Siapkan completed import job

**Eksekusi:**
```php
php artisan tinker

// Ambil ID job yang sudah completed
$jobId = 1; // adjust sesuai job ID

// Refresh cache (simulasi cache expired)
Cache::forget('import_job_progress:' . $jobId);

// Check getStatusPayload
$payload = app(\App\Services\Import\ImportProgressService::class)->getStatusPayload($jobId);
dd($payload);
```

**Verifikasi:**
- [ ] `status` tetap sesuai database: "completed"
- [ ] `message` **bukan** generic "Import sedang diproses"
- [ ] `message` adalah pesan spesifik dari database atau default yang relevan dengan status
- [ ] Tidak ada warning/error di log

---

### Skenario 3: Real-Time Progress

**Setup:**
1. Siapkan file CSV medium (100K-500K rows)
2. Buka Developer Console di Browser: F12 → Network tab → filter "SSE"

**Eksekusi:**
1. Upload dan start import via Modal Preview
2. Monitor SSE messages di Developer Console

**Verifikasi:**
- [ ] SSE stream mengirim `progress` events dengan message yang berubah sesuai fase:
  - "Sanitasi CSV via Polars..."
  - "Memproses filter via Polars..."
  - "Loading data ke database..."
  - "Finalisasi dan reindex snapshot..."
- [ ] Pesan di Dashboard Job Management mencerminkan pesan yang sama
- [ ] Progress bar dan message update secara sinkron (delay < 1 detik)

---

## Debugging Checklist

Jika ada masalah, check:

### 1. Cache System
```php
// Check if cache is configured
php artisan config:show cache

// Test cache functionality
Cache::put('test', 'value', 10);
dd(Cache::get('test'));
```

### 2. Database Consistency
```php
// Check import_jobs table structure
php artisan migrate:status

// Verify job record
DB::table('import_jobs')->where('id', 1)->first();
```

### 3. Log Output
```bash
# Monitor real-time logs
tail -f storage/logs/laravel.log | grep -i import

# Or via artisan
php artisan logs:show import
```

### 4. SSE Stream Health
```php
// Check if streamStatus() is returning valid SSE format
curl -N http://localhost/api/import/status/stream/1
```

---

## Success Criteria

✅ Semua skenario sukses jika:

1. **Inline Fallback**: Modal Preview dan Dashboard menampilkan pesan fallback yang **identik**
2. **Cache Expiry**: Dashboard tetap menampilkan status relevan (bukan generic default)
3. **Real-Time Progress**: Message update dengan **fase actual** (bukan generic progress)

✅ **Tidak ada regression**: Existing import flows tetap berjalan normal (queued → processing → completed)

---

## Rollback Plan (If Needed)

Jika ada issue, revert changes:

```bash
git revert HEAD --no-edit
# atau
git checkout HEAD -- app/Services/Import/ImportProgressService.php
git checkout HEAD -- app/Services/Import/ImportExecutionService.php
```

---

## Related Files Modified

- `app/Services/Import/ImportProgressService.php` - Line 1169, 593
- `app/Services/Import/ImportExecutionService.php` - Line 167, 363-366
- `tests/Feature/Import/StatusSyncValidationTest.php` - New test file
- `tests/Manual/StatusSyncManualTestScript.php` - Manual testing script

---

## Questions?

Jika ada pertanyaan atau issue ditemukan selama testing, dokumentasi di CLAUDE.md atau check commit message untuk context lebih detail.
