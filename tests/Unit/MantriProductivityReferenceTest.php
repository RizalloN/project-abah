<?php

namespace Tests\Unit;

use App\Support\MantriProductivityReference;
use Tests\TestCase;

class MantriProductivityReferenceTest extends TestCase
{
    private string $referencePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->referencePath = tempnam(sys_get_temp_dir(), 'mantri_reference_');
        file_put_contents($this->referencePath, json_encode($this->fixture(), JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        @unlink($this->referencePath);
        parent::tearDown();
    }

    public function test_reference_is_explicit_in_tests_and_limited_to_its_source_period(): void
    {
        $this->assertNull((new MantriProductivityReference)->dataForPeriod('2026-10-12'));
        $reference = new MantriProductivityReference($this->referencePath);
        $this->assertNull($reference->dataForPeriod('2026-09-30'));
        $this->assertNotNull($reference->dataForPeriod('2026-10-01'));
        $this->assertNotNull($reference->dataForPeriod('2026-10-12'));
        $this->assertNull($reference->dataForPeriod('2026-10-13'));
        $this->assertSame('brihc', $reference->fingerprint('2026-10-13'));
        $this->assertSame(hash_file('sha256', $this->referencePath), $reference->fingerprint('2026-10-12'));
    }

    public function test_account_identity_requires_account_cif_and_booking_date_and_never_matches_future_booking(): void
    {
        $reference = new MantriProductivityReference($this->referencePath);
        $matched = $reference->accountReference('2026-10-12', '0001001', ' cif-source ', '2026-10-05 00:00:00');
        $this->assertSame('555', $matched['pn']);
        $this->assertSame('KC NGAWI', $matched['branch']);
        $this->assertNull($reference->accountReference('2026-10-12', '1001', 'CIF-OTHER', '2026-10-05'));
        $this->assertNull($reference->accountReference('2026-10-12', 'OTHER', 'CIF-SOURCE', '2026-10-05'));
        $this->assertNull($reference->accountReference('2026-10-12', '1001', 'CIF-SOURCE', '2026-10-06'));
        $this->assertNull($reference->accountReference('2026-10-04', '1001', 'CIF-SOURCE', '2026-10-05'));
        $this->assertNull($reference->accountReference('2026-10-13', '1001', 'CIF-SOURCE', '2026-10-05'));
    }

    public function test_source_baseline_deduplicates_closed_accounts_excludes_open_accounts_and_leaves_other_cifs_to_database(): void
    {
        $reference = new MantriProductivityReference($this->referencePath);
        $this->assertSame(['CIF-SOURCE' => 120_000_000.0], $reference->closedPreviousOsByCif('2026-10-12', [' cif-source ', 'CIF-OTHER']));
        $this->assertSame([], $reference->closedPreviousOsByCif('2026-10-12', ['CIF-OTHER']));
        $this->assertNull($reference->closedPreviousOsByCif('2026-10-11', ['CIF-SOURCE']));
        $this->assertSame('555', $reference->accountReference('2026-10-11', '1001', 'CIF-SOURCE', '2026-10-05')['pn']);
        $this->assertNull($reference->closedPreviousOsByCif('2026-10-13', ['CIF-SOURCE']));
    }

    public function test_replacing_source_content_changes_fingerprint_without_requiring_a_loan_import(): void
    {
        $original = new MantriProductivityReference($this->referencePath);
        $before = $original->fingerprint('2026-10-12');
        $originalData = $original->dataForPeriod('2026-10-12');
        $data = $this->fixture();
        $data['baseline_accounts'][0]['balance'] = 150_000_000;
        file_put_contents($this->referencePath, json_encode($data, JSON_THROW_ON_ERROR));
        $replacement = new MantriProductivityReference($this->referencePath);
        $this->assertSame($before, $original->fingerprint('2026-10-12'));
        $this->assertSame($originalData, $original->dataForPeriod('2026-10-12'));
        $this->assertSame(['CIF-SOURCE' => 120_000_000.0], $original->closedPreviousOsByCif('2026-10-12', ['CIF-SOURCE']));
        $this->assertNotSame($before, $replacement->fingerprint('2026-10-12'));
        $this->assertSame(['CIF-SOURCE' => 150_000_000.0], $replacement->closedPreviousOsByCif('2026-10-12', ['CIF-SOURCE']));
    }

    private function fixture(): array
    {
        return [
            'version' => 1, 'month_start' => '2026-10-01', 'as_of' => '2026-10-12', 'baseline_period' => '2026-09-30',
            'sources' => ['test-workbook.xlsx'],
            'people' => [
                ['pn' => '555', 'name' => 'Mantri Referensi', 'branch' => 'KC NGAWI', 'unit' => 'UNIT NGAWI', 'bc' => '3201', 'category' => 'pt'],
                ['pn' => '556', 'name' => 'Mantri Nol', 'branch' => 'KC NGAWI', 'unit' => 'UNIT NGAWI', 'bc' => '3201', 'category' => 'contract'],
            ],
            'accounts' => [
                ['account' => '1001', 'cif' => 'CIF-SOURCE', 'realization_date' => '2026-10-05', 'pn' => '555', 'name' => 'Mantri Referensi', 'branch' => 'KC NGAWI', 'unit' => 'UNIT NGAWI', 'bc' => '3201'],
            ],
            'baseline_accounts' => [
                ['account' => '000750', 'cif' => 'CIF-SOURCE', 'balance' => 120_000_000],
                ['account' => '750', 'cif' => 'CIF-SOURCE', 'balance' => 80_000_000],
                ['account' => 'OPEN', 'cif' => 'CIF-SOURCE', 'balance' => 40_000_000],
                ['account' => 'OTHER-CLOSED', 'cif' => 'CIF-OTHER', 'balance' => 999_000_000],
            ],
            'current_accounts' => ['1001', 'OPEN'],
        ];
    }
}
