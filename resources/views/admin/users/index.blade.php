@extends('layouts.dashboard', ['title' => 'Manage Users', 'dark' => true])

@php
    $nxd = fn ($val) => number_format((float) $val, 2);
    $chainMode = \App\Support\Chain::enabled();
@endphp

@section('content')
<div class="space-y-6 text-slate-200">

    {{-- Top Controls & Search --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-bold text-white">Member Directory</h2>
            <p class="text-xs text-slate-400">View user profiles, linked wallets, active NFT packages, and team earnings</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <span class="rounded-xl border border-white/10 bg-white/5 px-3 py-1.5 text-xs text-slate-300">
                Total: <strong class="text-white">{{ number_format($totalUsersCount) }}</strong>
            </span>
            <span class="rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-3 py-1.5 text-xs text-emerald-300">
                Wallets Linked: <strong class="text-white">{{ number_format($linkedCount) }}</strong>
            </span>
            <span class="rounded-xl border border-nx-gold/40 bg-nx-gold/10 px-3 py-1.5 text-xs text-nx-gold">
                Active NFT Holders: <strong class="text-white">{{ number_format($activeNftUsersCount) }}</strong>
            </span>
        </div>
    </div>

    {{-- Filters Card --}}
    <form method="GET" action="{{ route('admin.users.index') }}" class="nx-panel flex flex-wrap items-center gap-3 p-4">
        {{-- Search text --}}
        <div class="min-w-64 flex-1">
            <label for="search" class="sr-only">Search</label>
            <div class="relative">
                <input type="text" id="search" name="q" value="{{ $search }}"
                    placeholder="Search by User ID, Name, or Wallet..."
                    class="w-full rounded-xl border border-white/15 bg-nx-panel px-4 py-2.5 pl-10 text-sm text-white placeholder-slate-400 focus:border-nx-gold focus:outline-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </div>
        </div>

        {{-- Wallet Filter --}}
        <div>
            <select name="wallet" onchange="this.form.submit()"
                class="rounded-xl border border-white/15 bg-nx-panel px-4 py-2.5 text-sm text-white focus:border-nx-gold focus:outline-none">
                <option value="">All Wallets</option>
                <option value="linked" @selected($walletFilter === 'linked')>Linked Only</option>
                <option value="unlinked" @selected($walletFilter === 'unlinked')>Unlinked Only</option>
            </select>
        </div>

        {{-- NFT Filter --}}
        <div>
            <select name="nft" onchange="this.form.submit()"
                class="rounded-xl border border-white/15 bg-nx-panel px-4 py-2.5 text-sm text-white focus:border-nx-gold focus:outline-none">
                <option value="">All NFT States</option>
                <option value="active" @selected($nftFilter === 'active')>Active NFT</option>
                <option value="completed" @selected($nftFilter === 'completed')>Completed (4X)</option>
                <option value="none" @selected($nftFilter === 'none')>No NFT</option>
            </select>
        </div>

        <button type="submit"
            class="rounded-xl border border-nx-gold/60 bg-nx-gold/15 px-4 py-2.5 text-sm font-semibold text-nx-gold hover:bg-nx-gold/25">
            Filter
        </button>

        @if ($search || $walletFilter || $nftFilter)
            <a href="{{ route('admin.users.index') }}"
                class="rounded-xl border border-white/15 bg-white/5 px-3 py-2.5 text-sm text-slate-300 hover:text-white">
                Clear
            </a>
        @endif
    </form>

    {{-- Users Table --}}
    {{-- In chain mode, linked wallets show their on-chain package and balance
         (resources/js/nexgrow/members.js). --}}
    <div class="nx-panel overflow-hidden" @if ($chainMode) data-nx-chain="members" @endif>
        <div class="overflow-x-auto">
            <table class="w-full min-w-240 text-left text-sm">
                <thead>
                    <tr class="border-b border-white/10 text-xs font-semibold text-slate-400">
                        <th class="px-5 py-4">#</th>
                        <th class="px-5 py-4">User</th>
                        <th class="px-5 py-4">Sponsor</th>
                        <th class="px-5 py-4">Linked Wallet</th>
                        <th class="px-5 py-4">NFT Package</th>
                        <th class="px-5 py-4">Token Balance</th>
                        <th class="px-5 py-4">Total Earned</th>
                        <th class="px-5 py-4">Team</th>
                        <th class="px-5 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($users as $u)
                        @php
                            $activeNft = $u->nfts->firstWhere('status', 'active');
                            $earned = $earningsByUser[$u->id] ?? 0.0;
                            $onChain = $chainMode && $u->wallet_address;
                        @endphp
                        <tr class="transition hover:bg-white/2" @if ($onChain) data-nx-member-wallet="{{ strtolower($u->wallet_address) }}" @endif>
                            <td class="px-5 py-4 text-xs text-slate-400">{{ $users->firstItem() + $loop->index }}</td>
                            <td class="px-5 py-4">
                                <a href="{{ route('admin.users.show', $u) }}" class="font-bold text-white hover:text-nx-gold">
                                    {{ $u->name ?: $u->user_id }}
                                </a>
                                <span class="block font-mono text-xs text-nx-gold/90">{{ $u->user_id }}</span>
                            </td>
                            <td class="px-5 py-4 text-xs">
                                @if ($u->sponsor)
                                    <span class="font-medium text-white">{{ $u->sponsor->name }}</span>
                                    <span class="block font-mono text-slate-400">{{ $u->sponsor->user_id }}</span>
                                @else
                                    <span class="text-slate-500">Root User</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-xs">
                                @if ($u->wallet_address)
                                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-400/40 bg-emerald-400/10 px-2.5 py-1 font-mono text-emerald-300">
                                        {{ Str::substr($u->wallet_address, 0, 6) }}...{{ Str::substr($u->wallet_address, -4) }}
                                    </span>
                                @else
                                    <span class="inline-flex rounded-lg border border-white/10 bg-white/5 px-2 py-0.5 text-[11px] text-slate-400">
                                        Unlinked
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap">
                                @if ($onChain)
                                    <span data-nx-field="package-loading" class="text-xs text-slate-500">Checking…</span>
                                    <div data-nx-field="package-active" class="hidden">
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-nx-gold/50 bg-nx-gold/10 px-2.5 py-0.5 text-xs font-bold text-nx-gold">
                                            <span data-nx-field="package"></span> NXD
                                        </span>
                                        <span class="block text-[11px] text-slate-400"><span data-nx-field="package-rate"></span> NXD/day</span>
                                    </div>
                                    <span data-nx-field="package-none" class="hidden text-xs text-slate-500">None</span>
                                @elseif ($activeNft)
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-nx-gold/50 bg-nx-gold/10 px-2.5 py-0.5 text-xs font-bold text-nx-gold">
                                        {{ $nxd($activeNft->purchase_price, 0) }} NXD
                                    </span>
                                    <span class="block text-[11px] text-slate-400">{{ $nxd($activeNft->daily_bonus) }} NXD/day</span>
                                @else
                                    <span class="text-xs text-slate-500">None</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 font-semibold whitespace-nowrap text-white">
                                @if ($onChain)
                                    <span data-nx-field="balance">…</span>
                                @else
                                    {{ $nxd($u->token_balance) }}
                                @endif
                                <span class="text-xs font-normal text-slate-400">NXD</span>
                            </td>
                            <td class="px-5 py-4 font-semibold whitespace-nowrap text-emerald-400">
                                +{{ $nxd($earned) }} <span class="text-xs font-normal text-slate-400">NXD</span>
                            </td>
                            <td class="px-5 py-4 text-xs whitespace-nowrap text-slate-300">
                                <span class="font-semibold text-white">{{ $u->referrals_count }}</span> directs
                            </td>
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    @unless ($u->is_admin)
                                        <form method="POST" action="{{ route('admin.users.impersonate', $u) }}" target="_blank">
                                            @csrf
                                            <button type="submit" title="Sign in as {{ $u->name }} to see their account"
                                                class="inline-flex items-center gap-1 rounded-lg border border-nx-gold/60 bg-nx-gold/15 px-3 py-1.5 text-xs font-semibold text-nx-gold transition hover:bg-nx-gold/25">
                                                View
                                            </button>
                                        </form>
                                    @endunless
                                    <a href="{{ route('admin.users.show', $u) }}"
                                        class="inline-flex items-center gap-1 rounded-lg border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-semibold text-nx-gold transition hover:border-nx-gold hover:bg-nx-gold/10">
                                        Details &rarr;
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-12 text-center text-slate-400">
                                No members match your search criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="border-t border-white/10 px-5 py-4">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

