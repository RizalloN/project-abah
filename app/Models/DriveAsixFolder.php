<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriveAsixFolder extends Model
{
    protected $table = 'drive_asix_folders';

    protected $fillable = ['parent_id', 'name', 'created_by'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(DriveAsixFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(DriveAsixFolder::class, 'parent_id')->orderBy('name');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DriveAsixFile::class, 'folder_id')->orderBy('original_name');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Build breadcrumb path from root to this folder.
     */
    public function breadcrumbs(): array
    {
        $crumbs = [];
        $folder = $this;
        while ($folder) {
            array_unshift($crumbs, $folder);
            $folder = $folder->parent;
        }

        return $crumbs;
    }
}
