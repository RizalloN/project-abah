<?php

// Test script for Rasio Uker SQL-First
$sql = "
            INSERT INTO rasio_uker_snapshots (
                uniqueid_rcdus, loan_period, casa_period, source_branch_key, 
                uker_key, uker_label, segment_key, os_amount, casa_amount, 
                source_row_count, created_at, updated_at
            )
            SELECT
                MD5(CONCAT_WS('|', 'rcdus', ?, agg.source_branch_key, agg.uker_key, seg.segment_key)),
                ?,
                ?,
                agg.source_branch_key,
                agg.uker_key,
                agg.uker_key,
                seg.segment_key,
                CASE seg.segment_key
                    WHEN 'total' THEN agg.total_os
                    WHEN 'briguna' THEN agg.briguna_os
                    WHEN 'kpr' THEN agg.kpr_os
                    WHEN 'mikro' THEN agg.mikro_os
                    WHEN 'smc' THEN agg.smc_os
                    ELSE 0
                END as os_amount,
                CASE seg.segment_key
                    WHEN 'total' THEN agg.total_casa
                    WHEN 'briguna' THEN agg.briguna_casa
                    WHEN 'kpr' THEN agg.kpr_casa
                    WHEN 'mikro' THEN agg.mikro_casa
                    WHEN 'smc' THEN agg.smc_casa
                    ELSE 0
                END as casa_amount,
                agg.source_row_count,
                NOW(),
                NOW()
            FROM (
                SELECT
                    base.source_branch_key,
                    base.uker_key,
                    SUM(base.loan_balance) as total_os,
                    SUM(CASE WHEN base.has_briguna = 1 THEN base.loan_balance ELSE 0 END) as briguna_os,
                    SUM(CASE WHEN base.has_kpr = 1 THEN base.loan_balance ELSE 0 END) as kpr_os,
                    SUM(CASE WHEN base.has_mikro = 1 THEN base.loan_balance ELSE 0 END) as mikro_os,
                    SUM(CASE WHEN base.has_smc = 1 THEN base.loan_balance ELSE 0 END) as smc_os,
                    SUM(COALESCE(c.casa_balance, 0)) as total_casa,
                    SUM(CASE WHEN base.has_briguna = 1 THEN COALESCE(c.casa_balance, 0) ELSE 0 END) as briguna_casa,
                    SUM(CASE WHEN base.has_kpr = 1 THEN COALESCE(c.casa_balance, 0) ELSE 0 END) as kpr_casa,
                    SUM(CASE WHEN base.has_mikro = 1 THEN COALESCE(c.casa_balance, 0) ELSE 0 END) as mikro_casa,
                    SUM(CASE WHEN base.has_smc = 1 THEN COALESCE(c.casa_balance, 0) ELSE 0 END) as smc_casa,
                    SUM(base.source_row_count) as source_row_count
                FROM (
                    SELECT
                        UPPER(TRIM(d.cabang1)) as source_branch_key,
                        UPPER(TRIM(d.unit1)) as uker_key,
                        REGEXP_REPLACE(d.cifno, '[^0-9]', '') as identity_key,
                        SUM(COALESCE(d.baki_debet1, 0)) as loan_balance,
                        MAX(CASE WHEN UPPER(TRIM(d.segmen_dashboard)) = 'BRIGUNA' THEN 1 ELSE 0 END) as has_briguna,
                        MAX(CASE WHEN UPPER(TRIM(d.segmen_dashboard)) = 'KPR' THEN 1 ELSE 0 END) as has_kpr,
                        MAX(CASE WHEN UPPER(TRIM(d.segmen_dashboard)) = 'MIKRO' THEN 1 ELSE 0 END) as has_mikro,
                        MAX(CASE WHEN UPPER(TRIM(d.segmen_dashboard)) = 'SMC' THEN 1 ELSE 0 END) as has_smc,
                        COUNT(*) as source_row_count
                    FROM daily_loan_dinamis d
                    WHERE d.periode = ?
                        AND d.cifno IS NOT NULL AND d.cifno <> ''
                        AND d.cabang1 IS NOT NULL AND d.cabang1 <> ''
                        AND d.unit1 IS NOT NULL AND d.unit1 <> ''
                    GROUP BY source_branch_key, uker_key, identity_key
                ) base
                LEFT JOIN (
                    SELECT
                        REGEXP_REPLACE(s.CIFNO, '[^0-9]', '') as identity_key,
                        SUM(COALESCE(saldo_idr, 0)) as casa_balance
                    FROM simpanan_multipn s
                    WHERE s.posisi = ?
                        AND s.CIFNO IS NOT NULL
                        AND s.CIFNO <> ''
                    GROUP BY identity_key
                ) c ON c.identity_key = base.identity_key
                GROUP BY base.source_branch_key, base.uker_key
            ) agg
            CROSS JOIN (
                SELECT 'total' as segment_key UNION ALL
                SELECT 'briguna' UNION ALL
                SELECT 'kpr' UNION ALL
                SELECT 'mikro' UNION ALL
                SELECT 'smc'
            ) seg
            ON DUPLICATE KEY UPDATE
                casa_period = VALUES(casa_period),
                uker_label = VALUES(uker_label),
                os_amount = VALUES(os_amount),
                casa_amount = VALUES(casa_amount),
                source_row_count = VALUES(source_row_count),
                updated_at = VALUES(updated_at)
";

echo "Draft written.\n";
