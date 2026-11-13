<?php

namespace Tests\Unit;

use App\Support\LandingMicroKsbkService;
use App\Support\LandingMicroPrewashBohService;
use App\Support\LandingMicroPrewashMbmService;
use App\Support\MicroReportNominativeService;
use App\Support\PipelinePrewashService;
use App\Support\UserBranchScope;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MicroReportNominativeServiceTest extends TestCase
{
    public function test_branch_unit_and_mbm_filters_intersect_and_never_include_other_branches(): void
    {
        foreach (['prewash', 'boh', 'mbm'] as $report) {
            $source = $this->source($report, [
                $this->row('001', ['mbm_pn' => '00123']),
                $this->row('002', ['branch' => '88', 'unit_code' => '88', 'mbm_pn' => '00456']),
                $this->row('003', ['nama_kanca' => 'KC MAGETAN', 'mbm_pn' => '00123']),
            ]);
            $filters = $this->filters($report, ['level' => 'unit', 'branch' => '45', 'unit' => '00077']);
            $page = $this->page($filters, UserBranchScope::forKey('madiun'));
            $this->assertSame(['001'], array_column($page['data'], 'id'));
            $this->assertSame('madiun', $source->scopes[0]['key']);
            $this->assertSame(0, $this->page([...$filters, 'unit' => '9999'], UserBranchScope::forKey('madiun'))['total']);
            $mbm = $this->page($this->filters($report, ['level' => 'mbm', 'branch' => '0045', 'mbm' => '123']));
            $this->assertSame(['001'], array_column($mbm['data'], 'id'));
        }
    }

    public function test_requested_branch_cannot_escape_authorized_kc(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(0);
        $this->page($this->filters('prewash', ['branch' => 'KC MAGETAN']), UserBranchScope::forKey('madiun'));
    }

    public function test_invalid_branch_is_rejected_instead_of_falling_back_to_area(): void
    {
        $this->expectException(ValidationException::class);
        $this->page($this->filters('prewash', ['branch' => 'KC TIDAK ADA']));
    }

    public function test_invalid_report_is_rejected_before_selecting_a_different_source(): void
    {
        $this->expectException(ValidationException::class);
        $this->page($this->filters('forged-report'));
    }

    public function test_exact_money_identifiers_and_source_context_survive_presentation(): void
    {
        $this->source('prewash', [$this->row('000000000000000000001', [
            'cif' => '000123456789012345', 'norek_pinjaman' => '000987654321098765',
            'pn_mantri' => '00001234', 'plafond' => '9007199254740993.29', 'plafond_real' => '1234567890123456.78',
        ])]);
        $page = $this->page($this->filters('prewash'));
        $this->assertSame('000000000000000000001', $page['data'][0]['id']);
        $this->assertSame('000123456789012345', $page['data'][0]['cif']);
        $this->assertSame('000987654321098765', $page['data'][0]['norek']);
        $this->assertSame('00001234', $page['data'][0]['pn_pemasar']);
        $this->assertSame('9007199254740993.29', $page['data'][0]['plafond']);
        $this->assertSame('9007199254740993.29', $page['summary']['plafond']);
        $this->assertSame('1234567890123456.78', $page['summary']['plafond_real']);
        $this->assertSame('2026-11-10', $page['context']['loan_period']);
        $this->assertSame('2026-11-12', $page['context']['visit_period']);
    }

    public function test_pagination_has_fifty_rows_stable_ids_and_exact_totals(): void
    {
        $this->source('prewash', array_map(fn (int $i): array => $this->row(sprintf('%04d', $i)), range(1, 103)));
        $first = $this->page($this->filters('prewash'));
        $second = $this->page($this->filters('prewash', ['page' => 2]));
        $last = $this->page($this->filters('prewash', ['page' => 3]));
        $this->assertCount(50, $first['data']);
        $this->assertCount(50, $second['data']);
        $this->assertSame(['0101', '0102', '0103'], array_column($last['data'], 'id'));
        $this->assertSame('0051', $second['data'][0]['id']);
        $this->assertSame([], array_intersect(array_column($first['data'], 'id'), array_column($second['data'], 'id')));
        $this->assertSame(103, $last['total']);
        $this->assertSame(50, $last['per_page']);
        $this->assertSame(3, $last['last_page']);
        $this->assertSame($second['data'], $this->page($this->filters('prewash', ['page' => 2]))['data']);
    }

    public function test_pipeline_metrics_keep_raw_rows_except_tl_realization_deduplication(): void
    {
        foreach (['prewash', 'boh', 'mbm'] as $report) {
            $status = $report === 'prewash' ? 'Sudah' : 'Real';
            $this->source($report, [
                $this->row('001', ['status' => $status]),
                $this->row('002', ['status' => $status]),
                $this->row('003', ['cif' => 'OTHER', 'kunjungan' => 'NOT', 'status' => 'Belum', 'plafond_real' => '0.00']),
            ]);
            $all = $this->page($this->filters($report));
            $visited = $this->page($this->filters($report, ['metric' => 'visited']));
            $this->assertSame(3, $all['total']);
            $this->assertSame('600.75', $all['summary']['plafond']);
            $this->assertSame($report === 'prewash' ? '200.50' : '100.25', $all['summary']['plafond_real']);
            $this->assertSame(2, $visited['total']);
            $this->assertSame($report === 'prewash' ? '200.50' : '100.25', $visited['summary']['plafond_real']);
            $this->assertSame(1, $this->page($this->filters($report, ['metric' => 'unvisited']))['total']);
            $real = $this->page($this->filters($report, ['metric' => 'realized']));
            $this->assertSame($report === 'prewash' ? 2 : 1, $real['total']);
            $this->assertSame($report === 'prewash' ? '200.50' : '100.25', $real['summary']['plafond_real']);
        }
    }

    public function test_tl_realization_deduplication_happens_after_unit_or_mbm_group_filter(): void
    {
        foreach (['boh', 'mbm'] as $report) {
            $this->source($report, [
                $this->row('001', ['status' => 'Real', 'mbm_pn' => '123']),
                $this->row('002', ['status' => 'Real', 'branch' => '88', 'unit_code' => '88', 'mbm_pn' => '456']),
            ]);
            $unit = $this->page($this->filters($report, ['level' => 'unit', 'unit' => '88', 'metric' => 'realized']));
            $mbm = $this->page($this->filters($report, ['level' => 'mbm', 'mbm' => '456', 'metric' => 'realized']));
            $this->assertSame(['002'], array_column($unit['data'], 'id'));
            $this->assertSame(['002'], array_column($mbm['data'], 'id'));
        }
    }

    public function test_mbm_realization_keeps_same_cif_in_different_kcs_distinct(): void
    {
        $this->source('mbm', [
            $this->row('001', ['status' => 'Real']),
            $this->row('002', ['status' => 'Real', 'nama_kanca' => 'KC MAGETAN']),
        ]);
        $this->assertSame(2, $this->page($this->filters('mbm', ['metric' => 'realized']))['total']);
    }

    public function test_ksbk_nominatives_keep_duplicate_cif_proposals_and_product_filters(): void
    {
        $rows = [];
        foreach ([['001', 'small_count', 'KSBK'], ['002', 'small_count', 'KSBK'], ['003', 'large_count', 'Non KSBK']] as [$id, $bucket, $category]) {
            $rows[] = $this->row($id, ['bo' => '45', 'cifno' => '000SAME', 'nama' => 'DEBITUR', 'plafond_pengajuan' => '200.25', 'plafond' => '100.25', 'cek_real' => 'Sudah Real', 'ksbk_non' => $category, 'product_bucket' => $bucket]);
        }
        $this->source('ksbk', $rows);
        $this->assertSame(3, $this->page($this->filters('ksbk', ['metric' => 'realized']))['total']);
        $this->assertSame(2, $this->page($this->filters('ksbk', ['metric' => 'ksbk']))['total']);
        $this->assertSame(2, $this->page($this->filters('ksbk', ['metric' => 'small']))['total']);
        $large = $this->page($this->filters('ksbk', ['metric' => 'large']));
        $this->assertSame(['003'], array_column($large['data'], 'id'));
        $this->assertSame('200.25', $large['summary']['plafond']);
        $this->assertSame('100.25', $large['summary']['plafond_real']);
    }

    public function test_ksbk_unit_scope_uses_bo_for_kc_and_branch_for_unit(): void
    {
        $rows = [];
        foreach ([['001', '45', '77'], ['002', '45', '88'], ['003', '49', '77']] as [$id, $bo, $unit]) {
            $row = $this->row($id, [
                'bo' => $bo, 'branch' => $unit, 'unit_code' => $unit, 'cifno' => '000123',
                'plafond_pengajuan' => '100.25', 'cek_real' => 'Belum Real', 'ksbk_non' => null,
            ]);
            unset($row['nama_kanca']);
            $rows[] = $row;
        }
        $source = $this->source('ksbk', $rows);
        $page = $this->page($this->filters('ksbk', ['level' => 'unit', 'unit' => '00077', 'metric' => 'unrealized']), UserBranchScope::forKey('madiun'));
        $this->assertSame(['001'], array_column($page['data'], 'id'));
        $this->assertSame('madiun', $source->scopes[0]['key']);
        $this->assertSame('0045', $page['data'][0]['branch_code']);
        $this->assertSame('77', $page['data'][0]['unit_code']);
    }

    public function test_unknown_realization_remains_visible_in_all_but_cannot_be_counted_as_verified(): void
    {
        foreach (['boh', 'mbm', 'ksbk'] as $report) {
            $this->source($report, [$this->row('001', ['plafond_real' => null, 'plafond' => null, 'cek_real' => null, 'ksbk_non' => null])]);
            $all = $this->page($this->filters($report));
            $this->assertSame(1, $all['total']);
            $this->assertNull($all['summary']['plafond_real']);
            $this->assertSame(1, $all['summary']['unverified_count']);
            try {
                $this->page($this->filters($report, ['metric' => 'realized']));
                $this->fail('Unknown realization was counted as verified for '.$report);
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('metric', $error->errors());
            }
        }
    }

    public function test_unknown_visits_cannot_be_reported_as_zero_visited_or_as_unvisited(): void
    {
        foreach (['boh', 'mbm'] as $report) {
            $this->source($report, [$this->row('001', ['jumlah_kunjungan' => null, 'kunjungan' => null])]);
            foreach (['visited', 'unvisited'] as $metric) {
                try {
                    $this->page($this->filters($report, ['metric' => $metric]));
                    $this->fail('Unknown visits were counted for '.$report.' '.$metric);
                } catch (ValidationException $error) {
                    $this->assertArrayHasKey('metric', $error->errors());
                }
            }
        }
    }

    public function test_report_incompatible_metric_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->page($this->filters('prewash', ['metric' => 'ksbk']));
    }

    private function page(array $filters, ?array $scope = null): array
    {
        return app(MicroReportNominativeService::class)->page($filters, $scope);
    }

    private function filters(string $report, array $overrides = []): array
    {
        return [...['report' => $report, 'level' => 'bo', 'metric' => 'all', 'periode' => '2026-11-10'], ...$overrides];
    }

    private function row(string $id, array $overrides = []): array
    {
        return [...[
            'uniqueid_namareport' => $id, 'nama_debt' => 'DEBITUR', 'nama_kanca' => 'KC MADIUN',
            'branch' => '77', 'unit_code' => '77', 'nama_unit' => 'UNIT CONTOH', 'cif' => 'SAME',
            'plafond' => '200.25', 'plafond_real' => '100.25', 'kunjungan' => 'DONE', 'jumlah_kunjungan' => 1,
            'status' => 'Real', 'mbm_pn' => '123', 'mbm_name' => 'PIC CONTOH',
        ], ...$overrides];
    }

    private function source(string $report, array $rows): object
    {
        $source = new class($rows)
        {
            public array $scopes = [];

            public function __construct(private array $rows) {}

            public function payload(?array $scope): array
            {
                $this->scopes[] = $scope;

                return ['available' => true, 'records' => $this->rows, 'year' => 2026, 'loan_period' => '2026-11-10', 'visit_period' => '2026-11-12'];
            }

            public function records(?array $scope): array
            {
                return $this->rows;
            }

            public function nominativeRows(string $period, ?array $scope): array
            {
                return ['meta' => $this->payload($scope), 'rows' => $this->rows];
            }
        };
        $class = match ($report) {
            'prewash' => PipelinePrewashService::class,
            'boh' => LandingMicroPrewashBohService::class,
            'mbm' => LandingMicroPrewashMbmService::class,
            'ksbk' => LandingMicroKsbkService::class,
        };
        $this->app->instance($class, $source);

        return $source;
    }
}
