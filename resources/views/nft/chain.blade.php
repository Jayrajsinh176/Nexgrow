@extends('layouts.dashboard', ['title' => 'NFT Packages', 'dark' => true])

{{-- Chain mode: NFTs are bought with the user's own wallet through the
     NexgrowNFT contract. Widgets in resources/js/nexgrow/nft.js. --}}

@php
    $nxd = fn ($value, $decimals = 2) => number_format((float) $value, $decimals);

    // Gem colours by tier (cheapest first): [light, dark, glow].
    $tiers = [
        ['#f59e6b', '#9a3412', 'rgb(249 115 22 / 0.45)'],
        ['#e2e8f0', '#475569', 'rgb(203 213 225 / 0.4)'],
        ['#fde68a', '#b45309', 'rgb(242 193 78 / 0.5)'],
        ['#6ee7b7', '#047857', 'rgb(34 197 94 / 0.5)'],
        ['#7dd3fc', '#1d4ed8', 'rgb(59 130 246 / 0.5)'],
        ['#d8b4fe', '#6d28d9', 'rgb(139 92 246 / 0.5)'],
        ['#fca5a5', '#b91c1c', 'rgb(239 68 68 / 0.5)'],
    ];
@endphp

@section('content')
<div class="space-y-5 text-slate-200">
    @error('nft_package_id')
        <div class="rounded-xl border border-rose-400/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">{{ $message }}</div>
    @enderror

    @if (! $deployed)
        <div class="rounded-xl border border-amber-400/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
            The NFT contracts are not deployed on this network yet. Purchases open once they are.
        </div>
    @endif

    @if (! $user->wallet_address)
        @include('partials.chain-wallet')
    @endif

    {{-- The wallet's NFTs --}}
    <section class="nx-panel hidden p-5 sm:p-6" data-nx-chain="my-nfts">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-white">Your NFTs</h3>
            <p class="text-sm text-slate-400">Wallet balance <span class="font-semibold text-white" data-nx-field="balance">…</span> NXD</p>
        </div>

        <p data-nx-field="empty" class="mt-3 hidden text-sm text-slate-400">You have no NFTs yet.</p>

        <div data-nx-field="list" class="mt-4 space-y-4"></div>

        <template data-nx-field="item">
            <div class="rounded-xl border border-white/10 bg-white/3 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-3">
                            <p class="text-lg font-semibold text-white"><span data-nx-item="package"></span> NXD NFT <span class="text-sm text-slate-400">#<span data-nx-item="id"></span></span></p>
                            <span data-nx-item="status" class="rounded-full border px-3 py-0.5 text-xs"></span>
                        </div>
                        <p class="mt-1 text-sm text-slate-400">Daily bonus <span class="text-white" data-nx-item="rate"></span> NXD</p>
                    </div>
                    <div class="text-right text-sm">
                        <p class="text-slate-400">Earned <span class="font-semibold text-white" data-nx-item="earned"></span> &middot; Paid <span class="font-semibold text-white" data-nx-item="paid"></span></p>
                        <p class="text-slate-400">4X cap <span class="text-white" data-nx-item="cap"></span> NXD</p>
                    </div>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-white/10">
                    <div data-nx-item="bar" class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-nx-gold" style="width: 0%"></div>
                </div>
            </div>
        </template>

        <p data-nx-field="message" class="mt-3 hidden text-sm"></p>
    </section>

    {{-- Packages --}}
    <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" data-nx-chain="packages">
        <p data-nx-field="message" class="col-span-full hidden rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm"></p>

        @foreach ($packages as $price => $dailyBonus)
            @php
                [$light, $dark, $glow] = $tiers[min($loop->index, count($tiers) - 1)];
                $gradient = 'gem-chain-'.$price;
            @endphp
            <div class="nx-panel flex flex-col p-5 transition" data-nx-package-card="{{ $price }}">
                <div class="flex items-center gap-4">
                    <svg class="h-20 w-20 shrink-0" viewBox="0 0 80 80" style="filter: drop-shadow(0 0 14px {{ $glow }})" aria-hidden="true">
                        <defs>
                            <linearGradient id="{{ $gradient }}" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0" stop-color="{{ $light }}" /><stop offset="1" stop-color="{{ $dark }}" />
                            </linearGradient>
                        </defs>
                        <polygon points="40,3 72,21.5 72,58.5 40,77 8,58.5 8,21.5" fill="#0b0b0b" stroke="url(#{{ $gradient }})" stroke-width="4" />
                        <polygon points="40,12 64,26 64,54 40,68 16,54 16,26" fill="url(#{{ $gradient }})" opacity=".22" />
                        <polygon points="40,22 56,31 40,40 24,31" fill="{{ $light }}" />
                        <polygon points="24,31 40,40 40,58 24,49" fill="url(#{{ $gradient }})" />
                        <polygon points="56,31 40,40 40,58 56,49" fill="{{ $dark }}" />
                        <path d="M40 22v36M24 31l16 9 16-9" stroke="#fff" stroke-opacity=".35" stroke-width="1" fill="none" />
                    </svg>
                    <div class="min-w-0">
                        <p class="text-lg font-semibold text-white">{{ $nxd($price, 0) }} NXD Package</p>
                        <p class="mt-0.5 text-3xl font-bold text-nx-gold">{{ $nxd($price, 0) }} <span class="text-base font-semibold text-slate-200">NXD</span></p>
                    </div>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-white/10 pt-4 text-sm">
                    <div>
                        <dt class="text-xs text-emerald-300/80">Daily bonus</dt>
                        <dd class="text-white">{{ $nxd($dailyBonus) }} NXD</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-emerald-300/80">{{ $capMultiplier }}X cap</dt>
                        <dd class="text-white">{{ $nxd($price * $capMultiplier) }} NXD</dd>
                    </div>
                </dl>

                <div class="mt-auto pt-4" data-nx-slot="action">
                    <button type="button" data-nx-action="buy" data-nx-package="{{ $price }}" @disabled(! $deployed)
                        class="w-full rounded-xl border border-nx-gold/70 bg-gradient-to-b from-amber-500/40 to-amber-900/40 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-nx-gold hover:shadow-[0_0_20px_-6px] hover:shadow-nx-gold disabled:cursor-not-allowed disabled:opacity-50">
                        Buy with wallet &middot; {{ $nxd($price, 0) }} NXD
                    </button>
                </div>
            </div>
        @endforeach
    </section>
</div>
@endsection
