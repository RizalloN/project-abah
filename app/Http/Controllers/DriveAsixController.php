<?php

namespace App\Http\Controllers;

use App\Exceptions\DriveAsixVersionConflictException;
use App\Exceptions\DriveAsixWorkbookException;
use App\Models\DriveAsixFile;
use App\Models\DriveAsixFolder;
use App\Services\DriveAsix\OfficeDocumentPreviewService;
use App\Services\DriveAsix\OnlyOfficeEditorService;
use App\Services\DriveAsix\SpreadsheetWorkbookService;
use App\Support\SpreadsheetFileFormatDetector;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use ZipArchive;

class DriveAsixController extends Controller
{
    private const DISK = 'local';

    private const BASE_PATH = 'drive_asix';

    private const VERSION_PATH = 'drive_asix_versions';

    private const OFFICE_SESSION_PATH = 'drive_asix_office_sessions';

    private const QUARANTINE_PATH = 'drive_asix_quarantine';

    private const MAX_ZIP_ENTRIES = 4_096;

    private const MAX_ZIP_UNCOMPRESSED_BYTES = 805_306_368;

    private const MAX_ZIP_ENTRY_BYTES = 268_435_456;

    private const ZIP_RATIO_INSPECTION_THRESHOLD = 1_048_576;

    private const MAX_ZIP_COMPRESSION_RATIO = 250;

    private const MAX_OOXML_METADATA_BYTES = 2_097_152;

    // -------------- INDEX --------------

    public function index(?int $folderId = null)
    {
        $currentFolder = null;
        $breadcrumbs = [];

        if ($folderId) {
            $currentFolder = DriveAsixFolder::findOrFail($folderId);
            $breadcrumbs = $currentFolder->breadcrumbs();
        }

        $folders = DriveAsixFolder::where('parent_id', $folderId)->orderBy('name')->get();
        $files = DriveAsixFile::where('folder_id', $folderId)
            ->whereNull('deleted_at')
            ->with('uploader')
            ->orderBy('original_name')
            ->get();

        // All folders (flat, with depth) for move/copy tree
        $allFolders = $this->buildFlatTree();

        // Trashed files count + list (admin only)
        $trashedFiles = Auth::user()?->isAdmin()
            ? DriveAsixFile::onlyTrashed()->orderByDesc('deleted_at')->get()
            : collect();
        $trashedCount = $trashedFiles->count();

        return view('drive.index', compact(
            'currentFolder', 'breadcrumbs', 'folders', 'files',
            'folderId', 'allFolders', 'trashedFiles', 'trashedCount'
        ));
    }

    // -------------- FOLDER ACTIONS --------------

