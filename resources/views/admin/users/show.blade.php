@extends('layouts.dashboard', ['title' => 'Member Profile: ' . $user->name, 'dark' => true])

@php
    $nxd = fn ($val) => number_format((float) $val, 2);
    $onChain = \App\Support\Chain::enabled() && $user->wallet_address;
@endphp

@section('content')
<div class="space-y-6 text-slate-200">

    {{-- Breadcrumb & Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-400 hover:text-nx-gold">
                &larr; Back to Member Directory
            </a>
            <div class="mt-2 flex items-center gap-3">
                <span class="nx-avatar flex h-12 w-12 items-center justify-center rounded-2xl text-lg font-bold text-nx-gold">
                    {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                </span>
                <div>
                    <h2 class="text-xl font-bold text-white">{{ $user->name }}</h2>
                    <p class="text-xs text-slate-400">
                        ID: <span class="font-mono text-nx-gold">{{ $user->user_id }}</span>
                        &middot; Joined: {{ $user->created_at->format('d M Y') }}
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            @unless ($user->is_admin)
                <form method="POST" action="{{ route('admin.users.impersonate', $user) }}" target="_blank">
                    @csrf
                    <button type="submit"
                        class="rounded-xl border border-nx-gold/60 bg-nx-gold/15 px-4 py-2.5 text-sm font-semibold text-nx-gold hover:bg-nx-gold/25">
                        View as member
                    </button>
                </form>
            @endunless
            @if ($user->wallet_address)
                <div class="rounded-xl border border-emerald-400/50 bg-emerald-400/10 px-3.5 py-2 text-right">
                    <p class="text-[10px] font-semibold text-emerald-300 uppercase">Linked Wallet</p>
                    <p class="font-mono text-xs font-bold text-white">{{ $user->wallet_address }}</p>
                </div>
            @else
                <div class="rounded-xl border border-white/10 bg-white/5 px-3.5 py-2 text-right">
                    <p class="text-[10px] text-slate-400 uppercase">Linked Wallet</p>
                    <p class="text-xs text-slate-400">No wallet linked</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Quick Stat Tiles. In chain mode with a linked wallet, the balance and
         package come from the contracts (resources/js/nexgrow/members.js). --}}
    <div @if ($onChain) data-nx-chain="members" @endif>
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" @if ($onChain) data-nx-member-wallet="{{ strtolower($user->wallet_address) }}" @endif>
        {{-- Token Balance --}}
        <div class="nx-panel p-5">
            <p class="text-xs text-slate-400">Token Balance</p>
            @if ($onChain)
                <p class="mt-1 text-2xl font-bold text-white"><span data-nx-field="balance">…</span> <span class="text-sm text-slate-400">NXD</span></p>
                <p class="mt-1 text-xs text-slate-400">On-chain wallet balance</p>
            @else
                <p class="mt-1 text-2xl font-bold text-white">{{ $nxd($user->token_balance) }} <span class="text-sm text-slate-400">NXD</span></p>
                <p class="mt-1 text-xs text-slate-400">Off-chain spendable balance</p>
            @endif
        </div>

        {{-- Total Distributed Earnings --}}
        <div class="nx-panel nx-panel-gold p-5">
            <p class="text-xs text-slate-300">Total Lifetime Earnings</p>
            <p class="nx-gold-text mt-1 text-2xl font-bold">+{{ $nxd($totalEarned) }} <span class="text-sm font-semibold">NXD</span></p>
            <p class="mt-1 text-xs text-slate-300">Across all bonus streams</p>
        </div>

        {{-- Active Package --}}
        <div class="nx-panel p-5">
            <p class="text-xs text-slate-400">Active NFT Package</p>
            @if ($onChain)
                <p data-nx-field="package-loading" class="mt-1 text-2xl font-bold text-slate-500">…</p>
                <div data-nx-field="package-active" class="hidden">
                    <p class="mt-1 text-2xl font-bold text-nx-gold"><span data-nx-field="package"></span> NXD</p>
                    <p class="mt-1 text-xs text-emerald-400"><span data-nx-field="package-rate"></span> NXD/day &middot; Paid: <span data-nx-field="package-paid"></span>/<span data-nx-field="package-cap"></span></p>
                </div>
                <div data-nx-field="package-none" class="hidden">
                    <p class="mt-1 text-2xl font-bold text-slate-500">None</p>
                    <p class="mt-1 text-xs text-slate-400">No active package running</p>
                </div>
            @elseif ($activeNft)
                <p class="mt-1 text-2xl font-bold text-nx-gold">{{ $nxd($activeNft->purchase_price, 0) }} NXD</p>
                <p class="mt-1 text-xs text-emerald-400">{{ $nxd($activeNft->daily_bonus) }} NXD/day &middot; Paid: {{ $nxd($activeNft->total_paid_out) }}/{{ $nxd($activeNft->cap_amount) }}</p>
            @else
                <p class="mt-1 text-2xl font-bold text-slate-500">None</p>
                <p class="mt-1 text-xs text-slate-400">No active package running</p>
            @endif
        </div>

        {{-- Team Counts --}}
        <div class="nx-panel p-5">
            <p class="text-xs text-slate-400">Team Structure</p>
            <p class="mt-1 text-2xl font-bold text-white">{{ $user->referrals->count() }} <span class="text-sm font-normal text-slate-400">Directs</span></p>
            <p class="mt-1 text-xs text-slate-400">{{ number_format($totalTeamCount) }} total downline members</p>
        </div>
    </section>
    </div>

    {{-- Detailed Income Stream Breakdown for this User --}}
    <section class="nx-panel p-6">
        <h3 class="border-b border-white/10 pb-3 text-lg font-bold text-white">Earnings Breakdown by Bonus Module</h3>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($incomeBreakdown as $key => $item)
                <div class="rounded-xl border border-white/10 bg-white/2 p-4">
                    <p class="text-xs text-slate-400">{{ $item['label'] }}</p>
                    <p class="mt-1 text-xl font-bold text-emerald-400">+{{ $nxd($item['amount']) }} <span class="text-xs text-slate-400">NXD</span></p>
                    <p class="mt-1 text-[11px] text-slate-400">{{ $item['count'] }} payment events recorded</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- NFT History --}}
    <section class="nx-panel overflow-hidden">
        <div class="border-b border-white/10 px-6 py-4">
            <h3 class="font-bold text-white">NFT Package History</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-white/10 text-xs font-semibold text-slate-400">
                        <th class="px-6 py-3">Package</th>
                        <th class="px-6 py-3">Price</th>
                        <th class="px-6 py-3">Daily ROI</th>
                        <th class="px-6 py-3">4X Cap</th>
                        <th class="px-6 py-3">Paid Out</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Purchased</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($user->nfts as $nft)
                        <tr class="transition hover:bg-white/2">
                            <td class="px-6 py-3 font-semibold text-white">{{ $nft->nftPackage?->name ?? ($nxd($nft->purchase_price, 0) . ' NXD') }}</td>
                            <td class="px-6 py-3 text-slate-300">{{ $nxd($nft->purchase_price) }} NXD</td>
                            <td class="px-6 py-3 text-emerald-400">{{ $nxd($nft->daily_bonus) }} NXD/day</td>
                            <td class="px-6 py-3 text-slate-300">{{ $nxd($nft->cap_amount) }} NXD</td>
                            <td class="px-6 py-3 text-nx-gold">{{ $nxd($nft->total_paid_out) }} NXD</td>
                            <td class="px-6 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $nft->status === 'active' ? 'border border-emerald-400/40 bg-emerald-400/10 text-emerald-300' : 'border border-white/20 bg-white/5 text-slate-400' }}">
                                    {{ ucfirst($nft->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-xs text-slate-400">{{ $nft->purchased_at?->format('d M Y, h:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-6 text-center text-slate-400">No NFT packages purchased yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Recent Transactions Ledger --}}
    <section class="nx-panel overflow-hidden">
        <div class="border-b border-white/10 px-6 py-4">
            <h3 class="font-bold text-white">Recent Wallet Activity / Credits</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-white/10 text-xs font-semibold text-slate-400">
                        <th class="px-6 py-3">Date</th>
                        <th class="px-6 py-3">Type</th>
                        <th class="px-6 py-3">Amount</th>
                        <th class="px-6 py-3">Balance After</th>
                        <th class="px-6 py-3">Reason</th>
                        <th class="px-6 py-3">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($transactions as $tx)
                        <tr class="transition hover:bg-white/2">
                            <td class="px-6 py-3 text-xs text-slate-400">{{ $tx->created_at->format('d M Y, h:i A') }}</td>
                            <td class="px-6 py-3">
                                <span class="rounded px-2 py-0.5 text-xs font-bold {{ $tx->type === 'credit' ? 'bg-emerald-400/15 text-emerald-300' : 'bg-rose-500/15 text-rose-300' }}">
                                    {{ strtoupper($tx->type) }}
                                </span>
                            </td>
                            <td class="px-6 py-3 font-semibold {{ $tx->type === 'credit' ? 'text-emerald-400' : 'text-rose-400' }}">
                                {{ $tx->type === 'credit' ? '+' : '-' }}{{ $nxd($tx->amount) }}
                            </td>
                            <td class="px-6 py-3 text-slate-300">{{ $nxd($tx->balance_after) }}</td>
                            <td class="px-6 py-3 text-slate-300">{{ Str::headline($tx->reason) }}</td>
                            <td class="px-6 py-3 text-xs text-slate-400">{{ $tx->note ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-6 text-center text-slate-400">No transactions recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

