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
    ];

    public $timestamps = true;
}