    public function storeFolder(Request $request)
    {
        $this->requireAdmin();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:drive_asix_folders,id',
        ]);

        DriveAsixFolder::create([
            'name' => $this->normalizeFolderName($validated['name']),
            'parent_id' => $request->parent_id ?: null,
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Folder berhasil dibuat.');
    }

    public function renameFolder(Request $request, DriveAsixFolder $folder)
    {
        $this->requireAdmin();
        $validated = $request->validate(['name' => 'required|string|max:255']);
        $folder->update(['name' => $this->normalizeFolderName($validated['name'])]);

        return back()->with('success', 'Nama folder berhasil diubah.');
    }

    public function moveFolder(Request $request, DriveAsixFolder $folder)
    {
        $this->requireAdmin();
        $request->validate([
            'destination_folder_id' => 'nullable|exists:drive_asix_folders,id',
        ]);
        $destId = $request->input('destination_folder_id') ?: null;

        // Prevent moving folder into itself or its descendant
        if ($destId && $this->isSelfOrDescendant($folder, $destId)) {
            return back()->with('error', 'Tidak dapat memindahkan folder ke dalam dirinya sendiri.');
        }

        $folder->update(['parent_id' => $destId]);

        return back()->with('success', 'Folder berhasil dipindahkan.');
    }

    public function deleteFolder(
        DriveAsixFolder $folder,
        OnlyOfficeEditorService $office
    ) {
        $this->requireAdmin();
        $this->assertFolderHasNoActiveOfficeSession($folder, $office);
        $this->deletePhysicalFiles($folder, $office);
        $folder->delete();

        return back()->with('success', 'Folder berhasil dihapus.');
    }

    // -------------- FILE ACTIONS --------------

    public function upload(Request $request, SpreadsheetWorkbookService $workbooks)
    {
        $this->requireAdmin();
        $validated = $request->validate([
            'files' => 'required|array|min:1|max:20',
            'files.*' => 'required|file|max:51200',
            'folder_id' => 'nullable|exists:drive_asix_folders,id',
        ]);

        $folderId = isset($validated['folder_id'])
            ? (int) $validated['folder_id']
            : null;
        $uploads = $request->file('files', []);

        foreach ($uploads as $index => $upload) {
            $extension = strtolower($upload->getClientOriginalExtension());
            if (! in_array($extension, DriveAsixFile::ALLOWED_EXTENSIONS, true)) {
                throw ValidationException::withMessages([
                    "files.{$index}" => 'Format .'.($extension ?: '(tanpa ekstensi)')
                        .' belum didukung DriveASIX.',
                ]);
            }
            $this->validateUploadedContent(
                $upload->getRealPath(),
                $extension,
                $index,
                $workbooks
            );
        }

        $storedPaths = [];
        try {
            $count = DB::transaction(function () use (
                $folderId,
                $uploads,
                &$storedPaths
            ): int {
                $persisted = 0;
                foreach ($uploads as $index => $upload) {
                    $extension = strtolower($upload->getClientOriginalExtension());
                    $storedName = Str::uuid().'.'.$extension;
                    $storedPath = self::BASE_PATH.'/'.$storedName;
                    $storedPaths[] = $storedPath;
                    $originalName = $this->normalizeFileName(
                        $upload->getClientOriginalName(),
                        $extension,
                        "files.{$index}"
                    );

                    if (! Storage::disk(self::DISK)->putFileAs(
                        self::BASE_PATH,
                        $upload,
                        $storedName
                    )) {
                        throw ValidationException::withMessages([
                            'files' => 'File "'.$originalName
                                .'" gagal ditulis ke penyimpanan.',
                        ]);
                    }

                    DriveAsixFile::create([
                        'folder_id' => $folderId,
                        'original_name' => $originalName,
                        'stored_name' => $storedName,
                        'mime_type' => $this->safeMimeType(
                            Storage::disk(self::DISK)->path($storedPath),
                            $extension
                        ),
                        'size_bytes' => Storage::disk(self::DISK)->size($storedPath),
                        'uploaded_by' => Auth::id(),
                    ]);
                    $persisted++;
                }

                return $persisted;
            });
        } catch (\Throwable $exception) {
            foreach (array_reverse($storedPaths) as $storedPath) {
                try {
                    $disk = Storage::disk(self::DISK);
                    if ($disk->exists($storedPath) && ! $disk->delete($storedPath)) {
                        report(new \RuntimeException(
                            'Rollback file upload DriveASIX gagal: '.$storedPath
                        ));
                    }
                } catch (\Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }

        $redirectUrl = $folderId === null
            ? route('drive.index')
            : route('drive.index', ['folderId' => $folderId]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $count.' file berhasil diunggah.',
                'uploaded_count' => $count,
                'folder_id' => $folderId,
                'redirect_url' => $redirectUrl,
            ], 201);
        }

        return back()->with('success', $count.' file berhasil diunggah.');
    }

    public function renameFile(Request $request, DriveAsixFile $file)
    {
        $this->requireAdmin();
        $validated = $request->validate(['name' => 'required|string|max:255']);
        $file->update([
            'original_name' => $this->normalizeFileName(
                $validated['name'],
                $file->extension()
            ),
        ]);

        return back()->with('success', 'File berhasil diubah namanya.');
    }

    public function moveFile(Request $request, DriveAsixFile $file)
    {
        $this->requireAdmin();
        $request->validate([
            'destination_folder_id' => 'nullable|exists:drive_asix_folders,id',
        ]);
        $destId = $request->input('destination_folder_id') ?: null;
        $file->update(['folder_id' => $destId]);

        return back()->with('success', 'File berhasil dipindahkan.');
    }

    public function copyFile(Request $request, DriveAsixFile $file)
    {
        $this->requireAdmin();
        $request->validate([
            'destination_folder_id' => 'nullable|exists:drive_asix_folders,id',
        ]);
        $destId = $request->input('destination_folder_id') ?: null;

        $srcPath = self::BASE_PATH.'/'.$file->stored_name;
        if (! Storage::disk(self::DISK)->exists($srcPath)) {
            return back()->with('error', 'File sumber tidak ditemukan.');
        }

        $newStoredName = Str::uuid().'.'.pathinfo($file->stored_name, PATHINFO_EXTENSION);
        $destinationPath = self::BASE_PATH.'/'.$newStoredName;
        if (! Storage::disk(self::DISK)->copy($srcPath, $destinationPath)) {
            return back()->with('error', 'File gagal disalin ke penyimpanan tujuan.');
        }

        try {
            DriveAsixFile::create([
                'folder_id' => $destId,
                'original_name' => $file->original_name,
                'stored_name' => $newStoredName,
                'mime_type' => $file->mime_type,
                'size_bytes' => Storage::disk(self::DISK)->size($destinationPath),
                'uploaded_by' => Auth::id(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk(self::DISK)->delete($destinationPath);
            throw $exception;
        }

        return back()->with('success', 'File berhasil disalin ke folder tujuan.');
    }

    public function deleteFile(
        DriveAsixFile $file,
        OnlyOfficeEditorService $office
    ) {
        $this->requireAdmin();
        $sessionLock = Cache::lock(
            'drive-asix:office-session:'.$file->getKey(),
            60
        );

        try {
            $sessionLock->block(8, function () use ($file, $office): void {
                $this->assertNoActiveOfficeSession($file, $office);
                // Soft delete - physical file kept, only DB marked deleted
                $file->delete();
            });
        } catch (LockTimeoutException) {
            abort(423, 'Sesi editor Office sedang berubah. Coba hapus kembali beberapa detik lagi.');
        }

        return back()->with('success', '"'.$file->original_name.'" dipindahkan ke Sampah.');
    }

    public function restoreFile(int $id)
    {
        $this->requireAdmin();
        $file = DriveAsixFile::onlyTrashed()->findOrFail($id);
        $file->restore();

        return back()->with('success', '"'.$file->original_name.'" berhasil dipulihkan.');
    }

    public function purgeFile(
        int $id,
        OnlyOfficeEditorService $office
    ) {
        $this->requireAdmin();
        $file = DriveAsixFile::onlyTrashed()->findOrFail($id);
        $this->assertNoActiveOfficeSession($file, $office);
        $this->deleteStoredArtifacts($file, $office);

        return back()->with('success', 'File dihapus permanen.');
    }

    public function purgeAllFiles(OnlyOfficeEditorService $office)
    {
        $this->requireAdmin();
        $trashed = DriveAsixFile::onlyTrashed()->get();
        foreach ($trashed as $file) {
            $this->assertNoActiveOfficeSession($file, $office);
        }
        foreach ($trashed as $file) {
            $this->deleteStoredArtifacts($file, $office);
        }

        return back()->with('success', 'Semua file di sampah telah dihapus permanen.');
    }

    // -------------- FILE ACCESS --------------

    public function preview(DriveAsixFile $file)
    {
        $path = $this->physicalPath($file);

        if ($file->isPdf()) {
            $signature = file_get_contents($path, false, null, 0, 5);
            abort_unless($signature === '%PDF-', 415, 'Isi file PDF tidak valid.');
            $mime = 'application/pdf';
        } elseif ($file->isImage()) {
            $imageInfo = @getimagesize($path);
            abort_unless(is_array($imageInfo), 415, 'Isi file gambar tidak valid.');
            $mime = (string) ($imageInfo['mime'] ?? '');
            abort_unless(in_array($mime, [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
            ], true), 415, 'Format gambar tidak didukung.');
        } else {
            abort(415, 'File ini dibuka melalui editor lokal atau diunduh.');
        }

        return response()->file($path, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ])->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $file->original_name,
            $this->safeDispositionFallback($file->original_name)
        );
    }

    public function download(DriveAsixFile $file)
    {
        $this->physicalPath($file);

        return Storage::disk(self::DISK)->download(
            self::BASE_PATH.'/'.$file->stored_name,
            $file->original_name,
            ['X-Content-Type-Options' => 'nosniff']
        );
    }

    public function editor(
        DriveAsixFile $file,
        SpreadsheetWorkbookService $workbooks
    ) {
        abort_unless($file->isSpreadsheet(), 415, 'File ini bukan spreadsheet.');
        abort_unless($workbooks->detectFormat($file) !== null, 415, 'Isi workbook tidak valid.');

        return view('drive.spreadsheet-editor', compact('file'));
    }

    public function workbook(
        DriveAsixFile $file,
        SpreadsheetWorkbookService $workbooks
    ) {
        try {
            return response()->json($workbooks->read($file));
        } catch (DriveAsixWorkbookException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function saveWorkbook(
        Request $request,
        DriveAsixFile $file,
        SpreadsheetWorkbookService $workbooks,
        OnlyOfficeEditorService $office
    ) {
        if ($office->hasActiveSession($file)) {
            return response()->json([
                'message' => 'File sedang dibuka di editor Office penuh. Tutup sesi editor sebelum menyimpan melalui mode kompatibel.',
            ], 423);
        }

        $validated = $request->validate([
            'base_revision' => 'required|string|max:100',
            'operations' => 'required|array|max:5000',
            'operations.*' => 'required|array',
            'active_sheet' => 'nullable|string|max:31',
        ]);

        try {
            return response()->json($workbooks->save(
                $file,
                $validated['base_revision'],
                $validated['operations'],
                Auth::id(),
                $validated['active_sheet'] ?? null
            ));
        } catch (DriveAsixVersionConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'current_revision' => $exception->currentRevision,
            ], 409);
        } catch (DriveAsixWorkbookException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function documentPreview(
        DriveAsixFile $file,
        OfficeDocumentPreviewService $documents
    ) {
        abort_unless($file->hasLocalDocumentPreview(), 415, 'Preview lokal hanya tersedia untuk DOCX/PPTX.');
        $path = $this->physicalPath($file);

        try {
            $document = $documents->preview($path, $file->original_name);
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        return view('drive.document-preview', [
            'file' => $file,
            'preview' => $document,
            'backUrl' => route('drive.index', ['folderId' => $file->folder_id]),
            'downloadUrl' => route('drive.file.download', $file),
        ]);
    }

    // -------------- PRIVATE HELPERS --------------

    private function requireAdmin(): void
    {
        if (! Auth::user()?->isAdmin()) {
            abort(403, 'Hanya admin yang dapat melakukan aksi ini.');
        }
    }

    private function assertNoActiveOfficeSession(
        DriveAsixFile $file,
        OnlyOfficeEditorService $office
    ): void {
        if ($office->hasActiveSession($file)) {
            abort(
                423,
                'File sedang diedit melalui editor Office penuh. Tutup semua sesi editor sebelum menghapus file.'
            );
        }
    }

    private function assertFolderHasNoActiveOfficeSession(
        DriveAsixFolder $folder,
        OnlyOfficeEditorService $office
    ): void {
        foreach ($folder->files()->withTrashed()->get() as $file) {
            $this->assertNoActiveOfficeSession($file, $office);
        }

        foreach ($folder->children as $child) {
            $this->assertFolderHasNoActiveOfficeSession($child, $office);
        }
    }

    private function physicalPath(DriveAsixFile $file): string
    {
        abort_unless(basename($file->stored_name) === $file->stored_name, 404);
        $relativePath = self::BASE_PATH.'/'.$file->stored_name;
        abort_unless(Storage::disk(self::DISK)->exists($relativePath), 404);

        return Storage::disk(self::DISK)->path($relativePath);
    }

    private function normalizeFolderName(string $name): string
    {
        $name = trim($name);
        if ($name === ''
            || in_array($name, ['.', '..'], true)
            || preg_match('/[\x00-\x1F\x7F\/\\\\]/u', $name) === 1) {
            throw ValidationException::withMessages([
                'name' => 'Nama folder tidak boleh kosong atau memuat karakter kontrol, /, maupun \\.',
            ]);
        }

        return mb_substr($name, 0, 255);
    }

    private function normalizeFileName(
        string $name,
        string $expectedExtension,
        string $field = 'name'
    ): string {
        $name = trim($name);
        $expectedExtension = strtolower(trim($expectedExtension));
        if ($name === ''
            || in_array($name, ['.', '..'], true)
            || preg_match('/[\x00-\x1F\x7F\/\\\\]/u', $name) === 1) {
            throw ValidationException::withMessages([
                $field => 'Nama file tidak boleh kosong atau memuat karakter kontrol, /, maupun \\.',
            ]);
        }

        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if ($extension === '') {
            $name .= '.'.$expectedExtension;
        } elseif ($extension !== $expectedExtension) {
            throw ValidationException::withMessages([
                $field => 'Ekstensi file harus tetap .'.$expectedExtension.'.',
            ]);
        }

        $suffix = '.'.$expectedExtension;
        $stem = trim((string) pathinfo($name, PATHINFO_FILENAME));
        if ($stem === '') {
            throw ValidationException::withMessages([
                $field => 'Nama file sebelum ekstensi tidak boleh kosong.',
            ]);
        }

        $maximumStemLength = max(1, 255 - mb_strlen($suffix));

        return mb_substr($stem, 0, $maximumStemLength).$suffix;
    }

    private function safeDispositionFallback(string $name): string
    {
        $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', Str::ascii($name));
        $fallback = trim((string) $fallback, '._-');

        return $fallback !== '' ? $fallback : 'drive-asix-file';
    }

    private function validateUploadedContent(
        string $path,
        string $extension,
        int $index,
        SpreadsheetWorkbookService $workbooks
    ): void {
        if (in_array($extension, DriveAsixFile::SPREADSHEET_EXTENSIONS, true)) {
            $this->validateSpreadsheetUpload($path, $index, $workbooks);

            return;
        }

        $valid = match (true) {
            $extension === 'pdf' => file_get_contents($path, false, null, 0, 5) === '%PDF-',
            in_array($extension, DriveAsixFile::IMAGE_EXTENSIONS, true) => @getimagesize($path) !== false,
            $extension === 'docx' => $this->isZipArchiveSafe($path)
                && $this->hasZipEntry($path, 'word/document.xml')
                && ! $this->zipContainsVbaProject($path),
            $extension === 'pptx' => $this->isZipArchiveSafe($path)
                && $this->hasZipEntry($path, 'ppt/presentation.xml')
                && ! $this->zipContainsVbaProject($path),
            in_array($extension, DriveAsixFile::LEGACY_DOCUMENT_EXTENSIONS, true) => SpreadsheetFileFormatDetector::isValidOleContainer($path)
                && ! $this->oleContainsVbaProject($path),
            $extension === 'csv' => ! $this->containsNullByte($path),
            default => false,
        };

        if (! $valid) {
            throw ValidationException::withMessages([
                "files.{$index}" => 'Isi file tidak sesuai format .'.$extension
                    .' atau file rusak.',
            ]);
        }
    }

    private function validateSpreadsheetUpload(
        string $path,
        int $index,
        SpreadsheetWorkbookService $workbooks
    ): void {
        if ($this->hasZipSignature($path)) {
            $archiveRejection = $this->zipArchiveRejectionReason($path);
            if ($archiveRejection !== null) {
                throw ValidationException::withMessages([
                    "files.{$index}" => $archiveRejection,
                ]);
            }
        }

        try {
            $workbooks->validateUploadedWorkbook($path, true, false);
        } catch (DriveAsixWorkbookException $exception) {
            $message = trim($exception->getMessage());
            if ($message === ''
                || strlen($message) > 500
                || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $message) === 1) {
                $message = 'Isi file bukan workbook XLSX/XLS yang valid atau file rusak.';
            }

            throw ValidationException::withMessages([
                "files.{$index}" => $message,
            ]);
        }
    }

    private function hasZipSignature(string $path): bool
    {
        return file_get_contents($path, false, null, 0, 2) === 'PK';
    }

    private function isZipArchiveSafe(string $path): bool
    {
        return $this->zipArchiveRejectionReason($path) === null;
    }

    private function zipArchiveRejectionReason(string $path): ?string
    {
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            return 'Paket Office tidak dapat dibuka atau file rusak.';
        }

        try {
            if ($archive->numFiles < 1) {
                return 'Paket Office tidak memiliki bagian dokumen.';
            }

            if ($archive->numFiles > self::MAX_ZIP_ENTRIES) {
                return 'Paket Office memuat terlalu banyak bagian (maksimal 4.096 bagian).';
            }

            $totalUncompressed = 0;
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entry = $archive->statIndex($index);
                if (! is_array($entry) || ! isset($entry['name'])) {
                    return 'Struktur paket Office tidak dapat diverifikasi.';
                }

                $name = str_replace('\\', '/', (string) ($entry['name'] ?? ''));
                $size = filter_var($entry['size'] ?? null, FILTER_VALIDATE_INT);
                $compressedSize = filter_var(
                    $entry['comp_size'] ?? null,
                    FILTER_VALIDATE_INT
                );

                if ($name === ''
                    || str_contains($name, "\0")
                    || str_starts_with($name, '/')
                    || preg_match('/^[A-Za-z]:\//', $name) === 1
                    || preg_match('/(^|\/)\.\.(\/|$)/', $name) === 1
                ) {
                    return 'Paket Office memuat path bagian yang tidak aman.';
                }

                if ($size === false
                    || $compressedSize === false
                    || $size < 0
                    || $compressedSize < 0) {
                    return 'Ukuran bagian paket Office tidak dapat diverifikasi.';
                }

                if ($size > self::MAX_ZIP_ENTRY_BYTES) {
                    return 'Satu bagian paket Office melebihi batas aman 256 MB.';
                }

                if ($size >= self::ZIP_RATIO_INSPECTION_THRESHOLD
                    && ($compressedSize === 0
                        || $size > $compressedSize * self::MAX_ZIP_COMPRESSION_RATIO)) {
                    return 'Paket Office memiliki rasio kompresi tidak aman.';
                }

                $totalUncompressed += $size;
                if ($totalUncompressed > self::MAX_ZIP_UNCOMPRESSED_BYTES) {
                    return 'Total isi paket Office melebihi batas aman 768 MB.';
                }
            }

            return null;
        } finally {
            $archive->close();
        }
    }

    private function hasZipEntry(string $path, string $entry): bool
    {
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            return false;
        }

        try {
            return $archive->locateName($entry) !== false;
        } finally {
            $archive->close();
        }
    }

    private function zipContainsVbaProject(string $path): bool
    {
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            return true;
        }

        try {
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entry = $archive->statIndex($index);
                $name = strtolower(str_replace('\\', '/', (string) ($entry['name'] ?? '')));
                $size = (int) ($entry['size'] ?? 0);
                $baseName = basename($name);

                if (in_array($baseName, [
                    'vbaproject.bin',
                    'vbaprojectsignature.bin',
                    'vbadata.xml',
                ], true)
                    || str_contains($name, '/_vba_project')
                    || str_contains($name, '/macros/')) {
                    return true;
                }

                if ($name !== '[content_types].xml' && ! str_ends_with($name, '.rels')) {
                    continue;
                }

                // Metadata OOXML normal berukuran kecil. Tolak tertutup bila
                // metadata terlalu besar atau tidak dapat dibaca utuh.
                if ($size < 0 || $size > self::MAX_OOXML_METADATA_BYTES) {
                    return true;
                }

                $metadata = $archive->getFromIndex($index);
                if ($metadata === false) {
                    return true;
                }

                $metadata = strtolower($metadata);
                if (str_contains($metadata, 'macroenabled')
                    || str_contains($metadata, 'vbaproject')
                    || str_contains($metadata, 'vba-project')) {
                    return true;
                }
            }

            return false;
        } finally {
            $archive->close();
        }
    }

    private function oleContainsVbaProject(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return true;
        }

        $markers = [
            'strong' => [
                $this->utf16LeMarker('_VBA_PROJECT_CUR'),
                $this->utf16LeMarker('_VBA_PROJECT'),
                $this->utf16LeMarker('VBA_PROJECT'),
            ],
            'vba' => [$this->utf16LeMarker('VBA')],
            'project' => [
                $this->utf16LeMarker('PROJECTwm'),
                $this->utf16LeMarker('PROJECT'),
            ],
            'directory' => [$this->utf16LeMarker('dir')],
            'macros' => [$this->utf16LeMarker('Macros')],
        ];
        $found = [
            'vba' => false,
            'project' => false,
            'directory' => false,
            'macros' => false,
        ];
        $overlap = '';

        try {
            while (! feof($handle)) {
                $chunk = fread($handle, 1_048_576);
                if ($chunk === false) {
                    return true;
                }

                $haystack = $overlap.$chunk;
                foreach ($markers['strong'] as $marker) {
                    if (str_contains($haystack, $marker)) {
                        return true;
                    }
                }

                foreach ($found as $group => $isFound) {
                    if ($isFound) {
                        continue;
                    }
                    foreach ($markers[$group] as $marker) {
                        if (str_contains($haystack, $marker)) {
                            $found[$group] = true;
                            break;
                        }
                    }
                }

                if ($found['vba']
                    && $found['project']
                    && ($found['directory'] || $found['macros'])) {
                    return true;
                }

                $overlap = substr($haystack, -128);
            }

            return false;
        } finally {
            fclose($handle);
        }
    }

    private function utf16LeMarker(string $value): string
    {
        return implode("\0", str_split($value))."\0";
    }

    private function containsNullByte(string $path): bool
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return true;
        }

        try {
            while (! feof($handle)) {
                $chunk = fread($handle, 8192);
                if ($chunk === false || str_contains($chunk, "\0")) {
                    return true;
                }
            }

            return false;
        } finally {
            fclose($handle);
        }
    }

    private function safeMimeType(string $path, string $extension): string
    {
        $format = SpreadsheetFileFormatDetector::detect($path);
        if ($format === 'xlsx') {
            return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }
        if ($format === 'xls') {
            return 'application/vnd.ms-excel';
        }

        return match ($extension) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'doc', 'word' => 'application/msword',
            'ppt' => 'application/vnd.ms-powerpoint',
            'csv' => 'text/csv',
            default => (string) (mime_content_type($path) ?: 'application/octet-stream'),
        };
    }

    private function deletePhysicalFiles(
        DriveAsixFolder $folder,
        OnlyOfficeEditorService $office
    ): void {
        foreach ($folder->files()->withTrashed()->get() as $file) {
            $this->deleteStoredArtifacts($file, $office);
        }
        foreach ($folder->children as $child) {
            $this->deletePhysicalFiles($child, $office);
        }
    }

    private function deleteStoredArtifacts(
        DriveAsixFile $file,
        OnlyOfficeEditorService $office
    ): void {
        $lock = Cache::lock('drive-asix:workbook:'.$file->getKey(), 600);
        $sessionLock = Cache::lock(
            'drive-asix:office-session:'.$file->getKey(),
            600
        );

        try {
            $lock->block(8, function () use ($file, $office, $sessionLock): void {
                $sessionLock->block(8, function () use ($file, $office): void {
                    $this->assertNoActiveOfficeSession($file, $office);
                    $disk = Storage::disk(self::DISK);
                    if (basename($file->stored_name) !== $file->stored_name) {
                        throw new \RuntimeException('Nama file tersimpan tidak valid.');
                    }

                    $filePath = self::BASE_PATH.'/'.$file->stored_name;
                    $versionPath = self::VERSION_PATH.'/'.$file->getKey();
                    $officeSessionPath = self::OFFICE_SESSION_PATH.'/'.$file->getKey();
                    $quarantinePath = self::QUARANTINE_PATH.'/'
                        .$file->getKey().'-'.Str::uuid();
                    $movedArtifacts = [];

                    try {
                        if ($disk->exists($filePath)) {
                            $quarantinedFile = $quarantinePath.'/main';
                            $this->moveLocalStorageArtifact($filePath, $quarantinedFile);
                            $movedArtifacts[] = [
                                'source' => $filePath,
                                'quarantine' => $quarantinedFile,
                            ];
                        }

                        if ($disk->directoryExists($versionPath)) {
                            $quarantinedVersions = $quarantinePath.'/versions';
                            $this->moveLocalStorageArtifact($versionPath, $quarantinedVersions);
                            $movedArtifacts[] = [
                                'source' => $versionPath,
                                'quarantine' => $quarantinedVersions,
                            ];
                        }

                        if ($disk->directoryExists($officeSessionPath)) {
                            $quarantinedOfficeSessions = $quarantinePath.'/office-sessions';
                            $this->moveLocalStorageArtifact(
                                $officeSessionPath,
                                $quarantinedOfficeSessions
                            );
                            $movedArtifacts[] = [
                                'source' => $officeSessionPath,
                                'quarantine' => $quarantinedOfficeSessions,
                            ];
                        }

                        $file->getConnection()->transaction(function () use ($file): void {
                            if (! $file->forceDelete()) {
                                throw new \RuntimeException('Metadata file gagal dihapus.');
                            }
                        });
                    } catch (\Throwable $exception) {
                        $this->restoreQuarantinedArtifacts($movedArtifacts);
                        try {
                            if ($disk->directoryExists($quarantinePath)) {
                                $disk->deleteDirectory($quarantinePath);
                            }
                        } catch (\Throwable $cleanupException) {
                            report($cleanupException);
                        }
                        throw $exception;
                    }

                    // Setelah metadata berhasil dihapus, sisa karantina tidak lagi
                    // boleh membatalkan operasi. Kegagalan cleanup hanya menyisakan
                    // artefak yatim yang tidak dapat diakses pengguna.
                    try {
                        if ($disk->directoryExists($quarantinePath)
                            && (! $disk->deleteDirectory($quarantinePath)
                                || $disk->directoryExists($quarantinePath))) {
                            throw new \RuntimeException(
                                'Karantina DriveASIX gagal dibersihkan: '.$quarantinePath
                            );
                        }
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                });
            });
        } catch (LockTimeoutException) {
            abort(423, 'File sedang disimpan oleh pengguna lain. Coba hapus kembali beberapa detik lagi.');
        } catch (\Throwable $exception) {
            report($exception);
            abort(500, 'Penghapusan permanen gagal. Metadata dan file aktif dipertahankan.');
        }
    }

    private function moveLocalStorageArtifact(string $source, string $destination): void
    {
        $disk = Storage::disk(self::DISK);
        $sourcePath = $disk->path($source);
        $destinationPath = $disk->path($destination);
        $destinationDirectory = dirname($destinationPath);

        if (! is_dir($destinationDirectory)
            && ! @mkdir($destinationDirectory, 0755, true)
            && ! is_dir($destinationDirectory)) {
            throw new \RuntimeException(
                'Direktori karantina DriveASIX gagal dibuat.'
            );
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            if (@rename($sourcePath, $destinationPath)) {
                return;
            }

            clearstatcache(true, $sourcePath);
            clearstatcache(true, $destinationPath);
            if (! file_exists($sourcePath) && file_exists($destinationPath)) {
                return;
            }
        }

        throw new \RuntimeException(
            'Artefak DriveASIX gagal dipindahkan secara atomik di penyimpanan lokal.'
        );
    }

    /**
     * @param  array<int, array{source: string, quarantine: string}>  $movedArtifacts
     */
    private function restoreQuarantinedArtifacts(array $movedArtifacts): void
    {
        $restoreFailures = [];

        foreach (array_reverse($movedArtifacts) as $artifact) {
            try {
                $this->moveLocalStorageArtifact(
                    $artifact['quarantine'],
                    $artifact['source']
                );
            } catch (\Throwable $exception) {
                $restoreFailures[] = $exception->getMessage();
            }
        }

        if ($restoreFailures !== []) {
            throw new \RuntimeException(
                'Rollback artefak DriveASIX gagal: '.implode(' ', $restoreFailures)
            );
        }
    }

    private function buildFlatTree(?int $parentId = null, int $depth = 0): Collection
    {
        $result = collect();
        $folders = DriveAsixFolder::where('parent_id', $parentId)->orderBy('name')->get();
        foreach ($folders as $folder) {
            $folder->depth = $depth;
            $result->push($folder);
            $result = $result->merge($this->buildFlatTree($folder->id, $depth + 1));
        }

        return $result;
    }

    private function isSelfOrDescendant(DriveAsixFolder $folder, int $targetId): bool
    {
        if ($folder->id === $targetId) {
            return true;
        }
        foreach ($folder->children as $child) {
            if ($this->isSelfOrDescendant($child, $targetId)) {
                return true;
            }
        }

        return false;
    }
}
