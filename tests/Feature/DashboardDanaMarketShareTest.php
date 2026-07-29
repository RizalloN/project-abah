<?php

use App\Models\User;
use App\Http\Controllers\DashboardSimpananController;
use App\Http\Controllers\PublicWorkbookController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Queue;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function (): void {
    useMarketShareWorkbookSqliteConnection();
    Cache::flush();

    config()->set('services.market_share.source_url', 'https://example.com/market-share-source.xlsx');
    config()->set('services.market_share_mapping.source_url', 'https://example.com/market-share-mapping-source.xlsx');
    Http::preventStrayRequests();

    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('pn')->unique();
        $table->string('role')->default('user');
        $table->string('password');
        $table->rememberToken();
        $table->timestamps();
    });
});

afterEach(function (): void {
    useMarketShareWorkbookSqliteConnection();
    Cache::flush();
    Schema::dropIfExists('users');
    Schema::dropIfExists('external_report_links');
    File::delete(storage_path('app/testing-market-share.xlsx'));
    File::delete(storage_path('app/testing-market-share-mapping.xlsx'));
    File::delete(storage_path('app/testing-market-share-mapping-fallback.xlsx'));
});

function useMarketShareWorkbookSqliteConnection(): void
{
    $path = database_path('testing-market-share.sqlite');

    if (!file_exists($path)) {
        touch($path);
    }

    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', $path);
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');
}

function createMarketShareWorkbookFixture(string $path): void
{
    File::ensureDirectoryExists(dirname($path));

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('MS Simpanan Per AH');
    $sheet->fromArray([
        ['No', 'Branch Office', 'Area', 'BRI', null, null, null, null, 'Total Industri', null, null, null, null, 'Industri di Luar BRI', null, null, null, null, 'Market Share'],
        [null, null, null, 45777, 46022, 46113, 'Ytd (Rp)', 'Ytd (%)', 45777, 46022, 46113, 'Ytd (Rp)', 'Ytd %', 45777, 46022, 46113, 'Ytd (Rp)', 'Ytd %', 45777, 46022, 46113, 'YoY', 'Ytd'],
        [22, 'Ngawi', 6, 2542.04, 2669.98, 2635.88, -34.10, -0.0127, 5652.95, 5867.80, 5936.34, 68.54, 0.0116, 3110.91, 3197.82, 3300.46, 102.64, 0.0320, 0.4496, 0.4550, 0.4440, -0.0056, -0.0109],
        [23, 'Magetan', 6, 2607.17, 2686.46, 2710.59, 24.13, 0.0089, 5502.81, 5711.71, 5799.86, 88.14, 0.0154, 2895.63, 3025.25, 3089.27, 64.01, 0.0211, 0.4737, 0.4703, 0.4673, -0.0064, -0.0029],
        [24, 'Madiun', 6, 4028.07, 3975.18, 4149.92, 174.73, 0.0439, 13675.34, 14098.02, 14317.16, 219.13, 0.0155, 9647.26, 10122.84, 10167.24, 44.39, 0.0043, 0.2945, 0.2819, 0.2898, -0.0046, 0.0078],
        [25, 'Ponorogo', 6, 4264.94, 4314.10, 4397.08, 82.98, 0.0192, 9627.26, 9749.35, 10016.66, 267.30, 0.0274, 5362.32, 5435.25, 5619.58, 184.32, 0.0339, 0.4430, 0.4425, 0.4389, -0.0040, -0.0035],
        [null, 'Area Head Madiun', null, 13442.24, 13645.72, 13893.47, 247.75, 0.0181, 34458.38, 35426.90, 36070.03, 643.13, 0.0181, 21016.14, 21781.18, 22176.56, 395.38, 0.0181, 0.3901, 0.3851, 0.3851, -0.0049, 0.0000],
    ]);

    foreach ([16 => 339.93, 25 => 11202.53, 34 => 2351.01, 43 => 11542.46] as $row => $value) {
        $sheet->setCellValue('B' . $row, 'Area Head Madiun');
        $sheet->setCellValue('F' . $row, $value);
        $sheet->setCellValue('K' . $row, $value * 2.5);
        $sheet->setCellValue('P' . $row, $value * 1.5);
        $sheet->setCellValue('U' . $row, 0.4);
        $sheet->setCellValue('V' . $row, 0.01);
        $sheet->setCellValue('W' . $row, 0.02);
    }

    $loanSheet = $spreadsheet->createSheet();
    $loanSheet->setTitle('Series Pinjaman UMKM, Kons AH');
    $loanSheet->setCellValue('D3', 45412);
    $loanSheet->setCellValue('X3', 46022);
    $loanSheet->setCellValue('AB3', 46113);
    $loanSheet->setCellValue('BF3', 46113);
    $loanSheet->setCellValue('BK3', 45412);
    $loanSheet->setCellValue('CE3', 46022);
    $loanSheet->setCellValue('CI3', 46113);

    $writeLoanRow = function (int $row, string $branch, float $bri, float $industry, float $share) use ($loanSheet): void {
        $loanSheet->setCellValue('A' . $row, $branch);
        $loanSheet->setCellValue('D' . $row, $bri - 100);
        $loanSheet->setCellValue('X' . $row, $bri - 40);
        $loanSheet->setCellValue('AB' . $row, $bri);
        $loanSheet->setCellValue('AC' . $row, 100);
        $loanSheet->setCellValue('AE' . $row, 40);
        $loanSheet->setCellValue('BF' . $row, $industry);
        $loanSheet->setCellValue('BK' . $row, $share - 0.01);
        $loanSheet->setCellValue('CE' . $row, $share - 0.005);
        $loanSheet->setCellValue('CI' . $row, $share);
        $loanSheet->setCellValue('CJ' . $row, 0.01);
        $loanSheet->setCellValue('CK' . $row, 0.005);
    };

    $writeLoanRow(4, 'Madiun', 4524.18, 10000.00, 0.4524);
    $writeLoanRow(5, 'Magetan', 3249.18, 7600.00, 0.4275);
    $writeLoanRow(6, 'Ngawi', 3202.78, 7300.00, 0.4387);
    $writeLoanRow(7, 'Ponorogo', 4499.73, 11150.00, 0.4036);
    $writeLoanRow(8, 'Area 6 - Madiun', 15475.87, 36050.00, 0.4293);
    $writeLoanRow(17, 'Area 6 - Madiun', 13156.01, 30000.00, 0.4385);
    $writeLoanRow(26, 'Area 6 - Madiun', 283.81, 1000.00, 0.2838);
    $writeLoanRow(35, 'Area 6 - Madiun', 2036.05, 5050.00, 0.4032);
    $writeLoanRow(43, 'Area 6 - Madiun', 2319.86, 6050.00, 0.3834);

    (new Xlsx($spreadsheet))->save($path);
    $spreadsheet->disconnectWorksheets();
}

