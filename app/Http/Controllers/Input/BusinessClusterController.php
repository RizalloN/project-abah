<?php

namespace App\Http\Controllers\Input;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BusinessClusterController extends Controller
{
    private const TABLE = 'business_cluster';

    private const KANCA_OPTIONS = [
        'KC Madiun',
        'KC Magetan',
        'KC Ngawi',
        'KC Ponorogo',
    ];

    public function store(Request $request)
    {
        if (!$request->filled('nama_kanca') && $request->filled('kanca_manual')) {
            $request->merge(['nama_kanca' => $request->input('kanca_manual')]);
        }

        $validated = $request->validate([
            'nama_kanca' => ['required', 'string', Rule::in(self::KANCA_OPTIONS)],
            'link_url' => ['required', 'url', 'max:2048'],
        ], [
            'nama_kanca.required' => 'Nama kanca wajib dipilih.',
            'nama_kanca.in' => 'Nama kanca tidak valid.',
            'link_url.required' => 'Link URL spreadsheet wajib diisi.',
            'link_url.url' => 'Link URL harus berupa URL yang valid.',
            'link_url.max' => 'Link URL terlalu panjang.',
        ]);

        $linkUrl = trim((string) $validated['link_url']);
        if (!Str::startsWith($linkUrl, ['http://', 'https://'])) {
            return back()
                ->withInput()
                ->with('sweet_warning', [
                    'title' => 'Link Tidak Valid',
                    'text' => 'Link spreadsheet harus diawali http:// atau https://.',
                ]);
        }

        if (!Schema::hasTable(self::TABLE)) {
            return redirect()
                ->route('import.index')
                ->with('sweet_warning', [
                    'title' => 'Tabel Belum Tersedia',
                    'text' => 'Tabel business_cluster belum ada. Jalankan migration terlebih dahulu.',
                ]);
        }

        $namaKanca = $validated['nama_kanca'];

        DB::table(self::TABLE)->updateOrInsert(
            ['nama_kanca' => $namaKanca],
            [
                'uniqueid_namareport' => $this->buildUniqueId($namaKanca),
                'link_url' => $linkUrl,
            ]
        );
        Cache::forget('report:business_cluster:v2:' . md5($namaKanca . '|' . $linkUrl));

        return redirect()
            ->route('import.index')
            ->with('sweet_success', [
                'title' => 'Link Business Cluster Disimpan',
                'text' => 'Link spreadsheet untuk ' . $namaKanca . ' sudah tersimpan.',
            ]);
    }

    private function buildUniqueId(string $namaKanca): string
    {
        return 'business_cluster_' . Str::slug($namaKanca, '_');
    }
}
