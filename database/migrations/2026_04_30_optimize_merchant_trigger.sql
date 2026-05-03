-- Migration: Optimize Trigger untuk Import Jumlah Merchant Detail
-- Purpose: Reduce overhead saat bulk import dengan menerapkan session variable bypass
--          dan deduplication agar DELETE hanya berjalan sekali per periode

-- Drop old inefficient trigger
DROP TRIGGER IF EXISTS trg_merchant_detail_after_insert;

-- Create optimized trigger dengan pattern sama seperti trg_daily_loan_after_insert
DELIMITER $$

CREATE DEFINER=`root`@`127.0.0.1` TRIGGER trg_merchant_detail_after_insert
    AFTER INSERT ON jumlah_merchant_detail
    FOR EACH ROW
    BEGIN
        -- Check if snapshot invalidation should be skipped (during bulk import)
        -- If @skip_snapshot_invalidation = 1, trigger akan completely bypass
        IF COALESCE(@skip_snapshot_invalidation, 0) = 0 AND NEW.POSISI IS NOT NULL THEN
            -- Initialize deduplication tracking list if not exists
            SET @jmd_snapshot_period_keys = COALESCE(@jmd_snapshot_period_keys, '');
            SET @jmd_snapshot_period_key = DATE_FORMAT(NEW.POSISI, '%Y-%m-%d');

            -- Only invalidate snapshot jika periode belum pernah di-invalidate dalam session ini
            IF FIND_IN_SET(@jmd_snapshot_period_key, @jmd_snapshot_period_keys) = 0 THEN
                DELETE FROM dashboard_harian_snapshots WHERE snapshot_period = NEW.POSISI;
                -- Add periode to deduplication list untuk mencegah duplicate DELETEs
                SET @jmd_snapshot_period_keys = CONCAT_WS(',', @jmd_snapshot_period_keys, @jmd_snapshot_period_key);
            END IF;
        END IF;
    END$$

DELIMITER ;