function createMarketShareMappingWorkbookFixture(string $path): void
{
    File::ensureDirectoryExists(dirname($path));

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('DASHBOARD');
    $sheet->setCellValue('A1', 'Dashboard Summary');
    $sheet->setCellValue('A2', 'Bukan sheet default halaman mapping');
    $sheet->setCellValue('A4', 'PILIH SEKTOR');
    $sheet->setCellValue('C4', 'Dropdown Sektor');
    $sheet->setCellValue('C5', 'PERDAGANGAN');
    $sheet->setCellValue('A7', 'KPI SEKTOR TERPILIH');
    $sheet->setCellValue('B8', 'Potensi');
    $sheet->setCellValue('B9', 267074);
    $sheet->setCellValue('D8', 'Debitur');
    $sheet->setCellValue('D9', 254842);
    $sheet->setCellValue('F8', 'Konversi');
    $sheet->setCellValue('F9', 0.954);
    $sheet->setCellValue('H8', 'OS Lancar');
    $sheet->setCellValue('H9', 3693981.8);
    $sheet->setCellValue('J8', 'OS NPL');
    $sheet->setCellValue('J9', 165219.4);
    $sheet->setCellValue('L8', 'NPL Ratio');
    $sheet->setCellValue('L9', 0.035);
    $sheet->setCellValue('A12', 'TOTAL POTENSI');
    $sheet->setCellValue('A13', 1745690);
    $sheet->setCellValue('D12', 'TOTAL DEBITUR');
    $sheet->setCellValue('D13', 675906);
    $sheet->setCellValue('G12', 'KONVERSI');
    $sheet->setCellValue('G13', 0.3872);
    $sheet->setCellValue('J12', 'TOTAL OS');
    $sheet->setCellValue('J13', 9762139.1);
    $sheet->setCellValue('A16', 'KARTU PER SEKTOR EKONOMI');
    $sheet->setCellValue('B17', 'PERTANIAN');
    $sheet->setCellValue('A18', 'Konversi');
    $sheet->setCellValue('A19', '22.8%');
    $sheet->setCellValue('E17', 'PERDAGANGAN');
    $sheet->setCellValue('D18', 'Konversi');
    $sheet->setCellValue('D19', '95.4%');
    $sheet->setCellValue('H17', 'PERKEBUNAN');
    $sheet->setCellValue('G18', 'Konversi');
    $sheet->setCellValue('G19', '12.6%');
    $sheet->setCellValue('B22', 'JASA');
    $sheet->setCellValue('A23', 'Konversi');
    $sheet->setCellValue('A24', '54.3%');
    $sheet->fromArray([
        ['Icon', 'Sektor', 'Potensi', 'Debitur', 'Konversi', 'SML', 'NPL', 'Lancar', 'DH', 'Total OS', 'NPL Ratio'],
        ['*', 'PERTANIAN', '1,107,959', '252,670', '22.8%', '74,108.4 M', '36,397.5 M', '1,564,484.1 M', '57,245.2 M', '1,732,235.1 M', '2.1%'],
        ['*', 'PERDAGANGAN', '267,074', '254,842', '95.4%', '325,717.7 M', '165,219.4 M', '3,693,981.8 M', '497,570.9 M', '4,682,489.8 M', '3.5%'],
        ['*', 'PERKEBUNAN', '41,563', '5,238', '12.6%', '5,513.8 M', '2,815.1 M', '155,313.2 M', '12,182.5 M', '175,824.5 M', '1.6%'],
        ['', 'TOTAL 9 SEKTOR', '1,745,690', '675,906', '38.7%', '615,068.9 M', '316,701.5 M', '8,117,213.8 M', '713,154.8 M', '9,762,139.1 M', '3.2%'],
    ], null, 'A43');
    $sheet->mergeCells('A1:C1');
    $sheet->mergeCells('A7:M7');
    $sheet->getColumnDimension('A')->setWidth(18);
    $sheet->getRowDimension(1)->setRowHeight(24);
    $sheet->getStyle('A1:C1')->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FF0F766E');
    $sheet->getStyle('A1:C1')->getFont()
        ->setBold(true)
        ->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle('A7:M7')->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FF0F766E');
    $sheet->getStyle('A7:M7')->getFont()
        ->setBold(true)
        ->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle('B9:L9')->getFont()->setBold(true);
    $sheet->getStyle('A12:M14')->getFont()->setBold(true);
    $sheet->getStyle('A17:J24')->getFont()->setBold(true);

    $rekapSheet = $spreadsheet->createSheet();
    $rekapSheet->setTitle('REKAP');
    $rekapSheet->setCellValue('A2', 'NAMA KANCA');
    $rekapSheet->setCellValue('B2', 'NAMA UKER WILAYAH');
    $rekapSheet->setCellValue('G2', 'SEKTOR PERTANIAN');
    $rekapSheet->setCellValue('Q2', 'TOTAL POTENSI');
    $rekapSheet->setCellValue('R2', 'SEKTOR PERTANIAN');
    $rekapSheet->setCellValue('AB2', 'TOTAL DEB');
    $rekapSheet->setCellValue('AC2', 'SEKTOR PERTANIAN');
    $rekapSheet->setCellValue('AM2', 'SHARE TOTAL');
    $rekapSheet->setCellValue('A3', '00045 -- KC Madiun (Konsolidasi-MB)');
    $rekapSheet->setCellValue('B3', '03212 -- UNIT DOLOPO MADIUN');
    $rekapSheet->setCellValue('G3', 100);
    $rekapSheet->setCellValue('Q3', 100);
    $rekapSheet->setCellValue('R3', 25);
    $rekapSheet->setCellValue('AB3', 25);
    $rekapSheet->setCellValue('AC3', '25%');
    $rekapSheet->setCellValue('AM3', '25%');
    $rekapSheet->setCellValue('B29', 'TOTAL - MADIUN');
    $rekapSheet->setCellValue('Q29', 100);
    $rekapSheet->setCellValue('AB29', 25);
    $rekapSheet->setCellValue('AM29', '25%');

    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle('AREA');
    $sheet->fromArray([
        ['KC', 'PN', 'Nama', 'OS', 'Status'],
        ['KC Madiun', '12345678', 'Dian Febriantari', 17314, 'Sudah'],
        ['KC Magetan', '23456789', 'Indra Hananto', 15368, 'Belum'],
    ]);

    $guidanceSheet = $spreadsheet->createSheet();
    $guidanceSheet->setTitle('GUIDANCE REAL');
    $guidanceSheet->fromArray([
        ['Step', 'Keterangan'],
        ['1', 'Pastikan filter Kanca aktif'],
    ]);

    $mappingSheet = $spreadsheet->createSheet();
    $mappingSheet->setTitle('MAPING');
    $mappingSheet->fromArray([
        ['Produk Mapping Utama', 'Status'],
        ['Simpanan Area 6', 'Aktif'],
    ]);

    (new Xlsx($spreadsheet))->save($path);
    $spreadsheet->disconnectWorksheets();
}

