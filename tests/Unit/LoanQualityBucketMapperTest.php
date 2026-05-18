<?php

namespace Tests\Unit;

use App\Support\LoanQualityBucketMapper;
use Tests\TestCase;

class LoanQualityBucketMapperTest extends TestCase
{
    public function test_kolek_is_the_primary_bucket_driver(): void
    {
        $this->assertSame('DPK 1', LoanQualityBucketMapper::map('L', 2, null, '9', '2'));
        $this->assertSame('L', LoanQualityBucketMapper::map('M', 130, null, '5', '1'));
        $this->assertSame('KL', LoanQualityBucketMapper::map('L', 0, null, '1', '3'));
        $this->assertSame('M', LoanQualityBucketMapper::map('L', 0, null, '1', '5'));
    }

    public function test_kolek_crosschecks_age_boundaries_for_dpk_and_diragukan(): void
    {
        $this->assertSame('DPK 1', LoanQualityBucketMapper::map('L', 30, null, null, '2'));
        $this->assertSame('DPK 2', LoanQualityBucketMapper::map('L', 31, null, null, '2'));
        $this->assertSame('DPK 2', LoanQualityBucketMapper::map('L', 60, null, null, '2'));
        $this->assertSame('DPK 3', LoanQualityBucketMapper::map('L', 61, null, null, '2'));
        $this->assertSame('DPK 3', LoanQualityBucketMapper::map('L', 90, null, null, '2'));
        $this->assertSame('DPK 3', LoanQualityBucketMapper::map('L', 274, null, null, '2'));
        $this->assertSame('DPK 3', LoanQualityBucketMapper::map('L', null, null, null, '2'));
        $this->assertSame('D1', LoanQualityBucketMapper::map('M', 149, null, null, '4'));
        $this->assertSame('D2', LoanQualityBucketMapper::map('M', 150, null, null, '4'));
        $this->assertSame('D2', LoanQualityBucketMapper::map('M', 179, null, null, '4'));
        $this->assertSame('D2', LoanQualityBucketMapper::map('M', 1323, null, null, '4'));
        $this->assertSame('D2', LoanQualityBucketMapper::map('M', null, null, null, '4'));
    }

    public function test_age_falls_back_to_periode_minus_earliest_next_pmt_when_umur_is_null(): void
    {
        // periode 2026-05-16, next_pmt_date 2026-05-16, next_pmt_int_date 2026-05-14
        // LEAST = 2026-05-14 -> age = 2 -> kolek=2 -> DPK 1
        $this->assertSame(
            'DPK 1',
            LoanQualityBucketMapper::map('L', null, null, null, '2', '2026-05-16', '2026-05-16', '2026-05-14')
        );

        // periode 2026-05-16, next_pmt_date 2025-12-01, int NULL -> age ~ 166 -> kolek=4, age<180 -> D2
        $this->assertSame(
            'D2',
            LoanQualityBucketMapper::map('M', null, null, null, '4', '2026-05-16', '2025-12-01', null)
        );

        // periode 2026-05-16, both NULL -> age unknown -> kolek=2 fallback DPK 3
        $this->assertSame(
            'DPK 3',
            LoanQualityBucketMapper::map('L', null, null, null, '2', '2026-05-16', null, null)
        );

        // umur_tunggakan provided wins over next_pmt fallback
        $this->assertSame(
            'DPK 1',
            LoanQualityBucketMapper::map('L', 10, null, null, '2', '2026-05-16', '2020-01-01', '2020-01-01')
        );
    }

    public function test_kolek_detail_is_used_when_umur_tunggakan_is_missing(): void
    {
        $this->assertSame('KL', LoanQualityBucketMapper::map('KL', null, null, '1'));
        $this->assertSame('DPK 2', LoanQualityBucketMapper::map('DPK2', null, null, null, null));
        $this->assertSame('Pay', LoanQualityBucketMapper::map('LUNAS', null, null, null, null));
    }

    public function test_lr_requires_l_detail_and_l_result_after_crosscheck(): void
    {
        $this->assertSame('LR', LoanQualityBucketMapper::map('L', 0, 'Y', '1', '1'));
        $this->assertSame('LR', LoanQualityBucketMapper::map('', 0, 'Y', '1', '1'));
        $this->assertSame('DPK 1', LoanQualityBucketMapper::map('L', 2, 'Y', '1', '2'));
    }

    public function test_invalid_or_missing_detail_becomes_unknown_when_age_is_missing(): void
    {
        $this->assertSame('Unknown', LoanQualityBucketMapper::map('', null, null, null, null));
        $this->assertSame('Unknown', LoanQualityBucketMapper::map('0', null, null, '2', null));
    }
}
