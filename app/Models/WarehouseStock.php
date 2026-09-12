<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ledger mutasi stok gudang. Setiap baris adalah SATU transaksi (IN/OUT/NEW REGISTER),
 * BUKAN saldo akhir. Saldo per component dihitung via SUM(qty) GROUP BY component
 * (lihat scope stockSummary()). qty positif = stock masuk, negatif = stock keluar.
 *
 * Pola ini meniru persis logika yang sudah dipakai aplikasi WinForms (FormInbound.cs)
 * supaya kedua aplikasi tetap konsisten membaca riwayat yang sama.
 */
class WarehouseStock extends Model
{
    protected $table = 'warehouse_stock';

    // Tabel ini tidak punya kolom created_at/updated_at Laravel — pakai update_date sendiri.
    public $timestamps = false;

    protected $fillable = [
        'component',
        'qty',
        'unit',
        'component_name',
        'bin_name',
        'remark',
    ];

    protected $casts = [
        'qty' => 'integer',
        'update_date' => 'datetime',
    ];

    /**
     * Saldo stok per component (SUM qty), dikelompokkan seperti query
     * yang dipakai FormInbound.cs / FormMenu1.cs di aplikasi WinForms.
     */
    public function scopeStockSummary($query)
    {
        return $query->selectRaw('component, component_name, unit, SUM(qty) as stock')
            ->groupBy('component', 'component_name', 'unit');
    }
}
