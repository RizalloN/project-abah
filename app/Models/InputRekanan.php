<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InputRekanan extends Model
{
    protected $table = 'input_rekanan';

    protected $fillable = [
        'periode',
        'perusahaan_anak',
        'rekanan_level_1',
        'rekanan_level_2',
        'status_nasabah',
        'cif',
        'produk_1',
        'produk_2',
        'produk_3',
    ];

    public function scopeWithSaldoIdrSimpanan(Builder $query): Builder
    {
        return $query->addSelect([
            'saldo_idr_simpanan' => DB::table('simpanan_multipn')
                ->select('saldo_idr')
                ->whereRaw('`simpanan_multipn`.`CIFNO` COLLATE utf8mb4_unicode_ci = `input_rekanan`.`cif` COLLATE utf8mb4_unicode_ci')
                ->orderByDesc('posisi')
                ->orderByDesc('id')
                ->limit(1),
        ]);
    }
}
