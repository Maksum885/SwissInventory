@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
    @php
        $fmtDelta = fn (int $d) => ($d > 0 ? '+' : '').$d;
        $inDelta = $cards['box_in_today'] - $cards['box_in_yesterday'];
        $outDelta = $cards['box_out_today'] - $cards['box_out_yesterday'];
    @endphp

    <div class="cards-row">
        <a href="{{ route('laporan.index') }}" class="card card-link">
            <div class="card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5Z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="8" y1="13" x2="16" y2="13"></line><line x1="8" y1="17" x2="16" y2="17"></line></svg></div>
            <div class="card-label">TOTAL ITEMS REGISTERED</div>
            <div class="card-value" id="cardTotalItem">{{ number_format($cards['total_item']) }}</div>
        </a>
        <a href="{{ route('laporan.index', ['tab' => 'stok']) }}" class="card card-link">
            <div class="card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2Z"></path></svg></div>
            <div class="card-label">TOP ITEM BY STOCK</div>
            @if ($topItem)
                <div class="card-value" id="cardTopItemQty">{{ number_format($topItem->qty_total) }} {{ $topItem->unit }}</div>
                <div class="card-sub" id="cardTopItemName">{{ $topItem->component_name }} &middot; {{ $topItem->component }}</div>
            @else
                <div class="card-value">&mdash;</div>
                <div class="card-sub">No items in stock yet</div>
            @endif
        </a>
        <a href="{{ route('laporan.index') }}" class="card card-link">
            <div class="card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"></rect><rect x="14" y="3" width="7" height="5" rx="1.5"></rect><rect x="14" y="12" width="7" height="9" rx="1.5"></rect><rect x="3" y="16" width="7" height="5" rx="1.5"></rect></svg></div>
            <div class="card-label">SLOTS FILLED</div>
            <div class="card-value"><span id="cardSlotTerisi">{{ $cards['slot_terisi'] }}</span> / {{ $cards['total_slot'] }}</div>
            <div class="card-sub"><span id="cardOkupansi">{{ $cards['okupansi_pct'] }}</span>% occupancy</div>
        </a>
        <a href="{{ route('transaksi', ['dari' => now()->toDateString(), 'sampai' => now()->toDateString()]) }}" class="card card-link">
            <div class="card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><polyline points="12 7 12 12 15.5 14"></polyline></svg></div>
            <div class="card-label">BOXES IN/OUT TODAY</div>
            <div class="card-value">
                <span class="stat-in" id="cardBoxIn">{{ $cards['box_in_today'] }}</span>
                <span class="stat-sep">/</span>
                <span class="stat-out" id="cardBoxOut">{{ $cards['box_out_today'] }}</span>
            </div>
            <div class="card-sub"><span class="stat-in">in</span> &middot; <span class="stat-out">out</span></div>
            <div class="card-sub" id="cardBoxDelta">vs yesterday: {{ $fmtDelta($inDelta) }} in &middot; {{ $fmtDelta($outDelta) }} out</div>
        </a>
    </div>

    <div class="panels-row">
        <div class="panel">
            <div class="panel-title">Utilization per rack</div>
            <div class="panel-caption">Number of filled slots out of 45 per rack, live from current slot status. Click a bar to open that rack.</div>
            <canvas id="chartRack" height="130"></canvas>
        </div>
        <div class="panel">
            <div class="panel-title-row">
                <div>
                    <div class="panel-title">In / out trend</div>
                    <div class="panel-caption">Number of distinct boxes moved in &amp; out per day, calculated from the transaction ledger.</div>
                </div>
                <div class="range-pills no-print">
                    @foreach ([7, 14, 30] as $d)
                        <a href="{{ route('dashboard', ['days' => $d]) }}" class="range-pill {{ $days === $d ? 'active' : '' }}">{{ $d }}D</a>
                    @endforeach
                </div>
            </div>
            <canvas id="chartTrend" height="130"></canvas>
        </div>
    </div>

    <div class="panel">
        <div class="panel-title">Item Overview</div>
        <div class="panel-caption">
            Current stock &amp; number of movements per item over the last {{ $days }} days, most active first
            @if ($cards['total_item'] > $itemOverview->count())
                &middot; showing top {{ $itemOverview->count() }} of {{ $cards['total_item'] }} items
            @endif
            &middot; <a href="{{ route('laporan.index', ['tab' => 'stok']) }}">view full stock report &rarr;</a>
        </div>
        @if ($itemOverview->isNotEmpty())
            <div class="item-overview-header" id="itemOverviewHeader">
                <span>Item</span>
                <span class="ta-right">Current Qty</span>
                <span class="ta-right">Movements ({{ $days }}D)</span>
            </div>
        @endif
        <div class="item-overview-list" id="itemOverviewList">
            @forelse ($itemOverview as $item)
                <div class="item-overview-row">
                    <span>{{ $item->component_name }}<span class="muted-sub">{{ $item->component }}</span></span>
                    <span class="ta-right mono">{{ number_format($item->qty_total) }} {{ $item->unit }}</span>
                    <span class="ta-right"><span class="item-move-badge mono">{{ $item->movement_count }}</span></span>
                </div>
            @empty
                <div class="table-empty-msg">No items yet.</div>
            @endforelse
        </div>
    </div>
