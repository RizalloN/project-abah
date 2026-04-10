<?php

namespace Tests\Unit;

use App\Support\LoanQualityBucketMapper;
use Tests\TestCase;

class LoanQualityBucketMapperTest extends TestCase
{
    public function test_umur_tunggakan_remains_the_primary_bucket_driver(): void
    {
        $this->assertSame('DPK 1', LoanQualityBucketMapper::map('L', 2, null, '2'));
        $this->assertSame('DPK 2', LoanQualityBucketMapper::map('L', 36, null, '1'));
        $this->assertSame('D1', LoanQualityBucketMapper::map('M', 130, null, '5'));
    }

    public function test_kolek_detail_is_used_when_umur_tunggakan_is_missing(): void
    {
        $this->assertSame('KL', LoanQualityBucketMapper::map('KL', null, null, '1'));
        $this->assertSame('DPK 2', LoanQualityBucketMapper::map('DPK2', null, null, null, null));
        $this->assertSame('Pay', LoanQualityBucketMapper::map('LUNAS', null, null, null, null));
    }

    public function test_lr_requires_l_detail_and_l_result_after_crosscheck(): void
    {
        $this->assertSame('LR', LoanQualityBucketMapper::map('L', 0, 'Y', '1'));
        $this->assertSame('L', LoanQualityBucketMapper::map('', 0, 'Y', '1'));
        $this->assertSame('DPK 1', LoanQualityBucketMapper::map('L', 2, 'Y', '1'));
    }

    public function test_invalid_or_missing_detail_becomes_unknown_when_age_is_missing(): void
    {
        $this->assertSame('Unknown', LoanQualityBucketMapper::map('', null, null, null, null));
        $this->assertSame('Unknown', LoanQualityBucketMapper::map('0', null, null, '2', '3'));
    }
}
