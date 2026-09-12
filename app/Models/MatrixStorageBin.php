<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Status fisik rak yang dikelola robot AGV (schema db_agv, domain robot — bukan punya
 * web app ini). READ-ONLY: save()/delete() sengaja diblok supaya tidak ada fitur di web
 * app ini yang tidak sengaja menulis ke data robot.
 */
class MatrixStorageBin extends Model
{
    protected $connection = 'pgsql_agv';

    protected $table = 'm_matrix_storage_bin';

    public $timestamps = false;

    protected $casts = [
        'upd_date' => 'datetime',
    ];

    public function save(array $options = [])
    {
        throw new RuntimeException('MatrixStorageBin bersifat read-only dari web app ini.');
    }

    public function delete()
    {
        throw new RuntimeException('MatrixStorageBin bersifat read-only dari web app ini.');
    }
}
