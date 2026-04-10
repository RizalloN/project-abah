<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | Most applications only need a single path for Blade templates. The
    | project keeps the default resources/views path and only customizes
    | the compiled path below for a more stable Windows local workflow.
    |
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | Windows can intermittently lock files under storage/framework/views
    | during active Apache + Vite + editor usage. Compiled Blade output is
    | moved to a dedicated cache folder to reduce rename/file-lock conflicts.
    |
    */

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        storage_path('framework/cache/blade')
    ),

];
