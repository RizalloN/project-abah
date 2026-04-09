<?php

return [
    'dashboard_pinjaman' => [
        /*
        |--------------------------------------------------------------------------
        | Row-Level Rounding Base (Rupiah)
        |--------------------------------------------------------------------------
        | Nilai baki debet dapat dinormalisasi di level baris sebelum agregasi
        | pivot dashboard pinjaman. Set ke 1 (default) untuk presisi penuh
        | tanpa pembulatan/truncation. Set > 1 hanya jika diperlukan untuk
        | simulasi pembulatan berbasis kelipatan tertentu.
        */
        'row_rounding_base' => env('DASHBOARD_PINJAMAN_ROW_ROUNDING_BASE', 1),
    ],
];
