<?php

namespace App\Exceptions;

use RuntimeException;

class DriveAsixVersionConflictException extends RuntimeException
{
    public function __construct(public readonly string $currentRevision)
    {
        parent::__construct('File telah berubah sejak terakhir dibuka. Muat ulang file sebelum menyimpan.');
    }
}
