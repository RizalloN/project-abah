<?php

namespace Tests\Unit;

use App\Services\Import\MauBrimoImportPolicy;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MauBrimoImportPolicyTest extends TestCase
{
    public function test_header_mapping_uses_exact_compact_names_and_ignores_other_branch_columns(): void
    {
        $policy = new MauBrimoImportPolicy;
        $headers = array_merge(['OLD_MBDESC', 'NAMA_KCI'], array_map(fn ($column) => strtoupper(str_replace('_', ' ', $column)), MauBrimoImportPolicy::COLUMNS));
        $headers[2] = "\xEF\xBB\xBF TAHUN ";
        $map = $policy->headerMap($headers);

        $this->assertCount(15, $map);
        $this->assertSame(8, $map['mbdesc']);
        $this->assertSame(8, $policy->mbdescIndex($headers));
        $this->assertSame(16, $map['jumlah_rek_fin']);
        $policy->assertSelectedColumns($headers, array_map('strval', range(2, 16)));
    }

    public function test_missing_mbdesc_does_not_fall_back_to_old_mbdesc_or_other_branch_headers(): void
    {
        $this->expectException(ValidationException::class);
        (new MauBrimoImportPolicy)->mbdescIndex(['OLD_MBDESC', 'NAMA_KCI', 'BRDESC']);
    }

    public function test_duplicate_mbdesc_is_rejected_even_when_spelled_with_spaces(): void
    {
        $this->expectException(ValidationException::class);
        (new MauBrimoImportPolicy)->mbdescIndex(['MBDESC', 'MB DESC']);
    }

    public function test_duplicate_canonical_column_is_rejected_before_mapping(): void
    {
        $this->expectException(ValidationException::class);
        (new MauBrimoImportPolicy)->headerMap(array_merge(MauBrimoImportPolicy::COLUMNS, ['Jumlah CIFNO']));
    }

    public function test_header_mapping_requires_every_business_column(): void
    {
        $headers = array_values(array_diff(MauBrimoImportPolicy::COLUMNS, ['jumlah_rek_fin']));
        $this->expectException(ValidationException::class);
        (new MauBrimoImportPolicy)->headerMap($headers);
    }

    #[DataProvider('branchProvider')]
    public function test_branch_membership_has_exact_names_and_consistent_code_prefixes(mixed $value, ?string $expected): void
    {
        $policy = new MauBrimoImportPolicy;
        $this->assertSame($expected, $policy->branch($value));
        $this->assertSame($expected !== null, $policy->belongsToArea6($value));
    }

    public static function branchProvider(): array
    {
        return [
            'canonical Madiun' => ['KC MADIUN', 'KC MADIUN'],
            'canonical Magetan' => ['KC MAGETAN', 'KC MAGETAN'],
            'canonical Ngawi' => ['KC NGAWI', 'KC NGAWI'],
            'canonical Ponorogo' => ['KC PONOROGO', 'KC PONOROGO'],
            'mixed whitespace and NBSP' => [" \tkc\xC2\xA0  madiun \r\n", 'KC MADIUN'],
            'bare city' => ['magetan', 'KC MAGETAN'],
            'short code prefix' => ['45 - KC MADIUN', 'KC MADIUN'],
            'padded code prefix' => ['00049 -- KC MAGETAN', 'KC MAGETAN'],
            'colon code prefix and bare city' => ['0057: ngawi', 'KC NGAWI'],
            'Ponorogo code prefix' => ['00070 - KC PONOROGO', 'KC PONOROGO'],
            'code only' => ['0045', null],
            'wrong code with valid city' => ['0045 - KC NGAWI', null],
            'unknown code with valid city' => ['9999 - KC MADIUN', null],
            'KCP is not KC' => ['KCP MADIUN', null],
            'unit is not KC' => ['UNIT KC MADIUN', null],
            'city suffix is not the branch' => ['KC MADIUN BARAT', null],
            'substring is not the city' => ['KC MADIUNAN', null],
            'different branch' => ['KC BLITAR', null],
            'blank' => [" \xC2\xA0 ", null],
            'null' => [null, null],
        ];
    }

    public function test_initial_selections_only_use_mbdesc_and_keep_raw_matching_values(): void
    {
        $raw = " kc\xC2\xA0madiun ";
        $all = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        $selected = (new MauBrimoImportPolicy)->initialSelections(
            ['NAMA_KCI', 'BRDESC', 'MBDESC'],
            [0 => $all, 1 => $all, 2 => [$raw, 'KC BLITAR', '0045 - KC NGAWI', $raw]]
        );
        $this->assertSame(['2' => [$raw]], $selected);
    }

    public function test_outside_mbdesc_is_skipped_before_other_fields_are_validated(): void
    {
        $policy = new MauBrimoImportPolicy;
        $map = $policy->headerMap(MauBrimoImportPolicy::COLUMNS);
        $row = array_fill(0, 15, null);
        $row[$map['mbdesc']] = 'KC BLITAR';
        $row[$map['brdesc']] = 'KC MADIUN';
        $this->assertNull($policy->normalizeRow($row, $map, 25));
        $this->assertNull($policy->normalizeRow([$map['mbdesc'] => 'UNIT KC MADIUN'], $map, 26));
    }

    public function test_normalization_keeps_source_dimensions_and_every_frequency_including_zero(): void
    {
        $policy = new MauBrimoImportPolicy;
        $map = $policy->headerMap(MauBrimoImportPolicy::COLUMNS);
        foreach (range(0, 15) as $frequency) {
            $row = $policy->normalizeRow($this->row([
                'mbdesc' => " kc\xC2\xA0Madiun ", 'brdesc' => 'Unit luar Area',
                'frequency' => (string) $frequency, 'jumlah_cifno' => '000', 'jumlah_rek_fin' => '0012',
            ]), $map, 17);
            $this->assertSame('kc Madiun', $row['mbdesc']);
            $this->assertSame('Unit luar Area', $row['brdesc']);
            $this->assertSame(2026, $row['tahun']);
            $this->assertSame('2026-10-24', $row['posisi']);
            $this->assertSame($frequency, $row['frequency']);
            $this->assertSame(0, $row['jumlah_cifno']);
            $this->assertSame(12, $row['jumlah_rek_fin']);
            $this->assertArrayNotHasKey('uniqueid_namareport', $row);
        }
    }

    #[DataProvider('invalidRowProvider')]
    public function test_invalid_eligible_rows_raise_422_with_the_source_line(array $overrides): void
    {
        $policy = new MauBrimoImportPolicy;
        try {
            $policy->normalizeRow($this->row($overrides), $policy->headerMap(MauBrimoImportPolicy::COLUMNS), 123);
            $this->fail('Expected the selected source row to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(422, $exception->status);
            $this->assertStringContainsString('Baris 123:', implode(' ', $exception->errors()['file']));
        }
    }

    public static function invalidRowProvider(): array
    {
        return [
            'blank dimension' => [['rgdesc' => " \xC2\xA0 "]],
            'null dimension' => [['segmentasi' => null]],
            'impossible calendar date' => [['posisi' => '31/02/2026']],
            'zero date' => [['posisi' => '0000-00-00']],
            'invalid month' => [['periode' => '2026-13']],
            'period month mismatch' => [['periode' => '2026-09']],
            'monthly period requires separator' => [['periode' => '202610']],
            'short year' => [['tahun' => '26']],
            'year mismatch' => [['tahun' => '2025']],
            'negative frequency' => [['frequency' => '-1']],
            'fractional count' => [['jumlah_cifno' => '1.5']],
            'scientific count' => [['jumlah_rek_fin' => '1e3']],
            'float input' => [['jumlah_cifno' => 1.0]],
            'integer overflow' => [['jumlah_rek_fin' => (string) PHP_INT_MAX.'0']],
        ];
    }

    public function test_missing_mapped_field_is_rejected_but_unknown_source_columns_are_ignored(): void
    {
        $policy = new MauBrimoImportPolicy;
        $headers = array_merge(MauBrimoImportPolicy::COLUMNS, ['extra_source_note']);
        $map = $policy->headerMap($headers);
        $row = array_merge($this->row(), ['ignored']);
        $this->assertSame(42, $policy->normalizeRow($row, $map, 2)['jumlah_cifno']);
        unset($row[$map['jumlah_cifno']]);
        $this->expectException(ValidationException::class);
        $policy->normalizeRow($row, $map, 3);
    }

    public function test_large_integers_are_checked_without_float_precision_loss(): void
    {
        $policy = new MauBrimoImportPolicy;
        $row = $policy->normalizeRow($this->row(['jumlah_cifno' => '9007199254740993', 'jumlah_rek_fin' => (string) PHP_INT_MAX]), $policy->headerMap(MauBrimoImportPolicy::COLUMNS), 5);
        $this->assertSame(9007199254740993, $row['jumlah_cifno']);
        $this->assertSame(PHP_INT_MAX, $row['jumlah_rek_fin']);
    }

    public function test_identity_uses_all_dimensions_and_excludes_both_counts(): void
    {
        $policy = new MauBrimoImportPolicy;
        $map = $policy->headerMap(MauBrimoImportPolicy::COLUMNS);
        $row = $policy->normalizeRow($this->row(), $map, 1);
        $identity = $policy->identity($row);
        $this->assertMatchesRegularExpression('/^MAUBRIMO:[a-f0-9]{64}$/', $identity);
        $this->assertSame($identity, $policy->identity(array_merge($row, ['jumlah_cifno' => 999, 'jumlah_rek_fin' => 999])));
        $this->assertSame($identity, $policy->identity(array_merge($row, ['rgdesc' => "  kanwil\xC2\xA0 MALANG  ", 'mbdesc' => '  kc madiun '])));
        foreach (array_diff(MauBrimoImportPolicy::COLUMNS, ['jumlah_cifno', 'jumlah_rek_fin']) as $dimension) {
            $this->assertNotSame($identity, $policy->identity(array_merge($row, [$dimension => (string) $row[$dimension].'-different'])));
        }
    }

    public function test_all_fifteen_source_columns_must_be_selected(): void
    {
        $policy = new MauBrimoImportPolicy;
        $headers = MauBrimoImportPolicy::COLUMNS;
        $policy->assertSelectedColumns($headers, range(0, 14));
        $this->expectException(ValidationException::class);
        $policy->assertSelectedColumns($headers, array_values(array_diff(range(0, 14), [6])));
    }

    public function test_nested_selected_column_input_is_rejected_as_validation_instead_of_a_type_error(): void
    {
        $this->expectException(ValidationException::class);
        (new MauBrimoImportPolicy)->assertSelectedColumns(MauBrimoImportPolicy::COLUMNS, array_merge(range(0, 14), [['outside']]));
    }

    private function row(array $overrides = []): array
    {
        return array_values(array_replace([
            'tahun' => '2026', 'periode' => '2026-10', 'posisi' => '24/10/2026',
            'region' => 'R', 'rgdesc' => 'KANWIL MALANG', 'mainbr' => '0045',
            'mbdesc' => 'KC MADIUN', 'branch' => '6495', 'brdesc' => 'UNIT UJI',
            'jenis' => 'MAU', 'kategori' => 'AKTIF', 'segmentasi' => 'MICRO',
            'frequency' => '0', 'jumlah_cifno' => '42', 'jumlah_rek_fin' => '84',
        ], $overrides));
    }
}
