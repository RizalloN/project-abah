<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LinkManagementController extends Controller
{
    private const LINK_TABLE = 'external_report_links';
    private const BUSINESS_CLUSTER_TABLE = 'business_cluster';
    private const KPI_GROUP = 'almafacts_kpi';
    private const KOLABORASI_GROUP = 'kolaborasi_report';
    private const MARKET_SHARE_GROUP = 'market_share';
    private const SPPG_LINK_KEY = 'sppg';
    private const BUSINESS_CLUSTER_BRANCHES = [
        'KC Madiun',
        'KC Magetan',
        'KC Ngawi',
        'KC Ponorogo',
    ];
    private const KPI_DEFAULTS = [
        'mbm' => [
            'label' => 'KPI MBM',
            'sheet_name' => 'KPI MBM',
            'spreadsheet_id' => '1KgXJ4fi9u4-mJyaZADXF0cM9wJnVlh0f7sQBZeR8fLY',
            'link_url' => 'https://docs.google.com/spreadsheets/d/1KgXJ4fi9u4-mJyaZADXF0cM9wJnVlh0f7sQBZeR8fLY/edit?usp=sharing',
        ],
        'ka-unit' => [
            'label' => 'KPI KA Unit',
            'sheet_name' => 'KPI Kaunit',
            'spreadsheet_id' => '1KgXJ4fi9u4-mJyaZADXF0cM9wJnVlh0f7sQBZeR8fLY',
            'link_url' => 'https://docs.google.com/spreadsheets/d/1KgXJ4fi9u4-mJyaZADXF0cM9wJnVlh0f7sQBZeR8fLY/edit?usp=sharing',
        ],
        'rm-mikro' => [
            'label' => 'KPI RM Mikro',
            'sheet_name' => 'rank',
            'spreadsheet_id' => '1v1loife4UzSSsdJ9yGYl3SSuKtk_16CwtlKMj2f8dTM',
            'link_url' => 'https://docs.google.com/spreadsheets/d/1v1loife4UzSSsdJ9yGYl3SSuKtk_16CwtlKMj2f8dTM/edit?usp=sharing',
        ],
        'mantri' => [
            'label' => 'KPI Mantri',
            'sheet_name' => 'RANK KPI',
            'spreadsheet_id' => '1qiek9zPfsd7NSGSSWoQQZAhIFD9hNnfoeLvQEoz1few',
            'link_url' => 'https://docs.google.com/spreadsheets/d/1qiek9zPfsd7NSGSSWoQQZAhIFD9hNnfoeLvQEoz1few/edit?usp=sharing',
        ],
    ];
    private const MARKET_SHARE_DEFAULTS = [
        'mapping' => [
            'label' => 'Mapping Market Share',
            'sheet_name' => 'DASHBOARD',
            'spreadsheet_id' => '18RTg3ajn4Lpa2MkXtg8uuiRE7HsmEWbS3EdqO5xrcbY',
            'link_url' => 'https://docs.google.com/spreadsheets/d/18RTg3ajn4Lpa2MkXtg8uuiRE7HsmEWbS3EdqO5xrcbY/edit?usp=sharing',
        ],
    ];

    public function index(): View
    {
        $this->ensureKpiDefaults();
        $this->ensureMarketShareDefaults();

        return view('admin.link-management', [
            'kpiLinks' => $this->kpiLinks(),
            'sppgLink' => $this->sppgLink(),
            'marketShareLinks' => $this->marketShareLinks(),
            'businessClusterLinks' => $this->businessClusterLinks(),
            'linkTableReady' => Schema::hasTable(self::LINK_TABLE),
            'businessClusterTableReady' => Schema::hasTable(self::BUSINESS_CLUSTER_TABLE),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kpi' => ['array'],
            'kpi.*.link_url' => ['required', 'url', 'max:2048'],
            'kpi.*.sheet_name' => ['required', 'string', 'max:160'],
            'sppg.link_url' => ['nullable', 'url', 'max:2048'],
            'sppg.sheet_name' => ['nullable', 'string', 'max:160'],
            'market_share' => ['array'],
            'market_share.*.link_url' => ['required', 'url', 'max:2048'],
            'market_share.*.sheet_name' => ['required', 'string', 'max:160'],
            'business_cluster' => ['array'],
            'business_cluster.*.link_url' => ['nullable', 'url', 'max:2048'],
        ], [
            'kpi.*.link_url.required' => 'Link KPI wajib diisi.',
            'kpi.*.link_url.url' => 'Link KPI harus berupa URL valid.',
            'kpi.*.sheet_name.required' => 'Nama sheet KPI wajib diisi.',
            'sppg.link_url.url' => 'Link SPPG harus berupa URL valid.',
            'market_share.*.link_url.required' => 'Link Market Share wajib diisi.',
            'market_share.*.link_url.url' => 'Link Market Share harus berupa URL valid.',
            'market_share.*.sheet_name.required' => 'Nama sheet Market Share wajib diisi.',
            'business_cluster.*.link_url.url' => 'Link Business Cluster harus berupa URL valid.',
        ]);

        if (!Schema::hasTable(self::LINK_TABLE)) {
            return back()->with('sweet_warning', [
                'title' => 'Tabel Link Belum Tersedia',
                'text' => 'Jalankan migration terlebih dahulu sebelum menyimpan Link Management.',
            ]);
        }

        DB::transaction(function () use ($validated): void {
            foreach (($validated['kpi'] ?? []) as $key => $payload) {
                if (!array_key_exists($key, self::KPI_DEFAULTS)) {
                    continue;
                }

                $linkUrl = trim((string) $payload['link_url']);
                $sheetName = trim((string) $payload['sheet_name']);
                $spreadsheetId = $this->extractSpreadsheetId($linkUrl) ?: self::KPI_DEFAULTS[$key]['spreadsheet_id'];

                DB::table(self::LINK_TABLE)->updateOrInsert(
                    ['group_key' => self::KPI_GROUP, 'link_key' => $key],
                    [
                        'uniqueid_link' => $this->linkId(self::KPI_GROUP, $key),
                        'label' => self::KPI_DEFAULTS[$key]['label'],
                        'sheet_name' => $sheetName,
                        'spreadsheet_id' => $spreadsheetId,
                        'link_url' => $linkUrl,
                        'is_active' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );

                foreach (['v5', 'v6'] as $cacheVersion) {
                    Cache::forget('dashboard_almafacts:kpi_sheet:' . $cacheVersion . ':' . $key . ':' . md5($spreadsheetId . '|' . $sheetName));
                }
            }

            $sppg = $validated['sppg'] ?? [];
            $sppgLinkUrl = trim((string) ($sppg['link_url'] ?? ''));
            $sppgSheetName = trim((string) ($sppg['sheet_name'] ?? 'Area 6')) ?: 'Area 6';
            $oldSppg = DB::table(self::LINK_TABLE)
                ->where('group_key', self::KOLABORASI_GROUP)
                ->where('link_key', self::SPPG_LINK_KEY)
                ->first();

            DB::table(self::LINK_TABLE)->updateOrInsert(
                ['group_key' => self::KOLABORASI_GROUP, 'link_key' => self::SPPG_LINK_KEY],
                [
                    'uniqueid_link' => $this->linkId(self::KOLABORASI_GROUP, self::SPPG_LINK_KEY),
                    'label' => 'SPPG',
                    'sheet_name' => $sppgSheetName,
                    'spreadsheet_id' => $this->extractSpreadsheetId($sppgLinkUrl) ?: '',
                    'link_url' => $sppgLinkUrl,
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            if ($oldSppg) {
                Cache::forget('report:sppg:v1:' . md5(($oldSppg->link_url ?? '') . '|' . ($oldSppg->sheet_name ?? 'Area 6')));
            }
            Cache::forget('report:sppg:v1:' . md5($sppgLinkUrl . '|' . $sppgSheetName));

            foreach (($validated['market_share'] ?? []) as $key => $payload) {
                if (!array_key_exists($key, self::MARKET_SHARE_DEFAULTS)) {
                    continue;
                }

                $linkUrl = trim((string) $payload['link_url']);
                $sheetName = trim((string) $payload['sheet_name']);
                $spreadsheetId = $this->extractSpreadsheetId($linkUrl) ?: self::MARKET_SHARE_DEFAULTS[$key]['spreadsheet_id'];

                DB::table(self::LINK_TABLE)->updateOrInsert(
                    ['group_key' => self::MARKET_SHARE_GROUP, 'link_key' => $key],
                    [
                        'uniqueid_link' => $this->linkId(self::MARKET_SHARE_GROUP, $key),
                        'label' => self::MARKET_SHARE_DEFAULTS[$key]['label'],
                        'sheet_name' => $sheetName,
                        'spreadsheet_id' => $spreadsheetId,
                        'link_url' => $linkUrl,
                        'is_active' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            if (Schema::hasTable(self::BUSINESS_CLUSTER_TABLE)) {
                foreach (($validated['business_cluster'] ?? []) as $branch => $payload) {
                    if (!in_array($branch, self::BUSINESS_CLUSTER_BRANCHES, true)) {
                        continue;
                    }

                    $linkUrl = trim((string) ($payload['link_url'] ?? ''));
                    if ($linkUrl === '') {
                        continue;
                    }

                    $oldLink = DB::table(self::BUSINESS_CLUSTER_TABLE)
                        ->where('nama_kanca', $branch)
                        ->value('link_url');

                    DB::table(self::BUSINESS_CLUSTER_TABLE)->updateOrInsert(
                        ['nama_kanca' => $branch],
                        [
                            'uniqueid_namareport' => 'business_cluster_' . Str::slug($branch, '_'),
                            'link_url' => $linkUrl,
                        ]
                    );

                    if ($oldLink) {
                        Cache::forget('report:business_cluster:v2:' . md5($branch . '|' . $oldLink));
                    }
                    Cache::forget('report:business_cluster:v2:' . md5($branch . '|' . $linkUrl));
                }
            }
        });

        return redirect()
            ->route('link-management.index')
            ->with('sweet_success', [
                'title' => 'Link Management Disimpan',
                'text' => 'Link Google Sheet sudah diperbarui.',
            ]);
    }

    private function ensureKpiDefaults(): void
    {
        if (!Schema::hasTable(self::LINK_TABLE)) {
            return;
        }

        foreach (self::KPI_DEFAULTS as $key => $payload) {
            $exists = DB::table(self::LINK_TABLE)
                ->where('group_key', self::KPI_GROUP)
                ->where('link_key', $key)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table(self::LINK_TABLE)->insert([
                'uniqueid_link' => $this->linkId(self::KPI_GROUP, $key),
                'group_key' => self::KPI_GROUP,
                'link_key' => $key,
                'label' => $payload['label'],
                'sheet_name' => $payload['sheet_name'],
                'spreadsheet_id' => $payload['spreadsheet_id'],
                'link_url' => $payload['link_url'],
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }

    private function ensureMarketShareDefaults(): void
    {
        if (!Schema::hasTable(self::LINK_TABLE)) {
            return;
        }

        foreach (self::MARKET_SHARE_DEFAULTS as $key => $payload) {
            $row = DB::table(self::LINK_TABLE)
                ->where('group_key', self::MARKET_SHARE_GROUP)
                ->where('link_key', $key)
                ->first();

            if ($row && $this->isLegacyMarketShareMappingLink((string) ($row->link_url ?? ''))) {
                DB::table(self::LINK_TABLE)
                    ->where('group_key', self::MARKET_SHARE_GROUP)
                    ->where('link_key', $key)
                    ->update([
                        'sheet_name' => $payload['sheet_name'],
                        'spreadsheet_id' => $payload['spreadsheet_id'],
                        'link_url' => $payload['link_url'],
                        'updated_at' => now(),
                    ]);
                continue;
            }

            if ($row) {
                continue;
            }

            DB::table(self::LINK_TABLE)->insert([
                'uniqueid_link' => $this->linkId(self::MARKET_SHARE_GROUP, $key),
                'group_key' => self::MARKET_SHARE_GROUP,
                'link_key' => $key,
                'label' => $payload['label'],
                'sheet_name' => $payload['sheet_name'],
                'spreadsheet_id' => $payload['spreadsheet_id'],
                'link_url' => $payload['link_url'],
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }

    private function kpiLinks(): array
    {
        $rows = Schema::hasTable(self::LINK_TABLE)
            ? DB::table(self::LINK_TABLE)
                ->where('group_key', self::KPI_GROUP)
                ->get()
                ->keyBy('link_key')
            : collect();

        return collect(self::KPI_DEFAULTS)->map(function (array $default, string $key) use ($rows): array {
            $row = $rows->get($key);

            return [
                'key' => $key,
                'label' => $default['label'],
                'sheet_name' => $row->sheet_name ?? $default['sheet_name'],
                'spreadsheet_id' => $row->spreadsheet_id ?? $default['spreadsheet_id'],
                'link_url' => $row->link_url ?? $default['link_url'],
            ];
        })->all();
    }

    private function sppgLink(): array
    {
        $row = Schema::hasTable(self::LINK_TABLE)
            ? DB::table(self::LINK_TABLE)
                ->where('group_key', self::KOLABORASI_GROUP)
                ->where('link_key', self::SPPG_LINK_KEY)
                ->first()
            : null;

        return [
            'label' => 'SPPG',
            'sheet_name' => $row->sheet_name ?? 'Area 6',
            'spreadsheet_id' => $row->spreadsheet_id ?? '',
            'link_url' => $row->link_url ?? '',
        ];
    }

    private function marketShareLinks(): array
    {
        $rows = Schema::hasTable(self::LINK_TABLE)
            ? DB::table(self::LINK_TABLE)
                ->where('group_key', self::MARKET_SHARE_GROUP)
                ->get()
                ->keyBy('link_key')
            : collect();

        return collect(self::MARKET_SHARE_DEFAULTS)->map(function (array $default, string $key) use ($rows): array {
            $row = $rows->get($key);

            return [
                'key' => $key,
                'label' => $default['label'],
                'sheet_name' => $row->sheet_name ?? $default['sheet_name'],
                'spreadsheet_id' => $row->spreadsheet_id ?? $default['spreadsheet_id'],
                'link_url' => $row->link_url ?? $default['link_url'],
            ];
        })->all();
    }

    private function businessClusterLinks(): array
    {
        $rows = Schema::hasTable(self::BUSINESS_CLUSTER_TABLE)
            ? DB::table(self::BUSINESS_CLUSTER_TABLE)->get()->keyBy('nama_kanca')
            : collect();

        return collect(self::BUSINESS_CLUSTER_BRANCHES)->mapWithKeys(function (string $branch) use ($rows): array {
            return [$branch => [
                'label' => $branch,
                'link_url' => $rows->get($branch)->link_url ?? '',
            ]];
        })->all();
    }

    private function linkId(string $group, string $key): string
    {
        return Str::slug($group . '_' . $key, '_');
    }

    private function extractSpreadsheetId(string $url): ?string
    {
        if (preg_match('~docs\.google\.com/spreadsheets/d/([^/]+)~', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function isLegacyMarketShareMappingLink(string $url): bool
    {
        $lowerUrl = strtolower($url);

        return str_contains($url, '1Wlf7Wv5SR8DhtDlRgYwzhAHDSdwIsooa')
            || str_contains($lowerUrl, 'sharepoint.com')
            || !str_contains($lowerUrl, 'docs.google.com/spreadsheets/d/');
    }
}
