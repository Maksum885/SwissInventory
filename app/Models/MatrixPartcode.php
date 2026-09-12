<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Master part/produk. Relasi ke komponen penyusunnya ada di BomList (partcode -> component).
 */
class MatrixPartcode extends Model
{
    protected $table = 'matrix_partcode';

    public $timestamps = false;

    protected $fillable = [
        'partcode',
        'model_name',
        'moq',
        'ean_code',
        'model_type',
    ];

    protected $casts = [
        'moq' => 'integer',
        'update_time' => 'datetime',
    ];

    public function bomItems()
    {
        return $this->hasMany(BomList::class, 'partcode', 'partcode');
    }
}
