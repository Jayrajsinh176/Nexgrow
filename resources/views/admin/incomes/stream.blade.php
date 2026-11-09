@extends('layouts.dashboard', ['title' => $meta['title'], 'dark' => true])

@php
    $nxd = fn ($val) => number_format((float) $val, 2);
@endphp

@section('content')
<div class="space-y-6 text-slate-200">

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <a href="{{ route('admin.incomes.index') }}" class="text-xs font-semibold text-slate-400 hover:text-nx-gold">
                &larr; Income Overview
            </a>
            <h2 class="mt-1 text-xl font-bold text-white">{{ $meta['title'] }}</h2>
            <p class="max-w-2xl text-xs text-slate-400">{{ $meta['description'] }}</p>
        </div>

        <div class="shrink-0 rounded-xl border border-white/10 bg-white/5 px-3.5 py-2 sm:text-right">
            <p class="text-[10px] font-semibold text-slate-400 uppercase">Contract &middot; <span class="font-mono normal-case">{{ $meta['contract'] }}</span></p>
            @if ($contractAddress)
                <a href="{{ rtrim($explorerUrl, '/') }}/address/{{ $contractAddress }}" target="_blank" rel="noopener"
                    class="font-mono text-xs font-bold break-all text-white hover:text-nx-gold">
                    {{ Str::substr($contractAddress, 0, 8) }}...{{ Str::substr($contractAddress, -6) }}
                </a>
            @else
                <p class="text-xs text-slate-500">Not deployed</p>
            @endif
        </div>
    </div>

    {{-- Stat tiles --}}
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="nx-panel nx-panel-gold p-5">
            <p class="text-xs text-slate-300">Total Distributed</p>
            <p class="mt-1 text-2xl font-bold text-emerald-400">+{{ $nxd($summary['total']) }} <span class="text-sm font-normal text-slate-400">NXD</span></p>
            <p class="mt-1 text-xs text-slate-400">Across {{ number_format($summary['count']) }} credits</p>
        </div>
        <div class="nx-panel p-5">
            <p class="text-xs text-slate-400">Earners</p>
            <p class="mt-1 text-2xl font-bold text-white">{{ number_format($summary['earners']) }}</p>
            <p class="mt-1 text-xs text-slate-400">Members credited at least once</p>
        </div>
        <div class="nx-panel p-5">
            <p class="text-xs text-slate-400">Average Credit</p>
            <p class="mt-1 text-2xl font-bold text-white">{{ $nxd($summary['avg']) }} <span class="text-sm font-normal text-slate-400">NXD</span></p>
            <p class="mt-1 text-xs text-slate-400">Per ledger entry</p>
        </div>
        <div class="nx-panel p-5">
            <p class="text-xs text-slate-400">{{ $extraStat['label'] }}</p>
            <p class="mt-1 text-2xl font-bold text-nx-gold">{{ number_format($extraStat['value']) }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $extraStat['hint'] }}</p>
        </div>
    </section>

    @if ($monthlyPool)
        @include('admin.incomes.partials.monthly-pool', $monthlyPool)
    @endif

    {{-- Top earners --}}
    <div class="nx-panel overflow-hidden">
        <div class="border-b border-white/10 px-6 py-4">
            <h3 class="font-bold text-white">Top Earners</h3>
            <p class="text-xs text-slate-400">Members who received the most from this stream.</p>
        </div>
        @if ($topEarners->isEmpty())
            <p class="px-6 py-8 text-center text-sm text-slate-400">Nobody has earned from this stream yet.</p>
        @else
            <ol class="divide-y divide-white/5">
                @foreach ($topEarners as $earner)
                    <li class="flex items-center justify-between gap-4 px-6 py-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-nx-gold/40 text-xs font-bold text-nx-gold">{{ $loop->iteration }}</span>
                            <div class="min-w-0">
                                @if ($earner->user)
                                    <a href="{{ route('admin.users.show', $earner->user) }}" class="truncate font-semibold text-white hover:text-nx-gold">{{ $earner->user->name }}</a>
                                    <span class="block font-mono text-xs text-slate-400">{{ $earner->user->user_id }}</span>
                                @else
                                    <span class="text-slate-500">User #{{ $earner->user_id }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="text-right whitespace-nowrap">
                            <p class="font-bold text-emerald-400">+{{ $nxd($earner->total) }} <span class="text-xs font-normal text-slate-400">NXD</span></p>
                            <p class="text-xs text-slate-400">{{ $earner->credits }} credits</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    @include('admin.incomes.partials.ledger', [
        'title' => 'Ledger Entries',
        'description' => 'Every credit paid by '.$meta['title'].', newest first.',
        'showStream' => false,
    ])
</div>
@endsection
