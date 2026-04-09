<?php

namespace App\Http\Controllers\Input;

use App\Http\Controllers\Controller;
use App\Models\BodBoc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;

class BodBocController extends Controller
{
    public function importTemplate(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
            'periode' => ['required', 'date_format:Y-m-d'],
        ], [
            'file.required' => 'File template Nasabah Prioritas BOD BOC wajib dipilih.',
            'file.mimes' => 'File harus berformat Excel (.xlsx atau .xls).',
            'file.max' => 'Ukuran file maksimal 20MB.',
            'periode.required' => 'Periode wajib diisi.',
            'periode.date_format' => 'Format periode harus YYYY-MM-DD.',
        ]);

        $file = $validated['file'];

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return redirect()
                ->route('import.index')
                ->with('error', 'File Excel Nasabah Prioritas BOD BOC gagal dibaca. Pastikan file sesuai template.');
        }

        if (empty($rows)) {
            return redirect()
                ->route('import.index')
                ->with('error', 'File Excel Nasabah Prioritas BOD BOC kosong.');
        }

        $headers = collect(array_shift($rows))
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->values()
            ->all();

        $expectedHeaders = [
            'instansi',
            'bod/boc',
            'nama_nasabah',
            'ket_nasabah',
            'cif',
            'fasilitas_1',
            'fasilitas_2',
            'fasilitas_3',
        ];

        if ($headers !== $expectedHeaders) {
            return redirect()
                ->route('import.index')
                ->with('error', 'Format kolom Nasabah Prioritas BOD BOC tidak sesuai template.');
        }

        $previewRows = collect($rows)
            ->map(function ($row) {
                return [
                    'instansi' => trim((string) ($row[0] ?? '')),
                    'bod_boc' => trim((string) ($row[1] ?? '')),
                    'nama_nasabah' => trim((string) ($row[2] ?? '')),
                    'ket_nasabah' => trim((string) ($row[3] ?? '')),
                    'cif' => trim((string) ($row[4] ?? '')),
                    'fasilitas_1' => trim((string) ($row[5] ?? '')),
                    'fasilitas_2' => trim((string) ($row[6] ?? '')),
                    'fasilitas_3' => trim((string) ($row[7] ?? '')),
                ];
            })
            ->filter(function ($row) {
                return collect($row)->contains(fn ($value) => $value !== '');
            })
            ->values()
            ->all();

        if (empty($previewRows)) {
            return redirect()
                ->route('import.index')
                ->with('error', 'Tidak ada data yang bisa dipreview dari file Nasabah Prioritas BOD BOC.');
        }

        session([
            'bod_boc_preview_rows' => $previewRows,
            'bod_boc_preview_source_name' => $file->getClientOriginalName(),
            'bod_boc_preview_periode' => $validated['periode'],
        ]);

        return redirect()->route('bod-boc.import-preview');
    }

    public function previewImport()
    {
        $previewRows = collect(session('bod_boc_preview_rows', []))
            ->filter(fn ($row) => is_array($row))
            ->values()
            ->all();

        if (empty($previewRows)) {
            return redirect()
                ->route('import.index')
                ->with('error', 'Preview Nasabah Prioritas BOD BOC tidak ditemukan. Silakan upload ulang file template.');
        }

        $sourceName = (string) session('bod_boc_preview_source_name', 'template-nasabah-prioritas-bod-boc.xlsx');
        $periode = (string) session('bod_boc_preview_periode', '');

        if ($periode === '') {
            return redirect()
                ->route('import.index')
                ->with('error', 'Periode Nasabah Prioritas BOD BOC tidak ditemukan. Silakan upload ulang file template.');
        }

        return view('input.bod-boc-import-preview', compact('previewRows', 'sourceName', 'periode'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'rows_payload' => ['required', 'string'],
            'periode' => ['required', 'date_format:Y-m-d'],
        ], [
            'rows_payload.required' => 'Data preview belum tersedia untuk disimpan.',
            'periode.required' => 'Periode wajib diisi.',
            'periode.date_format' => 'Format periode harus YYYY-MM-DD.',
        ]);

        $rows = json_decode($validated['rows_payload'], true);

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
                'periode' => $validated['periode'],
                'instansi' => trim((string) ($row['instansi'] ?? '')),
                'bod_boc' => trim((string) ($row['bod_boc'] ?? '')),
                'nama_nasabah' => trim((string) ($row['nama_nasabah'] ?? '')),
                'ket_nasabah' => trim((string) ($row['ket_nasabah'] ?? '')),
                'cif' => trim((string) ($row['cif'] ?? '')),
                'fasilitas_1' => trim((string) ($row['fasilitas_1'] ?? '')),
                'fasilitas_2' => trim((string) ($row['fasilitas_2'] ?? '')),
                'fasilitas_3' => trim((string) ($row['fasilitas_3'] ?? '')),
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

        if (!Schema::hasTable('bod_boc')) {
            return back()->with('sweet_warning', [
                'title' => 'Tabel Belum Tersedia',
                'text' => 'Tabel bod_boc belum ada. Jalankan migration terlebih dahulu.',
            ]);
        }

        BodBoc::insert(array_map(function ($row) {
            return array_merge($row, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, $payload));

        session()->forget(['bod_boc_preview_rows', 'bod_boc_preview_source_name', 'bod_boc_preview_periode']);

        return redirect()
            ->route('import.index', [
                'import_notice' => 'bod_boc_success',
                'import_rows' => count($payload),
            ])
            ->with('sweet_success', [
                'title' => 'Berhasil Disimpan',
                'text' => count($payload) . ' baris data berhasil disimpan ke tabel bod_boc.',
            ]);
    }
}
