<?php

namespace App\Http\Controllers;

use App\Models\MatrixStorageBin;
use App\Services\RackTrackingService;
use Illuminate\Http\Request;

class RackMonitoringController extends Controller
{
    public function __construct(private RackTrackingService $rack) {}

    public function index(Request $request)
    {
        $racks = RackTrackingService::RACKS;
        $activeRack = $request->query('rack', $racks[0]);
        if (! in_array($activeRack, $racks, true)) {
            $activeRack = $racks[0];
        }

        $slots = $this->rack->slotsForRack($activeRack);

        // Bentuk ringkas untuk dipakai scene 3D "tampak depan" rak terpilih.
        $slotsData = $slots->map(fn ($bin) => [
            'col' => (int) str_replace('C', '', (string) $bin->column_no),
            'layer' => (int) $bin->stack,
            'status' => $bin->status,
            'storage_bin' => $bin->storage_bin,
            'code' => $this->rack->slotCode($bin),
        ])->values();

        return view('rack.index', [
            'racks' => $racks,
            'activeRack' => $activeRack,
            // true kalau rack dipilih lewat tab (?rack=...), false kalau baru masuk menu.
            // Dipakai untuk memutuskan apakah rack lain di 3D di-abu-abukan.
            'rackExplicit' => $request->has('rack'),
            'slots' => $slots,
            'slotsData' => $slotsData,
            'columns' => RackTrackingService::COLUMNS,
            'rows' => RackTrackingService::ROWS,
            'highlightCode' => $request->query('highlight'),
        ]);
    }

    /** JSON: daftar slot untuk satu rack (dipanggil saat ganti tab rack). */
    public function slots(string $rack)
    {
        $slots = $this->rack->slotsForRack($rack)->map(fn ($bin) => [
            'code' => $this->rack->slotCode($bin),
            'storage_bin' => $bin->storage_bin,
            'status' => $bin->status,
            'qr_id' => $bin->qr_id,
        ]);

        return response()->json($slots);
    }

    /** JSON: status ringkas SEMUA rack sekaligus, dipakai visualisasi 3D overview. */
    public function allSlots()
    {
        $result = collect(RackTrackingService::RACKS)->mapWithKeys(function ($rack) {
            $slots = $this->rack->slotsForRack($rack)->map(fn ($bin) => [
                'col' => (int) str_replace('C', '', (string) $bin->column_no),
                'layer' => (int) $bin->stack,
                'status' => $bin->status,
                'code' => $this->rack->slotCode($bin),
            ]);

            return [$rack => $slots];
        });

        return response()->json($result);
    }

    /** JSON: detail satu slot + isi box-nya, dipanggil saat slot diklik. */
    public function slotDetail(string $storageBin)
    {
        $bin = MatrixStorageBin::where('storage_bin', $storageBin)->firstOrFail();

        return response()->json([
            'code' => $this->rack->slotCode($bin),
            'storage_bin' => $bin->storage_bin,
            'status' => $bin->status,
            'qr_id' => $bin->qr_id,
            'upd_date' => optional($bin->upd_date)->format('Y-m-d H:i'),
            'items' => $bin->qr_id ? $this->rack->boxContents($bin->qr_id)->values() : [],
        ]);
    }

    /** JSON: pencarian kode box / nama-SKU item -> lokasi rak & slot. */
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $results = $this->rack->search($q)->map(fn ($bin) => [
            'code' => $this->rack->slotCode($bin),
            'rack' => $bin->side,
            'storage_bin' => $bin->storage_bin,
            'qr_id' => $bin->qr_id,
            'status' => $bin->status,
        ]);

        return response()->json($results);
    }
}
