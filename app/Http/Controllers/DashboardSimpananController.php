<?php

namespace App\Http\Controllers;

use App\Jobs\EnsureDashboardSimpananSnapshotJob;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Support\SimpananMultiPnSnapshotGate;
use App\Support\DashboardDanaService;
use App\Support\CrasMappingService;
use App\Support\HourlyDpkDashboardService;
use App\Support\MarketShareArea6Report;
use App\Support\ReportCacheVersion;
use App\Support\DashboardHarianSnapshotService;
use App\Support\UserBranchScope;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;
use XMLReader;

class DashboardSimpananController extends Controller
{
    private const PAYLOAD_CACHE_MINUTES = 1440;
    private const SUMMARY_CACHE_MINUTES = 1440;
    private const SUMMARY_LATEST_CACHE_MINUTES = 1440;
    private const TOP_BRANCH_CACHE_MINUTES = 1440;
    private const DIGITAL_PERFORMANCE_CACHE_MINUTES = 1440;
    private const LOAN_SNAPSHOT_TABLE = 'dashboard_pinjaman_snapshots';
    private const HARIAN_SNAPSHOT_TABLE = 'dashboard_harian_snapshots';
    private const EXTERNAL_REPORT_LINK_TABLE = 'external_report_links';
    private const MARKET_SHARE_LINK_GROUP = 'market_share';
    private const MARKET_SHARE_MAPPING_LINK_KEY = 'mapping';
    private const LANDING_SOURCE_CACHE_VERSION = 'harian_snapshot_v19';
    private const CACHE_LOCK_SECONDS = 20;
    private const SNAPSHOT_SUMMARY_TABLE = 'dashboard_simpanan_snapshots';
    private const SNAPSHOT_BRANCH_TABLE = 'dashboard_simpanan_branch_snapshots';
    private const AREA_6_BRANCH_LABELS = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];
    private const MAPPING_WORKBOOK_MAX_ROWS = 500;
    private const MAPPING_WORKBOOK_MAX_COLUMNS = 90;
    private const OFFICE_VIEWER_MAX_BYTES = 25 * 1024 * 1024;
    private array $snapshotExistsMemo = [];
    private array $snapshotPeriodMemo = [];

    private static array $hasTableMemo = [];
    private static array $hasColumnMemo = [];

    private function hasTable(string $table): bool
    {
        if (!isset(self::$hasTableMemo[$table])) {
            self::$hasTableMemo[$table] = \Illuminate\Support\Facades\Schema::hasTable($table);
        }
        return self::$hasTableMemo[$table];
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (!isset(self::$hasColumnMemo[$key])) {
            self::$hasColumnMemo[$key] = \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
        }
        return self::$hasColumnMemo[$key];
    }

    public function index(Request $request): View
    {
        $periodsService = app(\App\Support\DashboardHarianSnapshotService::class);
        $availablePeriods = $periodsService->fetchPeriods();
        
        $selectedPeriod = $request->input('periode');
        if ($selectedPeriod && !$availablePeriods->contains($selectedPeriod)) {
            $selectedPeriod = $availablePeriods->first();
        } else {
            $selectedPeriod ??= $availablePeriods->first();
        }

        $dashboard = $this->buildDashboardPayload($selectedPeriod);

        return view('dashboard', [
            'dashboard' => $dashboard,
            'periods' => $availablePeriods,
            'selectedPeriod' => $selectedPeriod,
        ]);
    }

    public function dashboardDanaIndex(Request $request): View
    {
        $service = app(DashboardDanaService::class);
        $periods = $service->fetchPeriods();
        $categories = $service->fetchCategories();
        $branches = $service->fetchBranches();
        $rkaPeriods = $service->fetchRkaPeriods();

        $selectedPeriod = $request->input('periode') ?? $periods->first();
        $selectedCategory = $request->input('kategori') ?? 'all';
        $selectedBranch = $request->input('cabang') ?? 'area6';
        $selectedRka = $request->input('rka_periode') ?? $rkaPeriods->first();

        return view('report.dashboard-dana', [
            'periods' => $periods,
            'categories' => $categories,
            'branches' => $branches,
            'rkaPeriods' => $rkaPeriods,
            'selectedPeriod' => $selectedPeriod,
            'selectedCategory' => $selectedCategory,
            'selectedBranch' => $selectedBranch,
            'selectedRka' => $selectedRka,
        ]);
    }

    public function dashboardDanaData(Request $request)
    {
        $service = app(DashboardDanaService::class);
        $period = $request->input('periode');
        $category = $request->input('kategori');
        $branch = $request->input('cabang');
        $rkaPeriod = $request->input('rka_periode');

        $data = $service->getDashboardData($period, $category, $rkaPeriod, $branch);

        return response()->json($data);
    }

    public function hourlyDpkIndex(Request $request): View
    {
        $service = app(HourlyDpkDashboardService::class);
        $filters = $service->filters();
        $selectedBranch = (string) $request->query('cabang', 'all');
        $selectedProduct = (string) $request->query('jenis', 'all');
        $selectedSegment = (string) $request->query('segmen', 'all');
        $payload = $service->payload($selectedBranch, $selectedProduct, $selectedSegment);

        return view('report.dashboard-dana-hourly-dpk', [
            'filters' => $filters,
            'payload' => $payload,
            'selectedBranch' => $payload['selectedBranch'] ?? $selectedBranch,
            'selectedProduct' => $payload['selectedProduct'] ?? $selectedProduct,
            'selectedSegment' => $payload['selectedSegment'] ?? $selectedSegment,
            'dateFormatter' => fn (?string $date): string => $service->formatDateLabel($date),
        ]);
    }

    public function hourlyDpkExportPdf(Request $request): View
    {
        $service = app(HourlyDpkDashboardService::class);
        $filters = $service->filters();
        $selectedBranch = (string) $request->query('cabang', 'all');
        $selectedSegment = (string) $request->query('segmen', 'all');
        $export = $service->exportPayload($selectedBranch, $selectedSegment);

        return view('report.dashboard-dana-hourly-dpk-pdf', [
            'filters' => $filters,
            'export' => $export,
            'selectedBranch' => $export['selectedBranch'] ?? $selectedBranch,
            'selectedSegment' => $export['selectedSegment'] ?? $selectedSegment,
            'dateFormatter' => fn (?string $date): string => $service->formatDateLabel($date),
        ]);
    }

    public function marketShareIndex(Request $request): View
    {
        $userBranchScope = UserBranchScope::current();
        $workbookUrl = $this->officeViewerUrlForPublicWorkbook(
            $request,
            'public-workbooks.market-share.token',
            'market_share',
            trim((string) config('services.market_share.workbook_url', ''))
        );

        $downloadUrl = '';
        $token = trim((string) config('services.market_share.public_token', ''));
        if ($token !== '') {
            $downloadUrl = route('public-workbooks.market-share.token', ['token' => $token]);
        }

        $showDownloadPanel = false;
        $cachePath = trim((string) config("services.market_share.cache_path", 'app/public_workbooks/market-share.xlsx'), '/\\');
        $filePath = storage_path($cachePath);
        if (is_file($filePath) && filesize($filePath) >= self::OFFICE_VIEWER_MAX_BYTES) {
            $showDownloadPanel = true;
        }

        if (!$showDownloadPanel) {
            $lowerUrl = strtolower($workbookUrl);
            if (str_contains($lowerUrl, 'sharepoint.com')
                && str_contains($lowerUrl, '/_layouts/15/doc.aspx')
                && !str_contains($lowerUrl, '/:x:/')
            ) {
                $showDownloadPanel = true;
            }
        }

        if ($userBranchScope !== null) {
            $workbookUrl = '';
            $downloadUrl = '';
            $showDownloadPanel = false;
        }

        return view('report.dashboard-dana-market-share', [
            'pageTitle' => 'Market Share',
            'pageIcon' => 'fas fa-chart-pie',
            'workbookTitle' => (string) config('services.market_share.title', 'Market Share Office 365'),
            'workbookUrl' => $workbookUrl,
            'workbookUrlIsComplete' => $workbookUrl !== '' && !str_contains($workbookUrl, '...'),
            'emptyMessage' => 'Workbook Market Share belum dikonfigurasi.',
            'warningMessage' => 'Link workbook belum terlihat lengkap. Isi `MARKET_SHARE_WORKBOOK_URL` dengan link SharePoint penuh agar workbook bisa tampil langsung.',
            'frameTitle' => 'Workbook Market Share Office 365',
            'downloadUrl' => $downloadUrl,
            'showDownloadPanel' => $showDownloadPanel,
            'nativeMarketShare' => $this->scopeMarketShareNativePayload($this->marketShareNativePayload()),
        ]);
    }

    public function marketShareMappingIndex(Request $request): View
    {
        $workbookPath = $this->freshMarketShareMappingWorkbookPath();
        $managedLink = $this->marketShareMappingManagedLink();
        $configuredWorkbookUrl = trim((string) ($managedLink['link_url'] ?? ''));
        $configuredSheetName = trim((string) ($managedLink['sheet_name'] ?? ''));
        $token = trim((string) config('services.market_share_mapping.public_token', ''));
        $googleWorkbookUrl = $this->googleSpreadsheetPreviewUrl($configuredWorkbookUrl, $configuredSheetName);
        if ($googleWorkbookUrl === '') {
            $configuredWorkbookUrl = trim((string) config('services.market_share_mapping.workbook_url', ''));
            $configuredSheetName = '';
            $googleWorkbookUrl = $this->googleSpreadsheetPreviewUrl($configuredWorkbookUrl);
        }

        $directSharePointEmbedUrl = $this->sharePointEmbedUrl($configuredWorkbookUrl);
        if ($directSharePointEmbedUrl === '') {
            $directSharePointEmbedUrl = $this->sharePointEmbedUrl(trim((string) config('services.market_share_mapping.source_url', '')));
        }

        $workbookUrl = $googleWorkbookUrl !== ''
            ? $googleWorkbookUrl
            : $this->officeViewerUrlForPublicWorkbook(
            $request,
            'public-workbooks.market-share-mapping.token',
            'market_share_mapping',
            $configuredWorkbookUrl
        );
        $excelWorkbookUrl = $workbookUrl;
        if ($googleWorkbookUrl !== '') {
            $excelWorkbookUrl = $googleWorkbookUrl;
        } elseif ($token === '') {
            $excelWorkbookUrl = $this->sharePointEmbedUrl($configuredWorkbookUrl);
            if ($excelWorkbookUrl === '') {
                $excelWorkbookUrl = $this->sharePointEmbedUrl(trim((string) config('services.market_share_mapping.source_url', '')));
            }
        }
        if ($excelWorkbookUrl === '') {
            $excelWorkbookUrl = $workbookUrl;
        }

        $downloadUrl = '';
        if ($token !== '') {
            $downloadUrl = route('public-workbooks.market-share-mapping.token', ['token' => $token]);
        }

        $showDownloadPanel = false;
        $cachePath = trim((string) config("services.market_share_mapping.cache_path", 'app/public_workbooks/market-share-mapping.xlsx'), '/\\');
        $filePath = $workbookPath ?? storage_path($cachePath);
        if (is_file($filePath) && filesize($filePath) >= self::OFFICE_VIEWER_MAX_BYTES) {
            $showDownloadPanel = true;
        }

        if (!$showDownloadPanel && $googleWorkbookUrl === '') {
            $lowerUrl = strtolower($workbookUrl);
            if (str_contains($lowerUrl, 'sharepoint.com')
                && str_contains($lowerUrl, '/_layouts/15/doc.aspx')
                && !str_contains($lowerUrl, '/:x:/')
            ) {
                $showDownloadPanel = true;
            }
        }

        if ($showDownloadPanel && $directSharePointEmbedUrl !== '') {
            $workbookUrl = $directSharePointEmbedUrl;
            $excelWorkbookUrl = $directSharePointEmbedUrl;
            $showDownloadPanel = false;
        }

        $marketShareGeography = $this->scopeMarketShareGeographyPayload(
            $this->marketShareMappingGeographyPayload($workbookPath)
        );
        $nativeWorkbook = $token !== '' || !empty($marketShareGeography['ready'])
            ? $this->marketShareMappingNativePayload($request, $workbookPath)
            : ['ready' => false];

        if (UserBranchScope::current() !== null && !empty($marketShareGeography['ready'])) {
            $workbookUrl = '';
            $excelWorkbookUrl = '';
            $downloadUrl = '';
            $showDownloadPanel = true;
            $nativeWorkbook = [
                'ready' => true,
                'summary' => ['ready' => false],
                'sheetNames' => [],
                'selectedSheet' => '',
                'columnLabels' => [],
                'columnWidths' => [],
                'rows' => [],
                'rowCount' => 0,
                'columnCount' => 0,
            ];
        }
        $excelWorkbookSheetUrls = $googleWorkbookUrl !== '' ? [] : $this->officeWorkbookSheetUrls(
            $excelWorkbookUrl,
            $nativeWorkbook['sheetNames'] ?? [],
        );
        $selectedWorkbookSheet = $this->selectedWorkbookSheet(
            trim((string) $request->query('sheet', '')),
            array_keys($excelWorkbookSheetUrls),
            (string) ($nativeWorkbook['selectedSheet'] ?? ''),
        );
        if ($selectedWorkbookSheet !== '' && isset($excelWorkbookSheetUrls[$selectedWorkbookSheet])) {
            $excelWorkbookUrl = $excelWorkbookSheetUrls[$selectedWorkbookSheet];
            $workbookUrl = $showDownloadPanel ? $workbookUrl : $excelWorkbookUrl;
        }

        return view('report.dashboard-dana-market-share', [
            'pageTitle' => 'Mapping',
            'pageIcon' => 'fas fa-map-marked-alt',
            'workbookTitle' => (string) config('services.market_share_mapping.title', 'Mapping Market Share Google Sheets'),
            'workbookUrl' => $workbookUrl,
            'workbookUrlIsComplete' => $workbookUrl !== '' && !str_contains($workbookUrl, '...'),
            'emptyMessage' => 'Workbook Mapping belum dikonfigurasi.',
            'warningMessage' => 'Link mapping belum terlihat lengkap. Isi link Google Spreadsheet penuh agar workbook bisa tampil langsung.',
            'frameTitle' => 'Workbook Mapping Market Share Google Sheets',
            'downloadUrl' => $downloadUrl,
            'showDownloadAction' => false,
            'showDownloadPanel' => $showDownloadPanel,
            'excelWorkbookUrl' => $excelWorkbookUrl,
            'excelWorkbookSheetUrls' => $excelWorkbookSheetUrls,
            'excelWorkbookSelectedSheet' => $selectedWorkbookSheet,
            'nativeWorkbook' => $nativeWorkbook,
            'marketShareGeography' => $marketShareGeography,
            'workbookProvider' => 'Google Sheets',
        ]);
    }

    public function marketShareCrasMappingIndex(Request $request): View
    {
        $payload = app(CrasMappingService::class)->payload($request->query());

        return view('report.dashboard-dana-cras-mapping', [
            'pageTitle' => 'Mapping CRAS',
            'crasMapping' => $payload,
            'crasMappingDataUrl' => route('report.dashboard-dana.market-share.mapping-cras.data'),
        ]);
    }

    public function marketShareCrasMappingData(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json(app(CrasMappingService::class)->payload($request->query()));
    }

    public function marketShareInstansiIndex(Request $request): View
    {
        $sheetName = 'DATA INSTANSI';
        $branchOptions = $this->marketShareInstansiBranchOptions();
        $userBranchScope = UserBranchScope::current();

        if ($userBranchScope !== null) {
            $branchOptions = array_intersect_key($branchOptions, [$userBranchScope['slug'] => true]);
        }

        $selectedBranch = $userBranchScope['slug'] ?? (string) $request->query('cabang', 'kc-madiun');
        if (!array_key_exists($selectedBranch, $branchOptions)) {
            $selectedBranch = 'kc-madiun';
        }

        $selectedOption = $branchOptions[$selectedBranch];
        $workbookUrl = $selectedBranch === 'area6'
            ? ''
            : $this->googleSpreadsheetSheetHtmlUrl($selectedOption['url'], $sheetName);

        return view('report.dashboard-dana-market-share', [
            'pageTitle' => 'Marketshare Instansi',
            'pageIcon' => 'fas fa-building',
            'workbookTitle' => $selectedOption['label'] . ' - Sheet ' . $sheetName,
            'workbookUrl' => $workbookUrl,
            'workbookUrlIsComplete' => $selectedBranch === 'area6' || $workbookUrl !== '',
            'emptyMessage' => 'Spreadsheet Marketshare Instansi belum bisa dimuat.',
            'warningMessage' => 'Link spreadsheet Marketshare Instansi belum valid.',
            'frameTitle' => 'Marketshare Instansi ' . $selectedOption['label'],
            'downloadUrl' => '',
            'showDownloadAction' => false,
            'showDownloadPanel' => false,
            'workbookProvider' => 'Google Sheets',
            'instansiBranchOptions' => $branchOptions,
            'selectedInstansiBranch' => $selectedBranch,
            'selectedInstansiBranchLabel' => $selectedOption['label'],
            'selectedInstansiSourceUrl' => $selectedOption['url'] ?? '',
            'selectedInstansiSheetName' => $sheetName,
            'instansiDataUrl' => route('report.dashboard-dana.market-share.instansi.data', ['cabang' => $selectedBranch]),
            'instansiNativeTable' => true,
        ]);
    }

    public function marketShareArea6Index(Request $request): View
    {
        $payload = MarketShareArea6Report::payload((string) $request->query('segmen', ''));
        $userBranchScope = UserBranchScope::current();
        if ($userBranchScope !== null) {
            $payload['title'] = 'Marketshare - ' . $userBranchScope['label'];
            $payload['subtitle'] = 'Cuplikan market share untuk ' . $userBranchScope['label'] . '.';
            $payload['rows'] = array_values(array_filter(
                $payload['rows'] ?? [],
                fn (array $row): bool => $this->normalizeToken((string) ($row['branch'] ?? '')) === $this->normalizeToken($userBranchScope['label'])
            ));
        }

        return view('report.dashboard-dana-market-share-area6', [
            'pageTitle' => $payload['title'],
            'marketShareArea6' => $payload,
        ]);
    }

    public function marketShareInstansiData(Request $request): \Illuminate\Http\JsonResponse
    {
        $sheetName = 'DATA INSTANSI';
        $branchOptions = $this->marketShareInstansiBranchOptions();
        $userBranchScope = UserBranchScope::current();
        if ($userBranchScope !== null) {
            $branchOptions = array_intersect_key($branchOptions, [$userBranchScope['slug'] => true]);
        }

        $selectedBranch = $userBranchScope['slug'] ?? (string) $request->query('cabang', 'kc-madiun');
        if (!array_key_exists($selectedBranch, $branchOptions)) {
            $selectedBranch = 'kc-madiun';
        }

        $selectedOption = $branchOptions[$selectedBranch];
        if ($selectedBranch === 'area6') {
            $cacheKey = 'marketshare_instansi_data:v2:area6';

            try {
                $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($branchOptions, $sheetName): array {
                    $columns = [];
                    $rows = [];

                    foreach ($branchOptions as $branchKey => $branchOption) {
                        if ($branchKey === 'area6') {
                            continue;
                        }

                        $branchPayload = $this->readMarketShareInstansiSheet($branchOption['url'], $branchOption['label'], $sheetName);
                        if ($columns === []) {
                            $columns = array_merge(['Cabang'], $branchPayload['columns']);
                        }

                        foreach ($branchPayload['rows'] as $row) {
                            $rows[] = array_merge([$branchOption['label']], $row);
                        }
                    }

                    return [
                        'ready' => true,
                        'message' => 'OK',
                        'branch' => 'Area 6 Konsolidasi',
                        'sheet' => $sheetName,
                        'columns' => $columns,
                        'rows' => $rows,
                        'rowCount' => count($rows),
                        'columnCount' => count($columns),
                        'fetchedAt' => now()->toDateTimeString(),
                    ];
                });

                return response()->json($payload);
            } catch (Throwable $e) {
                Log::warning('Marketshare Instansi konsolidasi gagal membaca Google Sheet.', [
                    'message' => $e->getMessage(),
                ]);

                return response()->json([
                    'ready' => false,
                    'message' => 'Data konsolidasi Area 6 belum bisa dibaca. Pastikan akses seluruh spreadsheet dibuka untuk viewer.',
                    'columns' => [],
                    'rows' => [],
                ], 502);
            }
        }

        $csvUrl = $this->googleSpreadsheetSheetCsvUrl($selectedOption['url'], $sheetName);
        if ($csvUrl === '') {
            return response()->json([
                'ready' => false,
                'message' => 'Link spreadsheet tidak valid.',
                'columns' => [],
                'rows' => [],
            ], 422);
        }

        $cacheKey = 'marketshare_instansi_data:v1:' . $selectedBranch . ':' . md5($csvUrl);

        try {
            $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($selectedOption, $sheetName): array {
                $sheetPayload = $this->readMarketShareInstansiSheet($selectedOption['url'], $selectedOption['label'], $sheetName);

                return [
                    'ready' => true,
                    'message' => 'OK',
                    'branch' => $selectedOption['label'],
                    'sheet' => $sheetName,
                    'columns' => $sheetPayload['columns'],
                    'rows' => $sheetPayload['rows'],
                    'rowCount' => count($sheetPayload['rows']),
                    'columnCount' => count($sheetPayload['columns']),
                    'fetchedAt' => now()->toDateTimeString(),
                ];
            });

            return response()->json($payload);
        } catch (Throwable $e) {
            Log::warning('Marketshare Instansi gagal membaca Google Sheet.', [
                'branch' => $selectedBranch,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'ready' => false,
                'message' => 'Data spreadsheet belum bisa dibaca. Pastikan akses sheet dibuka untuk viewer.',
                'columns' => [],
                'rows' => [],
            ], 502);
        }
    }

    /**
     * @return array<string, array{label: string, url: string}>
     */
    private function marketShareInstansiBranchOptions(): array
    {
        return [
            'area6' => [
                'label' => 'Area 6 Konsolidasi',
                'url' => '',
            ],
            'kc-madiun' => [
                'label' => 'KC Madiun',
                'url' => 'https://docs.google.com/spreadsheets/d/1_HRbgKXKy6Rv9gi56x0rpKaJEQgXbOlkeupqAa_XACo/edit?usp=sharing',
            ],
            'kc-magetan' => [
                'label' => 'KC Magetan',
                'url' => 'https://docs.google.com/spreadsheets/d/1uTvCIxznFkqbzgfJdLbCUUtUOTrURkqaiCxiJWFLbQ8/edit?usp=sharing',
            ],
            'kc-ngawi' => [
                'label' => 'KC Ngawi',
                'url' => 'https://docs.google.com/spreadsheets/d/1Xdq0tjkUuKkD5rC4Zo0RJDeHy33bCOPDp0-98489v28/edit?usp=sharing',
            ],
            'kc-ponorogo' => [
                'label' => 'KC Ponorogo',
                'url' => 'https://docs.google.com/spreadsheets/d/16bJoKksVdWUloplXOk07LDnZGPzh5inRKuSOdYTbEQA/edit?usp=sharing',
            ],
        ];
    }

    /**
     * @return array{columns: array<int, string>, rows: array<int, array<int, string|null>>}
     */
    private function readMarketShareInstansiSheet(string $url, string $branchLabel, string $sheetName): array
    {
        $csvUrl = $this->googleSpreadsheetSheetCsvUrl($url, $sheetName);
        if ($csvUrl === '') {
            throw new \RuntimeException("Link spreadsheet {$branchLabel} tidak valid.");
        }

        $response = \Illuminate\Support\Facades\Http::timeout(25)
            ->retry(1, 250)
            ->get($csvUrl);

        if (!$response->successful()) {
            throw new \RuntimeException("Google Sheet {$branchLabel} mengembalikan status " . $response->status());
        }

        $parsedRows = $this->parseCsvText((string) $response->body());
        $parsedRows = array_values(array_filter($parsedRows, function (array $row): bool {
            return collect($row)->contains(fn ($value): bool => trim((string) $value) !== '');
        }));

        $columns = array_values(array_map(
            fn ($value): string => trim((string) $value),
            $parsedRows[0] ?? []
        ));
        if ($columns === []) {
            throw new \RuntimeException("Sheet {$sheetName} {$branchLabel} kosong atau belum terbaca.");
        }

        $rows = array_slice($parsedRows, 1);
        $columnCount = count($columns);
        $rows = array_map(function (array $row) use ($columnCount): array {
            $row = array_values($row);
            if (count($row) < $columnCount) {
                $row = array_pad($row, $columnCount, '');
            }

            return array_slice($row, 0, $columnCount);
        }, $rows);

        return [
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * @param array<int, string> $sheetNames
     * @return array<string, string>
     */
    private function officeWorkbookSheetUrls(string $workbookUrl, array $sheetNames): array
    {
        if ($workbookUrl === '' || $sheetNames === []) {
            return [];
        }

        $sheetUrls = [];
        foreach ($sheetNames as $sheetName) {
            $sheetName = trim((string) $sheetName);
            if ($sheetName === '') {
                continue;
            }

            $sheetUrls[$sheetName] = $this->officeWorkbookUrlForSheet($workbookUrl, $sheetName);
        }

        return $sheetUrls;
    }

    /**
     * @param array<int, string> $sheetNames
     */
    private function selectedWorkbookSheet(string $requestedSheet, array $sheetNames, string $fallbackSheet): string
    {
        if ($sheetNames === []) {
            return '';
        }

        if ($requestedSheet !== '' && in_array($requestedSheet, $sheetNames, true)) {
            return $requestedSheet;
        }

        if ($fallbackSheet !== '' && in_array($fallbackSheet, $sheetNames, true)) {
            return $fallbackSheet;
        }

        return $sheetNames[0];
    }

    private function officeWorkbookUrlForSheet(string $workbookUrl, string $sheetName): string
    {
        $parts = parse_url($workbookUrl);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return $workbookUrl;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        $query['ActiveCell'] = "'" . str_replace("'", "''", $sheetName) . "'!A1";

        return $this->buildUrl($parts, $query);
    }

    private function officeViewerUrlForPublicWorkbook(
        Request $request,
        string $routeName,
        string $configKey,
        string $fallbackUrl
    ): string {
        $token = trim((string) config("services.{$configKey}.public_token", ''));
        $sourceUrl = trim((string) config("services.{$configKey}.source_url", ''));

        $useDirectSharePoint = $token === '';

        if ($useDirectSharePoint) {
            $sourceUrl = trim((string) config("services.{$configKey}.source_url", ''));
            $directEmbedUrl = '';
            if (str_contains($sourceUrl, '/:x:/')) {
                $directEmbedUrl = $this->sharePointEmbedUrl($sourceUrl);
            }

            if ($directEmbedUrl === '') {
                $directEmbedUrl = $this->sharePointEmbedUrl($fallbackUrl);
            }

            if ($directEmbedUrl !== '') {
                return $directEmbedUrl;
            }

            return $fallbackUrl;
        }

        $publicWorkbookPath = route($routeName, [
            'token' => $token,
        ], false);
        $publicWorkbookPath .= '?v=' . $this->publicWorkbookVersion($configKey);
        $publicOrigin = rtrim((string) config('app.url', $request->getSchemeAndHttpHost()), '/');
        $publicWorkbookUrl = $publicOrigin . $publicWorkbookPath;

        return 'https://view.officeapps.live.com/op/embed.aspx?src='
            . rawurlencode($publicWorkbookUrl)
            . '&wdAllowInteractivity=True&wdAllowTyping=False&wdDownloadButton=True';
    }

    private function publicWorkbookVersion(string $configKey): string
    {
        $cachePath = trim((string) config("services.{$configKey}.cache_path", 'app/public_workbooks/' . $configKey . '.xlsx'), '/\\');
        $filePath = storage_path($cachePath);

        if (is_file($filePath)) {
            return (string) filemtime($filePath);
        }

        return now()->format('YmdHi');
    }

    private function marketShareNativePayload(): array
    {
        $cachePath = trim((string) config('services.market_share.cache_path', 'app/public_workbooks/market-share.xlsx'), '/\\');
        $path = storage_path($cachePath);

        if (!is_file($path) || filesize($path) < 1024) {
            return ['ready' => false];
        }

        $cacheKey = 'market_share_native_payload:v2:' . md5($path . '|' . filemtime($path) . '|' . filesize($path));

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($path): array {
            try {
                $reader = IOFactory::createReaderForFile($path);
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($path);
                $simpanan = $this->marketShareSavingsPayload(
                    $spreadsheet->getSheetByName('MS Simpanan Per AH') ?? $spreadsheet->getActiveSheet()
                );
                $pinjamanSheet = $spreadsheet->getSheetByName('Series Pinjaman UMKM, Kons AH');
                $pinjaman = $pinjamanSheet ? $this->marketShareLoanPayload($pinjamanSheet) : null;

                $spreadsheet->disconnectWorksheets();

                return [
                    'ready' => true,
                    'periods' => $simpanan['periods'] ?? [],
                    'sections' => $simpanan['sections'] ?? [],
                    'branchRows' => $simpanan['branchRows'] ?? [],
                    'modes' => array_filter([
                        'simpanan' => $simpanan,
                        'pinjaman' => $pinjaman,
                    ]),
                ];
            } catch (Throwable $exception) {
                Log::warning('Market Share native payload failed.', [
                    'message' => $exception->getMessage(),
                ]);

                return ['ready' => false];
            }
        });
    }

    private function scopeMarketShareNativePayload(array $payload): array
    {
        $scope = UserBranchScope::current();
        if ($scope === null || empty($payload['ready'])) {
            return $payload;
        }

        $filterMode = function (array $mode) use ($scope): array {
            foreach (($mode['sections'] ?? []) as $key => $section) {
                $branches = array_values(array_filter(
                    $section['branches'] ?? [],
                    fn (array $row): bool => $this->normalizeToken((string) ($row['branch'] ?? '')) === $this->normalizeToken($scope['label'])
                ));
                $mode['sections'][$key]['branches'] = $branches;
                $mode['sections'][$key]['summary'] = $branches[0] ?? [];
            }

            $mode['branchRows'] = $mode['sections']['total']['branches'] ?? [];
            $mode['total_label'] = str_replace('Area 6', $scope['label'], (string) ($mode['total_label'] ?? ''));
            $mode['panel_label'] = str_replace('Per Cabang', $scope['label'], (string) ($mode['panel_label'] ?? ''));

            return $mode;
        };

        foreach (($payload['modes'] ?? []) as $key => $mode) {
            $payload['modes'][$key] = $filterMode($mode);
        }

        $simpanan = $payload['modes']['simpanan'] ?? null;
        if (is_array($simpanan)) {
            $payload['sections'] = $simpanan['sections'] ?? [];
            $payload['branchRows'] = $simpanan['branchRows'] ?? [];
        }

        return $payload;
    }

    private function marketShareMappingNativePayload(Request $request, ?string $workbookPath = null): array
    {
        $cachePath = trim((string) config('services.market_share_mapping.cache_path', 'app/public_workbooks/market-share-mapping.xlsx'), '/\\');
        $path = $workbookPath ?? storage_path($cachePath);

        if (!is_file($path) || filesize($path) < 1024) {
            return ['ready' => false];
        }

        $requestedSheet = trim((string) $request->query('sheet', ''));
        $cacheKey = 'market_share_mapping_native_workbook:v7:'
            . md5($path . '|' . filemtime($path) . '|' . filesize($path) . '|' . $requestedSheet);

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($path, $requestedSheet): array {
            try {
                $payload = $this->readMarketShareMappingWorkbookPreview($path, $requestedSheet);

                return [
                    'ready' => true,
                    'sheetNames' => $payload['sheetNames'],
                    'selectedSheet' => $payload['selectedSheet'],
                    'columnLabels' => $payload['columnLabels'],
                    'columnWidths' => $payload['columnWidths'],
                    'summary' => $payload['summary'],
                    'rows' => $this->trimTrailingEmptyWorkbookRows($payload['rows']),
                    'rowCount' => $payload['rowCount'],
                    'columnCount' => $payload['columnCount'],
                    'maxRows' => self::MAPPING_WORKBOOK_MAX_ROWS,
                    'maxColumns' => self::MAPPING_WORKBOOK_MAX_COLUMNS,
                    'truncated' => $payload['rowCount'] > self::MAPPING_WORKBOOK_MAX_ROWS
                        || $payload['columnCount'] > self::MAPPING_WORKBOOK_MAX_COLUMNS,
                    'updatedAt' => date('d M Y H:i', filemtime($path)),
                ];
            } catch (Throwable $exception) {
                Log::warning('Market Share Mapping native workbook failed.', [
                    'message' => $exception->getMessage(),
                ]);

                return ['ready' => false];
            }
        });
    }

    private function marketShareMappingGeographyPayload(?string $workbookPath = null): array
    {
        $cachePath = trim((string) config('services.market_share_mapping.cache_path', 'app/public_workbooks/market-share-mapping.xlsx'), '/\\');
        $workbookPath = $workbookPath ?? storage_path($cachePath);
        $source = (array) config('marketshare-geography.source', []);
        $geoJsonRelativePath = trim((string) ($source['geojson_path'] ?? 'data/marketshare-area6-kecamatan.geojson'), '/\\');
        $geoJsonPath = public_path($geoJsonRelativePath);

        if (!is_file($workbookPath) || filesize($workbookPath) < 1024 || !is_file($geoJsonPath)) {
            return ['ready' => false];
        }

        $unitDistricts = (array) config('marketshare-geography.unit_districts', []);
        $branchDefinitions = (array) config('marketshare-geography.branches', []);
        $cacheKey = 'market_share_mapping_geography:v2:' . md5(implode('|', [
            $workbookPath,
            (string) filemtime($workbookPath),
            (string) filesize($workbookPath),
            (string) filemtime($geoJsonPath),
            (string) filesize($geoJsonPath),
            json_encode([$unitDistricts, $branchDefinitions]),
        ]));

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use (
            $workbookPath,
            $geoJsonPath,
            $geoJsonRelativePath,
            $unitDistricts,
            $branchDefinitions,
            $source
        ): array {
            try {
                $preview = $this->readMarketShareMappingWorkbookPreview($workbookPath, 'REKAP');
                $sectorColumns = [
                    'pertanian' => ['label' => 'Pertanian', 'potential' => 6, 'existing' => 17, 'penetration' => 28],
                    'perdagangan' => ['label' => 'Perdagangan', 'potential' => 7, 'existing' => 18, 'penetration' => 29],
                    'perkebunan' => ['label' => 'Perkebunan', 'potential' => 8, 'existing' => 19, 'penetration' => 30],
                    'perikanan' => ['label' => 'Perikanan', 'potential' => 9, 'existing' => 20, 'penetration' => 31],
                    'peternakan' => ['label' => 'Peternakan', 'potential' => 10, 'existing' => 21, 'penetration' => 32],
                    'jasa' => ['label' => 'Jasa', 'potential' => 11, 'existing' => 22, 'penetration' => 33],
                    'consumer_briguna' => ['label' => 'Consumer Briguna', 'potential' => 12, 'existing' => 23, 'penetration' => 34],
                    'industri' => ['label' => 'Industri', 'potential' => 13, 'existing' => 24, 'penetration' => 35],
                    'pertambangan' => ['label' => 'Pertambangan', 'potential' => 14, 'existing' => 25, 'penetration' => 36],
                    'lainnya' => ['label' => 'Lainnya', 'potential' => 15, 'existing' => 26, 'penetration' => 37],
                ];

                $units = [];
                $branchTotals = [];
                foreach ($preview['rows'] as $row) {
                    $values = $this->marketShareGeographyRowValues((array) $row);
                    $branchCell = trim((string) ($values[0] ?? ''));
                    $unitCell = trim((string) ($values[1] ?? ''));
                    if ($unitCell === '') {
                        continue;
                    }

                    if (str_starts_with(strtoupper($unitCell), 'TOTAL -')) {
                        $branchKey = $this->marketShareGeographyBranchKey($unitCell);
                        if ($branchKey !== null) {
                            $branchTotals[$branchKey] = [
                                'potential' => $this->marketShareGeographyNumber($values[16] ?? null),
                                'existing' => $this->marketShareGeographyNumber($values[27] ?? null),
                                'penetration' => $this->marketShareGeographyNumber($values[38] ?? null),
                                'source_row' => (int) ($row['number'] ?? 0),
                            ];
                        }
                        continue;
                    }

                    if (!preg_match('/^\s*(\d+)\s*--\s*(.+)$/u', $unitCell, $unitMatch)) {
                        continue;
                    }

                    $branchKey = $this->marketShareGeographyBranchKey($branchCell);
                    if ($branchKey === null || !isset($branchDefinitions[$branchKey])) {
                        continue;
                    }

                    $unitCode = str_pad((string) $unitMatch[1], 5, '0', STR_PAD_LEFT);
                    $valuesBySector = [];
                    foreach ($sectorColumns as $sectorKey => $definition) {
                        $valuesBySector[$sectorKey] = [
                            'potential' => $this->marketShareGeographyNumber($values[$definition['potential']] ?? null),
                            'existing' => $this->marketShareGeographyNumber($values[$definition['existing']] ?? null),
                            'penetration' => $this->marketShareGeographyNumber($values[$definition['penetration']] ?? null),
                        ];
                    }
                    $valuesBySector['total'] = [
                        'potential' => $this->marketShareGeographyNumber($values[16] ?? null),
                        'existing' => $this->marketShareGeographyNumber($values[27] ?? null),
                        'penetration' => $this->marketShareGeographyNumber($values[38] ?? null),
                    ];

                    $unitName = trim((string) $unitMatch[2]);
                    $unitName = preg_replace('/^UNIT\s+/i', '', $unitName) ?? $unitName;
                    $unitName = preg_replace('/\s+(MADIUN|MAGETAN|NGAWI|PONOROGO)$/i', '', $unitName) ?? $unitName;
                    $districtCodes = array_values(array_unique(array_filter(array_map(
                        static fn ($code): string => trim((string) $code),
                        (array) ($unitDistricts[$unitCode] ?? [])
                    ))));

                    $units[] = [
                        'code' => $unitCode,
                        'name' => trim($unitName),
                        'label' => $unitCode . ' - ' . trim($unitName),
                        'branch' => $branchKey,
                        'branch_label' => (string) ($branchDefinitions[$branchKey]['label'] ?? ucfirst($branchKey)),
                        'district_codes' => $districtCodes,
                        'source_row' => (int) ($row['number'] ?? 0),
                        'values' => $valuesBySector,
                    ];
                }

                $units = collect($units)
                    ->sortBy(fn (array $unit): string => $unit['branch'] . '|' . $unit['name'])
                    ->values()
                    ->all();
                $unitsByBranch = collect($units)->groupBy('branch');

                $branches = [];
                foreach ($branchDefinitions as $branchKey => $definition) {
                    $branchUnits = $unitsByBranch->get($branchKey, collect());
                    $calculatedPotential = (float) $branchUnits->sum(fn (array $unit): float => (float) ($unit['values']['total']['potential'] ?? 0));
                    $calculatedExisting = (float) $branchUnits->sum(fn (array $unit): float => (float) ($unit['values']['total']['existing'] ?? 0));
                    $totals = $branchTotals[$branchKey] ?? [
                        'potential' => $calculatedPotential,
                        'existing' => $calculatedExisting,
                        'penetration' => $calculatedPotential > 0 ? ($calculatedExisting / $calculatedPotential) * 100 : 0,
                        'source_row' => null,
                    ];

                    $branches[] = [
                        'key' => $branchKey,
                        'label' => (string) ($definition['label'] ?? ucfirst((string) $branchKey)),
                        'regency_codes' => array_values((array) ($definition['regency_codes'] ?? [])),
                        'unit_count' => $branchUnits->count(),
                        'totals' => $totals,
                    ];
                }

                $geoJson = json_decode((string) file_get_contents($geoJsonPath), true, 512, JSON_THROW_ON_ERROR);
                $geoDistrictCodes = collect($geoJson['features'] ?? [])
                    ->map(fn (array $feature): string => trim((string) ($feature['properties']['KDCPUM'] ?? '')))
                    ->filter()
                    ->unique()
                    ->values();
                $configuredDistrictCodes = collect($unitDistricts)
                    ->flatten()
                    ->map(fn ($code): string => trim((string) $code))
                    ->filter()
                    ->unique()
                    ->values();
                $mappedUnits = collect($units)->filter(fn (array $unit): bool => $unit['district_codes'] !== [])->count();
                $areaPotential = (float) collect($branches)->sum(fn (array $branch): float => (float) ($branch['totals']['potential'] ?? 0));
                $areaExisting = (float) collect($branches)->sum(fn (array $branch): float => (float) ($branch['totals']['existing'] ?? 0));

                return [
                    'ready' => $units !== [] && $geoDistrictCodes->count() > 0,
                    'title' => 'Peta Potensi & Penetrasi Area 6',
                    'subtitle' => 'Visualisasi wilayah layanan Unit Kerja berbasis polygon kecamatan.',
                    'sheet' => 'REKAP',
                    'updated_at' => date('d M Y H:i', filemtime($workbookPath)),
                    'geojson' => $geoJson,
                    'geojson_url' => asset($geoJsonRelativePath),
                    'geojson_sha256' => hash_file('sha256', $geoJsonPath),
                    'source' => [
                        'label' => (string) ($source['label'] ?? 'Badan Informasi Geospasial'),
                        'url' => (string) ($source['url'] ?? ''),
                    ],
                    'sectors' => array_merge(
                        [['key' => 'total', 'label' => 'Total seluruh sektor']],
                        collect($sectorColumns)
                            ->map(fn (array $definition, string $key): array => ['key' => $key, 'label' => $definition['label']])
                            ->values()
                            ->all()
                    ),
                    'metrics' => [
                        ['key' => 'potential', 'label' => 'Potensi Nasabah'],
                        ['key' => 'existing', 'label' => 'Existing Nasabah'],
                        ['key' => 'penetration', 'label' => 'Penetrasi'],
                    ],
                    'branches' => $branches,
                    'units' => $units,
                    'area_totals' => [
                        'potential' => $areaPotential,
                        'existing' => $areaExisting,
                        'penetration' => $areaPotential > 0 ? ($areaExisting / $areaPotential) * 100 : 0,
                    ],
                    'coverage' => [
                        'unit_count' => count($units),
                        'mapped_unit_count' => $mappedUnits,
                        'district_count' => $geoDistrictCodes->count(),
                        'mapped_district_count' => $geoDistrictCodes->intersect($configuredDistrictCodes)->count(),
                        'unmapped_unit_codes' => collect($units)
                            ->filter(fn (array $unit): bool => $unit['district_codes'] === [])
                            ->pluck('code')
                            ->values()
                            ->all(),
                        'unknown_district_codes' => $configuredDistrictCodes->diff($geoDistrictCodes)->values()->all(),
                    ],
                ];
            } catch (Throwable $exception) {
                Log::warning('Market Share geography payload failed.', [
                    'message' => $exception->getMessage(),
                ]);

                return ['ready' => false];
            }
        });
    }

    private function scopeMarketShareGeographyPayload(array $payload): array
    {
        $scope = UserBranchScope::current();
        if ($scope === null || empty($payload['ready'])) {
            return $payload;
        }

        $payload['branches'] = array_values(array_filter(
            $payload['branches'] ?? [],
            fn (array $branch): bool => (string) ($branch['key'] ?? '') === $scope['key']
        ));
        $payload['units'] = array_values(array_filter(
            $payload['units'] ?? [],
            fn (array $unit): bool => (string) ($unit['branch'] ?? '') === $scope['key']
        ));

        $districtCodes = collect($payload['units'])
            ->pluck('district_codes')
            ->flatten()
            ->map(fn ($code): string => trim((string) $code))
            ->filter()
            ->unique()
            ->values();
        $payload['geojson']['features'] = array_values(array_filter(
            $payload['geojson']['features'] ?? [],
            fn (array $feature): bool => $districtCodes->contains(trim((string) data_get($feature, 'properties.KDCPUM', '')))
        ));

        $branchTotals = (array) data_get($payload, 'branches.0.totals', []);
        $payload['area_totals'] = [
            'potential' => (float) ($branchTotals['potential'] ?? 0),
            'existing' => (float) ($branchTotals['existing'] ?? 0),
            'penetration' => (float) ($branchTotals['penetration'] ?? 0),
        ];
        $payload['coverage'] = array_merge($payload['coverage'] ?? [], [
            'unit_count' => count($payload['units']),
            'mapped_unit_count' => count(array_filter($payload['units'], fn (array $unit): bool => ($unit['district_codes'] ?? []) !== [])),
            'district_count' => count($payload['geojson']['features']),
            'mapped_district_count' => count($payload['geojson']['features']),
        ]);
        $payload['title'] = 'Peta Potensi & Penetrasi ' . $scope['label'];
        $payload['subtitle'] = 'Visualisasi wilayah layanan unit kerja ' . $scope['label'] . '.';

        return $payload;
    }

    private function freshMarketShareMappingWorkbookPath(): ?string
    {
        $cachePath = trim((string) config('services.market_share_mapping.cache_path', 'app/cache/market-share-mapping.xlsx'), '/\\');
        $path = storage_path($cachePath);
        $fallbackCachePath = trim((string) config('services.market_share_mapping.fallback_cache_path', 'app/public_workbooks/market-share-mapping.xlsx'), '/\\');
        $fallbackPath = storage_path($fallbackCachePath);
        $cacheMinutes = max(0, (int) config('services.market_share_mapping.cache_minutes', 15));
        $useFallback = $cachePath === 'app/cache/market-share-mapping.xlsx'
            || $fallbackCachePath !== 'app/public_workbooks/market-share-mapping.xlsx';
        $availableWorkbookPath = function () use ($path, $fallbackPath, $useFallback): ?string {
            if ($this->isUsableMarketShareMappingWorkbook($path)) {
                return $path;
            }

            return $useFallback && $this->isUsableMarketShareMappingWorkbook($fallbackPath) ? $fallbackPath : null;
        };

        if ($cacheMinutes > 0
            && $this->isUsableMarketShareMappingWorkbook($path)
            && filemtime($path) >= now()->subMinutes($cacheMinutes)->getTimestamp()) {
            return $path;
        }

        $availablePath = $availableWorkbookPath();
        if ($cacheMinutes > 0 && $availablePath !== null) {
            $this->deferMarketShareMappingWorkbookRefresh($path);

            return $availablePath;
        }

        $this->refreshMarketShareMappingWorkbook($path);

        return $availableWorkbookPath();
    }

    private function deferMarketShareMappingWorkbookRefresh(string $path): void
    {
        $pendingKey = 'market_share_mapping_workbook_refresh:pending';
        if (!Cache::add($pendingKey, now()->toIso8601String(), now()->addMinutes(10))) {
            return;
        }

        app()->terminating(function () use ($path, $pendingKey) {
            try {
                $this->refreshMarketShareMappingWorkbook($path);
            } finally {
                Cache::forget($pendingKey);
            }
        });
    }

    private function refreshMarketShareMappingWorkbook(string $path): void
    {
        $lock = Cache::lock('market_share_mapping_workbook_refresh', 60);
        if (!$lock->get()) {
            return;
        }

        try {
            $sourceUrl = $this->marketShareMappingExportUrl();
            if ($sourceUrl === '') {
                return;
            }

            $timeoutSeconds = max(15, (int) config('services.market_share_mapping.timeout_seconds', 30));
            $response = Http::timeout($timeoutSeconds)
                ->retry(2, 500)
                ->withHeaders(['User-Agent' => 'ASIXDashboardMarketShare/1.0'])
                ->get($sourceUrl);
            $body = $response->body();

            if (!$response->successful() || !$this->looksLikeXlsxWorkbook($body)) {
                throw new \RuntimeException('Google Sheets tidak mengembalikan workbook XLSX yang valid.');
            }

            File::ensureDirectoryExists(dirname($path));
            $temporaryPath = $path . '.refresh-' . bin2hex(random_bytes(6));
            File::put($temporaryPath, $body);

            if (!$this->isUsableMarketShareMappingWorkbook($temporaryPath)) {
                File::delete($temporaryPath);
                throw new \RuntimeException('Workbook Google Sheets hasil unduh tidak valid.');
            }

            File::move($temporaryPath, $path, true);
        } catch (Throwable $exception) {
            Log::warning('Market Share Mapping workbook refresh failed.', [
                'message' => $exception->getMessage(),
            ]);
        } finally {
            optional($lock)->release();
        }
    }

    private function marketShareMappingExportUrl(): string
    {
        $sourceUrl = $this->extractIframeSource(trim((string) config('services.market_share_mapping.source_url', '')));
        if ($sourceUrl === '') {
            $sourceUrl = $this->extractIframeSource(trim((string) config('services.market_share_mapping.workbook_url', '')));
        }

        $parts = parse_url($sourceUrl);
        if ($parts === false || strtolower((string) ($parts['host'] ?? '')) !== 'docs.google.com') {
            return $sourceUrl;
        }

        if (!preg_match('~/spreadsheets/d/([^/]+)~', (string) ($parts['path'] ?? ''), $matches)) {
            return '';
        }

        return 'https://docs.google.com/spreadsheets/d/'
            . rawurlencode((string) $matches[1])
            . '/export?format=xlsx';
    }

    private function isUsableMarketShareMappingWorkbook(string $path): bool
    {
        if (!is_file($path) || filesize($path) < 1024) {
            return false;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 4);
        fclose($handle);

        return $this->looksLikeXlsxWorkbook((string) $header, false);
    }

    private function looksLikeXlsxWorkbook(string $content, bool $checkSize = true): bool
    {
        if ($checkSize && strlen($content) < 1024) {
            return false;
        }

        return str_starts_with($content, "PK\x03\x04")
            || str_starts_with($content, "PK\x05\x06")
            || str_starts_with($content, "PK\x07\x08");
    }

    private function marketShareGeographyRowValues(array $row): array
    {
        return array_map(
            static fn (array $cell): string => !empty($cell['skip']) ? '' : trim((string) ($cell['value'] ?? '')),
            (array) ($row['cells'] ?? [])
        );
    }

    private function marketShareGeographyBranchKey(string $value): ?string
    {
        $normalized = strtoupper(trim($value));
        foreach (array_keys((array) config('marketshare-geography.branches', [])) as $branchKey) {
            if (str_contains($normalized, strtoupper((string) $branchKey))) {
                return (string) $branchKey;
            }
        }

        return null;
    }

    private function marketShareGeographyNumber(mixed $value): float
    {
        $normalized = preg_replace('/\s+/', '', trim((string) $value)) ?? '';
        $normalized = str_replace('%', '', $normalized);
        if ($normalized === '' || $normalized === '-') {
            return 0.0;
        }

        if (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $normalized)) {
            $normalized = str_replace(',', '', $normalized);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $normalized)) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function readMarketShareMappingWorkbookPreview(string $path, string $requestedSheet): array
    {
        $workbook = $this->readXlsxWorkbookSheets($path);
        $sheetNames = array_column($workbook, 'name');
        $selectedSheet = in_array($requestedSheet, $sheetNames, true)
            ? $requestedSheet
            : $this->defaultMarketShareMappingSheet($sheetNames);
        $sheetEntry = collect($workbook)->firstWhere('name', $selectedSheet);

        if (!$sheetEntry || empty($sheetEntry['path'])) {
            throw new \RuntimeException('Sheet mapping workbook tidak ditemukan.');
        }

        $preview = $this->readXlsxSheetPreview($path, (string) $sheetEntry['path']);
        $sharedStrings = $this->readXlsxSharedStrings($path, $preview['sharedStringIndexes']);
        $styleDefinitions = $this->readXlsxStyleDefinitions($path);
        [$mergeStarts, $mergeCovered] = $this->xlsxMergeMaps(
            $preview['mergedCells'],
            self::MAPPING_WORKBOOK_MAX_ROWS,
            $preview['renderColumns']
        );

        $rows = [];
        foreach ($preview['rows'] as $rowNumber => $rowCells) {
            $cells = [];
            for ($column = 1; $column <= $preview['renderColumns']; $column++) {
                $mergeKey = $rowNumber . ':' . $column;
                if (isset($mergeCovered[$mergeKey])) {
                    $cells[] = ['skip' => true];
                    continue;
                }

                $cell = $rowCells[$column] ?? ['type' => 'blank', 'value' => ''];
                $styleId = (int) ($cell['style'] ?? 0);
                $display = $this->formatXlsxPreviewCell($cell, $sharedStrings, $styleDefinitions);
                $merge = $mergeStarts[$mergeKey] ?? ['rowspan' => 1, 'colspan' => 1];

                $cells[] = [
                    'value' => $display,
                    'style' => $styleDefinitions['cellStyles'][$styleId] ?? '',
                    'rowspan' => $merge['rowspan'],
                    'colspan' => $merge['colspan'],
                    'empty' => trim($display) === '',
                ];
            }

            $rows[] = [
                'number' => $rowNumber,
                'style' => $this->xlsxRowStyle($preview['rowHeights'][$rowNumber] ?? null),
                'cells' => $cells,
            ];
        }

        $columnLabels = [];
        $columnWidths = [];
        for ($column = 1; $column <= $preview['renderColumns']; $column++) {
            $columnLabels[] = Coordinate::stringFromColumnIndex($column);
            $columnWidths[] = $this->xlsxColumnStyle($preview['columnWidths'][$column] ?? null);
        }

        $summary = $this->mappingWorkbookSummaryPayload($rows, $selectedSheet);
        if (!empty($summary['ready'])) {
            $summary['charts'] = $this->readXlsxDashboardCharts($path, (string) $sheetEntry['path']);
        }

        return [
            'sheetNames' => $sheetNames,
            'selectedSheet' => $selectedSheet,
            'columnLabels' => $columnLabels,
            'columnWidths' => $columnWidths,
            'summary' => $summary,
            'rows' => $rows,
            'rowCount' => $preview['rowCount'],
            'columnCount' => $preview['columnCount'],
        ];
    }

    private function mappingWorkbookSummaryPayload(array $rows, string $selectedSheet): array
    {
        if (strtoupper(trim($selectedSheet)) !== 'DASHBOARD') {
            return ['ready' => false];
        }

        $cellValue = function (int $rowNumber, int $columnNumber) use ($rows): string {
            foreach ($rows as $row) {
                if ((int) ($row['number'] ?? 0) !== $rowNumber) {
                    continue;
                }

                $cell = $row['cells'][$columnNumber - 1] ?? null;
                if (!is_array($cell) || !empty($cell['skip'])) {
                    return '';
                }

                return trim((string) ($cell['value'] ?? ''));
            }

            return '';
        };

        $headlineMetrics = [];
        foreach ([2, 4, 6, 8, 10, 12] as $columnNumber) {
            $label = $cellValue(8, $columnNumber);
            $value = $cellValue(9, $columnNumber);
            if ($label === '' && $value === '') {
                continue;
            }

            $headlineMetrics[] = [
                'label' => $label !== '' ? $label : 'KPI',
                'value' => $value !== '' ? $value : '-',
                'icon' => $this->mappingSummaryMetricIcon($label),
            ];
        }

        $totalMetrics = [];
        foreach ([1, 4, 7, 10, 13] as $columnNumber) {
            $label = $cellValue(12, $columnNumber);
            $value = $cellValue(13, $columnNumber);
            if ($label === '' && $value === '') {
                continue;
            }

            $totalMetrics[] = [
                'label' => $label !== '' ? $label : 'Total',
                'value' => $value !== '' ? $value : '-',
                'icon' => $this->mappingSummaryMetricIcon($label),
            ];
        }

        $sectors = [];
        foreach ([17, 22] as $rowNumber) {
            foreach ([1, 4, 7, 10, 13] as $columnNumber) {
                $label = $cellValue($rowNumber, $columnNumber + 1);
                $conversion = $cellValue($rowNumber + 2, $columnNumber);
                if ($label === '' || $conversion === '') {
                    continue;
                }

                $sectors[] = [
                    'label' => $label,
                    'conversion' => $conversion,
                    'icon' => $this->mappingSummarySectorIcon($label),
                    'tone' => $this->mappingSummarySectorTone($label),
                ];
            }
        }
        $sectorDetails = $this->mappingWorkbookSectorDetailsPayload($rows, $headlineMetrics, $sectors);

        return [
            'ready' => $headlineMetrics !== [] || $totalMetrics !== [] || $sectors !== [],
            'title' => $cellValue(1, 1) ?: 'Dashboard Sektor Potensi & Debitur',
            'subtitle' => $cellValue(2, 1) ?: 'Ringkasan sektor utama dari workbook Mapping Market Share.',
            'selectedSector' => $cellValue(5, 3) ?: '-',
            'headlineMetrics' => $headlineMetrics,
            'totalMetrics' => $totalMetrics,
            'sectors' => $sectors,
            'sectorDetails' => $sectorDetails,
        ];
    }

    private function mappingWorkbookSectorDetailsPayload(array $rows, array $headlineMetrics, array $sectors): array
    {
        $headerRow = null;
        $headerRowNumber = 0;
        foreach ($rows as $row) {
            if ((int) ($row['number'] ?? 0) < 30) {
                continue;
            }

            $values = array_map(function (array $cell): string {
                return !empty($cell['skip']) ? '' : trim((string) ($cell['value'] ?? ''));
            }, $row['cells'] ?? []);

            $normalizedValues = array_map(fn (string $value): string => $this->mappingSummaryDataKey($value), $values);
            if (
                in_array('SEKTOR', $normalizedValues, true)
                && in_array('POTENSI', $normalizedValues, true)
                && in_array('DEBITUR', $normalizedValues, true)
            ) {
                $headerRow = $values;
                $headerRowNumber = (int) ($row['number'] ?? 0);
                break;
            }
        }

        if ($headerRow === null) {
            return [];
        }

        $headerIndexes = [];
        foreach ($headerRow as $index => $label) {
            $key = $this->mappingSummaryDataKey($label);
            if ($key !== '' && !isset($headerIndexes[$key])) {
                $headerIndexes[$key] = $index;
            }
        }

        $sectorIndex = $headerIndexes['SEKTOR'] ?? null;
        if ($sectorIndex === null) {
            return [];
        }

        $sectorConversions = [];
        foreach ($sectors as $sector) {
            $label = (string) ($sector['label'] ?? '');
            $key = $this->mappingSummaryDataKey($label);
            if ($key !== '') {
                $sectorConversions[$key] = (string) ($sector['conversion'] ?? '');
            }
        }

        $details = [];
        foreach ($rows as $row) {
            if ((int) ($row['number'] ?? 0) <= $headerRowNumber) {
                continue;
            }

            $cells = $row['cells'] ?? [];
            $sectorLabel = isset($cells[$sectorIndex]) && is_array($cells[$sectorIndex])
                ? trim((string) ($cells[$sectorIndex]['value'] ?? ''))
                : '';
            $sectorKey = $this->mappingSummaryDataKey($sectorLabel);

            if ($sectorKey === '' || str_contains($sectorKey, 'TOTAL')) {
                continue;
            }

            $metrics = [];
            foreach ($headlineMetrics as $metric) {
                $metricLabel = (string) ($metric['label'] ?? '');
                $metricKey = $this->mappingSummaryDataKey($metricLabel);
                $sourceKey = $this->mappingWorkbookSectorDetailSourceKey($metricLabel);
                $sourceIndex = $headerIndexes[$sourceKey] ?? null;

                if ($metricKey === '' || $sourceIndex === null) {
                    continue;
                }

                $cell = $cells[$sourceIndex] ?? null;
                if (!is_array($cell) || !empty($cell['skip'])) {
                    continue;
                }

                $value = trim((string) ($cell['value'] ?? ''));
                if ($value !== '') {
                    $metrics[$metricKey] = $value;
                }
            }

            if ($metrics === []) {
                continue;
            }

            $details[$sectorKey] = [
                'label' => $sectorLabel,
                'conversion' => $sectorConversions[$sectorKey] ?? ($metrics['KONVERSI'] ?? ''),
                'metrics' => $metrics,
            ];
        }

        return $details;
    }

    private function mappingWorkbookSectorDetailSourceKey(string $label): string
    {
        $normalized = $this->mappingSummaryDataKey($label);

        return match (true) {
            $normalized === 'OSLANCAR' => 'LANCAR',
            $normalized === 'OSNPL' => 'NPL',
            default => $normalized,
        };
    }

    private function mappingSummaryDataKey(string $value): string
    {
        return (string) preg_replace('/[^A-Z0-9]+/', '', strtoupper(trim($value)));
    }

    private function mappingSummaryMetricIcon(string $label): string
    {
        $normalized = strtoupper($label);

        return match (true) {
            str_contains($normalized, 'POTENSI') => 'fas fa-seedling',
            str_contains($normalized, 'DEBITUR') => 'fas fa-users',
            str_contains($normalized, 'KONVERSI') => 'fas fa-bolt',
            str_contains($normalized, 'NPL') => 'fas fa-shield-alt',
            str_contains($normalized, 'OS') => 'fas fa-chart-line',
            default => 'fas fa-chart-pie',
        };
    }

    private function mappingSummarySectorIcon(string $label): string
    {
        $normalized = strtoupper($label);

        return match (true) {
            str_contains($normalized, 'PERTANIAN') => 'fas fa-seedling',
            str_contains($normalized, 'PERDAGANGAN') => 'fas fa-store',
            str_contains($normalized, 'PERKEBUNAN') => 'fas fa-leaf',
            str_contains($normalized, 'PERIKANAN') => 'fas fa-water',
            str_contains($normalized, 'PETERNAKAN') => 'fas fa-warehouse',
            str_contains($normalized, 'JASA') => 'fas fa-handshake',
            str_contains($normalized, 'INDUSTRI') => 'fas fa-industry',
            str_contains($normalized, 'PERTAMBANGAN') => 'fas fa-gem',
            default => 'fas fa-cubes',
        };
    }

    private function mappingSummarySectorTone(string $label): string
    {
        $tones = ['emerald', 'blue', 'green', 'cyan', 'orange', 'violet', 'slate', 'amber', 'gray'];
        $index = abs(crc32(strtoupper($label))) % count($tones);

        return $tones[$index];
    }

    private function readXlsxDashboardCharts(string $path, string $sheetEntry): array
    {
        $drawingPath = $this->xlsxWorksheetDrawingPath($path, $sheetEntry);
        if ($drawingPath === '') {
            return [];
        }

        $chartRelationships = $this->xlsxDrawingChartRelationships($path, $drawingPath);
        if ($chartRelationships === []) {
            return [];
        }

        $anchors = $this->xlsxDrawingChartAnchors($path, $drawingPath, $chartRelationships);
        $charts = [];
        foreach ($anchors as $anchor) {
            $chart = $this->readXlsxChartPayload($path, $anchor['path']);
            if (empty($chart['ready'])) {
                continue;
            }

            $chart['fromRow'] = $anchor['fromRow'];
            $chart['fromColumn'] = $anchor['fromColumn'];
            $charts[] = $chart;
        }

        usort($charts, function (array $left, array $right): int {
            return [$left['fromRow'], $left['fromColumn']] <=> [$right['fromRow'], $right['fromColumn']];
        });

        return array_values(array_filter(array_slice($charts, 0, 3), function (array $chart): bool {
            $title = strtoupper((string) ($chart['title'] ?? ''));

            return str_contains($title, 'POTENSI')
                || str_contains($title, 'SHARE')
                || str_contains($title, 'NPL');
        }));
    }

    private function xlsxWorksheetDrawingPath(string $path, string $sheetEntry): string
    {
        $relsPath = dirname($sheetEntry) . '/_rels/' . basename($sheetEntry) . '.rels';
        $reader = $this->xlsxXmlReader($path, $relsPath, false);
        if (!$reader) {
            return '';
        }

        $drawingPath = '';
        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'Relationship') {
                continue;
            }

            $type = (string) $reader->getAttribute('Type');
            if (!str_contains($type, '/drawing')) {
                continue;
            }

            $drawingPath = $this->resolveXlsxRelativePath($sheetEntry, (string) $reader->getAttribute('Target'));
            break;
        }
        $reader->close();

        return $drawingPath;
    }

    private function xlsxDrawingChartRelationships(string $path, string $drawingPath): array
    {
        $relsPath = dirname($drawingPath) . '/_rels/' . basename($drawingPath) . '.rels';
        $reader = $this->xlsxXmlReader($path, $relsPath, false);
        if (!$reader) {
            return [];
        }

        $relationships = [];
        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'Relationship') {
                continue;
            }

            $type = (string) $reader->getAttribute('Type');
            if (!str_contains($type, '/chart')) {
                continue;
            }

            $id = (string) $reader->getAttribute('Id');
            $target = (string) $reader->getAttribute('Target');
            if ($id === '' || $target === '') {
                continue;
            }

            $relationships[$id] = $this->resolveXlsxRelativePath($drawingPath, $target);
        }
        $reader->close();

        return $relationships;
    }

    private function xlsxDrawingChartAnchors(string $path, string $drawingPath, array $chartRelationships): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $drawingXml = $zip->getFromName($drawingPath);
        $zip->close();

        if (!is_string($drawingXml) || trim($drawingXml) === '') {
            return [];
        }

        $dom = new \DOMDocument();
        if (!@$dom->loadXML($drawingXml)) {
            return [];
        }

        $xdrNamespace = 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing';
        $chartNamespace = 'http://schemas.openxmlformats.org/drawingml/2006/chart';
        $relationshipNamespace = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('xdr', $xdrNamespace);
        $xpath->registerNamespace('c', $chartNamespace);
        $anchors = [];

        foreach ($xpath->query('//xdr:twoCellAnchor') ?: [] as $anchor) {
            if (!$anchor instanceof \DOMElement) {
                continue;
            }

            $chartNode = $xpath->query('.//c:chart', $anchor)->item(0);
            if (!$chartNode instanceof \DOMElement) {
                continue;
            }

            $relationId = $chartNode->getAttributeNS($relationshipNamespace, 'id');
            if ($relationId === '' || !isset($chartRelationships[$relationId])) {
                continue;
            }

            $fromColumnNode = $xpath->query('./xdr:from/xdr:col', $anchor)->item(0);
            $fromRowNode = $xpath->query('./xdr:from/xdr:row', $anchor)->item(0);
            $fromColumn = $fromColumnNode ? ((int) $fromColumnNode->textContent) + 1 : 1;
            $fromRow = $fromRowNode ? ((int) $fromRowNode->textContent) + 1 : 1;

            $anchors[] = [
                'path' => $chartRelationships[$relationId],
                'fromColumn' => $fromColumn,
                'fromRow' => $fromRow,
            ];
        }

        return $anchors;
    }

    private function readXlsxChartPayload(string $path, string $chartEntry): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return ['ready' => false];
        }

        $chartXml = $zip->getFromName($chartEntry);
        $zip->close();

        if (!is_string($chartXml) || trim($chartXml) === '') {
            return ['ready' => false];
        }

        $dom = new \DOMDocument();
        if (!@$dom->loadXML($chartXml)) {
            return ['ready' => false];
        }

        $chartNamespace = 'http://schemas.openxmlformats.org/drawingml/2006/chart';
        $drawingNamespace = 'http://schemas.openxmlformats.org/drawingml/2006/main';
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('c', $chartNamespace);
        $xpath->registerNamespace('a', $drawingNamespace);

        $titleNode = $xpath->query('//c:chart/c:title//a:t')->item(0);
        $title = $titleNode ? trim($titleNode->textContent) : 'Chart';
        $type = $this->xlsxChartTypeFromDom($xpath);
        $seriesNodes = $xpath->query('//c:ser') ?: [];

        $categories = [];
        $series = [];
        $maxValue = 0.0;
        foreach ($seriesNodes as $seriesNode) {
            if (!$seriesNode instanceof \DOMElement) {
                continue;
            }

            $seriesName = $this->xlsxChartFirstDomValue($xpath, $seriesNode, './/c:tx//c:v') ?: 'Series';
            $seriesCategories = $this->xlsxChartDomPointValues($xpath, $seriesNode, './/c:cat//c:pt', false);
            $values = $this->xlsxChartDomPointValues($xpath, $seriesNode, './/c:val//c:pt', true);

            if ($categories === [] && $seriesCategories !== []) {
                $categories = array_map(fn (array $point): string => (string) $point['value'], $seriesCategories);
            }

            $numericValues = array_map(fn (array $point): float => (float) $point['value'], $values);
            if ($numericValues !== []) {
                $maxValue = max($maxValue, max(array_map('abs', $numericValues)));
            }

            $series[] = [
                'name' => $seriesName,
                'values' => $numericValues,
            ];
        }

        $normalizedTitle = strtoupper($title);
        if (str_contains($normalizedTitle, 'NPL') && str_contains($normalizedTitle, 'RATIO')) {
            foreach ($series as $chartSeries) {
                if (!str_contains(strtoupper((string) ($chartSeries['name'] ?? '')), 'NPL RATIO')) {
                    continue;
                }

                $series = [$chartSeries];
                $seriesValues = array_map('abs', array_map('floatval', $chartSeries['values'] ?? []));
                $maxValue = $seriesValues !== [] ? max($seriesValues) : $maxValue;
                break;
            }
        }

        return [
            'ready' => $series !== [] && $categories !== [],
            'title' => $title,
            'type' => $type,
            'categories' => $categories,
            'series' => $series,
            'maxValue' => $maxValue > 0 ? $maxValue : 1.0,
        ];
    }

    private function xlsxChartTypeFromDom(\DOMXPath $xpath): string
    {
        if (($xpath->query('//c:doughnutChart')->length ?? 0) > 0 || ($xpath->query('//c:pieChart')->length ?? 0) > 0) {
            return 'doughnut';
        }

        $barChart = $xpath->query('//c:barChart')->item(0);
        if ($barChart instanceof \DOMElement) {
            $barDir = $xpath->query('./c:barDir', $barChart)->item(0);
            $direction = $barDir instanceof \DOMElement ? $barDir->getAttribute('val') : '';

            return $direction === 'bar' ? 'bar-horizontal' : 'bar-column';
        }

        return 'bar-column';
    }

    private function xlsxChartFirstDomValue(\DOMXPath $xpath, \DOMNode $node, string $query): string
    {
        $valueNode = $xpath->query($query, $node)->item(0);

        return $valueNode ? trim($valueNode->textContent) : '';
    }

    private function xlsxChartDomPointValues(\DOMXPath $xpath, \DOMNode $node, string $query, bool $numeric): array
    {
        $points = $xpath->query($query, $node) ?: [];
        $values = [];

        foreach ($points as $point) {
            if (!$point instanceof \DOMElement) {
                continue;
            }

            $valueNode = $xpath->query('./c:v', $point)->item(0);
            if (!$valueNode) {
                continue;
            }

            $value = trim($valueNode->textContent);
            $values[] = [
                'index' => (int) ($point->getAttribute('idx') !== '' ? $point->getAttribute('idx') : count($values)),
                'value' => $numeric && is_numeric($value) ? (float) $value : $value,
            ];
        }

        usort($values, fn (array $left, array $right): int => $left['index'] <=> $right['index']);

        return $values;
    }

    private function readXlsxWorkbookSheets(string $path): array
    {
        $relationships = [];
        $rels = $this->xlsxXmlReader($path, 'xl/_rels/workbook.xml.rels');
        while ($rels->read()) {
            if ($rels->nodeType !== XMLReader::ELEMENT || $rels->localName !== 'Relationship') {
                continue;
            }

            $id = (string) $rels->getAttribute('Id');
            $target = (string) $rels->getAttribute('Target');
            if ($id === '' || $target === '') {
                continue;
            }

            $relationships[$id] = $this->resolveXlsxWorkbookRelationshipTarget($target);
        }
        $rels->close();

        $sheets = [];
        $workbook = $this->xlsxXmlReader($path, 'xl/workbook.xml');
        while ($workbook->read()) {
            if ($workbook->nodeType !== XMLReader::ELEMENT || $workbook->localName !== 'sheet') {
                continue;
            }

            $name = trim((string) $workbook->getAttribute('name'));
            $relationId = (string) $workbook->getAttributeNs('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            if ($name === '' || !isset($relationships[$relationId])) {
                continue;
            }

            $sheets[] = [
                'name' => $name,
                'path' => $this->normalizeXlsxEntryPath($relationships[$relationId]),
            ];
        }
        $workbook->close();

        return $sheets;
    }

    private function readXlsxSheetPreview(string $path, string $sheetEntry): array
    {
        $rows = [];
        $columnWidths = [];
        $rowHeights = [];
        $mergedCells = [];
        $sharedStringIndexes = [];
        $rowCount = 1;
        $columnCount = 1;
        $reader = $this->xlsxXmlReader($path, $sheetEntry);

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }

            if ($reader->localName === 'dimension') {
                [$rowCount, $columnCount] = $this->parseXlsxDimension((string) $reader->getAttribute('ref'));
                continue;
            }

            if ($reader->localName === 'col') {
                $min = max(1, (int) $reader->getAttribute('min'));
                $max = max($min, (int) $reader->getAttribute('max'));
                $width = (float) $reader->getAttribute('width');
                if ($width > 0) {
                    for ($column = $min; $column <= min($max, self::MAPPING_WORKBOOK_MAX_COLUMNS); $column++) {
                        $columnWidths[$column] = $width;
                    }
                }
                continue;
            }

            if ($reader->localName === 'row') {
                $rowNumber = (int) $reader->getAttribute('r');
                $height = (float) $reader->getAttribute('ht');
                if ($rowNumber > 0 && $height > 0) {
                    $rowHeights[$rowNumber] = $height;
                }
                continue;
            }

            if ($reader->localName === 'mergeCell') {
                $ref = trim((string) $reader->getAttribute('ref'));
                if ($ref !== '') {
                    $mergedCells[] = $ref;
                }
                continue;
            }

            if ($reader->localName !== 'c') {
                continue;
            }

            $cellReference = (string) $reader->getAttribute('r');
            [$columnIndex, $rowNumber] = $this->parseXlsxCellReference($cellReference);
            if ($columnIndex < 1 || $rowNumber < 1) {
                continue;
            }

            $rowCount = max($rowCount, $rowNumber);
            $columnCount = max($columnCount, $columnIndex);

            if ($rowNumber > self::MAPPING_WORKBOOK_MAX_ROWS || $columnIndex > self::MAPPING_WORKBOOK_MAX_COLUMNS) {
                continue;
            }

            $type = (string) $reader->getAttribute('t');
            $value = $this->readXlsxCellRawValue($reader);
            $rows[$rowNumber][$columnIndex] = [
                'type' => $type,
                'value' => $value,
                'style' => (int) ((string) $reader->getAttribute('s') ?: 0),
            ];

            if ($type === 's' && is_numeric($value)) {
                $sharedStringIndexes[(int) $value] = true;
            }
        }
        $reader->close();

        ksort($rows);

        return [
            'rows' => $rows,
            'sharedStringIndexes' => array_keys($sharedStringIndexes),
            'columnWidths' => $columnWidths,
            'rowHeights' => $rowHeights,
            'mergedCells' => $mergedCells,
            'rowCount' => $rowCount,
            'columnCount' => $columnCount,
            'renderColumns' => min(max(1, $columnCount), self::MAPPING_WORKBOOK_MAX_COLUMNS),
        ];
    }

    private function readXlsxCellRawValue(XMLReader $reader): string
    {
        if ($reader->isEmptyElement) {
            return '';
        }

        $depth = $reader->depth;
        $value = '';

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->depth === $depth && $reader->localName === 'c') {
                break;
            }

            if ($reader->nodeType === XMLReader::ELEMENT && in_array($reader->localName, ['v', 't'], true)) {
                $value .= (string) $reader->readString();
            }
        }

        return trim($value);
    }

    private function readXlsxSharedStrings(string $path, array $neededIndexes): array
    {
        if ($neededIndexes === []) {
            return [];
        }

        $needed = array_fill_keys(array_map('intval', $neededIndexes), true);
        $strings = [];
        $index = -1;
        $reader = $this->xlsxXmlReader($path, 'xl/sharedStrings.xml', false);
        if (!$reader) {
            return [];
        }

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si') {
                continue;
            }

            $index++;
            if (!isset($needed[$index])) {
                continue;
            }

            $strings[$index] = $this->readXlsxSharedStringItem($reader);
            if (count($strings) === count($needed)) {
                break;
            }
        }
        $reader->close();

        return $strings;
    }

    private function readXlsxSharedStringItem(XMLReader $reader): string
    {
        if ($reader->isEmptyElement) {
            return '';
        }

        $depth = $reader->depth;
        $value = '';
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->depth === $depth && $reader->localName === 'si') {
                break;
            }

            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 't') {
                $value .= (string) $reader->readString();
            }
        }

        return trim($value);
    }

    private function formatXlsxPreviewCell(array $cell, array $sharedStrings, array $styleDefinitions = []): string
    {
        $type = (string) ($cell['type'] ?? '');
        $value = (string) ($cell['value'] ?? '');

        if ($value === '') {
            return '';
        }

        if ($type === 's') {
            return trim((string) ($sharedStrings[(int) $value] ?? ''));
        }

        if ($type === 'inlineStr' || $type === 'str') {
            return trim($value);
        }

        $styleId = (int) ($cell['style'] ?? 0);
        $formatCode = (string) ($styleDefinitions['numberFormats'][$styleId] ?? '');
        if (is_numeric($value) && $formatCode !== '') {
            return $this->formatXlsxNumberByFormat((float) $value, $formatCode);
        }

        return $this->formatMappingWorkbookScalar($value);
    }

    private function readXlsxStyleDefinitions(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return ['cellStyles' => [], 'numberFormats' => []];
        }

        $stylesXml = $zip->getFromName('xl/styles.xml');
        $zip->close();

        if (!is_string($stylesXml) || trim($stylesXml) === '') {
            return ['cellStyles' => [], 'numberFormats' => []];
        }

        $xml = @simplexml_load_string($stylesXml);
        if (!$xml) {
            return ['cellStyles' => [], 'numberFormats' => []];
        }

        $themeColors = $this->readXlsxThemeColors($path);
        $customFormats = [];
        foreach (($xml->numFmts->numFmt ?? []) as $numFmt) {
            $customFormats[(int) $numFmt['numFmtId']] = html_entity_decode((string) $numFmt['formatCode'], ENT_QUOTES | ENT_XML1);
        }

        $fonts = [];
        foreach (($xml->fonts->font ?? []) as $font) {
            $fontCss = [];
            if (isset($font->b)) {
                $fontCss[] = 'font-weight:700';
            }
            if (isset($font->i)) {
                $fontCss[] = 'font-style:italic';
            }
            if (isset($font->sz['val'])) {
                $fontCss[] = 'font-size:' . round(((float) $font->sz['val']) * 1.333, 2) . 'px';
            }
            if (isset($font->name['val'])) {
                $fontCss[] = 'font-family:"' . str_replace('"', '', (string) $font->name['val']) . '", Arial, sans-serif';
            }
            $color = $this->xlsxColorToCss($font->color ?? null, $themeColors);
            if ($color !== null) {
                $fontCss[] = 'color:' . $color;
            }

            $fonts[] = $fontCss;
        }

        $fills = [];
        foreach (($xml->fills->fill ?? []) as $fill) {
            $fillCss = [];
            $patternFill = $fill->patternFill ?? null;
            $patternType = $patternFill ? (string) $patternFill['patternType'] : '';
            if ($patternFill && !in_array($patternType, ['', 'none', 'gray125'], true)) {
                $color = $this->xlsxColorToCss($patternFill->fgColor ?? null, $themeColors)
                    ?? $this->xlsxColorToCss($patternFill->bgColor ?? null, $themeColors);
                if ($color !== null) {
                    $fillCss[] = 'background-color:' . $color;
                }
            }

            $fills[] = $fillCss;
        }

        $borders = [];
        foreach (($xml->borders->border ?? []) as $border) {
            $borderCss = [];
            foreach (['left', 'right', 'top', 'bottom'] as $side) {
                if (!isset($border->{$side})) {
                    continue;
                }

                $style = (string) $border->{$side}['style'];
                if ($style === '') {
                    continue;
                }

                $width = in_array($style, ['medium', 'thick', 'double'], true) ? '2px' : '1px';
                $color = $this->xlsxColorToCss($border->{$side}->color ?? null, $themeColors) ?? '#d7dee8';
                $borderCss[] = "border-{$side}:{$width} solid {$color}";
            }

            $borders[] = $borderCss;
        }

        $cellStyles = [];
        $numberFormats = [];
        $styleIndex = 0;
        foreach (($xml->cellXfs->xf ?? []) as $xf) {
            $fontId = (int) ($xf['fontId'] ?? 0);
            $fillId = (int) ($xf['fillId'] ?? 0);
            $borderId = (int) ($xf['borderId'] ?? 0);
            $numFmtId = (int) ($xf['numFmtId'] ?? 0);

            $css = array_merge(
                $fills[$fillId] ?? [],
                $fonts[$fontId] ?? [],
                $borders[$borderId] ?? []
            );

            if (isset($xf->alignment)) {
                $horizontal = (string) ($xf->alignment['horizontal'] ?? '');
                $vertical = (string) ($xf->alignment['vertical'] ?? '');
                if (in_array($horizontal, ['left', 'center', 'right'], true)) {
                    $css[] = 'text-align:' . $horizontal;
                } elseif ($horizontal === 'centerContinuous') {
                    $css[] = 'text-align:center';
                }
                if ($vertical !== '') {
                    $css[] = 'vertical-align:' . ($vertical === 'center' ? 'middle' : $vertical);
                }
                if ((string) ($xf->alignment['wrapText'] ?? '') === '1') {
                    $css[] = 'white-space:normal';
                }
            }

            $cellStyles[$styleIndex] = $css === [] ? '' : implode(';', array_unique($css)) . ';';
            $numberFormats[$styleIndex] = $customFormats[$numFmtId]
                ?? $this->builtinXlsxNumberFormat($numFmtId);
            $styleIndex++;
        }

        return [
            'cellStyles' => $cellStyles,
            'numberFormats' => $numberFormats,
        ];
    }

    private function readXlsxThemeColors(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $themeXml = $zip->getFromName('xl/theme/theme1.xml');
        $zip->close();

        if (!is_string($themeXml) || trim($themeXml) === '') {
            return [];
        }

        $xml = @simplexml_load_string($themeXml);
        if (!$xml) {
            return [];
        }

        $namespace = 'http://schemas.openxmlformats.org/drawingml/2006/main';
        $theme = $xml->children($namespace);
        $scheme = $theme->themeElements->clrScheme ?? null;
        if (!$scheme) {
            return [];
        }

        $themeSlots = [
            'lt1',
            'dk1',
            'lt2',
            'dk2',
            'accent1',
            'accent2',
            'accent3',
            'accent4',
            'accent5',
            'accent6',
            'hlink',
            'folHlink',
        ];

        $colors = [];
        foreach ($themeSlots as $index => $slot) {
            if (!isset($scheme->{$slot})) {
                continue;
            }

            $children = $scheme->{$slot}->children($namespace);
            $hex = '';
            if (isset($children->srgbClr)) {
                $hex = (string) $children->srgbClr['val'];
            } elseif (isset($children->sysClr)) {
                $hex = (string) ($children->sysClr['lastClr'] ?: $children->sysClr['val']);
            }

            $hex = strtoupper($hex);
            if (preg_match('/^[0-9A-F]{6}$/', $hex) === 1) {
                $colors[$index] = '#' . $hex;
            }
        }

        return $colors;
    }

    private function formatXlsxNumberByFormat(float $number, string $formatCode): string
    {
        $format = trim(str_replace('\\', '', $formatCode));

        if (str_contains($format, '%')) {
            $decimals = $this->xlsxFormatDecimalPlaces($format);
            return number_format($number * 100, $decimals, '.', ',') . '%';
        }

        $scale = 1;
        $suffix = '';
        if (str_contains($format, ',,')) {
            $scale = 1000000;
            $suffix = str_contains($format, 'M') ? ' M' : '';
        } elseif (preg_match('/0(?:\.0+)?,(?:[^\d#]|$)/', $format) === 1) {
            $scale = 1000;
        }

        $decimals = $this->xlsxFormatDecimalPlaces($format);
        $scaled = $number / $scale;

        if ($decimals === 0 && abs($scaled - round($scaled)) > 0.0000001 && str_contains($format, '.0')) {
            $decimals = 1;
        }

        return number_format($scaled, $decimals, '.', ',') . $suffix;
    }

    private function xlsxFormatDecimalPlaces(string $format): int
    {
        if (preg_match('/0\.([0]+)/', $format, $matches) !== 1) {
            return 0;
        }

        return strlen($matches[1]);
    }

    private function builtinXlsxNumberFormat(int $numFmtId): string
    {
        return match ($numFmtId) {
            3 => '#,##0',
            4 => '#,##0.00',
            9 => '0%',
            10 => '0.00%',
            11 => '0.00E+00',
            12 => '# ?/?',
            13 => '# ??/??',
            37 => '#,##0;(#,##0)',
            38 => '#,##0;[Red](#,##0)',
            39 => '#,##0.00;(#,##0.00)',
            40 => '#,##0.00;[Red](#,##0.00)',
            43 => '_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)',
            default => '',
        };
    }

    private function xlsxColorToCss(mixed $colorNode, array $themeColors = []): ?string
    {
        if ($colorNode === null) {
            return null;
        }

        $rgb = strtoupper((string) ($colorNode['rgb'] ?? ''));
        if ($rgb !== '') {
            $rgb = strlen($rgb) === 8 ? substr($rgb, 2) : $rgb;
            return preg_match('/^[0-9A-F]{6}$/', $rgb) === 1 ? '#' . $rgb : null;
        }

        $indexed = (string) ($colorNode['indexed'] ?? '');
        if ($indexed !== '') {
            return $this->xlsxIndexedColor((int) $indexed);
        }

        $theme = (string) ($colorNode['theme'] ?? '');
        if ($theme !== '') {
            return $this->xlsxThemeColor((int) $theme, (float) ($colorNode['tint'] ?? 0), $themeColors);
        }

        return null;
    }

    private function xlsxThemeColor(int $theme, float $tint = 0.0, array $themeColors = []): string
    {
        $colors = [
            0 => '#FFFFFF',
            1 => '#000000',
            2 => '#EEECE1',
            3 => '#1F497D',
            4 => '#4F81BD',
            5 => '#C0504D',
            6 => '#9BBB59',
            7 => '#8064A2',
            8 => '#4BACC6',
            9 => '#F79646',
        ];

        return $this->applyXlsxTint($themeColors[$theme] ?? $colors[$theme] ?? '#000000', $tint);
    }

    private function xlsxIndexedColor(int $indexed): ?string
    {
        $colors = [
            0 => '#000000',
            1 => '#FFFFFF',
            2 => '#FF0000',
            3 => '#00FF00',
            4 => '#0000FF',
            5 => '#FFFF00',
            6 => '#FF00FF',
            7 => '#00FFFF',
            8 => '#000000',
            9 => '#FFFFFF',
            64 => null,
            65 => null,
        ];

        return $colors[$indexed] ?? null;
    }

    private function applyXlsxTint(string $hex, float $tint): string
    {
        if (abs($tint) < 0.0001 || preg_match('/^#[0-9A-Fa-f]{6}$/', $hex) !== 1) {
            return $hex;
        }

        $red = hexdec(substr($hex, 1, 2));
        $green = hexdec(substr($hex, 3, 2));
        $blue = hexdec(substr($hex, 5, 2));

        foreach (['red', 'green', 'blue'] as $channel) {
            $value = $$channel;
            $$channel = $tint < 0
                ? (int) round($value * (1 + $tint))
                : (int) round($value + (255 - $value) * $tint);
            $$channel = max(0, min(255, $$channel));
        }

        return sprintf('#%02X%02X%02X', $red, $green, $blue);
    }

    private function xlsxMergeMaps(array $mergedCells, int $maxRows, int $maxColumns): array
    {
        $starts = [];
        $covered = [];

        foreach ($mergedCells as $ref) {
            if (!str_contains($ref, ':')) {
                continue;
            }

            [$start, $end] = explode(':', $ref, 2);
            [$startColumn, $startRow] = $this->parseXlsxCellReference($start);
            [$endColumn, $endRow] = $this->parseXlsxCellReference($end);
            if ($startColumn < 1 || $startRow < 1 || $endColumn < $startColumn || $endRow < $startRow) {
                continue;
            }

            if ($startRow > $maxRows || $startColumn > $maxColumns) {
                continue;
            }

            $endRow = min($endRow, $maxRows);
            $endColumn = min($endColumn, $maxColumns);
            $starts[$startRow . ':' . $startColumn] = [
                'rowspan' => max(1, $endRow - $startRow + 1),
                'colspan' => max(1, $endColumn - $startColumn + 1),
            ];

            for ($row = $startRow; $row <= $endRow; $row++) {
                for ($column = $startColumn; $column <= $endColumn; $column++) {
                    if ($row === $startRow && $column === $startColumn) {
                        continue;
                    }

                    $covered[$row . ':' . $column] = true;
                }
            }
        }

        return [$starts, $covered];
    }

    private function xlsxColumnStyle(?float $width): string
    {
        if ($width === null || $width <= 0) {
            return 'width:96px;min-width:96px;';
        }

        $pixels = max(42, min(240, (int) round($width * 7 + 5)));

        return "width:{$pixels}px;min-width:{$pixels}px;";
    }

    private function xlsxRowStyle(?float $height): string
    {
        if ($height === null || $height <= 0) {
            return '';
        }

        $pixels = max(18, min(96, (int) round($height * 1.333)));

        return "height:{$pixels}px;";
    }

    private function parseXlsxDimension(string $dimension): array
    {
        if ($dimension === '') {
            return [1, 1];
        }

        $lastCell = str_contains($dimension, ':') ? substr(strrchr($dimension, ':'), 1) : $dimension;
        [$column, $row] = $this->parseXlsxCellReference((string) $lastCell);

        return [max(1, $row), max(1, $column)];
    }

    private function parseXlsxCellReference(string $reference): array
    {
        if (preg_match('/^([A-Z]+)(\d+)$/i', $reference, $matches) !== 1) {
            return [0, 0];
        }

        return [
            Coordinate::columnIndexFromString(strtoupper($matches[1])),
            (int) $matches[2],
        ];
    }

    private function xlsxXmlReader(string $path, string $entry, bool $throw = true): ?XMLReader
    {
        $reader = new XMLReader();
        $uri = 'zip://' . str_replace('\\', '/', $path) . '#' . $this->normalizeXlsxEntryPath($entry);

        if (@$reader->open($uri)) {
            return $reader;
        }

        if ($throw) {
            throw new \RuntimeException("Gagal membaca entry XLSX: {$entry}");
        }

        return null;
    }

    private function resolveXlsxWorkbookRelationshipTarget(string $target): string
    {
        $target = str_replace('\\', '/', trim($target));
        if ($target === '') {
            return '';
        }

        if (str_starts_with($target, '/')) {
            return $this->normalizeXlsxEntryPath($target);
        }

        if (str_starts_with($target, 'xl/')) {
            return $this->normalizeXlsxEntryPath($target);
        }

        while (str_starts_with($target, '../')) {
            $target = substr($target, 3);
        }

        return $this->normalizeXlsxEntryPath('xl/' . $target);
    }

    private function resolveXlsxRelativePath(string $baseEntry, string $target): string
    {
        $target = str_replace('\\', '/', trim($target));
        if ($target === '') {
            return '';
        }

        if (str_starts_with($target, '/')) {
            return $this->normalizeXlsxEntryPath($target);
        }

        $baseDirectory = dirname($this->normalizeXlsxEntryPath($baseEntry));
        $parts = explode('/', $this->normalizeXlsxEntryPath($baseDirectory . '/' . $target));
        $resolved = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($resolved);
                continue;
            }

            $resolved[] = $part;
        }

        return implode('/', $resolved);
    }

    private function normalizeXlsxEntryPath(string $entry): string
    {
        $entry = str_replace('\\', '/', $entry);
        $entry = preg_replace('#/+#', '/', $entry) ?? $entry;

        return ltrim($entry, '/');
    }

    private function defaultMarketShareMappingSheet(array $sheetNames): string
    {
        $preferredNames = ['DASHBOARD', 'MAPING', 'MAPPING'];
        foreach ($preferredNames as $preferredName) {
            foreach ($sheetNames as $sheetName) {
                if (strtoupper(trim((string) $sheetName)) === $preferredName) {
                    return (string) $sheetName;
                }
            }
        }

        return (string) ($sheetNames[0] ?? '');
    }

    private function mappingWorkbookCellDisplay($cell): string
    {
        $rawValue = $cell->getValue();

        if (is_string($rawValue) && str_starts_with($rawValue, '=')) {
            $rawValue = method_exists($cell, 'getOldCalculatedValue')
                ? $cell->getOldCalculatedValue()
                : null;

            return $this->formatMappingWorkbookScalar($rawValue);
        }

        try {
            return trim((string) $cell->getFormattedValue());
        } catch (Throwable) {
            return $this->formatMappingWorkbookScalar($rawValue);
        }
    }

    private function formatMappingWorkbookScalar(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if (is_numeric($value)) {
            $number = (float) $value;

            if (abs($number) >= 1000) {
                return number_format($number, 0, '.', ',');
            }

            if (abs($number - round($number)) < 0.0000001) {
                return number_format($number, 0, ',', '.');
            }

            return rtrim(rtrim(number_format($number, 4, ',', '.'), '0'), ',');
        }

        return trim((string) $value);
    }

    private function trimTrailingEmptyWorkbookRows(array $rows): array
    {
        while ($rows !== []) {
            $last = end($rows);
            $cells = $last['cells'] ?? [];
            $hasValue = false;

            foreach ($cells as $cell) {
                if (!empty($cell['skip'])) {
                    continue;
                }

                $value = is_array($cell) ? (string) ($cell['value'] ?? '') : (string) $cell;
                if (trim($value) !== '') {
                    $hasValue = true;
                    break;
                }
            }

            if ($hasValue) {
                break;
            }

            array_pop($rows);
        }

        return array_values($rows);
    }

    private function marketShareSavingsPayload($sheet): array
    {
        $blocks = [
            ['key' => 'total', 'label' => 'Total Simpanan', 'icon' => 'fas fa-landmark', 'row' => 7, 'start' => 3, 'end' => 6],
            ['key' => 'giro', 'label' => 'Giro', 'icon' => 'fas fa-money-check-alt', 'row' => 16, 'start' => 12, 'end' => 15],
            ['key' => 'tabungan', 'label' => 'Tabungan', 'icon' => 'fas fa-wallet', 'row' => 25, 'start' => 21, 'end' => 24],
            ['key' => 'deposito', 'label' => 'Deposito', 'icon' => 'fas fa-piggy-bank', 'row' => 34, 'start' => 30, 'end' => 33],
            ['key' => 'casa', 'label' => 'CASA', 'icon' => 'fas fa-layer-group', 'row' => 43, 'start' => 39, 'end' => 42],
        ];

        $periods = [
            'yoy' => $this->marketShareDateLabel($sheet->getCell('D2')->getValue()),
            'ytd' => $this->marketShareDateLabel($sheet->getCell('E2')->getValue()),
            'current' => $this->marketShareDateLabel($sheet->getCell('F2')->getValue()),
        ];

        $sections = [];
        foreach ($blocks as $block) {
            $summary = $this->marketShareRowPayload($sheet, $block['row']);
            $branches = [];
            for ($row = $block['start']; $row <= $block['end']; $row++) {
                $branches[] = $this->marketShareRowPayload($sheet, $row);
            }

            $sections[$block['key']] = [
                'key' => $block['key'],
                'label' => $block['label'],
                'icon' => $block['icon'],
                'summary' => $summary,
                'branches' => $branches,
            ];
        }

        $sections = $this->marketShareAttachComposition($sections);

        return [
            'key' => 'simpanan',
            'label' => 'Simpanan',
            'total_label' => 'Total Simpanan Area 6',
            'panel_label' => 'Market Share Simpanan Per Cabang',
            'periods' => $periods,
            'sections' => $sections,
            'branchRows' => $sections['total']['branches'] ?? [],
            'components' => ['giro', 'tabungan', 'deposito', 'casa'],
        ];
    }

    private function marketShareLoanPayload($sheet): array
    {
        $blocks = [
            ['key' => 'total', 'label' => 'Total Pinjaman', 'icon' => 'fas fa-hand-holding-usd', 'row' => 8, 'start' => 4, 'end' => 7],
            ['key' => 'umkm', 'label' => 'Pinjaman UMKM', 'icon' => 'fas fa-store', 'row' => 17, 'start' => 13, 'end' => 16],
            ['key' => 'konsumer', 'label' => 'Pinjaman Konsumer', 'icon' => 'fas fa-user-tie', 'row' => 43, 'start' => 39, 'end' => 42],
            ['key' => 'kpr', 'label' => 'Pinjaman KPR', 'icon' => 'fas fa-home', 'row' => 26, 'start' => 22, 'end' => 25],
            ['key' => 'briguna', 'label' => 'Pinjaman BRIGUNA', 'icon' => 'fas fa-briefcase', 'row' => 35, 'start' => 31, 'end' => 34],
        ];

        $periods = [
            'yoy' => $this->marketShareDateLabel($sheet->getCell('D3')->getValue()),
            'ytd' => $this->marketShareDateLabel($sheet->getCell('X3')->getValue()),
            'current' => $this->marketShareDateLabel($sheet->getCell('AB3')->getValue()),
        ];

        $sections = [];
        foreach ($blocks as $block) {
            $summary = $this->marketShareLoanRowPayload($sheet, $block['row']);
            $branches = [];
            for ($row = $block['start']; $row <= $block['end']; $row++) {
                $branches[] = $this->marketShareLoanRowPayload($sheet, $row);
            }

            $sections[$block['key']] = [
                'key' => $block['key'],
                'label' => $block['label'],
                'icon' => $block['icon'],
                'summary' => $summary,
                'branches' => $branches,
            ];
        }

        $sections = $this->marketShareAttachComposition($sections);

        return [
            'key' => 'pinjaman',
            'label' => 'Pinjaman',
            'total_label' => 'Total Pinjaman Area 6',
            'panel_label' => 'Market Share Pinjaman Per Cabang',
            'periods' => $periods,
            'sections' => $sections,
            'branchRows' => $sections['total']['branches'] ?? [],
            'components' => ['umkm', 'konsumer', 'kpr', 'briguna'],
        ];
    }

    private function marketShareAttachComposition(array $sections): array
    {
        $totalCurrent = (float) ($sections['total']['summary']['bri_current'] ?? 0);
        foreach ($sections as $key => $section) {
            $sections[$key]['composition_pct'] = $key === 'total'
                ? 1.0
                : $this->marketShareSafeDivide((float) $section['summary']['bri_current'], $totalCurrent);
        }

        return $sections;
    }

    private function marketShareRowPayload($sheet, int $row): array
    {
        return [
            'branch' => trim((string) $sheet->getCell('B' . $row)->getFormattedValue()),
            'bri_yoy' => (float) $sheet->getCell('D' . $row)->getCalculatedValue(),
            'bri_ytd' => (float) $sheet->getCell('E' . $row)->getCalculatedValue(),
            'bri_current' => (float) $sheet->getCell('F' . $row)->getCalculatedValue(),
            'bri_delta_ytd' => (float) $sheet->getCell('G' . $row)->getCalculatedValue(),
            'industry_current' => (float) $sheet->getCell('K' . $row)->getCalculatedValue(),
            'outside_current' => (float) $sheet->getCell('P' . $row)->getCalculatedValue(),
            'share_yoy' => (float) $sheet->getCell('S' . $row)->getCalculatedValue(),
            'share_ytd' => (float) $sheet->getCell('T' . $row)->getCalculatedValue(),
            'share_current' => (float) $sheet->getCell('U' . $row)->getCalculatedValue(),
            'share_delta_yoy' => (float) $sheet->getCell('V' . $row)->getCalculatedValue(),
            'share_delta_ytd' => (float) $sheet->getCell('W' . $row)->getCalculatedValue(),
        ];
    }

    private function marketShareLoanRowPayload($sheet, int $row): array
    {
        $briCurrent = (float) $sheet->getCell('AB' . $row)->getCalculatedValue();
        $industryCurrent = (float) $sheet->getCell('BF' . $row)->getCalculatedValue();

        return [
            'branch' => trim((string) ($sheet->getCell('A' . $row)->getFormattedValue() ?: $sheet->getCell('B' . $row)->getFormattedValue())),
            'bri_yoy' => (float) $sheet->getCell('D' . $row)->getCalculatedValue(),
            'bri_ytd' => (float) $sheet->getCell('X' . $row)->getCalculatedValue(),
            'bri_current' => $briCurrent,
            'bri_delta_ytd' => (float) $sheet->getCell('AE' . $row)->getCalculatedValue(),
            'industry_current' => $industryCurrent,
            'outside_current' => $industryCurrent - $briCurrent,
            'share_yoy' => (float) $sheet->getCell('BK' . $row)->getCalculatedValue(),
            'share_ytd' => (float) $sheet->getCell('CE' . $row)->getCalculatedValue(),
            'share_current' => (float) $sheet->getCell('CI' . $row)->getCalculatedValue(),
            'share_delta_yoy' => (float) $sheet->getCell('CJ' . $row)->getCalculatedValue(),
            'share_delta_ytd' => (float) $sheet->getCell('CK' . $row)->getCalculatedValue(),
        ];
    }

    private function marketShareDateLabel(mixed $value): string
    {
        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('M Y');
        }

        return trim((string) $value);
    }

    private function marketShareSafeDivide(float $value, float $total): float
    {
        return abs($total) > 0.000001 ? $value / $total : 0.0;
    }

    private function marketShareMappingManagedLink(): ?array
    {
        if (!$this->hasTable(self::EXTERNAL_REPORT_LINK_TABLE)) {
            return null;
        }

        $query = DB::table(self::EXTERNAL_REPORT_LINK_TABLE)
            ->where('group_key', self::MARKET_SHARE_LINK_GROUP)
            ->where('link_key', self::MARKET_SHARE_MAPPING_LINK_KEY);

        if ($this->hasColumn(self::EXTERNAL_REPORT_LINK_TABLE, 'is_active')) {
            $query->where('is_active', true);
        }

        $row = $query->first();
        if (!$row || trim((string) ($row->link_url ?? '')) === '') {
            return null;
        }

        $linkUrl = trim((string) $row->link_url);
        $sheetName = trim((string) ($row->sheet_name ?? ''));
        if (str_contains($linkUrl, '1Wlf7Wv5SR8DhtDlRgYwzhAHDSdwIsooa')
            || str_contains($linkUrl, '18RTg3ajn4Lpa2MkXtg8uuiRE7HsmEWbS3EdqO5xrcbY')
            || $this->googleSpreadsheetPreviewUrl($linkUrl, $sheetName) === ''
        ) {
            return null;
        }

        return [
            'link_url' => $linkUrl,
            'sheet_name' => $sheetName,
        ];
    }

    private function googleSpreadsheetPreviewUrl(string $url, string $sheetName = ''): string
    {
        $url = $this->extractIframeSource($url);
        if ($url === '' || str_contains($url, '...')) {
            return '';
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host']) || empty($parts['path'])) {
            return '';
        }

        $host = strtolower((string) $parts['host']);
        if ($host !== 'docs.google.com') {
            return '';
        }

        $path = (string) $parts['path'];
        if (!preg_match('~/spreadsheets/d/([^/]+)~', $path, $matches)) {
            return '';
        }

        parse_str((string) ($parts['query'] ?? ''), $sourceQuery);
        $query = [
            'usp' => 'sharing',
        ];

        if (!empty($sourceQuery['gid'])) {
            $query['gid'] = (string) $sourceQuery['gid'];
        } elseif ($sheetName !== '' && ctype_digit($sheetName)) {
            $query['gid'] = $sheetName;
        }

        return 'https://docs.google.com/spreadsheets/d/'
            . rawurlencode((string) $matches[1])
            . '/edit?'
            . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function googleSpreadsheetSheetHtmlUrl(string $url, string $sheetName): string
    {
        $url = $this->extractIframeSource($url);
        if ($url === '' || str_contains($url, '...')) {
            return '';
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host']) || empty($parts['path'])) {
            return '';
        }

        if (strtolower((string) $parts['host']) !== 'docs.google.com') {
            return '';
        }

        if (!preg_match('~/spreadsheets/d/([^/]+)~', (string) $parts['path'], $matches)) {
            return '';
        }

        return 'https://docs.google.com/spreadsheets/d/'
            . rawurlencode((string) $matches[1])
            . '/gviz/tq?'
            . http_build_query([
                'tqx' => 'out:html',
                'sheet' => $sheetName,
            ], '', '&', PHP_QUERY_RFC3986);
    }

    private function googleSpreadsheetSheetCsvUrl(string $url, string $sheetName): string
    {
        $url = $this->extractIframeSource($url);
        if ($url === '' || str_contains($url, '...')) {
            return '';
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host']) || empty($parts['path'])) {
            return '';
        }

        if (strtolower((string) $parts['host']) !== 'docs.google.com') {
            return '';
        }

        if (!preg_match('~/spreadsheets/d/([^/]+)~', (string) $parts['path'], $matches)) {
            return '';
        }

        return 'https://docs.google.com/spreadsheets/d/'
            . rawurlencode((string) $matches[1])
            . '/gviz/tq?'
            . http_build_query([
                'tqx' => 'out:csv',
                'sheet' => $sheetName,
            ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function parseCsvText(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return [];
        }

        fwrite($stream, $csv);
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream)) !== false) {
            $rows[] = $row;
        }

        fclose($stream);

        return $rows;
    }

    private function sharePointEmbedUrl(string $url): string
    {
        $url = $this->extractIframeSource($url);
        if ($url === '' || str_contains($url, '...')) {
            return '';
        }

        $url = str_replace(['{', '}'], ['%7B', '%7D'], $url);

        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $host = strtolower((string) $parts['host']);
        $path = (string) ($parts['path'] ?? '');
        $lowerPath = strtolower($path);
        if (!str_ends_with($host, 'sharepoint.com')) {
            return '';
        }

        if (!str_contains($lowerPath, '/_layouts/15/doc.aspx') && !str_contains($lowerPath, '/:x:/')) {
            return '';
        }

        parse_str((string) ($parts['query'] ?? ''), $query);

        if (str_contains($lowerPath, '/:x:/')) {
            $docEmbed = $this->sharePointDocEmbedParts($parts);
            if ($docEmbed !== null) {
                $parts['path'] = $docEmbed['path'];
                unset($parts['fragment']);

                return $this->buildUrl($parts, [
                    'sourcedoc' => '{' . $docEmbed['sourceDoc'] . '}',
                    'action' => 'embedview',
                    'wdAllowInteractivity' => 'True',
                    'wdAllowTyping' => 'True',
                    'wdHideHeaders' => 'False',
                    'wdHideGridlines' => 'False',
                    'wdHideSheetTabs' => 'False',
                ]);
            }

            unset($query['download']);
            unset($query['action']);
            $query['web'] = '1';

            return $this->buildUrl($parts, $query);
        }

        $query['action'] = 'embedview';
        $query['wdAllowInteractivity'] = 'True';
        $query['wdAllowTyping'] = 'True';
        $query['wdHideHeaders'] = 'False';
        $query['wdHideGridlines'] = 'False';
        $query['wdHideSheetTabs'] = 'False';

        return $this->buildUrl($parts, $query);
    }

    /**
     * @param array<string, mixed> $parts
     * @return array{path: string, sourceDoc: string}|null
     */
    private function sharePointDocEmbedParts(array $parts): ?array
    {
        $path = trim((string) ($parts['path'] ?? ''), '/');
        if ($path === '') {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path), fn (string $segment): bool => $segment !== ''));
        $normalizedSegments = array_map('strtolower', $segments);
        $shareToken = (string) end($segments);
        $sourceDoc = $this->sharePointSourceDocFromSharingToken($shareToken);
        if ($sourceDoc === '') {
            return null;
        }

        foreach (['personal', 'sites', 'teams'] as $rootSegment) {
            $rootIndex = array_search($rootSegment, $normalizedSegments, true);
            if ($rootIndex === false || !isset($segments[$rootIndex + 1])) {
                continue;
            }

            return [
                'path' => '/' . $segments[$rootIndex] . '/' . $segments[$rootIndex + 1] . '/_layouts/15/Doc.aspx',
                'sourceDoc' => $sourceDoc,
            ];
        }

        return null;
    }

    private function sharePointSourceDocFromSharingToken(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }

        $padding = str_repeat('=', (4 - strlen($token) % 4) % 4);
        $decoded = base64_decode(strtr($token . $padding, '-_', '+/'), true);
        if (!is_string($decoded) || strlen($decoded) < 18) {
            return '';
        }

        $guidBytes = substr($decoded, 2, 16);
        $hex = bin2hex($guidBytes);
        if (strlen($hex) !== 32) {
            return '';
        }

        return strtolower(
            substr($hex, 6, 2) . substr($hex, 4, 2) . substr($hex, 2, 2) . substr($hex, 0, 2)
            . '-'
            . substr($hex, 10, 2) . substr($hex, 8, 2)
            . '-'
            . substr($hex, 14, 2) . substr($hex, 12, 2)
            . '-'
            . substr($hex, 16, 4)
            . '-'
            . substr($hex, 20, 12)
        );
    }

    private function extractIframeSource(string $url): string
    {
        $url = trim($url);

        if (preg_match('/<iframe\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $url, $matches) === 1) {
            $url = $matches[1];
        }

        return html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5);
    }

    /**
     * @param array<string, mixed> $parts
     * @param array<string, mixed> $query
     */
    private function buildUrl(array $parts, array $query): string
    {
        $rebuilt = $parts['scheme'] . '://' . $parts['host'];
        $rebuilt .= isset($parts['port']) ? ':' . $parts['port'] : '';
        $rebuilt .= (string) ($parts['path'] ?? '');

        if ($query !== []) {
            $rebuilt .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        if (isset($parts['fragment'])) {
            $rebuilt .= '#' . $parts['fragment'];
        }

        return $rebuilt;
    }

    public function presentationData(Request $request)
    {
        $period = $this->resolvePresentationPeriod($request->query('periode'));
        $forceFresh = $request->boolean('fresh')
            || $request->boolean('refresh')
            || $request->has('_ts');
        $payload = $forceFresh
            ? $this->freshPresentationPayload($period)
            : $this->cachedPresentationPayload($period);

        if ($request->boolean('warm')) {
            return response()->noContent();
        }

        return response()->json($payload);
    }

    public function presentationKtsData(Request $request)
    {
        $requestedPeriod = trim((string) $request->query('periode'));
        $period = $this->resolveArea6DailyLoanPeriod($requestedPeriod !== '' ? $requestedPeriod : null);

        return response()->json([
            'kts' => $this->cachedPresentationKtsPayload($period),
        ]);
    }

    public function area6Data(Request $request)
    {
        $selectedPeriod = $request->query('periode');
        $loanPeriods = $this->resolveLoanDashboardPeriods($selectedPeriod ?: null);
        $loanPeriod = $loanPeriods[0] ?? null;

        $area6Portfolio = $this->buildArea6PortfolioLanding($loanPeriod);

        return response()->json([
            'area6_portfolio' => $area6Portfolio,
        ]);
    }

    public function presentation(Request $request)
    {
        $periods = app(DashboardHarianSnapshotService::class)->fetchPeriods();
        $period = $this->resolvePresentationPeriod($request->query('periode'), $periods);
        $payload = $this->cachedPresentationPayloadIfAvailable($period);

        return view('presentation', [
            'selectedPeriod' => $period,
            'periods' => $periods,
            'presentationPayload' => $payload,
        ]);
    }

    private function cachedPresentationPayload(?string $period): array
    {
        $cacheKey = $this->presentationPayloadCacheKey($period);

        return Cache::remember($cacheKey, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES), function () use ($period) {
            return $this->buildPresentationPayload($period);
        });
    }

    private function freshPresentationPayload(?string $period): array
    {
        $payload = $this->buildPresentationPayload($period, true);

        Cache::put(
            $this->presentationPayloadCacheKey($period),
            $payload,
            now()->addMinutes(self::PAYLOAD_CACHE_MINUTES)
        );

        return $payload;
    }

    private function cachedPresentationPayloadIfAvailable(?string $period): ?array
    {
        $cacheKey = $this->presentationPayloadCacheKey($period);

        return Cache::has($cacheKey)
            ? Cache::get($cacheKey)
            : null;
    }

    private function cachedPresentationKtsPayload(?string $period): array
    {
        $cacheKey = 'dashboard_simpanan:presentation_kts_payload:'
            . self::LANDING_SOURCE_CACHE_VERSION . ':v'
            . $this->reportCacheVersion() . ':'
            . ($period ?? 'null');

        return Cache::remember($cacheKey, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES), function () use ($period) {
            return $this->buildPresentationKts([], $period);
        });
    }

    private function presentationPayloadCacheKey(?string $period): string
    {
        return 'dashboard_simpanan:presentation_payload:'
            . ($period ?? 'null') . ':'
            . self::LANDING_SOURCE_CACHE_VERSION . ':v'
            . $this->reportCacheVersion() . ':ppt_deck_v2';
    }

    private function resolvePresentationPeriod(mixed $requestedPeriod, ?Collection $periods = null): ?string
    {
        $requested = trim((string) $requestedPeriod);
        $periods ??= app(DashboardHarianSnapshotService::class)->fetchPeriods();

        if ($requested !== '' && $periods->contains($requested)) {
            return $requested;
        }

        return $periods->first()
            ?: (Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)
                ? $this->area6HarianSnapshotSummaryQuery()->max('snapshot_period')
                : null);
    }

    private function buildPresentationPayload(?string $selectedPeriod, bool $forceFresh = false): array
    {
        $dashboard = $forceFresh
            ? $this->buildDashboardPayloadFresh($selectedPeriod, true)
            : $this->buildDashboardPayload($selectedPeriod);
        $dashboardPeriod = (string) data_get($dashboard, 'period', $selectedPeriod);
        $loanPeriods = $this->resolveLoanDashboardPeriods($dashboardPeriod ?: $selectedPeriod);
        $loanPeriod = $loanPeriods[0] ?? null;
        $dailyLoanPeriod = $this->resolveArea6DailyLoanPeriod($loanPeriod ?? $dashboardPeriod ?: null);
        $area6Portfolio = data_get($dashboard, 'area6_portfolio', []);
        $presentationPeriod = (string) (data_get($area6Portfolio, 'period') ?: $dashboardPeriod ?: $selectedPeriod);
        $digitalPerformance = data_get($dashboard, 'digital_performance', []);

        return [
            'meta' => [
                'title' => 'Area 6 - Region Malang',
                'subtitle' => 'Materi Pendukung Asistensi',
                'period' => $presentationPeriod ?: null,
                'period_label' => $this->formatPeriodLabel($presentationPeriod ?: null),
                'loan_period' => $loanPeriod,
                'loan_period_label' => $this->formatPeriodLabel($loanPeriod),
                'daily_loan_period' => $dailyLoanPeriod,
                'daily_loan_period_label' => $this->formatPeriodLabel($dailyLoanPeriod),
                'generated_at' => now()->timezone(config('app.timezone', 'Asia/Jakarta'))->format('Y-m-d H:i:s'),
                'source_note' => 'Angka diambil dari payload landing dan tabel snapshot/report existing; tidak memakai angka dummy.',
            ],
            'assets' => $this->buildPresentationAssets(),
            'summary' => $this->buildPresentationSummary($dashboard, $presentationPeriod ?: null, $loanPeriod),
            'performance_overview' => $this->buildPresentationPerformanceOverview($area6Portfolio, $presentationPeriod ?: null),
            'timeseries' => $this->buildPresentationTimeseries($presentationPeriod ?: null),
            'cover_card_timeseries' => $this->buildPresentationCoverCardTimeseries($presentationPeriod ?: null, $dailyLoanPeriod),
            'savings_breakdown' => $this->buildPresentationSavingsBreakdown($presentationPeriod ?: null),
            'loan_products' => $this->buildPresentationLoanProducts($presentationPeriod ?: null),
            'financial_highlights' => $this->buildPresentationFinancialHighlights($presentationPeriod ?: null),
            'executive_summary' => $this->buildLandingExecutiveSummary($dailyLoanPeriod ?? $loanPeriod, $area6Portfolio),
            'micro' => $this->buildPresentationMicro($dailyLoanPeriod),
            'quality' => $this->buildPresentationQuality($area6Portfolio),
            'kts' => $this->buildPresentationKtsSummary($dailyLoanPeriod),
            'digital_strategy' => $this->buildPresentationDigitalStrategy($digitalPerformance),
        ];
    }

    private function buildPresentationAssets(): array
    {
        return [
            'bri_logo' => asset('images/bri-logo-template.png'),
            'danantara_logo' => asset('images/danantara-logo-template.png'),
            'cover_base' => asset('images/ppt-template/cover-base.png'),
            'branch_building' => asset('images/bri-area6-building.png'),
        ];
    }

    private function buildPresentationSavingsBreakdown(?string $period): array
    {
        $empty = [
            'available' => false,
            'period' => $period,
            'period_label' => $this->formatPeriodLabel($period),
            'cards' => [],
        ];

        if (!$period || !Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return $empty;
        }

        $row = $this->area6HarianSnapshotSummaryQuery()
            ->where('snapshot_period', $period)
            ->selectRaw('COALESCE(SUM(COALESCE(total_simpanan, 0)), 0) as total_simpanan')
            ->selectRaw('COALESCE(SUM(COALESCE(giro_ritel, 0) + COALESCE(giro_mikro, 0) + COALESCE(giro_wholesale, 0)), 0) as giro')
            ->selectRaw('COALESCE(SUM(COALESCE(tabungan_ritel, 0) + COALESCE(tabungan_mikro, 0) + COALESCE(tabungan_wholesale, 0)), 0) as tabungan')
            ->selectRaw('COALESCE(SUM(COALESCE(deposito_ritel, 0) + COALESCE(deposito_mikro, 0) + COALESCE(deposito_wholesale, 0)), 0) as deposito')
            ->selectRaw('COALESCE(SUM(COALESCE(total_casa, 0)), 0) as casa')
            ->first();

        if (!$row) {
            return $empty;
        }

        $total = (float) ($row->total_simpanan ?? 0.0);
        $items = [
            'total_simpanan' => ['label' => 'Total Simpanan', 'value' => $total, 'tone' => '#0857c3', 'icon' => 'fas fa-piggy-bank'],
            'giro' => ['label' => 'Giro', 'value' => (float) ($row->giro ?? 0.0), 'tone' => '#307fe2', 'icon' => 'fas fa-building-columns'],
            'tabungan' => ['label' => 'Tabungan', 'value' => (float) ($row->tabungan ?? 0.0), 'tone' => '#71c5e8', 'icon' => 'fas fa-wallet'],
            'deposito' => ['label' => 'Deposito', 'value' => (float) ($row->deposito ?? 0.0), 'tone' => '#ccad95', 'icon' => 'fas fa-vault'],
            'casa' => ['label' => 'CASA', 'value' => (float) ($row->casa ?? 0.0), 'tone' => '#059669', 'icon' => 'fas fa-layer-group'],
        ];

        $cards = collect($items)
            ->map(function (array $item, string $key) use ($total): array {
                $pct = $key === 'total_simpanan' ? 100.0 : $this->percentOf($item['value'], $total);

                return [
                    'key' => $key,
                    'label' => $item['label'],
                    'value_raw' => $item['value'],
                    'value' => $this->formatCurrencyCompact($item['value']),
                    'pct_raw' => $pct,
                    'pct' => $this->formatPercentTwo($pct),
                    'tone' => $item['tone'],
                    'icon' => $item['icon'],
                ];
            })
            ->values()
            ->all();

        return [
            'available' => $total > 0.0,
            'period' => $period,
            'period_label' => $this->formatPeriodLabel($period),
            'cards' => $cards,
        ];
    }

    private function buildPresentationLoanProducts(?string $period): array
    {
        $empty = [
            'available' => false,
            'period' => $period,
            'period_label' => $this->formatPeriodLabel($period),
            'rows' => [],
        ];

        if (!$period || !Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return $empty;
        }

        $row = $this->area6HarianSnapshotSummaryQuery()
            ->where('snapshot_period', $period)
            ->selectRaw('COALESCE(SUM(COALESCE(kupedes_os, 0)), 0) as kupedes_os')
            ->selectRaw('COALESCE(SUM(COALESCE(kupedes_sml, 0)), 0) as kupedes_sml')
            ->selectRaw('COALESCE(SUM(COALESCE(kupedes_npl, 0)), 0) as kupedes_npl')
            ->selectRaw('COALESCE(SUM(COALESCE(kur_mikro_os, 0)), 0) as kur_mikro_os')
            ->selectRaw('COALESCE(SUM(COALESCE(kur_mikro_sml, 0)), 0) as kur_mikro_sml')
            ->selectRaw('COALESCE(SUM(COALESCE(kur_mikro_npl, 0)), 0) as kur_mikro_npl')
            ->selectRaw('COALESCE(SUM(COALESCE(briguna_mikro_os, 0)), 0) as briguna_mikro_os')
            ->selectRaw('COALESCE(SUM(COALESCE(briguna_mikro_sml, 0)), 0) as briguna_mikro_sml')
            ->selectRaw('COALESCE(SUM(COALESCE(briguna_mikro_npl, 0)), 0) as briguna_mikro_npl')
            ->selectRaw('COALESCE(SUM(COALESCE(kur_kpp_os, 0)), 0) as kur_kpp_os')
            ->selectRaw('COALESCE(SUM(COALESCE(kur_kpp_sml, 0)), 0) as kur_kpp_sml')
            ->selectRaw('COALESCE(SUM(COALESCE(kur_kpp_npl, 0)), 0) as kur_kpp_npl')
            ->selectRaw('COALESCE(SUM(COALESCE(kur_kecil_os, 0)), 0) as kur_kecil_os')
            ->selectRaw('COALESCE(SUM(COALESCE(kur_kecil_sml, 0)), 0) as kur_kecil_sml')
            ->selectRaw('COALESCE(SUM(COALESCE(kur_kecil_npl, 0)), 0) as kur_kecil_npl')
            ->first();

        if (!$row) {
            return $empty;
        }

        $definitions = [
            ['key' => 'kupedes', 'label' => 'Kupedes', 'os' => 'kupedes_os', 'sml' => 'kupedes_sml', 'npl' => 'kupedes_npl', 'icon' => 'fas fa-users'],
            ['key' => 'kur_mikro', 'label' => 'KUR Mikro', 'os' => 'kur_mikro_os', 'sml' => 'kur_mikro_sml', 'npl' => 'kur_mikro_npl', 'icon' => 'fas fa-store'],
            ['key' => 'briguna_mikro', 'label' => 'Briguna Mikro', 'os' => 'briguna_mikro_os', 'sml' => 'briguna_mikro_sml', 'npl' => 'briguna_mikro_npl', 'icon' => 'fas fa-id-card'],
            ['key' => 'kpp', 'label' => 'KPP', 'os' => 'kur_kpp_os', 'sml' => 'kur_kpp_sml', 'npl' => 'kur_kpp_npl', 'icon' => 'fas fa-briefcase'],
            ['key' => 'kur_kecil', 'label' => 'KUR Kecil', 'os' => 'kur_kecil_os', 'sml' => 'kur_kecil_sml', 'npl' => 'kur_kecil_npl', 'icon' => 'fas fa-building'],
        ];

        $rows = collect($definitions)
            ->map(function (array $definition) use ($row): array {
                $os = (float) ($row->{$definition['os']} ?? 0.0);
                $sml = (float) ($row->{$definition['sml']} ?? 0.0);
                $npl = (float) ($row->{$definition['npl']} ?? 0.0);

                return [
                    'key' => $definition['key'],
                    'label' => $definition['label'],
                    'icon' => $definition['icon'],
                    'os_raw' => $os,
                    'sml_raw' => $sml,
                    'npl_raw' => $npl,
                    'os' => $this->formatCurrencyCompact($os),
                    'sml' => $this->formatCurrencyCompact($sml),
                    'npl' => $this->formatCurrencyCompact($npl),
                    'sml_pct' => $os > 0 ? $this->formatPercentTwo(($sml / $os) * 100) : '-',
                    'npl_pct' => $os > 0 ? $this->formatPercentTwo(($npl / $os) * 100) : '-',
                ];
            })
            ->values()
            ->all();

        return [
            'available' => collect($rows)->contains(fn (array $item): bool => (float) $item['os_raw'] > 0.0),
            'period' => $period,
            'period_label' => $this->formatPeriodLabel($period),
            'rows' => $rows,
        ];
    }

    private function buildPresentationFinancialHighlights(?string $targetPeriod): array
    {
        $empty = [
            'available' => false,
            'period' => null,
            'period_label' => 'Belum ada data',
            'cards' => [],
            'branches' => [],
        ];

        if (!$this->hasTable('ssa_almafacts')) {
            return $empty;
        }

        $periodQuery = DB::table('ssa_almafacts')
            ->whereIn('kanca_konsolidasi', $this->dashboardBranchDisplayNames());

        if ($targetPeriod) {
            $periodQuery->whereDate('month_day_year_of_posisi', '<=', $targetPeriod);
        }

        $period = $periodQuery->max('month_day_year_of_posisi');
        if (!$period) {
            return $empty;
        }

        $metricLabels = [
            'profit_after_tax' => ['label' => 'Laba Setelah Pajak', 'source' => '15. Laba Setelah Pajak', 'type' => 'money', 'tone' => '#0857c3'],
            'ppop' => ['label' => 'PPOP', 'source' => '10. PPOP', 'type' => 'money', 'tone' => '#307fe2'],
            'nim' => ['label' => 'NIM', 'source' => '22. NIM (%)', 'type' => 'percent', 'tone' => '#059669'],
            'bopo' => ['label' => 'BOPO', 'source' => '28. BOPO (%)', 'type' => 'percent', 'tone' => '#dc2626'],
            'cer' => ['label' => 'CER', 'source' => '29. CER (%)', 'type' => 'percent', 'tone' => '#f59e0b'],
            'roa_before_tax' => ['label' => 'ROA Before Tax', 'source' => '26. ROA sebelum Pajak (%)', 'type' => 'percent', 'tone' => '#0f766e'],
            'roa_after_tax' => ['label' => 'ROA After Tax', 'source' => '27. ROA setelah Pajak (%)', 'type' => 'percent', 'tone' => '#7c3aed'],
            'casa' => ['label' => 'CASA', 'source' => '38. CASA (%)', 'type' => 'percent', 'tone' => '#00aeef'],
        ];

        $rows = DB::table('ssa_almafacts')
            ->whereDate('month_day_year_of_posisi', $period)
            ->whereIn('kanca_konsolidasi', $this->dashboardBranchDisplayNames())
            ->whereIn('keterangan', collect($metricLabels)->pluck('source')->all())
            ->select('keterangan')
            ->selectRaw('SUM(COALESCE(saldo, 0)) as sum_saldo')
            ->selectRaw('AVG(COALESCE(saldo, 0)) as avg_saldo')
            ->groupBy('keterangan')
            ->get()
            ->keyBy('keterangan');

        $cards = collect($metricLabels)
            ->map(function (array $metric, string $key) use ($rows): array {
                $row = $rows->get($metric['source']);
                $raw = $metric['type'] === 'percent'
                    ? (float) ($row->avg_saldo ?? 0.0)
                    : (float) ($row->sum_saldo ?? 0.0);

                return [
                    'key' => $key,
                    'label' => $metric['label'],
                    'value_raw' => $raw,
                    'value' => $metric['type'] === 'percent' ? $this->formatPercentTwo($raw) : $this->formatCurrencyCompact($raw),
                    'type' => $metric['type'],
                    'tone' => $metric['tone'],
                ];
            })
            ->values()
            ->all();

        $branchRows = DB::table('ssa_almafacts')
            ->whereDate('month_day_year_of_posisi', $period)
            ->where('keterangan', '15. Laba Setelah Pajak')
            ->whereIn('kanca_konsolidasi', $this->dashboardBranchDisplayNames())
            ->select('kanca_konsolidasi', DB::raw('SUM(COALESCE(saldo, 0)) as nominal'))
            ->groupBy('kanca_konsolidasi')
            ->get()
            ->keyBy('kanca_konsolidasi');

        $branches = collect($this->dashboardBranchDisplayNames())
            ->map(fn (string $branch): array => [
                'name' => $branch,
                'value_raw' => (float) data_get($branchRows->get($branch), 'nominal', 0.0),
                'value' => $this->formatCurrencyCompact((float) data_get($branchRows->get($branch), 'nominal', 0.0)),
            ])
            ->values()
            ->all();

        return [
            'available' => collect($cards)->contains(fn (array $card): bool => (float) $card['value_raw'] !== 0.0),
            'period' => $period,
            'period_label' => $this->formatPeriodLabel($period),
            'cards' => $cards,
            'branches' => $branches,
        ];
    }

    private function buildPresentationSummary(array $dashboard, ?string $period, ?string $loanPeriod): array
    {
        $liveReports = collect(data_get($dashboard, 'live_reports', []));
        $simpananReport = $liveReports->firstWhere('key', 'simpanan') ?? [];
        $pinjamanReport = $liveReports->firstWhere('key', 'pinjaman') ?? [];
        $portfolioReport = $liveReports->firstWhere('key', 'portfolio') ?? [];
        $area6Metrics = $period ? $this->area6ScopeSnapshotMetrics($period, 'cabang_konsol') : (object) [];
        $harianAvailable = $period && Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)
            ? $this->area6HarianSnapshotSummaryQuery()
                ->where('snapshot_period', $period)
                ->exists()
            : false;
        $simpananSummary = $period ? $this->buildPeriodSummary($period) : $this->emptySummary();
        $loanSummary = $loanPeriod ? $this->buildLoanSummary($loanPeriod) : $this->emptyLoanSummary();
        $simpananRaw = (float) ($simpananSummary['total_balance'] ?? 0);
        $loanRaw = (float) ($loanSummary['total_balance'] ?? 0);
        $osRaw = (float) ($area6Metrics->total_os_non_commercial ?? 0.0);
        $smlRaw = (float) ($area6Metrics->total_sml_abs_non_commercial ?? 0.0);
        $nplRaw = (float) ($area6Metrics->total_npl_abs_non_commercial ?? 0.0);
        $smlRatio = $this->percentOf($smlRaw, $osRaw);
        $nplRatio = $this->percentOf($nplRaw, $osRaw);
        $unavailable = 'Data belum tersedia';

        return [
            'cards' => [
                [
                    'key' => 'simpanan',
                    'label' => 'Total Simpanan',
                    'available' => $harianAvailable,
                    'value' => $harianAvailable ? data_get($simpananReport, 'value', $this->formatCurrencyCompact($simpananRaw)) : $unavailable,
                    'value_raw' => $harianAvailable ? $simpananRaw : null,
                    'trend' => data_get($simpananReport, 'trend', '0,00%'),
                    'meta' => data_get($simpananReport, 'meta', '-'),
                    'source' => data_get($simpananReport, 'detail_payload.source_table', self::HARIAN_SNAPSHOT_TABLE),
                ],
                [
                    'key' => 'os',
                    'label' => 'Total OS Non Commercial',
                    'available' => $harianAvailable,
                    'value' => $harianAvailable ? $this->formatCurrencyCompact($osRaw) : $unavailable,
                    'value_raw' => $harianAvailable ? $osRaw : null,
                    'trend' => data_get($pinjamanReport, 'trend', '0,00%'),
                    'meta' => 'OS Area 6 Cabang Konsol',
                    'source' => self::HARIAN_SNAPSHOT_TABLE,
                ],
                [
                    'key' => 'ldr',
                    'label' => 'LDR',
                    'available' => $harianAvailable && $simpananRaw > 0,
                    'value' => $harianAvailable && $simpananRaw > 0
                        ? data_get($portfolioReport, 'value', $this->formatRatio($loanRaw, $simpananRaw))
                        : $unavailable,
                    'value_raw' => $harianAvailable && $simpananRaw > 0 ? $loanRaw / $simpananRaw : null,
                    'trend' => data_get($portfolioReport, 'trend', '0,00%'),
                    'meta' => data_get($portfolioReport, 'meta', '-'),
                    'source' => 'Landing LDR',
                ],
                [
                    'key' => 'sml',
                    'label' => 'SML',
                    'available' => $harianAvailable,
                    'value' => $harianAvailable ? $this->formatCurrencyCompact($smlRaw) : $unavailable,
                    'value_raw' => $harianAvailable ? $smlRaw : null,
                    'ratio' => $harianAvailable ? $this->formatPercentTwo($smlRatio) : $unavailable,
                    'ratio_raw' => $harianAvailable ? $smlRatio : null,
                    'meta' => 'Nominal dan rasio SML',
                    'source' => self::HARIAN_SNAPSHOT_TABLE,
                ],
                [
                    'key' => 'npl',
                    'label' => 'NPL',
                    'available' => $harianAvailable,
                    'value' => $harianAvailable ? $this->formatCurrencyCompact($nplRaw) : $unavailable,
                    'value_raw' => $harianAvailable ? $nplRaw : null,
                    'ratio' => $harianAvailable ? $this->formatPercentTwo($nplRatio) : $unavailable,
                    'ratio_raw' => $harianAvailable ? $nplRatio : null,
                    'meta' => 'Nominal dan rasio NPL',
                    'source' => self::HARIAN_SNAPSHOT_TABLE,
                ],
            ],
            'highlights' => [
                $harianAvailable ? data_get($simpananReport, 'detail', 'Simpanan mengikuti snapshot landing.') : $unavailable,
                $harianAvailable ? data_get($pinjamanReport, 'detail', 'Pinjaman mengikuti snapshot landing.') : $unavailable,
                $harianAvailable ? 'SML ' . $this->formatCurrencyCompact($smlRaw) . ' (' . $this->formatPercentTwo($smlRatio) . ')' : $unavailable,
                $harianAvailable ? 'NPL ' . $this->formatCurrencyCompact($nplRaw) . ' (' . $this->formatPercentTwo($nplRatio) . ')' : $unavailable,
            ],
            'composition_dpk' => [
                'tabungan_pct' => $simpananRaw > 0 ? (($simpananSummary['tabungan_balance'] ?? 0) / $simpananRaw) * 100 : 0.0,
                'giro_pct' => $simpananRaw > 0 ? (($simpananSummary['giro_balance'] ?? 0) / $simpananRaw) * 100 : 0.0,
                'other_pct' => $simpananRaw > 0 ? (($simpananSummary['other_balance'] ?? 0) / $simpananRaw) * 100 : 0.0,
            ]
        ];
    }

    private function buildPresentationPerformanceOverview(array $area6Portfolio, ?string $period = null): array
    {
        $scope = data_get($area6Portfolio, 'scopes.cabang_konsol', $area6Portfolio);

        return [
            'period_label' => data_get($area6Portfolio, 'period_label', '-'),
            'rka_month_year' => data_get($scope, 'segment_performance.rka_month_year', null),
            'segments' => data_get($scope, 'segment_performance.segments', []),
            'total' => data_get($scope, 'segment_performance.total', []),
            'composition' => data_get($scope, 'segment_performance.composition', []),
            'branches' => array_values(
                data_get($area6Portfolio, 'ranking_modes.area6.branches',
                    data_get($area6Portfolio, 'ranking_modes.cabang_konsol.branches', [])
                )
            ),
            'scope_cards' => data_get($area6Portfolio, 'scopes', []),
            'matrix' => $this->buildPresentationPerformanceMatrix($period),
        ];
    }

    private function buildPresentationPerformanceMatrix(?string $period): array
    {
        $empty = [
            'available' => false,
            'unit' => 'Rupiah',
            'periods' => [],
            'metrics' => [
                'simpanan' => ['label' => 'Simpanan', 'tone' => 'blue'],
                'os' => ['label' => 'OS', 'tone' => 'green'],
                'sml' => ['label' => 'SML', 'tone' => 'amber'],
                'npl' => ['label' => 'NPL', 'tone' => 'red'],
            ],
            'scope_options' => [],
            'rows' => [],
        ];

        if (!$period || !Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return $empty;
        }

        $comparisonPeriods = $this->buildPresentationComparisonPeriods($period);
        $periodValues = array_values(array_filter(array_column($comparisonPeriods, 'period')));
        if (empty($periodValues)) {
            return $empty;
        }

        $branchRows = $this->fetchPresentationMatrixRows($periodValues, true);
        $unitRows = $this->fetchPresentationMatrixRows($periodValues, false);
        $currentPeriod = Carbon::parse($period)->toDateString();

        $rows = [
            'area6' => $this->formatPresentationMatrixRows($branchRows, $comparisonPeriods, $currentPeriod, true),
        ];

        foreach ($this->dashboardBranchDisplayNames() as $branchName) {
            $branchKey = strtoupper(trim($branchName));
            $branchUnitRows = $unitRows->filter(function ($row) use ($branchKey): bool {
                return strtoupper(trim((string) ($row->branch_label ?? ''))) === $branchKey;
            });

            $rows[$branchKey] = $this->formatPresentationMatrixRows($branchUnitRows, $comparisonPeriods, $currentPeriod, false);
        }

        return [
            'available' => $branchRows->isNotEmpty() || $unitRows->isNotEmpty(),
            'unit' => 'Rupiah',
            'periods' => $comparisonPeriods,
            'metrics' => $empty['metrics'],
            'scope_options' => array_merge(
                [['key' => 'area6', 'label' => 'Area 6 Konsol']],
                collect($this->dashboardBranchDisplayNames())
                    ->map(fn (string $branch): array => [
                        'key' => strtoupper(trim($branch)),
                        'label' => $branch,
                    ])
                    ->all()
            ),
            'rows' => $rows,
        ];
    }

    private function buildPresentationComparisonPeriods(string $period): array
    {
        $current = Carbon::parse($period)->toDateString();
        $endOfPreviousMonth = Carbon::parse($current)->subMonthNoOverflow()->endOfMonth()->toDateString();
        $sameDatePreviousMonth = Carbon::parse($current)->subMonthNoOverflow()->toDateString();
        $prevYearEnd = Carbon::parse($current)->subYear()->endOfYear()->toDateString();

        $ytdPeriod = $this->area6HarianSnapshotSummaryQuery()
            ->where('snapshot_period', '<=', $prevYearEnd)
            ->orderBy('snapshot_period', 'desc')
            ->value('snapshot_period') ?: $prevYearEnd;

        $mtmPeriod = $this->resolveHarianSnapshotPeriodOnOrBefore($sameDatePreviousMonth) ?: $sameDatePreviousMonth;
        $mtdPeriod = $this->resolveHarianSnapshotPeriodOnOrBefore($endOfPreviousMonth) ?: $endOfPreviousMonth;

        return [
            'ytd' => [
                'key' => 'ytd',
                'period' => Carbon::parse($ytdPeriod)->toDateString(),
                'label' => 'YtD',
                'display' => Carbon::parse($ytdPeriod)->translatedFormat('d M y'),
            ],
            'mtm' => [
                'key' => 'mtm',
                'period' => Carbon::parse($mtmPeriod)->toDateString(),
                'label' => 'MtM',
                'display' => Carbon::parse($mtmPeriod)->translatedFormat('d M y'),
            ],
            'mtd' => [
                'key' => 'mtd',
                'period' => Carbon::parse($mtdPeriod)->toDateString(),
                'label' => 'MtD',
                'display' => Carbon::parse($mtdPeriod)->translatedFormat('d M y'),
            ],
            'current' => [
                'key' => 'current',
                'period' => $current,
                'label' => 'Posisi',
                'display' => Carbon::parse($current)->translatedFormat('d M y'),
            ],
        ];
    }

    private function fetchPresentationMatrixRows(array $periods, bool $summaryRows): Collection
    {
        $branchLabelExpression = Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_label')
            ? 'kanca_label'
            : (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'branch_label') ? 'branch_label' : "''");
        $unitLabelExpression = $summaryRows
            ? $branchLabelExpression
            : (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'unit_label')
                ? 'unit_label'
                : (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'uker_label') ? 'uker_label' : "''"));

        $query = DB::table(self::HARIAN_SNAPSHOT_TABLE)
            ->whereIn('snapshot_period', $periods)
            ->whereIn(DB::raw('UPPER(TRIM(' . $branchLabelExpression . '))'), $this->dashboardBranchNames());

        if ($this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_key') && $this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'unit_key')) {
            $summaryRows
                ? $query->whereColumn('kanca_key', 'unit_key')
                : $query->whereColumn('kanca_key', '<>', 'unit_key');
        } elseif ($this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'scope')) {
            $query->where('scope', $summaryRows ? 'branch' : 'unit');
        }

        $query
            ->selectRaw('snapshot_period')
            ->selectRaw("COALESCE({$branchLabelExpression}, '') as branch_label")
            ->selectRaw("COALESCE({$unitLabelExpression}, '') as unit_label")
            ->selectRaw('COALESCE(SUM(COALESCE(total_simpanan, 0)), 0) as total_simpanan')
            ->selectRaw('COALESCE(SUM(COALESCE(total_os, 0)), 0) as total_os')
            ->selectRaw('COALESCE(SUM(COALESCE(total_os_non_commercial, 0)), 0) as total_os_non_commercial')
            ->selectRaw('COALESCE(SUM(COALESCE(total_sml_abs_non_commercial, 0)), 0) as sml_abs')
            ->selectRaw('COALESCE(SUM(COALESCE(total_npl_abs_non_commercial, 0)), 0) as npl_abs')
            ->selectRaw('COALESCE(SUM(COALESCE(sme_os, 0)), 0) as sme_os')
            ->selectRaw('COALESCE(SUM(COALESCE(consumer_os, 0)), 0) as consumer_os')
            ->selectRaw('COALESCE(SUM(COALESCE(micro_os, 0)), 0) as micro_os')
            ->groupBy('snapshot_period');

        // GROUP BY must use the raw column name (not a COALESCE expression) to satisfy
        // MySQL ONLY_FULL_GROUP_BY mode. When the expression is a literal string (e.g. "''")
        // we skip grouping on it — there is only one distinct value so it's not needed.
        if ($branchLabelExpression !== "''") {
            $query->groupBy($branchLabelExpression);
        }
        if ($unitLabelExpression !== "''" && $unitLabelExpression !== $branchLabelExpression) {
            $query->groupBy($unitLabelExpression);
        }

        return $query->get();
    }

    private function formatPresentationMatrixRows(Collection $rows, array $comparisonPeriods, string $currentPeriod, bool $summaryRows): array
    {
        $grouped = $rows->groupBy(function ($row): string {
            return strtoupper(trim((string) ($row->branch_label ?? ''))) . '|'
                . strtoupper(trim((string) ($row->unit_label ?? '')));
        });

        $rkaService = null;
        $rkaYear = null;
        $monthColumn = null;
        $definitions = [
            'simpanan'     => ['mata_anggaran' => ['A.1. DPK Retail Funding Total', 'A.2. DPK Korporasi']],
            'os'           => ['mata_anggaran' => ['B. KREDIT TOTAL']],
            'sme_os'       => ['mata_anggaran' => ['B.2. SMALL', 'B.3. MEDIUM']],
            'consumer_os'  => ['mata_anggaran' => ['B.4. KONSUMER']],
            'micro_os'     => ['mata_anggaran' => ['B.1. MIKRO']],
        ];
        try {
            $rkaService = app(\App\Support\RkaLookupService::class);
            $rkaYear = (int) Carbon::parse($currentPeriod)->format('Y');
            $monthColumn = $rkaService->resolveMonthColumn(Carbon::parse($currentPeriod));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to initialize RkaLookupService in formatPresentationMatrixRows: ' . $e->getMessage());
        }

        return $grouped
            ->map(function (Collection $periodRows) use ($comparisonPeriods, $currentPeriod, $summaryRows, $rkaService, $rkaYear, $monthColumn, $definitions): ?array {
                $indexed = $periodRows->keyBy(fn ($row) => Carbon::parse($row->snapshot_period)->toDateString());
                $current = $indexed->get($currentPeriod);
                if (!$current) {
                    return null;
                }

                $metricValues = [
                    'simpanan'    => fn ($row): float => (float) ($row->total_simpanan ?? 0.0),
                    'os'          => fn ($row): float => (float) ($row->total_os ?? 0.0),
                    'sml'         => fn ($row): float => (float) ($row->sml_abs ?? 0.0),
                    'npl'         => fn ($row): float => (float) ($row->npl_abs ?? 0.0),
                    'sme_os'      => fn ($row): float => (float) ($row->sme_os ?? 0.0),
                    'consumer_os' => fn ($row): float => (float) ($row->consumer_os ?? 0.0),
                    'micro_os'    => fn ($row): float => (float) ($row->micro_os ?? 0.0),
                ];

                $rkaValues = [];
                if ($rkaService && $monthColumn) {
                    $kanca = (string) ($current->branch_label ?? '');
                    $unit = $summaryRows ? null : (string) ($current->unit_label ?? '');
                    try {
                        $rkaValues = $rkaService->aggregateForScope($definitions, $monthColumn, $kanca, $unit, $rkaYear);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('RkaLookupService aggregateForScope failed in formatPresentationMatrixRows: ' . $e->getMessage());
                    }
                }

                $metrics = [];
                foreach ($metricValues as $metricKey => $resolver) {
                    $latest = $resolver($current);
                    $baselineValues = [];
                    foreach ($comparisonPeriods as $periodInfo) {
                        $row = $indexed->get((string) $periodInfo['period']);
                        $baselineValues[$periodInfo['key']] = $row ? $resolver($row) : 0.0;
                    }

                    $osNonCommercial = (float) ($current->total_os_non_commercial ?? 0.0);
                    $ratio = in_array($metricKey, ['sml', 'npl'], true) && $osNonCommercial > 0
                        ? ($latest / $osNonCommercial) * 100
                        : null;

                    $metricData = [
                        'latest_raw' => $latest,
                        'latest' => $this->formatCurrencyCompact($latest),
                        'ytd_raw' => $latest - ($baselineValues['ytd'] ?? 0.0),
                        'ytd' => $this->formatPresentationMatrixDelta($latest - ($baselineValues['ytd'] ?? 0.0)),
                        'mtm_raw' => $latest - ($baselineValues['mtm'] ?? 0.0),
                        'mtm' => $this->formatPresentationMatrixDelta($latest - ($baselineValues['mtm'] ?? 0.0)),
                        'mtd_raw' => $latest - ($baselineValues['mtd'] ?? 0.0),
                        'mtd' => $this->formatPresentationMatrixDelta($latest - ($baselineValues['mtd'] ?? 0.0)),
                        'series' => [
                            round(($baselineValues['ytd'] ?? 0.0) / 1000000),
                            round(($baselineValues['mtm'] ?? 0.0) / 1000000),
                            round(($baselineValues['mtd'] ?? 0.0) / 1000000),
                            round($latest / 1000000),
                        ],
                        'ratio_raw' => $ratio,
                        'ratio' => $ratio === null ? null : $this->formatPercentTwo($ratio),
                    ];

                    if (in_array($metricKey, ['simpanan', 'os', 'sme_os', 'consumer_os', 'micro_os'], true)) {
                        $targetVal = (float) ($rkaValues[$metricKey] ?? 0.0);
                        if ($targetVal > 0.0) {
                            $gap = $latest - $targetVal;
                            $penc = ($latest / $targetVal) * 100;

                            $metricData['rka_raw'] = $targetVal;
                            $metricData['rka_fmt'] = $this->formatCurrencyCompact($targetVal);

                            $gapDelta = $this->formatPresentationMatrixDelta($gap);
                            $metricData['gap_raw'] = $gap;
                            $metricData['gap_fmt'] = $gapDelta['value'];
                            $metricData['gap_class'] = $gapDelta['class'];

                            $metricData['penc_raw'] = $penc;
                            $metricData['penc_fmt'] = $this->formatPercentTwo($penc);
                        } else {
                            $metricData['rka_raw'] = 0.0;
                            $metricData['rka_fmt'] = '-';

                            $metricData['gap_raw'] = 0.0;
                            $metricData['gap_fmt'] = '-';
                            $metricData['gap_class'] = '';

                            $metricData['penc_raw'] = 0.0;
                            $metricData['penc_fmt'] = '-';
                        }
                    }

                    $metrics[$metricKey] = $metricData;
                }

                return [
                    'label' => $summaryRows ? (string) ($current->branch_label ?? '-') : (string) ($current->unit_label ?? '-'),
                    'branch' => (string) ($current->branch_label ?? '-'),
                    'type' => $summaryRows ? 'Cabang Konsol' : 'Unit',
                    'metrics' => $metrics,
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $row): float => (float) data_get($row, 'metrics.simpanan.latest_raw', 0.0))
            ->values()
            ->all();
    }

    private function formatPresentationMatrixDelta(float $delta): array
    {
        $prefix = $delta >= 0 ? '+' : '-';
        $class = $delta >= 0 ? 'pos' : 'neg';

        return [
            'value' => $prefix . $this->formatCurrencyCompact(abs($delta)),
            'class' => $class,
        ];
    }

    private function buildPresentationTimeseries(?string $period): array
    {
        $empty = [
            'available' => false,
            'source' => self::HARIAN_SNAPSHOT_TABLE,
            'unit' => 'Rp Juta',
            'labels' => [],
            'series' => [],
        ];

        if (!$period || !Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return $empty;
        }

        $comp = $this->buildPresentationComparisonPeriods($period);
        $resolvedPeriods = [
            $comp['ytd']['period'],
            $comp['mtm']['period'],
            $comp['mtd']['period'],
            $comp['current']['period']
        ];
        usort($resolvedPeriods, function ($a, $b) {
            return strcmp($a, $b);
        });
        $resolvedPeriods = array_values(array_unique($resolvedPeriods));

        $rows = $this->area6HarianSnapshotSummaryQuery()
            ->whereIn('snapshot_period', $resolvedPeriods)
            ->selectRaw('snapshot_period')
            ->selectRaw('COALESCE(SUM(COALESCE(total_simpanan, 0)), 0) as simpanan_total')
            ->selectRaw('COALESCE(SUM(COALESCE(total_os_non_commercial, 0)), 0) as os_total')
            ->selectRaw('COALESCE(SUM(COALESCE(total_sml_abs_non_commercial, 0)), 0) as sml_nominal')
            ->selectRaw('COALESCE(SUM(COALESCE(total_npl_abs_non_commercial, 0)), 0) as npl_nominal')
            ->groupBy('snapshot_period')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->snapshot_period)->toDateString());

        $labels = [];
        $series = [
            'simpanan_total' => ['key' => 'simpanan_total', 'label' => 'Realisasi Simpanan Total', 'values' => [], 'display_values' => []],
            'os_total' => ['key' => 'os_total', 'label' => 'Realisasi OS Total', 'values' => [], 'display_values' => []],
            'sml_nominal' => ['key' => 'sml_nominal', 'label' => 'Realisasi SML', 'values' => [], 'display_values' => []],
            'npl_nominal' => ['key' => 'npl_nominal', 'label' => 'Realisasi NPL', 'values' => [], 'display_values' => []],
        ];

        foreach ($resolvedPeriods as $resolvedPeriod) {
            $date = Carbon::parse($resolvedPeriod)->toDateString();
            $row = $rows->get($date);
            if (!$row) {
                continue;
            }

            // Find matching prefix
            $prefix = 'Posisi';
            if ($date === $comp['ytd']['period']) {
                $prefix = 'YtD';
            } elseif ($date === $comp['mtm']['period']) {
                $prefix = 'MtM';
            } elseif ($date === $comp['mtd']['period']) {
                $prefix = 'MtD';
            }

            $labels[] = $prefix . ' (' . Carbon::parse($date)->translatedFormat('d M y') . ')';

            foreach (array_keys($series) as $key) {
                $raw = (float) ($row->{$key} ?? 0.0);
                $series[$key]['values'][] = round($raw / 1000000);
                $series[$key]['display_values'][] = $this->formatCurrencyCompact($raw);
            }
        }

        return [
            'available' => $rows->isNotEmpty(),
            'source' => self::HARIAN_SNAPSHOT_TABLE,
            'unit' => 'Rp Juta',
            'labels' => $labels,
            'series' => array_values($series),
        ];
    }

    private function buildPresentationCoverCardTimeseries(?string $period, ?string $dailyLoanPeriod): array
    {
        $pointKeys = ['ytd', 'mtm', 'mtd', 'current'];
        $defaultPeriods = [
            'ytd' => ['key' => 'ytd', 'label' => 'YtD', 'period' => null, 'display' => '-'],
            'mtm' => ['key' => 'mtm', 'label' => 'MtM', 'period' => null, 'display' => '-'],
            'mtd' => ['key' => 'mtd', 'label' => 'MtD', 'period' => null, 'display' => '-'],
            'current' => ['key' => 'current', 'label' => 'Posisi', 'period' => null, 'display' => '-'],
        ];
        $snapshotPeriods = $defaultPeriods;

        $cards = [];
        $emptyFormatter = fn (?float $value): string => $value === null ? 'Data belum tersedia' : $this->formatCurrencyCompact($value);
        $ratioFormatter = fn (?float $value): string => $value === null ? 'Data belum tersedia' : number_format($value, 2, ',', '.') . 'x';
        $integerFormatter = fn (?float $value): string => $value === null ? 'Data belum tersedia' : $this->formatInteger((int) $value);

        $makeCard = function (
            string $key,
            string $label,
            string $unit,
            string $tone,
            array $periods,
            callable $resolver,
            callable $formatter,
            ?string $meta = null
        ) use ($pointKeys): array {
            $points = [];

            foreach ($pointKeys as $pointKey) {
                $periodInfo = $periods[$pointKey] ?? ['key' => $pointKey, 'label' => strtoupper($pointKey), 'period' => null, 'display' => '-'];
                $value = $periodInfo['period'] ? $resolver((string) $periodInfo['period']) : null;
                $points[] = [
                    'key' => $pointKey,
                    'label' => (string) ($periodInfo['label'] ?? strtoupper($pointKey)),
                    'period' => $periodInfo['period'] ?? null,
                    'period_label' => (string) ($periodInfo['display'] ?? '-'),
                    'value' => $value,
                    'display_value' => $formatter($value),
                ];
            }

            return [
                'key' => $key,
                'label' => $label,
                'unit' => $unit,
                'tone' => $tone,
                'meta' => $meta,
                'available' => collect($points)->contains(fn (array $point): bool => $point['value'] !== null),
                'points' => $points,
            ];
        };

        if ($period && Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            $snapshotPeriods = $this->buildPresentationComparisonPeriods($period);
            $periodValues = array_values(array_unique(array_filter(array_map(
                fn (string $key): ?string => $snapshotPeriods[$key]['period'] ?? null,
                $pointKeys
            ))));

            $totalRows = $this->area6HarianSnapshotSummaryQuery()
                ->whereIn('snapshot_period', $periodValues)
                ->selectRaw('snapshot_period')
                ->selectRaw('COALESCE(SUM(COALESCE(total_simpanan, 0)), 0) as simpanan_total')
                ->selectRaw('COALESCE(SUM(COALESCE(total_os, 0)), 0) as os_total')
                ->selectRaw('COALESCE(SUM(COALESCE(total_os_non_commercial, 0)), 0) as os_non_commercial_total')
                ->selectRaw('COALESCE(SUM(COALESCE(total_sml_abs_non_commercial, 0)), 0) as sml_nominal')
                ->selectRaw('COALESCE(SUM(COALESCE(total_npl_abs_non_commercial, 0)), 0) as npl_nominal')
                ->groupBy('snapshot_period')
                ->get()
                ->keyBy(fn ($row) => Carbon::parse($row->snapshot_period)->toDateString());

            $branchRows = $this->fetchPresentationMatrixRows($periodValues, true);
            $currentPeriod = (string) ($snapshotPeriods['current']['period'] ?? '');
            $currentBranchRows = $branchRows->filter(fn ($row): bool => Carbon::parse($row->snapshot_period)->toDateString() === $currentPeriod);
            $topSimpananBranch = $currentBranchRows->sortByDesc(fn ($row): float => (float) ($row->total_simpanan ?? 0))->first();
            $topOsBranch = $currentBranchRows->sortByDesc(fn ($row): float => (float) ($row->total_os ?? 0))->first();
            $branchGroups = $branchRows->groupBy(fn ($row): string => strtoupper(trim((string) ($row->branch_label ?? ''))));

            $branchResolver = function ($branchRow, string $column) use ($branchGroups): callable {
                $branchKey = strtoupper(trim((string) ($branchRow->branch_label ?? '')));
                $rows = $branchGroups->get($branchKey, collect())->keyBy(fn ($row) => Carbon::parse($row->snapshot_period)->toDateString());

                return fn (string $pointPeriod): ?float => $rows->has($pointPeriod)
                    ? (float) ($rows->get($pointPeriod)->{$column} ?? 0.0)
                    : null;
            };

            $cards['simpanan'] = $makeCard(
                'simpanan',
                'Simpanan',
                'currency',
                '#059669',
                $snapshotPeriods,
                fn (string $pointPeriod): ?float => $totalRows->has($pointPeriod) ? (float) ($totalRows->get($pointPeriod)->simpanan_total ?? 0.0) : null,
                $emptyFormatter
            );

            $cards['os'] = $makeCard(
                'os',
                'OS',
                'currency',
                '#2563eb',
                $snapshotPeriods,
                fn (string $pointPeriod): ?float => $totalRows->has($pointPeriod) ? (float) ($totalRows->get($pointPeriod)->os_non_commercial_total ?? 0.0) : null,
                $emptyFormatter
            );

            $cards['ldr'] = $makeCard(
                'ldr',
                'LDR',
                'ratio',
                '#7c3aed',
                $snapshotPeriods,
                function (string $pointPeriod) use ($totalRows): ?float {
                    if (!$totalRows->has($pointPeriod)) {
                        return null;
                    }

                    $row = $totalRows->get($pointPeriod);
                    $simpanan = (float) ($row->simpanan_total ?? 0.0);
                    if ($simpanan <= 0.0) {
                        return null;
                    }

                    return (float) ($row->os_non_commercial_total ?? 0.0) / $simpanan;
                },
                $ratioFormatter
            );

            $cards['sml'] = $makeCard(
                'sml',
                'SML',
                'currency',
                '#ea580c',
                $snapshotPeriods,
                fn (string $pointPeriod): ?float => $totalRows->has($pointPeriod) ? (float) ($totalRows->get($pointPeriod)->sml_nominal ?? 0.0) : null,
                $emptyFormatter
            );

            $cards['npl'] = $makeCard(
                'npl',
                'NPL',
                'currency',
                '#dc2626',
                $snapshotPeriods,
                fn (string $pointPeriod): ?float => $totalRows->has($pointPeriod) ? (float) ($totalRows->get($pointPeriod)->npl_nominal ?? 0.0) : null,
                $emptyFormatter
            );

            $cards['top_simpanan'] = $makeCard(
                'top_simpanan',
                'Top Simpanan',
                'currency',
                '#d97706',
                $snapshotPeriods,
                $topSimpananBranch ? $branchResolver($topSimpananBranch, 'total_simpanan') : fn (string $pointPeriod): ?float => null,
                $emptyFormatter,
                $topSimpananBranch ? (string) ($topSimpananBranch->branch_label ?? '') : null
            );

            $cards['top_os'] = $makeCard(
                'top_os',
                'Top OS',
                'currency',
                '#0891b2',
                $snapshotPeriods,
                $topOsBranch ? $branchResolver($topOsBranch, 'total_os') : fn (string $pointPeriod): ?float => null,
                $emptyFormatter,
                $topOsBranch ? (string) ($topOsBranch->branch_label ?? '') : null
            );
        }

        $ktsPeriods = $dailyLoanPeriod ? $this->buildPresentationDailyLoanComparisonPeriods($dailyLoanPeriod) : $defaultPeriods;
        $cards['kts_membaik'] = $makeCard(
            'kts_membaik',
            'KTS Membaik',
            'rekening',
            '#10b981',
            $ktsPeriods ?: $defaultPeriods,
            fn (string $pointPeriod): ?float => null,
            $integerFormatter,
            'Ritel + Micro'
        );
        $cards['kts_memburuk'] = $makeCard(
            'kts_memburuk',
            'KTS Memburuk',
            'rekening',
            '#ef4444',
            $ktsPeriods ?: $defaultPeriods,
            fn (string $pointPeriod): ?float => null,
            $integerFormatter,
            'Ritel + Micro'
        );

        return [
            'source' => [
                'harian' => self::HARIAN_SNAPSHOT_TABLE,
                'kts' => 'daily_loan_dinamis',
            ],
            'periods' => $snapshotPeriods,
            'cards' => $cards,
        ];
    }

    private function buildPresentationDailyLoanComparisonPeriods(?string $period): array
    {
        if (!$period || !Schema::hasTable('daily_loan_dinamis')) {
            return [];
        }

        $current = Carbon::parse($period)->toDateString();
        $endOfPreviousMonth = Carbon::parse($current)->subMonthNoOverflow()->endOfMonth()->toDateString();
        $sameDatePreviousMonth = Carbon::parse($current)->subMonthNoOverflow()->toDateString();
        $prevYearEnd = Carbon::parse($current)->subYear()->endOfYear()->toDateString();

        $periods = [
            'ytd' => $this->resolveArea6DailyLoanPeriod($prevYearEnd),
            'mtm' => $this->resolveArea6DailyLoanPeriod($sameDatePreviousMonth),
            'mtd' => $this->resolveArea6DailyLoanPeriod($endOfPreviousMonth),
            'current' => $this->resolveArea6DailyLoanPeriod($current),
        ];

        return collect($periods)->map(function (?string $resolvedPeriod, string $key): array {
            $labels = [
                'ytd' => 'YtD',
                'mtm' => 'MtM',
                'mtd' => 'MtD',
                'current' => 'Posisi',
            ];

            return [
                'key' => $key,
                'period' => $resolvedPeriod,
                'label' => $labels[$key] ?? strtoupper($key),
                'display' => $resolvedPeriod ? Carbon::parse($resolvedPeriod)->translatedFormat('d M y') : '-',
            ];
        })->all();
    }

    private function buildPresentationMicro(?string $period): array
    {
        return [
            'period' => $period,
            'period_label' => $this->formatPeriodLabel($period),
            'decision' => $this->buildPresentationDecisionEvaluation($period),
            'mantri_productivity' => $this->buildPresentationMantriProductivity($period),
            'rm_kur_micro' => $this->buildPresentationRmKurMicro($period),
        ];
    }

    private function buildPresentationDecisionEvaluation(?string $period): array
    {
        $payload = $this->invokeKinerjaRmMikroPayload('unit_pemutus', $period, true);
        $rows = collect($payload['rows'] ?? [])
            ->sortByDesc(fn (array $row) => (float) ($row['mtd_total_os'] ?? 0))
            ->take(24)
            ->map(fn (array $row): array => [
                'unit' => (string) ($row['unit'] ?? '-'),
                'cabang' => (string) ($row['cabang'] ?? '-'),
                'kaunit_deb' => (int) ($row['kaunit_mtd_deb'] ?? 0),
                'mbm_deb' => (int) ($row['mbm_mtd_deb'] ?? 0),
                'pinca_deb' => (int) ($row['pinca_mtd_deb'] ?? 0),
                'rmbh_deb' => (int) ($row['rmbh_mtd_deb'] ?? 0),
                'total_deb' => (int) ($row['mtd_total_deb'] ?? 0),
                'total_os' => (float) ($row['mtd_total_os'] ?? 0),
                'total_os_fmt' => $this->formatCurrencyCompact((float) ($row['mtd_total_os'] ?? 0)),
            ])
            ->values()
            ->all();

        return [
            'available' => !empty($rows),
            'source' => 'Kinerja RM Mikro - Unit per Pemutus',
            'rows' => $rows,
            'total' => [
                'total_deb' => (int) data_get($payload, 'total.mtd_total_deb', 0),
                'total_os' => (float) data_get($payload, 'total.mtd_total_os', 0.0),
                'total_os_fmt' => $this->formatCurrencyCompact((float) data_get($payload, 'total.mtd_total_os', 0.0)),
            ],
        ];
    }

    private function buildPresentationMantriProductivity(?string $period): array
    {
        $payload = $this->invokeKinerjaRmMikroPayload('produktivitas_mantri', $period, true);
        $rows = collect($payload['rows'] ?? [])
            ->sortByDesc(fn (array $row) => (float) ($row['realisasi_os'] ?? 0))
            ->take(24)
            ->map(fn (array $row): array => [
                'nama_mantri' => (string) ($row['nama_mantri'] ?? $row['pn_pengelola'] ?? '-'),
                'unit' => (string) ($row['unit'] ?? '-'),
                'cabang' => (string) ($row['cabang'] ?? '-'),
                'realisasi_deb' => (int) ($row['realisasi_deb'] ?? 0),
                'realisasi_os' => (float) ($row['realisasi_os'] ?? 0),
                'realisasi_os_fmt' => $this->formatCurrencyCompact((float) ($row['realisasi_os'] ?? 0)),
                'ratas_mantri_hk' => (float) ($row['ratas_mantri_hk'] ?? 0),
                'tiket_size' => (float) ($row['tiket_size'] ?? 0),
                'ket' => (string) ($row['ket'] ?? '-'),
            ])
            ->values()
            ->all();

        return [
            'available' => !empty($rows),
            'source' => 'Kinerja Mantri - Produktivitas per Mantri',
            'working_days' => (int) data_get($payload, 'working_days', 0),
            'rows' => $rows,
            'total' => [
                'jumlah_mantri' => (int) data_get($payload, 'total.jumlah_mantri', 0),
                'realisasi_deb' => (int) data_get($payload, 'total.realisasi_deb', 0),
                'realisasi_os' => (float) data_get($payload, 'total.realisasi_os', 0.0),
                'realisasi_os_fmt' => $this->formatCurrencyCompact((float) data_get($payload, 'total.realisasi_os', 0.0)),
            ],
        ];
    }

    private function buildPresentationRmKurMicro(?string $period): array
    {
        $payload = $this->invokeKinerjaRmMikroPayload('per_rm', $period, false);
        $rows = collect($payload['rows'] ?? [])
            ->sortByDesc(fn (array $row) => (float) ($row['realisasi_os'] ?? 0))
            ->take(24)
            ->map(fn (array $row): array => [
                'nama' => (string) ($row['nama'] ?? $row['rm'] ?? '-'),
                'unit' => (string) ($row['unit'] ?? '-'),
                'cabang' => (string) ($row['cabang'] ?? '-'),
                'total_deb' => (int) ($row['total_deb'] ?? 0),
                'total_os' => (float) ($row['total_os'] ?? 0),
                'total_os_fmt' => $this->formatCurrencyCompact((float) ($row['total_os'] ?? 0)),
                'realisasi_deb' => (int) ($row['realisasi_deb'] ?? 0),
                'realisasi_os' => (float) ($row['realisasi_os'] ?? 0),
                'realisasi_os_fmt' => $this->formatCurrencyCompact((float) ($row['realisasi_os'] ?? 0)),
            ])
            ->values()
            ->all();

        return [
            'available' => !empty($rows),
            'source' => 'Kinerja RM Mikro - RM KUR Mikro',
            'rows' => $rows,
            'total' => [
                'total_deb' => (int) data_get($payload, 'total.total_deb', 0),
                'total_os' => (float) data_get($payload, 'total.total_os', 0.0),
                'total_os_fmt' => $this->formatCurrencyCompact((float) data_get($payload, 'total.total_os', 0.0)),
                'realisasi_deb' => (int) data_get($payload, 'total.realisasi_deb', 0),
                'realisasi_os' => (float) data_get($payload, 'total.realisasi_os', 0.0),
                'realisasi_os_fmt' => $this->formatCurrencyCompact((float) data_get($payload, 'total.realisasi_os', 0.0)),
            ],
        ];
    }

    private function buildLandingExecutiveSummary(?string $loanPeriod, array $area6Portfolio): array
    {
        $detailPeriod = data_get($area6Portfolio, 'loan_detail_period') ?: $this->resolveArea6DailyLoanPeriod($loanPeriod);

        return [
            'title' => 'Ringkasan Eksekutif Area 6',
            'subtitle' => 'Laba rugi, putusan mikro, dan realisasi segmen pada periode aktif.',
            'profit' => $this->buildLandingProfitLossSummary(),
            'decision' => $this->buildLandingDecisionSummary($detailPeriod),
            'realization' => $this->buildLandingSegmentRealizationSummary($area6Portfolio),
        ];
    }

    private function emptyLandingExecutiveSummary(): array
    {
        return [
            'title' => 'Ringkasan Eksekutif Area 6',
            'subtitle' => 'Laba rugi, putusan mikro, dan realisasi segmen pada periode aktif.',
            'profit' => [
                'available' => false,
                'period' => null,
                'period_label' => 'Belum ada data',
                'total' => 0.0,
                'total_fmt' => 'Rp0',
                'delta_fmt' => '0,0%',
                'delta_class' => 'text-muted',
                'branches' => [],
            ],
            'decision' => [
                'available' => false,
                'period' => null,
                'period_label' => 'Belum ada data',
                'source' => 'Kinerja RM Mikro - Unit per Pemutus',
                'items' => [],
            ],
            'realization' => [
                'available' => false,
                'default_scope' => 'area6',
                'scopes' => [],
            ],
        ];
    }

    private function buildLandingProfitLossSummary(): array
    {
        $empty = $this->emptyLandingExecutiveSummary()['profit'];

        if (!$this->hasTable('ssa_almafacts')) {
            return $empty;
        }

        try {
            $period = DB::table('ssa_almafacts')
                ->where('keterangan', '15. Laba Setelah Pajak')
                ->whereIn('kanca_konsolidasi', $this->dashboardBranchDisplayNames())
                ->max('month_day_year_of_posisi');

            if (!$period) {
                return $empty;
            }

            $rows = DB::table('ssa_almafacts')
                ->select('kanca_konsolidasi', DB::raw('SUM(saldo) as nominal'))
                ->where('month_day_year_of_posisi', $period)
                ->where('keterangan', '15. Laba Setelah Pajak')
                ->whereIn('kanca_konsolidasi', $this->dashboardBranchDisplayNames())
                ->groupBy('kanca_konsolidasi')
                ->get()
                ->keyBy('kanca_konsolidasi');

            $branches = collect($this->dashboardBranchDisplayNames())
                ->map(function (string $branchName) use ($rows): array {
                    $nominal = (float) data_get($rows->get($branchName), 'nominal', 0.0);

                    return [
                        'name' => $branchName,
                        'nominal' => $nominal,
                        'nominal_fmt' => $this->formatCurrencyCompact($nominal),
                    ];
                })
                ->values()
                ->all();

            $total = array_sum(array_column($branches, 'nominal'));
            $previousPeriod = DB::table('ssa_almafacts')
                ->where('keterangan', '15. Laba Setelah Pajak')
                ->whereIn('kanca_konsolidasi', $this->dashboardBranchDisplayNames())
                ->where('month_day_year_of_posisi', '<', $period)
                ->max('month_day_year_of_posisi');
            $previousTotal = $previousPeriod
                ? (float) DB::table('ssa_almafacts')
                    ->where('month_day_year_of_posisi', $previousPeriod)
                    ->where('keterangan', '15. Laba Setelah Pajak')
                    ->whereIn('kanca_konsolidasi', $this->dashboardBranchDisplayNames())
                    ->sum('saldo')
                : 0.0;
            $delta = $this->percentChange($total, $previousTotal);

            return [
                'available' => $total !== 0.0,
                'period' => $period,
                'period_label' => $this->formatPeriodLabel($period),
                'total' => $total,
                'total_fmt' => $this->formatCurrencyCompact($total),
                'delta_fmt' => $this->formatSignedPercent($delta),
                'delta_class' => $this->deltaClass($delta),
                'branches' => $branches,
            ];
        } catch (Throwable $e) {
            Log::warning('Ringkasan laba rugi landing gagal dibaca.', [
                'error' => $e->getMessage(),
            ]);

            return $empty;
        }
    }

    private function buildLandingDecisionSummary(?string $period): array
    {
        $empty = $this->emptyLandingExecutiveSummary()['decision'];

        if (!$period) {
            return $empty;
        }

        $payload = $this->invokeKinerjaRmMikroPayload('unit_pemutus', $period, true);
        $total = (array) data_get($payload, 'total', []);
        $kurRitelTotals = $this->buildLandingKurRitelDecisionTotals($period);

        $items = [
            [
                'key' => 'pinca_boh',
                'label' => 'Pinca/BOH',
                'deb' => (int) ($total['pinca_mtd_deb'] ?? 0) + (int) data_get($kurRitelTotals, 'pinca.deb', 0),
                'nominal' => (float) ($total['pinca_mtd_os'] ?? 0.0) + (float) data_get($kurRitelTotals, 'pinca.nominal', 0.0),
                'kur_ritel_deb' => (int) data_get($kurRitelTotals, 'pinca.deb', 0),
                'kur_ritel_nominal' => (float) data_get($kurRitelTotals, 'pinca.nominal', 0.0),
                'icon' => 'fas fa-user-tie',
            ],
            [
                'key' => 'k_unit',
                'label' => 'K Unit',
                'deb' => (int) ($total['kaunit_mtd_deb'] ?? 0) + (int) data_get($kurRitelTotals, 'kaunit.deb', 0),
                'nominal' => (float) ($total['kaunit_mtd_os'] ?? 0.0) + (float) data_get($kurRitelTotals, 'kaunit.nominal', 0.0),
                'kur_ritel_deb' => (int) data_get($kurRitelTotals, 'kaunit.deb', 0),
                'kur_ritel_nominal' => (float) data_get($kurRitelTotals, 'kaunit.nominal', 0.0),
                'icon' => 'fas fa-store-alt',
            ],
            [
                'key' => 'mbm',
                'label' => 'MBM',
                'deb' => (int) ($total['mbm_mtd_deb'] ?? 0) + (int) data_get($kurRitelTotals, 'mbm.deb', 0),
                'nominal' => (float) ($total['mbm_mtd_os'] ?? 0.0) + (float) data_get($kurRitelTotals, 'mbm.nominal', 0.0),
                'kur_ritel_deb' => (int) data_get($kurRitelTotals, 'mbm.deb', 0),
                'kur_ritel_nominal' => (float) data_get($kurRitelTotals, 'mbm.nominal', 0.0),
                'icon' => 'fas fa-user-shield',
            ],
        ];

        $items = collect($items)
            ->map(function (array $item): array {
                $item['nominal_fmt'] = $this->formatCurrencyCompact((float) $item['nominal']);
                $item['deb_fmt'] = $this->formatInteger((int) $item['deb']) . ' deb';
                $item['kur_ritel_nominal_fmt'] = $this->formatCurrencyCompact((float) $item['kur_ritel_nominal']);
                $item['kur_ritel_deb_fmt'] = $this->formatInteger((int) $item['kur_ritel_deb']) . ' deb';
                $item['kur_ritel_note'] = (int) $item['kur_ritel_deb'] > 0
                    ? 'KUR Ritel 2015: ' . $item['kur_ritel_deb_fmt'] . ' | ' . $item['kur_ritel_nominal_fmt']
                    : null;

                return $item;
            })
            ->all();

        $kurRitelDeb = array_sum(array_column($items, 'kur_ritel_deb'));
        $kurRitelNominal = array_sum(array_column($items, 'kur_ritel_nominal'));

        return [
            'available' => collect($items)->contains(fn (array $item) => (int) $item['deb'] > 0 || (float) $item['nominal'] !== 0.0),
            'period' => $period,
            'period_label' => $this->formatPeriodLabel($period),
            'source' => 'Kinerja RM Mikro - Unit per Pemutus, termasuk KUR Ritel 2015',
            'items' => $items,
            'total_deb' => array_sum(array_column($items, 'deb')),
            'total_nominal' => array_sum(array_column($items, 'nominal')),
            'total_deb_fmt' => $this->formatInteger((int) array_sum(array_column($items, 'deb'))) . ' deb',
            'total_nominal_fmt' => $this->formatCurrencyCompact((float) array_sum(array_column($items, 'nominal'))),
            'kur_ritel_deb' => $kurRitelDeb,
            'kur_ritel_nominal' => $kurRitelNominal,
            'kur_ritel_deb_fmt' => $this->formatInteger((int) $kurRitelDeb) . ' deb',
            'kur_ritel_nominal_fmt' => $this->formatCurrencyCompact((float) $kurRitelNominal),
            'note' => 'Termasuk KUR Ritel 2015 pada bucket Pinca/BOH, K Unit, dan MBM.',
        ];
    }

    private function buildLandingKurRitelDecisionTotals(string $period): array
    {
        $blank = [
            'pinca' => ['deb' => 0, 'nominal' => 0.0],
            'kaunit' => ['deb' => 0, 'nominal' => 0.0],
            'mbm' => ['deb' => 0, 'nominal' => 0.0],
        ];

        if (!$this->hasTable('daily_loan_dinamis')) {
            return $blank;
        }

        $requiredColumns = ['periode', 'segmen_kinerja', 'produk_kinerja', 'description', 'tgl_realisasi', 'plafon'];
        foreach ($requiredColumns as $column) {
            if (!$this->hasColumn('daily_loan_dinamis', $column)) {
                return $blank;
            }
        }

        $periodStart = Carbon::parse($period)->startOfMonth()->toDateString();
        $descriptionSql = $this->normalizedSql('d.description');
        $kurRitelToken = $this->normalizeToken('Kredit Mikro - KUR Ritel 2015');
        $pemutusSql = $this->hasColumn('daily_loan_dinamis', 'pn_pemutus_normalized')
            ? "NULLIF(d.pn_pemutus_normalized, '')"
            : "NULLIF(TRIM(LEADING '0' FROM TRIM(SUBSTRING_INDEX(COALESCE(d.pn_pemutus1, ''), '-', 1))), '')";
        $rekeningSql = $this->hasColumn('daily_loan_dinamis', 'nomor_rekening1')
            ? "COALESCE(NULLIF(d.nomor_rekening1, ''), CONCAT(COALESCE(d.branch1, ''), '-', COALESCE(d.pn_pengelola1, ''), '-', COALESCE(d.plafon, ''), '-', COALESCE(d.tgl_realisasi, '')))"
            : "CONCAT(COALESCE(d.branch1, ''), '-', COALESCE(d.pn_pengelola1, ''), '-', COALESCE(d.plafon, ''), '-', COALESCE(d.tgl_realisasi, ''))";
        $jabatanSql = $this->hasTable('brihc')
            ? "UPPER(TRIM(COALESCE(b.jabatan, '')))"
            : "''";
        $roleSql = "CASE"
            . " WHEN {$jabatanSql} LIKE '%BOH%' OR {$jabatanSql} LIKE '%PINCA%' OR {$jabatanSql} LIKE '%PIMPINAN CABANG%' THEN 'pinca'"
            . " WHEN {$jabatanSql} LIKE '%RMBH%' THEN 'rmbh'"
            . " WHEN {$jabatanSql} LIKE '%MBM%' THEN 'mbm'"
            . " WHEN {$jabatanSql} LIKE '%KAUNIT%' OR {$jabatanSql} LIKE '%KEPALA UNIT%' THEN 'kaunit'"
            . " WHEN COALESCE(d.plafon, 0) <= 100000000 THEN 'kaunit'"
            . " WHEN COALESCE(d.plafon, 0) <= 250000000 THEN 'mbm'"
            . " ELSE 'pinca' END";

        $base = DB::table('daily_loan_dinamis as d')
            ->where('d.periode', $period)
            ->where('d.segmen_kinerja', 'MICRO')
            ->where('d.produk_kinerja', 'KURMIKRO')
            ->whereRaw("{$descriptionSql} = ?", [$kurRitelToken])
            ->whereBetween('d.tgl_realisasi', [$periodStart, $period]);

        if ($this->hasTable('brihc')) {
            $base->leftJoin('brihc as b', function ($join) use ($pemutusSql): void {
                $join->on('b.pn', '=', DB::raw($pemutusSql));
            });
        }

        $rows = $base
            ->selectRaw("{$roleSql} as role_key")
            ->selectRaw("{$rekeningSql} as rekening_key")
            ->selectRaw('COALESCE(d.plafon, 0) as nominal')
            ->get();

        foreach ($rows as $row) {
            $roleKey = (string) ($row->role_key ?? '');
            if (!isset($blank[$roleKey])) {
                continue;
            }

            $nominal = (float) ($row->nominal ?? 0.0);
            $rekening = trim((string) ($row->rekening_key ?? ''));

            $blank[$roleKey]['nominal'] += $nominal;
            $blank[$roleKey]['rekening_keys'][$rekening] = true;
        }

        foreach ($blank as $key => $row) {
            $blank[$key]['deb'] = count($row['rekening_keys'] ?? []);
            unset($blank[$key]['rekening_keys']);
        }

        return $blank;
    }

    private function buildLandingSegmentRealizationSummary(array $area6Portfolio): array
    {
        $source = data_get($area6Portfolio, 'scopes.area6.segment_performance');
        if (!$source) {
            return [
                'available' => false,
                'default_scope' => 'area6',
                'scopes' => [],
            ];
        }

        $segmentsByKey = collect(data_get($source, 'segments', []))
            ->mapWithKeys(function (array $segment): array {
                $key = $this->landingSegmentKey((string) ($segment['label'] ?? ''));

                return $key ? [$key => $segment] : [];
            });

        $segments = collect(['sme', 'consumer', 'micro'])
            ->map(function (string $segmentKey) use ($segmentsByKey): ?array {
                $segment = $segmentsByKey->get($segmentKey);
                if (!$segment) {
                    return null;
                }

                return $this->formatLandingRealizationSegment($segmentKey, $segment);
            })
            ->filter()
            ->values();

        $totalRealization = (float) $segments->sum('realization');
        $totalTarget = (float) $segments->sum('target');
        $totalPct = $totalTarget > 0 ? ($totalRealization / $totalTarget) * 100 : 0.0;
        $area6Total = [
            'key' => 'area6_total',
            'label' => 'Area 6 Total',
            'icon' => 'fas fa-layer-group',
            'realization' => $totalRealization,
            'target' => $totalTarget,
            'realization_fmt' => $this->formatCurrencyCompact($totalRealization),
            'target_fmt' => $this->formatCurrencyCompact($totalTarget),
            'pct_fmt' => number_format($totalPct, 2, ',', '.') . '%',
            'pct_color' => $this->getArea6AchievementColor($totalPct, 'os'),
        ];

        $scopes = [
            'area6' => [
                'label' => 'Area 6',
                'segments' => array_merge([$area6Total], $segments->all()),
            ],
            'sme' => [
                'label' => 'SME',
                'segments' => $segments->where('key', 'sme')->values()->all(),
            ],
            'consumer' => [
                'label' => 'Konsumer',
                'segments' => $segments->where('key', 'consumer')->values()->all(),
            ],
            'micro' => [
                'label' => 'Micro',
                'segments' => $segments->where('key', 'micro')->values()->all(),
            ],
        ];

        return [
            'available' => !empty($scopes),
            'default_scope' => 'area6',
            'scopes' => $scopes,
        ];
    }

    private function formatLandingRealizationSegment(string $segmentKey, array $segment): array
    {
        $os = (array) data_get($segment, 'os', []);
        $realization = (float) data_get($os, 'realization', 0.0);
        $target = (float) data_get($os, 'target', 0.0);

        return [
            'key' => $segmentKey,
            'label' => $this->landingSegmentDisplayLabel($segmentKey),
            'icon' => (string) data_get($segment, 'icon', 'fas fa-chart-line'),
            'realization' => $realization,
            'target' => $target,
            'realization_fmt' => $this->formatCurrencyCompact($realization),
            'target_fmt' => $this->formatCurrencyCompact($target),
            'pct_fmt' => (string) data_get($os, 'pct_fmt', '0,00%'),
            'pct_color' => (string) data_get($os, 'pct_color', 'blue'),
        ];
    }

    private function landingSegmentKey(string $label): ?string
    {
        $normalized = strtoupper($label);

        if (str_contains($normalized, 'MIKRO')) {
            return 'micro';
        }

        if (str_contains($normalized, 'KONSUMER') || str_contains($normalized, 'CONSUMER')) {
            return 'consumer';
        }

        if (str_contains($normalized, 'SME') || str_contains($normalized, 'KECIL')) {
            return 'sme';
        }

        return null;
    }

    private function landingSegmentDisplayLabel(string $key): string
    {
        return match ($key) {
            'micro' => 'Micro',
            'consumer' => 'Konsumer',
            'sme' => 'SME / Small',
            default => strtoupper($key),
        };
    }

    private function invokeKinerjaRmMikroPayload(string $category, ?string $period, bool $mantri): array
    {
        if (!$period) {
            return ['rows' => [], 'total' => []];
        }

        $cacheKey = 'dashboard_simpanan:kinerja_rm_mikro:'
            . ($mantri ? 'mantri' : 'report') . ':'
            . $category . ':'
            . $period . ':v'
            . $this->reportCacheVersion();

        return Cache::remember($cacheKey, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES), function () use ($category, $period, $mantri) {
            try {
                $controller = app(\App\Http\Controllers\Report\KinerjaRmMikroReportController::class);
                $method = new \ReflectionMethod($controller, $mantri ? 'buildMantriPayload' : 'buildReportPayload');
                $method->setAccessible(true);

                return (array) $method->invoke($controller, $category, $period);
            } catch (Throwable $e) {
                Log::warning('Payload presentasi Kinerja RM Mikro gagal dibaca.', [
                    'category' => $category,
                    'period' => $period,
                    'error' => $e->getMessage(),
                ]);

                return ['rows' => [], 'total' => [], 'message' => 'Data belum tersedia'];
            }
        });
    }

    private function buildPresentationQuality(array $area6Portfolio): array
    {
        $scope = data_get($area6Portfolio, 'scopes.cabang_konsol', $area6Portfolio);
        $cards = collect(data_get($scope, 'cards', []))->keyBy('key');
        $rankingModes = data_get($area6Portfolio, 'legacy_ranking_modes', data_get($area6Portfolio, 'ranking_modes', []));

        return [
            'sml' => [
                'title' => 'Kinerja SML Area 6 - Region Malang',
                'card' => $cards->get('sml', []),
                'ritel_nominal' => $this->extractPresentationRankingRows($rankingModes, 'ritel', '5 SML Nominal'),
                'micro_nominal' => $this->extractPresentationRankingRows($rankingModes, 'micro', '5 SML Nominal'),
                'ritel_ratio' => $this->extractPresentationRankingRows($rankingModes, 'ritel', '5 SML Rasio'),
                'micro_ratio' => $this->extractPresentationRankingRows($rankingModes, 'micro', '5 SML Rasio'),
            ],
            'npl' => [
                'title' => 'Kinerja NPL Area 6 - Region Malang',
                'card' => $cards->get('npl', []),
                'ritel_nominal' => $this->extractPresentationRankingRows($rankingModes, 'ritel', '5 NPL Nominal'),
                'micro_nominal' => $this->extractPresentationRankingRows($rankingModes, 'micro', '5 NPL Nominal'),
                'ritel_ratio' => $this->extractPresentationRankingRows($rankingModes, 'ritel', '5 NPL Rasio'),
                'micro_ratio' => $this->extractPresentationRankingRows($rankingModes, 'micro', '5 NPL Rasio'),
            ],
        ];
    }

    private function buildPresentationKts(array $area6Portfolio, ?string $period): array
    {
        $ktsRetail = $this->buildArea6KtsRanking($period, 'retail');
        $ktsMicro = $this->buildArea6KtsRanking($period, 'unit');
        $categories = [
            'membaik' => [
                'label' => 'KTS Membaik',
                'ritel' => $this->buildPresentationKtsCategoryRanking($period, 'retail', 'membaik'),
                'micro' => $this->buildPresentationKtsCategoryRanking($period, 'unit', 'membaik'),
            ],
            'memburuk' => [
                'label' => 'KTS Memburuk',
                'ritel' => $this->buildPresentationKtsCategoryRanking($period, 'retail', 'memburuk'),
                'micro' => $this->buildPresentationKtsCategoryRanking($period, 'unit', 'memburuk'),
            ],
        ];

        return [
            'period' => $period,
            'period_label' => $this->formatPeriodLabel($period),
            'source' => 'daily_loan_dinamis',
            'ritel_total' => $ktsRetail['total_count'] ?? 0,
            'ritel' => $ktsRetail['rows'] ?? [],
            'micro_total' => $ktsMicro['total_count'] ?? 0,
            'micro' => $ktsMicro['rows'] ?? [],
            'categories' => $categories,
        ];
    }

    private function buildPresentationKtsSummary(?string $period): array
    {
        $emptyScope = [
            'total_count' => 0,
            'total_os' => 0.0,
            'total_os_fmt' => 'Rp0',
            'rows' => [],
            'branches' => [],
        ];

        return [
            'period' => $period,
            'period_label' => $this->formatPeriodLabel($period),
            'source' => 'daily_loan_dinamis',
            'loading_details' => true,
            'ritel_total' => 0,
            'ritel' => [],
            'micro_total' => 0,
            'micro' => [],
            'categories' => [
                'membaik' => [
                    'label' => 'KTS Membaik',
                    'ritel' => $emptyScope,
                    'micro' => $emptyScope,
                ],
                'memburuk' => [
                    'label' => 'KTS Memburuk',
                    'ritel' => $emptyScope,
                    'micro' => $emptyScope,
                ],
            ],
        ];
    }

    private function buildPresentationKtsCategoryRanking(?string $period, string $scope, string $category): array
    {
        $empty = ['total_count' => 0, 'total_os' => 0.0, 'total_os_fmt' => 'Rp0', 'rows' => [], 'branches' => []];
        if (!$period || !Schema::hasTable('daily_loan_dinamis')) {
            return $empty;
        }

        foreach (['cabang1', 'unit1', 'status_rekening1', 'baki_debet1', 'kolek', 'umur_tunggakan', 'nomor_rekening1', 'nama_debitur1'] as $column) {
            if (!Schema::hasColumn('daily_loan_dinamis', $column)) {
                return $empty;
            }
        }

        $cacheKey = 'dashboard_simpanan:presentation_kts_category:'
            . self::LANDING_SOURCE_CACHE_VERSION . ':v'
            . $this->reportCacheVersion() . ':'
            . $period . ':' . $scope . ':' . $category;

        return Cache::remember($cacheKey, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES), function () use ($period, $scope, $category): array {
            $actualKolekExpression = "CAST(kolek AS UNSIGNED)";
            $umurTunggakanExpression = "CAST(umur_tunggakan AS SIGNED)";
            $expectedKolekExpression = "CASE
                WHEN {$umurTunggakanExpression} <= 0 THEN 1
                WHEN {$umurTunggakanExpression} <= 90 THEN 2
                WHEN {$umurTunggakanExpression} <= 120 THEN 3
                WHEN {$umurTunggakanExpression} <= 180 THEN 4
                ELSE 5
            END";
            $directionSql = $category === 'membaik'
                ? "{$actualKolekExpression} < {$expectedKolekExpression}"
                : "{$actualKolekExpression} > {$expectedKolekExpression}";
            $groupColumns = $scope === 'branch' ? ['cabang1'] : ['cabang1', 'unit1'];

            $baseQuery = DB::table('daily_loan_dinamis')
                ->where('periode', $period)
                ->whereIn('cabang1', $this->dashboardBranchDisplayNames())
                ->whereIn('status_rekening1', ['1', '3'])
                ->where('baki_debet1', '>', 0)
                ->whereIn('kolek', ['1', '2', '3', '4', '5'])
                ->whereNotNull('umur_tunggakan')
                ->whereRaw($directionSql);

            $this->applyArea6DailyLoanUnitScope($baseQuery, $scope);

            $rankedRows = (clone $baseQuery)
                ->select($groupColumns)
                ->selectRaw('COUNT(*) as mismatch_count')
                ->selectRaw('COALESCE(SUM(COALESCE(baki_debet1, 0)), 0) as outstanding_balance')
                ->groupBy($groupColumns)
                ->orderByDesc('mismatch_count')
                ->orderByDesc('outstanding_balance')
                ->limit(18)
                ->get();

            $total = (clone $baseQuery)
                ->selectRaw('COUNT(*) as mismatch_count')
                ->selectRaw('COALESCE(SUM(COALESCE(baki_debet1, 0)), 0) as outstanding_balance')
                ->first();

            $branchTotals = (clone $baseQuery)
                ->select('cabang1')
                ->selectRaw('COUNT(*) as mismatch_count')
                ->selectRaw('COALESCE(SUM(COALESCE(baki_debet1, 0)), 0) as outstanding_balance')
                ->groupBy('cabang1')
                ->get()
                ->keyBy('cabang1');

            $detailLimitPerBranch = 30;
            $branchesData = [];
            foreach ($this->dashboardBranchDisplayNames() as $branchName) {
                $branchTotal = $branchTotals->get($branchName);
                $branchTotalCount = (int) ($branchTotal->mismatch_count ?? 0);
                $branchTotalOs = (float) ($branchTotal->outstanding_balance ?? 0);

                $debiturs = (clone $baseQuery)
                    ->where('cabang1', $branchName)
                    ->select([
                        'nomor_rekening1',
                        'nama_debitur1',
                        'baki_debet1',
                        'kolek',
                        'umur_tunggakan',
                        'unit1'
                    ])
                    ->orderByDesc('baki_debet1')
                    ->limit($detailLimitPerBranch)
                    ->get();

                $debitursMapped = $debiturs->map(function ($deb, int $idx): array {
                    $arrears = (int) ($deb->umur_tunggakan ?? 0);
                    $expected = 1;
                    if ($arrears <= 0) {
                        $expected = 1;
                    } elseif ($arrears <= 90) {
                        $expected = 2;
                    } elseif ($arrears <= 120) {
                        $expected = 3;
                    } elseif ($arrears <= 180) {
                        $expected = 4;
                    } else {
                        $expected = 5;
                    }

                    $actual = (int) ($deb->kolek ?? 1);

                    return [
                        'rank' => $idx + 1,
                        'nomor_rekening' => (string) ($deb->nomor_rekening1 ?? '-'),
                        'nama_debitur' => (string) ($deb->nama_debitur1 ?? '-'),
                        'baki_debet' => (float) ($deb->baki_debet1 ?? 0),
                        'baki_debet_fmt' => $this->formatCurrencyCompact((float) ($deb->baki_debet1 ?? 0)),
                        'kolek_aktual' => $actual,
                        'kolek_seharusnya' => $expected,
                        'umur_tunggakan' => $arrears,
                        'unit' => (string) ($deb->unit1 ?? '-'),
                    ];
                })->all();

                $branchesData[] = [
                    'branch_name' => $branchName,
                    'total_count' => $branchTotalCount,
                    'total_os' => $branchTotalOs,
                    'total_os_fmt' => $this->formatCurrencyCompact($branchTotalOs),
                    'shown_count' => count($debitursMapped),
                    'is_limited' => $branchTotalCount > count($debitursMapped),
                    'debiturs' => $debitursMapped,
                ];
            }

            return [
                'total_count' => (int) ($total->mismatch_count ?? 0),
                'total_os' => (float) ($total->outstanding_balance ?? 0),
                'total_os_fmt' => $this->formatCurrencyCompact((float) ($total->outstanding_balance ?? 0)),
                'rows' => $rankedRows->map(function ($row, int $index) use ($scope): array {
                    return [
                        'rank' => $index + 1,
                        'label' => $scope === 'branch' ? (string) ($row->cabang1 ?? '-') : (string) ($row->unit1 ?? '-'),
                        'meta' => in_array($scope, ['unit', 'unit_kerja', 'retail'], true) ? (string) ($row->cabang1 ?? 'Area 6') : 'Area 6',
                        'value' => $this->formatInteger((int) ($row->mismatch_count ?? 0)) . ' rek',
                        'sub' => $this->formatCurrencyCompact((float) ($row->outstanding_balance ?? 0)),
                    ];
                })->all(),
                'branches' => $branchesData,
            ];
        });
    }

    private function queryPresentationKtsCategoryTotalsForPeriods(array $periods): array
    {
        $periods = array_values(array_unique(array_filter(array_map(
            fn ($period): ?string => $period ? Carbon::parse($period)->toDateString() : null,
            $periods
        ))));

        if (empty($periods) || !Schema::hasTable('daily_loan_dinamis')) {
            return [];
        }

        foreach (['periode', 'cabang1', 'unit1', 'status_rekening1', 'baki_debet1', 'kolek', 'umur_tunggakan'] as $column) {
            if (!Schema::hasColumn('daily_loan_dinamis', $column)) {
                return [];
            }
        }

        $cacheKey = 'dashboard_simpanan:presentation_kts_category_totals:'
            . self::LANDING_SOURCE_CACHE_VERSION . ':v'
            . $this->reportCacheVersion() . ':'
            . md5(implode('|', $periods));

        return Cache::remember($cacheKey, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES), function () use ($periods): array {
            $actualKolekExpression = "CAST(kolek AS UNSIGNED)";
            $umurTunggakanExpression = "CAST(umur_tunggakan AS SIGNED)";
            $expectedKolekExpression = "CASE
                WHEN {$umurTunggakanExpression} <= 0 THEN 1
                WHEN {$umurTunggakanExpression} <= 90 THEN 2
                WHEN {$umurTunggakanExpression} <= 120 THEN 3
                WHEN {$umurTunggakanExpression} <= 180 THEN 4
                ELSE 5
            END";
            $presentationScopeSql = "(UPPER(TRIM(unit1)) LIKE 'KC %'
                OR UPPER(TRIM(unit1)) LIKE 'KCP %'
                OR UPPER(TRIM(unit1)) LIKE 'UNIT %')";

            return DB::table('daily_loan_dinamis')
                ->whereIn('periode', $periods)
                ->whereIn('cabang1', $this->dashboardBranchDisplayNames())
                ->whereIn('status_rekening1', ['1', '3'])
                ->where('baki_debet1', '>', 0)
                ->whereRaw($presentationScopeSql)
                ->whereIn('kolek', ['1', '2', '3', '4', '5'])
                ->whereNotNull('umur_tunggakan')
                ->selectRaw('periode')
                ->selectRaw("SUM(CASE WHEN {$actualKolekExpression} < {$expectedKolekExpression} THEN 1 ELSE 0 END) as membaik")
                ->selectRaw("SUM(CASE WHEN {$actualKolekExpression} > {$expectedKolekExpression} THEN 1 ELSE 0 END) as memburuk")
                ->groupBy('periode')
                ->get()
                ->mapWithKeys(fn ($row): array => [
                    Carbon::parse($row->periode)->toDateString() => [
                        'membaik' => (int) ($row->membaik ?? 0),
                        'memburuk' => (int) ($row->memburuk ?? 0),
                    ],
                ])
                ->all();
        });
    }

    private function queryPresentationKtsCategoryTotal(?string $period, string $category): ?float
    {
        if (!$period || !Schema::hasTable('daily_loan_dinamis')) {
            return null;
        }

        foreach (['cabang1', 'unit1', 'status_rekening1', 'baki_debet1', 'kolek', 'umur_tunggakan'] as $column) {
            if (!Schema::hasColumn('daily_loan_dinamis', $column)) {
                return null;
            }
        }

        $actualKolekExpression = "CAST(kolek AS UNSIGNED)";
        $umurTunggakanExpression = "CAST(umur_tunggakan AS SIGNED)";
        $expectedKolekExpression = "CASE
            WHEN {$umurTunggakanExpression} <= 0 THEN 1
            WHEN {$umurTunggakanExpression} <= 90 THEN 2
            WHEN {$umurTunggakanExpression} <= 120 THEN 3
            WHEN {$umurTunggakanExpression} <= 180 THEN 4
            ELSE 5
        END";
        $directionSql = $category === 'membaik'
            ? "{$actualKolekExpression} < {$expectedKolekExpression}"
            : "{$actualKolekExpression} > {$expectedKolekExpression}";

        $total = 0;

        foreach (['retail', 'unit'] as $scope) {
            $query = DB::table('daily_loan_dinamis')
                ->where('periode', $period)
                ->whereIn('cabang1', $this->dashboardBranchDisplayNames())
                ->whereIn('status_rekening1', ['1', '3'])
                ->where('baki_debet1', '>', 0)
                ->whereIn('kolek', ['1', '2', '3', '4', '5'])
                ->whereNotNull('umur_tunggakan')
                ->whereRaw($directionSql);

            $this->applyArea6DailyLoanUnitScope($query, $scope);

            $total += (int) $query->count();
        }

        return (float) $total;
    }

    private function extractPresentationRankingRows(array $rankingModes, string $scope, string $title): array
    {
        $groups = data_get($rankingModes, $scope . '.rankings', []);
        foreach ($groups as $group) {
            if (($group['title'] ?? '') === $title) {
                return array_values($group['rows'] ?? []);
            }
        }

        return [];
    }

    private function buildPresentationDigitalStrategy(array $digitalPerformance): array
    {
        $cards = collect(data_get($digitalPerformance, 'cards', []))->keyBy('key');
        $order = ['edc', 'qris', 'qlola', 'brimo', 'brilink', 'casa', 'dormant', 'payroll'];

        return [
            'updated_at' => data_get($digitalPerformance, 'updated_at'),
            'cards' => collect($order)->map(function (string $key) use ($cards): array {
                $card = $cards->get($key);
                if (!$card) {
                    return [
                        'key' => $key,
                        'title' => strtoupper($key),
                        'available' => false,
                        'current_value' => 'Data belum tersedia',
                        'secondary_value' => '-',
                        'trend' => '-',
                        'source' => $this->defaultDigitalSourceTable($key),
                    ];
                }

                return [
                    'key' => $key,
                    'title' => (string) data_get($card, 'title', strtoupper($key)),
                    'available' => data_get($card, 'current_value') !== '-',
                    'current_value' => (string) data_get($card, 'current_value', 'Data belum tersedia'),
                    'current_label' => (string) data_get($card, 'current_label', ''),
                    'secondary_value' => (string) data_get($card, 'secondary_value', '-'),
                    'secondary_label' => (string) data_get($card, 'secondary_label', ''),
                    'trend' => (string) data_get($card, 'trend', '-'),
                    'source_updated_at' => data_get($card, 'source_updated_at'),
                    'source' => (string) data_get($card, 'source_table', $this->defaultDigitalSourceTable($key)),
                    'stats' => data_get($card, 'stats', []),
                ];
            })->all(),
        ];
    }

    private function buildDashboardPayload(?string $selectedPeriod = null): array
    {
        $cacheVersion = $this->reportCacheVersion();
        
        if ($selectedPeriod) {
            $payloadCacheKey = 'dashboard_simpanan:payload:' . $selectedPeriod . ':' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $cacheVersion;
            
            return Cache::remember($payloadCacheKey, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES), function () use ($selectedPeriod) {
                return $this->buildDashboardPayloadFresh($selectedPeriod);
            });
        }

        $payloadCacheKey = 'dashboard_simpanan:payload:' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $cacheVersion;
        $latestCacheKey = 'dashboard_simpanan:payload:' . self::LANDING_SOURCE_CACHE_VERSION . ':latest:v' . $cacheVersion;
        $stableLatestCacheKey = 'dashboard_simpanan:payload:' . self::LANDING_SOURCE_CACHE_VERSION . ':latest:stable:v' . $cacheVersion;
        $cachedPayload = Cache::get($payloadCacheKey);

        if (is_array($cachedPayload)) {
            return $cachedPayload;
        }

        $latestPayload = Cache::get($latestCacheKey);

        if (is_array($latestPayload)) {
            Cache::put($payloadCacheKey, $latestPayload, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES));
            $this->deferDashboardPayloadRefresh($payloadCacheKey, $latestCacheKey, $stableLatestCacheKey);

            return $latestPayload;
        }

        $stableLatestPayload = Cache::get($stableLatestCacheKey);

        if (is_array($stableLatestPayload)) {
            Cache::put($payloadCacheKey, $stableLatestPayload, now()->addSeconds(30));
            $this->deferDashboardPayloadRefresh($payloadCacheKey, $latestCacheKey, $stableLatestCacheKey);

            return $stableLatestPayload;
        }

        $lock = Cache::lock($payloadCacheKey . ':lock', self::CACHE_LOCK_SECONDS);
        $locked = false;

        try {
            $locked = $lock->get();

            if ($locked) {
                return $this->cacheFreshDashboardPayload($payloadCacheKey, $latestCacheKey, $stableLatestCacheKey);
            }
        } catch (Throwable $e) {
            Log::warning('Dashboard simpanan payload gagal dimuat langsung.', [
                'error' => $e->getMessage(),
            ]);
        } finally {
            if ($locked) {
                $lock->release();
            }
        }

        return $this->emptyDashboard(false);
    }

    private function cacheFreshDashboardPayload(string $payloadCacheKey, string $latestCacheKey, string $stableLatestCacheKey): array
    {
        $freshPayload = $this->buildDashboardPayloadFresh();
        Cache::put($payloadCacheKey, $freshPayload, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES));
        Cache::put($latestCacheKey, $freshPayload, now()->addMinutes(self::SUMMARY_LATEST_CACHE_MINUTES));
        Cache::put($stableLatestCacheKey, $freshPayload, now()->addMinutes(self::SUMMARY_LATEST_CACHE_MINUTES));

        return $freshPayload;
    }

    private function deferDashboardPayloadRefresh(string $payloadCacheKey, string $latestCacheKey, string $stableLatestCacheKey): void
    {
        app()->terminating(function () use ($payloadCacheKey, $latestCacheKey, $stableLatestCacheKey) {
            $lock = Cache::lock($payloadCacheKey . ':lock', self::CACHE_LOCK_SECONDS);
            $locked = false;

            try {
                $locked = $lock->get();

                if (!$locked) {
                    return;
                }

                $this->cacheFreshDashboardPayload($payloadCacheKey, $latestCacheKey, $stableLatestCacheKey);
            } catch (Throwable $e) {
                Log::warning('Dashboard simpanan payload gagal dihangatkan setelah response.', [
                    'error' => $e->getMessage(),
                ]);
            } finally {
                if ($locked) {
                    $lock->release();
                }
            }
        });
    }

    private function buildDashboardPayloadFresh(?string $selectedPeriod = null, bool $forceFresh = false): array
    {
        if (!Schema::hasTable('simpanan_multipn') && !Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return $this->emptyDashboard();
        }

        $scopeLabel = $this->dashboardScopeLabel();
        [$currentPeriod, $previousPeriod, $yoyPeriod] = $this->resolveDashboardPeriods($selectedPeriod);
        [$loanCurrentPeriod, $loanPreviousPeriod, $loanYoyPeriod] = $this->resolveLoanDashboardPeriods($selectedPeriod);

        if (!$currentPeriod) {
            return $this->emptyDashboard();
        }

        $currentSummary = $this->buildPeriodSummary($currentPeriod, $forceFresh);
        $previousSummary = $previousPeriod ? $this->buildPeriodSummary($previousPeriod, $forceFresh) : $this->emptySummary();
        $yoySummary = $yoyPeriod ? $this->buildPeriodSummary($yoyPeriod, $forceFresh) : $this->emptySummary();
        $loanCurrentSummary = $loanCurrentPeriod ? $this->buildLoanSummary($loanCurrentPeriod) : $this->emptyLoanSummary();
        $loanPreviousSummary = $loanPreviousPeriod ? $this->buildLoanSummary($loanPreviousPeriod) : $this->emptyLoanSummary();
        $loanYoySummary = $loanYoyPeriod ? $this->buildLoanSummary($loanYoyPeriod) : $this->emptyLoanSummary();

        $topBranches = $this->fetchTopBranches($currentPeriod, $forceFresh);
        $loanTopBranches = $loanCurrentPeriod ? $this->fetchLoanTopBranches($loanCurrentPeriod, $forceFresh) : collect();
        $composition = $this->buildComposition($currentSummary);
        $latestUpdatedAt = $currentSummary['source_updated_at'] ?? null;
        $topBranchLabel = data_get($topBranches->first(), 'label', 'Cabang belum tersedia');
        $topBranchDisplay = data_get($topBranches->first(), 'display', '-');
        $loanTopBranchLabel = data_get($loanTopBranches->first(), 'label', 'Cabang belum tersedia');
        $loanTopBranchDisplay = data_get($loanTopBranches->first(), 'display', '-');
        $savingsMoM = $this->percentChange($currentSummary['total_balance'], $previousSummary['total_balance']);
        $loanMoM = $this->percentChange($loanCurrentSummary['total_balance'], $loanPreviousSummary['total_balance']);
        $coverageNow = $this->formatRatio($loanCurrentSummary['total_balance'], $currentSummary['total_balance']);
        $coveragePrev = $this->formatRatio($loanPreviousSummary['total_balance'], $previousSummary['total_balance']);
        $coverageChange = $this->percentChange(
            $currentSummary['total_balance'] > 0 ? $loanCurrentSummary['total_balance'] / $currentSummary['total_balance'] : 0,
            $previousSummary['total_balance'] > 0 ? $loanPreviousSummary['total_balance'] / $previousSummary['total_balance'] : 0
        );
        $latestCombinedLabel = trim(sprintf(
            'Simpanan %s | Pinjaman %s',
            $this->formatPeriodLabel($currentPeriod),
            $loanCurrentPeriod ? $this->formatPeriodLabel($loanCurrentPeriod) : 'Belum ada data'
        ));
        $digitalPerformance = $this->buildDigitalPerformance();
        $timeseries = $this->buildTimeseriesPayload($currentPeriod, $loanCurrentPeriod);
        $area6Portfolio = $this->buildArea6PortfolioLanding($loanCurrentPeriod, $forceFresh);
        $landingSummary = $this->buildLandingExecutiveSummary($loanCurrentPeriod, $area6Portfolio);
        $simpananSourceDetail = $this->buildLandingSourceDetail(
            'Simpanan Realtime',
            $currentPeriod,
            $currentSummary['source_table'] ?? 'simpanan_multipn',
            [
                ['label' => 'Total saldo', 'value' => $this->formatCurrencyFull($currentSummary['total_balance']), 'source' => $currentSummary['source_table'] ?? 'simpanan_multipn'],
                ['label' => 'Rekening', 'value' => $this->formatInteger($currentSummary['account_count']), 'source' => $currentSummary['source_table'] ?? 'simpanan_multipn'],
                ['label' => 'CIF', 'value' => $this->formatInteger($currentSummary['cif_count']), 'source' => $currentSummary['source_table'] ?? 'simpanan_multipn'],
                ['label' => 'Top cabang', 'value' => $topBranchLabel . ' - ' . $topBranchDisplay, 'source' => $currentSummary['branch_source_table'] ?? $currentSummary['source_table'] ?? 'simpanan_multipn'],
            ],
            $currentSummary['source_note'] ?? 'Snapshot dashboard simpanan; fallback hanya ke simpanan_multipn jika snapshot belum tersedia.'
        );
        $pinjamanSourceDetail = $this->buildLandingSourceDetail(
            'Pinjaman Realtime',
            $loanCurrentPeriod,
            $loanCurrentSummary['source_table'] ?? 'daily_loan_dinamis',
            [
                ['label' => 'Total outstanding', 'value' => $this->formatCurrencyFull($loanCurrentSummary['total_balance']), 'source' => $loanCurrentSummary['source_table'] ?? 'daily_loan_dinamis'],
                ['label' => 'Rekening', 'value' => $this->formatInteger($loanCurrentSummary['account_count']), 'source' => $loanCurrentSummary['source_table'] ?? 'daily_loan_dinamis'],
                ['label' => 'Cabang', 'value' => $this->formatInteger($loanCurrentSummary['branch_count']), 'source' => $loanCurrentSummary['source_table'] ?? 'daily_loan_dinamis'],
                ['label' => 'Top cabang', 'value' => $loanTopBranchLabel . ' - ' . $loanTopBranchDisplay, 'source' => $loanCurrentSummary['branch_source_table'] ?? $loanCurrentSummary['source_table'] ?? 'daily_loan_dinamis'],
            ],
            $loanCurrentSummary['source_note'] ?? 'Snapshot dashboard pinjaman; fallback hanya ke daily_loan_dinamis jika snapshot belum tersedia.'
        );
        $portfolioSourceDetail = $this->buildLandingSourceDetail(
            'LDR (Loan to Deposit Ratio)',
            $latestCombinedLabel,
            ($loanCurrentSummary['source_table'] ?? 'daily_loan_dinamis') . ' + ' . ($currentSummary['source_table'] ?? 'simpanan_multipn'),
            [
                ['label' => 'Total OS pinjaman', 'value' => $this->formatCurrencyFull($loanCurrentSummary['total_balance']), 'source' => $loanCurrentSummary['source_table'] ?? 'daily_loan_dinamis'],
                ['label' => 'Total dana simpanan', 'value' => $this->formatCurrencyFull($currentSummary['total_balance']), 'source' => $currentSummary['source_table'] ?? 'simpanan_multipn'],
                ['label' => 'LDR', 'value' => $coverageNow, 'source' => 'Hasil bagi OS pinjaman / dana simpanan'],
                ['label' => 'LDR pembanding', 'value' => $coveragePrev, 'source' => 'Periode sebelumnya'],
            ],
            'Tidak memakai angka sisipan dari dashboard lain; dihitung dari dua ringkasan sumber yang tampil di kartu ini.'
        );

        return [
            'period' => $currentPeriod,
            'previous_period' => $previousPeriod,
            'yoy_period' => $yoyPeriod,
            'hero' => [
                'title' => 'A-SIX',
                'kicker' => 'DASHBOARD ' . strtoupper($scopeLabel),
                'subtitle' => 'Ringkasan posisi keuangan ' . $scopeLabel . ' secara realtime.',
                'badge' => 'A-SIX LIVE PORTFOLIO',
                'updated_label' => $latestCombinedLabel,
                'stats' => [
                    [
                        'label' => 'Total Dana (Simpanan)',
                        'value' => $this->formatCurrencyCompact($currentSummary['total_balance']),
                        'posisi' => $currentPeriod ? $this->formatPeriodLabel($currentPeriod) : '-',
                        'icon' => 'fas fa-piggy-bank'
                    ],
                    [
                        'label' => 'Total OS (Pinjaman)',
                        'value' => $this->formatCurrencyCompact($loanCurrentSummary['total_balance']),
                        'posisi' => $loanCurrentPeriod ? $this->formatPeriodLabel($loanCurrentPeriod) : '-',
                        'icon' => 'fas fa-hand-holding-usd'
                    ]
                ],
            ],
            'health' => [
                'title' => $composition['status_label'],
                'badge' => $composition['badge'],
                'badge_class' => $composition['badge_class'],
                'progress' => $composition['known_ratio'],
                'items' => [
                    [
                        'label' => 'Tabungan',
                        'value' => $this->formatPercent($composition['tabungan_pct']),
                    ],
                    [
                        'label' => 'Giro',
                        'value' => $this->formatPercent($composition['giro_pct']),
                    ],
                    [
                        'label' => 'Tipe Terpetakan',
                        'value' => $this->formatPercent($composition['known_ratio']),
                    ],
                ],
            ],
            'live_reports' => [
                [
                    'key' => 'simpanan',
                    'title' => 'Simpanan Realtime',
                    'eyebrow' => 'Snapshot aktif',
                    'value' => $this->formatCurrencyCompact($currentSummary['total_balance']),
                    'trend' => $this->formatSignedPercent($savingsMoM),
                    'trend_class' => $this->deltaClass($savingsMoM),
                    'meta' => $currentSummary['account_count'] . ' rekening | ' . $currentSummary['cif_count'] . ' CIF',
                    'detail' => 'Top cabang ' . $topBranchLabel . ' ' . $topBranchDisplay,
                    'updated' => $this->formatPeriodLabel($currentPeriod),
                    'badge' => 'Simpanan',
                    'badge_class' => 'badge-primary',
                    'icon' => 'fas fa-piggy-bank',
                    'icon_bg' => 'rgba(13, 110, 253, 0.12)',
                    'tone' => 'primary',
                    'link' => route('dashboard'),
                    'link_label' => 'Buka report simpanan',
                    'detail_payload' => $simpananSourceDetail,
                ],
                [
                    'key' => 'pinjaman',
                    'title' => 'Pinjaman Realtime',
                    'eyebrow' => 'Outstanding aktif',
                    'value' => $this->formatCurrencyCompact($loanCurrentSummary['total_balance']),
                    'trend' => $this->formatSignedPercent($loanMoM),
                    'trend_class' => $this->deltaClass($loanMoM),
                    'meta' => $loanCurrentSummary['account_count'] . ' rekening | ' . $loanCurrentSummary['branch_count'] . ' cabang',
                    'detail' => 'Top cabang ' . $loanTopBranchLabel . ' ' . $loanTopBranchDisplay,
                    'updated' => $loanCurrentPeriod ? $this->formatPeriodLabel($loanCurrentPeriod) : 'Belum ada data',
                    'badge' => 'Pinjaman',
                    'badge_class' => 'badge-info',
                    'icon' => 'fas fa-hand-holding-usd',
                    'icon_bg' => 'rgba(23, 162, 184, 0.12)',
                    'tone' => 'info',
                    'link' => route('report.dashboard-pinjaman'),
                    'link_label' => 'Buka report pinjaman',
                    'detail_payload' => $pinjamanSourceDetail,
                ],
                [
                    'key' => 'portfolio',
                    'title' => 'LDR (Loan to Deposit Ratio)',
                    'eyebrow' => 'Cross report',
                    'value' => $this->formatRatio($loanCurrentSummary['total_balance'], $currentSummary['total_balance']),
                    'trend' => $this->formatSignedPercent($coverageChange),
                    'trend_class' => $this->deltaClass($coverageChange),
                    'meta' => 'Gap pinjaman vs simpanan ' . $this->formatCurrencyCompact($loanCurrentSummary['total_balance'] - $currentSummary['total_balance']),
                    'detail' => 'LDR periode saat ini ' . $coverageNow . ' vs ' . $coveragePrev,
                    'updated' => $latestCombinedLabel,
                    'badge' => 'LDR',
                    'badge_class' => 'badge-success',
                    'icon' => 'fas fa-layer-group',
                    'icon_bg' => 'rgba(40, 167, 69, 0.12)',
                    'tone' => 'success',
                    'link' => route('dashboard.harian'),
                    'link_label' => 'Lihat portfolio harian',
                    'detail_payload' => $portfolioSourceDetail,
                ],
            ],
            'digital_performance' => $digitalPerformance,
            'timeseries' => $timeseries,
            'area6_portfolio' => $area6Portfolio,
            'landing_summary' => $landingSummary,
            'metrics' => [
                [
                    'label' => 'Total Simpanan',
                    'value' => $this->formatCurrencyCompact($currentSummary['total_balance']),
                    'delta' => $this->formatInteger($currentSummary['account_count']) . ' rekening aktif',
                    'delta_class' => 'text-muted',
                    'icon' => 'fas fa-building',
                    'icon_class' => 'text-primary',
                    'icon_bg' => 'rgba(13, 110, 253, 0.12)',
                ],
                [
                    'label' => 'Total Pinjaman',
                    'value' => $this->formatCurrencyCompact($loanCurrentSummary['total_balance']),
                    'delta' => $this->formatInteger($loanCurrentSummary['account_count']) . ' rekening aktif',
                    'delta_class' => 'text-muted',
                    'icon' => 'fas fa-chart-line',
                    'icon_class' => 'text-info',
                    'icon_bg' => 'rgba(23, 162, 184, 0.13)',
                ],
                [
                    'label' => 'Growth Simpanan MtM',
                    'value' => $this->formatSignedPercent($savingsMoM),
                    'delta' => 'vs ' . ($previousPeriod ? $this->formatPeriodLabel($previousPeriod) : 'periode sebelumnya'),
                    'delta_class' => $this->deltaClass($savingsMoM),
                    'icon' => 'fas fa-wallet',
                    'icon_class' => 'text-warning',
                    'icon_bg' => 'rgba(255, 193, 7, 0.16)',
                ],
                [
                    'label' => 'Growth Pinjaman MtM',
                    'value' => $this->formatSignedPercent($loanMoM),
                    'delta' => 'vs ' . ($loanPreviousPeriod ? $this->formatPeriodLabel($loanPreviousPeriod) : 'periode sebelumnya'),
                    'delta_class' => $this->deltaClass($loanMoM),
                    'icon' => 'fas fa-database',
                    'icon_class' => 'text-success',
                    'icon_bg' => 'rgba(40, 167, 69, 0.14)',
                ],
            ],
            'performance' => [
                'title' => 'Performa Simpanan',
                'subtitle' => 'Ringkasan kontribusi saldo per jenis simpanan dan konsentrasi cabang terbesar.',
                'updated_at' => $latestUpdatedAt
                    ? Carbon::parse($latestUpdatedAt)->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d M Y H:i')
                    : null,
                'bars' => [
                    [
                        'label' => 'Tabungan',
                        'value' => $composition['tabungan_pct'],
                        'display' => $this->formatPercent($composition['tabungan_pct']),
                        'class' => 'bg-primary',
                    ],
                    [
                        'label' => 'Giro',
                        'value' => $composition['giro_pct'],
                        'display' => $this->formatPercent($composition['giro_pct']),
                        'class' => 'bg-success',
                    ],
                    [
                        'label' => 'Produk Lain / Belum Terpetakan',
                        'value' => $composition['other_pct'],
                        'display' => $this->formatPercent($composition['other_pct']),
                        'class' => 'bg-info',
                    ],
                    [
                        'label' => 'Kontribusi Top 5 Cabang',
                        'value' => $this->percentOf($topBranches->sum('balance'), $currentSummary['total_balance']),
                        'display' => $this->formatPercent($this->percentOf($topBranches->sum('balance'), $currentSummary['total_balance'])),
                        'class' => 'bg-warning',
                    ],
                ],
            ],
            'priorities' => [
                [
                    'badge' => '01',
                    'badge_class' => 'badge-primary',
                    'title' => 'Pantau Pergerakan Simpanan & Pinjaman',
                    'text' => 'Posisi simpanan ' . $this->formatCurrencyFull($currentSummary['total_balance']) . ' dan pinjaman ' . $this->formatCurrencyFull($loanCurrentSummary['total_balance']) . ' sama-sama sudah ter-update.',
                ],
                [
                    'badge' => '02',
                    'badge_class' => 'badge-warning',
                    'title' => 'Jaga Kualitas Mapping Produk',
                    'text' => $this->formatPercent($composition['known_ratio']) . ' saldo sudah terpetakan ke jenis simpanan utama.',
                ],
                [
                    'badge' => '03',
                    'badge_class' => 'badge-success',
                    'title' => 'Fokus Cabang Kontributor',
                    'text' => $topBranchLabel . ' unggul di simpanan, sementara pinjaman terbesar datang dari ' . $loanTopBranchLabel . '.',
                ],
            ],
            'activities' => $this->buildActivities(
                $currentSummary,
                $previousSummary,
                $loanCurrentSummary,
                $loanPreviousSummary,
                $composition,
                $currentPeriod,
                $loanCurrentPeriod,
                $topBranchLabel,
                $topBranchDisplay,
                $loanTopBranchLabel,
                $loanTopBranchDisplay
            ),
            'agenda' => [
                [
                    'title' => 'Review Posisi Simpanan',
                    'time' => $this->formatPeriodLabel($currentPeriod),
                    'tag' => 'Data',
                ],
                [
                    'title' => 'Review Posisi Pinjaman',
                    'time' => $loanCurrentPeriod ? $this->formatPeriodLabel($loanCurrentPeriod) : 'Belum ada data',
                    'tag' => 'Loan',
                ],
                [
                    'title' => 'Bandingkan Coverage',
                    'time' => $coverageNow,
                    'tag' => 'Cross',
                ],
            ],
            'data_quality' => [
                'snapshot_completeness' => $currentSummary['snapshot_completeness'] ?? 'complete',
                'partial_branches' => $currentSummary['partial_branches'] ?? [],
            ],
            'top_branches' => $topBranches->all(),
            'loan_top_branches' => $loanTopBranches->all(),
        ];
    }

    private function buildPeriodSummary(string $period, bool $forceFresh = false): array
    {
        $cacheKey = 'dashboard_simpanan:summary:' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $this->reportCacheVersion() . ':' . $period;
        $latestKey = $cacheKey . ':latest';
        $ttl = now()->addMinutes(self::SUMMARY_CACHE_MINUTES);
        $latestTtl = now()->addMinutes(self::SUMMARY_LATEST_CACHE_MINUTES);

        if ($forceFresh) {
            $summary = $this->queryPeriodSummary($period);
            Cache::put($cacheKey, $summary, $ttl);
            Cache::put($latestKey, $summary, $latestTtl);

            return $summary;
        }

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $lock = Cache::lock($cacheKey . ':lock', self::CACHE_LOCK_SECONDS);

        try {
            return $lock->block(2, function () use ($cacheKey, $latestKey, $ttl, $latestTtl, $period) {
                $cached = Cache::get($cacheKey);
                if (is_array($cached)) {
                    return $cached;
                }

                $summary = $this->queryPeriodSummary($period);
                Cache::put($cacheKey, $summary, $ttl);
                Cache::put($latestKey, $summary, $latestTtl);

                return $summary;
            });
        } catch (Throwable) {
            $latest = Cache::get($latestKey);
            if (is_array($latest)) {
                return $latest;
            }

            return $this->queryPeriodSummary($period);
        }
    }

    private function queryPeriodSummary(string $period): array
    {
        $harianSummary = $this->querySimpananSummaryFromHarianSnapshot($period);
        if ($harianSummary !== null) {
            return $harianSummary;
        }

        $snapshotSummary = $this->queryPeriodSummaryFromSnapshot($period);
        if ($snapshotSummary !== null) {
            return $snapshotSummary;
        }

        $summary = DB::table('simpanan_multipn')
            ->where('posisi', $period)
            ->whereIn(DB::raw('UPPER(TRIM(kantor_cabang))'), $this->dashboardBranchNames())
            ->selectRaw('COALESCE(SUM(COALESCE(saldo_idr, 0)), 0) as total_balance')
            ->selectRaw('COUNT(DISTINCT no_rekening) as account_count')
            ->selectRaw('COUNT(DISTINCT CIFNO) as cif_count')
            ->selectRaw('COUNT(DISTINCT kantor_cabang) as branch_count')
            ->selectRaw('COUNT(DISTINCT unit_kerja) as unit_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN UPPER(COALESCE(jenis_simpanan, '')) LIKE 'TABUNGAN%' THEN COALESCE(saldo_idr, 0) ELSE 0 END), 0) as tabungan_balance")
            ->selectRaw("COALESCE(SUM(CASE WHEN UPPER(COALESCE(jenis_simpanan, '')) LIKE 'GIRO%' THEN COALESCE(saldo_idr, 0) ELSE 0 END), 0) as giro_balance")
            ->selectRaw('MAX(updated_at) as source_updated_at')
            ->first();

        $totalBalance = (float) ($summary->total_balance ?? 0);
        $cifCount = (int) ($summary->cif_count ?? 0);

        return [
            'total_balance' => $totalBalance,
            'account_count' => (int) ($summary->account_count ?? 0),
            'cif_count' => $cifCount,
            'branch_count' => (int) ($summary->branch_count ?? 0),
            'unit_count' => (int) ($summary->unit_count ?? 0),
            'tabungan_balance' => (float) ($summary->tabungan_balance ?? 0),
            'giro_balance' => (float) ($summary->giro_balance ?? 0),
            'other_balance' => max(0, $totalBalance - (float) ($summary->tabungan_balance ?? 0) - (float) ($summary->giro_balance ?? 0)),
            'avg_balance_per_cif' => $cifCount > 0 ? $totalBalance / $cifCount : 0,
            'source_updated_at' => $summary->source_updated_at ?? null,
            'source_table' => 'simpanan_multipn',
            'branch_source_table' => 'simpanan_multipn',
            'source_note' => 'Agregasi langsung dari simpanan_multipn untuk posisi yang sama.',
            'snapshot_completeness' => 'complete',
            'partial_branches' => [],
        ];
    }

    private function fetchTopBranches(string $period, bool $forceFresh = false): Collection
    {
        $cacheKey = 'dashboard_simpanan:top_branches:' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $this->reportCacheVersion() . ':' . $period;

        $builder = function () use ($period) {
            $harianRows = $this->queryTopBranchesFromHarianSnapshot($period);
            if ($harianRows !== null) {
                return $harianRows;
            }

            $snapshotRows = $this->queryTopBranchesFromSnapshot($period);
            if ($snapshotRows !== null) {
                return $snapshotRows;
            }

            return DB::table('simpanan_multipn')
                ->where('posisi', $period)
                ->whereIn(DB::raw('UPPER(TRIM(kantor_cabang))'), $this->dashboardBranchNames())
                ->whereNotNull('kantor_cabang')
                ->where('kantor_cabang', '<>', '')
                ->selectRaw('kantor_cabang, COALESCE(SUM(COALESCE(saldo_idr, 0)), 0) as total_balance')
                ->groupBy('kantor_cabang')
                ->orderByDesc('total_balance')
                ->limit(5)
                ->get();
        };

        if ($forceFresh) {
            $rows = $builder();
            Cache::put($cacheKey, $rows, now()->addMinutes(self::TOP_BRANCH_CACHE_MINUTES));
        } else {
            $rows = Cache::remember($cacheKey, now()->addMinutes(self::TOP_BRANCH_CACHE_MINUTES), $builder);
        }

        return collect($rows)->map(function ($row) {
            $balance = (float) ($row->total_balance ?? 0);

            return [
                'label' => $this->simplifyBranchLabel((string) ($row->kantor_cabang ?? '-')),
                'full_label' => (string) ($row->kantor_cabang ?? '-'),
                'balance' => $balance,
                'display' => $this->formatCurrencyCompact($balance),
            ];
        });
    }

    private function buildComposition(array $summary): array
    {
        $total = (float) ($summary['total_balance'] ?? 0);
        $tabungan = (float) ($summary['tabungan_balance'] ?? 0);
        $giro = (float) ($summary['giro_balance'] ?? 0);
        $other = (float) ($summary['other_balance'] ?? 0);
        $knownRatio = $this->percentOf($tabungan + $giro, $total);

        return [
            'tabungan_pct' => $this->percentOf($tabungan, $total),
            'giro_pct' => $this->percentOf($giro, $total),
            'other_pct' => $this->percentOf($other, $total),
            'known_ratio' => $knownRatio,
            'status_label' => $knownRatio >= 75 ? 'Komposisi Simpanan Terbaca' : 'Perlu Review Mapping',
            'badge' => $knownRatio >= 75 ? 'Healthy' : 'Check',
            'badge_class' => $knownRatio >= 75 ? 'badge-success' : 'badge-warning',
        ];
    }

    private function buildLandingSourceDetail(string $title, ?string $period, string $sourceTable, array $rows, string $note): array
    {
        return [
            'title' => $title,
            'period' => $this->formatSourcePeriodLabel($period),
            'source_table' => $sourceTable,
            'note' => $note,
            'rows' => array_values($rows),
        ];
    }

    private function buildArea6PortfolioLanding(?string $loanPeriod, bool $forceFresh = false): array
    {
        $dailyLoanPeriod = $this->resolveArea6DailyLoanPeriod($loanPeriod);
        $cacheVersion = $this->reportCacheVersion();
        $cacheKey = 'dashboard_simpanan:area6_portfolio:' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $cacheVersion . ':' . ($loanPeriod ?? 'none') . ':daily:' . ($dailyLoanPeriod ?? 'none');
        $latestCacheKey = 'dashboard_simpanan:area6_portfolio:' . self::LANDING_SOURCE_CACHE_VERSION . ':latest:v' . $cacheVersion . ':' . ($loanPeriod ?? 'none') . ':daily:' . ($dailyLoanPeriod ?? 'none');
        $stableLatestCacheKey = 'dashboard_simpanan:area6_portfolio:' . self::LANDING_SOURCE_CACHE_VERSION . ':latest:stable:v' . $cacheVersion . ':' . ($loanPeriod ?? 'none') . ':daily:' . ($dailyLoanPeriod ?? 'none');

        if ($forceFresh) {
            $freshPayload = $this->buildArea6PortfolioLandingFresh($loanPeriod, $dailyLoanPeriod);
            Cache::put($cacheKey, $freshPayload, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES));
            Cache::put($latestCacheKey, $freshPayload, now()->addMinutes(self::SUMMARY_LATEST_CACHE_MINUTES));
            Cache::put($stableLatestCacheKey, $freshPayload, now()->addMinutes(self::SUMMARY_LATEST_CACHE_MINUTES));

            return $freshPayload;
        }

        $cachedPayload = Cache::get($cacheKey);

        if (is_array($cachedPayload)) {
            return $cachedPayload;
        }

        $latestPayload = Cache::get($latestCacheKey);

        if (is_array($latestPayload)) {
            Cache::put($cacheKey, $latestPayload, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES));
            $this->deferArea6PortfolioRefresh($cacheKey, $latestCacheKey, $stableLatestCacheKey, $loanPeriod, $dailyLoanPeriod);

            return $latestPayload;
        }

        $stableLatestPayload = Cache::get($stableLatestCacheKey);

        if (is_array($stableLatestPayload)) {
            Cache::put($cacheKey, $stableLatestPayload, now()->addSeconds(30));
            $this->deferArea6PortfolioRefresh($cacheKey, $latestCacheKey, $stableLatestCacheKey, $loanPeriod, $dailyLoanPeriod);

            return $stableLatestPayload;
        }

        $lock = Cache::lock($cacheKey . ':lock', self::CACHE_LOCK_SECONDS);
        $locked = false;

        try {
            try {
                $locked = $lock->block(5);
            } catch (Throwable $e) {
                $locked = $lock->get();
            }

            if ($locked) {
                $freshPayload = $this->buildArea6PortfolioLandingFresh($loanPeriod, $dailyLoanPeriod);
                Cache::put($cacheKey, $freshPayload, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES));
                Cache::put($latestCacheKey, $freshPayload, now()->addMinutes(self::SUMMARY_LATEST_CACHE_MINUTES));
                Cache::put($stableLatestCacheKey, $freshPayload, now()->addMinutes(self::SUMMARY_LATEST_CACHE_MINUTES));

                return $freshPayload;
            }
        } catch (Throwable $e) {
            Log::warning('Dashboard simpanan Area 6 lock failed, generating fresh directly.', [
                'period' => $loanPeriod,
                'error' => $e->getMessage(),
            ]);
        } finally {
            if ($locked) {
                $lock->release();
            }
        }

        try {
            return $this->buildArea6PortfolioLandingFresh($loanPeriod, $dailyLoanPeriod);
        } catch (Throwable $e) {
            Log::warning('Dashboard simpanan Area 6 fallback digunakan.', [
                'period' => $loanPeriod,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->emptyArea6PortfolioLanding();
    }

    private function deferArea6PortfolioRefresh(string $cacheKey, string $latestCacheKey, string $stableLatestCacheKey, ?string $loanPeriod, ?string $dailyLoanPeriod): void
    {
        app()->terminating(function () use ($cacheKey, $latestCacheKey, $stableLatestCacheKey, $loanPeriod, $dailyLoanPeriod) {
            $lock = Cache::lock($cacheKey . ':lock', self::CACHE_LOCK_SECONDS);
            $locked = false;

            try {
                $locked = $lock->get();
                if (!$locked) {
                    return;
                }

                $freshPayload = $this->buildArea6PortfolioLandingFresh($loanPeriod, $dailyLoanPeriod);
                Cache::put($cacheKey, $freshPayload, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES));
                Cache::put($latestCacheKey, $freshPayload, now()->addMinutes(self::SUMMARY_LATEST_CACHE_MINUTES));
                Cache::put($stableLatestCacheKey, $freshPayload, now()->addMinutes(self::SUMMARY_LATEST_CACHE_MINUTES));
            } catch (Throwable $e) {
                Log::warning('Dashboard simpanan Area 6 gagal dihangatkan setelah response.', [
                    'period' => $loanPeriod,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                if ($locked) {
                    $lock->release();
                }
            }
        });
    }

    private function buildArea6PortfolioLandingFresh(?string $loanPeriod, ?string $dailyLoanPeriod = null): array
    {
        $harian = $this->fetchArea6HarianPortfolio();
        $dailyLoanPeriod ??= $this->resolveArea6DailyLoanPeriod($loanPeriod);

        // Fetch unified rankings (KC, KCP, and Unit combined)
        $period = $harian['period'];
        $branchLabelExpression = Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_label')
            ? 'kanca_label'
            : (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'branch_label') ? 'branch_label' : "''");
        $unitLabelExpression = Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'unit_label')
            ? 'unit_label'
            : (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'uker_label') ? 'uker_label' : "''");

        $allUkerRows = [];
        if ($period) {
            $allUkerRows = $this->area6HarianSnapshotScopeQuery((string) $period, false)
                ->selectRaw("COALESCE({$branchLabelExpression}, '') as branch_label")
                ->selectRaw("COALESCE({$unitLabelExpression}, '') as unit_label")
                ->selectRaw('COALESCE(total_simpanan, 0) as total_simpanan')
                ->selectRaw('COALESCE(total_os, 0) as total_os')
                ->selectRaw('COALESCE(total_os_non_commercial, 0) as total_os_non_commercial')
                ->selectRaw('COALESCE(total_sml_abs_non_commercial, 0) as sml_abs')
                ->selectRaw('COALESCE(total_npl_abs_non_commercial, 0) as npl_abs')
                ->get()
                ->map(function ($row) {
                    $osNonCommercial = (float) ($row->total_os_non_commercial ?? 0);
                    $smlAbs = (float) ($row->sml_abs ?? 0);
                    $nplAbs = (float) ($row->npl_abs ?? 0);
                    $simpanan = (float) ($row->total_simpanan ?? 0);

                    return [
                        'branch' => trim((string) ($row->branch_label ?? '')),
                        'unit' => trim((string) ($row->unit_label ?? '')),
                        'total_os' => (float) ($row->total_os ?? 0),
                        'total_os_non_commercial' => $osNonCommercial,
                        'total_simpanan' => $simpanan,
                        'sml_abs' => $smlAbs,
                        'npl_abs' => $nplAbs,
                        'sml_pct' => $this->percentOf($smlAbs, $osNonCommercial),
                        'npl_pct' => $this->percentOf($nplAbs, $osNonCommercial),
                    ];
                })
                ->filter(fn (array $row) => $row['unit'] !== '')
                ->values()
                ->all();
        }

        $retailRows = array_values(array_filter(
            $allUkerRows,
            fn (array $row): bool => $this->isArea6RetailLabel($row['unit'] ?? null)
        ));
        $microRows = array_values(array_filter(
            $allUkerRows,
            fn (array $row): bool => $this->isArea6MicroLabel($row['unit'] ?? null)
        ));

        $ktsRetail = $this->buildArea6KtsRanking($dailyLoanPeriod, 'retail');
        $smallArrearsRetail = $this->buildArea6SmallArrearsRanking($dailyLoanPeriod, 'retail');
        $retailRankings = $this->buildArea6RankingGroups($retailRows, $ktsRetail, $smallArrearsRetail, 'unit_kerja');

        $ktsMicro = $this->buildArea6KtsRanking($dailyLoanPeriod, 'unit');
        $smallArrearsMicro = $this->buildArea6SmallArrearsRanking($dailyLoanPeriod, 'unit');
        $microRankings = $this->buildArea6RankingGroups($microRows, $ktsMicro, $smallArrearsMicro, 'unit');

        // Fetch Cabang Konsol Data (Madiun, Magetan, Ngawi, Ponorogo)
        $branchRows = [];
        if ($period) {
            $branchRows = $this->area6HarianSnapshotScopeQuery((string) $period, true)
                ->selectRaw("COALESCE({$branchLabelExpression}, '') as branch_label")
                ->selectRaw('COALESCE(total_simpanan, 0) as total_simpanan')
                ->selectRaw('COALESCE(total_os, 0) as total_os')
                ->selectRaw('COALESCE(total_os_non_commercial, 0) as total_os_non_commercial')
                ->selectRaw('COALESCE(total_sml_abs_non_commercial, 0) as sml_abs')
                ->selectRaw('COALESCE(total_sml_pct_non_commercial, 0) as sml_pct')
                ->selectRaw('COALESCE(total_npl_abs_non_commercial, 0) as npl_abs')
                ->selectRaw('COALESCE(total_npl_pct_non_commercial, 0) as npl_pct')
                ->get();
        }

        $branchesIndexed = collect($branchRows)->keyBy(function ($row) {
            return strtoupper(trim($row->branch_label));
        });

        $simpananRka = [];
        $pinjamanRka = [];
        if ($period) {
            try {
                $rkaService = app(\App\Support\RkaLookupService::class);
                $rkaYear = (int) Carbon::parse($period)->format('Y');
                $monthColumn = $rkaService->resolveMonthColumn(Carbon::parse($period));
                
                $definitions = [
                    'simpanan' => ['mata_anggaran' => ['A.1. DPK Retail Funding Total', 'A.2. DPK Korporasi']],
                    'pinjaman' => ['mata_anggaran' => ['B. KREDIT TOTAL']],
                ];
                
                $branchesToQuery = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
                $rkaBranchAggregates = $rkaService->aggregateByKancaWithSummaryFallback(
                    $definitions,
                    $monthColumn,
                    $branchesToQuery,
                    $rkaYear
                );
                
                $simpananRka = $rkaBranchAggregates['simpanan'] ?? [];
                $pinjamanRka = $rkaBranchAggregates['pinjaman'] ?? [];
            } catch (\Throwable $e) {
                // Safe fallback if service fails or RKA table is missing
            }
        }

        $maxSimpanan = 0.0;
        $maxPinjaman = 0.0;
        $maxSmlNominal = 0.0;
        $maxNplNominal = 0.0;
        $maxSmlPct = 0.0;
        $maxNplPct = 0.0;

        $totalArea6Simpanan = 0.0;
        $totalArea6Pinjaman = 0.0;
        $totalArea6SmlAbs = 0.0;
        $totalArea6NplAbs = 0.0;
        foreach ($this->dashboardBranchDisplayNames() as $branchName) {
            $key = strtoupper(trim($branchName));
            $row = $branchesIndexed->get($key);
            $totalArea6Simpanan += $row ? (float) $row->total_simpanan : 0.0;
            $totalArea6Pinjaman += $row ? (float) $row->total_os : 0.0;
            $totalArea6SmlAbs += $row ? (float) $row->sml_abs : 0.0;
            $totalArea6NplAbs += $row ? (float) $row->npl_abs : 0.0;
        }

        $restrukByBranch = [];
        if ($period && $this->hasTable('daily_loan_dinamis') && $this->hasColumn('daily_loan_dinamis', 'baki_debet1') && $this->hasColumn('daily_loan_dinamis', 'cabang1')) {
            try {
                $restrukByBranch = DB::table('daily_loan_dinamis')
                    ->where('periode', $period)
                    ->whereIn(DB::raw('UPPER(TRIM(cabang1))'), $this->dashboardBranchNames())
                    ->where('kolek', 1)
                    ->where(DB::raw("UPPER(TRIM(COALESCE(flag_restruk, '')))"), 'Y')
                    ->selectRaw('UPPER(TRIM(cabang1)) as branch_key')
                    ->selectRaw('SUM(baki_debet1) as restruk_abs')
                    ->groupBy('branch_key')
                    ->get()
                    ->pluck('restruk_abs', 'branch_key')
                    ->toArray();
            } catch (\Throwable $e) {
                $restrukByBranch = [];
            }
        }

        $branchesData = [];
        foreach ($this->dashboardBranchDisplayNames() as $branchName) {
            $key = strtoupper(trim($branchName));
            $row = $branchesIndexed->get($key);

            $simpanan = $row ? (float) $row->total_simpanan : 0.0;
            $pinjaman = $row ? (float) $row->total_os : 0.0;
            $smlAbs = $row ? (float) $row->sml_abs : 0.0;
            $smlPct = $row ? (float) $row->sml_pct : 0.0;
            $nplAbs = $row ? (float) $row->npl_abs : 0.0;
            $nplPct = $row ? (float) $row->npl_pct : 0.0;

            $restrukAbs = (float) ($restrukByBranch[$key] ?? 0.0);
            $larAbs = $restrukAbs + $smlAbs + $nplAbs;
            $larPct = $pinjaman > 0 ? ($larAbs / $pinjaman) * 100 : 0.0;

            if ($simpanan > $maxSimpanan) $maxSimpanan = $simpanan;
            if ($pinjaman > $maxPinjaman) $maxPinjaman = $pinjaman;
            if ($smlAbs > $maxSmlNominal) $maxSmlNominal = $smlAbs;
            if ($smlPct > $maxSmlPct) $maxSmlPct = $smlPct;
            if ($nplAbs > $maxNplNominal) $maxNplNominal = $nplAbs;
            if ($nplPct > $maxNplPct) $maxNplPct = $nplPct;

            $smlShare = $totalArea6SmlAbs > 0 ? ($smlAbs / $totalArea6SmlAbs) * 100 : 0.0;
            $nplShare = $totalArea6NplAbs > 0 ? ($nplAbs / $totalArea6NplAbs) * 100 : 0.0;

            $simpananTarget = (float) ($simpananRka[$key] ?? 0.0);
            $pinjamanTarget = (float) ($pinjamanRka[$key] ?? 0.0);

            if ($simpananTarget > 0.0) {
                $simpShare = ($simpanan / $simpananTarget) * 100;
                $simpWidth = min(100.0, $simpShare);
            } else {
                $simpShare = $totalArea6Simpanan > 0 ? ($simpanan / $totalArea6Simpanan) * 100 : 0.0;
                $simpWidth = $maxSimpanan > 0 ? ($simpanan / $maxSimpanan) * 100 : 0.0;
            }

            if ($pinjamanTarget > 0.0) {
                $pinjShare = ($pinjaman / $pinjamanTarget) * 100;
                $pinjWidth = min(100.0, $pinjShare);
            } else {
                $pinjShare = $totalArea6Pinjaman > 0 ? ($pinjaman / $totalArea6Pinjaman) * 100 : 0.0;
                $pinjWidth = $maxPinjaman > 0 ? ($pinjaman / $maxPinjaman) * 100 : 0.0;
            }

            $branchesData[$branchName] = [
                'name' => $branchName,
                'simpanan' => $simpanan,
                'simpanan_fmt' => $this->formatCurrencyCompact($simpanan),
                'simpanan_target' => $simpananTarget > 0.0 ? $simpananTarget : null,
                'simpanan_target_fmt' => $simpananTarget > 0.0 ? $this->formatCurrencyCompact($simpananTarget) : null,
                'simpanan_share_fmt' => $this->formatPercentTwo($simpShare),
                'simpanan_contribution_pct' => $totalArea6Simpanan > 0 ? ($simpanan / $totalArea6Simpanan) * 100 : 0.0,
                'simpanan_contribution_pct_fmt' => $this->formatPercentTwo($totalArea6Simpanan > 0 ? ($simpanan / $totalArea6Simpanan) * 100 : 0.0),
                'simpanan_width' => $simpWidth,
                'pinjaman' => $pinjaman,
                'pinjaman_fmt' => $this->formatCurrencyCompact($pinjaman),
                'pinjaman_target' => $pinjamanTarget > 0.0 ? $pinjamanTarget : null,
                'pinjaman_target_fmt' => $pinjamanTarget > 0.0 ? $this->formatCurrencyCompact($pinjamanTarget) : null,
                'pinjaman_share_fmt' => $this->formatPercentTwo($pinjShare),
                'pinjaman_contribution_pct' => $totalArea6Pinjaman > 0 ? ($pinjaman / $totalArea6Pinjaman) * 100 : 0.0,
                'pinjaman_contribution_pct_fmt' => $this->formatPercentTwo($totalArea6Pinjaman > 0 ? ($pinjaman / $totalArea6Pinjaman) * 100 : 0.0),
                'pinjaman_width' => $pinjWidth,
                'sml_abs' => $smlAbs,
                'sml_abs_fmt' => $this->formatCurrencyCompact($smlAbs),
                'sml_pct' => $smlPct,
                'sml_pct_fmt' => $this->formatPercentTwo($smlPct),
                'sml_share_fmt' => $this->formatPercentTwo($smlShare),
                'npl_abs' => $nplAbs,
                'npl_abs_fmt' => $this->formatCurrencyCompact($nplAbs),
                'npl_pct' => $nplPct,
                'npl_pct_fmt' => $this->formatPercentTwo($nplPct),
                'npl_share_fmt' => $this->formatPercentTwo($nplShare),
                'restruk_abs' => $restrukAbs,
                'restruk_abs_fmt' => $this->formatCurrencyCompact($restrukAbs),
                'restruk_pct' => $pinjaman > 0 ? ($restrukAbs / $pinjaman) * 100 : 0.0,
                'restruk_pct_fmt' => $this->formatPercentTwo($pinjaman > 0 ? ($restrukAbs / $pinjaman) * 100 : 0.0),
                'lar_abs' => $larAbs,
                'lar_abs_fmt' => $this->formatCurrencyCompact($larAbs),
                'lar_pct' => $larPct,
                'lar_pct_fmt' => $this->formatPercentTwo($larPct),
            ];
        }

        foreach ($this->dashboardBranchDisplayNames() as $branchName) {
            $branchesData[$branchName]['sml_width'] = $maxSmlNominal > 0 ? ($branchesData[$branchName]['sml_abs'] / $maxSmlNominal) * 100 : 0;
            $branchesData[$branchName]['npl_width'] = $maxNplNominal > 0 ? ($branchesData[$branchName]['npl_abs'] / $maxNplNominal) * 100 : 0;
            $branchesData[$branchName]['sml_pct_width'] = $maxSmlPct > 0 ? ($branchesData[$branchName]['sml_pct'] / $maxSmlPct) * 100 : 0;
            $branchesData[$branchName]['npl_pct_width'] = $maxNplPct > 0 ? ($branchesData[$branchName]['npl_pct'] / $maxNplPct) * 100 : 0;
        }

        $periodLabel = $this->formatSourcePeriodLabel($harian['period']);
        $loanPeriodLabel = $this->formatSourcePeriodLabel($loanPeriod);
        $dailyLoanPeriodLabel = $this->formatSourcePeriodLabel($dailyLoanPeriod);

        $periodDate = $harian['period'] ? Carbon::parse($harian['period']) : null;
        $periodFormat = $periodDate ? $periodDate->translatedFormat('d F Y') : '19 Mei 2026';
        $rkaMonthYear = $periodDate ? $periodDate->translatedFormat('F y') : 'Mei 26';

        // Aggregate Retail (KC + KCP) branch performance
        $maxSimpananRetail = 0.0;
        $maxPinjamanRetail = 0.0;
        $maxSmlNominalRetail = 0.0;
        $maxNplNominalRetail = 0.0;
        $maxSmlPctRetail = 0.0;
        $maxNplPctRetail = 0.0;

        $retailBranchesData = [];
        foreach ($this->dashboardBranchDisplayNames() as $branchName) {
            $key = strtoupper(trim($branchName));
            $branchRetailRows = array_filter($retailRows, function ($r) use ($key) {
                return strtoupper(trim($r['branch'])) === $key;
            });

            $simpanan = 0.0;
            $pinjaman = 0.0;
            $osNonCommercial = 0.0;
            $smlAbs = 0.0;
            $nplAbs = 0.0;

            foreach ($branchRetailRows as $r) {
                $simpanan += (float) ($r['total_simpanan'] ?? 0.0);
                $pinjaman += (float) ($r['total_os'] ?? 0.0);
                $osNonCommercial += (float) ($r['total_os_non_commercial'] ?? 0.0);
                $smlAbs += (float) ($r['sml_abs'] ?? 0.0);
                $nplAbs += (float) ($r['npl_abs'] ?? 0.0);
            }

            $smlPct = $osNonCommercial > 0 ? ($smlAbs / $osNonCommercial) * 100 : 0.0;
            $nplPct = $osNonCommercial > 0 ? ($nplAbs / $osNonCommercial) * 100 : 0.0;

            if ($simpanan > $maxSimpananRetail) $maxSimpananRetail = $simpanan;
            if ($pinjaman > $maxPinjamanRetail) $maxPinjamanRetail = $pinjaman;
            if ($smlAbs > $maxSmlNominalRetail) $maxSmlNominalRetail = $smlAbs;
            if ($smlPct > $maxSmlPctRetail) $maxSmlPctRetail = $smlPct;
            if ($nplAbs > $maxNplNominalRetail) $maxNplNominalRetail = $nplAbs;
            if ($nplPct > $maxNplPctRetail) $maxNplPctRetail = $nplPct;

            $simpShare = $totalArea6Simpanan > 0 ? ($simpanan / $totalArea6Simpanan) * 100 : 0.0;
            $pinjShare = $totalArea6Pinjaman > 0 ? ($pinjaman / $totalArea6Pinjaman) * 100 : 0.0;
            $smlShare = $totalArea6SmlAbs > 0 ? ($smlAbs / $totalArea6SmlAbs) * 100 : 0.0;
            $nplShare = $totalArea6NplAbs > 0 ? ($nplAbs / $totalArea6NplAbs) * 100 : 0.0;

            $retailBranchesData[$branchName] = [
                'name' => $branchName,
                'simpanan' => $simpanan,
                'simpanan_fmt' => $this->formatCurrencyCompact($simpanan),
                'simpanan_share_fmt' => $this->formatPercentTwo($simpShare),
                'pinjaman' => $pinjaman,
                'pinjaman_fmt' => $this->formatCurrencyCompact($pinjaman),
                'pinjaman_share_fmt' => $this->formatPercentTwo($pinjShare),
                'sml_abs' => $smlAbs,
                'sml_abs_fmt' => $this->formatCurrencyCompact($smlAbs),
                'sml_pct' => $smlPct,
                'sml_pct_fmt' => $this->formatPercentTwo($smlPct),
                'sml_share_fmt' => $this->formatPercentTwo($smlShare),
                'npl_abs' => $nplAbs,
                'npl_abs_fmt' => $this->formatCurrencyCompact($nplAbs),
                'npl_pct' => $nplPct,
                'npl_pct_fmt' => $this->formatPercentTwo($nplPct),
                'npl_share_fmt' => $this->formatPercentTwo($nplShare),
            ];
        }

        foreach ($this->dashboardBranchDisplayNames() as $branchName) {
            $retailBranchesData[$branchName]['simpanan_width'] = $maxSimpananRetail > 0 ? ($retailBranchesData[$branchName]['simpanan'] / $maxSimpananRetail) * 100 : 0;
            $retailBranchesData[$branchName]['pinjaman_width'] = $maxPinjamanRetail > 0 ? ($retailBranchesData[$branchName]['pinjaman'] / $maxPinjamanRetail) * 100 : 0;
            $retailBranchesData[$branchName]['sml_width'] = $maxSmlNominalRetail > 0 ? ($retailBranchesData[$branchName]['sml_abs'] / $maxSmlNominalRetail) * 100 : 0;
            $retailBranchesData[$branchName]['npl_width'] = $maxNplNominalRetail > 0 ? ($retailBranchesData[$branchName]['npl_abs'] / $maxNplNominalRetail) * 100 : 0;
            $retailBranchesData[$branchName]['sml_pct_width'] = $maxSmlPctRetail > 0 ? ($retailBranchesData[$branchName]['sml_pct'] / $maxSmlPctRetail) * 100 : 0;
            $retailBranchesData[$branchName]['npl_pct_width'] = $maxNplPctRetail > 0 ? ($retailBranchesData[$branchName]['npl_pct'] / $maxNplPctRetail) * 100 : 0;
        }

        $segmentBranchModes = [
            'sme' => $this->buildArea6SegmentBranchPerformance($harian['period'], 'sme'),
            'consumer' => $this->buildArea6SegmentBranchPerformance($harian['period'], 'consumer'),
            'micro' => $this->buildArea6SegmentBranchPerformance($harian['period'], 'micro'),
        ];

        $scopePayloads = [
            'area6' => $this->buildArea6PortfolioScopePayload('area6', $harian['period'], $periodFormat, $rkaMonthYear, null),
            'sme' => $this->buildArea6PortfolioScopePayload('sme', $harian['period'], $periodFormat, $rkaMonthYear, null),
            'consumer' => $this->buildArea6PortfolioScopePayload('consumer', $harian['period'], $periodFormat, $rkaMonthYear, null),
            'micro' => $this->buildArea6PortfolioScopePayload('micro', $harian['period'], $periodFormat, $rkaMonthYear, null),
        ];

        return [
            'title' => 'Kinerja Area 6',
            'subtitle' => 'Ringkasan cepat dari snapshot Dashboard Harian dan Pinjaman. Area 6 mencakup seluruh cabang dan seluruh segmen.',
            'period' => $harian['period'],
            'period_label' => $periodLabel,
            'loan_period_label' => $loanPeriodLabel,
            'loan_detail_period_label' => $dailyLoanPeriodLabel,
            'default_scope' => 'area6',
            'cards' => $scopePayloads['area6']['cards'],
            'segment_performance' => $scopePayloads['area6']['segment_performance'],
            'ranking_modes' => [
                'area6' => [
                    'label' => 'Area 6',
                    'description' => 'Seluruh cabang dan seluruh segmen.',
                    'branches' => array_values($branchesData),
                ],
                'sme' => [
                    'label' => 'SME',
                    'description' => 'Segmen small / SME per cabang.',
                    'branches' => array_values($segmentBranchModes['sme']),
                    'hide_simpanan' => true,
                ],
                'consumer' => [
                    'label' => 'Konsumer',
                    'description' => 'Segmen konsumer per cabang.',
                    'branches' => array_values($segmentBranchModes['consumer']),
                    'hide_simpanan' => true,
                ],
                'micro' => [
                    'label' => 'Micro',
                    'description' => 'Segmen micro per cabang.',
                    'branches' => array_values($segmentBranchModes['micro']),
                    'hide_simpanan' => true,
                ],
            ],
            'legacy_ranking_modes' => [
                'cabang_konsol' => [
                    'label' => 'Cabang Konsol',
                    'description' => 'Semua unit kerja termasuk KC, KCP, dan unit.',
                    'branches' => array_values($branchesData),
                ],
                'ritel' => [
                    'label' => 'Ritel',
                    'description' => 'KC dan KCP.',
                    'branches' => array_values($retailBranchesData),
                ],
                'micro' => [
                    'label' => 'Micro',
                    'description' => 'Unit mikro.',
                    'rankings' => $microRankings,
                ],
            ],
            'rankings' => $retailRankings,
            'overall_trends' => $scopePayloads['area6']['overall_trends'],
            'scopes' => $scopePayloads,
        ];
    }

    private function buildArea6PortfolioScopePayload(string $scopeKey, ?string $period, string $periodFormat, string $rkaMonthYear, ?array $unitKeys): array
    {
        $service = app(DashboardHarianSnapshotService::class);
        $periodPayload = $service->buildDashboardPayload($period, null, $this->dashboardBranchNames(), $unitKeys);
        $rows = collect($periodPayload['rows'] ?? []);

        $metricKeys = $this->area6PortfolioMetricKeys($scopeKey);
        $osRow = $rows->firstWhere('key', $metricKeys['os_row']);
        $smlRow = $rows->firstWhere('key', $metricKeys['sml_row']);
        $nplRow = $rows->firstWhere('key', $metricKeys['npl_row']);
        $snapshotMetrics = $this->area6ScopeSnapshotMetrics((string) $period, $scopeKey);

        $osRealization = (float) ($snapshotMetrics->{$metricKeys['os_metric']} ?? data_get($osRow, 'values.current', 0.0));
        $osTarget = (float) data_get($osRow, 'values.rka', 0.0);
        $osPct = (float) data_get($osRow, 'values.penc_pct', 0.0);
        $osGap = $osRealization - $osTarget;

        $smlRealization = (float) ($snapshotMetrics->{$metricKeys['sml_metric']} ?? data_get($smlRow, 'values.current', 0.0));
        $smlTarget = (float) data_get($smlRow, 'values.rka', 0.0);
        $smlPct = $smlRealization > 0 ? ($smlTarget / $smlRealization) * 100 : 100.0;
        $smlGap = $smlTarget - $smlRealization;

        $nplRealization = (float) ($snapshotMetrics->{$metricKeys['npl_metric']} ?? data_get($nplRow, 'values.current', 0.0));
        $nplTarget = (float) data_get($nplRow, 'values.rka', 0.0);
        $nplPct = $nplRealization > 0 ? ($nplTarget / $nplRealization) * 100 : 100.0;
        $nplGap = $nplTarget - $nplRealization;

        $scopeLabel = $this->area6ScopeLabel($scopeKey);
        $overallTrends = $this->buildArea6ScopeOverallTrends(
            $scopeKey,
            $period,
            data_get($periodPayload, 'comparison_periods.mtm.period'),
            data_get($periodPayload, 'comparison_periods.mtd.period'),
            [
                'os' => [$osRealization, $osTarget, $osPct, $osGap],
                'sml' => [$smlRealization, $smlTarget, $smlPct, $smlGap],
                'npl' => [$nplRealization, $nplTarget, $nplPct, $nplGap],
            ],
            $metricKeys
        );

        $osMomDelta = (float) data_get($overallTrends, 'os.mom_delta', 0.0);
        $smlMomDelta = (float) data_get($overallTrends, 'sml.mom_delta', 0.0);
        $nplMomDelta = (float) data_get($overallTrends, 'npl.mom_delta', 0.0);

        $cards = [
            [
                'key' => 'os',
                'header_title' => 'OUTSTANDING (OS)',
                'realization_value' => number_format(round($osRealization / 1000000), 0, ',', '.'),
                'realization_label' => $metricKeys['os_label'] . ' per ' . $periodFormat,
                'target_value' => number_format(round($osTarget / 1000000), 0, ',', '.'),
                'target_label' => 'RKA ' . $rkaMonthYear,
                'pct_value' => number_format($osPct, 2, ',', '.') . '%',
                'pct_label' => '% Penc. RKA ' . $rkaMonthYear,
                'pct_color' => $this->getArea6AchievementColor($osPct, 'os'),
                'gap_value' => $this->formatArea6CardGap($osGap),
                'gap_label' => 'Gap thd RKA ' . $rkaMonthYear,
                'gap_color' => $osGap >= 0 ? 'green' : 'red',
                'deltas' => [
                    'dtd' => $this->formatArea6CardDelta((float) data_get($osRow, 'deltas.dtd', 0.0), 'os'),
                    'mtd' => $this->formatArea6CardDelta((float) data_get($osRow, 'deltas.mtd', 0.0), 'os'),
                    'ytd' => $this->formatArea6CardDelta((float) data_get($osRow, 'deltas.ytd', 0.0), 'os'),
                    'mom' => $this->formatArea6CardDelta($osMomDelta, 'os'),
                ],
                'tone' => 'blue',
                'icon' => 'fas fa-chart-line',
                'detail_payload' => $this->buildLandingSourceDetail('Pinjaman Outstanding Area 6 - ' . $scopeLabel, $period, self::HARIAN_SNAPSHOT_TABLE, [
                    ['label' => 'Total OS', 'value' => $this->formatCurrencyFull($osRealization), 'source' => 'SUM total_os_non_commercial scope ' . $scopeLabel],
                    ['label' => 'Unit scope', 'value' => $scopeLabel, 'source' => self::HARIAN_SNAPSHOT_TABLE],
                ], 'Sumber mengikuti snapshot Dashboard Harian terbaru sesuai toggle ' . $scopeLabel . '.'),
            ],
            [
                'key' => 'sml',
                'header_title' => 'SPECIAL MENTION LOAN (SML)',
                'realization_value' => number_format(round($smlRealization / 1000000), 0, ',', '.'),
                'realization_label' => $metricKeys['sml_label'] . ' per ' . $periodFormat,
                'target_value' => number_format(round($smlTarget / 1000000), 0, ',', '.'),
                'target_label' => 'RKA ' . $rkaMonthYear,
                'pct_value' => number_format($smlPct, 2, ',', '.') . '%',
                'pct_label' => '% Penc. RKA ' . $rkaMonthYear,
                'pct_color' => $this->getArea6AchievementColor($smlPct, 'sml'),
                'gap_value' => $this->formatArea6CardGap($smlGap),
                'gap_label' => 'Gap thd RKA ' . $rkaMonthYear,
                'gap_color' => $smlGap >= 0 ? 'green' : 'red',
                'deltas' => [
                    'dtd' => $this->formatArea6CardDelta((float) data_get($smlRow, 'deltas.dtd', 0.0), 'sml'),
                    'mtd' => $this->formatArea6CardDelta((float) data_get($smlRow, 'deltas.mtd', 0.0), 'sml'),
                    'ytd' => $this->formatArea6CardDelta((float) data_get($smlRow, 'deltas.ytd', 0.0), 'sml'),
                    'mom' => $this->formatArea6CardDelta($smlMomDelta, 'sml'),
                ],
                'tone' => 'blue',
                'icon' => 'fas fa-search',
                'detail_payload' => $this->buildLandingSourceDetail('SML Area 6 - ' . $scopeLabel, $period, self::HARIAN_SNAPSHOT_TABLE, [
                    ['label' => 'SML (ABS)', 'value' => $this->formatCurrencyFull($smlRealization), 'source' => 'SUM total_sml_abs_non_commercial scope ' . $scopeLabel],
                    ['label' => 'Unit scope', 'value' => $scopeLabel, 'source' => self::HARIAN_SNAPSHOT_TABLE],
                ], 'Sumber mengikuti snapshot Dashboard Harian terbaru sesuai toggle ' . $scopeLabel . '.'),
            ],
            [
                'key' => 'npl',
                'header_title' => 'NON-PERFORMING LOAN (NPL)',
                'realization_value' => number_format(round($nplRealization / 1000000), 0, ',', '.'),
                'realization_label' => $metricKeys['npl_label'] . ' per ' . $periodFormat,
                'target_value' => number_format(round($nplTarget / 1000000), 0, ',', '.'),
                'target_label' => 'RKA ' . $rkaMonthYear,
                'pct_value' => number_format($nplPct, 2, ',', '.') . '%',
                'pct_label' => '% Penc. RKA ' . $rkaMonthYear,
                'pct_color' => $this->getArea6AchievementColor($nplPct, 'npl'),
                'gap_value' => $this->formatArea6CardGap($nplGap),
                'gap_label' => 'Gap thd RKA ' . $rkaMonthYear,
                'gap_color' => $nplGap >= 0 ? 'green' : 'red',
                'deltas' => [
                    'dtd' => $this->formatArea6CardDelta((float) data_get($nplRow, 'deltas.dtd', 0.0), 'npl'),
                    'mtd' => $this->formatArea6CardDelta((float) data_get($nplRow, 'deltas.mtd', 0.0), 'npl'),
                    'ytd' => $this->formatArea6CardDelta((float) data_get($nplRow, 'deltas.ytd', 0.0), 'npl'),
                    'mom' => $this->formatArea6CardDelta($nplMomDelta, 'npl'),
                ],
                'tone' => 'blue',
                'icon' => 'fas fa-shield-alt',
                'detail_payload' => $this->buildLandingSourceDetail('NPL Area 6 - ' . $scopeLabel, $period, self::HARIAN_SNAPSHOT_TABLE, [
                    ['label' => 'NPL (ABS)', 'value' => $this->formatCurrencyFull($nplRealization), 'source' => 'SUM total_npl_abs_non_commercial scope ' . $scopeLabel],
                    ['label' => 'Unit scope', 'value' => $scopeLabel, 'source' => self::HARIAN_SNAPSHOT_TABLE],
                ], 'Sumber mengikuti snapshot Dashboard Harian terbaru sesuai toggle ' . $scopeLabel . '.'),
            ],
        ];

        $segmentPerformance = $this->buildArea6ScopeSegmentPerformance($scopeKey, $rows, $snapshotMetrics, $rkaMonthYear, $periodFormat, $period, $unitKeys);

        return [
            'cards' => $cards,
            'segment_performance' => $segmentPerformance,
            'overall_trends' => $overallTrends,
        ];
    }

    private function area6PortfolioMetricKeys(string $scopeKey): array
    {
        return match ($scopeKey) {
            'sme' => [
                'os_row' => 'sme_os',
                'sml_row' => 'sme_sml',
                'npl_row' => 'sme_npl',
                'os_metric' => 'sme_os',
                'sml_metric' => 'sme_sml',
                'npl_metric' => 'sme_npl',
                'os_label' => 'OS SME',
                'sml_label' => 'SML SME',
                'npl_label' => 'NPL SME',
            ],
            'consumer' => [
                'os_row' => 'consumer_os',
                'sml_row' => 'consumer_sml',
                'npl_row' => 'consumer_npl',
                'os_metric' => 'consumer_os',
                'sml_metric' => 'consumer_sml',
                'npl_metric' => 'consumer_npl',
                'os_label' => 'OS Konsumer',
                'sml_label' => 'SML Konsumer',
                'npl_label' => 'NPL Konsumer',
            ],
            'micro' => [
                'os_row' => 'micro_os',
                'sml_row' => 'micro_sml',
                'npl_row' => 'micro_npl',
                'os_metric' => 'micro_os',
                'sml_metric' => 'micro_sml',
                'npl_metric' => 'micro_npl',
                'os_label' => 'OS Micro',
                'sml_label' => 'SML Micro',
                'npl_label' => 'NPL Micro',
            ],
            default => [
                'os_row' => 'total_os_non_commercial',
                'sml_row' => 'total_sml_abs_non_commercial',
                'npl_row' => 'total_npl_abs_non_commercial',
                'os_metric' => 'total_os_non_commercial',
                'sml_metric' => 'total_sml_abs_non_commercial',
                'npl_metric' => 'total_npl_abs_non_commercial',
                'os_label' => 'OS',
                'sml_label' => 'SML',
                'npl_label' => 'NPL',
            ],
        };
    }

    private function buildArea6SegmentBranchPerformance(?string $period, string $segmentKey): array
    {
        if (!$period || !Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return [];
        }

        $expressions = $this->area6SegmentSqlExpressions($segmentKey);
        if (empty($expressions)) {
            return [];
        }

        $branchLabelExpression = Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_label')
            ? 'kanca_label'
            : (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'branch_label') ? 'branch_label' : "''");

        $rows = $this->area6HarianSnapshotScopeQuery($period, true)
            ->selectRaw("COALESCE({$branchLabelExpression}, '') as branch_label")
            ->selectRaw("COALESCE(SUM({$expressions['os']}), 0) as pinjaman")
            ->selectRaw("COALESCE(SUM({$expressions['sml']}), 0) as sml_abs")
            ->selectRaw("COALESCE(SUM({$expressions['npl']}), 0) as npl_abs")
            ->groupBy('branch_label')
            ->get()
            ->keyBy(fn ($row) => strtoupper(trim((string) ($row->branch_label ?? ''))));

        $totalPinjaman = (float) $rows->sum(fn ($row) => (float) ($row->pinjaman ?? 0.0));
        $totalSml = (float) $rows->sum(fn ($row) => (float) ($row->sml_abs ?? 0.0));
        $totalNpl = (float) $rows->sum(fn ($row) => (float) ($row->npl_abs ?? 0.0));
        $maxPinjaman = max(0.0, (float) $rows->max(fn ($row) => (float) ($row->pinjaman ?? 0.0)));
        $maxSmlPct = 0.0;
        $maxNplPct = 0.0;

        $branches = [];
        foreach ($this->dashboardBranchDisplayNames() as $branchName) {
            $row = $rows->get(strtoupper(trim($branchName)));
            $pinjaman = (float) ($row->pinjaman ?? 0.0);
            $smlAbs = (float) ($row->sml_abs ?? 0.0);
            $nplAbs = (float) ($row->npl_abs ?? 0.0);
            $smlPct = $pinjaman > 0 ? ($smlAbs / $pinjaman) * 100 : 0.0;
            $nplPct = $pinjaman > 0 ? ($nplAbs / $pinjaman) * 100 : 0.0;
            $maxSmlPct = max($maxSmlPct, $smlPct);
            $maxNplPct = max($maxNplPct, $nplPct);

            $branches[$branchName] = [
                'name' => $branchName,
                'pinjaman' => $pinjaman,
                'pinjaman_fmt' => $this->formatCurrencyCompact($pinjaman),
                'pinjaman_share_fmt' => $this->formatPercentTwo($totalPinjaman > 0 ? ($pinjaman / $totalPinjaman) * 100 : 0.0),
                'pinjaman_width' => $maxPinjaman > 0 ? ($pinjaman / $maxPinjaman) * 100 : 0.0,
                'sml_abs' => $smlAbs,
                'sml_abs_fmt' => $this->formatCurrencyCompact($smlAbs),
                'sml_pct' => $smlPct,
                'sml_pct_fmt' => $this->formatPercentTwo($smlPct),
                'sml_share_fmt' => $this->formatPercentTwo($totalSml > 0 ? ($smlAbs / $totalSml) * 100 : 0.0),
                'npl_abs' => $nplAbs,
                'npl_abs_fmt' => $this->formatCurrencyCompact($nplAbs),
                'npl_pct' => $nplPct,
                'npl_pct_fmt' => $this->formatPercentTwo($nplPct),
                'npl_share_fmt' => $this->formatPercentTwo($totalNpl > 0 ? ($nplAbs / $totalNpl) * 100 : 0.0),
            ];
        }

        foreach ($branches as $branchName => $branch) {
            $branches[$branchName]['sml_pct_width'] = $maxSmlPct > 0 ? ($branch['sml_pct'] / $maxSmlPct) * 100 : 0.0;
            $branches[$branchName]['npl_pct_width'] = $maxNplPct > 0 ? ($branch['npl_pct'] / $maxNplPct) * 100 : 0.0;
        }

        return array_values($branches);
    }

    private function area6SegmentSqlExpressions(string $segmentKey): array
    {
        return match ($segmentKey) {
            'sme' => [
                'os' => "CASE WHEN COALESCE(sme_os, 0) <> 0 THEN COALESCE(sme_os, 0) ELSE COALESCE(kecil_non_cashcoll_os, 0) + COALESCE(cashcoll_os, 0) END",
                'sml' => "CASE WHEN COALESCE(sme_sml, 0) <> 0 THEN COALESCE(sme_sml, 0) ELSE COALESCE(kecil_non_cashcoll_sml, 0) + COALESCE(cashcoll_sml, 0) END",
                'npl' => "CASE WHEN COALESCE(sme_npl, 0) <> 0 THEN COALESCE(sme_npl, 0) ELSE COALESCE(kecil_non_cashcoll_npl, 0) + COALESCE(cashcoll_npl, 0) END",
            ],
            'consumer' => [
                'os' => "CASE WHEN COALESCE(consumer_os, 0) <> 0 THEN COALESCE(consumer_os, 0) ELSE COALESCE(briguna_konsumer_os, 0) + COALESCE(kpr_os, 0) + COALESCE(kkb_os, 0) END",
                'sml' => "CASE WHEN COALESCE(consumer_sml, 0) <> 0 THEN COALESCE(consumer_sml, 0) ELSE COALESCE(briguna_konsumer_sml, 0) + COALESCE(kpr_sml, 0) + COALESCE(kkb_sml, 0) END",
                'npl' => "CASE WHEN COALESCE(consumer_npl, 0) <> 0 THEN COALESCE(consumer_npl, 0) ELSE COALESCE(briguna_konsumer_npl, 0) + COALESCE(kpr_npl, 0) + COALESCE(kkb_npl, 0) END",
            ],
            'micro' => [
                'os' => "CASE WHEN COALESCE(micro_os, 0) <> 0 THEN COALESCE(micro_os, 0) ELSE COALESCE(briguna_mikro_os, 0) + COALESCE(kupedes_os, 0) + COALESCE(kur_mikro_os, 0) + COALESCE(kur_kecil_os, 0) + COALESCE(kur_kpp_os, 0) END",
                'sml' => "CASE WHEN COALESCE(micro_sml, 0) <> 0 THEN COALESCE(micro_sml, 0) ELSE COALESCE(briguna_mikro_sml, 0) + COALESCE(kupedes_sml, 0) + COALESCE(kur_mikro_sml, 0) + COALESCE(kur_kecil_sml, 0) + COALESCE(kur_kpp_sml, 0) END",
                'npl' => "CASE WHEN COALESCE(micro_npl, 0) <> 0 THEN COALESCE(micro_npl, 0) ELSE COALESCE(briguna_mikro_npl, 0) + COALESCE(kupedes_npl, 0) + COALESCE(kur_mikro_npl, 0) + COALESCE(kur_kecil_npl, 0) + COALESCE(kur_kpp_npl, 0) END",
            ],
            default => [],
        };
    }

    private function buildArea6ScopeOverallTrends(string $scopeKey, ?string $period, ?string $mtmPeriod, ?string $mtdPeriod, array $currentMetrics, ?array $metricKeys = null): array
    {
        $metricKeys ??= $this->area6PortfolioMetricKeys($scopeKey);
        $date4 = $period ?? '2026-05-19';
        
        $endOfPreviousMonth = Carbon::parse($date4)->subMonthNoOverflow()->endOfMonth()->toDateString();
        $date3 = $mtdPeriod ?: $this->resolveHarianSnapshotPeriodOnOrBefore($endOfPreviousMonth) ?: $endOfPreviousMonth;
        
        $sameDatePreviousMonth = Carbon::parse($date4)->subMonthNoOverflow()->toDateString();
        $date2 = $mtmPeriod ?: $this->resolveHarianSnapshotPeriodOnOrBefore($sameDatePreviousMonth) ?: $sameDatePreviousMonth;
        
        $prevYearEnd = Carbon::parse($date4)->subYear()->endOfYear()->format('Y-m-d');
        $date1 = $this->area6HarianSnapshotSummaryQuery()
            ->where('snapshot_period', '<=', $prevYearEnd)
            ->orderBy('snapshot_period', 'desc')
            ->value('snapshot_period') ?? '2025-12-31';

        $resolvedDates = [$date1, $date2, $date3, $date4];
        $historicalMetrics = collect($resolvedDates)
            ->mapWithKeys(fn ($date) => [
                Carbon::parse($date)->toDateString() => $this->area6ScopeSnapshotMetrics(Carbon::parse($date)->toDateString(), $scopeKey),
            ]);

        $values = ['os' => [], 'sml' => [], 'npl' => []];
        foreach ($resolvedDates as $date) {
            $row = $historicalMetrics->get(Carbon::parse($date)->toDateString());
            $values['os'][] = $row ? round(((float) ($row->{$metricKeys['os_metric']} ?? 0.0)) / 1000000) : 0;
            $values['sml'][] = $row ? round(((float) ($row->{$metricKeys['sml_metric']} ?? 0.0)) / 1000000) : 0;
            $values['npl'][] = $row ? round(((float) ($row->{$metricKeys['npl_metric']} ?? 0.0)) / 1000000) : 0;
        }

        $prefixes = ['YtD', 'MtM', 'MtD', 'Posisi'];
        $formattedDates = [];
        foreach ($resolvedDates as $idx => $d) {
            $formattedDates[] = $prefixes[$idx] . ' (' . Carbon::parse($d)->translatedFormat('d-M-y') . ')';
        }

        return [
            'dates' => $formattedDates,
            'os' => $this->buildArea6TrendMetric($values['os'], $currentMetrics['os'], $this->snapshotMetricDelta($historicalMetrics, $date4, $date2, $metricKeys['os_metric']), 'os'),
            'sml' => $this->buildArea6TrendMetric($values['sml'], $currentMetrics['sml'], $this->snapshotMetricDelta($historicalMetrics, $date4, $date2, $metricKeys['sml_metric']), 'sml'),
            'npl' => $this->buildArea6TrendMetric($values['npl'], $currentMetrics['npl'], $this->snapshotMetricDelta($historicalMetrics, $date4, $date2, $metricKeys['npl_metric']), 'npl'),
        ];
    }

    private function buildArea6TrendMetric(array $values, array $metric, float $momDelta, string $type): array
    {
        [$realization, $target, $pct, $gap] = $metric;
        $points = $this->calculateSvgPoints($values);
        $path = '';
        foreach ($points as $idx => $point) {
            $path .= ($idx === 0 ? 'M' : 'L') . $point['x'] . ',' . $point['y'] . ' ';
        }

        $threshold = $type === 'os' ? 95 : 80;

        return [
            'values' => $values,
            'points' => $points,
            'path' => trim($path),
            'latest' => number_format(round($realization / 1000000), 0, ',', '.'),
            'rka' => number_format(round($target / 1000000), 0, ',', '.'),
            'pct' => number_format($pct, 2, ',', '.') . '%',
            'gap' => $this->formatArea6CardGap($gap),
            'gap_color' => $gap >= 0 ? 'green' : 'red',
            'status_arrow' => $gap >= 0 ? 'up' : 'down',
            'status_bg' => $gap >= 0 ? 'green' : 'red',
            'pct_color' => $pct >= 100 ? 'green' : ($pct >= $threshold ? 'amber' : 'red'),
            'mom_delta' => $momDelta,
        ];
    }

    private function area6ScopeSnapshotMetrics(string $period, string $scopeKey): object
    {
        $empty = (object) [
            'total_os_non_commercial' => 0.0,
            'total_sml_abs_non_commercial' => 0.0,
            'total_npl_abs_non_commercial' => 0.0,
            'sme_os' => 0.0,
            'consumer_os' => 0.0,
            'micro_os' => 0.0,
            'sme_sml' => 0.0,
            'consumer_sml' => 0.0,
            'micro_sml' => 0.0,
            'sme_npl' => 0.0,
            'consumer_npl' => 0.0,
            'micro_npl' => 0.0,
        ];

        if ($period === '' || !Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return $empty;
        }

        $query = DB::table(self::HARIAN_SNAPSHOT_TABLE)
            ->where('snapshot_period', $period)
            ->whereIn(DB::raw('UPPER(TRIM(kanca_label))'), $this->dashboardBranchNames());
        $this->applyArea6PortfolioScope($query, $scopeKey);

        return $query
            ->selectRaw('COALESCE(SUM(COALESCE(total_os_non_commercial, 0)), 0) as total_os_non_commercial')
            ->selectRaw('COALESCE(SUM(COALESCE(total_sml_abs_non_commercial, 0)), 0) as total_sml_abs_non_commercial')
            ->selectRaw('COALESCE(SUM(COALESCE(total_npl_abs_non_commercial, 0)), 0) as total_npl_abs_non_commercial')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(sme_os, 0) <> 0 THEN COALESCE(sme_os, 0) ELSE COALESCE(kecil_non_cashcoll_os, 0) + COALESCE(cashcoll_os, 0) END), 0) as sme_os')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(consumer_os, 0) <> 0 THEN COALESCE(consumer_os, 0) ELSE COALESCE(briguna_konsumer_os, 0) + COALESCE(kpr_os, 0) + COALESCE(kkb_os, 0) END), 0) as consumer_os')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(micro_os, 0) <> 0 THEN COALESCE(micro_os, 0) ELSE COALESCE(briguna_mikro_os, 0) + COALESCE(kupedes_os, 0) + COALESCE(kur_mikro_os, 0) + COALESCE(kur_kecil_os, 0) + COALESCE(kur_kpp_os, 0) END), 0) as micro_os')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(sme_sml, 0) <> 0 THEN COALESCE(sme_sml, 0) ELSE COALESCE(kecil_non_cashcoll_sml, 0) + COALESCE(cashcoll_sml, 0) END), 0) as sme_sml')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(consumer_sml, 0) <> 0 THEN COALESCE(consumer_sml, 0) ELSE COALESCE(briguna_konsumer_sml, 0) + COALESCE(kpr_sml, 0) + COALESCE(kkb_sml, 0) END), 0) as consumer_sml')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(micro_sml, 0) <> 0 THEN COALESCE(micro_sml, 0) ELSE COALESCE(briguna_mikro_sml, 0) + COALESCE(kupedes_sml, 0) + COALESCE(kur_mikro_sml, 0) + COALESCE(kur_kecil_sml, 0) + COALESCE(kur_kpp_sml, 0) END), 0) as micro_sml')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(sme_npl, 0) <> 0 THEN COALESCE(sme_npl, 0) ELSE COALESCE(kecil_non_cashcoll_npl, 0) + COALESCE(cashcoll_npl, 0) END), 0) as sme_npl')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(consumer_npl, 0) <> 0 THEN COALESCE(consumer_npl, 0) ELSE COALESCE(briguna_konsumer_npl, 0) + COALESCE(kpr_npl, 0) + COALESCE(kkb_npl, 0) END), 0) as consumer_npl')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(micro_npl, 0) <> 0 THEN COALESCE(micro_npl, 0) ELSE COALESCE(briguna_mikro_npl, 0) + COALESCE(kupedes_npl, 0) + COALESCE(kur_mikro_npl, 0) + COALESCE(kur_kecil_npl, 0) + COALESCE(kur_kpp_npl, 0) END), 0) as micro_npl')
            ->first() ?: $empty;
    }

    private function parseSegmentMetricWithCurrent(?array $row, string $type, float $current): array
    {
        $metric = $this->parseSegmentMetric($row, $type);
        $target = (float) ($metric['target'] ?? 0.0);
        $pct = $type === 'os'
            ? ($target > 0 ? ($current / $target) * 100 : 0.0)
            : ($current > 0 ? ($target / $current) * 100 : 100.0);
        $maxVal = max($current, $target);

        $metric['realization'] = $current;
        $metric['realization_scaled'] = round($current / 1000000);
        $metric['realization_fmt'] = number_format($metric['realization_scaled'], 0, ',', '.');
        $metric['pct'] = $pct;
        $metric['pct_fmt'] = number_format($pct, 2, ',', '.') . '%';
        $metric['pct_color'] = $this->getArea6AchievementColor($pct, $type);
        $metric['penc_bar_width'] = $maxVal > 0 ? ($current / $maxVal) * 100 : 0.0;
        $metric['rka_bar_width'] = $maxVal > 0 ? ($target / $maxVal) * 100 : 0.0;

        return $metric;
    }

    private function buildArea6ScopeSegmentPerformance(string $scopeKey, Collection $rows, object $snapshotMetrics, string $rkaMonthYear, string $periodFormat, ?string $period = null, ?array $unitKeys = null): array
    {
        $segmentDefinitions = match ($scopeKey) {
            'sme' => [
                ['label' => 'OS SME', 'icon' => 'fas fa-briefcase', 'os' => 'sme_os', 'sml' => 'sme_sml', 'npl' => 'sme_npl'],
            ],
            'consumer' => [
                ['label' => 'OS KONSUMER', 'icon' => 'fas fa-users', 'os' => 'consumer_os', 'sml' => 'consumer_sml', 'npl' => 'consumer_npl'],
            ],
            'ritel' => [
                ['label' => 'OS SME', 'icon' => 'fas fa-briefcase', 'os' => 'sme_os', 'sml' => 'sme_sml', 'npl' => 'sme_npl'],
                ['label' => 'OS KONSUMER', 'icon' => 'fas fa-users', 'os' => 'consumer_os', 'sml' => 'consumer_sml', 'npl' => 'consumer_npl'],
            ],
            'micro' => [
                ['label' => 'OS MIKRO', 'icon' => 'fas fa-store', 'os' => 'micro_os', 'sml' => 'micro_sml', 'npl' => 'micro_npl'],
            ],
            default => [
                ['label' => 'OS SME', 'icon' => 'fas fa-briefcase', 'os' => 'sme_os', 'sml' => 'sme_sml', 'npl' => 'sme_npl'],
                ['label' => 'OS KONSUMER', 'icon' => 'fas fa-users', 'os' => 'consumer_os', 'sml' => 'consumer_sml', 'npl' => 'consumer_npl'],
                ['label' => 'OS MIKRO', 'icon' => 'fas fa-store', 'os' => 'micro_os', 'sml' => 'micro_sml', 'npl' => 'micro_npl'],
            ],
        };

        $segments = [];
        $totals = [
            'os' => ['realization' => 0.0, 'target' => 0.0],
            'sml' => ['realization' => 0.0, 'target' => 0.0],
            'npl' => ['realization' => 0.0, 'target' => 0.0],
        ];

        foreach ($segmentDefinitions as $definition) {
            $os = $this->parseSegmentMetricWithCurrent($rows->firstWhere('key', $definition['os']), 'os', (float) ($snapshotMetrics->{$definition['os']} ?? 0.0));
            $sml = $this->parseSegmentMetricWithCurrent($rows->firstWhere('key', $definition['sml']), 'sml', (float) ($snapshotMetrics->{$definition['sml']} ?? 0.0));
            $npl = $this->parseSegmentMetricWithCurrent($rows->firstWhere('key', $definition['npl']), 'npl', (float) ($snapshotMetrics->{$definition['npl']} ?? 0.0));

            foreach (['os' => $os, 'sml' => $sml, 'npl' => $npl] as $metricKey => $metric) {
                $totals[$metricKey]['realization'] += (float) $metric['realization'];
                $totals[$metricKey]['target'] += (float) $metric['target'];
            }

            $segments[] = [
                'label' => $definition['label'],
                'icon' => $definition['icon'],
                'os' => $os,
                'sml' => $sml,
                'npl' => $npl,
            ];
        }

        $totalOs = $this->formatArea6ScopeSegmentTotal($totals['os']['realization'], $totals['os']['target'], 'os');
        $totalSml = $this->formatArea6ScopeSegmentTotal($totals['sml']['realization'], $totals['sml']['target'], 'sml');
        $totalNpl = $this->formatArea6ScopeSegmentTotal($totals['npl']['realization'], $totals['npl']['target'], 'npl');
        
        // Fetch restruk_os (kolek 1 flag_restruk = Y)
        $restrukOs = 0.0;
        if ($period && $this->hasTable('daily_loan_dinamis') && !in_array($scopeKey, ['sme', 'consumer', 'micro'], true)) {
            $q = DB::table('daily_loan_dinamis')
                ->where('periode', $period)
                ->whereIn(DB::raw('UPPER(TRIM(cabang1))'), $this->dashboardBranchNames());
            
            if (!empty($unitKeys)) {
                $q->whereIn(DB::raw('UPPER(TRIM(unit1))'), array_map('strtoupper', $unitKeys));
            }
            
            $restrukOs = (float) $q->where('kolek', 1)
                ->where(DB::raw("UPPER(TRIM(COALESCE(flag_restruk, '')))"), 'Y')
                ->sum('baki_debet1');
        }

        $totalOsRealization = $totals['os']['realization'];
        $smlRealization = $totals['sml']['realization'];
        $nplRealization = $totals['npl']['realization'];
        $larRealization = $restrukOs + $smlRealization + $nplRealization;

        $smlPct = $totalOsRealization > 0 ? ($smlRealization / $totalOsRealization) * 100 : 0.0;
        $nplPct = $totalOsRealization > 0 ? ($nplRealization / $totalOsRealization) * 100 : 0.0;
        $larPct = $totalOsRealization > 0 ? ($larRealization / $totalOsRealization) * 100 : 0.0;
        $healthyPct = max(0.0, 100.0 - $larPct);
        $lrPct = $totalOsRealization > 0 ? ($restrukOs / $totalOsRealization) * 100 : 0.0;

        return [
            'rka_month_year' => $rkaMonthYear,
            'period_format' => $periodFormat,
            'segments' => $segments,
            'total' => [
                'os' => $totalOs,
                'sml' => $totalSml,
                'npl' => $totalNpl,
            ],
            'composition' => [
                'os' => [
                    'name' => 'LAR',
                    'value' => number_format(round($larRealization / 1000000), 0, ',', '.'),
                    'pct' => number_format($larPct, 2, ',', '.') . '%',
                    'raw_pct' => $larPct,
                ],
                'sml' => [
                    'value' => number_format(round($smlRealization / 1000000), 0, ',', '.'),
                    'pct' => number_format($smlPct, 2, ',', '.') . '%',
                    'raw_pct' => $smlPct,
                ],
                'npl' => [
                    'value' => number_format(round($nplRealization / 1000000), 0, ',', '.'),
                    'pct' => number_format($nplPct, 2, ',', '.') . '%',
                    'raw_pct' => $nplPct,
                ],
                'total' => [
                    'value' => number_format(round($totalOsRealization / 1000000), 0, ',', '.'),
                ],
                'angles' => [
                    'healthy' => $healthyPct,
                    'lr' => $healthyPct + $lrPct,
                    'sml' => $healthyPct + $lrPct + $smlPct,
                ],
                'center' => [
                    'pct' => number_format($larPct, 2, ',', '.') . '%',
                    'label' => 'LAR SHARE',
                ]
            ],
        ];
    }

    private function formatArea6ScopeSegmentTotal(float $realization, float $target, string $type): array
    {
        $pct = $type === 'os'
            ? ($target > 0 ? ($realization / $target) * 100 : 0.0)
            : ($realization > 0 ? ($target / $realization) * 100 : 100.0);
        $max = max($realization, $target);

        return [
            'realization_fmt' => number_format(round($realization / 1000000), 0, ',', '.'),
            'target_fmt' => number_format(round($target / 1000000), 0, ',', '.'),
            'pct_fmt' => number_format($pct, 2, ',', '.') . '%',
            'pct_color' => $this->getArea6AchievementColor($pct, $type),
            'penc_bar_width' => $max > 0 ? ($realization / $max) * 100 : 0.0,
            'rka_bar_width' => $max > 0 ? ($target / $max) * 100 : 0.0,
        ];
    }

    private function formatArea6CompositionMetric(float $value, float $total): array
    {
        $pct = $total > 0 ? ($value / $total) * 100 : 0.0;

        return [
            'value' => number_format(round($value / 1000000), 0, ',', '.'),
            'pct' => number_format($pct, 2, ',', '.') . '%',
            'raw_pct' => $pct,
        ];
    }

    private function area6ScopeUnitKeys(string $period, string $scopeKey): array
    {
        if ($period === '' || !Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return [];
        }

        $query = $this->area6HarianSnapshotScopeQuery($period, false)
            ->select('unit_key');
        $this->applyArea6UnitLabelScope($query, $scopeKey);

        return $query->pluck('unit_key')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function applyArea6PortfolioScope($query, string $scopeKey): void
    {
        if (in_array($scopeKey, ['area6', 'cabang_konsol', 'sme', 'consumer', 'micro'], true)) {
            $query->whereRaw('kanca_key = unit_key');

            return;
        }

        $query->whereRaw('kanca_key <> unit_key');
        $this->applyArea6UnitLabelScope($query, $scopeKey);
    }

    private function applyArea6UnitLabelScope($query, string $scopeKey): void
    {
        $labelColumn = Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'unit_label')
            ? 'unit_label'
            : (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'uker_label') ? 'uker_label' : null);

        if ($labelColumn === null) {
            return;
        }

        if ($scopeKey === 'ritel') {
            $query->where(function ($nested) use ($labelColumn): void {
                $nested->whereRaw("UPPER(TRIM({$labelColumn})) LIKE 'KC%'")
                    ->orWhereRaw("UPPER(TRIM({$labelColumn})) LIKE 'KCP%'");
            });

            return;
        }

        if ($scopeKey === 'micro') {
            $query->whereRaw("UPPER(TRIM({$labelColumn})) LIKE 'UNIT%'");
        }
    }

    private function area6ScopeLabel(string $scopeKey): string
    {
        return match ($scopeKey) {
            'area6' => 'Area 6',
            'sme' => 'SME',
            'consumer' => 'Konsumer',
            'ritel' => 'Ritel',
            'micro' => 'Micro',
            default => 'Cabang Konsol',
        };
    }

    private function snapshotMetricDelta(Collection $metricsByPeriod, ?string $currentPeriod, ?string $comparisonPeriod, string $column): float
    {
        if (!$currentPeriod || !$comparisonPeriod) {
            return 0.0;
        }

        $currentKey = Carbon::parse($currentPeriod)->toDateString();
        $comparisonKey = Carbon::parse($comparisonPeriod)->toDateString();
        $current = $metricsByPeriod->get($currentKey);
        $comparison = $metricsByPeriod->get($comparisonKey);

        return (float) ($current->{$column} ?? 0.0) - (float) ($comparison->{$column} ?? 0.0);
    }

    private function calculateSvgPoints(array $values, int $width = 110, int $height = 50, int $padding = 12): array
    {
        $min = min($values);
        $max = max($values);
        $range = $max - $min;
        
        $xCoords = [10, 40, 70, 100];
        $points = [];
        
        foreach ($values as $index => $val) {
            $x = $xCoords[$index] ?? 10;
            if ($range > 0) {
                // Map values so that max is at top ($padding) and min is at bottom ($height - $padding)
                $y = $height - $padding - (($val - $min) / $range) * ($height - 2 * $padding);
            } else {
                $y = $height / 2;
            }
            $points[] = [
                'x' => $x,
                'y' => round($y, 1),
                'val' => $val,
                'val_fmt' => number_format($val, 0, ',', '.')
            ];
        }
        
        return $points;
    }

    private function formatArea6CardDelta(float $delta, string $metricType): array
    {
        $scaled = round($delta / 1000000);
        $isNegative = $scaled < 0;
        $absVal = abs($scaled);
        
        $formattedVal = number_format($absVal, 0, ',', '.');
        if ($isNegative) {
            $valueStr = '(' . $formattedVal . ')';
            $type = 'down';
        } else {
            $valueStr = '+' . $formattedVal;
            $type = 'up';
        }
        
        if ($metricType === 'os') {
            $color = $isNegative ? 'red' : 'green';
        } else {
            // SML/NPL: always red in the mockup
            $color = 'red';
        }
        
        return [
            'raw' => $delta,
            'value' => $valueStr,
            'type' => $type,
            'color' => $color,
        ];
    }

    private function formatArea6CardGap(float $gap): string
    {
        $scaled = round($gap / 1000000);
        $isNegative = $scaled < 0;
        $absVal = abs($scaled);
        
        $formattedVal = number_format($absVal, 0, ',', '.');
        if ($isNegative) {
            return '(' . $formattedVal . ')';
        } else {
            return '+' . $formattedVal;
        }
    }

    private function getArea6AchievementColor(float $pct, string $metricType): string
    {
        if ($metricType === 'os') {
            if ($pct >= 100) {
                return 'green';
            } elseif ($pct >= 95) {
                return 'amber';
            } else {
                return 'red';
            }
        } else {
            if ($pct >= 100) {
                return 'green';
            } elseif ($pct >= 90) {
                return 'amber';
            } else {
                return 'red';
            }
        }
    }

    private function parseSegmentMetric(?array $row, string $type): array
    {
        $realization = (float) ($row['values']['current'] ?? 0.0);
        $target = (float) ($row['values']['rka'] ?? 0.0);

        if ($type === 'os') {
            $pct = $target > 0 ? ($realization / $target) * 100 : 0.0;
            $color = $this->getArea6AchievementColor($pct, 'os');
        } else {
            $pct = $realization > 0 ? ($target / $realization) * 100 : 100.0;
            $color = $this->getArea6AchievementColor($pct, $type);
        }

        $maxVal = max($realization, $target);
        $pencBarWidth = $maxVal > 0 ? ($realization / $maxVal) * 100 : 0.0;
        $rkaBarWidth = $maxVal > 0 ? ($target / $maxVal) * 100 : 0.0;

        $realizationScaled = round($realization / 1000000);
        $targetScaled = round($target / 1000000);

        return [
            'realization' => $realization,
            'target' => $target,
            'realization_scaled' => $realizationScaled,
            'target_scaled' => $targetScaled,
            'realization_fmt' => number_format($realizationScaled, 0, ',', '.'),
            'target_fmt' => number_format($targetScaled, 0, ',', '.'),
            'pct' => $pct,
            'pct_fmt' => number_format($pct, 2, ',', '.') . '%',
            'pct_color' => $color,
            'penc_bar_width' => $pencBarWidth,
            'rka_bar_width' => $rkaBarWidth,
        ];
    }

    private function emptyArea6PortfolioLanding(): array
    {
        return [
            'title' => 'Ringkasan Area 6',
            'subtitle' => 'Data lintas report belum tersedia.',
            'period' => null,
            'period_label' => 'Belum ada data',
            'loan_period_label' => 'Belum ada data',
            'loan_detail_period_label' => 'Belum ada data',
            'cards' => [],
            'rankings' => [],
            'segment_performance' => [],
            'overall_trends' => [],
        ];
    }

    private function resolveArea6DailyLoanPeriod(?string $requestedPeriod): ?string
    {
        if (!Schema::hasTable('daily_loan_dinamis')) {
            return null;
        }

        $cacheKey = 'dashboard_simpanan:area6_daily_loan_period:' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $this->reportCacheVersion() . ':' . ($requestedPeriod ?? 'latest');

        return Cache::remember($cacheKey, now()->addMinutes(2), function () use ($requestedPeriod) {
            $query = DB::table('daily_loan_dinamis');
            $this->applyDashboardBranchScope($query, 'cabang1');

            if ($requestedPeriod) {
                $period = $query
                    ->where('periode', '<=', $requestedPeriod)
                    ->orderByDesc('periode')
                    ->value('periode');

                if ($period) {
                    return Carbon::parse($period)->toDateString();
                }
            }

            $period = $query
                ->orderByDesc('periode')
                ->value('periode');

            return $period ? Carbon::parse($period)->toDateString() : null;
        });
    }

    private function fetchArea6HarianPortfolio(): array
    {
        $empty = [
            'period' => null,
            'totals' => [
                'total_os' => 0.0,
                'total_os_non_commercial' => 0.0,
                'total_simpanan' => 0.0,
                'sml_abs' => 0.0,
                'npl_abs' => 0.0,
                'sml_pct' => 0.0,
                'npl_pct' => 0.0,
                'ldr_pct' => 0.0,
                'casa_pct' => 0.0,
                'total_casa' => 0.0,
                'rec_dh_total' => 0.0,
            ],
            'unit_rows' => [],
            'branch_rows' => [],
        ];

        if (!Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return $empty;
        }

        $period = $this->area6HarianSnapshotSummaryQuery()->max('snapshot_period');
        if (!$period) {
            return $empty;
        }

        $summaryQuery = $this->area6HarianSnapshotScopeQuery((string) $period, true);
        $summary = $summaryQuery
            ->selectRaw('COUNT(*) as row_count')
            ->selectRaw('COALESCE(SUM(COALESCE(total_os, 0)), 0) as total_os')
            ->selectRaw('COALESCE(SUM(COALESCE(total_os_non_commercial, 0)), 0) as total_os_non_commercial')
            ->selectRaw('COALESCE(SUM(COALESCE(total_simpanan, 0)), 0) as total_simpanan')
            ->selectRaw('COALESCE(SUM(COALESCE(total_sml_abs_non_commercial, 0)), 0) as sml_abs')
            ->selectRaw('COALESCE(SUM(COALESCE(total_npl_abs_non_commercial, 0)), 0) as npl_abs')
            ->selectRaw('COALESCE(SUM(COALESCE(total_casa, 0)), 0) as total_casa')
            ->selectRaw('COALESCE(SUM(COALESCE(rec_dh_total, 0)), 0) as rec_dh_total')
            ->first();

        $branchLabelExpression = Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_label')
            ? 'kanca_label'
            : (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'branch_label') ? 'branch_label' : "''");
        $unitLabelExpression = Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'unit_label')
            ? 'unit_label'
            : (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'uker_label') ? 'uker_label' : "''");

        $unitRows = $this->area6HarianSnapshotScopeQuery((string) $period, false)
            ->selectRaw("COALESCE({$branchLabelExpression}, '') as branch_label")
            ->selectRaw("COALESCE({$unitLabelExpression}, '') as unit_label")
            ->selectRaw('COALESCE(total_os, 0) as total_os')
            ->selectRaw('COALESCE(total_os_non_commercial, 0) as total_os_non_commercial')
            ->selectRaw('COALESCE(total_sml_abs_non_commercial, 0) as sml_abs')
            ->selectRaw('COALESCE(total_npl_abs_non_commercial, 0) as npl_abs')
            ->get()
            ->map(function ($row) {
                $osNonCommercial = (float) ($row->total_os_non_commercial ?? 0);
                $smlAbs = (float) ($row->sml_abs ?? 0);
                $nplAbs = (float) ($row->npl_abs ?? 0);

                return [
                    'branch' => trim((string) ($row->branch_label ?? '')),
                    'unit' => trim((string) ($row->unit_label ?? '')),
                    'total_os' => (float) ($row->total_os ?? 0),
                    'total_os_non_commercial' => $osNonCommercial,
                    'sml_abs' => $smlAbs,
                    'npl_abs' => $nplAbs,
                    'sml_pct' => $this->percentOf($smlAbs, $osNonCommercial),
                    'npl_pct' => $this->percentOf($nplAbs, $osNonCommercial),
                ];
            })
            ->filter(fn (array $row) => $row['unit'] !== '' && $this->isArea6MicroLabel($row['unit']))
            ->values()
            ->all();

        $summaryBranchRows = $this->area6HarianSnapshotScopeQuery((string) $period, true)
            ->selectRaw("COALESCE({$branchLabelExpression}, '') as branch_label")
            ->selectRaw('COALESCE(total_os, 0) as total_os')
            ->selectRaw('COALESCE(total_os_non_commercial, 0) as total_os_non_commercial')
            ->selectRaw('COALESCE(total_sml_abs_non_commercial, 0) as sml_abs')
            ->selectRaw('COALESCE(total_npl_abs_non_commercial, 0) as npl_abs')
            ->get()
            ->map(function ($row) {
                $osNonCommercial = (float) ($row->total_os_non_commercial ?? 0);
                $smlAbs = (float) ($row->sml_abs ?? 0);
                $nplAbs = (float) ($row->npl_abs ?? 0);

                return [
                    'branch' => trim((string) ($row->branch_label ?? '')),
                    'unit' => 'Ritel Area 6',
                    'total_os' => (float) ($row->total_os ?? 0),
                    'total_os_non_commercial' => $osNonCommercial,
                    'sml_abs' => $smlAbs,
                    'npl_abs' => $nplAbs,
                    'sml_pct' => $this->percentOf($smlAbs, $osNonCommercial),
                    'npl_pct' => $this->percentOf($nplAbs, $osNonCommercial),
                ];
            })
            ->filter(fn (array $row) => $row['branch'] !== '')
            ->values()
            ->all();

        $retailRows = $this->area6HarianSnapshotScopeQuery((string) $period, false)
            ->selectRaw("COALESCE({$branchLabelExpression}, '') as branch_label")
            ->selectRaw("COALESCE({$unitLabelExpression}, '') as unit_label")
            ->selectRaw('COALESCE(total_os, 0) as total_os')
            ->selectRaw('COALESCE(total_os_non_commercial, 0) as total_os_non_commercial')
            ->selectRaw('COALESCE(total_sml_abs_non_commercial, 0) as sml_abs')
            ->selectRaw('COALESCE(total_npl_abs_non_commercial, 0) as npl_abs')
            ->get()
            ->map(function ($row) {
                $osNonCommercial = (float) ($row->total_os_non_commercial ?? 0);
                $smlAbs = (float) ($row->sml_abs ?? 0);
                $nplAbs = (float) ($row->npl_abs ?? 0);

                return [
                    'branch' => trim((string) ($row->unit_label ?? '')),
                    'unit' => trim((string) ($row->branch_label ?? '')),
                    'total_os' => (float) ($row->total_os ?? 0),
                    'total_os_non_commercial' => $osNonCommercial,
                    'sml_abs' => $smlAbs,
                    'npl_abs' => $nplAbs,
                    'sml_pct' => $this->percentOf($smlAbs, $osNonCommercial),
                    'npl_pct' => $this->percentOf($nplAbs, $osNonCommercial),
                ];
            })
            ->filter(fn (array $row) => $row['branch'] !== '' && $this->isArea6RetailLabel($row['branch']))
            ->values()
            ->all();

        $totalOsNonCommercial = (float) ($summary->total_os_non_commercial ?? 0);
        $totalSimpanan = (float) ($summary->total_simpanan ?? 0);
        $smlAbs = (float) ($summary->sml_abs ?? 0);
        $nplAbs = (float) ($summary->npl_abs ?? 0);
        $totalCasa = (float) ($summary->total_casa ?? 0);

        return [
            'period' => (string) $period,
            'totals' => [
                'total_os' => (float) ($summary->total_os ?? 0),
                'total_os_non_commercial' => $totalOsNonCommercial,
                'total_simpanan' => $totalSimpanan,
                'sml_abs' => $smlAbs,
                'npl_abs' => $nplAbs,
                'sml_pct' => $this->percentOf($smlAbs, $totalOsNonCommercial),
                'npl_pct' => $this->percentOf($nplAbs, $totalOsNonCommercial),
                'ldr_pct' => $this->percentOf($totalOsNonCommercial, $totalSimpanan),
                'casa_pct' => $this->percentOf($totalCasa, $totalSimpanan),
                'total_casa' => $totalCasa,
                'rec_dh_total' => (float) ($summary->rec_dh_total ?? 0),
            ],
            'unit_rows' => $unitRows,
            'branch_rows' => $retailRows ?: $summaryBranchRows,
        ];
    }

    private function isArea6RetailLabel(?string $label): bool
    {
        return preg_match('/^(KC|KCP)\b/i', trim((string) $label)) === 1;
    }

    private function isArea6MicroLabel(?string $label): bool
    {
        return preg_match('/^UNIT\b/i', trim((string) $label)) === 1;
    }

    private function area6HarianSnapshotScopeQuery(string $period, bool $summaryRows)
    {
        $query = DB::table(self::HARIAN_SNAPSHOT_TABLE)
            ->where('snapshot_period', $period);

        if ($this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_label')) {
            $query->whereIn(DB::raw('UPPER(TRIM(kanca_label))'), $this->dashboardBranchNames());
        } elseif ($this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'branch_label')) {
            $query->whereIn(DB::raw('UPPER(TRIM(branch_label))'), $this->dashboardBranchNames());
        }

        if ($this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_key') && $this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'unit_key')) {
            return $summaryRows
                ? $query->whereColumn('kanca_key', 'unit_key')
                : $query->whereColumn('kanca_key', '<>', 'unit_key');
        }

        if ($this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'scope')) {
            return $query->where('scope', $summaryRows ? 'branch' : 'unit');
        }

        return $query;
    }

    private function area6HarianSnapshotSummaryQuery()
    {
        $query = DB::table(self::HARIAN_SNAPSHOT_TABLE);

        if ($this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_label')) {
            $query->whereIn(DB::raw('UPPER(TRIM(kanca_label))'), $this->dashboardBranchNames());
        } elseif ($this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'branch_label')) {
            $query->whereIn(DB::raw('UPPER(TRIM(branch_label))'), $this->dashboardBranchNames());
        }

        if ($this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_key') && $this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'unit_key')) {
            return $query->whereColumn('kanca_key', 'unit_key');
        }

        if ($this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'scope')) {
            return $query->where('scope', 'branch');
        }

        return $query;
    }

    private function querySimpananSummaryFromHarianSnapshot(string $period): ?array
    {
        if (!Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return null;
        }

        $row = $this->area6HarianSnapshotSummaryQuery()
            ->where('snapshot_period', $period)
            ->selectRaw('COUNT(*) as branch_count')
            ->selectRaw('COALESCE(SUM(COALESCE(total_simpanan, 0)), 0) as total_balance')
            ->selectRaw('COALESCE(SUM(COALESCE(tabungan_ritel, 0) + COALESCE(tabungan_mikro, 0) + COALESCE(tabungan_wholesale, 0)), 0) as tabungan_balance')
            ->selectRaw('COALESCE(SUM(COALESCE(giro_ritel, 0) + COALESCE(giro_mikro, 0) + COALESCE(giro_wholesale, 0)), 0) as giro_balance')
            ->selectRaw('COALESCE(SUM(COALESCE(source_savings_row_count, 0)), 0) as source_row_count')
            ->selectRaw('MAX(updated_at) as source_updated_at')
            ->first();

        if (!$row || (int) ($row->branch_count ?? 0) === 0) {
            return null;
        }

        $totalBalance = (float) ($row->total_balance ?? 0);
        $tabunganBalance = (float) ($row->tabungan_balance ?? 0);
        $giroBalance = (float) ($row->giro_balance ?? 0);
        $sourceRows = (int) ($row->source_row_count ?? 0);

        return [
            'total_balance' => $totalBalance,
            'account_count' => $sourceRows,
            'cif_count' => 0,
            'branch_count' => (int) ($row->branch_count ?? 0),
            'unit_count' => $this->countHarianUnitRows($period),
            'tabungan_balance' => $tabunganBalance,
            'giro_balance' => $giroBalance,
            'other_balance' => max(0, $totalBalance - $tabunganBalance - $giroBalance),
            'avg_balance_per_cif' => 0,
            'source_updated_at' => $row->source_updated_at ?? null,
            'source_table' => self::HARIAN_SNAPSHOT_TABLE,
            'branch_source_table' => self::HARIAN_SNAPSHOT_TABLE,
            'source_note' => 'Agregasi dari summary kanca Dashboard Harian untuk posisi yang sama.',
            'snapshot_completeness' => 'complete',
            'partial_branches' => [],
        ];
    }

    private function queryLoanSummaryFromHarianSnapshot(string $period): ?array
    {
        if (!Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return null;
        }

        $row = $this->area6HarianSnapshotSummaryQuery()
            ->where('snapshot_period', $period)
            ->selectRaw('COUNT(*) as branch_count')
            ->selectRaw('COALESCE(SUM(COALESCE(total_os, 0)), 0) as total_balance')
            ->selectRaw('COALESCE(SUM(COALESCE(source_loan_row_count, 0)), 0) as source_row_count')
            ->selectRaw('MAX(updated_at) as source_updated_at')
            ->first();

        if (!$row || (int) ($row->branch_count ?? 0) === 0) {
            return null;
        }

        return [
            'total_balance' => (float) ($row->total_balance ?? 0),
            'account_count' => (int) ($row->source_row_count ?? 0),
            'branch_count' => (int) ($row->branch_count ?? 0),
            'unit_count' => $this->countHarianUnitRows($period),
            'source_updated_at' => $row->source_updated_at ?? null,
            'source_table' => self::HARIAN_SNAPSHOT_TABLE,
            'branch_source_table' => self::HARIAN_SNAPSHOT_TABLE,
            'source_note' => 'Agregasi dari summary kanca Dashboard Harian untuk posisi yang sama.',
        ];
    }

    private function countHarianUnitRows(string $period): int
    {
        if (!Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return 0;
        }

        return (int) $this->area6HarianSnapshotScopeQuery($period, false)->count();
    }

    private function queryTopBranchesFromHarianSnapshot(string $period): ?Collection
    {
        if (!Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return null;
        }

        $branchLabelExpression = Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_label')
            ? 'kanca_label'
            : (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'branch_label') ? 'branch_label' : "''");

        $query = $this->area6HarianSnapshotSummaryQuery()
            ->where('snapshot_period', $period)
            ->selectRaw("COALESCE({$branchLabelExpression}, '') as kantor_cabang")
            ->selectRaw('COALESCE(SUM(COALESCE(total_simpanan, 0)), 0) as total_balance');

        if ($branchLabelExpression !== "''") {
            $query->groupBy($branchLabelExpression);
        }

        $rows = $query->orderByDesc('total_balance')
            ->limit(5)
            ->get();

        return $rows->isNotEmpty() ? $rows : null;
    }

    private function queryLoanTopBranchesFromHarianSnapshot(string $period): ?Collection
    {
        if (!Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return null;
        }

        $branchLabelExpression = Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_label')
            ? 'kanca_label'
            : (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'branch_label') ? 'branch_label' : "''");

        $query = $this->area6HarianSnapshotSummaryQuery()
            ->where('snapshot_period', $period)
            ->selectRaw("COALESCE({$branchLabelExpression}, '') as cabang1")
            ->selectRaw('COALESCE(SUM(COALESCE(total_os, 0)), 0) as total_balance');

        if ($branchLabelExpression !== "''") {
            $query->groupBy($branchLabelExpression);
        }

        $rows = $query->orderByDesc('total_balance')
            ->limit(5)
            ->get();

        return $rows->isNotEmpty() ? $rows : null;
    }

    private function buildArea6RankingGroups(array $rows, array $kts, array $smallArrears, string $scope): array
    {
        $targetLabel = $scope === 'unit' ? 'unit kerja' : 'KC/KCP';

        return [
            [
                'title' => '5 OS Terbesar',
                'tone' => 'blue',
                'rows' => $this->rankHarianUnits($rows, 'total_os', 'desc', 5, 'currency', false, null, $scope),
            ],
            [
                'title' => '5 OS Terkecil',
                'tone' => 'slate',
                'rows' => $this->rankHarianUnits($rows, 'total_os', 'asc', 5, 'currency', true, null, $scope),
            ],
            [
                'title' => '5 SML Nominal',
                'tone' => 'amber',
                'rows' => $this->rankHarianUnits($rows, 'sml_abs', 'desc', 5, 'currency', false, 'sml_pct', $scope),
            ],
            [
                'title' => '5 SML Rasio',
                'tone' => 'red',
                'rows' => $this->rankHarianUnits($rows, 'sml_pct', 'desc', 5, 'percent', false, 'sml_abs', $scope),
            ],
            [
                'title' => '5 NPL Nominal',
                'tone' => 'orange',
                'rows' => $this->rankHarianUnits($rows, 'npl_abs', 'desc', 5, 'currency', false, 'npl_pct', $scope),
            ],
            [
                'title' => '5 NPL Rasio',
                'tone' => 'red',
                'rows' => $this->rankHarianUnits($rows, 'npl_pct', 'desc', 5, 'percent', false, 'npl_abs', $scope),
            ],
            [
                'title' => '5 KTS Terbanyak',
                'tone' => 'orange',
                'rows' => $kts['rows'],
            ],
            [
                'title' => '5 Tunggakan Kecil',
                'tone' => 'teal',
                'rows' => $smallArrears['rows'],
            ],
        ];
    }

    private function rankHarianUnits(array $rows, string $field, string $direction, int $limit, string $format, bool $positiveOnly = false, ?string $secondaryField = null, string $scope = 'unit'): array
    {
        $labelField = in_array($scope, ['unit', 'unit_kerja']) ? 'unit' : 'branch';
        $metaField = in_array($scope, ['unit', 'unit_kerja']) ? 'branch' : 'unit';

        $sorted = collect($rows)
            ->filter(fn (array $row) => !$positiveOnly || (float) ($row[$field] ?? 0) > 0)
            ->sortBy([
                [$field, $direction],
                [$labelField, 'asc'],
            ])
            ->take($limit)
            ->values();

        return $sorted->map(function (array $row, int $index) use ($field, $format, $secondaryField, $labelField, $metaField) {
            $value = (float) ($row[$field] ?? 0);
            $secondary = $secondaryField ? (float) ($row[$secondaryField] ?? 0) : null;

            return [
                'rank' => $index + 1,
                'label' => $row[$labelField] ?: '-',
                'meta' => $row[$metaField] ?: 'Area 6',
                'value' => $format === 'percent' ? $this->formatPercentTwo($value) : $this->formatCurrencyCompact($value),
                'sub' => $secondaryField
                    ? ($secondaryField === 'sml_pct' || $secondaryField === 'npl_pct'
                        ? $this->formatPercentTwo((float) $secondary)
                        : $this->formatCurrencyCompact((float) $secondary))
                    : null,
            ];
        })->all();
    }

    private function buildArea6KtsRanking(?string $period, string $scope = 'unit'): array
    {
        $empty = ['total_count' => 0, 'total_os' => 0.0, 'rows' => []];
        if (!$period || !Schema::hasTable('daily_loan_dinamis')) {
            return $empty;
        }

        foreach (['cabang1', 'unit1', 'status_rekening1', 'baki_debet1', 'kolek', 'umur_tunggakan'] as $column) {
            if (!Schema::hasColumn('daily_loan_dinamis', $column)) {
                return $empty;
            }
        }

        $cacheKey = 'dashboard_simpanan:area6_kts_top5:v' . $this->reportCacheVersion() . ':' . $period . ':' . $scope;

        return Cache::remember($cacheKey, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES), function () use ($period, $scope) {
            $actualKolekExpression = "CAST(kolek AS UNSIGNED)";
            $umurTunggakanExpression = "CAST(umur_tunggakan AS SIGNED)";
            $expectedKolekExpression = "CASE
                WHEN {$umurTunggakanExpression} <= 0 THEN 1
                WHEN {$umurTunggakanExpression} <= 90 THEN 2
                WHEN {$umurTunggakanExpression} <= 120 THEN 3
                WHEN {$umurTunggakanExpression} <= 180 THEN 4
                ELSE 5
            END";

            $baseQuery = DB::table('daily_loan_dinamis')
                ->where('periode', $period)
                ->whereIn('cabang1', $this->dashboardBranchDisplayNames())
                ->whereIn('status_rekening1', ['1', '3'])
                ->where('baki_debet1', '>', 0)
                ->whereIn('kolek', ['1', '2', '3', '4', '5'])
                ->whereNotNull('umur_tunggakan')
                ->whereRaw("{$actualKolekExpression} <> {$expectedKolekExpression}");

            $groupColumns = $scope === 'branch' ? ['cabang1'] : ['cabang1', 'unit1'];
            $rankedRows = (clone $baseQuery)
                ->select($groupColumns)
                ->selectRaw('COUNT(*) as mismatch_count')
                ->selectRaw('COALESCE(SUM(COALESCE(baki_debet1, 0)), 0) as outstanding_balance');

            $this->applyArea6DailyLoanUnitScope($rankedRows, $scope);

            $rankedRows = $rankedRows
                ->groupBy($groupColumns)
                ->orderByDesc('mismatch_count')
                ->orderByDesc('outstanding_balance')
                ->limit(5)
                ->get();

            $total = (clone $baseQuery)
                ->selectRaw('COUNT(*) as mismatch_count')
                ->selectRaw('COALESCE(SUM(COALESCE(baki_debet1, 0)), 0) as outstanding_balance')
                ->first();

            $ranked = $rankedRows
                ->map(function ($row, int $index) use ($scope) {
                    return [
                        'rank' => $index + 1,
                        'label' => $scope === 'branch' ? (string) ($row->cabang1 ?? '-') : (string) ($row->unit1 ?? '-'),
                        'meta' => in_array($scope, ['unit', 'unit_kerja']) ? (string) ($row->cabang1 ?? 'Area 6') : 'Ritel Area 6',
                        'value' => $this->formatInteger((int) ($row->mismatch_count ?? 0)) . ' rek',
                        'sub' => $this->formatCurrencyCompact((float) ($row->outstanding_balance ?? 0)),
                    ];
                })
                ->all();

            return [
                'total_count' => (int) ($total->mismatch_count ?? 0),
                'total_os' => (float) ($total->outstanding_balance ?? 0),
                'rows' => $ranked,
            ];
        });
    }

    private function buildArea6SmallArrearsRanking(?string $period, string $scope = 'unit'): array
    {
        $empty = ['total_count' => 0, 'total_amount' => 0.0, 'rows' => []];
        if (!$period || !Schema::hasTable('daily_loan_dinamis')) {
            return $empty;
        }

        foreach (['cabang1', 'unit1', 'tunggakan_pokok', 'tunggakan_bunga'] as $column) {
            if (!Schema::hasColumn('daily_loan_dinamis', $column)) {
                return $empty;
            }
        }

        $cacheKey = 'dashboard_simpanan:area6_small_arrears_top5:v' . $this->reportCacheVersion() . ':' . $period . ':' . $scope;

        return Cache::remember($cacheKey, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES), function () use ($period, $scope) {
            $accountColumn = Schema::hasColumn('daily_loan_dinamis', 'nomor_rekening1') ? 'nomor_rekening1' : null;
            $penaltyColumn = Schema::hasColumn('daily_loan_dinamis', 'tunggakan_penalti')
                ? 'tunggakan_penalti'
                : (Schema::hasColumn('daily_loan_dinamis', 'tunggakan_pinalti') ? 'tunggakan_pinalti' : null);
            $totalExpression = 'COALESCE(tunggakan_pokok, 0) + COALESCE(tunggakan_bunga, 0)';
            if ($penaltyColumn !== null) {
                $totalExpression .= " + COALESCE({$penaltyColumn}, 0)";
            }

            $groupColumns = $scope === 'branch' ? ['cabang1'] : ['cabang1', 'unit1'];
            $query = DB::table('daily_loan_dinamis')
                ->where('periode', $period)
                ->whereIn('cabang1', $this->dashboardBranchDisplayNames())
                ->whereRaw("({$totalExpression}) > 0 AND ({$totalExpression}) <= 100000")
                ->select($groupColumns)
                ->selectRaw('SUM(' . $totalExpression . ') as total_amount');

            if ($scope !== 'branch') {
                $this->applyArea6DailyLoanUnitScope($query, $scope);
            }

            if ($accountColumn !== null) {
                $query->selectRaw("COUNT(DISTINCT {$accountColumn}) as current_count");
            } else {
                $query->selectRaw('COUNT(*) as current_count');
            }

            $rows = $query
                ->groupBy($groupColumns)
                ->orderByDesc('current_count')
                ->orderByDesc('total_amount')
                ->limit(5)
                ->get();

            $total = DB::table('daily_loan_dinamis')
                ->where('periode', $period)
                ->whereIn('cabang1', $this->dashboardBranchDisplayNames())
                ->whereRaw("({$totalExpression}) > 0 AND ({$totalExpression}) <= 100000")
                ->selectRaw(($accountColumn !== null ? "COUNT(DISTINCT {$accountColumn})" : 'COUNT(*)') . ' as total_count')
                ->selectRaw('SUM(' . $totalExpression . ') as total_amount')
                ->first();

            return [
                'total_count' => (int) ($total->total_count ?? 0),
                'total_amount' => (float) ($total->total_amount ?? 0),
                'rows' => $rows->map(function ($row, int $index) use ($scope) {
                    return [
                        'rank' => $index + 1,
                        'label' => $scope === 'branch' ? (string) ($row->cabang1 ?? '-') : (string) ($row->unit1 ?? '-'),
                        'meta' => in_array($scope, ['unit', 'unit_kerja']) ? (string) ($row->cabang1 ?? 'Area 6') : 'Ritel Area 6',
                        'value' => $this->formatInteger((int) ($row->current_count ?? 0)) . ' rek',
                        'sub' => $this->formatCurrencyCompact((float) ($row->total_amount ?? 0)),
                    ];
                })->all(),
            ];
        });
    }

    private function applyArea6DailyLoanUnitScope($query, string $scope): void
    {
        if ($scope === 'branch') {
            return;
        }

        $query->whereNotNull('unit1')->where('unit1', '<>', '');

        if ($scope === 'unit_kerja') {
            return;
        }

        if ($scope === 'retail') {
            $query->where(function ($nested): void {
                $nested
                    ->whereRaw('UPPER(TRIM(unit1)) LIKE ?', ['KC %'])
                    ->orWhereRaw('UPPER(TRIM(unit1)) LIKE ?', ['KCP %']);
            });

            return;
        }

        $query->whereRaw('UPPER(TRIM(unit1)) LIKE ?', ['UNIT %']);
    }

    private function formatSourcePeriodLabel(?string $period): string
    {
        if (!$period) {
            return 'Belum ada data';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $period)) {
            return $period;
        }

        return $this->formatPeriodLabel($period);
    }

    private function buildActivities(
        array $currentSummary,
        array $previousSummary,
        array $loanCurrentSummary,
        array $loanPreviousSummary,
        array $composition,
        string $period,
        ?string $loanPeriod,
        string $topBranchLabel,
        string $topBranchDisplay,
        string $loanTopBranchLabel,
        string $loanTopBranchDisplay
    ): array {
        return [
            [
                'class' => 'badge-success',
                'title' => 'Posisi simpanan ' . $this->formatPeriodLabel($period) . ' sudah terbaca',
                'time' => $this->formatCurrencyFull($currentSummary['total_balance']),
            ],
            [
                'class' => $this->deltaClass($this->percentChange($currentSummary['total_balance'], $previousSummary['total_balance']), true),
                'title' => 'Growth saldo dibanding periode sebelumnya',
                'time' => $this->formatSignedPercent($this->percentChange($currentSummary['total_balance'], $previousSummary['total_balance'])),
            ],
            [
                'class' => 'badge-primary',
                'title' => 'Kontributor simpanan terbesar: ' . $topBranchLabel,
                'time' => $topBranchDisplay,
            ],
            [
                'class' => 'badge-info',
                'title' => 'Posisi pinjaman ' . ($loanPeriod ? $this->formatPeriodLabel($loanPeriod) : 'belum tersedia'),
                'time' => $this->formatCurrencyFull($loanCurrentSummary['total_balance']),
            ],
            [
                'class' => $this->deltaClass($this->percentChange($loanCurrentSummary['total_balance'], $loanPreviousSummary['total_balance']), true),
                'title' => 'Growth pinjaman dibanding periode sebelumnya',
                'time' => $this->formatSignedPercent($this->percentChange($loanCurrentSummary['total_balance'], $loanPreviousSummary['total_balance'])),
            ],
            [
                'class' => $composition['badge_class'],
                'title' => 'Fokus cabang pinjaman: ' . $loanTopBranchLabel,
                'time' => $loanTopBranchDisplay,
            ],
        ];
    }

    private function emptyDashboard(bool $includeDigitalPerformance = true): array
    {
        $scopeLabel = $this->dashboardScopeLabel();

        return [
            'period' => null,
            'previous_period' => null,
            'yoy_period' => null,
            'hero' => [
                'title' => 'A-SIX',
                'kicker' => 'DASHBOARD ' . strtoupper($scopeLabel),
                'subtitle' => 'Data simpanan belum tersedia untuk ditampilkan.',
                'badge' => 'A-SIX OVERVIEW',
                'updated_label' => 'Belum ada data',
                'stats' => [
                    ['label' => 'Total Dana (Simpanan)', 'value' => 'Rp0', 'posisi' => '-', 'icon' => 'fas fa-piggy-bank'],
                    ['label' => 'Total OS (Pinjaman)', 'value' => 'Rp0', 'posisi' => '-', 'icon' => 'fas fa-hand-holding-usd']
                ],
            ],
            'health' => [
                'title' => 'Menunggu Data',
                'badge' => 'Pending',
                'badge_class' => 'badge-secondary',
                'progress' => 0,
                'items' => [
                    ['label' => 'Tabungan', 'value' => '0,0%'],
                    ['label' => 'Giro', 'value' => '0,0%'],
                    ['label' => 'Tipe Terpetakan', 'value' => '0,0%'],
                ],
            ],
            'metrics' => [
                ['label' => 'Total Simpanan', 'value' => 'Rp0', 'delta' => '0 rekening aktif', 'delta_class' => 'text-muted', 'icon' => 'fas fa-building', 'icon_class' => 'text-primary', 'icon_bg' => 'rgba(13, 110, 253, 0.12)'],
                ['label' => 'Total Pinjaman', 'value' => 'Rp0', 'delta' => '0 rekening aktif', 'delta_class' => 'text-muted', 'icon' => 'fas fa-chart-line', 'icon_class' => 'text-info', 'icon_bg' => 'rgba(23, 162, 184, 0.13)'],
                ['label' => 'Growth Simpanan MtM', 'value' => '0,0%', 'delta' => 'vs periode sebelumnya', 'delta_class' => 'text-muted', 'icon' => 'fas fa-wallet', 'icon_class' => 'text-warning', 'icon_bg' => 'rgba(255, 193, 7, 0.16)'],
                ['label' => 'Growth Pinjaman MtM', 'value' => '0,0%', 'delta' => 'vs periode sebelumnya', 'delta_class' => 'text-muted', 'icon' => 'fas fa-database', 'icon_class' => 'text-success', 'icon_bg' => 'rgba(40, 167, 69, 0.14)'],
            ],
            'performance' => [
                'title' => 'Performa Simpanan',
                'subtitle' => 'Ringkasan akan muncul setelah data tersedia.',
                'updated_at' => null,
                'bars' => [
                    ['label' => 'Tabungan', 'value' => 0, 'display' => '0,0%', 'class' => 'bg-primary'],
                    ['label' => 'Giro', 'value' => 0, 'display' => '0,0%', 'class' => 'bg-success'],
                    ['label' => 'Produk Lain / Belum Terpetakan', 'value' => 0, 'display' => '0,0%', 'class' => 'bg-info'],
                    ['label' => 'Kontribusi Top 5 Cabang', 'value' => 0, 'display' => '0,0%', 'class' => 'bg-warning'],
                ],
            ],
            'priorities' => [
                ['badge' => '01', 'badge_class' => 'badge-primary', 'title' => 'Import Data Simpanan', 'text' => 'Upload data simpanan terbaru agar dashboard dapat menampilkan ringkasan aktual.'],
                ['badge' => '02', 'badge_class' => 'badge-warning', 'title' => 'Periksa Periode Posisi', 'text' => 'Pastikan kolom posisi pada `simpanan_multipn` berisi tanggal snapshot yang valid.'],
                ['badge' => '03', 'badge_class' => 'badge-success', 'title' => 'Cek Mapping Jenis Simpanan', 'text' => 'Jenis simpanan yang rapi akan membuat komposisi dashboard lebih akurat.'],
            ],
            'activities' => [
                ['class' => 'badge-secondary', 'title' => 'Belum ada data simpanan yang bisa diringkas', 'time' => 'Menunggu import'],
            ],
            'agenda' => [
                ['title' => 'Import Simpanan', 'time' => 'Belum ada data', 'tag' => 'Data'],
            ],
            'top_branches' => [],
            'loan_top_branches' => [],
            'digital_performance' => $includeDigitalPerformance ? $this->buildDigitalPerformance() : [
                'title' => 'Performance Digital Area 6',
                'subtitle' => 'Ringkasan digital belum dimuat.',
                'updated_at' => null,
                'cards' => [],
            ],
            'area6_portfolio' => $this->emptyArea6PortfolioLanding(),
            'landing_summary' => $this->emptyLandingExecutiveSummary(),
            'live_reports' => [
                ['key' => 'simpanan', 'title' => 'Simpanan Realtime', 'eyebrow' => 'Snapshot aktif', 'value' => 'Rp0', 'trend' => '0,0%', 'trend_class' => 'text-muted', 'meta' => '0 rekening | 0 CIF', 'detail' => 'Top cabang belum tersedia', 'updated' => 'Belum ada data', 'badge' => 'Simpanan', 'badge_class' => 'badge-primary', 'icon' => 'fas fa-piggy-bank', 'icon_bg' => 'rgba(13, 110, 253, 0.12)', 'tone' => 'primary', 'link' => route('dashboard'), 'link_label' => 'Buka report simpanan', 'detail_payload' => $this->buildLandingSourceDetail('Simpanan Realtime', null, 'simpanan_multipn', [['label' => 'Status', 'value' => 'Belum ada data', 'source' => 'simpanan_multipn']], 'Tabel sumber belum memiliki posisi yang bisa ditampilkan.')],
                ['key' => 'pinjaman', 'title' => 'Pinjaman Realtime', 'eyebrow' => 'Outstanding aktif', 'value' => 'Rp0', 'trend' => '0,0%', 'trend_class' => 'text-muted', 'meta' => '0 rekening | 0 cabang', 'detail' => 'Top cabang belum tersedia', 'updated' => 'Belum ada data', 'badge' => 'Pinjaman', 'badge_class' => 'badge-info', 'icon' => 'fas fa-hand-holding-usd', 'icon_bg' => 'rgba(23, 162, 184, 0.12)', 'tone' => 'info', 'link' => route('report.dashboard-pinjaman'), 'link_label' => 'Buka report pinjaman', 'detail_payload' => $this->buildLandingSourceDetail('Pinjaman Realtime', null, 'daily_loan_dinamis', [['label' => 'Status', 'value' => 'Belum ada data', 'source' => 'daily_loan_dinamis']], 'Tabel sumber belum memiliki periode yang bisa ditampilkan.')],
                ['key' => 'portfolio', 'title' => 'LDR (Loan to Deposit Ratio)', 'eyebrow' => 'Cross report', 'value' => '0,00x', 'trend' => '0,0%', 'trend_class' => 'text-muted', 'meta' => 'Gap pinjaman vs simpanan Rp0', 'detail' => 'LDR periode saat ini 0,00x vs 0,00x', 'updated' => 'Belum ada data', 'badge' => 'LDR', 'badge_class' => 'badge-success', 'icon' => 'fas fa-layer-group', 'icon_bg' => 'rgba(40, 167, 69, 0.12)', 'tone' => 'success', 'link' => route('dashboard.harian'), 'link_label' => 'Lihat portfolio harian', 'detail_payload' => $this->buildLandingSourceDetail('LDR (Loan to Deposit Ratio)', null, 'daily_loan_dinamis + simpanan_multipn', [['label' => 'Status', 'value' => 'Belum ada data', 'source' => 'Sumber pinjaman dan simpanan']], 'LDR kosong karena salah satu sumber belum tersedia.')],
            ],
        ];
    }

    private function emptySummary(): array
    {
        return [
            'total_balance' => 0,
            'account_count' => 0,
            'cif_count' => 0,
            'branch_count' => 0,
            'unit_count' => 0,
            'tabungan_balance' => 0,
            'giro_balance' => 0,
            'other_balance' => 0,
            'avg_balance_per_cif' => 0,
            'source_updated_at' => null,
            'source_table' => 'simpanan_multipn',
            'branch_source_table' => 'simpanan_multipn',
            'source_note' => 'Belum ada data simpanan untuk periode ini.',
        ];
    }

    private function emptyLoanSummary(): array
    {
        return [
            'total_balance' => 0,
            'account_count' => 0,
            'branch_count' => 0,
            'unit_count' => 0,
            'source_updated_at' => null,
            'source_table' => 'daily_loan_dinamis',
            'branch_source_table' => 'daily_loan_dinamis',
            'source_note' => 'Belum ada data pinjaman untuk periode ini.',
        ];
    }

    private function resolveLoanDashboardPeriods(?string $selectedPeriod = null): array
    {
        if ($selectedPeriod) {
            $currentPeriod = Carbon::parse($selectedPeriod)->toDateString();
            $previousCandidate = Carbon::parse($currentPeriod)->subMonthNoOverflow()->endOfMonth()->toDateString();
            $yoyCandidate = Carbon::parse($currentPeriod)->subYearNoOverflow()->endOfMonth()->toDateString();

            $previousPeriod = $this->resolveHarianSnapshotPeriodOnOrBefore($previousCandidate) ?: $previousCandidate;
            $yoyPeriod = $this->resolveHarianSnapshotPeriodOnOrBefore($yoyCandidate) ?: $yoyCandidate;

            return [$currentPeriod, $previousPeriod, $yoyPeriod];
        }

        $cacheKey = 'dashboard_pinjaman:periods:' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $this->reportCacheVersion();

        return Cache::remember($cacheKey, now()->addMinutes(10), function () {
            $harianPeriods = $this->resolveHarianDashboardPeriods();
            if ($harianPeriods[0] !== null) {
                return $harianPeriods;
            }

            if (!Schema::hasTable('daily_loan_dinamis')) {
                return [null, null, null];
            }

            $periodQuery = DB::table('daily_loan_dinamis');
            $this->applyDashboardBranchScope($periodQuery, 'cabang1');
            $latestPeriod = (clone $periodQuery)->max('periode');
            if (!$latestPeriod) {
                return [null, null, null];
            }

            $currentPeriod = Carbon::parse($latestPeriod)->toDateString();
            $previousCandidate = Carbon::parse($currentPeriod)->subMonthNoOverflow()->endOfMonth()->toDateString();
            $yoyCandidate = Carbon::parse($currentPeriod)->subYearNoOverflow()->endOfMonth()->toDateString();

            $previousPeriod = (clone $periodQuery)
                ->where('periode', '<=', $previousCandidate)
                ->max('periode');

            $yoyPeriod = (clone $periodQuery)
                ->where('periode', '<=', $yoyCandidate)
                ->max('periode');

            return [$currentPeriod, $previousPeriod, $yoyPeriod];
        });
    }

    private function buildLoanSummary(string $period): array
    {
        $harianSummary = $this->queryLoanSummaryFromHarianSnapshot($period);
        if ($harianSummary !== null) {
            return $harianSummary;
        }

        $snapshotSummary = $this->queryLoanSummaryFromSnapshot($period);
        if ($snapshotSummary !== null) {
            return $snapshotSummary;
        }

        if (!Schema::hasTable('daily_loan_dinamis')) {
            return $this->emptyLoanSummary();
        }

        foreach (['periode', 'baki_debet1', 'cabang1', 'unit1'] as $column) {
            if (!Schema::hasColumn('daily_loan_dinamis', $column)) {
                return $this->emptyLoanSummary();
            }
        }

        $accountCountExpression = Schema::hasColumn('daily_loan_dinamis', 'nomor_rekening1')
            ? 'COUNT(DISTINCT nomor_rekening1)'
            : 'COUNT(*)';
        $sourceUpdatedExpression = Schema::hasColumn('daily_loan_dinamis', 'updated_at')
            ? 'MAX(updated_at)'
            : 'NULL';

        $summary = DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->whereIn(DB::raw('UPPER(TRIM(cabang1))'), $this->dashboardBranchNames())
            ->selectRaw('COALESCE(SUM(COALESCE(baki_debet1, 0)), 0) as total_balance')
            ->selectRaw($accountCountExpression . ' as account_count')
            ->selectRaw('COUNT(DISTINCT cabang1) as branch_count')
            ->selectRaw('COUNT(DISTINCT unit1) as unit_count')
            ->selectRaw($sourceUpdatedExpression . ' as source_updated_at')
            ->first();

        return [
            'total_balance' => (float) ($summary->total_balance ?? 0),
            'account_count' => (int) ($summary->account_count ?? 0),
            'branch_count' => (int) ($summary->branch_count ?? 0),
            'unit_count' => (int) ($summary->unit_count ?? 0),
            'source_updated_at' => $summary->source_updated_at ?? null,
            'source_table' => 'daily_loan_dinamis',
            'branch_source_table' => 'daily_loan_dinamis',
            'source_note' => 'Agregasi langsung dari daily_loan_dinamis untuk periode yang sama.',
        ];
    }

    private function queryLoanSummaryFromSnapshot(string $period): ?array
    {
        if (!Schema::hasTable(self::LOAN_SNAPSHOT_TABLE)) {
            return null;
        }

        foreach (['periode', 'loan_balance', 'account_number', 'cabang1', 'unit1'] as $column) {
            if (!Schema::hasColumn(self::LOAN_SNAPSHOT_TABLE, $column)) {
                return null;
            }
        }

        $row = DB::table(self::LOAN_SNAPSHOT_TABLE)
            ->where('periode', $period)
            ->whereIn(DB::raw('UPPER(TRIM(cabang1))'), $this->dashboardBranchNames())
            ->selectRaw('COALESCE(SUM(COALESCE(loan_balance, 0)), 0) as total_balance')
            ->selectRaw('COUNT(DISTINCT account_number) as account_count')
            ->selectRaw('COUNT(DISTINCT cabang1) as branch_count')
            ->selectRaw('COUNT(DISTINCT unit1) as unit_count')
            ->selectRaw('MAX(updated_at) as source_updated_at')
            ->first();

        if (!$row) {
            return null;
        }

        return [
            'total_balance' => (float) ($row->total_balance ?? 0),
            'account_count' => (int) ($row->account_count ?? 0),
            'branch_count' => (int) ($row->branch_count ?? 0),
            'unit_count' => (int) ($row->unit_count ?? 0),
            'source_updated_at' => $row->source_updated_at ?? null,
            'source_table' => self::LOAN_SNAPSHOT_TABLE,
            'branch_source_table' => self::LOAN_SNAPSHOT_TABLE,
            'source_note' => 'Agregasi dari snapshot dashboard pinjaman untuk periode yang sama.',
        ];
    }

    private function fetchLoanTopBranches(string $period, bool $forceFresh = false): Collection
    {
        $cacheKey = 'dashboard_pinjaman:top_branches:' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $this->reportCacheVersion() . ':' . $period;

        $builder = function () use ($period) {
            $harianRows = $this->queryLoanTopBranchesFromHarianSnapshot($period);
            if ($harianRows !== null) {
                return $harianRows;
            }

            if (
                Schema::hasTable(self::LOAN_SNAPSHOT_TABLE)
                && Schema::hasColumn(self::LOAN_SNAPSHOT_TABLE, 'periode')
                && Schema::hasColumn(self::LOAN_SNAPSHOT_TABLE, 'cabang1')
                && Schema::hasColumn(self::LOAN_SNAPSHOT_TABLE, 'loan_balance')
            ) {
                return DB::table(self::LOAN_SNAPSHOT_TABLE)
                    ->where('periode', $period)
                    ->whereIn(DB::raw('UPPER(TRIM(cabang1))'), $this->dashboardBranchNames())
                    ->whereNotNull('cabang1')
                    ->where('cabang1', '<>', '')
                    ->selectRaw('cabang1, COALESCE(SUM(COALESCE(loan_balance, 0)), 0) as total_balance')
                    ->groupBy('cabang1')
                    ->orderByDesc('total_balance')
                    ->limit(5)
                    ->get();
            }

            if (
                !Schema::hasTable('daily_loan_dinamis')
                || !Schema::hasColumn('daily_loan_dinamis', 'periode')
                || !Schema::hasColumn('daily_loan_dinamis', 'cabang1')
                || !Schema::hasColumn('daily_loan_dinamis', 'baki_debet1')
            ) {
                return collect();
            }

            return DB::table('daily_loan_dinamis')
                ->where('periode', $period)
                ->whereIn(DB::raw('UPPER(TRIM(cabang1))'), $this->dashboardBranchNames())
                ->whereNotNull('cabang1')
                ->where('cabang1', '<>', '')
                ->selectRaw('cabang1, COALESCE(SUM(COALESCE(baki_debet1, 0)), 0) as total_balance')
                ->groupBy('cabang1')
                ->orderByDesc('total_balance')
                ->limit(5)
                ->get();
        };

        if ($forceFresh) {
            $rows = $builder();
            Cache::put($cacheKey, $rows, now()->addMinutes(self::TOP_BRANCH_CACHE_MINUTES));
        } else {
            $rows = Cache::remember($cacheKey, now()->addMinutes(self::TOP_BRANCH_CACHE_MINUTES), $builder);
        }

        return collect($rows)->map(function ($row) {
            $balance = (float) ($row->total_balance ?? 0);

            return [
                'label' => $this->simplifyBranchLabel((string) ($row->cabang1 ?? '-')),
                'full_label' => (string) ($row->cabang1 ?? '-'),
                'balance' => $balance,
                'display' => $this->formatCurrencyCompact($balance),
            ];
        });
    }

    private function queryPeriodSummaryFromSnapshot(string $period): ?array
    {
        if (UserBranchScope::current() !== null) {
            return null;
        }

        if (!$this->hasSimpananSnapshot($period)) {
            return null;
        }

        $row = DB::table(self::SNAPSHOT_SUMMARY_TABLE)
            ->where('snapshot_period', $period)
            ->first();

        if (!$row) {
            return null;
        }

        $totalBalance = (float) ($row->total_balance ?? 0);
        $cifCount = (int) ($row->cif_count ?? 0);
        $tabunganBalance = (float) ($row->tabungan_balance ?? 0);
        $giroBalance = (float) ($row->giro_balance ?? 0);
        $otherBalance = (float) ($row->other_balance ?? max(0, $totalBalance - $tabunganBalance - $giroBalance));

        return [
            'total_balance' => $totalBalance,
            'account_count' => (int) ($row->account_count ?? 0),
            'cif_count' => $cifCount,
            'branch_count' => (int) ($row->branch_count ?? 0),
            'unit_count' => (int) ($row->unit_count ?? 0),
            'tabungan_balance' => $tabunganBalance,
            'giro_balance' => $giroBalance,
            'other_balance' => $otherBalance,
            'avg_balance_per_cif' => $cifCount > 0 ? $totalBalance / $cifCount : 0,
            'source_updated_at' => $row->source_updated_at ?? null,
            'source_table' => self::SNAPSHOT_SUMMARY_TABLE,
            'branch_source_table' => self::SNAPSHOT_BRANCH_TABLE,
            'source_note' => 'Agregasi dari snapshot dashboard simpanan untuk posisi yang sama.',
            'snapshot_completeness' => (string) ($row->snapshot_completeness ?? 'complete'),
            'partial_branches' => $this->decodePartialBranches($row->partial_branches ?? null),
        ];
    }

    private function decodePartialBranches(mixed $value): array
    {
        if ($value === null || trim((string) $value) === '') {
            return [];
        }

        $decoded = json_decode((string) $value, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn ($branch): bool => is_string($branch) && trim($branch) !== ''));
    }

    private function queryTopBranchesFromSnapshot(string $period): ?Collection
    {
        if (!$this->hasSimpananSnapshot($period)) {
            return null;
        }

        if (!Schema::hasTable(self::SNAPSHOT_BRANCH_TABLE)) {
            return null;
        }

        $rows = DB::table(self::SNAPSHOT_BRANCH_TABLE)
            ->where('snapshot_period', $period)
            ->whereIn(DB::raw('UPPER(TRIM(kantor_cabang))'), $this->dashboardBranchNames())
            ->orderBy('rank_order')
            ->limit(5)
            ->get();

        return $rows->isNotEmpty() ? $rows : collect();
    }

    private function hasSimpananSnapshot(string $period): bool
    {
        if (!Schema::hasTable(self::SNAPSHOT_SUMMARY_TABLE) || !Schema::hasTable('simpanan_multipn')) {
            return false;
        }

        if (array_key_exists($period, $this->snapshotExistsMemo)) {
            return $this->snapshotExistsMemo[$period];
        }

        $cacheKey = 'dashboard_simpanan:snapshot_exists:v' . $this->reportCacheVersion() . ':' . $period;
        $knownExists = Cache::get($cacheKey);
        if ($knownExists === true) {
            $this->snapshotExistsMemo[$period] = true;

            return true;
        }

        $exists = DB::table(self::SNAPSHOT_SUMMARY_TABLE)
            ->where('snapshot_period', $period)
            ->exists();

        if ($exists) {
            Cache::put($cacheKey, true, now()->addMinutes(10));
            $this->snapshotExistsMemo[$period] = true;

            return true;
        }

        $hasSourceRows = DB::table('simpanan_multipn')
            ->where('posisi', $period)
            ->exists();

        if (!$hasSourceRows) {
            Cache::put($cacheKey, false, now()->addSeconds(30));
            $this->snapshotExistsMemo[$period] = false;

            return false;
        }

        if (!$this->isSimpananMultiPnSnapshotReady($period)) {
            $missingBranches = app(SimpananMultiPnSnapshotGate::class)->getMissingBranches($period);

            Log::info('Dashboard simpanan snapshot ditunda karena Area 6 belum lengkap.', [
                'period' => $period,
                'missing_branches' => $missingBranches,
            ]);

            Cache::put($cacheKey, false, now()->addSeconds(30));
            $this->snapshotExistsMemo[$period] = false;

            return false;
        }

        $lock = Cache::lock('snapshot:dashboard_simpanan:auto-rebuild:' . $period, 60);
        $pendingKey = 'snapshot:dashboard_simpanan:auto-rebuild:pending:' . $period;
        $jobDispatched = false;
        $built = false;

        try {
            if ($lock->get()) {
                try {
                    $builder = app(\App\Support\ReportSnapshotBuilder::class);
                    $builder->rebuildDashboardSimpanan($period, false);
                    $builder->rebuildRekeningDormant($period, false);
                    $builder->rebuildRasioCasa($period, false);
                    $builder->rebuildPerformanceRm($period, false);

                    app(\App\Support\DashboardHarianSnapshotService::class)->rebuild($period, false);

                    $built = DB::table(self::SNAPSHOT_SUMMARY_TABLE)
                        ->where('snapshot_period', $period)
                        ->exists();
                } catch (Throwable $builderEx) {
                    Log::warning('Synchronous rebuild dashboard simpanan failed, falling back: ' . $builderEx->getMessage());
                }

                if (Cache::add($pendingKey, now()->toIso8601String(), now()->addMinutes(10))) {
                    EnsureDashboardSimpananSnapshotJob::dispatch($period, static::class . '::hasSimpananSnapshot')
                        ->onQueue((string) config('queue.report_queue', 'default'));
                    $jobDispatched = true;
                }
            }
        } catch (Throwable $e) {
            Log::warning('Auto rebuild dashboard simpanan snapshot gagal: ' . $e->getMessage(), [
                'period' => $period,
            ]);
        } finally {
            optional($lock)->release();
        }

        if ($built) {
            Cache::put($cacheKey, true, now()->addMinutes(10));
            $this->snapshotExistsMemo[$period] = true;

            return true;
        }

        Log::info('Dashboard simpanan snapshot unavailable; using source query fallback.', [
            'period' => $period,
            'job_dispatched' => $jobDispatched,
        ]);

        Cache::put($cacheKey, false, now()->addSeconds(30));
        $this->snapshotExistsMemo[$period] = false;

        return false;
    }

    private function isSimpananMultiPnSnapshotReady(string $period): bool
    {
        return app(SimpananMultiPnSnapshotGate::class)->isReady($period);
    }

    private function resolveDashboardPeriods(?string $selectedPeriod = null): array
    {
        if ($selectedPeriod) {
            $currentPeriod = Carbon::parse($selectedPeriod)->toDateString();
            $previousCandidate = Carbon::parse($currentPeriod)->subMonthNoOverflow()->endOfMonth()->toDateString();
            $yoyCandidate = Carbon::parse($currentPeriod)->subYearNoOverflow()->endOfMonth()->toDateString();

            $previousPeriod = $this->resolveHarianSnapshotPeriodOnOrBefore($previousCandidate) ?: $previousCandidate;
            $yoyPeriod = $this->resolveHarianSnapshotPeriodOnOrBefore($yoyCandidate) ?: $yoyCandidate;

            return [$currentPeriod, $previousPeriod, $yoyPeriod];
        }

        $cacheKey = 'dashboard_simpanan:periods:' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $this->reportCacheVersion();

        return Cache::remember($cacheKey, now()->addMinutes(10), function () {
            $harianPeriods = $this->resolveHarianDashboardPeriods();
            if ($harianPeriods[0] !== null) {
                return $harianPeriods;
            }

            if (!Schema::hasTable('simpanan_multipn')) {
                return [null, null, null];
            }

            $periodQuery = DB::table('simpanan_multipn');
            $this->applyDashboardBranchScope($periodQuery, 'kantor_cabang');
            $latestPeriod = (clone $periodQuery)->max('posisi');
            if (!$latestPeriod) {
                return [null, null, null];
            }

            $currentPeriod = Carbon::parse($latestPeriod)->toDateString();
            $previousCandidate = Carbon::parse($currentPeriod)->subMonthNoOverflow()->endOfMonth()->toDateString();
            $yoyCandidate = Carbon::parse($currentPeriod)->subYearNoOverflow()->endOfMonth()->toDateString();

            $previousPeriod = (clone $periodQuery)
                ->where('posisi', '<=', $previousCandidate)
                ->max('posisi');

            $yoyPeriod = (clone $periodQuery)
                ->where('posisi', '<=', $yoyCandidate)
                ->max('posisi');

            return [$currentPeriod, $previousPeriod, $yoyPeriod];
        });
    }

    private function resolveHarianDashboardPeriods(): array
    {
        if (!$this->hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return [null, null, null];
        }

        $baseQuery = $this->area6HarianSnapshotSummaryQuery();
        $latestPeriod = (clone $baseQuery)->max('snapshot_period');
        if (!$latestPeriod) {
            return [null, null, null];
        }

        $currentPeriod = Carbon::parse($latestPeriod)->toDateString();
        $previousCandidate = Carbon::parse($currentPeriod)->subMonthNoOverflow()->endOfMonth()->toDateString();
        $yoyCandidate = Carbon::parse($currentPeriod)->subYearNoOverflow()->endOfMonth()->toDateString();

        $previousPeriod = (clone $baseQuery)
            ->where('snapshot_period', '<=', $previousCandidate)
            ->max('snapshot_period');

        $yoyPeriod = (clone $baseQuery)
            ->where('snapshot_period', '<=', $yoyCandidate)
            ->max('snapshot_period');

        return [$currentPeriod, $previousPeriod, $yoyPeriod];
    }

    private function resolveHarianSnapshotPeriodOnOrBefore(string $period): ?string
    {
        if (array_key_exists($period, $this->snapshotPeriodMemo)) {
            return $this->snapshotPeriodMemo[$period];
        }

        if (!$this->hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return $this->snapshotPeriodMemo[$period] = null;
        }

        $actualPeriod = $this->area6HarianSnapshotSummaryQuery()
            ->where('snapshot_period', '<=', $period)
            ->max('snapshot_period');

        return $this->snapshotPeriodMemo[$period] = ($actualPeriod ? Carbon::parse($actualPeriod)->toDateString() : null);
    }

    private function buildDigitalPerformance(): array
    {
        $cacheKey = 'dashboard_simpanan:digital_performance:' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $this->reportCacheVersion();

        return Cache::remember($cacheKey, now()->addMinutes(self::DIGITAL_PERFORMANCE_CACHE_MINUTES), function () {
            $cards = array_values(array_filter([
                $this->buildEdcPerformanceCard(),
                $this->buildQrisPerformanceCard(),
                $this->buildQlolaPerformanceCard(),
                $this->buildBrimoPerformanceCard(),
                $this->buildBrilinkPerformanceCard(),
                $this->buildCasaDebiturKpiCard(),
                $this->buildRekeningDormantKpiCard(),
                $this->buildPayrollPerformanceCard(),
            ]));

            $latestSource = collect($cards)
                ->pluck('source_updated_at')
                ->filter()
                ->map(function ($value) {
                    try {
                        return Carbon::parse($value)->timestamp;
                    } catch (Throwable) {
                        return null;
                    }
                })
                ->filter()
                ->max();

            return [
                'title' => 'Performance Digital Area 6',
                'subtitle' => 'Snapshot realtime untuk 8 strategi: EDC, QRIS, QLola, BRIMO, BRILink, Casa Debitur, Rekening Dormant, dan Payroll.',
                'updated_at' => $latestSource
                    ? Carbon::createFromTimestamp($latestSource)->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d M Y H:i')
                    : null,
                'cards' => $cards,
            ];
        });
    }

    private function buildEdcPerformanceCard(): ?array
    {
        try {
            if (!Schema::hasTable('jumlah_merchant_detail')) {
                return null;
            }

            $latestPeriod = DB::table('jumlah_merchant_detail')->max(DB::raw('DATE(POSISI)'));
            if (!$latestPeriod) {
                return null;
            }

            $periods = $this->buildTrendDatePeriods($latestPeriod);
            $branches = $this->dashboardBranchNames();
            $timeline = [];

            foreach ($periods as $period) {
                $row = DB::table('jumlah_merchant_detail')
                    ->whereDate('POSISI', $period)
                    ->whereIn(DB::raw('UPPER(NAMA_KANCA)'), $branches)
                    ->selectRaw('COUNT(DISTINCT MID) as merchant_count')
                    ->selectRaw('COUNT(DISTINCT CASE WHEN COALESCE(SALES_VOLUME, 0) >= 15000000 THEN MID END) as productive_count')
                    ->selectRaw('COALESCE(SUM(COALESCE(SALES_VOLUME, 0)), 0) as volume')
                    ->first();

                $timeline[] = [
                    'label' => Carbon::parse($period)->translatedFormat('d M'),
                    'merchant_count' => (int) ($row->merchant_count ?? 0),
                    'productive_count' => (int) ($row->productive_count ?? 0),
                    'volume' => (float) ($row->volume ?? 0),
                    'source_updated_at' => $period,
                ];
            }

            $current = $timeline[array_key_last($timeline)] ?? ['merchant_count' => 0, 'productive_count' => 0, 'volume' => 0];
            $previous = $timeline[count($timeline) - 2] ?? $current;

            return $this->buildDigitalCard([
                'key' => 'edc',
                'title' => 'Performance EDC',
                'subtitle' => 'MID aktif, merchant produktif, dan volume penjualan tersaji dalam satu kartu ringkas.',
                'badge' => 'EDC',
                'badge_class' => 'badge-primary',
                'tone' => 'digital-edc',
                'icon' => 'fas fa-credit-card',
                'link' => route('report.edc'),
                'link_label' => 'Buka report EDC',
                'current_value' => $this->formatInteger((int) $current['merchant_count']),
                'current_label' => 'MID Aktif',
                'secondary_value' => $this->formatCurrencyCompact((float) $current['volume']),
                'secondary_label' => 'Sales Volume',
                'trend_reference' => $this->formatInteger((int) $previous['merchant_count']) . ' MID sebelumnya',
                'trend_direction' => $this->percentChange((float) $current['merchant_count'], (float) $previous['merchant_count']),
                'series' => array_column($timeline, 'merchant_count'),
                'series_labels' => array_column($timeline, 'label'),
                'stats' => [
                    [
                        'label' => 'Merchant Produktif',
                        'value' => $this->formatInteger((int) $current['productive_count']),
                    ],
                    [
                        'label' => 'Volume Total',
                        'value' => $this->formatCurrencyCompact((float) $current['volume']),
                    ],
                    [
                        'label' => 'Periode',
                        'value' => Carbon::parse($latestPeriod)->translatedFormat('d M Y'),
                    ],
                ],
                'source_updated_at' => $latestPeriod,
            ]);
        } catch (Throwable $e) {
            Log::warning('Dashboard digital EDC gagal disusun: ' . $e->getMessage());

            return null;
        }
    }

    private function buildQrisPerformanceCard(): ?array
    {
        try {
            if (!Schema::hasTable('jumlah_merchant_qris_detail')) {
                return null;
            }

            $latestPeriod = DB::table('jumlah_merchant_qris_detail')->max(DB::raw('DATE(POSISI)'));
            if (!$latestPeriod) {
                return null;
            }

            $periods = $this->buildTrendDatePeriods($latestPeriod);
            $branches = $this->dashboardBranchNames();
            $timeline = [];

            foreach ($periods as $period) {
                $salesVolumeExpression = "COALESCE(CAST(NULLIF(REPLACE(AKUMULASI_SV_TOTAL, ',', ''), '') AS DECIMAL(20,2)), 0)";
                $row = DB::table('jumlah_merchant_qris_detail')
                    ->whereDate('POSISI', $period)
                    ->whereIn(DB::raw('UPPER(TRIM(MBDESC))'), $branches)
                    ->selectRaw('COUNT(DISTINCT STOREID) as merchant_count')
                    ->selectRaw("COUNT(DISTINCT CASE WHEN {$salesVolumeExpression} >= 50000 THEN STOREID END) as productive_count")
                    ->selectRaw("COALESCE(SUM({$salesVolumeExpression}), 0) as volume")
                    ->first();

                $timeline[] = [
                    'label' => Carbon::parse($period)->translatedFormat('d M'),
                    'merchant_count' => (int) ($row->merchant_count ?? 0),
                    'productive_count' => (int) ($row->productive_count ?? 0),
                    'volume' => (float) ($row->volume ?? 0),
                    'source_updated_at' => $period,
                ];
            }

            $current = $timeline[array_key_last($timeline)] ?? ['merchant_count' => 0, 'productive_count' => 0, 'volume' => 0];
            $previous = $timeline[count($timeline) - 2] ?? $current;

            return $this->buildDigitalCard([
                'key' => 'qris',
                'title' => 'Performance QRIS',
                'subtitle' => 'Sales volume, merchant tercatat, dan merchant produktif dikemas untuk pemantauan cepat.',
                'badge' => 'QRIS',
                'badge_class' => 'badge-info',
                'tone' => 'digital-qris',
                'icon' => 'fas fa-qrcode',
                'link' => route('report.qris'),
                'link_label' => 'Buka report QRIS',
                'current_value' => $this->formatCurrencyCompact((float) $current['volume']),
                'current_label' => 'Sales Volume',
                'secondary_value' => $this->formatInteger((int) $current['merchant_count']),
                'secondary_label' => 'Merchant Tercatat',
                'trend_reference' => $this->formatCurrencyCompact((float) $previous['volume']) . ' periode sebelumnya',
                'trend_direction' => $this->percentChange((float) $current['volume'], (float) $previous['volume']),
                'series' => array_column($timeline, 'volume'),
                'series_labels' => array_column($timeline, 'label'),
                'stats' => [
                    [
                        'label' => 'Merchant Produktif',
                        'value' => $this->formatInteger((int) $current['productive_count']),
                    ],
                    [
                        'label' => 'Volume Akumulasi',
                        'value' => $this->formatCurrencyCompact((float) $current['volume']),
                    ],
                    [
                        'label' => 'Periode',
                        'value' => Carbon::parse($latestPeriod)->translatedFormat('d M Y'),
                    ],
                ],
                'source_updated_at' => $latestPeriod,
            ]);
        } catch (Throwable $e) {
            Log::warning('Dashboard digital QRIS gagal disusun: ' . $e->getMessage());

            return null;
        }
    }

    private function buildBrimoPerformanceCard(): ?array
    {
        try {
            if (!Schema::hasTable('user_brimo_rpt_v2') || !Schema::hasTable('user_brimo_fin')) {
                return null;
            }

            $latestRek = DB::table('user_brimo_rpt_v2')->max('posisi');
            $latestFin = DB::table('user_brimo_fin')->max('posisi');
            $latestCandidates = array_filter([$latestRek, $latestFin]);
            if (empty($latestCandidates)) {
                return null;
            }

            $latestPeriod = Carbon::parse(max($latestCandidates))->toDateString();
            $periods = $this->buildTrendDatePeriods($latestPeriod);
            $branches = $this->dashboardBranchNames();
            $timeline = [];

            foreach ($periods as $period) {
                $rekRow = DB::table('user_brimo_rpt_v2')
                    ->whereDate('posisi', $period)
                    ->whereIn(DB::raw('UPPER(COALESCE(mbdesc, branch))'), $branches)
                    ->selectRaw('COALESCE(SUM(COALESCE(jumlah, 0)), 0) as total')
                    ->selectRaw('COUNT(*) as row_count')
                    ->first();

                $finRow = DB::table('user_brimo_fin')
                    ->whereDate('posisi', $period)
                    ->whereIn(DB::raw('UPPER(COALESCE(mbdesc, branch))'), $branches)
                    ->selectRaw('COALESCE(SUM(COALESCE(jumlah, 0)), 0) as total')
                    ->selectRaw('COUNT(*) as row_count')
                    ->first();

                $timeline[] = [
                    'label' => Carbon::parse($period)->translatedFormat('d M'),
                    'rekening_total' => (float) ($rekRow->total ?? 0),
                    'rekening_rows' => (int) ($rekRow->row_count ?? 0),
                    'fin_total' => (float) ($finRow->total ?? 0),
                    'fin_rows' => (int) ($finRow->row_count ?? 0),
                    'source_updated_at' => $period,
                ];
            }

            $current = $timeline[array_key_last($timeline)] ?? ['rekening_total' => 0, 'rekening_rows' => 0, 'fin_total' => 0, 'fin_rows' => 0];
            $previous = $timeline[count($timeline) - 2] ?? $current;
            $currentTotal = (float) $current['rekening_total'] + (float) $current['fin_total'];
            $previousTotal = (float) $previous['rekening_total'] + (float) $previous['fin_total'];

            return $this->buildDigitalCard([
                'key' => 'brimo',
                'title' => 'Performance BRIMO',
                'subtitle' => 'Gabungan Ureg Rekening dan Finansial untuk memantau aktivitas BRIMO Area 6.',
                'badge' => 'BRIMO',
                'badge_class' => 'badge-primary',
                'tone' => 'digital-brimo',
                'icon' => 'fas fa-mobile-alt',
                'link' => route('report.brimo'),
                'link_label' => 'Buka report BRIMO',
                'current_value' => $this->formatCurrencyCompact($currentTotal),
                'current_label' => 'Total Ureg',
                'secondary_value' => $this->formatInteger((int) $current['rekening_rows'] + (int) $current['fin_rows']),
                'secondary_label' => 'Baris Tersedia',
                'trend_reference' => $this->formatCurrencyCompact($previousTotal) . ' periode sebelumnya',
                'trend_direction' => $this->percentChange($currentTotal, $previousTotal),
                'series' => array_map(
                    fn ($item) => (float) ($item['rekening_total'] ?? 0) + (float) ($item['fin_total'] ?? 0),
                    $timeline
                ),
                'series_labels' => array_column($timeline, 'label'),
                'stats' => [
                    [
                        'label' => 'Ureg Rekening',
                        'value' => $this->formatCurrencyCompact((float) $current['rekening_total']),
                    ],
                    [
                        'label' => 'Ureg Finansial',
                        'value' => $this->formatCurrencyCompact((float) $current['fin_total']),
                    ],
                    [
                        'label' => 'Periode',
                        'value' => Carbon::parse($latestPeriod)->translatedFormat('d M Y'),
                    ],
                ],
                'source_updated_at' => $latestPeriod,
            ]);
        } catch (Throwable $e) {
            Log::warning('Dashboard digital BRIMO gagal disusun: ' . $e->getMessage());

            return null;
        }
    }

    private function buildBrilinkPerformanceCard(): ?array
    {
        try {
            if (!Schema::hasTable('brilink_web_laporan_summary_transaksi_brilink_web')) {
                return null;
            }

            $latestPeriod = DB::table('brilink_web_laporan_summary_transaksi_brilink_web')->max('periode');
            if (!$latestPeriod) {
                return null;
            }

            $periods = $this->buildTrendMonthPeriods($latestPeriod);
            $branches = $this->dashboardBranchNames();
            $timeline = [];

            foreach ($periods as $period) {
                $row = DB::table('brilink_web_laporan_summary_transaksi_brilink_web')
                    ->where('periode', $period)
                    ->whereIn(DB::raw('UPPER(TRIM(cabang))'), $branches)
                    ->selectRaw('COUNT(*) as agen')
                    ->selectRaw('SUM(CASE WHEN COALESCE(total_fee, 0) >= 750000 THEN 1 ELSE 0 END) as juragan')
                    ->selectRaw('SUM(CASE WHEN COALESCE(total_fee, 0) >= 150000 THEN 1 ELSE 0 END) as bep')
                    ->selectRaw('COALESCE(SUM(COALESCE(total_transaksi, 0)), 0) as trx')
                    ->selectRaw('COALESCE(SUM(COALESCE(total_nominal, 0)), 0) as volume')
                    ->first();

                $timeline[] = [
                    'label' => Carbon::parse($period)->translatedFormat('M Y'),
                    'agen' => (int) ($row->agen ?? 0),
                    'juragan' => (int) ($row->juragan ?? 0),
                    'bep' => (int) ($row->bep ?? 0),
                    'trx' => (float) ($row->trx ?? 0),
                    'volume' => (float) ($row->volume ?? 0),
                    'source_updated_at' => $period,
                ];
            }

            $current = $timeline[array_key_last($timeline)] ?? ['agen' => 0, 'juragan' => 0, 'bep' => 0, 'trx' => 0, 'volume' => 0];
            $previous = $timeline[count($timeline) - 2] ?? $current;

            return $this->buildDigitalCard([
                'key' => 'brilink',
                'title' => 'Performance BRILink',
                'subtitle' => 'Agen, transaksi, dan volume akumulasi disusun untuk melihat produktivitas jaringan.',
                'badge' => 'BRILink',
                'badge_class' => 'badge-success',
                'tone' => 'digital-brilink',
                'icon' => 'fas fa-network-wired',
                'link' => route('report.brilink'),
                'link_label' => 'Buka report BRILink',
                'current_value' => $this->formatCurrencyCompact((float) $current['volume']),
                'current_label' => 'Volume Aktif',
                'secondary_value' => $this->formatInteger((int) $current['agen']),
                'secondary_label' => 'Agen Tercatat',
                'trend_reference' => $this->formatCurrencyCompact((float) $previous['volume']) . ' periode sebelumnya',
                'trend_direction' => $this->percentChange((float) $current['volume'], (float) $previous['volume']),
                'series' => array_column($timeline, 'volume'),
                'series_labels' => array_column($timeline, 'label'),
                'stats' => [
                    [
                        'label' => 'Agen Juragan',
                        'value' => $this->formatInteger((int) $current['juragan']),
                    ],
                    [
                        'label' => 'Volume Trx',
                        'value' => $this->formatCurrencyCompact((float) $current['volume']),
                    ],
                    [
                        'label' => 'Transaksi',
                        'value' => $this->formatInteger((int) $current['trx']),
                    ],
                ],
                'source_updated_at' => $latestPeriod,
            ]);
        } catch (Throwable $e) {
            Log::warning('Dashboard digital BRILink gagal disusun: ' . $e->getMessage());

            return null;
        }
    }

    private function buildPayrollPerformanceCard(): ?array
    {
        try {
            $snapshotCard = $this->buildPayrollPerformanceCardFromSnapshot();
            if ($snapshotCard !== null) {
                return $snapshotCard;
            }

            if (!Schema::hasTable('performance_pis_per_produk')) {
                return null;
            }

            $latestPeriod = DB::table('performance_pis_per_produk')->max('posisi');
            if (!$latestPeriod) {
                return null;
            }

            $periods = $this->buildTrendDatePeriods($latestPeriod);
            $branches = $this->dashboardBranchNames();
            $timeline = [];

            foreach ($periods as $period) {
                $monthStart = Carbon::parse($period)->startOfMonth()->toDateString();
                $monthEnd = Carbon::parse($period)->endOfMonth()->toDateString();

                $row = DB::table('performance_pis_per_produk')
                    ->whereDate('posisi', $period)
                    ->whereIn(DB::raw('UPPER(TRIM(kanca))'), $branches)
                    ->selectRaw('COUNT(*) as rekening_count')
                    ->selectRaw('COALESCE(SUM(COALESCE(saldo_britama_kerjasama, 0)), 0) as saldo')
                    ->whereBetween('tanggal_pembuatan_rekening', [$monthStart, $monthEnd])
                    ->first();

                $timeline[] = [
                    'label' => Carbon::parse($period)->translatedFormat('d M'),
                    'rekening_count' => (int) ($row->rekening_count ?? 0),
                    'saldo' => (float) ($row->saldo ?? 0),
                    'source_updated_at' => $period,
                ];
            }

            $current = $timeline[array_key_last($timeline)] ?? ['rekening_count' => 0, 'saldo' => 0];
            $previous = $timeline[count($timeline) - 2] ?? $current;

            return $this->buildDigitalCard([
                'key' => 'payroll',
                'title' => 'Performance Lainnya',
                'subtitle' => 'Performance PIS per produk untuk melihat kontribusi payroll dan saldo kerjasama.',
                'badge' => 'PIS',
                'badge_class' => 'badge-warning',
                'tone' => 'digital-payroll',
                'icon' => 'fas fa-briefcase',
                'link' => route('report.kinerja.newpayroll'),
                'link_label' => 'Buka report payroll',
                'current_value' => $this->formatInteger((int) $current['rekening_count']),
                'current_label' => 'Rekening Aktif',
                'secondary_value' => $this->formatCurrencyCompact((float) $current['saldo']),
                'secondary_label' => 'Saldo Kerjasama',
                'trend_reference' => $this->formatInteger((int) $previous['rekening_count']) . ' rekening sebelumnya',
                'trend_direction' => $this->percentChange((float) $current['rekening_count'], (float) $previous['rekening_count']),
                'series' => array_column($timeline, 'rekening_count'),
                'series_labels' => array_column($timeline, 'label'),
                'stats' => [
                    [
                        'label' => 'Rekening',
                        'value' => $this->formatInteger((int) $current['rekening_count']),
                    ],
                    [
                        'label' => 'Saldo',
                        'value' => $this->formatCurrencyCompact((float) $current['saldo']),
                    ],
                    [
                        'label' => 'Periode',
                        'value' => Carbon::parse($latestPeriod)->translatedFormat('d M Y'),
                    ],
                ],
                'source_updated_at' => $latestPeriod,
            ]);
        } catch (Throwable $e) {
            Log::warning('Dashboard digital payroll gagal disusun: ' . $e->getMessage());

            return null;
        }
    }

    private function buildPayrollPerformanceCardFromSnapshot(): ?array
    {
        if (!Schema::hasTable('performance_new_payroll_snapshots')) {
            return null;
        }

        $latestPeriod = DB::table('performance_new_payroll_snapshots')->max('snapshot_posisi');
        if (!$latestPeriod) {
            return null;
        }

        $branches = $this->dashboardBranchNames();
        $row = DB::table('performance_new_payroll_snapshots')
            ->whereDate('snapshot_posisi', $latestPeriod)
            ->whereIn(DB::raw('UPPER(TRIM(branch))'), $branches)
            ->selectRaw('COALESCE(SUM(COALESCE(rekening_curr, 0)), 0) as rekening_curr')
            ->selectRaw('COALESCE(SUM(COALESCE(rekening_prev, 0)), 0) as rekening_prev')
            ->selectRaw('COALESCE(SUM(COALESCE(saldo_curr, 0)), 0) as saldo_curr')
            ->selectRaw('COALESCE(SUM(COALESCE(saldo_prev, 0)), 0) as saldo_prev')
            ->first();

        $rekeningCurrent = (int) ($row->rekening_curr ?? 0);
        $rekeningPrevious = (int) ($row->rekening_prev ?? 0);
        $saldoCurrent = (float) ($row->saldo_curr ?? 0);
        $saldoPrevious = (float) ($row->saldo_prev ?? 0);

        return $this->buildDigitalCard([
            'key' => 'payroll',
            'title' => 'Kinerja New Payroll',
            'subtitle' => 'Snapshot rekening dan saldo payroll Area 6.',
            'badge' => 'PIS',
            'badge_class' => 'badge-warning',
            'tone' => 'digital-payroll',
            'icon' => 'fas fa-briefcase',
            'link' => route('report.kinerja.newpayroll'),
            'link_label' => 'Buka report payroll',
            'current_value' => $this->formatInteger($rekeningCurrent),
            'current_label' => 'Rekening Aktif',
            'secondary_value' => $this->formatCurrencyCompact($saldoCurrent),
            'secondary_label' => 'Saldo Kerjasama',
            'trend_reference' => $this->formatInteger($rekeningPrevious) . ' rekening sebelumnya',
            'trend_direction' => $this->percentChange($rekeningCurrent, $rekeningPrevious),
            'series' => [$rekeningPrevious, $rekeningCurrent],
            'series_labels' => ['Sebelumnya', Carbon::parse($latestPeriod)->translatedFormat('d M')],
            'stats' => [
                ['label' => 'Rekening', 'value' => $this->formatInteger($rekeningCurrent)],
                ['label' => 'Saldo', 'value' => $this->formatCurrencyCompact($saldoCurrent)],
                ['label' => 'Periode', 'value' => Carbon::parse($latestPeriod)->translatedFormat('d M Y')],
            ],
            'source_updated_at' => $latestPeriod,
            'source_table' => 'performance_new_payroll_snapshots',
            'source_note' => 'Kartu payroll landing memakai snapshot ringkas, bukan agregasi ulang tabel detail.',
        ]);
    }

    private function buildQlolaPerformanceCard(): ?array
    {
        try {
            if (!$this->hasTable('usak_ibbiz_uker') && !$this->hasTable('ibbisniz_corp')) {
                // Return stub card jika tabel tidak ada
                return [
                    'key' => 'qlola',
                    'title' => 'Performance QLola',
                    'subtitle' => 'Platform cash management QLola untuk monitoring likuiditas nasabah korporasi.',
                    'badge' => 'QLOLA',
                    'badge_class' => 'badge-warning',
                    'tone' => 'digital-qlola',
                    'icon' => 'fas fa-university',
                    'link' => route('report.qlola'),
                    'link_label' => 'Buka report QLola',
                    'current_value' => '-',
                    'current_label' => 'Nasabah Aktif',
                    'secondary_value' => '-',
                    'secondary_label' => 'Volume',
                    'trend' => '0,0%',
                    'trend_class' => 'text-muted',
                    'trend_value' => 0,
                    'trend_reference' => 'Data belum tersedia',
                    'chart' => ['points' => [], 'path' => '', 'area_path' => ''],
                    'series' => [],
                    'series_labels' => [],
                    'stats' => [
                        ['label' => 'Status', 'value' => 'Menunggu Data'],
                        ['label' => 'Periode', 'value' => '-'],
                        ['label' => 'Link', 'value' => 'Lihat Detail'],
                    ],
                    'source_updated_at' => null,
                    'is_stub' => true,
                    'detail_payload' => $this->buildLandingSourceDetail('Performance QLola', null, 'usak_ibbiz_uker / ibbisniz_corp', [['label' => 'Status', 'value' => 'Tabel sumber belum tersedia', 'source' => 'Schema check']], 'Landing page tidak membuat angka pengganti saat tabel QLola belum ada.'),
                ];
            }

            $latestUserPeriod = $this->hasTable('usak_ibbiz_uker') ? DB::table('usak_ibbiz_uker')->max('periode') : null;
            $latestCorpPeriod = $this->hasTable('ibbisniz_corp') ? DB::table('ibbisniz_corp')->max('periode') : null;
            $latestPeriod = $latestUserPeriod ?? $latestCorpPeriod;

            if (!$latestPeriod) {
                return null;
            }

            $periods = $this->buildTrendDatePeriods($latestPeriod);
            $branches = $this->dashboardBranchNames();
            $timeline = [];

            $usakBranchExpression = "UPPER(TRIM(CASE WHEN LOCATE(' - ', kanca) > 0 THEN SUBSTRING(kanca, LOCATE(' - ', kanca) + 3) ELSE kanca END))";
            $corpBranchExpression = "UPPER(TRIM(CASE WHEN LOCATE(' - ', cabang) > 0 THEN SUBSTRING(cabang, LOCATE(' - ', cabang) + 3) ELSE cabang END))";

            foreach ($periods as $period) {
                $userCount = 0;
                if ($this->hasTable('usak_ibbiz_uker')) {
                    $userCount = DB::table('usak_ibbiz_uker')
                        ->whereDate('periode', $period)
                        ->whereIn(DB::raw($usakBranchExpression), $branches)
                        ->whereIn(DB::raw('UPPER(TRIM(deskripsi))'), ['ACTIVE', 'ACTIVATED'])
                        ->count();
                }

                $volume = 0.0;
                if ($this->hasTable('ibbisniz_corp')) {
                    $volume = (float) DB::table('ibbisniz_corp')
                        ->whereDate('periode', $period)
                        ->whereIn(DB::raw($corpBranchExpression), $branches)
                        ->sum('nominal');
                }

                $timeline[] = [
                    'label' => Carbon::parse($period)->translatedFormat('d M'),
                    'nasabah_count' => $userCount,
                    'volume' => $volume,
                    'source_updated_at' => $period,
                ];
            }

            $current = $timeline[array_key_last($timeline)] ?? ['nasabah_count' => 0, 'volume' => 0];
            $previous = $timeline[count($timeline) - 2] ?? $current;

            return $this->buildDigitalCard([
                'key' => 'qlola',
                'title' => 'Performance QLola',
                'subtitle' => 'Platform cash management QLola untuk monitoring likuiditas nasabah korporasi.',
                'badge' => 'QLOLA',
                'badge_class' => 'badge-warning',
                'tone' => 'digital-qlola',
                'icon' => 'fas fa-university',
                'link' => route('report.qlola'),
                'link_label' => 'Buka report QLola',
                'current_value' => $this->formatInteger((int) $current['nasabah_count']),
                'current_label' => 'Nasabah Aktif',
                'secondary_value' => $this->formatCurrencyCompact((float) $current['volume']),
                'secondary_label' => 'Volume',
                'trend_reference' => $this->formatInteger((int) $previous['nasabah_count']) . ' nasabah sebelumnya',
                'trend_direction' => $this->percentChange((float) $current['nasabah_count'], (float) $previous['nasabah_count']),
                'series' => array_column($timeline, 'nasabah_count'),
                'series_labels' => array_column($timeline, 'label'),
                'stats' => [
                    ['label' => 'Nasabah', 'value' => $this->formatInteger((int) $current['nasabah_count'])],
                    ['label' => 'Volume', 'value' => $this->formatCurrencyCompact((float) $current['volume'])],
                    ['label' => 'Periode', 'value' => Carbon::parse($latestPeriod)->translatedFormat('d M Y')],
                ],
                'source_updated_at' => $latestPeriod,
                'source_table' => 'usak_ibbiz_uker / ibbisniz_corp',
            ]);
        } catch (Throwable $e) {
            Log::warning('Dashboard digital QLola gagal disusun: ' . $e->getMessage());
            return null;
        }
    }

    private function buildCasaDebiturKpiCard(): ?array
    {
        try {
            $snapshotCard = $this->buildCasaDebiturKpiCardFromSnapshot();
            if ($snapshotCard !== null) {
                return $snapshotCard;
            }

            // Coba tabel rasio casa debitur
            $tableName = null;
            foreach (['rasio_casa_debitur', 'casa_debitur_summary', 'rekening_transaksi_debitur'] as $tbl) {
                if (Schema::hasTable($tbl)) {
                    $tableName = $tbl;
                    break;
                }
            }

            if (!$tableName) {
                return [
                    'key' => 'casa',
                    'title' => 'Rasio Casa Debitur',
                    'subtitle' => 'Rasio kepemilikan rekening tabungan oleh debitur aktif Area 6.',
                    'badge' => 'CASA',
                    'badge_class' => 'badge-info',
                    'tone' => 'digital-casa',
                    'icon' => 'fas fa-percentage',
                    'link' => route('report.rasiocasa.debitur'),
                    'link_label' => 'Buka report Casa',
                    'current_value' => '-',
                    'current_label' => 'Rasio Casa',
                    'secondary_value' => '-',
                    'secondary_label' => 'Debitur Aktif',
                    'trend' => '0,0%',
                    'trend_class' => 'text-muted',
                    'trend_value' => 0,
                    'trend_reference' => 'Data belum tersedia',
                    'value' => '–',
                    'meta' => 'Data belum tersedia',
                    'chart' => ['points' => [], 'path' => '', 'area_path' => ''],
                    'series' => [],
                    'series_labels' => [],
                    'stats' => [
                        ['label' => 'Status', 'value' => 'Menunggu Data'],
                        ['label' => 'Periode', 'value' => '-'],
                        ['label' => 'Link', 'value' => 'Lihat Detail'],
                    ],
                    'source_updated_at' => null,
                    'is_stub' => true,
                    'detail_payload' => $this->buildLandingSourceDetail('Rasio Casa Debitur', null, 'rasio_casa_debitur / casa_debitur_summary / rekening_transaksi_debitur', [['label' => 'Status', 'value' => 'Tabel sumber belum tersedia', 'source' => 'Schema check']], 'Landing page tidak membuat angka pengganti saat tabel CASA belum ada.'),
                ];
            }

            $branchColumn = collect(['branch_label', 'cabang', 'kanca', 'kantor_cabang', 'branch_office'])
                ->first(fn (string $column): bool => Schema::hasColumn($tableName, $column));
            if (UserBranchScope::current() !== null && $branchColumn === null) {
                return null;
            }

            // Cari kolom periode
            $periodCol = Schema::hasColumn($tableName, 'posisi') ? 'posisi'
                : (Schema::hasColumn($tableName, 'periode') ? 'periode' : 'created_at');
            $sourceQuery = DB::table($tableName);
            if ($branchColumn !== null) {
                $this->applyDashboardBranchScope($sourceQuery, $branchColumn);
            }
            $latestPeriod = (clone $sourceQuery)->max($periodCol);
            if (!$latestPeriod) {
                return null;
            }

            $row = (clone $sourceQuery)
                ->where($periodCol, $latestPeriod)
                ->selectRaw('COUNT(*) as total_debitur')
                ->selectRaw('SUM(CASE WHEN COALESCE(flag_casa, 0) = 1 THEN 1 ELSE 0 END) as casa_count')
                ->first();

            $totalDebitur = (int) ($row->total_debitur ?? 0);
            $casaCount = (int) ($row->casa_count ?? 0);
            $rasio = $totalDebitur > 0 ? ($casaCount / $totalDebitur) * 100 : 0;

            return $this->buildDigitalCard([
                'key' => 'casa',
                'title' => 'Rasio Casa Debitur',
                'subtitle' => 'Rasio kepemilikan rekening tabungan oleh debitur aktif Area 6.',
                'badge' => 'CASA',
                'badge_class' => 'badge-info',
                'tone' => 'digital-casa',
                'icon' => 'fas fa-percentage',
                'link' => route('report.rasiocasa.debitur'),
                'link_label' => 'Buka report Casa',
                'current_value' => $this->formatPercent($rasio),
                'current_label' => 'Rasio Casa',
                'secondary_value' => $this->formatInteger($totalDebitur),
                'secondary_label' => 'Total Debitur',
                'trend_reference' => $this->formatInteger($casaCount) . ' debitur punya CASA',
                'value' => $this->formatPercent($rasio),
                'meta' => $this->formatInteger($casaCount) . ' debitur punya CASA',
                'trend_direction' => $rasio - 50,
                'series' => [$rasio],
                'series_labels' => [Carbon::parse($latestPeriod)->translatedFormat('d M')],
                'stats' => [
                    ['label' => 'Debitur CASA', 'value' => $this->formatInteger($casaCount)],
                    ['label' => 'Total Debitur', 'value' => $this->formatInteger($totalDebitur)],
                    ['label' => 'Rasio', 'value' => $this->formatPercent($rasio)],
                ],
                'source_updated_at' => $latestPeriod,
                'source_table' => $tableName,
            ]);
        } catch (Throwable $e) {
            Log::warning('Dashboard digital Casa Debitur gagal disusun: ' . $e->getMessage());
            return null;
        }
    }

    private function buildCasaDebiturKpiCardFromSnapshot(): ?array
    {
        if (!Schema::hasTable('rasio_casa_debitur_snapshots')) {
            return null;
        }

        $sourceQuery = DB::table('rasio_casa_debitur_snapshots');
        $this->applyDashboardBranchScope($sourceQuery, 'branch_label');
        $latestPeriod = (clone $sourceQuery)->max('loan_period');
        if (!$latestPeriod) {
            return null;
        }

        $summary = (clone $sourceQuery)
            ->whereDate('loan_period', $latestPeriod)
            ->selectRaw('COALESCE(SUM(COALESCE(os_amount, 0)), 0) as os_amount')
            ->selectRaw('COALESCE(SUM(COALESCE(casa_amount, 0)), 0) as casa_amount')
            ->selectRaw('COALESCE(SUM(COALESCE(source_row_count, 0)), 0) as source_row_count')
            ->selectRaw('MAX(casa_period) as casa_period')
            ->first();

        if (!$summary) {
            return null;
        }

        $osAmount = (float) ($summary->os_amount ?? 0);
        $casaAmount = (float) ($summary->casa_amount ?? 0);
        $ratio = $this->percentOf($casaAmount, $osAmount);

        return $this->buildDigitalCard([
            'key' => 'casa',
            'title' => 'Rasio Casa Debitur',
            'subtitle' => 'Snapshot CASA debitur Area 6.',
            'badge' => 'CASA',
            'badge_class' => 'badge-info',
            'tone' => 'digital-casa',
            'icon' => 'fas fa-percentage',
            'link' => route('report.rasiocasa.debitur'),
            'link_label' => 'Buka report Casa',
            'current_value' => $this->formatPercent($ratio),
            'current_label' => 'Rasio Casa',
            'secondary_value' => $this->formatCurrencyCompact($osAmount),
            'secondary_label' => 'OS Debitur',
            'trend_reference' => $this->formatCurrencyCompact($casaAmount) . ' CASA',
            'value' => $this->formatPercent($ratio),
            'meta' => $this->formatCurrencyCompact($casaAmount) . ' CASA',
            'trend_direction' => $ratio,
            'series' => [$ratio],
            'series_labels' => [Carbon::parse($latestPeriod)->translatedFormat('d M')],
            'stats' => [
                ['label' => 'Total CASA', 'value' => $this->formatCurrencyCompact($casaAmount)],
                ['label' => 'OS Debitur', 'value' => $this->formatCurrencyCompact($osAmount)],
                ['label' => 'Periode', 'value' => Carbon::parse($latestPeriod)->translatedFormat('d M Y')],
            ],
            'source_updated_at' => $summary->casa_period ?? $latestPeriod,
            'source_table' => 'rasio_casa_debitur_snapshots',
            'source_note' => 'Kartu CASA landing memakai snapshot rasio CASA debitur yang sudah dibangun.',
        ]);
    }

    private function buildRekeningDormantKpiCard(): ?array
    {
        try {
            $snapshotCard = $this->buildRekeningDormantKpiCardFromSnapshot();
            if ($snapshotCard !== null) {
                return $snapshotCard;
            }

            $tableName = null;
            foreach (['rekening_dormant', 'rekening_dormant_detail', 'dormant_summary'] as $tbl) {
                if (Schema::hasTable($tbl)) {
                    $tableName = $tbl;
                    break;
                }
            }

            if (!$tableName) {
                return [
                    'key' => 'dormant',
                    'title' => 'Rekening Dormant',
                    'subtitle' => 'Monitoring rekening tidak aktif untuk menjaga kualitas DPK Area 6.',
                    'badge' => 'DORMANT',
                    'badge_class' => 'badge-danger',
                    'tone' => 'digital-dormant',
                    'icon' => 'fas fa-bed',
                    'link' => route('report.rekening-dormant'),
                    'link_label' => 'Buka report Dormant',
                    'current_value' => '-',
                    'current_label' => 'Rekening Dormant',
                    'secondary_value' => '-',
                    'secondary_label' => 'Saldo Tertahan',
                    'trend' => '0,0%',
                    'trend_class' => 'text-muted',
                    'trend_value' => 0,
                    'trend_reference' => 'Data belum tersedia',
                    'chart' => ['points' => [], 'path' => '', 'area_path' => ''],
                    'series' => [],
                    'series_labels' => [],
                    'stats' => [
                        ['label' => 'Status', 'value' => 'Menunggu Data'],
                        ['label' => 'Periode', 'value' => '-'],
                        ['label' => 'Link', 'value' => 'Lihat Detail'],
                    ],
                    'source_updated_at' => null,
                    'is_stub' => true,
                    'detail_payload' => $this->buildLandingSourceDetail('Rekening Dormant', null, 'rekening_dormant / rekening_dormant_detail / dormant_summary', [['label' => 'Status', 'value' => 'Tabel sumber belum tersedia', 'source' => 'Schema check']], 'Landing page tidak membuat angka pengganti saat tabel dormant belum ada.'),
                ];
            }

            $periodCol = Schema::hasColumn($tableName, 'posisi') ? 'posisi'
                : (Schema::hasColumn($tableName, 'periode') ? 'periode' : 'created_at');
            $latestPeriod = DB::table($tableName)->max($periodCol);
            if (!$latestPeriod) {
                return null;
            }

            $periods = $this->buildTrendDatePeriods($latestPeriod);
            $branches = $this->dashboardBranchNames();
            $branchCol = Schema::hasColumn($tableName, 'kanca') ? 'kanca'
                : (Schema::hasColumn($tableName, 'cabang') ? 'cabang' : 'kantor_cabang');
            $saldoCol = Schema::hasColumn($tableName, 'saldo_idr') ? 'saldo_idr'
                : (Schema::hasColumn($tableName, 'saldo') ? 'saldo' : '0');
            $timeline = [];

            foreach ($periods as $period) {
                $row = DB::table($tableName)
                    ->whereDate($periodCol, $period)
                    ->whereIn(DB::raw('UPPER(TRIM(' . $branchCol . '))'), $branches)
                    ->selectRaw('COUNT(*) as dormant_count')
                    ->selectRaw('COALESCE(SUM(COALESCE(' . $saldoCol . ', 0)), 0) as saldo')
                    ->first();

                $timeline[] = [
                    'label' => Carbon::parse($period)->translatedFormat('d M'),
                    'dormant_count' => (int) ($row->dormant_count ?? 0),
                    'saldo' => (float) ($row->saldo ?? 0),
                    'source_updated_at' => $period,
                ];
            }

            $current = $timeline[array_key_last($timeline)] ?? ['dormant_count' => 0, 'saldo' => 0];
            $previous = $timeline[count($timeline) - 2] ?? $current;

            return $this->buildDigitalCard([
                'key' => 'dormant',
                'title' => 'Rekening Dormant',
                'subtitle' => 'Monitoring rekening tidak aktif untuk menjaga kualitas DPK Area 6.',
                'badge' => 'DORMANT',
                'badge_class' => 'badge-danger',
                'tone' => 'digital-dormant',
                'icon' => 'fas fa-bed',
                'link' => route('report.rekening-dormant'),
                'link_label' => 'Buka report Dormant',
                'current_value' => $this->formatInteger((int) $current['dormant_count']),
                'current_label' => 'Rekening Dormant',
                'secondary_value' => $this->formatCurrencyCompact((float) $current['saldo']),
                'secondary_label' => 'Saldo Tertahan',
                'trend_reference' => $this->formatInteger((int) $previous['dormant_count']) . ' rek. periode sebelumnya',
                'trend_direction' => -$this->percentChange((float) $current['dormant_count'], (float) $previous['dormant_count']),
                'series' => array_column($timeline, 'dormant_count'),
                'series_labels' => array_column($timeline, 'label'),
                'stats' => [
                    ['label' => 'Rekening', 'value' => $this->formatInteger((int) $current['dormant_count'])],
                    ['label' => 'Saldo', 'value' => $this->formatCurrencyCompact((float) $current['saldo'])],
                    ['label' => 'Periode', 'value' => Carbon::parse($latestPeriod)->translatedFormat('d M Y')],
                ],
                'source_updated_at' => $latestPeriod,
                'source_table' => $tableName,
            ]);
        } catch (Throwable $e) {
            Log::warning('Dashboard digital Rekening Dormant gagal disusun: ' . $e->getMessage());
            return null;
        }
    }

    private function buildRekeningDormantKpiCardFromSnapshot(): ?array
    {
        if (!Schema::hasTable('rekening_dormant_snapshots')) {
            return null;
        }

        $latestPeriod = DB::table('rekening_dormant_snapshots')->max('posisi');
        if (!$latestPeriod) {
            return null;
        }

        $periods = $this->buildTrendDatePeriods($latestPeriod);
        $timeline = [];

        foreach ($periods as $period) {
            $row = DB::table('rekening_dormant_snapshots')
                ->whereDate('posisi', $period)
                ->whereIn(DB::raw('UPPER(TRIM(branch_label))'), $this->dashboardBranchNames())
                ->selectRaw('COALESCE(SUM(COALESCE(dormant_count, 0)), 0) as dormant_count')
                ->first();

            $timeline[] = [
                'label' => Carbon::parse($period)->translatedFormat('d M'),
                'dormant_count' => (int) ($row->dormant_count ?? 0),
                'source_updated_at' => $period,
            ];
        }

        $current = $timeline[array_key_last($timeline)] ?? ['dormant_count' => 0];
        $previous = $timeline[count($timeline) - 2] ?? $current;

        return $this->buildDigitalCard([
            'key' => 'dormant',
            'title' => 'Rekening Dormant',
            'subtitle' => 'Snapshot rekening dormant Area 6.',
            'badge' => 'DORMANT',
            'badge_class' => 'badge-danger',
            'tone' => 'digital-dormant',
            'icon' => 'fas fa-bed',
            'link' => route('report.rekening-dormant'),
            'link_label' => 'Buka report Dormant',
            'current_value' => $this->formatInteger((int) $current['dormant_count']),
            'current_label' => 'Rekening Dormant',
            'secondary_value' => '-',
            'secondary_label' => 'Saldo Tertahan',
            'trend_reference' => $this->formatInteger((int) $previous['dormant_count']) . ' rek. periode sebelumnya',
            'trend_direction' => -$this->percentChange((float) $current['dormant_count'], (float) $previous['dormant_count']),
            'series' => array_column($timeline, 'dormant_count'),
            'series_labels' => array_column($timeline, 'label'),
            'stats' => [
                ['label' => 'Rekening', 'value' => $this->formatInteger((int) $current['dormant_count'])],
                ['label' => 'Saldo', 'value' => '-'],
                ['label' => 'Periode', 'value' => Carbon::parse($latestPeriod)->translatedFormat('d M Y')],
            ],
            'source_updated_at' => $latestPeriod,
            'source_table' => 'rekening_dormant_snapshots',
            'source_note' => 'Kartu dormant landing memakai snapshot rekening dormant yang sudah dibangun.',
        ]);
    }

    private function buildTimeseriesPayload(?string $simpananPeriod, ?string $loanPeriod): array
    {
        $cacheKey = 'dashboard_simpanan:timeseries:' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $this->reportCacheVersion() . ':' . ($simpananPeriod ?? 'null') . ':' . ($loanPeriod ?? 'null');

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($simpananPeriod, $loanPeriod) {
            $points = 6;
            $simpananTimeline = [];
            $loanTimeline = [];
            $labels = [];

            // Build simpanan timeseries
            if ($simpananPeriod && (Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE) || Schema::hasTable('simpanan_multipn'))) {
                $current = Carbon::parse($simpananPeriod)->startOfDay();
                for ($offset = $points - 1; $offset >= 0; $offset--) {
                    $p = $offset === 0
                        ? $current->toDateString()
                        : $current->copy()->subMonthsNoOverflow($offset)->endOfMonth()->toDateString();

                    $actualPeriod = $this->resolveHarianSnapshotPeriodOnOrBefore($p);
                    if (!$actualPeriod && Schema::hasTable('simpanan_multipn')) {
                        $periodQuery = DB::table('simpanan_multipn')->where('posisi', '<=', $p);
                        $this->applyDashboardBranchScope($periodQuery, 'kantor_cabang');
                        $actualPeriod = $periodQuery->max('posisi');
                    }
                    if ($actualPeriod) {
                        $sum = $this->buildPeriodSummary($actualPeriod);
                        $simpananTimeline[] = round((float) ($sum['total_balance'] ?? 0) / 1e12, 3);
                        $labels[] = Carbon::parse($actualPeriod)->translatedFormat('M y');
                    } else {
                        $simpananTimeline[] = 0;
                        $labels[] = Carbon::parse($p)->translatedFormat('M y');
                    }
                }
            } else {
                $simpananTimeline = array_fill(0, $points, 0);
                $labels = array_fill(0, $points, '-');
            }

            // Build loan timeseries aligned to same labels
            if ($loanPeriod && (Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE) || Schema::hasTable('daily_loan_dinamis') || Schema::hasTable(self::LOAN_SNAPSHOT_TABLE))) {
                $current = Carbon::parse($loanPeriod)->startOfDay();
                for ($offset = $points - 1; $offset >= 0; $offset--) {
                    $p = $offset === 0
                        ? $current->toDateString()
                        : $current->copy()->subMonthsNoOverflow($offset)->endOfMonth()->toDateString();

                    $actualPeriod = $this->resolveHarianSnapshotPeriodOnOrBefore($p);
                    if (!$actualPeriod) {
                        $table = Schema::hasTable(self::LOAN_SNAPSHOT_TABLE)
                            ? self::LOAN_SNAPSHOT_TABLE
                            : (Schema::hasTable('daily_loan_dinamis') ? 'daily_loan_dinamis' : null);
                        if ($table) {
                            $periodQuery = DB::table($table)->where('periode', '<=', $p);
                            $this->applyDashboardBranchScope($periodQuery, 'cabang1');
                            $actualPeriod = $periodQuery->max('periode');
                        }
                    }
                    if ($actualPeriod) {
                        $sum = $this->buildLoanSummary($actualPeriod);
                        $loanTimeline[] = round((float) ($sum['total_balance'] ?? 0) / 1e12, 3);
                    } else {
                        $loanTimeline[] = 0;
                    }
                }
            } else {
                $loanTimeline = array_fill(0, $points, 0);
            }

            return [
                'labels' => $labels,
                'simpanan' => $simpananTimeline,
                'pinjaman' => $loanTimeline,
            ];
        });
    }

    private function buildDigitalCard(array $card): array
    {
        $series = collect(data_get($card, 'series', []))
            ->map(fn ($value) => (float) $value)
            ->values()
            ->all();

        $chart = $this->buildChartPoints($series);
        $currentSeriesValue = !empty($series) ? (float) $series[array_key_last($series)] : 0;
        $previousSeriesValue = count($series) > 1 ? (float) $series[count($series) - 2] : 0;
        $trend = $this->percentChange($currentSeriesValue, $previousSeriesValue);
        $detailPayload = $card['detail_payload'] ?? $this->buildDigitalCardDetail($card);

        return array_merge($card, [
            'trend' => $this->formatSignedPercent($trend),
            'trend_class' => $this->deltaClass($trend),
            'trend_value' => $trend,
            'chart' => $chart,
            'series' => $series,
            'detail_payload' => $detailPayload,
        ]);
    }

    private function buildDigitalCardDetail(array $card): array
    {
        $sourceTable = (string) ($card['source_table'] ?? $this->defaultDigitalSourceTable((string) ($card['key'] ?? '')));
        $rows = [
            ['label' => (string) ($card['current_label'] ?? 'Nilai utama'), 'value' => (string) ($card['current_value'] ?? '-'), 'source' => $sourceTable],
            ['label' => (string) ($card['secondary_label'] ?? 'Nilai pembanding'), 'value' => (string) ($card['secondary_value'] ?? '-'), 'source' => $sourceTable],
            ['label' => 'Trend', 'value' => (string) ($card['trend_reference'] ?? '-'), 'source' => $sourceTable],
        ];

        foreach (array_slice((array) ($card['stats'] ?? []), 0, 5) as $stat) {
            $rows[] = [
                'label' => (string) data_get($stat, 'label', '-'),
                'value' => (string) data_get($stat, 'value', '-'),
                'source' => $sourceTable,
            ];
        }

        return $this->buildLandingSourceDetail(
            (string) ($card['title'] ?? $card['badge'] ?? 'Detail'),
            (string) ($card['source_updated_at'] ?? ''),
            $sourceTable,
            $rows,
            (string) ($card['source_note'] ?? 'Angka diambil dari agregasi tabel sumber dengan filter cabang Area 6 bila tersedia.')
        );
    }

    private function defaultDigitalSourceTable(string $key): string
    {
        return match ($key) {
            'edc' => 'jumlah_merchant_detail',
            'qris' => 'jumlah_merchant_qris_detail',
            'brimo' => 'user_brimo_rpt_v2 + user_brimo_fin',
            'brilink' => 'brilink_web_laporan_summary_transaksi_brilink_web',
            'payroll' => 'performance_pis_per_produk',
            'qlola' => 'qlola_detail / qlola_report / qlola_summary',
            'casa' => 'rasio_casa_debitur / casa_debitur_summary / rekening_transaksi_debitur',
            'dormant' => 'rekening_dormant / rekening_dormant_detail / dormant_summary',
            default => 'Sumber belum dipetakan',
        };
    }

    private function buildTrendDatePeriods(string $latestPeriod, int $points = 4): array
    {
        $current = Carbon::parse($latestPeriod)->startOfDay();
        $periods = [];

        for ($offset = $points - 1; $offset >= 0; $offset--) {
            if ($offset === 0) {
                $periods[] = $current->toDateString();
                continue;
            }

            $periods[] = $current->copy()->subMonthsNoOverflow($offset)->endOfMonth()->toDateString();
        }

        return array_values(array_unique($periods));
    }

    private function buildTrendMonthPeriods(string $latestPeriod, int $points = 4): array
    {
        $current = Carbon::parse($latestPeriod)->startOfMonth();
        $periods = [];

        for ($offset = $points - 1; $offset >= 0; $offset--) {
            $periods[] = $current->copy()->subMonthsNoOverflow($offset)->format('F Y');
        }

        return array_values(array_unique($periods));
    }

    private function buildChartPoints(array $series, int $width = 160, int $height = 48): array
    {
        $values = array_values(array_map(fn ($value) => max(0, (float) $value), $series));
        if (empty($values)) {
            $values = [0, 0, 0, 0];
        }

        $count = count($values);
        $paddingX = 8;
        $paddingY = 6;
        $usableWidth = max(1, $width - ($paddingX * 2));
        $usableHeight = max(1, $height - ($paddingY * 2));
        $max = max(max($values), 1);
        $min = min($values);
        $points = [];

        foreach ($values as $index => $value) {
            $x = $count > 1 ? $paddingX + ($usableWidth * ($index / ($count - 1))) : ($width / 2);
            $normalized = $max === $min ? 0.5 : (($value - $min) / ($max - $min));
            $y = $height - $paddingY - ($normalized * $usableHeight);
            $points[] = [
                'x' => round($x, 2),
                'y' => round($y, 2),
                'value' => $value,
            ];
        }

        $path = 'M ' . implode(' L ', array_map(fn ($point) => $point['x'] . ' ' . $point['y'], $points));
        $lastPoint = end($points);
        $firstPoint = reset($points);
        $areaPath = $path;

        if ($firstPoint && $lastPoint) {
            $areaPath .= ' L ' . $lastPoint['x'] . ' ' . ($height - $paddingY);
            $areaPath .= ' L ' . $firstPoint['x'] . ' ' . ($height - $paddingY) . ' Z';
        }

        return [
            'points' => $points,
            'path' => $path,
            'area_path' => $areaPath,
            'max' => $max,
            'min' => $min,
        ];
    }

    private function dashboardBranchNames(): array
    {
        $scope = UserBranchScope::current();
        if ($scope !== null) {
            return [$scope['upper_label']];
        }

        static $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];

        return $branches;
    }

    private function dashboardBranchDisplayNames(): array
    {
        $scope = UserBranchScope::current();

        return $scope !== null ? [$scope['label']] : self::AREA_6_BRANCH_LABELS;
    }

    private function dashboardScopeLabel(): string
    {
        return UserBranchScope::current()['label'] ?? 'Area 6';
    }

    private function applyDashboardBranchScope($query, string $column): void
    {
        $scope = UserBranchScope::current();
        if ($scope === null) {
            return;
        }

        $query->whereRaw("UPPER(TRIM(COALESCE({$column}, ''))) LIKE ?", [
            '%' . $scope['upper_label'] . '%',
        ]);
    }

    private function normalizedSql(string $column): string
    {
        return "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE({$column}, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', ''))";
    }

    private function normalizeToken(?string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string) $value))) ?? '';
    }

    private function percentChange(float|int $current, float|int $previous): float
    {
        if ((float) $previous === 0.0) {
            return (float) $current > 0 ? 100.0 : 0.0;
        }

        return (((float) $current - (float) $previous) / (float) $previous) * 100;
    }

    private function percentOf(float|int $value, float|int $total): float
    {
        if ((float) $total === 0.0) {
            return 0.0;
        }

        return ((float) $value / (float) $total) * 100;
    }

    private function formatInteger(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    private function formatPercent(float $value): string
    {
        return number_format($value, 1, ',', '.') . '%';
    }

    private function formatPercentTwo(float $value): string
    {
        return number_format($value, 2, ',', '.') . '%';
    }

    private function formatSignedPercent(float $value): string
    {
        $prefix = $value > 0 ? '+' : '';

        return $prefix . number_format($value, 2, ',', '.') . '%';
    }

    private function formatCurrencyCompact(float $value): string
    {
        $abs = abs($value);

        if ($abs >= 1000000000000) {
            return 'Rp' . number_format($value / 1000000000000, 2, ',', '.') . ' T';
        }

        if ($abs >= 1000000000) {
            return 'Rp' . number_format($value / 1000000000, 2, ',', '.') . ' M';
        }

        if ($abs >= 1000000) {
            return 'Rp' . number_format($value / 1000000, 2, ',', '.') . ' Jt';
        }

        return 'Rp' . number_format($value, 0, ',', '.');
    }

    private function formatCurrencyFull(float $value): string
    {
        return 'Rp' . number_format($value, 0, ',', '.');
    }

    private function formatRatio(float $numerator, float $denominator): string
    {
        if ($denominator == 0.0) {
            return '0,00x';
        }

        return number_format($numerator / $denominator, 2, ',', '.') . 'x';
    }

    private function formatPeriodLabel(?string $period): string
    {
        if (!$period) {
            return 'Belum ada data';
        }

        return Carbon::parse($period)->translatedFormat('d M Y');
    }

    private function simplifyBranchLabel(string $branch): string
    {
        $label = preg_replace('/^\d+\s*--\s*/', '', $branch) ?? $branch;
        $label = preg_replace('/\(.+\)$/', '', $label) ?? $label;

        return trim($label);
    }

    private function deltaClass(float $value, bool $badgeCompatible = false): string
    {
        if ($value > 0) {
            return $badgeCompatible ? 'badge-success' : 'text-success';
        }

        if ($value < 0) {
            return $badgeCompatible ? 'badge-danger' : 'text-danger';
        }

        return $badgeCompatible ? 'badge-secondary' : 'text-muted';
    }

    private function reportCacheVersion(): string
    {
        return ReportCacheVersion::composite(['simpanan', 'pinjaman', 'harian'])
            . ':scope:' . UserBranchScope::cacheKey();
    }
}
