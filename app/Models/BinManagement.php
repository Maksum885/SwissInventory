<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Master lokasi bin penyimpanan (bukan status live robot — untuk itu lihat MatrixStorageBin
 * di koneksi pgsql_agv).
 */
class BinManagement extends Model
{
    protected $table = 'bin_management';

    public $timestamps = false;

    protected $fillable = [
        'bin_name',
        'rack',
        'status',
    ];

    protected $casts = [
        'upd_date' => 'datetime',
    ];
}