it('renders market share as an office 365 workbook view', function (): void {
    config()->set('services.market_share.title', 'Market Share Test');
    config()->set('services.market_share.public_token', '');
    config()->set('services.market_share.cache_path', 'app/testing-non-existent-market-share.xlsx');
    config()->set('services.market_share.workbook_url', 'https://example.sharepoint.com/personal/test/_layouts/15/doc2.aspx?sourcedoc=%7Babc%7D&action=embedview&wdAllowInteractivity=True&wdAllowTyping=False');

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/report/dashboard-dana/market-share')
        ->assertOk()
        ->assertSee('Market Share')
        ->assertSee('Market Share Test')
        ->assertSee('https://example.sharepoint.com/personal/test/_layouts/15/doc2.aspx?sourcedoc=%7Babc%7D&amp;action=embedview&amp;wdAllowInteractivity=True&amp;wdAllowTyping=False', false)
        ->assertDontSee('Buka Workbook');

    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain('frame-src')
        ->and($csp)->toContain('https://*.sharepoint.com')
        ->and($csp)->toContain('https://example.sharepoint.com');
});

it('renders cached market share as a native dashboard instead of raw excel', function (): void {
    config()->set('services.market_share.title', 'Market Share Test');
    config()->set('services.market_share.public_token', 'market-token');
    config()->set('services.market_share.cache_path', 'app/testing-market-share.xlsx');
    config()->set('services.market_share.source_url', 'https://example.com/workbook.xlsx');
    config()->set('services.market_share.workbook_url', 'https://wrong.example/workbook.xlsx');

    $filePath = storage_path('app/testing-market-share.xlsx');
    createMarketShareWorkbookFixture($filePath);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/report/dashboard-dana/market-share')
        ->assertOk()
        ->assertSee('Market Share')
        ->assertSee('Total Simpanan Area 6')
        ->assertSee('Market Share Simpanan Per Cabang')
        ->assertSee('Rp13,89 T')
        ->assertSee('38,51%')
        ->assertSee('Madiun')
        ->assertSee('Pinjaman')
        ->assertSee('Total Pinjaman Area 6')
        ->assertSee('Market Share Pinjaman Per Cabang')
        ->assertSee('Rp15,48 T')
        ->assertSee('42,93%')
        ->assertDontSee('https://view.officeapps.live.com/op/embed.aspx?src=', false)
        ->assertDontSee('https://wrong.example/workbook.xlsx', false);
});

it('limits the static area market share rows to the signed in branch', function (): void {
    $user = User::factory()->create(['pn' => '0049']);

    $this->actingAs($user)
        ->get(route('report.dashboard-dana.market-share.area6'))
        ->assertOk()
        ->assertSee('Marketshare - KC Magetan')
        ->assertSee('KC Magetan')
        ->assertDontSee('KC Madiun')
        ->assertDontSee('KC Ngawi')
        ->assertDontSee('KC Ponorogo')
        ->assertDontSee('>Area 6<', false);
});

it('renders market share mapping as a separate office 365 workbook view', function (): void {
    config()->set('services.market_share_mapping.title', 'Mapping Test');
    config()->set('services.market_share_mapping.public_token', 'abc');
    config()->set('services.market_share_mapping.cache_path', 'app/testing-non-existent-market-share-mapping.xlsx');
    config()->set('services.market_share_mapping.workbook_url', 'https://wrong-domain.example/workbook.xlsx');

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/report/dashboard-dana/market-share/mapping')
        ->assertOk()
        ->assertSee('Mapping')
        ->assertSee('Mapping Test')
        ->assertSee('https://view.officeapps.live.com/op/embed.aspx?src=', false)
        ->assertSee('workbooks%2Fmarket-share-mapping%2Fabc%2Fmarket-share-mapping.xlsx', false)
        ->assertSee('wdAllowInteractivity=True', false)
        ->assertSee('wdAllowTyping=False', false)
        ->assertSee('wdDownloadButton=True', false)
        ->assertDontSee('https://wrong-domain.example/workbook.xlsx', false)
        ->assertDontSee('https://example.sharepoint.com', false);

    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain('frame-src')
        ->and($csp)->toContain('https://*.officeapps.live.com');
});

it('renders market share mapping through the google sheets workbook UI when configured', function (): void {
    config()->set('services.market_share_mapping.title', 'Mapping Google Test');
    config()->set('services.market_share_mapping.public_token', 'abc');
    config()->set('services.market_share_mapping.cache_path', 'app/testing-non-existent-market-share-mapping.xlsx');
    config()->set(
        'services.market_share_mapping.workbook_url',
        'https://docs.google.com/spreadsheets/d/18RTg3ajn4Lpa2MkXtg8uuiRE7HsmEWbS3EdqO5xrcbY/edit?usp=sharing'
    );

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/report/dashboard-dana/market-share/mapping')
        ->assertOk()
        ->assertSee('Mapping')
        ->assertSee('Mapping Google Test')
        ->assertSee('https://docs.google.com/spreadsheets/d/18RTg3ajn4Lpa2MkXtg8uuiRE7HsmEWbS3EdqO5xrcbY/edit?usp=sharing', false)
        ->assertSee('Workbook Mapping Market Share Google Sheets')
        ->assertDontSee('rm=minimal', false)
        ->assertDontSee('widget=true', false)
        ->assertDontSee('headers=false', false)
        ->assertDontSee('https://view.officeapps.live.com/op/embed.aspx?src=', false)
        ->assertDontSee('workbooks%2Fmarket-share-mapping%2Fabc%2Fmarket-share-mapping.xlsx', false);

    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain('frame-src')
        ->and($csp)->toContain('https://docs.google.com');
});

