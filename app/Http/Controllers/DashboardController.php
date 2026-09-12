<?php

namespace App\Http\Controllers;

use App\Services\RackTrackingService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Rentang tren in/out yang bisa dipilih user. */
    private const TREND_DAYS = [7, 14, 30];

    public function __construct(private RackTrackingService $rack) {}

    public function index(Request $request)
    {
        $days = $this->readDays($request);

        $cards = $this->rack->dashboardCards();
        $utilization = $this->rack->rackUtilization();
        $trend = $this->rack->inOutTrend($days);
        $topItem = $this->rack->topItemByStock();
        $itemOverview = $this->rack->itemOverview($days);

        return view('dashboard.index', compact('cards', 'utilization', 'trend', 'topItem', 'itemOverview', 'days'));
    }

    /** JSON: dipakai auto-refresh dashboard (polling) tanpa reload halaman. */
    public function data(Request $request)
    {
        $days = $this->readDays($request);

        return response()->json([
            'cards' => $this->rack->dashboardCards(),
            'utilization' => $this->rack->rackUtilization(),
            'trend' => $this->rack->inOutTrend($days),
            'top_item' => $this->rack->topItemByStock(),
            'item_overview' => $this->rack->itemOverview($days),
        ]);
    }

    private function readDays(Request $request): int
    {
        $days = (int) $request->query('days');

        return in_array($days, self::TREND_DAYS, true) ? $days : 7;
    }
}
