<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bill of material: komponen penyusun tiap partcode dan qty kebutuhannya per unit produk.
 */
class BomList extends Model
{
    protected $table = 'bom_list';

    public $timestamps = false;

    protected $fillable = [
        'partcode',
        'component',
        'qty',
        'unit',
        'component_name',
    ];

    protected $casts = [
        'qty' => 'integer',
        'update_time' => 'datetime',
    ];

    public function part()
    {
        return $this->belongsTo(MatrixPartcode::class, 'partcode', 'partcode');
    }

    /**
     * Saldo stok komponen ini di gudang, dihitung dari ledger warehouse_stock.
     */
    public function stock()
    {
        return $this->belongsTo(WarehouseStock::class, 'component', 'component');
    }
}
