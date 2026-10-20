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

    public function test_version_two_uses_explicit_cif_exposure_including_zero_and_preserves_unknown_cifs_for_database(): void
    {
        file_put_contents($this->referencePath, json_encode($this->versionTwoFixture(), JSON_THROW_ON_ERROR));
        $reference = new MantriProductivityReference($this->referencePath);

        foreach (['2026-10-01', '2026-10-11', '2026-10-12', '2026-10-17', '2026-10-31'] as $period) {
            $this->assertSame([
                'CIF-SOURCE' => 160_000_000.25, 'CIF-ZERO' => 0.0,
            ], $reference->previousMicroOsByCif($period, [' cif-source ', 'CIF-ZERO', 'CIF-UNKNOWN']));
        }
        $this->assertSame([], $reference->previousMicroOsByCif('2026-10-12', ['CIF-UNKNOWN']));
        $this->assertNull($reference->previousMicroOsByCif('2026-09-30', ['CIF-SOURCE']));
        $this->assertNull($reference->previousMicroOsByCif('2026-11-01', ['CIF-SOURCE']));
    }

    public function test_version_two_baseline_and_fingerprint_remain_valid_after_owner_snapshot_until_month_end(): void
    {
        file_put_contents($this->referencePath, json_encode($this->versionTwoFixture(), JSON_THROW_ON_ERROR));
        $reference = new MantriProductivityReference($this->referencePath);

        foreach (['2026-10-17', '2026-10-31'] as $period) {
            $this->assertNull($reference->dataForPeriod($period));
            $this->assertNull($reference->accountReference($period, '1001', 'CIF-SOURCE', '2026-10-05'));
            $this->assertSame(['CIF-SOURCE' => 160_000_000.25], $reference->previousMicroOsByCif($period, ['CIF-SOURCE']));
            $this->assertSame(hash_file('sha256', $this->referencePath), $reference->fingerprint($period));
        }
        $this->assertNull($reference->previousMicroOsByCif('2026-11-01', ['CIF-SOURCE']));
        $this->assertSame('brihc', $reference->fingerprint('2026-11-01'));
    }

    public function test_version_two_requires_an_explicit_cif_baseline_array(): void
    {
        $data = $this->versionTwoFixture();
        unset($data['baseline_cif_os']);
        file_put_contents($this->referencePath, json_encode($data, JSON_THROW_ON_ERROR));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Referensi produktivitas Mantri tidak lengkap.');
        (new MantriProductivityReference($this->referencePath))->dataForPeriod('2026-10-12');
    }

    public function test_version_two_rejects_a_scalar_cif_baseline(): void
    {
        $data = $this->versionTwoFixture();
        $data['baseline_cif_os'] = '160000000.25';
        file_put_contents($this->referencePath, json_encode($data, JSON_THROW_ON_ERROR));

        $this->expectException(\RuntimeException::class);
        (new MantriProductivityReference($this->referencePath))->dataForPeriod('2026-10-12');
    }

    public function test_version_two_owner_requires_exact_source_position_and_account_cif_booking_date(): void
    {
        file_put_contents($this->referencePath, json_encode($this->versionTwoFixture(), JSON_THROW_ON_ERROR));
        $reference = new MantriProductivityReference($this->referencePath);

        $this->assertSame('555', $reference->accountReference('2026-10-12', '0001001', ' cif-source ', '2026-10-05 00:00:00')['pn']);
        $this->assertNull($reference->accountReference('2026-10-11', '1001', 'CIF-SOURCE', '2026-10-05'));
        $this->assertNull($reference->accountReference('2026-10-13', '1001', 'CIF-SOURCE', '2026-10-05'));
        $this->assertNull($reference->accountReference('2026-10-12', '1001', 'CIF-OTHER', '2026-10-05'));
        $this->assertNull($reference->accountReference('2026-10-12', '1001', 'CIF-SOURCE', '2026-10-06'));
        $this->assertNull($reference->accountReference('2026-10-12', 'OTHER', 'CIF-SOURCE', '2026-10-05'));
    }

    public function test_cif_baseline_from_wrong_previous_month_is_not_applied(): void
    {
        $data = $this->versionTwoFixture();
        $data['baseline_period'] = '2026-08-31';
        file_put_contents($this->referencePath, json_encode($data, JSON_THROW_ON_ERROR));

        $this->assertNull((new MantriProductivityReference($this->referencePath))->previousMicroOsByCif('2026-10-12', ['CIF-SOURCE']));
        $this->assertNull((new MantriProductivityReference($this->referencePath))->previousMicroOsByCif('2026-10-01', ['CIF-SOURCE']));
    }

    public function test_legacy_closed_exposure_is_never_silently_reused_as_version_two_micro_exposure(): void
    {
        $reference = new MantriProductivityReference($this->referencePath);
        $this->assertNull($reference->previousMicroOsByCif('2026-10-12', ['CIF-SOURCE']));
        $this->assertSame(['CIF-SOURCE' => 120_000_000.0], $reference->closedPreviousOsByCif('2026-10-12', ['CIF-SOURCE']));
    }

    private function versionTwoFixture(): array
    {
        $data = $this->fixture();
        $data['version'] = 2;
        $data['baseline_accounts'] = [];
        $data['current_accounts'] = [];
        $data['baseline_cif_os'] = ['CIF-SOURCE' => 160_000_000.25, 'CIF-ZERO' => 0, 'CIF-UNREQUESTED' => 999_000_000];

        return $data;
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
