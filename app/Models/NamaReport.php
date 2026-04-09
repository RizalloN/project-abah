<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NamaReport extends Model
{
    protected $table = 'nama_report';

    protected $primaryKey = 'id_report';

    protected $fillable = [
        'nama_report',
        'table_name',
        'active',
        'import_controller',
        'requires_manual_periode',
        'manual_periode_type',
        'manual_periode_label',
        'manual_periode_help',
    ];

    public $timestamps = true;
}
