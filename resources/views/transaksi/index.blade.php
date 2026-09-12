@extends('layouts.app')
@section('title', 'Transaction History')

@section('content')
    @php
        // Build the sort-toggle URL: clicking the same column flips direction, a different column starts at asc.
        $sortUrl = fn (string $col) => request()->fullUrlWithQuery([
            'sort' => $col,
            'dir' => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc',
            'page' => null,
        ]);
        $sortIcon = fn (string $col) => $sort !== $col ? '' : ($dir === 'asc' ? ' &uarr;' : ' &darr;');
    @endphp

    <form method="GET" class="filters">
        <div class="filter-field">
            <label for="f-dari">From date</label>
            <input type="date" name="dari" id="f-dari" value="{{ $dateFrom }}" class="filter-date">
        </div>
        <div class="filter-field">
            <label for="f-sampai">To date</label>
            <input type="date" name="sampai" id="f-sampai" value="{{ $dateTo }}" class="filter-date">
        </div>
        <div class="filter-field">
            <label for="f-rack">Rack</label>
            <select name="rack" id="f-rack" class="filter-select">
                <option value="">All racks</option>
                @foreach ($racks as $r)
                    <option value="{{ $r }}" @selected($rack === $r)>Rack {{ substr($r, 1) }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-field">
            <label for="f-event">Event</label>
            <select name="event" id="f-event" class="filter-select">
                <option value="">All events</option>
                <option value="IN" @selected($event === 'IN')>In</option>
                <option value="OUT" @selected($event === 'OUT')>Out</option>
            </select>
        </div>
        <button class="filter-btn filter-field-btn">Filter</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th><a href="{{ $sortUrl('waktu') }}" class="th-sort {{ $sort === 'waktu' ? 'active' : '' }}">Time{!! $sortIcon('waktu') !!}</a></th>
                    <th>Box Code</th>
                    <th>Item</th>
                    <th class="ta-right">Qty</th>
                    <th>Box Location (current)</th>
                    <th><a href="{{ $sortUrl('event') }}" class="th-sort {{ $sort === 'event' ? 'active' : '' }}">Event{!! $sortIcon('event') !!}</a></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $m)
                    <tr>
                        <td class="mono">{{ optional($m->update_date)->format('d M Y · H:i') }}</td>
                        <td class="mono">{{ $m->bin_name }}</td>
                        <td>
                            <span class="mono">{{ $m->component }}</span>
                            <span class="muted-sub">{{ $m->component_name }}</span>
                        </td>
                        <td class="ta-right mono {{ $m->qty < 0 ? 'qty-out' : 'qty-in' }}">
                            {{ $m->qty > 0 ? '+' : '' }}{{ number_format($m->qty) }} {{ $m->unit }}
                        </td>
                        <td class="mono">{{ $m->rack_slot ?? '—' }}</td>
                        <td>
                            @if ($m->remark === 'IN')
                                <span class="tag tag-in">IN</span>
                            @else
                                <span class="tag tag-out">OUT</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="table-empty-msg">No data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pager">
        <span class="pager-info">
            @if ($movements->total() > 0)
                Showing {{ $movements->firstItem() }}&ndash;{{ $movements->lastItem() }} of {{ number_format($movements->total()) }} transactions
            @else
                No transactions
            @endif
        </span>
        <span class="pager-nav">
            @if ($movements->onFirstPage())
                <span class="pager-btn disabled">&larr; Previous</span>
            @else
                <a href="{{ $movements->previousPageUrl() }}" class="pager-btn">&larr; Previous</a>
            @endif
            <span class="pager-page">Page {{ $movements->currentPage() }} / {{ max($movements->lastPage(), 1) }}</span>
            @if ($movements->hasMorePages())
                <a href="{{ $movements->nextPageUrl() }}" class="pager-btn">Next &rarr;</a>
            @else
                <span class="pager-btn disabled">Next &rarr;</span>
            @endif
        </span>
    </div>
@endsection
