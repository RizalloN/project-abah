<?php

namespace Tests\Unit;

use App\Support\Lw321DailyLoanMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Lw321DailyLoanMapperTest extends TestCase
{
    #[DataProvider('descriptionProvider')]
    public function test_description_reference_maps_to_daily_loan_dimensions(
        string $description,
        string $segment,
        string $product
    ): void {
        $this->assertSame(
            ['segment' => $segment, 'product' => $product, 'matched' => true],
            array_intersect_key(
                Lw321DailyLoanMapper::classifyDescription($description),
                array_flip(['segment', 'product', 'matched'])
            )
        );
    }

    public static function descriptionProvider(): array
    {
        return [
            ['KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - BRIGUNA KARYA', 'Consumer', 'Briguna-Konsumer'],
            ['KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - KREDIT PEGAWAI BRI', 'Consumer', 'Briguna-Konsumer'],
            ['KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - BRIGUNA PURNA', 'Consumer', 'Briguna-Konsumer'],
            ['KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - BRIGUNA UMUM', 'Consumer', 'Briguna-Konsumer'],
            ['KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - KREDIT PEMILIKAN RUMAH (KPR) KOMERSIAL', 'Consumer', 'KPR'],
            ['KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - KREDIT PEMILIKAN RUMAH (KPR) SUBSIDI', 'Consumer', 'KPR'],
            ['01. RITKOM - S/D Rp 50 JUTA', 'Small', 'Commercial'],
            ['02. RITKOM - > Rp.50 JUTA S/D Rp 100 JUTA', 'Small', 'Commercial'],
            ['03. RITKOM - > Rp 100 JUTA S/D Rp 350 JUTA', 'Small', 'Commercial'],
            ['04. RITKOM - > Rp 350 JUTA S/D Rp 500 JUTA', 'Small', 'Commercial'],
            ['05. RITKOM - > Rp 500 JUTA S/D Rp 1 M', 'Small', 'Commercial'],
            ['06. RITKOM - > Rp 1 M S/D Rp 2 M', 'Small', 'Commercial'],
            ['07. RITKOM - > Rp 2 M S/D Rp 3 M', 'Small', 'Commercial'],
            ['08. RITKOM - > Rp 3 M S/D Rp 4 M', 'Small', 'Commercial'],
            ['09. RITKOM - > Rp 4 M S/D Rp 5 M', 'Small', 'Commercial'],
            ['KREDIT PANGAN', 'Small', 'Commercial'],
            ['CASHCOLL KREDIT SMALL', 'Small', 'Cashcall'],
            ['10. RITKOM -> Rp. 5 M S/D 15 M', 'Small', 'Commercial'],
            ['11. RITKOM -> Rp. 15 M S/D 25 M', 'Small', 'Commercial'],
            ['(KWL) 1. MENENGAH > Rp 25 M S/D 50 M', 'Medium', 'Medium'],
            ['(KWL) 2. MENENGAH > Rp 50 M S/D 200 M', 'Medium', 'Medium'],
            ['KREDIT MIKRO - GBT', 'Micro', 'Briguna-Mikro'],
            ['Kredit Mikro - KUR Ritel 2015�', 'Micro', 'KUR-Kecil'],
            ['KREDIT MIKRO - KUR MIKRO BARU', 'Micro', 'KUR-Mikro'],
            ['KREDIT MIKRO - KUPEDES', 'Micro', 'Kupedes'],
            ['KREDIT MIKRO - KUPEDES RAKYAT', 'Micro', 'Kupedes'],
            ['KREDITMIKRO - KPP', 'Micro', 'KPR'],
            ['Kredit Mikro - Cash Collateral', 'Micro', 'Cash Collateral'],
        ];
    }

    public function test_blank_or_unknown_description_is_not_guessed(): void
    {
        $unmatched = [
            'segment' => null,
            'product' => null,
            'performance_segment' => null,
            'performance_product' => null,
            'matched' => false,
        ];
        $this->assertSame($unmatched, Lw321DailyLoanMapper::classifyDescription(null));
        $this->assertSame($unmatched, Lw321DailyLoanMapper::classifyDescription('DESKRIPSI BARU'));
    }

    public function test_performance_dimensions_follow_daily_loan_including_ritkom_medium_band(): void
    {
        $ritkom = Lw321DailyLoanMapper::classifyDescription('10. RITKOM -> Rp. 5 M S/D 15 M');
        $this->assertSame('MEDIUM', $ritkom['performance_segment']);
        $this->assertSame('COMMERCIAL', $ritkom['performance_product']);

        $menengah = Lw321DailyLoanMapper::classifyDescription('(KWL) 1. MENENGAH > Rp 25 M S/D 50 M');
        $this->assertSame(['Medium', 'Medium', 'MEDIUM', 'MEDIUM'], [
            $menengah['segment'], $menengah['product'], $menengah['performance_segment'], $menengah['performance_product'],
        ]);

        $kur = Lw321DailyLoanMapper::classifyDescription('KREDIT MIKRO - KUR MIKRO BARU');
        $this->assertSame(['MICRO', 'KURMIKRO'], [$kur['performance_segment'], $kur['performance_product']]);
    }

    public function test_description_is_written_in_daily_loan_vocabulary(): void
    {
        $this->assertSame('KUR Mikro Baru', Lw321DailyLoanMapper::dailyDescription('KREDIT MIKRO - KUR MIKRO BARU'));
        $this->assertSame('Kupedes Rakyat', Lw321DailyLoanMapper::dailyDescription('KREDIT MIKRO - KUPEDES RAKYAT'));
        $this->assertSame('05. > Rp 500 JUTA S/D Rp 1 M', Lw321DailyLoanMapper::dailyDescription('05. RITKOM - > Rp 500 JUTA S/D Rp 1 M'));
        $this->assertSame(
            'KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - KRETAP',
            Lw321DailyLoanMapper::dailyDescription('KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - BRIGUNA KARYA')
        );
        $this->assertSame(
            'KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - KRESUN',
            Lw321DailyLoanMapper::dailyDescription('KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - BRIGUNA PURNA')
        );
        // Deskripsi yang sudah identik dengan Daily Loan dipertahankan apa adanya.
        $this->assertSame('KREDIT MIKRO - GBT', Lw321DailyLoanMapper::dailyDescription('KREDIT MIKRO - GBT'));
        $this->assertSame('PRODUK BARU', Lw321DailyLoanMapper::dailyDescription('  PRODUK BARU '));
        $this->assertNull(Lw321DailyLoanMapper::dailyDescription('-'));
        $this->assertNull(Lw321DailyLoanMapper::dailyDescription(null));
    }

    public function test_rate_and_tenor_follow_daily_loan_units(): void
    {
        $this->assertSame(0.06, Lw321DailyLoanMapper::dailyRate('6.000000'));
        $this->assertSame(0.2625, Lw321DailyLoanMapper::dailyRate(26.25));
        $this->assertNull(Lw321DailyLoanMapper::dailyRate(null));
        $this->assertNull(Lw321DailyLoanMapper::dailyRate(''));

        $this->assertSame('36', Lw321DailyLoanMapper::dailyTenor('36M'));
        $this->assertSame('120', Lw321DailyLoanMapper::dailyTenor(' 120m '));
        $this->assertSame('24', Lw321DailyLoanMapper::dailyTenor('24'));
        $this->assertSame('12D', Lw321DailyLoanMapper::dailyTenor('12D'));
        $this->assertNull(Lw321DailyLoanMapper::dailyTenor(''));
    }

    public function test_age_uses_the_earliest_available_due_date(): void
    {
        $this->assertSame(31, Lw321DailyLoanMapper::resolveArrearsAge(
            '2026-09-08',
            '2026-08-20',
            '2026-08-08'
        ));
        $this->assertSame(-2, Lw321DailyLoanMapper::resolveArrearsAge(
            '2026-09-08',
            null,
            '2026-09-10'
        ));
    }

    public function test_quality_boundaries_follow_lw321_business_rule_exactly(): void
    {
        $cases = [
            [-1, 'N', '1', 'L'],
            [0, 'Y', '1', 'LR'],
            [1, 'Y', '2', 'DPK 1'],
            [30, 'N', '2', 'DPK 1'],
            [31, 'N', '2', 'DPK 2'],
            [60, 'N', '2', 'DPK 2'],
            [61, 'N', '2', 'DPK 3'],
            [90, 'N', '2', 'DPK 3'],
            [91, 'N', '3', 'KL'],
            [120, 'N', '3', 'KL'],
            [121, 'N', '4', 'D1'],
            [150, 'N', '4', 'D1'],
            [151, 'N', '4', 'D2'],
            [180, 'N', '4', 'D2'],
            [181, 'N', '5', 'M'],
        ];

        foreach ($cases as [$age, $flag, $kolek, $detail]) {
            $this->assertSame(
                ['kolek' => $kolek, 'kolek_detail' => $detail],
                Lw321DailyLoanMapper::qualityFromAge($age, $flag),
                "Gagal pada umur {$age}"
            );
        }
    }
}