@endsection

@push('scripts')
<script>
    Chart.defaults.font.family = 'Inter';
    Chart.defaults.color = '#64748B';

    const rackLabels = @json($utilization->pluck('rack'));
    const rackIndexUrl = "{{ route('rack.index') }}";
    const days = @json($days);

    const chartRack = new Chart(document.getElementById('chartRack'), {
        type: 'bar',
        data: { labels: rackLabels, datasets: [{ label: 'Slots filled', data: @json($utilization->pluck('filled')), backgroundColor: '#6366F1', borderRadius: 4, maxBarThickness: 28 }] },
        options: {
            plugins: { legend: { display: false } },
            onClick: (evt, elements) => {
                if (!elements.length) return;
                window.location.href = `${rackIndexUrl}?rack=${rackLabels[elements[0].index]}`;
            },
            onHover: (evt, elements) => { evt.native.target.style.cursor = elements.length ? 'pointer' : 'default'; },
            scales: {
                y: { min: 0, max: 45, ticks: { stepSize: 15, font: { family: 'JetBrains Mono', size: 10.5 } }, grid: { color: '#E2E8F0' } },
                x: { ticks: { font: { family: 'JetBrains Mono', size: 10.5 } }, grid: { display: false } }
            }
        }
    });

    const chartTrend = new Chart(document.getElementById('chartTrend'), {
        type: 'line',
        data: {
            labels: @json($trend['labels']),
            datasets: [
                { label: 'In', data: @json($trend['in']), borderColor: '#059669', backgroundColor: 'transparent', tension: 0.35, borderWidth: 2.5, pointRadius: 3, pointBackgroundColor: '#059669' },
                { label: 'Out', data: @json($trend['out']), borderColor: '#E11D48', backgroundColor: 'transparent', tension: 0.35, borderWidth: 2.5, pointRadius: 3, pointBackgroundColor: '#E11D48' }
            ]
        },
        options: {
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11, family: 'Inter' } } } },
            scales: {
                y: {
                    beginAtZero: true, suggestedMax: 5,
                    ticks: { font: { size: 10.5, family: 'JetBrains Mono' }, precision: 0, stepSize: 1 },
                    grid: { color: '#E2E8F0' },
                },
                x: { ticks: { font: { size: 10.5, family: 'Inter' } }, grid: { display: false } }
            }
        }
    });

    // Lightweight auto-refresh every 30 seconds — card numbers & chart data update in
    // place, without a page reload. This is what satisfies the original "realtime" goal;
    // the actual data writer (the WinForms app) writes straight to Postgres outside of
    // Laravel, so polling the DB is the most realistic approach (not websockets/broadcast).
    const fmt = n => Number(n).toLocaleString('en-US');
    const fmtDelta = n => (n > 0 ? '+' : '') + n;

    function renderItemOverview(items) {
        const list = document.getElementById('itemOverviewList');
        if (!list) return;
        if (!items.length) {
            list.innerHTML = '<div class="table-empty-msg">No items yet.</div>';
            return;
        }
        list.innerHTML = items.map(item => `
            <div class="item-overview-row">
                <span>${item.component_name}<span class="muted-sub">${item.component}</span></span>
                <span class="ta-right mono">${fmt(item.qty_total)} ${item.unit}</span>
                <span class="ta-right"><span class="item-move-badge mono">${item.movement_count}</span></span>
            </div>`).join('');
    }

    async function refreshDashboard() {
        try {
            const res = await fetch(`{{ route('dashboard.data') }}?days=${days}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) return;
            const d = await res.json();

            document.getElementById('cardTotalItem').textContent = fmt(d.cards.total_item);
            document.getElementById('cardSlotTerisi').textContent = fmt(d.cards.slot_terisi);
            document.getElementById('cardOkupansi').textContent = d.cards.okupansi_pct;
            document.getElementById('cardBoxIn').textContent = fmt(d.cards.box_in_today);
            document.getElementById('cardBoxOut').textContent = fmt(d.cards.box_out_today);
            document.getElementById('cardBoxDelta').textContent =
                `vs yesterday: ${fmtDelta(d.cards.box_in_today - d.cards.box_in_yesterday)} in · ${fmtDelta(d.cards.box_out_today - d.cards.box_out_yesterday)} out`;

            const topQtyEl = document.getElementById('cardTopItemQty');
            const topNameEl = document.getElementById('cardTopItemName');
            if (d.top_item && topQtyEl && topNameEl) {
                topQtyEl.textContent = `${fmt(d.top_item.qty_total)} ${d.top_item.unit}`;
                topNameEl.textContent = `${d.top_item.component_name} · ${d.top_item.component}`;
            }

            chartRack.data.datasets[0].data = d.utilization.map(u => u.filled);
            chartRack.update();

            chartTrend.data.labels = d.trend.labels;
            chartTrend.data.datasets[0].data = d.trend.in;
            chartTrend.data.datasets[1].data = d.trend.out;
            chartTrend.update();

            renderItemOverview(d.item_overview);
        } catch (e) { /* stay quiet, retry on the next interval */ }
    }
    setInterval(refreshDashboard, 30000);
</script>
@endpush