it('keeps the geography workspace available alongside the configured google sheet', function (): void {
    config()->set('services.market_share_mapping.title', 'Mapping Google Direct Test');
    config()->set('services.market_share_mapping.public_token', 'abc');
    config()->set('services.market_share_mapping.cache_path', 'app/testing-market-share-mapping.xlsx');
    config()->set(
        'services.market_share_mapping.workbook_url',
        'https://docs.google.com/spreadsheets/d/18RTg3ajn4Lpa2MkXtg8uuiRE7HsmEWbS3EdqO5xrcbY/edit?usp=sharing'
    );

    $filePath = storage_path('app/testing-market-share-mapping.xlsx');
    createMarketShareMappingWorkbookFixture($filePath);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/report/dashboard-dana/market-share/mapping')
        ->assertOk()
        ->assertSee('Mapping Google Direct Test')
        ->assertSee('Peta Wilayah')
        ->assertSee('marketShareGeographyMap', false)
        ->assertSee('Potensi Nasabah')
        ->assertSee('Penetrasi Nasabah')
        ->assertSee('market-geo-tooltip__metrics', false)
        ->assertSee('colorForPenetration', false)
        ->assertDontSee('data-market-geo-metric', false)
        ->assertDontSee('data-market-geo-sector', false)
        ->assertSee('Workbook Mapping Market Share Google Sheets')
        ->assertSee('https://docs.google.com/spreadsheets/d/18RTg3ajn4Lpa2MkXtg8uuiRE7HsmEWbS3EdqO5xrcbY/edit?usp=sharing', false)
        ->assertDontSee('rm=minimal', false)
        ->assertDontSee('widget=true', false)
        ->assertDontSee('headers=false', false)
        ->assertSee('Summary')
        ->assertSee('Google Spreadsheet')
        ->assertDontSee('| Cache ', false)
        ->assertDontSee('Cache 19')
        ->assertDontSee('https://view.officeapps.live.com/op/embed.aspx?src=', false);
});

