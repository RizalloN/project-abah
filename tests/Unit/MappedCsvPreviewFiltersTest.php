<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\Concerns\ServesMappedCsvPreviewFilters;
use Illuminate\Http\Request;
use Tests\TestCase;

class MappedCsvPreviewFiltersTest extends TestCase
{
    public function test_initial_sample_stops_scanning_after_the_configured_limit(): void
    {
        $harness = $this->makeHarness($this->makeRows(1200));

        [$previewRows, $uniqueValues] = $harness->sample(100, 1000, 200);

        $this->assertCount(100, $previewRows);
        $this->assertSame(1000, $harness->iterations);
        $this->assertCount(4, $uniqueValues[0]);
        $this->assertCount(200, $uniqueValues[1]);
        $this->assertSame('ID-0001', $previewRows[0][1]);
        $this->assertSame('ID-0100', $previewRows[99][1]);
        $this->assertNotContains('ID-1200', $uniqueValues[1]);
    }

    public function test_on_demand_filter_options_scan_beyond_the_initial_sample(): void
    {
        $harness = $this->makeHarness($this->makeRows(1200));
        $request = Request::create('/preview/filter-options', 'GET', [
            'file_path' => 'virtual.csv',
            'column_index' => 1,
            'active_filters_json' => '{}',
        ]);

        $payload = $harness->previewFilterOptions($request)->getData(true);

        $this->assertSame('success', $payload['status']);
        $this->assertFalse($payload['cached']);
        $this->assertCount(1200, $payload['values']);
        $this->assertContains('ID-1200', $payload['values']);
        $this->assertSame(1200, $harness->iterations);
    }

    public function test_filtered_preview_returns_only_matching_source_rows(): void
    {
        $harness = $this->makeHarness($this->makeRows(1200));
        $request = Request::create('/preview/filtered-rows', 'GET', [
            'file_path' => 'virtual.csv',
            'limit' => 10,
            'active_filters_json' => json_encode(['0' => ['KC Madiun']]),
        ]);

        $payload = $harness->previewFilteredRows($request)->getData(true);

        $this->assertSame('success', $payload['status']);
        $this->assertCount(10, $payload['rows']);
        $this->assertTrue($payload['truncated']);
        $this->assertNull($payload['total_matched']);
        foreach ($payload['rows'] as $row) {
            $this->assertSame('KC Madiun', $row[0]);
        }
    }

    public function test_specialized_mapped_preview_routes_are_registered(): void
    {
        $routes = app('router')->getRoutes();

        foreach ([
            'import.casabrilink.filter-options',
            'import.casabrilink.filtered-rows',
            'import.cognos-ph.filter-options',
            'import.cognos-ph.filtered-rows',
            'import.cognos-recovery.filter-options',
            'import.cognos-recovery.filtered-rows',
            'import.reportph.filter-options',
            'import.reportph.filtered-rows',
        ] as $routeName) {
            $this->assertNotNull($routes->getByName($routeName), $routeName.' belum terdaftar.');
        }
    }

    private function makeRows(int $count): array
    {
        $branches = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];
        $rows = [];
        for ($index = 1; $index <= $count; $index++) {
            $rows[] = [
                $branches[($index - 1) % count($branches)],
                'ID-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            ];
        }

        return $rows;
    }

    private function makeHarness(array $rows): object
    {
        return new class($rows)
        {
            use ServesMappedCsvPreviewFilters;

            public int $iterations = 0;

            private array $rows;

            private string $namespace;

            public function __construct(array $rows)
            {
                $this->rows = $rows;
                $this->namespace = 'mapped-preview-test-'.bin2hex(random_bytes(6));
            }

            public function sample(int $rowLimit, int $scanLimit, int $uniqueLimit): array
            {
                return $this->collectMappedPreviewSample(
                    'virtual.csv',
                    ['headers' => ['branch', 'identifier']],
                    $rowLimit,
                    $scanLimit,
                    $uniqueLimit
                );
            }

            protected function resolveMappedPreviewSource(string $requestedPath, ?string $requestedDelimiter = null): array
            {
                return [$requestedPath, ['headers' => ['branch', 'identifier']]];
            }

            protected function iterateMappedPreviewRows(string $path, array $context, callable $callback): void
            {
                $this->iterations = 0;
                foreach ($this->rows as $row) {
                    $this->iterations++;
                    if ($callback($row) === false) {
                        break;
                    }
                }
            }

            protected function mappedPreviewCacheNamespace(): string
            {
                return $this->namespace;
            }

            protected function mappedPreviewLabel(): string
            {
                return 'Test Preview';
            }
        };
    }
}
