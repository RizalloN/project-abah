<?php

namespace Tests\Unit;

use App\Support\DailyLoanManualSegmentRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DailyLoanManualSegmentRuleTest extends TestCase
{
    #[DataProvider('manualDescriptionProvider')]
    public function test_description_is_the_source_of_truth_for_manual_segment(
        string $description,
        string $expectedSegment
    ): void {
        $classification = DailyLoanManualSegmentRule::classify(
            $description,
            'Wrong source segment',
            'Wrong source product'
        );

        $this->assertTrue($classification['matched']);
        $this->assertSame($expectedSegment, $classification['segment']);
        $this->assertSame('WRONGSOURCEPRODUCT', $classification['product']);
    }

    public function test_normalization_accepts_nbsp_case_and_numbering_variants(): void
    {
        $kurRitel = DailyLoanManualSegmentRule::classify(
            "  Kredit Mikro - KUR Ritel 2015\xC2\xA0 ",
            'Small',
            'Commercial'
        );
        $legacyNumber = DailyLoanManualSegmentRule::classify(
            '12. > Rp 3 M S/D Rp 4 M',
            'Medium',
            'Medium'
        );

        $this->assertSame(['segment' => 'MICRO', 'product' => 'COMMERCIAL', 'matched' => true], $kurRitel);
        $this->assertSame(['segment' => 'SMALL', 'product' => 'MEDIUM', 'matched' => true], $legacyNumber);
    }

    public function test_unknown_description_keeps_normalized_source_classification(): void
    {
        $classification = DailyLoanManualSegmentRule::classify(
            'Description yang belum dipetakan',
            'Micro Retail',
            'KUR-Kecil'
        );

        $this->assertFalse($classification['matched']);
        $this->assertSame('MICRORETAIL', $classification['segment']);
        $this->assertSame('KURKECIL', $classification['product']);
    }

    public static function manualDescriptionProvider(): array
    {
        return [
            'consumer briguna' => [
                'KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - BRIGUNA UMUM',
                'CONSUMER',
            ],
            'consumer kpr' => [
                'KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - KREDIT PEMILIKAN RUMAH (KPR) SUBSIDI',
                'CONSUMER',
            ],
            'small bracket 1 to 2 billion' => [
                '06. > Rp 1 M S/D Rp 2 M',
                'SMALL',
            ],
            'small cash collateral' => [
                'CASHCOLL KREDIT SMALL',
                'SMALL',
            ],
            'medium ritkom' => [
                '10. RITKOM -> Rp. 5 M S/D 15 M',
                'MEDIUM',
            ],
            'medium kwl' => [
                '(KWL) 2. MENENGAH > Rp 50 M S/D 200 M',
                'MEDIUM',
            ],
            'micro kur retail' => [
                'Kredit Mikro - KUR Ritel 2015',
                'MICRO',
            ],
            'micro kupedes rakyat' => [
                'Kupedes Rakyat',
                'MICRO',
            ],
            'micro cash collateral' => [
                'Kredit Mikro - Cash Collateral',
                'MICRO',
            ],
        ];
    }
}
