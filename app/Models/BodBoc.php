<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BodBoc extends Model
{
    protected $table = 'bod_boc';

    protected $fillable = [
        'periode',
        'instansi',
        'bod_boc',
        'nama_nasabah',
        'ket_nasabah',
        'cif',
        'fasilitas_1',
        'fasilitas_2',
        'fasilitas_3',
    ];

    public function scopeWithSaldoIdrSimpanan(Builder $query): Builder
    {
        return $query->addSelect([
            'saldo_idr_simpanan' => DB::table('simpanan_multipn')
                ->select('saldo_idr')
                ->whereRaw('`simpanan_multipn`.`CIFNO` COLLATE utf8mb4_unicode_ci = `bod_boc`.`cif` COLLATE utf8mb4_unicode_ci')
                ->orderByDesc('posisi')
                ->orderByDesc('id')
                ->limit(1),
        ]);
    }
}
