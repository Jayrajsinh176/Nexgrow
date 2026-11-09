@extends('layouts.dashboard', ['title' => 'All Income Streams', 'dark' => true])

@php
    $nxd = fn ($val) => number_format((float) $val, 2);
@endphp

@section('content')
<div class="space-y-6 text-slate-200">

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-bold text-white">Income Overview</h2>
            <p class="text-xs text-slate-400">Every income stream at a glance. Open a stream for its own page, top earners and ledger.</p>
        </div>

        <span class="self-start rounded-xl border border-nx-gold/50 bg-nx-gold/10 px-4 py-2 text-sm font-bold whitespace-nowrap text-nx-gold sm:self-auto">
            Total Distributed: {{ $nxd($grandTotal) }} NXD
        </span>
    </div>

    {{-- One card per stream, linking to its page --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($summaries as $key => $s)
            <a href="{{ route('admin.incomes.index', ['stream' => $key]) }}"
                class="nx-panel group flex flex-col p-4 transition hover:-translate-y-0.5 hover:border-nx-gold/70">
                <p class="text-sm font-semibold text-white">{{ $s['title'] }}</p>
                <p class="mt-2 text-xl font-bold text-emerald-400">+{{ $nxd($s['total']) }} <span class="text-xs font-normal text-slate-400">NXD</span></p>
                <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
                    <span>{{ $s['count'] }} credits</span>
                    <span>{{ $s['earners'] }} earners</span>
                </div>
                <span class="mt-3 text-xs font-semibold text-nx-gold/80 group-hover:text-nx-gold">Open page &rarr;</span>
            </a>
        @endforeach
    </div>

    @include('admin.incomes.partials.ledger', [
        'title' => 'All Ledger Entries',
        'description' => 'Complete ledger of all income distributed across the entire ecosystem.',
        'showStream' => true,
    ])
</div>
@endsection