it('ignores legacy managed sharepoint mapping links and uses the configured google sheet', function (): void {
    Schema::create('external_report_links', function (Blueprint $table): void {
        $table->string('uniqueid_link', 120)->primary();
        $table->string('group_key', 80);
        $table->string('link_key', 100);
        $table->string('label', 160);
        $table->string('sheet_name', 160)->nullable();
        $table->string('spreadsheet_id', 160)->nullable();
        $table->text('link_url');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->unique(['group_key', 'link_key'], 'uq_external_report_links_scope');
    });

    DB::table('external_report_links')->insert([
        'uniqueid_link' => 'market_share_mapping',
        'group_key' => 'market_share',
        'link_key' => 'mapping',
        'label' => 'Mapping Market Share',
        'sheet_name' => 'DASHBOARD',
        'spreadsheet_id' => 'old-sharepoint',
        'link_url' => 'https://lin20912662-my.sharepoint.com/:x:/g/personal/rizallon_officeoriku_com/IQAIGE-zAu8USKWHKx7iL4nXAQAKpSprz5FQWYWMldddDPs?e=GRPhfF',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    config()->set('services.market_share_mapping.title', 'Mapping Google Test');
    config()->set('services.market_share_mapping.workbook_url', 'https://docs.google.com/spreadsheets/d/18RTg3ajn4Lpa2MkXtg8uuiRE7HsmEWbS3EdqO5xrcbY/edit?usp=sharing');
    config()->set('services.market_share_mapping.cache_path', 'app/testing-non-existent-market-share-mapping.xlsx');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/report/dashboard-dana/market-share/mapping')
        ->assertOk()
        ->assertSee('Mapping Google Test')
        ->assertSee('Workbook Mapping Market Share Google Sheets')
        ->assertSee('https://docs.google.com/spreadsheets/d/18RTg3ajn4Lpa2MkXtg8uuiRE7HsmEWbS3EdqO5xrcbY/edit?usp=sharing', false)
        ->assertDontSee('Workbook Mapping Market Share Office 365')
        ->assertDontSee('lin20912662-my.sharepoint.com', false)
        ->assertDontSee('https://view.officeapps.live.com/op/embed.aspx?src=', false);
});

it('prioritizes the public mapping workbook endpoint over a configured sharepoint embed link', function (): void {
    config()->set('services.market_share_mapping.title', 'Mapping Test');
    config()->set('services.market_share_mapping.public_token', 'abc');
    config()->set('services.market_share_mapping.cache_path', 'app/testing-non-existent-market-share-mapping.xlsx');
    config()->set(
        'services.market_share_mapping.workbook_url',
        '<iframe src="https://lin20912662-my.sharepoint.com/personal/rizallon_officeoriku_com/_layouts/15/Doc.aspx?sourcedoc={b34f1808-ef02-4814-a587-2b1ee22f89d7}&action=embedview&wdHideGridlines=True&wdHideHeaders=True&wdDownloadButton=True"></iframe>'
    );

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/report/dashboard-dana/market-share/mapping')
        ->assertOk()
        ->assertSee('Mapping')
        ->assertSee('Mapping Test')
        ->assertSee('https://view.officeapps.live.com/op/embed.aspx?src=', false)
        ->assertSee('workbooks%2Fmarket-share-mapping%2Fabc%2Fmarket-share-mapping.xlsx', false)
        ->assertSee('wdAllowInteractivity=True', false)
        ->assertSee('wdAllowTyping=False', false)
        ->assertSee('wdDownloadButton=True', false)
        ->assertDontSee('https://lin20912662-my.sharepoint.com/personal/rizallon_officeoriku_com/_layouts/15/Doc.aspx', false);

    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain('frame-src')
        ->and($csp)->toContain('https://*.officeapps.live.com');
});

it('keeps sharepoint guest mapping sources behind the public workbook endpoint when a token exists', function (): void {
    config()->set('services.market_share_mapping.title', 'Mapping Guest Source Test');
    config()->set('services.market_share_mapping.public_token', 'abc');
    config()->set('services.market_share_mapping.cache_path', 'app/testing-non-existent-market-share-mapping.xlsx');
    config()->set(
        'services.market_share_mapping.source_url',
        'https://lin20912662-my.sharepoint.com/:x:/g/personal/rizallon_officeoriku_com/IQAIGE-zAu8USKWHKx7iL4nXAQAKpSprz5FQWYWMldddDPs?e=GRPhfF&download=1'
    );
    config()->set(
        'services.market_share_mapping.workbook_url',
        'https://lin20912662-my.sharepoint.com/:x:/g/personal/rizallon_officeoriku_com/IQAIGE-zAu8USKWHKx7iL4nXAQAKpSprz5FQWYWMldddDPs?e=GRPhfF'
    );

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/report/dashboard-dana/market-share/mapping')
        ->assertOk()
        ->assertSee('Mapping')
        ->assertSee('Mapping Guest Source Test')
        ->assertSee('https://view.officeapps.live.com/op/embed.aspx?src=', false)
        ->assertSee('workbooks%2Fmarket-share-mapping%2Fabc%2Fmarket-share-mapping.xlsx', false)
        ->assertDontSee('lin20912662-my.sharepoint.com/:x:/g/personal/rizallon_officeoriku_com', false);
});

it('keeps mapping on the public workbook endpoint when the cached workbook is below the office viewer limit', function (): void {
    config()->set('services.market_share_mapping.title', 'Mapping Test Large');
    config()->set('services.market_share_mapping.public_token', 'abc');
    config()->set('services.market_share_mapping.cache_path', 'app/testing-market-share-mapping.xlsx');
    config()->set(
        'services.market_share_mapping.workbook_url',
        '<iframe src="https://lin20912662-my.sharepoint.com/personal/rizallon_officeoriku_com/_layouts/15/Doc.aspx?sourcedoc={b34f1808-ef02-4814-a587-2b1ee22f89d7}&action=embedview&wdHideGridlines=True&wdHideHeaders=True&wdDownloadButton=True"></iframe>'
    );

    $filePath = storage_path('app/testing-market-share-mapping.xlsx');
    File::ensureDirectoryExists(dirname($filePath));
    File::put($filePath, "PK\x03\x04" . str_repeat('0', 9.2 * 1024 * 1024));

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/report/dashboard-dana/market-share/mapping');

    $response->assertOk()
        ->assertSee('Mapping')
        ->assertSee('Mapping Test Large')
        ->assertSee('https://view.officeapps.live.com/op/embed.aspx?src=', false)
        ->assertSee('workbooks%2Fmarket-share-mapping%2Fabc%2Fmarket-share-mapping.xlsx', false)
        ->assertDontSee('Workbook Tidak Dapat Ditampilkan di Browser')
        ->assertDontSee('https://lin20912662-my.sharepoint.com/personal/rizallon_officeoriku_com/_layouts/15/Doc.aspx', false);
});

it('does not send oversized mapping workbooks to the office viewer iframe', function (): void {
    config()->set('services.market_share_mapping.title', 'Mapping Oversized Test');
    config()->set('services.market_share_mapping.public_token', 'abc');
    config()->set('services.market_share_mapping.cache_path', 'app/testing-market-share-mapping.xlsx');
    config()->set('services.market_share_mapping.workbook_url', 'https://wrong-domain.example/workbook.xlsx');

    $filePath = storage_path('app/testing-market-share-mapping.xlsx');
    File::ensureDirectoryExists(dirname($filePath));
    File::put($filePath, "PK\x03\x04" . str_repeat('0', 26 * 1024 * 1024));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/report/dashboard-dana/market-share/mapping')
        ->assertOk()
        ->assertSee('Mapping Oversized Test')
        ->assertSee('Workbook Tidak Dapat Ditampilkan di Browser')
        ->assertDontSee('https://view.officeapps.live.com/op/embed.aspx?src=', false)
        ->assertDontSee('https://wrong-domain.example/workbook.xlsx', false);
});

it('uses the direct sharepoint embed for oversized mapping workbooks when a guest link is configured', function (): void {
    config()->set('services.market_share_mapping.title', 'Mapping Oversized SharePoint Test');
    config()->set('services.market_share_mapping.public_token', 'abc');
    config()->set('services.market_share_mapping.cache_path', 'app/testing-market-share-mapping.xlsx');
    config()->set(
        'services.market_share_mapping.workbook_url',
        'https://lin20912662-my.sharepoint.com/:x:/g/personal/rizallon_officeoriku_com/IQAIGE-zAu8USKWHKx7iL4nXAQAKpSprz5FQWYWMldddDPs?e=GRPhfF'
    );

    $filePath = storage_path('app/testing-market-share-mapping.xlsx');
    createMarketShareMappingWorkbookFixture($filePath);
    $zip = new ZipArchive();
    $zip->open($filePath);
    $zip->addFromString('xl/media/oversized-filler.bin', str_repeat('0', 26 * 1024 * 1024));
    $zip->setCompressionName('xl/media/oversized-filler.bin', ZipArchive::CM_STORE);
    $zip->close();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/report/dashboard-dana/market-share/mapping')
        ->assertOk()
        ->assertSee('Mapping Oversized SharePoint Test')
        ->assertSee('lin20912662-my.sharepoint.com/personal/rizallon_officeoriku_com/_layouts/15/Doc.aspx', false)
        ->assertSee('sourcedoc=%7Bb34f1808-ef02-4814-a587-2b1ee22f89d7%7D', false)
        ->assertSee('wdAllowInteractivity=True', false)
        ->assertSee('wdAllowTyping=True', false)
        ->assertSee('wdHideSheetTabs=False', false)
        ->assertSee('ActiveCell=%27DASHBOARD%27%21A1', false)
        ->assertSee('market-office-sheet-tabs', false)
        ->assertSee('data-market-office-sheet-url', false)
        ->assertSee('DASHBOARD')
        ->assertSee('AREA')
        ->assertSee('MAPING')
        ->assertDontSee('https://view.officeapps.live.com/op/embed.aspx?src=', false)
        ->assertDontSee('Unduh Workbook')
        ->assertDontSee('Workbook Melebihi Batas Excel Online');
});

it('keeps oversized mapping workbooks browsable through the native renderer', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->view('report.dashboard-dana-market-share', [
            'pageTitle' => 'Mapping',
            'pageIcon' => 'fas fa-map-marked-alt',
            'workbookTitle' => 'Mapping Oversized Native Test',
            'workbookUrl' => 'https://view.officeapps.live.com/op/embed.aspx?src=https%3A%2F%2Fexample.test%2Fworkbook.xlsx',
            'workbookUrlIsComplete' => true,
            'emptyMessage' => 'Workbook Mapping belum dikonfigurasi.',
            'warningMessage' => 'Link mapping belum lengkap.',
            'frameTitle' => 'Workbook Mapping Market Share Google Sheets',
            'downloadUrl' => '/workbooks/market-share-mapping/token/market-share-mapping.xlsx',
            'showDownloadPanel' => true,
            'excelWorkbookUrl' => 'https://view.officeapps.live.com/op/embed.aspx?src=https%3A%2F%2Fexample.test%2Fworkbook.xlsx',
            'nativeWorkbook' => [
                'ready' => true,
                'updatedAt' => '19 Jun 2026 07:09',
                'sheetNames' => ['DASHBOARD', 'AREA', 'MAPING'],
                'selectedSheet' => 'DASHBOARD',
                'columnLabels' => ['A', 'B', 'C'],
                'columnWidths' => [
                    'width:120px;min-width:120px;',
                    'width:110px;min-width:110px;',
                    'width:110px;min-width:110px;',
                ],
                'rowCount' => 59,
                'columnCount' => 16,
                'maxRows' => 500,
                'maxColumns' => 90,
                'truncated' => false,
                'rows' => [
                    [
                        'number' => 1,
                        'style' => '',
                        'cells' => [
                            ['value' => 'Dashboard Summary', 'style' => 'background-color:#0f766e;color:#ffffff;font-weight:700;', 'rowspan' => 1, 'colspan' => 3, 'empty' => false],
                        ],
                    ],
                    [
                        'number' => 2,
                        'style' => '',
                        'cells' => [
                            ['value' => 'PERDAGANGAN', 'style' => '', 'rowspan' => 1, 'colspan' => 1, 'empty' => false],
                            ['value' => '95.4%', 'style' => '', 'rowspan' => 1, 'colspan' => 1, 'empty' => false],
                            ['value' => '', 'style' => '', 'rowspan' => 1, 'colspan' => 1, 'empty' => true],
                        ],
                    ],
                ],
                'summary' => [
                    'ready' => true,
                    'title' => 'Dashboard Summary',
                    'subtitle' => 'Ringkasan mapping',
                    'selectedSector' => 'PERDAGANGAN',
                    'totalMetrics' => [],
                    'sectors' => [],
                    'charts' => [],
                ],
            ],
        ])
        ->assertSee('Summary')
        ->assertSee('Google Sheet')
        ->assertSee('Mode native dashboard; sheet dan filter tetap bisa digunakan tanpa membuka Google Sheets.')
        ->assertSee('Filter baris workbook')
        ->assertSee('<table id="marketNativeWorkbookTable" class="market-excel-table">', false)
        ->assertSee('Dashboard Summary')
        ->assertSee('PERDAGANGAN')
        ->assertSee('95.4%')
        ->assertDontSee('Workbook Melebihi Batas Excel Online')
        ->assertDontSee('Unduh Workbook')
        ->assertDontSee('<iframe', false)
        ->assertDontSee('https://view.officeapps.live.com/op/embed.aspx?src=', false);
});

it('keeps market share workbook panes tall on tablet and phone screens', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->view('report.dashboard-dana-market-share', [
            'pageTitle' => 'Market Share',
            'pageIcon' => 'fas fa-chart-pie',
            'workbookTitle' => 'Market Share Responsive Test',
            'workbookUrl' => 'https://view.officeapps.live.com/op/embed.aspx?src=https%3A%2F%2Fexample.test%2Fworkbook.xlsx',
            'workbookUrlIsComplete' => true,
            'emptyMessage' => 'Workbook Market Share belum dikonfigurasi.',
            'warningMessage' => 'Link market share belum lengkap.',
            'frameTitle' => 'Workbook Market Share Office 365',
            'downloadUrl' => '/workbooks/market-share.xlsx',
            'showDownloadPanel' => false,
            'nativeWorkbook' => ['ready' => false],
            'nativeMarketShare' => ['ready' => false],
        ])
        ->assertSee('@media (max-width: 1024px)', false)
        ->assertSee('height: max(760px, calc(100svh - 132px));', false)
        ->assertSee('height: max(760px, calc(100dvh - 132px));', false)
        ->assertSee('@media (max-width: 768px)', false)
        ->assertSee('height: max(700px, calc(100svh - 116px));', false)
        ->assertSee('height: max(700px, calc(100dvh - 116px));', false)
        ->assertSee('min-height: 500px;', false);
});

