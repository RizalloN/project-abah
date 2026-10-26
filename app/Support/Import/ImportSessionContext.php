<?php

namespace App\Support\Import;

use Illuminate\Contracts\Session\Session;

/**
 * Konteks session satu upload/preview import.
 *
 * Flow import lama menyimpan report aktif, file, dan parameter manual di key
 * session tunggal. Upload kedua di tab lain menimpanya, sehingga preview
 * pertama mengimport ke report/file yang salah atau ditolak. Snapshot key ini
 * disimpan bersama preview state, lalu dipulihkan untuk request yang membawa
 * preview tersebut sehingga beberapa preview dapat diimport berbarengan.
 */
final class ImportSessionContext
{
    /** @var array<int, string> */
    public const KEYS = [
        'active_id_report',
        // Upload Excel/CSV via ImportExcelController & Simpanan CSV.
        'excel_path',
        'excel_preview_key',
        'excel_headers',
        'excel_import_source',
        'excel_manual_kanca',
        'excel_manual_periode',
        'excel_derived_kanca',
        'excel_derived_tahun',
        'excel_display_filter_map',
        'excel_preview_meta',
        // Upload CSV generik via ImportFileController.
        'import_files',
        'final_import_path',
        'import_display_to_source_map',
        'generic_import_manual_periode',
    ];

    /**
     * Hanya key yang benar-benar ada di session (termasuk yang bernilai null),
     * agar pemulihan tidak menghapus key milik flow lain.
     *
     * @return array<string, mixed>
     */
    public static function capture(Session $session): array
    {
        $all = $session->all();
        $context = [];
        foreach (self::KEYS as $key) {
            if (array_key_exists($key, $all)) {
                $context[$key] = $all[$key];
            }
        }

        return $context;
    }

    /**
     * Replika persis konteks preview: key konteks yang tidak ada saat snapshot
     * (mis. meta preview tab lain) ikut dibersihkan.
     *
     * @param  array<string, mixed>  $context
     */
    public static function restore(Session $session, array $context): void
    {
        $values = array_intersect_key($context, array_flip(self::KEYS));
        if ($values === []) {
            return;
        }

        $session->forget(array_values(array_diff(self::KEYS, array_keys($values))));
        $session->put($values);
    }
}
