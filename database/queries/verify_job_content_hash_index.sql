-- ============================================================================
-- SQL Verification Script: Virtual Column Index on job_content_hash
-- ============================================================================
--
-- Jalankan queries ini setelah migration dijalankan untuk memverifikasi:
-- 1. Virtual column dibuat dengan benar
-- 2. Index dibuat dan aktif
-- 3. Query menggunakan index (Sargable query)
--
-- Execution: Copy-paste ke MySQL console atau jalankan via artisan tinker
-- ============================================================================

-- 1. Verify virtual column exists and is computed correctly
-- Expected: Column 'job_content_hash' ada dengan GENERATION EXPRESSION
SHOW COLUMNS FROM import_jobs WHERE Field = 'job_content_hash';

-- Output yang diharapkan:
-- | Field                | Type         | Null | Key | Default | Extra                                                          |
-- | job_content_hash     | varchar(64)  | YES  | MUL | NULL    | GENERATED ALWAYS AS (...JSON_EXTRACT...) STORED/VIRTUAL        |

-- ============================================================================

-- 2. Verify index exists on virtual column
-- Expected: Index 'idx_import_jobs_content_hash' ada dan VISIBLE
SHOW INDEX FROM import_jobs WHERE Column_name = 'job_content_hash' OR Key_name = 'idx_import_jobs_content_hash';

-- Output yang diharapkan:
-- | Table        | Non_unique | Key_name                      | Seq_in_index | Column_name      | Collation | Cardinality | Null | Index_type | Comment | Index_comment |
-- | import_jobs  |          1 | idx_import_jobs_content_hash  |            1 | job_content_hash |           |             | YES  | BTREE      |         |               |

-- ============================================================================

-- 3. Test EXPLAIN untuk verifikasi index digunakan
-- Ganti 'sample_hash_value' dengan hash nyata dari database
EXPLAIN SELECT * FROM import_jobs
WHERE job_content_hash = 'sample_hash_value'
AND id_report = 9;

-- Output yang diharapkan:
-- - Column 'key' harus menunjukkan 'idx_import_jobs_content_hash' (ATAU composite index yang include column ini)
-- - Column 'type' harus 'ref' atau 'eq_ref' (bukan 'ALL' yang artinya full table scan)
-- - Column 'rows' harus kecil (1-10, bukan jutaan)

-- Contoh output yang BAIK:
-- | id | select_type | table        | partitions | type | possible_keys                    | key                               | key_len | ref                    | rows | filtered | Extra |
-- | 1  | SIMPLE      | import_jobs  | NULL       | ref  | idx_import_jobs_content_hash     | idx_import_jobs_content_hash      | 131     | const                  | 1    | 11.11    |       |

-- Contoh output yang BURUK (full table scan):
-- | id | select_type | table        | partitions | type | possible_keys | key    | key_len | ref   | rows    | filtered | Extra       |
-- | 1  | SIMPLE      | import_jobs  | NULL       | ALL  | NULL          | NULL   | NULL    | NULL  | 1000000 | 0.10     | Using where |

-- ============================================================================

-- 4. Test performance comparison (untuk dataset besar)
-- Sebelum index: Waktu akan lambat (full table scan)
-- Setelah index: Waktu akan cepat (< 10ms untuk jutaan rows)
SELECT COUNT(*) AS matching_jobs,
       MIN(created_at) AS oldest,
       MAX(created_at) AS newest
FROM import_jobs
WHERE job_content_hash = 'sample_hash_value'
AND id_report = 9
AND status = 'completed';

-- ============================================================================

-- 5. Verify virtual column computed value (spot check)
-- Lihat beberapa rows untuk memastikan virtual column values benar
SELECT id,
       id_report,
       status,
       JSON_EXTRACT(job_context, '$.content_hash') AS extracted_hash,
       job_content_hash AS virtual_column_value,
       (JSON_EXTRACT(job_context, '$.content_hash') = job_content_hash) AS values_match
FROM import_jobs
WHERE job_context IS NOT NULL
AND JSON_EXTRACT(job_context, '$.content_hash') IS NOT NULL
LIMIT 10;

-- Output yang diharapkan:
-- Kolom 'values_match' harus TRUE untuk semua rows

-- ============================================================================

-- 6. Check index statistics (untuk query planning)
-- Ini menunjukkan berapa banyak unique values dalam index
SELECT object_schema,
       object_name,
       index_name,
       count_read,
       count_write,
       count_delete,
       count_update
FROM performance_schema.table_io_waits_summary_by_index_usage
WHERE index_name = 'idx_import_jobs_content_hash'
ORDER BY count_read DESC;

-- Note: Jika tidak ada data, index belum diakses. Jalankan query di step #3 dulu.

-- ============================================================================
-- Summary
-- ============================================================================
--
-- Jika semua steps di atas menunjukkan:
-- ✓ Column ada dan VIRTUAL
-- ✓ Index ada dan BTREE
-- ✓ EXPLAIN menunjukkan 'key' = 'idx_import_jobs_content_hash'
-- ✓ Type = 'ref' atau 'eq_ref' (bukan 'ALL')
-- ✓ Rows = sedikit (< 100, bukan jutaan)
--
-- Maka optimization BERHASIL! Complexity berkurang dari O(N) menjadi O(log N)
--
-- ============================================================================
