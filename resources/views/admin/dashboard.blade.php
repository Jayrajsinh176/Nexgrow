@extends('layouts.dashboard', ['title' => 'Admin Overview', 'dark' => true])

@php
    $nxd = fn ($val) => number_format((float) $val, 2);
    $coin = '<span class="nx-coin inline-block h-3.5 w-3.5 align-middle"><img src="'.asset('coin_nexadon.png').'" alt="" class="h-full w-full scale-[1.06] object-cover"></span>';
@endphp

@section('content')
<div class="space-y-6 text-slate-200">

    {{-- System Status Banner --}}
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-nx-gold/40 bg-gradient-to-r from-emerald-950/60 via-nx-panel to-amber-950/40 p-5">
        <div class="flex items-center gap-3.5">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-nx-gold/60 bg-nx-gold/15 text-nx-gold">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
            </span>
            <div>
                <h2 class="text-lg font-bold text-white">Nexadon System Administration</h2>
                <p class="text-xs text-slate-300">
                    Mode: <span class="rounded bg-nx-gold/20 px-2 py-0.5 font-semibold text-nx-gold uppercase">{{ $blockchainMode }}</span>
                    &middot; Contracts: <span class="{{ $isDeployed ? 'text-emerald-400 font-semibold' : 'text-amber-300' }}">{{ $isDeployed ? 'Deployed (' . count($contracts) . ' modules)' : 'Not deployed yet' }}</span>
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('admin.users.index') }}"
                class="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/5 px-4 py-2 text-xs font-semibold text-white transition hover:border-nx-gold hover:bg-white/10">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-nx-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                Manage Users
            </a>
            <a href="{{ route('admin.incomes.index') }}"
                class="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/5 px-4 py-2 text-xs font-semibold text-white transition hover:border-nx-gold hover:bg-white/10">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-nx-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m9-8a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                Income Ledger
            </a>
            <a href="{{ route('admin.blockchain.index') }}"
                class="inline-flex items-center gap-2 rounded-xl border border-nx-gold/60 bg-nx-gold/15 px-4 py-2 text-xs font-semibold text-nx-gold transition hover:border-nx-gold hover:bg-nx-gold/25">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                Blockchain Operations
            </a>
        </div>
    </div>

    {{-- Top Metrics Grid --}}
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Total Users --}}
        <div class="nx-panel flex items-center gap-4 p-5">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl border border-sky-400/50 bg-sky-400/10 text-sky-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4" /></svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-xs text-slate-400">Total Registered Users</p>
                <p class="mt-1 text-2xl font-bold text-white">{{ number_format($totalUsers) }}</p>
                <p class="mt-1 text-xs text-emerald-400">{{ number_format($linkedWalletsCount) }} wallets linked</p>
            </div>
        </div>

        {{-- Active NFTs --}}
        <div class="nx-panel flex items-center gap-4 p-5">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl border border-violet-400/50 bg-violet-500/15 text-violet-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-xs text-slate-400">Active NFT Packages</p>
                <p class="mt-1 text-2xl font-bold text-white">{{ number_format($activeNftsCount) }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ $nxd($totalNftVolume) }} NXD package volume</p>
            </div>
        </div>

        {{-- Total Distributed Earnings --}}
        <div class="nx-panel nx-panel-gold flex items-center gap-4 p-5">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl border border-nx-gold/60 bg-nx-gold/15 text-nx-gold">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m9-8a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-xs text-slate-300">Total Income Distributed</p>
                <p class="nx-gold-text mt-1 text-2xl font-bold">{{ $nxd($totalDistributedIncome) }} <span class="text-sm font-semibold">NXD</span></p>
                <p class="mt-1 text-xs text-slate-300">Across all 8 bonus streams</p>
            </div>
        </div>

        {{-- Profit Sharing Slots --}}
        <div class="nx-panel flex items-center gap-4 p-5">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl border border-emerald-400/50 bg-emerald-400/10 text-emerald-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 3.05A9 9 0 1020.95 13H11V3.05zM14 3.5V10h6.5A9 9 0 0014 3.5z" /></svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-xs text-slate-400">Profit Sharing Slots</p>
                <p class="mt-1 text-2xl font-bold text-white">{{ number_format($totalSlotsSold) }}</p>
                <p class="mt-1 text-xs text-emerald-400">${{ $nxd($totalSlotsVolume) }} principal locked</p>
            </div>
        </div>
    </section>

    {{-- Income Breakdown By Module --}}
    <section class="nx-panel p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 pb-4">
            <div>
                <h3 class="text-lg font-bold text-white">All Income Streams Summary</h3>
                <p class="text-xs text-slate-400">Real-time distributed bonuses and qualification counts across all 8 modules</p>
            </div>
            <a href="{{ route('admin.incomes.index') }}" class="text-xs font-semibold text-nx-gold hover:underline">
                View Detailed Ledger &rarr;
            </a>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($incomeStreams as $key => $stream)
                <div class="rounded-xl border border-white/10 bg-white/2 p-4 transition hover:border-nx-gold/50">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-semibold text-white">{{ $stream['title'] }}</p>
                        <span class="rounded bg-white/10 px-2 py-0.5 text-[10px] font-bold text-slate-300">{{ $stream['count'] }} credits</span>
                    </div>
                    <p class="mt-2 text-xl font-bold text-emerald-400">{{ $nxd($stream['total']) }} <span class="text-xs text-slate-300">NXD</span></p>
                    <p class="mt-1 line-clamp-2 text-xs text-slate-400">{{ $stream['description'] }}</p>
                    <a href="{{ route('admin.incomes.index', ['stream' => $key]) }}" class="mt-3 inline-block text-xs font-semibold text-nx-gold hover:underline">
                        Audit records &rarr;
                    </a>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Two Columns: Recent Registrations & Recent Transactions --}}
    <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Recent Users --}}
        <div class="nx-panel overflow-hidden">
            <div class="flex items-center justify-between border-b border-white/10 px-6 py-4">
                <h3 class="font-bold text-white">Recent Member Registrations</h3>
                <a href="{{ route('admin.users.index') }}" class="text-xs font-semibold text-nx-gold hover:underline">View All Users</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-white/10 text-xs font-semibold text-slate-400">
                            <th class="px-5 py-3">User ID</th>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Sponsor</th>
                            <th class="px-5 py-3">Wallet</th>
                            <th class="px-5 py-3">Joined</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($recentUsers as $u)
                            <tr class="transition hover:bg-white/2">
                                <td class="px-5 py-3 font-semibold text-nx-gold">
                                    <a href="{{ route('admin.users.show', $u) }}" class="hover:underline">{{ $u->user_id }}</a>
                                </td>
                                <td class="px-5 py-3 text-white">{{ $u->name }}</td>
                                <td class="px-5 py-3 text-slate-300">{{ $u->sponsor?->name ?? 'None' }}</td>
                                <td class="px-5 py-3 text-xs">
                                    @if ($u->wallet_address)
                                        <span class="font-mono text-emerald-300">{{ Str::substr($u->wallet_address, 0, 6) }}...{{ Str::substr($u->wallet_address, -4) }}</span>
                                    @else
                                        <span class="text-slate-500">Unlinked</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-xs text-slate-400">{{ $u->created_at->format('d M') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-6 text-center text-slate-400">No users registered yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Recent Payouts / Transactions --}}
        <div class="nx-panel overflow-hidden">
            <div class="flex items-center justify-between border-b border-white/10 px-6 py-4">
                <h3 class="font-bold text-white">Recent Income Credits</h3>
                <a href="{{ route('admin.incomes.index') }}" class="text-xs font-semibold text-nx-gold hover:underline">View All Credits</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-white/10 text-xs font-semibold text-slate-400">
                            <th class="px-5 py-3">User</th>
                            <th class="px-5 py-3">Reason</th>
                            <th class="px-5 py-3">Amount</th>
                            <th class="px-5 py-3">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($recentTransactions as $tx)
                            <tr class="transition hover:bg-white/2">
                                <td class="px-5 py-3">
                                    <span class="font-medium text-white">{{ $tx->user->name ?? 'User' }}</span>
                                    <span class="block text-xs text-slate-400">{{ $tx->user->user_id ?? '' }}</span>
                                </td>
                                <td class="px-5 py-3 text-xs text-slate-300">
                                    {{ Str::headline($tx->reason) }}
                                </td>
                                <td class="px-5 py-3 font-semibold {{ $tx->type === 'credit' ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ $tx->type === 'credit' ? '+' : '-' }}{{ $nxd($tx->amount) }}
                                </td>
                                <td class="px-5 py-3 text-xs text-slate-400">{{ $tx->created_at->format('d M, h:i A') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-6 text-center text-slate-400">No transactions recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
@endsection

