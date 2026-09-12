<?php

namespace App\Http\Controllers;

use App\Services\RackTrackingService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanController extends Controller
{
    /** Bagian laporan yang bisa dipilih user (juga dipakai sebagai nama tab di layar). */
    private const SECTIONS = ['stok', 'utilisasi', 'mutasi'];

    private const NEAR_FULL_PCT = 85;

    public function __construct(private RackTrackingService $rack) {}

    public function index(Request $request)
    {
        [$rackFilter, $search, $sortBy, $sortDir, $dateFrom, $dateTo, $sections, $activeTab] = $this->readFilters($request);

        $stockPerItem = $this->rack->stockPerItem($rackFilter, $search, $sortBy, $sortDir);
        $rackUtilization = $this->rack->rackUtilization();
        $mutationSummary = $this->rack->mutationSummary($dateFrom, $dateTo, $rackFilter, $search, $sortBy, $sortDir);
        $nearFullCount = $rackUtilization->where('pct', '>=', self::NEAR_FULL_PCT)->count();

        return view('laporan.index', [
            'stockPerItem' => $stockPerItem,
            'rackUtilization' => $rackUtilization,
            'mutationSummary' => $mutationSummary,
            'nearFullCount' => $nearFullCount,
            'racks' => RackTrackingService::RACKS,
            'rackFilter' => $rackFilter,
            'search' => $search,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'sections' => $sections,
            'activeTab' => $activeTab,
        ]);
    }

    /** Export ke CSV (dibuka Excel) — hanya bagian yang dicentang user, ikut filter/periode yang aktif. */
    public function exportExcel(Request $request): StreamedResponse
    {
        [$rackFilter, $search, $sortBy, $sortDir, $dateFrom, $dateTo, $sections] = $this->readFilters($request);
        $filename = 'inventory-report-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($rackFilter, $search, $sortBy, $sortDir, $dateFrom, $dateTo, $sections) {
            $out = fopen('php://output', 'w');

            if (in_array('stok', $sections, true)) {
                $items = $this->rack->stockPerItem($rackFilter, $search, $sortBy, $sortDir);
                fputcsv($out, ['REPORT A - STOCK PER ITEM']);
                fputcsv($out, ['Part Code', 'Item Name', 'Unit', 'Total Qty', 'Spread Across Racks']);
                foreach ($items as $item) {
                    fputcsv($out, [$item->component, $item->component_name, $item->unit, $item->qty_total, $item->racks->implode(', ')]);
                }
                fputcsv($out, []);
            }

            if (in_array('utilisasi', $sections, true)) {
                $rackUtilization = $this->rack->rackUtilization();
                fputcsv($out, ['REPORT B - RACK UTILIZATION (CURRENT)']);
                fputcsv($out, ['Rack', 'Slots Filled', 'Capacity', 'Occupancy (%)']);
                foreach ($rackUtilization as $u) {
                    fputcsv($out, [$u['rack'], $u['filled'], $u['capacity'], $u['pct']]);
                }
                fputcsv($out, []);
            }

            if (in_array('mutasi', $sections, true)) {
                $mutationSummary = $this->rack->mutationSummary($dateFrom, $dateTo, $rackFilter, $search, $sortBy, $sortDir);
                $periode = ($dateFrom || $dateTo) ? ' ('.($dateFrom ?: 'start').' to '.($dateTo ?: 'now').')' : '';
                fputcsv($out, ['REPORT C - MOVEMENT SUMMARY'.$periode]);
                fputcsv($out, ['Part Code', 'Item Name', 'Unit', 'Total In', 'Total Out', 'Net', 'Box Count']);
                foreach ($mutationSummary as $m) {
                    fputcsv($out, [$m->component, $m->component_name, $m->unit, $m->total_masuk, $m->total_keluar, $m->net, $m->jumlah_box]);
                }
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** Export ke PDF — hanya bagian yang dicentang user, ikut filter/periode yang aktif. */
    public function exportPdf(Request $request)
    {
        [$rackFilter, $search, $sortBy, $sortDir, $dateFrom, $dateTo, $sections] = $this->readFilters($request);

        $pdf = \Pdf::loadView('laporan.pdf', [
            'items' => in_array('stok', $sections, true) ? $this->rack->stockPerItem($rackFilter, $search, $sortBy, $sortDir) : null,
            'rackUtilization' => in_array('utilisasi', $sections, true) ? $this->rack->rackUtilization() : null,
            'mutationSummary' => in_array('mutasi', $sections, true) ? $this->rack->mutationSummary($dateFrom, $dateTo, $rackFilter, $search, $sortBy, $sortDir) : null,
            'rackFilter' => $rackFilter,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);

        return $pdf->download('inventory-report-'.now()->format('Y-m-d_His').'.pdf');
    }

    /** Baca & validasi filter/sortir/periode/tab/section dari query string — dipakai sama di layar & export. */
    private function readFilters(Request $request): array
    {
        $rackFilter = in_array($request->query('rack'), RackTrackingService::RACKS, true) ? $request->query('rack') : null;
        $search = trim((string) $request->query('q')) ?: null;
        $sortBy = in_array($request->query('sort'), ['sku', 'nama', 'qty', 'masuk', 'keluar', 'net', 'box'], true) ? $request->query('sort') : 'sku';
        $sortDir = $request->query('dir') === 'desc' ? 'desc' : 'asc';
        $dateFrom = trim((string) $request->query('dari')) ?: null;
        $dateTo = trim((string) $request->query('sampai')) ?: null;

        $activeTab = in_array($request->query('tab'), self::SECTIONS, true) ? $request->query('tab') : 'stok';

        // Kalau user belum pernah menyentuh checkbox unduhan sama sekali, defaultnya adalah
        // laporan yang SEDANG DILIHAT saja (bukan semuanya) — supaya "apa yang tampil di layar"
        // konsisten dengan "apa yang terunduh" tanpa user perlu mikir. Checkbox tidak tercentang
        // tidak terkirim di query string, jadi kalau param "sections" ada tapi kosong -> user
        // sengaja uncheck semua -> tetap fallback ke tab aktif (bukan pernah menghasilkan file kosong).
        $sections = $request->has('sections')
            ? array_values(array_intersect((array) $request->query('sections', []), self::SECTIONS))
            : [$activeTab];
        if (empty($sections)) {
            $sections = [$activeTab];
        }

        return [$rackFilter, $search, $sortBy, $sortDir, $dateFrom, $dateTo, $sections, $activeTab];
    }
}
