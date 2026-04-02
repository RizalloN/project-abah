<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InputRekanan extends Model
{
    protected $table = 'input_rekanan';

    protected $fillable = [
        'perusahaan_anak',
        'rekanan_level_1',
        'rekanan_level_2',
        'status_nasabah',
        'cif',
        'produk_1',
        'produk_2',
        'produk_3',
    ];
}
