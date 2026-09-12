<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') — SWIS Inventory Tracking</title>
    <link rel="icon" href="{{ asset('images/polibatam.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    @vite(['resources/css/app.css'])
</head>
<body>
<div class="app">
    <div class="sidebar">
        <div class="brand-plate brand-plate-top">
            <img src="{{ asset('images/polibatam.png') }}" alt="Politeknik Negeri Batam">
        </div>
        <div class="nav">
            <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"></rect><rect x="14" y="3" width="7" height="5" rx="1.5"></rect><rect x="14" y="12" width="7" height="9" rx="1.5"></rect><rect x="3" y="16" width="7" height="5" rx="1.5"></rect></svg>
                Dashboard
            </a>
            <a href="{{ route('rack.index') }}" class="nav-item {{ request()->routeIs('rack.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4a2 2 0 0 0 1-1.73Z"></path><path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path></svg>
                Rack Monitoring
            </a>
            <a href="{{ route('transaksi') }}" class="nav-item {{ request()->routeIs('transaksi') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><polyline points="12 7 12 12 15.5 14"></polyline></svg>
                Transaction History
            </a>
            <a href="{{ route('laporan.index') }}" class="nav-item {{ request()->routeIs('laporan.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5Z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="8" y1="13" x2="16" y2="13"></line><line x1="8" y1="17" x2="16" y2="17"></line></svg>
                Reports
            </a>
        </div>
        <div class="brand-plate brand-plate-bottom">
            <img src="{{ asset('images/MMG-Logo.png') }}" alt="Multi Mitra Guna">
        </div>
    </div>

    <div class="main">
        <div class="topbar">
            <div class="topbar-title">@yield('title', 'Dashboard')</div>
            <div class="topbar-meta" id="clock"></div>
        </div>
        <div class="content">
            @yield('content')
        </div>
    </div>
</div>

<script>
    function tickClock(){
        const d = new Date();
        const dateStr = d.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'}).toUpperCase().replace(/\./g,'');
        const pad = n => String(n).padStart(2, '0');
        const timeStr = `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
        document.getElementById('clock').textContent = dateStr + ' · ' + timeStr;
    }
    tickClock(); setInterval(tickClock, 1000);
</script>
@stack('scripts')
</body>
</html>