it('renders valid cached market share mapping workbook as a summary with excel mode', function (): void {
    config()->set('services.market_share_mapping.title', 'Mapping Native Test');
    config()->set('services.market_share_mapping.public_token', 'abc');
    config()->set('services.market_share_mapping.cache_path', 'app/testing-market-share-mapping.xlsx');
    config()->set('services.market_share_mapping.workbook_url', 'https://wrong-domain.example/workbook.xlsx');

    $filePath = storage_path('app/testing-market-share-mapping.xlsx');
    createMarketShareMappingWorkbookFixture($filePath);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/report/dashboard-dana/market-share/mapping')
        ->assertOk()
        ->assertSee('Mapping')
        ->assertSee('Mapping Native Test')
        ->assertSee('Peta Wilayah')
        ->assertSee('Peta Potensi &amp; Penetrasi Area 6', false)
        ->assertSee('marketShareGeographyMap', false)
        ->assertSee('03212 - DOLOPO')
        ->assertSee('marketshare-area6-kecamatan.geojson', false)
        ->assertSee('"type":"FeatureCollection"', false)
        ->assertSee('preferCanvas: false', false)
        ->assertSee('window.L.svg', false)
        ->assertSee('Summary')
        ->assertSee('Excel')
        ->assertSee('market-mapping-summary', false)
        ->assertSee('market-workbook-frame', false)
        ->assertSee('Google Spreadsheet')
        ->assertSee('Gunakan dropdown, filter, dan sheet asli dari Google Sheets.')
        ->assertDontSee('Gunakan dropdown, filter, dan sheet asli dari Excel 365.')
        ->assertSee('https://view.officeapps.live.com/op/embed.aspx?src=', false)
        ->assertDontSee('Filter baris workbook')
        ->assertDontSee('<table class="market-excel-table"', false)
        ->assertDontSee('https://wrong-domain.example/workbook.xlsx', false);
});

