<?php

namespace App\Http\Controllers;

use Throwable;

abstract class Controller
{
    /**
     * Release the underlying PHP session lock as early as possible for
     * read-heavy endpoints so other page requests are not blocked.
     */
    protected function releaseSessionLockIfNeeded(): void
    {
        try {
            if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
                return;
            }

            if (function_exists('request') && request()->hasSession()) {
                request()->session()->save();
            }
        } catch (Throwable) {
            // Ignore session lock release failures.
        }
    }
}
