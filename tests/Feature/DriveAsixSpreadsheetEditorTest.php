<?php

use App\Exceptions\DriveAsixWorkbookException;
use App\Models\DriveAsixFile;
use App\Models\DriveAsixFolder;
use App\Models\User;
use App\Services\DriveAsix\SpreadsheetWorkbookService;
use App\Support\SpreadsheetFileFormatDetector;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function (): void {
    Config::set('database.default', 'sqlite');
    Config::set('database.connections.sqlite.database', ':memory:');
    Config::set('cache.default', 'array');
    Config::set('services.onlyoffice.enabled', false);

    DB::purge('sqlite');
    DB::reconnect('sqlite');
    Schema::dropAllTables();

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('pn', 20)->unique();
        $table->string('password');
        $table->string('role', 20)->default('user');
        $table->string('branch_scope', 20)->default('area');
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('drive_asix_folders', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->string('name');
        $table->unsignedBigInteger('created_by')->nullable();
        $table->timestamps();
    });

    Schema::create('drive_asix_files', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('folder_id')->nullable();
        $table->string('original_name');
        $table->string('stored_name');
        $table->string('mime_type')->nullable();
        $table->unsignedBigInteger('size_bytes')->default(0);
        $table->unsignedBigInteger('uploaded_by')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Storage::fake('local');
});

afterEach(function (): void {
    Schema::dropAllTables();
});

function driveAsixTestUser(string $role = 'user'): User
{
    static $sequence = 0;
    $sequence++;

    return User::query()->create([
        'name' => $role === 'admin' ? 'Admin DriveASIX' : 'Pengguna DriveASIX',
        'pn' => sprintf('98%06d', $sequence),
        'password' => 'password',
        'role' => $role,
        'branch_scope' => 'area',
    ]);
}

function driveAsixWorkbookBinary(string $format = 'xlsx'): string
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Ringkasan');
    $sheet->fromArray([
        ['Cabang', 'Nilai', 'Total'],
        ['Madiun', 125, null],
        ['Ngawi', 75, null],
    ]);
    $sheet->setCellValue('C1', '=SUM(B2:B3)');
    $sheet->getStyle('A1:C1')->getFont()->setBold(true);
    $sheet->getStyle('A1:C1')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()
        ->setARGB('FFD9EAF7');
    $sheet->mergeCells('D1:E1');
    $sheet->setCellValue('D1', 'Catatan');
    $sheet->freezePane('A2');
    $sheet->setAutoFilter('A1:C3');

    $detail = $spreadsheet->createSheet();
    $detail->setTitle('Detail');
    $detail->setCellValue('A1', 'Nomor Rekening');
    $spreadsheet->setActiveSheetIndex(0);

    $temporaryPath = tempnam(sys_get_temp_dir(), 'drive-asix-test-');
    if ($temporaryPath === false) {
        throw new RuntimeException('Tidak dapat membuat file workbook sementara untuk test.');
    }

    try {
        $writer = $format === 'xls' ? new Xls($spreadsheet) : new Xlsx($spreadsheet);
        $writer->save($temporaryPath);
        $binary = file_get_contents($temporaryPath);
        if ($binary === false) {
            throw new RuntimeException('Tidak dapat membaca workbook sementara untuk test.');
        }

        return $binary;
    } finally {
        $spreadsheet->disconnectWorksheets();
        @unlink($temporaryPath);
    }
}

/**
 * @param  array<string, string>  $entries
 */
function driveAsixZipBinary(array $entries): string
{
    $temporaryPath = tempnam(sys_get_temp_dir(), 'drive-asix-zip-');
    if ($temporaryPath === false) {
        throw new RuntimeException('Tidak dapat membuat arsip sementara untuk test.');
    }

    $archive = new ZipArchive;
    $archiveOpen = false;
    try {
        if ($archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak dapat membuat arsip ZIP untuk test.');
        }
        $archiveOpen = true;
        foreach ($entries as $name => $contents) {
            $archive->addFromString($name, $contents);
        }
        $archive->close();
        $archiveOpen = false;

        $binary = file_get_contents($temporaryPath);
        if ($binary === false) {
            throw new RuntimeException('Tidak dapat membaca arsip ZIP sementara.');
        }

        return $binary;
    } finally {
        if ($archiveOpen) {
            $archive->close();
        }
        @unlink($temporaryPath);
    }
}

function driveAsixStoredWorkbook(?User $uploader = null): DriveAsixFile
{
    $storedName = 'fixture-'.bin2hex(random_bytes(5)).'.xlsx';
    $path = 'drive_asix/'.$storedName;
    $binary = driveAsixWorkbookBinary();

    Storage::disk('local')->put($path, $binary);

    return DriveAsixFile::query()->create([
        'folder_id' => null,
        'original_name' => 'Rekap Cabang.xlsx',
        'stored_name' => $storedName,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'size_bytes' => strlen($binary),
        'uploaded_by' => $uploader?->getKey(),
    ]);
}