it('renders the local geography workspace without requiring a public workbook token', function (): void {
    config()->set('services.market_share_mapping.title', 'Mapping Local Geography Test');
    config()->set('services.market_share_mapping.public_token', '');
    config()->set('services.market_share_mapping.cache_path', 'app/testing-market-share-mapping.xlsx');
    config()->set('services.market_share_mapping.workbook_url', '');

    createMarketShareMappingWorkbookFixture(storage_path('app/testing-market-share-mapping.xlsx'));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/report/dashboard-dana/market-share/mapping')
        ->assertOk()
        ->assertSee('Mapping Local Geography Test')
        ->assertSee('Peta Wilayah')
        ->assertSee('marketShareGeographyMap', false)
        ->assertSee('03212 - DOLOPO')
        ->assertSee('Summary');
});

it('refreshes the mapping geography workbook from the configured Google Sheet export', function (): void {
    $filePath = storage_path('app/testing-market-share-mapping.xlsx');
    createMarketShareMappingWorkbookFixture($filePath);
    $workbookBody = File::get($filePath);
    File::delete($filePath);

    config()->set('services.market_share_mapping.cache_path', 'app/testing-market-share-mapping.xlsx');
    config()->set('services.market_share_mapping.cache_minutes', 5);
    config()->set(
        'services.market_share_mapping.source_url',
        'https://docs.google.com/spreadsheets/d/1aepYbSA8RAFU7RFUh4vOQ-Rp7xALY9q87uXgn6aVYSE/edit?usp=sharing'
    );
    config()->set(
        'services.market_share_mapping.workbook_url',
        'https://docs.google.com/spreadsheets/d/1aepYbSA8RAFU7RFUh4vOQ-Rp7xALY9q87uXgn6aVYSE/edit?usp=sharing'
    );
    Http::fake([
        'https://docs.google.com/spreadsheets/d/1aepYbSA8RAFU7RFUh4vOQ-Rp7xALY9q87uXgn6aVYSE/export?format=xlsx' => Http::response(
            $workbookBody,
            200,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        ),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/report/dashboard-dana/market-share/mapping')
        ->assertOk()
        ->assertSee('Peta Potensi &amp; Penetrasi Area 6', false)
        ->assertSee('03212 - DOLOPO');

    expect(File::exists($filePath))->toBeTrue();
    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://docs.google.com/spreadsheets/d/1aepYbSA8RAFU7RFUh4vOQ-Rp7xALY9q87uXgn6aVYSE/export?format=xlsx';
    });
});

