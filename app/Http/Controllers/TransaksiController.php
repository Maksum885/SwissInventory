<?php

namespace App\Http\Controllers;

use App\Services\RackTrackingService;
use Illuminate\Http\Request;

class TransaksiController extends Controller
{
    public function __construct(private RackTrackingService $rack) {}

    public function index(Request $request)
    {
        $dateFrom = $request->query('dari');
        $dateTo = $request->query('sampai');
        $rack = $request->query('rack');
        $event = $request->query('event'); // 'IN' | 'OUT' | null

        // Kolom yang boleh diurutkan lewat klik header tabel.
        $sort = in_array($request->query('sort'), ['waktu', 'event'], true) ? $request->query('sort') : 'waktu';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc'; // default: waktu terkini dulu

        $movements = $this->rack->transactionHistory($dateFrom, $dateTo, $rack, $event, 10, $sort, $dir);

        return view('transaksi.index', [
            'movements' => $movements,
            'racks' => RackTrackingService::RACKS,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'rack' => $rack,
            'event' => $event,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }
}