it('mengenali mode buka lokal berdasarkan jenis file tanpa bergantung pada MIME browser', function (): void {
    $spreadsheet = new DriveAsixFile([
        'original_name' => 'Target Harian.XLSX',
        'mime_type' => 'application/octet-stream',
        'size_bytes' => 1536,
    ]);
    $pdf = new DriveAsixFile(['original_name' => 'Memo.pdf']);
    $image = new DriveAsixFile(['original_name' => 'Foto.JPEG']);
    $document = new DriveAsixFile(['original_name' => 'Paparan.pptx']);
    $legacyDocument = new DriveAsixFile(['original_name' => 'Surat.doc']);

    expect($spreadsheet->extension())->toBe('xlsx')
        ->and($spreadsheet->openMode())->toBe('spreadsheet')
        ->and($spreadsheet->isPreviewable())->toBeTrue()
        ->and($spreadsheet->humanSize())->toBe('2 KB')
        ->and($pdf->openMode())->toBe('pdf')
        ->and($image->openMode())->toBe('image')
        ->and($document->openMode())->toBe('document')
        ->and($legacyDocument->openMode())->toBe('download');
});

it('mengizinkan user biasa membuka editor dan membaca workbook multi-sheet', function (): void {
    $user = driveAsixTestUser();
    $file = driveAsixStoredWorkbook($user);

    $this->actingAs($user)
        ->get(route('drive.file.editor', $file))
        ->assertOk()
        ->assertSee('Rekap Cabang.xlsx');

    $this->actingAs($user)
        ->getJson(route('drive.file.workbook', $file))
        ->assertOk()
        ->assertJsonPath('file.id', $file->getKey())
        ->assertJsonPath('file.name', 'Rekap Cabang.xlsx')
        ->assertJsonPath('workbook.active_sheet', 0)
        ->assertJsonPath('workbook.sheets.0.title', 'Ringkasan')
        ->assertJsonPath('workbook.sheets.0.cells.A1.value', 'Cabang')
        ->assertJsonPath('workbook.sheets.0.cells.C1.formula', '=SUM(B2:B3)')
        ->assertJsonPath('workbook.sheets.1.title', 'Detail');
});

it('menyimpan nilai formula format dan merge ke file workbook lokal', function (): void {
    $user = driveAsixTestUser();
    $file = driveAsixStoredWorkbook($user);

    $revision = $this->actingAs($user)
        ->getJson(route('drive.file.workbook', $file))
        ->assertOk()
        ->json('file.revision');

    expect($revision)->toBeString()->not->toBe('');

    $response = $this->actingAs($user)
        ->patchJson(route('drive.file.workbook.save', $file), [
            'base_revision' => $revision,
            'operations' => [
                [
                    'type' => 'set_cell',
                    'sheet' => 0,
                    'address' => 'A4',
                    'value' => 'Total Baru',
                ],
                [
                    'type' => 'set_cell',
                    'sheet' => 0,
                    'address' => 'B4',
                    'value' => 50,
                ],
                [
                    'type' => 'set_cell',
                    'sheet' => 0,
                    'address' => 'C4',
                    'formula' => '=SUM(B2:B4)',
                ],
                [
                    'type' => 'set_style',
                    'sheet' => 0,
                    'range' => 'A4:C4',
                    'style' => [
                        'bold' => true,
                        'fill_color' => 'FFFF99',
                        'font_size' => 14,
                        'horizontal' => 'center',
                        'vertical' => 'top',
                        'border_style' => 'thin',
                        'border_color' => '64748B',
                    ],
                ],
                [
                    'type' => 'merge',
                    'sheet' => 0,
                    'range' => 'D4:E4',
                ],
            ],
        ]);

    $response->assertOk();
    expect($response->json('file.revision'))->toBeString()->not->toBe($revision);

    $savedPath = Storage::disk('local')->path('drive_asix/'.$file->stored_name);
    $savedWorkbook = IOFactory::load($savedPath);
    $sheet = $savedWorkbook->getSheetByName('Ringkasan');

    expect($sheet)->not->toBeNull()
        ->and($sheet->getCell('A4')->getValue())->toBe('Total Baru')
        ->and($sheet->getCell('B4')->getValue())->toBe(50)
        ->and($sheet->getCell('C4')->getValue())->toBe('=SUM(B2:B4)')
        ->and($sheet->getStyle('A4')->getFont()->getBold())->toBeTrue()
        ->and($sheet->getStyle('A4')->getFont()->getSize())->toBe(14.0)
        ->and($sheet->getStyle('A4')->getFill()->getStartColor()->getRGB())->toBe('FFFF99')
        ->and($sheet->getStyle('A4')->getAlignment()->getHorizontal())->toBe('center')
        ->and($sheet->getStyle('A4')->getAlignment()->getVertical())->toBe('top')
        ->and($sheet->getStyle('A4')->getBorders()->getBottom()->getBorderStyle())->toBe('thin')
        ->and($sheet->getStyle('A4')->getBorders()->getBottom()->getColor()->getRGB())->toBe('64748B')
        ->and($sheet->getMergeCells())->toHaveKey('D4:E4');

    $savedWorkbook->disconnectWorksheets();
});

it('menolak simpan dari revisi lama tanpa menimpa perubahan terbaru', function (): void {
    $user = driveAsixTestUser();
    $file = driveAsixStoredWorkbook($user);

    $originalRevision = $this->actingAs($user)
        ->getJson(route('drive.file.workbook', $file))
        ->assertOk()
        ->json('file.revision');

    $firstSave = $this->actingAs($user)
        ->patchJson(route('drive.file.workbook.save', $file), [
            'base_revision' => $originalRevision,
            'operations' => [[
                'type' => 'set_cell',
                'sheet' => 0,
                'address' => 'A2',
                'value' => 'Madiun terbaru',
            ]],
        ])
        ->assertOk();

    expect($firstSave->json('file.revision'))->not->toBe($originalRevision);

    $this->actingAs($user)
        ->patchJson(route('drive.file.workbook.save', $file), [
            'base_revision' => $originalRevision,
            'operations' => [[
                'type' => 'set_cell',
                'sheet' => 0,
                'address' => 'A2',
                'value' => 'Data kedaluwarsa',
            ]],
        ])
        ->assertStatus(409)
        ->assertJsonPath('current_revision', $firstSave->json('file.revision'));

    $savedWorkbook = IOFactory::load(
        Storage::disk('local')->path('drive_asix/'.$file->stored_name)
    );
    expect($savedWorkbook->getSheet(0)->getCell('A2')->getValue())->toBe('Madiun terbaru');
    $savedWorkbook->disconnectWorksheets();
});