it('uses the local mapping workbook fallback when the Google Sheet cannot be reached', function (): void {
    Queue::fake();
    $fallbackPath = storage_path('app/testing-market-share-mapping-fallback.xlsx');
    createMarketShareMappingWorkbookFixture($fallbackPath);

    config()->set('services.market_share_mapping.cache_path', 'app/testing-market-share-mapping.xlsx');
    config()->set('services.market_share_mapping.fallback_cache_path', 'app/testing-market-share-mapping-fallback.xlsx');
    config()->set(
        'services.market_share_mapping.source_url',
        'https://docs.google.com/spreadsheets/d/1aepYbSA8RAFU7RFUh4vOQ-Rp7xALY9q87uXgn6aVYSE/edit?usp=sharing'
    );
    Http::fake([
        'https://docs.google.com/spreadsheets/d/1aepYbSA8RAFU7RFUh4vOQ-Rp7xALY9q87uXgn6aVYSE/export?format=xlsx' => Http::response('', 503),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/report/dashboard-dana/market-share/mapping')
        ->assertOk()
        ->assertSee('Peta Potensi &amp; Penetrasi Area 6', false)
        ->assertSee('03212 - DOLOPO');

    expect(File::exists(storage_path('app/testing-market-share-mapping.xlsx')))->toBeFalse();
    Http::assertNothingSent();
});

it('uses the geography workspace by default while retaining the configured google sheet', function (): void {
    config()->set('services.market_share_mapping.title', 'Mapping Google Default Test');
    config()->set('services.market_share_mapping.public_token', 'abc');
    config()->set('services.market_share_mapping.cache_path', 'app/testing-market-share-mapping.xlsx');
    config()->set('services.market_share_mapping.workbook_url', 'https://docs.google.com/spreadsheets/d/18RTg3ajn4Lpa2MkXtg8uuiRE7HsmEWbS3EdqO5xrcbY/edit?usp=sharing');

    $filePath = storage_path('app/testing-market-share-mapping.xlsx');
    createMarketShareMappingWorkbookFixture($filePath);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/report/dashboard-dana/market-share/mapping')
        ->assertOk()
        ->assertSee('Mapping Google Default Test')
        ->assertSee('Peta Wilayah')
        ->assertSee('marketShareGeographyMap', false)
        ->assertSee('Workbook Mapping Market Share Google Sheets')
        ->assertSee('https://docs.google.com/spreadsheets/d/18RTg3ajn4Lpa2MkXtg8uuiRE7HsmEWbS3EdqO5xrcbY/edit?usp=sharing', false)
        ->assertSee('market-workbook-frame', false)
        ->assertSee('Dashboard Summary')
        ->assertSee('data-market-workbook-mode-panel="excel"', false)
        ->assertDontSee('Gunakan dropdown, filter, dan sheet asli dari Excel 365.')
        ->assertDontSee('https://view.officeapps.live.com/op/embed.aspx?src=', false);
});


it('serves market share workbook through a protected public token endpoint', function (): void {
    Queue::fake();
    $sourceUrl = 'https://example.com/market-share.xlsx';
    $token = 'market-token-test';
    $path = storage_path('app/testing-market-share.xlsx');

    File::delete($path);

    config()->set('services.market_share.source_url', $sourceUrl);
    config()->set('services.market_share.public_token', $token);
    config()->set('services.market_share.cache_path', 'app/testing-market-share.xlsx');
    config()->set('services.market_share.cache_minutes', 0);

    Http::fake([
        $sourceUrl => Http::response(
            "PK\x03\x04" . str_repeat('0', 2048),
            200,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        ),
    ]);

    (new PublicWorkbookController())->refreshMarketShareSource();

    $this->get('/workbooks/market-share.xlsx?token=wrong')
        ->assertNotFound();

    $this->get('/workbooks/market-share.xlsx?token=' . $token)
        ->assertOk()
        ->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin')
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertHeaderMissing('X-Frame-Options');

    Http::assertSentCount(1);
    expect(File::exists($path))->toBeTrue();
});

it('serves market share mapping workbook through a protected public token endpoint', function (): void {
    $sourceUrl = 'https://example.com/market-share-mapping.xlsx';
    $token = 'mapping-token-test';
    $path = storage_path('app/testing-market-share-mapping.xlsx');

    File::delete($path);

    config()->set('services.market_share_mapping.source_url', $sourceUrl);
    config()->set('services.market_share_mapping.public_token', $token);
    config()->set('services.market_share_mapping.cache_path', 'app/testing-market-share-mapping.xlsx');
    config()->set('services.market_share_mapping.cache_minutes', 0);

    Http::fake([
        $sourceUrl => Http::response(
            "PK\x03\x04" . str_repeat('0', 2048),
            200,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        ),
    ]);

    $this->get('/workbooks/market-share-mapping.xlsx?token=wrong')
        ->assertNotFound();

    $this->get('/workbooks/market-share-mapping.xlsx?token=' . $token)
        ->assertOk()
        ->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin')
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertHeaderMissing('X-Frame-Options');

    Http::assertSentCount(1);
    expect(File::exists($path))->toBeTrue();
});

it('serves market share mapping workbook through a path token endpoint for office viewer', function (): void {
    $sourceUrl = 'https://example.com/market-share-mapping.xlsx';
    $token = 'mapping-token-test';
    $path = storage_path('app/testing-market-share-mapping.xlsx');

    File::delete($path);

    config()->set('services.market_share_mapping.source_url', $sourceUrl);
    config()->set('services.market_share_mapping.public_token', $token);
    config()->set('services.market_share_mapping.cache_path', 'app/testing-market-share-mapping.xlsx');
    config()->set('services.market_share_mapping.cache_minutes', 0);

    Http::fake([
        $sourceUrl => Http::response(
            "PK\x03\x04" . str_repeat('0', 2048),
            200,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        ),
    ]);

    $this->get('/workbooks/market-share-mapping/wrong/market-share-mapping.xlsx')
        ->assertNotFound();

    $this->get('/workbooks/market-share-mapping/' . $token . '/market-share-mapping.xlsx')
        ->assertOk()
        ->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin')
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertHeaderMissing('X-Frame-Options');

    Http::assertSentCount(1);
    expect(File::exists($path))->toBeTrue();
});

it('normalizes pasted office iframe sources before fetching mapping workbook', function (): void {
    $token = 'mapping-token-test';
    $path = storage_path('app/testing-market-share-mapping.xlsx');
    $requestedUrl = null;

    File::delete($path);

    config()->set(
        'services.market_share_mapping.source_url',
        '<iframe src="https://lin20912662-my.sharepoint.com/personal/rizallon_officeoriku_com/_layouts/15/Doc.aspx?sourcedoc={abc-123}&action=embedview&wdHideGridlines=True&wdAllowInteractivity=True"></iframe>'
    );
    config()->set('services.market_share_mapping.public_token', $token);
    config()->set('services.market_share_mapping.cache_path', 'app/testing-market-share-mapping.xlsx');
    config()->set('services.market_share_mapping.cache_minutes', 0);

    Http::fake(function ($request) use (&$requestedUrl) {
        $requestedUrl = $request->url();

        return Http::response(
            "PK\x03\x04" . str_repeat('0', 2048),
            200,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    });

    (new DashboardSimpananController())->refreshMarketShareMappingSource();

    $this->get('/workbooks/market-share-mapping.xlsx?token=' . $token)
        ->assertOk();

    expect($requestedUrl)
        ->toContain('sourcedoc=%7Babc-123%7D')
        ->toContain('action=default')
        ->toContain('download=1')
        ->not->toContain('embedview')
        ->not->toContain('wdHideGridlines')
        ->not->toContain('wdAllowInteractivity');
});

it('bypasses the office viewer proxy and embeds sharepoint directly if source url is a guest link', function (): void {
    config()->set('services.market_share_mapping.title', 'Mapping Bypass Test');
    config()->set('services.market_share_mapping.public_token', '');
    config()->set('services.market_share_mapping.cache_path', 'app/testing-non-existent-market-share-mapping.xlsx');
    config()->set('services.market_share_mapping.source_url', 'https://lin20912662-my.sharepoint.com/:x:/g/personal/rizallon_officeoriku_com/IQAIGE-zAu8USKWHKx7iL4nXAQAKpSprz5FQWYWMldddDPs?download=1');
    config()->set(
        'services.market_share_mapping.workbook_url',
        '<iframe src="https://lin20912662-my.sharepoint.com/:x:/g/personal/rizallon_officeoriku_com/IQAIGE-zAu8USKWHKx7iL4nXAQAKpSprz5FQWYWMldddDPs?e=CLvMhN"></iframe>'
    );

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/report/dashboard-dana/market-share/mapping');

    $response->assertOk()
        ->assertSee('Mapping')
        ->assertSee('Mapping Bypass Test')
        ->assertDontSee('https://view.officeapps.live.com/op/embed.aspx?src=', false)
        ->assertSee('https://lin20912662-my.sharepoint.com/personal/rizallon_officeoriku_com/_layouts/15/Doc.aspx', false)
        ->assertSee('sourcedoc=%7Bb34f1808-ef02-4814-a587-2b1ee22f89d7%7D', false)
        ->assertSee('wdAllowInteractivity=True', false)
        ->assertSee('wdAllowTyping=True', false)
        ->assertSee('wdHideSheetTabs=False', false);
});

it('keeps guest sharepoint links behind the public workbook endpoint when a public token exists', function (): void {
    config()->set('services.market_share.title', 'Market Share Public Endpoint Test');
    config()->set('services.market_share.public_token', 'market-token');
    config()->set('services.market_share.cache_path', 'app/testing-non-existent-market-share.xlsx');
    config()->set('services.market_share.source_url', 'https://example.com/workbook.xlsx');
    config()->set('services.market_share.workbook_url', 'https://wrong.example/workbook.xlsx');

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/report/dashboard-dana/market-share');

    $response->assertOk()
        ->assertSee('Market Share')
        ->assertSee('Market Share Public Endpoint Test')
        ->assertSee('https://view.officeapps.live.com/op/embed.aspx?src=', false)
        ->assertSee('workbooks%2Fmarket-share%2Fmarket-token%2Fmarket-share.xlsx', false)
        ->assertDontSee('https://wrong.example/workbook.xlsx', false)
        ->assertDontSee('lin20912662-my.sharepoint.com/:x:/g/personal/rizallon_officeoriku_com', false);
});
