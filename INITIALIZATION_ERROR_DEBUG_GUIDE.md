# Error: "Gagal menginisialisasi import job" - Debugging Guide

## Error Message
```
Gagal menginisialisasi import job (deteksi header/staging). Silakan ulangi import dari awal.
```

## What This Means
Async initialization (header detection + CSV staging) failed ketika job execution dimulai.

## Root Causes (Check Order)

### 1. **File Tidak Ditemukan**
**Log entry:**
```
initializeQueuedImportJobForExecution: File tidak ditemukan
path: /storage/uploads/file.xlsx
```

**Penyebab:**
- File sudah dihapus antara upload dan execution
- Path storage tidak correct
- Permission denied untuk akses file

**Solution:**
- Re-upload file
- Check storage permissions
- Verify file size < limit

### 2. **Header Detection Gagal**
**Log entry:**
```
initializeQueuedImportJobForExecution: Header tidak ditemukan di Excel
table_name: ssa_pinjaman
```

**Penyebab:**
- Format file tidak sesuai table
- Header row berada di row selain 0
- Kolom header kosong atau tidak matches DB schema

**Solution:**
- Verify file format matches table schema
- Check if header row di posisi yang benar
- Lihat dokumentasi format file untuk table tersebut

### 3. **CSV Staging Gagal**
**Log entry:**
```
initializeQueuedImportJobForExecution: Gagal staging Excel to CSV
table_name: ssa_simpanan
exception: PhpOffice\PhpSpreadsheet\Exception
```

**Penyebab:**
- File Excel corrupted
- Memory insufficient untuk staging
- Disk space penuh untuk staging CSV

**Solution:**
- Try upload file yang baru
- Increase memory limit (sudah 512M di code)
- Check disk space di storage folder
- Break file menjadi smaller chunks

### 4. **Transform Headers Gagal**
**Log entry:**
```
initializeQueuedImportJobForExecution: Gagal transform headers
table_name: rka
exception: InvalidArgumentException
```

**Penyebab:**
- Import strategy untuk table tidak cocok
- Custom header transformation logic error
- Table name typo di import params

**Solution:**
- Check import strategy implementation
- Verify table name is correct
- Check custom transformation rules

### 5. **State Update Gagal**
**Log entry:**
```
initializeQueuedImportJobForExecution: Gagal update job state
exception: Exception
```

**Penyebab:**
- Redis/cache connection down
- Database locked
- Session expired

**Solution:**
- Check Redis status
- Check database connection
- Retry import

### 6. **Job Record Update Gagal**
**Log entry:**
```
initializeQueuedImportJobForExecution: Gagal update job total_files
```

**Penyebab:**
- Job record sudah dihapus
- Database permission issue
- Connection timeout

**Solution:**
- Check database status
- Retry import
- Check job record di DB

## How to Debug

### Step 1: Check Application Logs
Location: `storage/logs/laravel.log`

Search for:
```bash
grep "initializeQueuedImportJobForExecution" storage/logs/laravel.log | tail -50
```

### Step 2: Get Full Error Details
Search for job_id yang fail:
```bash
grep "job_id.*12345" storage/logs/laravel.log
```

### Step 3: Check Common Issues
- **Memory:** Verify PHP memory_limit >= 512MB
- **Disk:** Check disk space di storage folder
- **Permissions:** Check file permissions di storage
- **Database:** Verify DB connection
- **Redis:** Verify Redis connection (if using cache driver)

### Step 4: Test File Upload
- Try dengan file lebih kecil dulu (~1MB)
- Verify file format sesuai table schema
- Check header row format

## Recovery Steps

### Immediate (User-Level)
1. Clear browser cache
2. Try upload file lagi
3. Try file format lain (CSV vs Excel)

### Technical (Admin-Level)
1. Check logs untuk specific error
2. Verify system resources (memory, disk, DB)
3. Recreate job:
   ```bash
   php artisan import:reset-job {jobId}
   ```
4. Re-upload file

## Prevention

### For Users
- Keep file size reasonable (< 50MB ideal)
- Verify file format matches requirements
- Check header row correctness

### For Admins
- Monitor disk space
- Setup log rotation
- Monitor queue worker health
- Ensure adequate memory allocation

## Improved Error Messages
**After this fix, error messages akan lebih detail:**
```
Gagal menginisialisasi import job. Kemungkinan: file tidak ditemukan, 
format header tidak sesuai, atau akses file ditolak. Silakan cek log 
untuk detail. (Job ID: 12345)
```

Gunakan Job ID untuk tracking di logs.

## Contact Support
If error persists, provide:
1. Job ID
2. File name and size
3. Table name being imported
4. Last 50 lines dari `storage/logs/laravel.log`
5. System info: PHP version, disk space, memory available
