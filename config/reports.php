<?php

return [
    'dashboard_harian' => [
        /*
        |--------------------------------------------------------------------------
        | Hourly DPK as Morning Savings Source
        |--------------------------------------------------------------------------
        | When enabled, Dashboard Harian can use hourly_dpk as the savings
        | source for periods that already have Hourly DPK rows.
        */
        'use_hourly_dpk' => env('DASHBOARD_HARIAN_USE_HOURLY_DPK', true),
    ],

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
