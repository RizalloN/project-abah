<?php

namespace App\Exceptions;

use RuntimeException;

class DatabaseBackupAlreadyRunningException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Backup database harian lain masih berjalan.');
    }
}
