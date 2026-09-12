<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1E2226; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        .sub { color: #5B6470; margin-bottom: 16px; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #D8DCDE; padding: 6px 8px; text-align: left; }
        th { background: #F7F8F8; }
        h2 { font-size: 13px; margin: 18px 0 8px; }
        .in { color: #2E8357; }
        .out { color: #C1503F; }
    </style>
</head>
<body>
    <h1>SWIS Inventory Tracking — Inventory Report</h1>
    <div class="sub">
        Printed: {{ now()->format('d M Y H:i') }}
        @if ($rackFilter) &middot; Rack filter: {{ $rackFilter }} @endif
        @if ($search) &middot; Search: "{{ $search }}" @endif
        @if ($dateFrom || $dateTo) &middot; Movement period: {{ $dateFrom ?: 'start' }} to {{ $dateTo ?: 'now' }} @endif
    </div>

    @if (! is_null($items))
        <h2>Report A &middot; Stock per Item</h2>
        <table>
            <thead><tr><th>Part Code</th><th>Item Name</th><th>Total Qty</th><th>Spread Across Racks</th></tr></thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>{{ $item->component }}</td>
                        <td>{{ $item->component_name }}</td>
                        <td>{{ number_format($item->qty_total) }} {{ $item->unit }}</td>
                        <td>{{ $item->racks->implode(', ') ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">No data.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif

    @if (! is_null($rackUtilization))
        <h2>Report B &middot; Rack Utilization (current)</h2>
        <table>
            <thead><tr><th>Rack</th><th>Slots Filled</th><th>Occupancy</th></tr></thead>
            <tbody>
                @foreach ($rackUtilization as $u)
                    <tr>
                        <td>{{ $u['rack'] }}</td>
                        <td>{{ $u['filled'] }} / {{ $u['capacity'] }}</td>
                        <td>{{ $u['pct'] }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (! is_null($mutationSummary))
        <h2>Report C &middot; Movement Summary</h2>
        <table>
            <thead><tr><th>Part Code</th><th>Item Name</th><th>Total In</th><th>Total Out</th><th>Net</th><th>Box Count</th></tr></thead>
            <tbody>
                @forelse ($mutationSummary as $m)
                    <tr>
                        <td>{{ $m->component }}</td>
                        <td>{{ $m->component_name }}</td>
                        <td class="in">+{{ number_format($m->total_masuk) }} {{ $m->unit }}</td>
                        <td class="out">-{{ number_format($m->total_keluar) }} {{ $m->unit }}</td>
                        <td>{{ $m->net > 0 ? '+' : '' }}{{ number_format($m->net) }} {{ $m->unit }}</td>
                        <td>{{ $m->jumlah_box }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No movement in this period/filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif
</body>
</html>
