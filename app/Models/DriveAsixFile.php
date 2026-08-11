<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DriveAsixFile extends Model
{
    use SoftDeletes;

    public const SPREADSHEET_EXTENSIONS = ['xlsx', 'xls'];

    public const TABULAR_EXTENSIONS = ['csv'];

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

    public const DOCUMENT_EXTENSIONS = ['docx', 'pptx'];

    public const LEGACY_DOCUMENT_EXTENSIONS = ['doc', 'ppt', 'word'];

    public const FULL_FIDELITY_EXTENSIONS = ['docx', 'pptx', 'xlsx'];

    public const ALLOWED_EXTENSIONS = [
        ...self::SPREADSHEET_EXTENSIONS,
        ...self::TABULAR_EXTENSIONS,
        ...self::IMAGE_EXTENSIONS,
        ...self::DOCUMENT_EXTENSIONS,
        ...self::LEGACY_DOCUMENT_EXTENSIONS,
        'pdf',
    ];

    protected $table = 'drive_asix_files';

    protected $fillable = [
        'folder_id', 'original_name', 'stored_name',
        'mime_type', 'size_bytes', 'uploaded_by',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(DriveAsixFolder::class, 'folder_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Human-readable file size.
     */
    public function humanSize(): string
    {
        $bytes = (int) $this->size_bytes;
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1).' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 0).' KB';
        }

        return $bytes.' B';
    }

    public function extension(): string
    {
        return strtolower((string) pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    public function isSpreadsheet(): bool
    {
        return in_array($this->extension(), self::SPREADSHEET_EXTENSIONS, true);
    }

    public function isTabular(): bool
    {
        return in_array($this->extension(), self::TABULAR_EXTENSIONS, true);
    }

    public function isPdf(): bool
    {
        return $this->extension() === 'pdf';
    }

    public function isImage(): bool
    {
        return in_array($this->extension(), self::IMAGE_EXTENSIONS, true);
    }

    public function hasLocalDocumentPreview(): bool
    {
        return in_array($this->extension(), self::DOCUMENT_EXTENSIONS, true);
    }

    public function supportsFullFidelityEditor(): bool
    {
        return in_array($this->extension(), self::FULL_FIDELITY_EXTENSIONS, true);
    }

    public function fallbackOpenMode(): string
    {
        return match (true) {
            $this->isSpreadsheet() => 'spreadsheet',
            $this->isPdf() => 'pdf',
            $this->isImage() => 'image',
            $this->hasLocalDocumentPreview() => 'document',
            default => 'download',
        };
    }

    public function openMode(): string
    {
        if ($this->supportsFullFidelityEditor()
            && (bool) config('services.onlyoffice.enabled', false)) {
            return 'office';
        }

        return $this->fallbackOpenMode();
    }

    /**
     * Return icon class and color based on mime type.
     */
    public function iconInfo(): array
    {
        $mime = $this->mime_type ?? '';
        $extension = $this->extension();

        if ($this->isPdf()) {
            return ['icon' => 'fas fa-file-pdf', 'color' => '#e53e3e'];
        }
        if ($this->isSpreadsheet() || $this->isTabular()) {
            return ['icon' => 'fas fa-file-excel', 'color' => '#38a169'];
        }
        if (in_array($extension, ['ppt', 'pptx'], true)) {
            return ['icon' => 'fas fa-file-powerpoint', 'color' => '#dd6b20'];
        }
        if (in_array($extension, ['doc', 'docx', 'word'], true)) {
            return ['icon' => 'fas fa-file-word', 'color' => '#3182ce'];
        }
        if ($this->isImage() || str_starts_with($mime, 'image/')) {
            return ['icon' => 'fas fa-file-image', 'color' => '#805ad5'];
        }
        if (str_contains($mime, 'zip') || str_contains($mime, 'rar') || str_contains($mime, 'compressed')) {
            return ['icon' => 'fas fa-file-archive', 'color' => '#d69e2e'];
        }
        if (str_starts_with($mime, 'text/')) {
            return ['icon' => 'fas fa-file-alt', 'color' => '#718096'];
        }

        return ['icon' => 'fas fa-file', 'color' => '#a0aec0'];
    }

    /**
     * Check if this file can be previewed inline.
     */
    public function isPreviewable(): bool
    {
        return $this->openMode() !== 'download';
    }
}