it('menyimpan operasi struktur baris kolom ukuran freeze filter dan sheet ke file fisik', function (): void {
    $user = driveAsixTestUser();
    $file = driveAsixStoredWorkbook($user);

    $revision = $this->actingAs($user)
        ->getJson(route('drive.file.workbook', $file))
        ->assertOk()
        ->json('file.revision');

    $response = $this->actingAs($user)
        ->patchJson(route('drive.file.workbook.save', $file), [
            'base_revision' => $revision,
            'active_sheet' => 'Target Copy',
            'operations' => [
                ['type' => 'insert_rows', 'sheet' => 0, 'row' => 2, 'count' => 1],
                ['type' => 'set_cell', 'sheet' => 0, 'address' => 'A2', 'value' => 'Baris sisipan'],
                ['type' => 'set_cell', 'sheet' => 0, 'address' => 'A6', 'value' => 'Baris dihapus'],
                ['type' => 'delete_rows', 'sheet' => 0, 'row' => 6, 'count' => 1],
                ['type' => 'insert_columns', 'sheet' => 0, 'column' => 'B', 'count' => 1],
                ['type' => 'set_cell', 'sheet' => 0, 'address' => 'B1', 'value' => 'Status'],
                ['type' => 'set_cell', 'sheet' => 0, 'address' => 'G1', 'value' => 'Kolom dihapus'],
                ['type' => 'delete_columns', 'sheet' => 0, 'column' => 'G', 'count' => 1],
                ['type' => 'set_column_width', 'sheet' => 0, 'column' => 'A', 'width' => 28.5],
                ['type' => 'set_row_height', 'sheet' => 0, 'row' => 1, 'height' => 32],
                ['type' => 'freeze_pane', 'sheet' => 0, 'pane' => 'B2'],
                ['type' => 'set_auto_filter', 'sheet' => 0, 'range' => 'A1:D4'],
                ['type' => 'add_sheet', 'title' => 'Kerja'],
                ['type' => 'rename_sheet', 'sheet' => 'Kerja', 'title' => 'Target'],
                ['type' => 'duplicate_sheet', 'sheet' => 'Target', 'title' => 'Target Copy'],
                ['type' => 'delete_sheet', 'sheet' => 'Detail'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('workbook.active_sheet', 2);

    expect($response->json('file.revision'))->toBeString()->not->toBe($revision);

    $savedPath = Storage::disk('local')->path('drive_asix/'.$file->stored_name);
    $savedWorkbook = IOFactory::load($savedPath);
    $sheet = $savedWorkbook->getSheetByName('Ringkasan');
    $sheetTitles = array_map(
        static fn ($worksheet): string => $worksheet->getTitle(),
        $savedWorkbook->getAllSheets()
    );

    expect($sheet)->not->toBeNull()
        ->and($sheet->getCell('A2')->getValue())->toBe('Baris sisipan')
        ->and($sheet->getCell('A3')->getValue())->toBe('Madiun')
        ->and($sheet->getCell('A6')->getValue())->toBeNull()
        ->and($sheet->getCell('B1')->getValue())->toBe('Status')
        ->and($sheet->getCell('C1')->getValue())->toBe('Nilai')
        ->and($sheet->getCell('G1')->getValue())->toBeNull()
        ->and($sheet->getColumnDimension('A')->getWidth())->toBe(28.5)
        ->and($sheet->getRowDimension(1)->getRowHeight())->toBe(32.0)
        ->and($sheet->getFreezePane())->toBe('B2')
        ->and($sheet->getAutoFilter()->getRange())->toBe('A1:D4')
        ->and($sheetTitles)->toBe(['Ringkasan', 'Target', 'Target Copy'])
        ->and($savedWorkbook->getActiveSheet()->getTitle())->toBe('Target Copy')
        ->and($savedWorkbook->getSheetByName('Detail'))->toBeNull();

    $savedWorkbook->disconnectWorksheets();
});

it('mendeteksi dan menyimpan XLSX yang terlanjur memakai ekstensi XLS berdasarkan isi file', function (): void {
    $user = driveAsixTestUser();
    $storedName = 'tablet-'.bin2hex(random_bytes(5)).'.xls';
    $binary = driveAsixWorkbookBinary();
    Storage::disk('local')->put('drive_asix/'.$storedName, $binary);

    $file = DriveAsixFile::query()->create([
        'folder_id' => null,
        'original_name' => 'Konversi Tablet.xls',
        'stored_name' => $storedName,
        'mime_type' => 'application/vnd.ms-excel',
        'size_bytes' => strlen($binary),
        'uploaded_by' => $user->getKey(),
    ]);

    $payload = $this->actingAs($user)
        ->getJson(route('drive.file.workbook', $file))
        ->assertOk()
        ->json();

    expect($payload['file']['extension'])->toBe('xlsx')
        ->and($payload['file']['warnings'])->toContain(
            'Ekstensi nama file tidak sesuai isi. DriveASIX menggunakan format isi XLSX.'
        );

    $saved = $this->actingAs($user)
        ->patchJson(route('drive.file.workbook.save', $file), [
            'base_revision' => $payload['file']['revision'],
            'operations' => [[
                'type' => 'set_cell',
                'sheet' => 'Ringkasan',
                'address' => 'A5',
                'value' => 'Tetap XLSX',
            ]],
        ])
        ->assertOk();

    expect($saved->json('file.extension'))->toBe('xlsx')
        ->and($saved->json('file.revision'))->not->toBe($payload['file']['revision']);

    $savedPath = Storage::disk('local')->path('drive_asix/'.$storedName);
    expect(SpreadsheetFileFormatDetector::detect($savedPath))->toBe('xlsx');

    $savedWorkbook = IOFactory::load($savedPath);
    expect($savedWorkbook->getSheetByName('Ringkasan')?->getCell('A5')->getValue())
        ->toBe('Tetap XLSX');
    $savedWorkbook->disconnectWorksheets();

    $file->refresh();
    expect($file->original_name)->toBe('Konversi Tablet.xls')
        ->and($file->mime_type)
        ->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('membuka dan menyimpan workbook biner XLS lama tanpa mengubah format isinya', function (): void {
    $user = driveAsixTestUser();
    $storedName = 'legacy-'.bin2hex(random_bytes(5)).'.xls';
    $binary = driveAsixWorkbookBinary('xls');
    Storage::disk('local')->put('drive_asix/'.$storedName, $binary);

    $file = DriveAsixFile::query()->create([
        'folder_id' => null,
        'original_name' => 'Workbook Lama.xls',
        'stored_name' => $storedName,
        'mime_type' => 'application/vnd.ms-excel',
        'size_bytes' => strlen($binary),
        'uploaded_by' => $user->getKey(),
    ]);

    $payload = $this->actingAs($user)
        ->getJson(route('drive.file.workbook', $file))
        ->assertOk()
        ->assertJsonPath('file.extension', 'xls')
        ->assertJsonPath('file.capabilities.legacy_xls', true)
        ->json();

    $this->actingAs($user)
        ->patchJson(route('drive.file.workbook.save', $file), [
            'base_revision' => $payload['file']['revision'],
            'operations' => [
                [
                    'type' => 'set_cell',
                    'sheet' => 'Ringkasan',
                    'address' => 'F2',
                    'formula' => '=SUM(B2:B3)',
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('file.extension', 'xls');

    $savedPath = Storage::disk('local')->path('drive_asix/'.$storedName);
    expect(SpreadsheetFileFormatDetector::detect($savedPath))->toBe('xls');

    $savedWorkbook = IOFactory::load($savedPath);
    expect($savedWorkbook->getSheetByName('Ringkasan')?->getCell('F2')->getValue())
        ->toBe('=SUM(B2:B3)');
    $savedWorkbook->disconnectWorksheets();
});

it('menolak formula jaringan dan referensi workbook eksternal tanpa mengubah revisi atau byte file', function (): void {
    $user = driveAsixTestUser();
    $file = driveAsixStoredWorkbook($user);
    $path = Storage::disk('local')->path('drive_asix/'.$file->stored_name);

    $revision = $this->actingAs($user)
        ->getJson(route('drive.file.workbook', $file))
        ->assertOk()
        ->json('file.revision');
    $originalHash = hash_file('sha256', $path);

    foreach ([
        '=WEBSERVICE("https://example.com/data")',
        "='[External.xlsx]Sheet1'!A1",
    ] as $formula) {
        $this->actingAs($user)
            ->patchJson(route('drive.file.workbook.save', $file), [
                'base_revision' => $revision,
                'operations' => [[
                    'type' => 'set_cell',
                    'sheet' => 0,
                    'address' => 'F10',
                    'formula' => $formula,
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Formula yang memanggil file, jaringan, atau layanan eksternal tidak diizinkan.'
            );

        expect(hash_file('sha256', $path))->toBe($originalHash);

        $currentRevision = $this->actingAs($user)
            ->getJson(route('drive.file.workbook', $file))
            ->assertOk()
            ->json('file.revision');

        expect($currentRevision)->toBe($revision);
    }
});

it('mengenali input umum persen tanggal dan nomor berawalan nol secara adaptif', function (): void {
    $user = driveAsixTestUser();
    $file = driveAsixStoredWorkbook($user);

    $revision = $this->actingAs($user)
        ->getJson(route('drive.file.workbook', $file))
        ->assertOk()
        ->json('file.revision');

    $this->actingAs($user)
        ->patchJson(route('drive.file.workbook.save', $file), [
            'base_revision' => $revision,
            'operations' => [
                ['type' => 'set_cell', 'sheet' => 0, 'address' => 'F2', 'value' => '25%'],
                ['type' => 'set_cell', 'sheet' => 0, 'address' => 'G2', 'value' => '2026-07-29'],
                ['type' => 'set_cell', 'sheet' => 0, 'address' => 'H2', 'value' => '00123'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('workbook.sheets.0.cells.F2.display', '25.00%')
        ->assertJsonPath('workbook.sheets.0.cells.G2.display', '2026-07-29')
        ->assertJsonPath('workbook.sheets.0.cells.H2.value', '00123');

    $savedWorkbook = IOFactory::load(
        Storage::disk('local')->path('drive_asix/'.$file->stored_name)
    );
    $sheet = $savedWorkbook->getSheet(0);

    expect($sheet->getCell('F2')->getValue())->toBe(0.25)
        ->and($sheet->getStyle('F2')->getNumberFormat()->getFormatCode())->toBe('0.00%')
        ->and($sheet->getCell('G2')->getValue())->toBeNumeric()
        ->and($sheet->getStyle('G2')->getNumberFormat()->getFormatCode())->toBe('yyyy-mm-dd')
        ->and($sheet->getCell('H2')->getValue())->toBe('00123');

    $savedWorkbook->disconnectWorksheets();
});

it('membatasi upload dan delete untuk admin tanpa membatasi akses edit user', function (): void {
    $user = driveAsixTestUser();
    $admin = driveAsixTestUser('admin');
    $file = driveAsixStoredWorkbook($admin);
    $uploadBinary = driveAsixWorkbookBinary();
    $legacyXlsBinary = driveAsixWorkbookBinary('xls');

    $this->actingAs($user)
        ->post(route('drive.upload'), [
            'files' => [
                UploadedFile::fake()->createWithContent('Upload User.xlsx', $uploadBinary),
            ],
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('drive.file.delete', $file))
        ->assertForbidden();

    expect(DriveAsixFile::query()->find($file->getKey()))->not->toBeNull();

    $this->actingAs($admin)
        ->post(route('drive.upload'), [
            'files' => [
                UploadedFile::fake()->createWithContent('Upload Admin.xlsx', $uploadBinary),
                UploadedFile::fake()->createWithContent('Legacy Admin.xls', $legacyXlsBinary),
                UploadedFile::fake()->createWithContent('Tablet Export.xls', $uploadBinary),
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('drive_asix_files', [
        'original_name' => 'Upload Admin.xlsx',
        'uploaded_by' => $admin->getKey(),
    ]);
    $this->assertDatabaseHas('drive_asix_files', [
        'original_name' => 'Legacy Admin.xls',
        'mime_type' => 'application/vnd.ms-excel',
        'uploaded_by' => $admin->getKey(),
    ]);
    $this->assertDatabaseHas('drive_asix_files', [
        'original_name' => 'Tablet Export.xls',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'uploaded_by' => $admin->getKey(),
    ]);

    $this->actingAs($admin)
        ->delete(route('drive.file.delete', $file))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(DriveAsixFile::withTrashed()->find($file->getKey())?->trashed())->toBeTrue();
});

it('menjaga nama file aman dan mempertahankan ekstensi saat rename', function (): void {
    $admin = driveAsixTestUser('admin');
    $file = driveAsixStoredWorkbook($admin);

    $this->actingAs($admin)
        ->patch(route('drive.file.rename', $file), ['name' => '../rusak.pdf'])
        ->assertSessionHasErrors('name');

    expect($file->fresh()->original_name)->toBe('Rekap Cabang.xlsx');

    $this->actingAs($admin)
        ->patch(route('drive.file.rename', $file), ['name' => 'Rekap Aman'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($file->fresh()->original_name)->toBe('Rekap Aman.xlsx');

    $this->actingAs($admin)
        ->patch(route('drive.file.rename', $file), ['name' => 'Bukan Workbook.pdf'])
        ->assertSessionHasErrors('name');

    expect($file->fresh()->original_name)->toBe('Rekap Aman.xlsx');
});

it('menolak file OLE palsu saat upload', function (): void {
    $admin = driveAsixTestUser('admin');
    $oleHeaderOnly = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 512);

    $this->actingAs($admin)
        ->post(route('drive.upload'), [
            'files' => [
                UploadedFile::fake()->createWithContent('Palsu.xls', $oleHeaderOnly),
            ],
        ])
        ->assertSessionHasErrors('files.0');

    $this->actingAs($admin)
        ->post(route('drive.upload'), [
            'files' => [
                UploadedFile::fake()->createWithContent('Palsu.doc', $oleHeaderOnly),
            ],
        ])
        ->assertSessionHasErrors('files.0');

    expect(DriveAsixFile::query()->count())->toBe(0);
});

it('menyimpan batch XLSX berformula eksternal ke folder tujuan dengan kontrak JSON lengkap', function (): void {
    $admin = driveAsixTestUser('admin');
    $folder = DriveAsixFolder::query()->create([
        'parent_id' => null,
        'name' => 'Folder Analisis',
        'created_by' => $admin->getKey(),
    ]);
    $regularBinary = driveAsixWorkbookBinary();
    $externalWorkbook = new Spreadsheet;
    $externalWorkbook->getActiveSheet()->setCellValue(
        'A1',
        '=HYPERLINK("ht"&"tps://example.invalid","Buka")'
    );
    $temporaryPath = tempnam(sys_get_temp_dir(), 'drive-asix-external-upload-');
    expect($temporaryPath)->not->toBeFalse();

    try {
        (new Xlsx($externalWorkbook))->save($temporaryPath);
        $externalBinary = file_get_contents($temporaryPath);
        expect($externalBinary)->toBeString();

        $redirectUrl = route('drive.index', ['folderId' => $folder->getKey()]);
        $response = $this->actingAs($admin)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('drive.upload'), [
                'folder_id' => (string) $folder->getKey(),
                'files' => [
                    UploadedFile::fake()->createWithContent(
                        'Rekap Biasa.xlsx',
                        $regularBinary
                    ),
                    UploadedFile::fake()->createWithContent(
                        'Referensi Eksternal.xlsx',
                        $externalBinary
                    ),
                ],
            ]);

        $response->assertCreated()->assertExactJson([
            'message' => '2 file berhasil diunggah.',
            'uploaded_count' => 2,
            'folder_id' => $folder->getKey(),
            'redirect_url' => $redirectUrl,
        ]);

        $records = DriveAsixFile::query()
            ->where('folder_id', $folder->getKey())
            ->orderBy('original_name')
            ->get();
        expect($records)->toHaveCount(2)
            ->and($records->pluck('original_name')->all())->toBe([
                'Referensi Eksternal.xlsx',
                'Rekap Biasa.xlsx',
            ])
            ->and(Storage::disk('local')->files('drive_asix'))->toHaveCount(2);

        $externalRecord = $records->firstWhere(
            'original_name',
            'Referensi Eksternal.xlsx'
        );
        $storedWorkbook = IOFactory::load(Storage::disk('local')->path(
            'drive_asix/'.$externalRecord->stored_name
        ));
        expect($storedWorkbook->getActiveSheet()->getCell('A1')->getValue())
            ->toBe('=HYPERLINK("ht"&"tps://example.invalid","Buka")');
        $storedWorkbook->disconnectWorksheets();

        $this->actingAs($admin)
            ->get($redirectUrl)
            ->assertOk()
            ->assertSee('Rekap Biasa.xlsx')
            ->assertSee('Referensi Eksternal.xlsx');

        $this->actingAs($admin)
            ->get(route('drive.index'))
            ->assertOk()
            ->assertDontSee('Rekap Biasa.xlsx')
            ->assertDontSee('Referensi Eksternal.xlsx');
    } finally {
        $externalWorkbook->disconnectWorksheets();
        @unlink($temporaryPath);
    }
});

it('merollback seluruh batch dan file fisik ketika metadata file kedua gagal', function (): void {
    $admin = driveAsixTestUser('admin');
    $binary = driveAsixWorkbookBinary();
    $createdCount = 0;
    $eventName = 'eloquent.created: '.DriveAsixFile::class;
    Event::listen($eventName, static function () use (&$createdCount): void {
        $createdCount++;
        if ($createdCount === 2) {
            throw new RuntimeException('Simulasi kegagalan metadata upload kedua.');
        }
    });

    try {
        $this->actingAs($admin)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('drive.upload'), [
                'files' => [
                    UploadedFile::fake()->createWithContent('Pertama.xlsx', $binary),
                    UploadedFile::fake()->createWithContent('Kedua.xlsx', $binary),
                ],
            ])
            ->assertStatus(500);
    } finally {
        Event::forget($eventName);
    }

    expect($createdCount)->toBe(2)
        ->and(DriveAsixFile::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('drive_asix'))->toBe([]);
});

it('memberi alasan spesifik untuk path arsip berbahaya dan rasio kompresi ekstrem', function (): void {
    $admin = driveAsixTestUser('admin');
    $unsafePathArchive = driveAsixZipBinary([
        '[Content_Types].xml' => '<Types/>',
        'xl/workbook.xml' => '<workbook/>',
        'xl/worksheets/sheet1.xml' => '<worksheet/>',
        '../keluar.xml' => '<payload/>',
    ]);
    $ratioBombArchive = driveAsixZipBinary([
        '[Content_Types].xml' => '<Types/>',
        'xl/workbook.xml' => '<workbook/>',
        'xl/worksheets/sheet1.xml' => str_repeat('A', 1_048_576),
    ]);

    $unsafePathResponse = $this->actingAs($admin)
        ->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('drive.upload'), [
            'files' => [
                UploadedFile::fake()->createWithContent(
                    'Path Berbahaya.xlsx',
                    $unsafePathArchive
                ),
            ],
        ]);

    $unsafePathResponse->assertUnprocessable()
        ->assertJsonValidationErrors('files.0');
    expect($unsafePathResponse->json('errors')['files.0'][0] ?? null)
        ->toBe('Paket Office memuat path bagian yang tidak aman.');

    $ratioResponse = $this->actingAs($admin)
        ->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('drive.upload'), [
            'files' => [
                UploadedFile::fake()->createWithContent(
                    'Rasio Ekstrem.xlsx',
                    $ratioBombArchive
                ),
            ],
        ]);

    $ratioResponse->assertUnprocessable()
        ->assertJsonValidationErrors('files.0');
    expect($ratioResponse->json('errors')['files.0'][0] ?? null)
        ->toBe('Paket Office memiliki rasio kompresi tidak aman.')
        ->and(DriveAsixFile::query()->count())->toBe(0)
        ->and(Storage::disk('local')->files('drive_asix'))->toBe([]);
});

it('menerima DOCX lokal biasa tetapi menolak paket Office yang memuat VBA', function (): void {
    $admin = driveAsixTestUser('admin');
    $contentTypes = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;
    $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>Dokumen aman</w:t></w:r></w:p></w:body>
</w:document>
XML;

    $safeDocx = driveAsixZipBinary([
        '[Content_Types].xml' => $contentTypes,
        'word/document.xml' => $documentXml,
    ]);
    $macroDocx = driveAsixZipBinary([
        '[Content_Types].xml' => str_replace(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
            'application/vnd.ms-word.document.macroEnabled.main+xml',
            $contentTypes
        ),
        'word/document.xml' => $documentXml,
        'word/vbaProject.bin' => 'macro payload',
    ]);

    $this->actingAs($admin)
        ->post(route('drive.upload'), [
            'files' => [
                UploadedFile::fake()->createWithContent('Dokumen Aman.docx', $safeDocx),
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->post(route('drive.upload'), [
            'files' => [
                UploadedFile::fake()->createWithContent('Dokumen Macro.docx', $macroDocx),
            ],
        ])
        ->assertSessionHasErrors('files.0');

    expect(DriveAsixFile::query()->pluck('original_name')->all())
        ->toBe(['Dokumen Aman.docx']);
});

it('membagi payload berdasarkan sel terisi dan tetap memuat dimensi struktural', function (): void {
    $user = driveAsixTestUser();
    $spreadsheet = new Spreadsheet;
    $denseSheet = $spreadsheet->getActiveSheet();
    $denseSheet->setTitle('Padat');
    $value = 0;
    for ($row = 1; $row <= 30; $row++) {
        for ($column = 1; $column <= 100; $column++) {
            $denseSheet->setCellValue([$column, $row], ++$value);
        }
    }

    for ($index = 1; $index < 25; $index++) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Kosong '.$index);
    }
    $structuralSheet = $spreadsheet->getSheet(1);
    $structuralSheet->getColumnDimension('Z')->setWidth(22);
    $structuralSheet->getRowDimension(75)->setRowHeight(33);
    $structuralSheet->mergeCells('AA60:AB61');

    $temporaryPath = tempnam(sys_get_temp_dir(), 'drive-asix-payload-');
    expect($temporaryPath)->not->toBeFalse();

    try {
        (new Xlsx($spreadsheet))->save($temporaryPath);
        $binary = file_get_contents($temporaryPath);
        expect($binary)->toBeString();

        $storedName = 'payload-'.bin2hex(random_bytes(5)).'.xlsx';
        Storage::disk('local')->put('drive_asix/'.$storedName, $binary);
        $file = DriveAsixFile::query()->create([
            'folder_id' => null,
            'original_name' => 'Payload Aman.xlsx',
            'stored_name' => $storedName,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size_bytes' => strlen($binary),
            'uploaded_by' => $user->getKey(),
        ]);

        $payload = $this->actingAs($user)
            ->getJson(route('drive.file.workbook', $file))
            ->assertOk()
            ->assertJsonPath('workbook.sheets.0.cells.CV30.value', 3000)
            ->assertJsonPath('workbook.sheets.1.max_row', 75)
            ->assertJsonPath('workbook.sheets.1.max_col', 28)
            ->assertJsonPath('workbook.sheets.1.row_heights.75', 33)
            ->assertJsonPath('workbook.sheets.1.column_widths.Z', 22)
            ->json();

        expect($payload['workbook']['sheets'][1]['merged_cells'])
            ->toContain('AA60:AB61')
            ->and(array_sum(array_map(
                static fn (array $sheet): int => count($sheet['cells']),
                $payload['workbook']['sheets']
            )))->toBeLessThanOrEqual(50_000);
    } finally {
        $spreadsheet->disconnectWorksheets();
        @unlink($temporaryPath);
    }
});

it('memblokir pembuatan sheet ke-256 untuk semua format workbook', function (): void {
    $spreadsheet = new Spreadsheet;
    for ($index = 1; $index < 255; $index++) {
        $spreadsheet->createSheet()->setTitle('Sheet '.$index);
    }

    $service = app(SpreadsheetWorkbookService::class);
    $method = new ReflectionMethod($service, 'applySheetOperation');
    $method->setAccessible(true);

    try {
        expect(fn () => $method->invoke(
            $service,
            $spreadsheet,
            ['title' => 'Sheet 256'],
            'add_sheet',
            'xlsx'
        ))->toThrow(
            DriveAsixWorkbookException::class,
            'DriveASIX mendukung maksimal 255 sheet dalam satu workbook.'
        );
    } finally {
        $spreadsheet->disconnectWorksheets();
    }
});

it('membatasi biaya agregat operasi dan ukuran merge tanpa mengubah file', function (): void {
    $user = driveAsixTestUser();
    $file = driveAsixStoredWorkbook($user);
    $path = Storage::disk('local')->path('drive_asix/'.$file->stored_name);
    $largeWorkbook = IOFactory::load($path);
    $largeWorkbook->getSheet(0)->setCellValue('A50001', 'Penanda batas');
    (new Xlsx($largeWorkbook))->save($path);
    $largeWorkbook->disconnectWorksheets();

    $revision = $this->actingAs($user)
        ->getJson(route('drive.file.workbook', $file))
        ->assertOk()
        ->json('file.revision');
    $originalHash = hash_file('sha256', $path);

    $this->actingAs($user)
        ->patchJson(route('drive.file.workbook.save', $file), [
            'base_revision' => $revision,
            'operations' => [[
                'type' => 'insert_rows',
                'sheet' => 0,
                'row' => 2,
                'count' => 1,
            ]],
        ])
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'Total perubahan dalam satu penyimpanan dibatasi 50.000 unit kerja agar server tetap responsif.'
        );

    expect(hash_file('sha256', $path))->toBe($originalHash);

    $this->actingAs($user)
        ->patchJson(route('drive.file.workbook.save', $file), [
            'base_revision' => $revision,
            'operations' => [[
                'type' => 'merge',
                'sheet' => 0,
                'range' => 'A1:T1001',
            ]],
        ])
        ->assertStatus(422);

    expect(hash_file('sha256', $path))->toBe($originalHash);
});

it('menolak sort pada formula agar relasi perhitungan tidak rusak', function (): void {
    $user = driveAsixTestUser();
    $file = driveAsixStoredWorkbook($user);
    $revision = $this->actingAs($user)
        ->getJson(route('drive.file.workbook', $file))
        ->assertOk()
        ->json('file.revision');

    $this->actingAs($user)
        ->patchJson(route('drive.file.workbook.save', $file), [
            'base_revision' => $revision,
            'operations' => [[
                'type' => 'sort_range',
                'sheet' => 0,
                'range' => 'A1:C3',
                'column' => 1,
                'direction' => 'asc',
            ]],
        ])
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'Sort lokal hanya tersedia untuk rentang tanpa formula agar referensi tetap akurat.'
        );
});

it('tetap mengurutkan rentang data polos secara akurat', function (): void {
    $user = driveAsixTestUser();
    $file = driveAsixStoredWorkbook($user);
    $revision = $this->actingAs($user)
        ->getJson(route('drive.file.workbook', $file))
        ->assertOk()
        ->json('file.revision');

    $this->actingAs($user)
        ->patchJson(route('drive.file.workbook.save', $file), [
            'base_revision' => $revision,
            'operations' => [[
                'type' => 'sort_range',
                'sheet' => 0,
                'range' => 'A2:B3',
                'column' => 2,
                'direction' => 'asc',
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('workbook.sheets.0.cells.A2.value', 'Ngawi')
        ->assertJsonPath('workbook.sheets.0.cells.B2.value', 75)
        ->assertJsonPath('workbook.sheets.0.cells.A3.value', 'Madiun')
        ->assertJsonPath('workbook.sheets.0.cells.B3.value', 125);
});

it('menghapus file fisik dan versi cadangan saat purge permanen', function (): void {
    $admin = driveAsixTestUser('admin');
    $file = driveAsixStoredWorkbook($admin);
    $filePath = 'drive_asix/'.$file->stored_name;
    $versionPath = 'drive_asix_versions/'.$file->getKey().'/cadangan.xlsx';
    Storage::disk('local')->put($versionPath, driveAsixWorkbookBinary());
    $file->delete();

    $this->actingAs($admin)
        ->delete(route('drive.file.purge', $file->getKey()))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(DriveAsixFile::withTrashed()->find($file->getKey()))->toBeNull()
        ->and(Storage::disk('local')->exists($filePath))->toBeFalse()
        ->and(Storage::disk('local')->directoryExists('drive_asix_versions/'.$file->getKey()))
        ->toBeFalse();
});

it('mengembalikan file dan versi bila penghapusan metadata permanen gagal', function (): void {
    $admin = driveAsixTestUser('admin');
    $file = driveAsixStoredWorkbook($admin);
    $filePath = 'drive_asix/'.$file->stored_name;
    $versionPath = 'drive_asix_versions/'.$file->getKey().'/cadangan.xlsx';
    Storage::disk('local')->put($versionPath, driveAsixWorkbookBinary());
    $file->delete();

    $eventName = 'eloquent.forceDeleting: '.DriveAsixFile::class;
    Event::listen($eventName, static function (): never {
        throw new RuntimeException('Simulasi kegagalan metadata.');
    });

    try {
        $this->actingAs($admin)
            ->delete(route('drive.file.purge', $file->getKey()))
            ->assertStatus(500);
    } finally {
        Event::forget($eventName);
    }

    $preserved = DriveAsixFile::withTrashed()->find($file->getKey());
    expect($preserved)->not->toBeNull()
        ->and($preserved?->trashed())->toBeTrue()
        ->and(Storage::disk('local')->exists($filePath))->toBeTrue()
        ->and(Storage::disk('local')->exists($versionPath))->toBeTrue()
        ->and(Storage::disk('local')->allFiles('drive_asix_quarantine'))->toBe([]);
});

it('memuat guard UI untuk drive kosong, save race, range, dan clipboard terstruktur', function (): void {
    $indexSource = file_get_contents(resource_path('views/drive/index.blade.php'));
    $editorSource = file_get_contents(resource_path('views/drive/spreadsheet-editor.blade.php'));

    expect($indexSource)->toBeString()
        ->and($editorSource)->toBeString()
        ->and($indexSource)->toContain('if (!grid || !list) return;')
        ->and(substr_count(
            $indexSource,
            'document.querySelectorAll(\'.dv-card[role="button"],.dv-list-row[role="button"]\').forEach(item => {'
        ))->toBe(1)
        ->and($editorSource)->toContain('if (state.saving)')
        ->and($editorSource)->toContain('state.conflictLocked = true;')
        ->and($editorSource)->toContain('state.conflictLocked = false;')
        ->and($editorSource)->toContain('Tutup, tetap terkunci')
        ->and($editorSource)->toContain('activateActionSheet(action)')
        ->and($editorSource)->toContain('MAX_INTERACTIVE_RANGE_CELLS')
        ->and($editorSource)->toContain('cells: cellRows')
        ->and($editorSource)->not->toContain('if (cut) clearRange(range);');
});

it('tidak memuat viewer Office cloud pada implementasi DriveASIX', function (): void {
    $viewSources = '';
    foreach (glob(resource_path('views/drive/*.blade.php')) ?: [] as $viewPath) {
        $source = file_get_contents($viewPath);
        if ($source !== false) {
            $viewSources .= "\n".$source;
        }
    }

    expect($viewSources)->not->toBe('')
        ->and(strtolower($viewSources))->not->toContain('view.officeapps.live.com');
});
