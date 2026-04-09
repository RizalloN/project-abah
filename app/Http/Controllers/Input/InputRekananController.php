<?php

namespace App\Http\Controllers\Input;

use App\Http\Controllers\Controller;
use App\Models\InputRekanan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;

class InputRekananController extends Controller
{
    public function index()
    {
        return redirect()->route('import.index');
    }

    public function importTemplate(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
            'periode' => ['required', 'date_format:Y-m-d'],
        ], [
            'file.required' => 'File template Input Rekanan wajib dipilih.',
            'file.mimes' => 'File harus berformat Excel (.xlsx atau .xls).',
            'file.max' => 'Ukuran file maksimal 20MB.',
            'periode.required' => 'Periode Input Rekanan wajib diisi.',
            'periode.date_format' => 'Format periode Input Rekanan harus YYYY-MM-DD.',
        ]);

        $file = $validated['file'];
        $periode = $validated['periode'];

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return redirect()
                ->route('import.index')
                ->with('error', 'File Excel Input Rekanan gagal dibaca. Pastikan file sesuai template.');
        }

        if (empty($rows)) {
            return redirect()
                ->route('import.index')
                ->with('error', 'File Excel Input Rekanan kosong.');
        }

        $headers = collect(array_shift($rows))
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->values()
            ->all();

        $expectedHeaders = [
            'perusahaan_anak',
            'rekanan_level_1',
            'rekanan_level_2',
            'status_nasabah',
            'cif',
            'produk_1',
            'produk_2',
            'produk_3',
        ];

        if ($headers !== $expectedHeaders) {
            return redirect()
                ->route('import.index')
                ->with('error', 'Format kolom Input Rekanan tidak sesuai template.');
        }

        $previewRows = collect($rows)
            ->map(function ($row) use ($expectedHeaders) {
                $mapped = [];

                foreach ($expectedHeaders as $index => $header) {
                    $mapped[$header] = trim((string) ($row[$index] ?? ''));
                }

                return $mapped;
            })
            ->filter(function ($row) {
                return collect($row)->contains(fn ($value) => $value !== '');
            })
            ->values()
            ->all();

        if (empty($previewRows)) {
            return redirect()
                ->route('import.index')
                ->with('error', 'Tidak ada data yang bisa dipreview dari file Input Rekanan.');
        }

        session([
            'input_rekanan_preview_rows' => $previewRows,
            'input_rekanan_preview_source_name' => $file->getClientOriginalName(),
            'input_rekanan_preview_periode' => $periode,
        ]);

        return redirect()->route('input.import-preview');
    }

    public function previewImport()
    {
        $previewRows = collect(session('input_rekanan_preview_rows', []))
            ->filter(fn ($row) => is_array($row))
            ->values()
            ->all();

        if (empty($previewRows)) {
            return redirect()
                ->route('import.index')
                ->with('error', 'Preview Input Rekanan tidak ditemukan. Silakan upload ulang file template.');
        }

        $sourceName = (string) session('input_rekanan_preview_source_name', 'template-input-rekanan.xlsx');
        $periode = (string) session('input_rekanan_preview_periode', '');

        return view('input.import-preview', compact('previewRows', 'sourceName', 'periode'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'rows_payload' => ['required', 'string'],
            'periode' => ['required', 'date_format:Y-m-d'],
        ], [
            'rows_payload.required' => 'Data preview belum tersedia untuk disimpan.',
            'periode.required' => 'Periode Input Rekanan wajib diisi.',
            'periode.date_format' => 'Format periode Input Rekanan harus YYYY-MM-DD.',
        ]);

        $rows = json_decode($validated['rows_payload'], true);
        $periode = $validated['periode'];

        if (!is_array($rows) || empty($rows)) {
            return back()
                ->withInput()
                ->with('sweet_warning', [
                    'title' => 'Data Tidak Valid',
                    'text' => 'Preview data belum terbentuk atau formatnya tidak sesuai.',
                ]);
        }

        $payload = [];

        foreach ($rows as $row) {
            $normalized = [
                'periode' => $periode,
                'perusahaan_anak' => trim((string) ($row['perusahaan_anak'] ?? '')),
                'rekanan_level_1' => trim((string) ($row['rekanan_level_1'] ?? '')),
                'rekanan_level_2' => trim((string) ($row['rekanan_level_2'] ?? '')),
                'status_nasabah' => trim((string) ($row['status_nasabah'] ?? '')),
                'cif' => trim((string) ($row['cif'] ?? '')),
                'produk_1' => trim((string) ($row['produk_1'] ?? '')),
                'produk_2' => trim((string) ($row['produk_2'] ?? '')),
                'produk_3' => trim((string) ($row['produk_3'] ?? '')),
            ];

            if (collect($normalized)->filter(fn ($value) => $value !== '')->isEmpty()) {
                continue;
            }

            $payload[] = $normalized;
        }

        if (empty($payload)) {
            return back()
                ->withInput()
                ->with('sweet_warning', [
                    'title' => 'Data Kosong',
                    'text' => 'Tidak ada baris berisi data yang bisa disimpan ke database.',
                ]);
        }

        if (!Schema::hasTable('input_rekanan')) {
            return back()->with('sweet_warning', [
                'title' => 'Tabel Belum Tersedia',
                'text' => 'Tabel input_rekanan belum ada. Jalankan migration terlebih dahulu.',
            ]);
        }

        $cifValues = collect($payload)
            ->pluck('cif')
            ->map(fn ($cif) => trim((string) $cif))
            ->filter(fn ($cif) => $cif !== '')
            ->values();

        $duplicateCifInPayload = $cifValues
            ->countBy()
            ->filter(fn ($count) => $count > 1)
            ->keys()
            ->values();

        if ($duplicateCifInPayload->isNotEmpty()) {
            return back()
                ->withInput()
                ->with('sweet_warning', [
                    'title' => 'CIF Duplikat di File',
                    'text' => 'Data ditolak karena CIF berikut muncul lebih dari satu kali pada file import: ' . $duplicateCifInPayload->join(', ') . '.',
                ]);
        }

        $hasSamePeriode = DB::table('input_rekanan')
            ->whereDate('periode', $periode)
            ->exists();

        $existingCif = DB::table('input_rekanan')
            ->whereIn('cif', $cifValues->unique()->all())
            ->pluck('cif')
            ->map(fn ($cif) => trim((string) $cif))
            ->filter(fn ($cif) => $cif !== '')
            ->unique()
            ->values();

        if ($hasSamePeriode || $existingCif->isNotEmpty()) {
            $reasons = [];

            if ($hasSamePeriode) {
                $reasons[] = 'periode ' . $periode . ' sudah ada di database';
            }

            if ($existingCif->isNotEmpty()) {
                $reasons[] = 'CIF berikut sudah ada di database: ' . $existingCif->join(', ');
            }

            return back()
                ->withInput()
                ->with('sweet_warning', [
                    'title' => 'Data Duplikat',
                    'text' => 'Data ditolak karena ' . implode('; ', $reasons) . '.',
                ]);
        }

        InputRekanan::insert(array_map(function ($row) {
            return array_merge($row, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, $payload));

        session()->forget(['input_rekanan_preview_rows', 'input_rekanan_preview_source_name', 'input_rekanan_preview_periode']);

        return redirect()
            ->route('import.index', [
                'import_notice' => 'input_rekanan_success',
                'import_rows' => count($payload),
            ])
            ->with('sweet_success', [
                'title' => 'Berhasil Disimpan',
                'text' => count($payload) . ' baris data periode ' . $periode . ' berhasil disimpan ke tabel input_rekanan.',
            ]);
    }
}
