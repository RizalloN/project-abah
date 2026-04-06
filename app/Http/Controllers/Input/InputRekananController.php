<?php

namespace App\Http\Controllers\Input;

use App\Http\Controllers\Controller;
use App\Models\InputRekanan;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class InputRekananController extends Controller
{
    public function index()
    {
        $recentInputs = Schema::hasTable('input_rekanan')
            ? InputRekanan::latest()->take(10)->get()
            : new Collection();

        return view('input.index', compact('recentInputs'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'rows_payload' => ['required', 'string'],
        ], [
            'rows_payload.required' => 'Data preview belum tersedia untuk disimpan.',
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

        InputRekanan::insert(array_map(function ($row) {
            return array_merge($row, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, $payload));

        return redirect()
            ->route('input.index')
            ->with('sweet_success', [
                'title' => 'Berhasil Disimpan',
                'text' => count($payload) . ' baris data berhasil disimpan ke tabel input_rekanan.',
            ]);
    }
}
