# Panduan Backfill Shadow Columns untuk Daily Loan Dinamis

## Ringkasan Masalah

Kolom shadow (`segmen_kinerja`, `produk_kinerja`, `cabang_normalized`, dll.) pada tabel `daily_loan_dinamis` untuk periode terbaru (2026-04-25 dan 2026-04-26) kosong (NULL). Hal ini menyebabkan:

- **Laporan Kinerja RM & Mantri**: Tampak "zonk" (kosong/tidak ada data)
- **Penyebab**: Lock wait timeout saat migrasi mencoba UPDATE massal ~1.9M baris
- **Solusi**: Backfill bertahap dengan chunking untuk menghindari lock contention

---

## Solusi: Command Artisan Backfill Shadow Columns

Command `shadow:backfill` melakukan:
✓ Chunked processing (default: 10,000 rows per chunk)
✓ Delay antara chunks untuk mengurangi lock contention
✓ Retry logic dengan exponential backoff
✓ Progress tracking real-time
✓ Automatic snapshot rebuild setelah backfill
✓ Cache clearing untuk UI update

---

## Cara Penggunaan

### 1. **Backfill Periode Terbaru (Recommended)**

```bash
php artisan shadow:backfill \
  --periods=2026-04-25,2026-04-26
```

**Output yang diharapkan:**
```
📅 Processing period: 2026-04-25
   Processing 323,635 rows in chunks of 10000
   [████████████░░░░░] 65% | 210000/323635 | 02:15 / 03:30
   ✓ Period completed: 323635/323635 rows

🔄 Rebuilding Performance RM snapshots...
✓ Snapshots rebuilt successfully
```

### 2. **Preview Tanpa Perubahan Data (Dry-Run)**

```bash
php artisan shadow:backfill \
  --periods=2026-04-25,2026-04-26 \
  --dry-run
```

Ini akan menampilkan apa yang akan dijalankan tanpa mengubah data.

### 3. **Custom Configuration**

```bash
php artisan shadow:backfill \
  --periods=2026-04-01,2026-04-02,2026-04-03 \
  --chunk-size=5000 \           # Reduce untuk menghindari timeout di XAMPP
  --delay=1000 \                 # Increase jika masih terjadi lock (ms)
  --retry-count=5                # Increase untuk higher reliability
```

**Parameter Details:**

| Parameter | Default | Deskripsi |
|-----------|---------|-----------|
| `--periods` | 2026-04-25,2026-04-26 | Periode yang ingin diisi (format: YYYY-MM-DD) |
| `--chunk-size` | 10000 | Baris per update (turunkan untuk XAMPP: 5000-8000) |
| `--delay` | 500 | Delay antar chunk dalam ms (tingkatkan jika timeout) |
| `--retry-count` | 3 | Retry attempts per chunk |
| `--dry-run` | false | Preview tanpa mengubah data |

---

## Penyesuaian untuk XAMPP Windows

XAMPP memiliki keterbatasan I/O, sehingga mungkin memerlukan tuning:

### **Jika Terjadi Lock Timeout:**

**Opsi 1: Reduce chunk size**
```bash
php artisan shadow:backfill \
  --periods=2026-04-25,2026-04-26 \
  --chunk-size=5000 \
  --delay=1000
```

**Opsi 2: Run secara manual dengan sleep interval**
```bash
php artisan shadow:backfill --periods=2026-04-25 --chunk-size=3000 --delay=2000
```

**Opsi 3: Run di luar jam puncak** (jika aplikasi sedang tidak digunakan)
```bash
# Run on background (PowerShell)
Start-Process "php" -ArgumentList "artisan shadow:backfill --periods=2026-04-25,2026-04-26" -WindowStyle Hidden
```

---

## Monitoring Progress

### Real-Time Monitoring (di terminal terpisah)

```bash
# Terminal 1: Jalankan backfill
php artisan shadow:backfill --periods=2026-04-25,2026-04-26

# Terminal 2: Monitor progress
php artisan shadow:validate --periods=2026-04-25,2026-04-26 --watch
```

### Check Status Query

```bash
php artisan shadow:validate --periods=2026-04-25,2026-04-26
```

Output:
```
Period 2026-04-25:
  ✓ segmen_kinerja: 323635/323635 filled (100%)
  ✓ produk_kinerja: 323635/323635 filled (100%)
  ✓ cabang_normalized: 323635/323635 filled (100%)
  ... (all columns shown)

Period 2026-04-26:
  ⚠ segmen_kinerja: 150000/200000 filled (75%)
  ... (in-progress)
```

---

## Step-by-Step Workflow

### **Langkah 1: Validasi Kondisi Awal**

```bash
php artisan shadow:validate --periods=2026-04-25,2026-04-26
```

Catat jumlah NULL sebelum backfill.

### **Langkah 2: Preview dengan Dry-Run**

```bash
php artisan shadow:backfill \
  --periods=2026-04-25,2026-04-26 \
  --chunk-size=5000 \
  --dry-run
```

Verifikasi konfigurasi sebelum eksekusi.

### **Langkah 3: Eksekusi Backfill**

```bash
php artisan shadow:backfill \
  --periods=2026-04-25,2026-04-26 \
  --chunk-size=5000 \
  --delay=1000
```

Tunggu hingga selesai. Command akan:
- ✓ Mengisi semua shadow columns
- ✓ Rebuild snapshots otomatis
- ✓ Clear cache

### **Langkah 4: Validasi Hasil**

```bash
php artisan shadow:validate --periods=2026-04-25,2026-04-26
```

Pastikan semua kolom 100% terisi.

### **Langkah 5: Verifikasi UI**

1. Buka aplikasi di browser
2. Akses menu **Laporan > Kinerja RM > Mikro (Mantri)**
3. Pilih periode **2026-04-25** atau **2026-04-26**
4. Verifikasi data muncul (tidak kosong)

---

## Troubleshooting

### **Error: "Lock wait timeout exceeded"**

**Penyebab**: Chunk size terlalu besar atau server I/O terbatas

**Solusi**:
```bash
# Reduce chunk size
php artisan shadow:backfill \
  --periods=2026-04-25 \
  --chunk-size=2000 \
  --delay=2000

# OR run satu periode per waktu
php artisan shadow:backfill --periods=2026-04-25 --chunk-size=5000
php artisan shadow:backfill --periods=2026-04-26 --chunk-size=5000
```

### **Error: "REGEXP_REPLACE function not found"**

**Penyebab**: MySQL version < 8.0

**Solusi**: Update command untuk menggunakan REGEXP_SUBSTR atau REPLACE biasa
```sql
-- MySQL < 8.0 compatible version
cifno_clean = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(cifno, ''), '-', ''), '/', ''), ' ', ''), '(', ''), ')', '')
```

### **Snapshot Rebuild Gagal**

**Penyebab**: Shadow columns belum terisi 100%

**Solusi**: Validate dulu sebelum rebuild
```bash
# Validate
php artisan shadow:validate --periods=2026-04-25,2026-04-26

# Manual rebuild setelah validation OK
php artisan snapshot:rebuild-rm --period=2026-04-25 --force
php artisan snapshot:rebuild-rm --period=2026-04-26 --force
```

---

## Recovery jika Terjadi Error

### **Rollback Partial Updates**

Jika proses terganggu di tengah jalan:

```bash
# Set NULL untuk periode tertentu dan mulai ulang
UPDATE daily_loan_dinamis 
SET 
    segmen_kinerja = NULL,
    produk_kinerja = NULL,
    cabang_normalized = NULL,
    unit_normalized = NULL,
    branch_normalized = NULL,
    rm_normalized = NULL,
    cifno_clean = NULL
WHERE periode IN ('2026-04-25', '2026-04-26');

-- Kemudian jalankan backfill lagi
php artisan shadow:backfill --periods=2026-04-25,2026-04-26
```

---

## Performance Expectations

**Expected Duration** (XAMPP Windows, default settings):

| Periode | Total Rows | Chunk Size | Expected Time |
|---------|-----------|-----------|---|
| 1 bulan | ~323,635 | 10,000 | 3-5 minutes |
| 2 bulan | ~650,000 | 10,000 | 6-10 minutes |
| 1 periode | ~323,635 | 5,000 | 5-8 minutes |

**Optimization Tips:**
- ✓ Run di luar jam puncak (malam/weekend)
- ✓ Stop background workers sebelum backfill
- ✓ Reduce chunk size untuk XAMPP (3000-5000)
- ✓ Increase delay untuk mengurangi lock contention

---

## Verifikasi Setelah Backfill

### **Database Query Check**

```sql
-- Verifikasi shadow columns terisi
SELECT 
    periode,
    COUNT(*) as total_rows,
    COUNT(CASE WHEN segmen_kinerja IS NOT NULL THEN 1 END) as segmen_filled,
    COUNT(CASE WHEN produk_kinerja IS NOT NULL THEN 1 END) as produk_filled,
    COUNT(CASE WHEN cabang_normalized IS NOT NULL THEN 1 END) as cabang_filled,
    ROUND(COUNT(CASE WHEN segmen_kinerja IS NOT NULL THEN 1 END) * 100.0 / COUNT(*), 2) as pct_filled
FROM daily_loan_dinamis
WHERE periode IN ('2026-04-25', '2026-04-26')
GROUP BY periode;
```

Expected output:
```
periode      | total_rows | segmen_filled | produk_filled | cabang_filled | pct_filled
2026-04-25   | 323635     | 323635        | 323635        | 323635        | 100.00
2026-04-26   | 200000     | 200000        | 200000        | 200000        | 100.00
```

### **Snapshot Check**

```sql
-- Verifikasi snapshots terisi
SELECT 
    periode,
    COUNT(*) as snapshot_rows,
    COUNT(CASE WHEN agregat_penempatan > 0 THEN 1 END) as non_zero_rows
FROM performance_rm_snapshots
WHERE periode IN ('2026-04-25', '2026-04-26')
GROUP BY periode;
```

Expected: `non_zero_rows > 0` (data tidak kosong)

---

## Next Steps

1. **Jalankan command** sesuai langkah-langkah di atas
2. **Validasi hasil** dengan query di database
3. **Test UI** untuk memverifikasi laporan tampil dengan benar
4. **Document konfigurasi** jika menggunakan parameter custom

---

## Support & Debugging

### Jika Masih Bermasalah:

1. **Check error log:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Run validation untuk detail:**
   ```bash
   php artisan shadow:validate --periods=2026-04-25,2026-04-26 --verbose
   ```

3. **Manual SQL debug:**
   ```sql
   -- Check sample data
   SELECT id, periode, segmen_dashboard, segmen_kinerja, cifno, cifno_clean
   FROM daily_loan_dinamis
   WHERE periode = '2026-04-25'
   LIMIT 10;
   ```

---

**Created**: 2026-04-29
**Updated**: For comprehensive shadow column backfill process
