@extends('layouts.dashboard', ['title' => 'NFT Packages', 'dark' => true])

@php
    $nxd = fn ($value) => number_format((float) $value, 2);

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
    @if (session('status'))
        <div class="rounded-xl border border-emerald-400/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    @error('nft_package_id')
        <div class="rounded-xl border border-rose-400/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">{{ $message }}</div>
    @enderror

    {{-- Balance hero --}}
    <section class="nx-panel nx-panel-gold overflow-hidden">
        <div class="nx-stars pointer-events-none absolute inset-0"></div>
        <div class="nx-dots pointer-events-none absolute inset-x-0 bottom-0 h-2/3"></div>
        <div class="pointer-events-none absolute top-1/2 left-2/3 h-64 w-64 -translate-y-1/2 rounded-full bg-emerald-500/15 blur-3xl"></div>

        <div class="relative flex flex-wrap items-center justify-between gap-6 p-5 sm:px-6">
            <div class="flex min-w-0 items-center gap-4 sm:gap-5">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl sm:h-20 sm:w-20 border border-nx-gold/60 bg-nx-gold/10 text-nx-gold shadow-[0_0_20px_-8px] shadow-nx-gold">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-9 4h16a2 2 0 002-2V7a2 2 0 00-2-2H3a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                </span>
                <div class="min-w-0 border-l border-white/10 pl-4 sm:pl-5">
                    <p class="text-slate-200">Your Token Balance</p>
                    <p class="nx-gold-text mt-1 text-3xl font-bold sm:text-4xl">{{ $nxd($balance) }} NXD</p>
                    <p class="mt-1 text-sm text-slate-400">Packages are paid from this balance</p>
                </div>
            </div>

            {{-- Decorative NXD cube --}}
            <svg class="hidden h-28 w-28 shrink-0 drop-shadow-[0_8px_20px_rgba(34,197,94,0.35)] lg:block" viewBox="0 0 140 140" aria-hidden="true">
                <defs>
                    <linearGradient id="heroCubeTop" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#3a3320" /><stop offset="1" stop-color="#141209" /></linearGradient>
                    <linearGradient id="heroCubeSide" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1c1a12" /><stop offset="1" stop-color="#050403" /></linearGradient>
                </defs>
                <ellipse cx="70" cy="122" rx="52" ry="12" fill="#22c55e" opacity=".25" />
                <polygon points="70,112 18,86 70,60 122,86" fill="#0f2a18" stroke="#22c55e" stroke-width="1.5" />
                <polygon points="70,20 118,44 70,68 22,44" fill="url(#heroCubeTop)" stroke="#f2c14e" stroke-width="1.5" />
                <polygon points="22,44 70,68 70,110 22,86" fill="url(#heroCubeSide)" stroke="#f2c14e" stroke-width="1.5" />
                <polygon points="118,44 70,68 70,110 118,86" fill="url(#heroCubeSide)" stroke="#f2c14e" stroke-width="1.5" />
                <text x="36" y="84" font-size="15" font-weight="800" fill="#f2c14e" transform="skewY(26) translate(0 -22)" font-family="Instrument Sans, sans-serif">NXD</text>
            </svg>

            <a href="{{ route('wallet') }}"
                class="inline-flex items-center gap-2.5 rounded-xl border border-nx-gold/70 bg-nx-gold/10 px-5 py-3 font-semibold text-nx-gold transition hover:bg-nx-gold/20 hover:shadow-[0_0_20px_-6px] hover:shadow-nx-gold">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-9 4h16a2 2 0 002-2V7a2 2 0 00-2-2H3a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                View Wallet
            </a>
        </div>
    </section>

    {{-- Active NFT --}}
    @if ($activeNft)
        <section class="nx-panel p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-400">Your Active NFT</p>
                    <div class="mt-1 flex items-center gap-3">
                        <p class="text-xl font-semibold text-white">{{ $activeNft->nftPackage->name }}</p>
                        <span class="rounded-full border border-emerald-400/50 bg-emerald-400/10 px-3 py-0.5 text-sm text-emerald-300">Active</span>
                    </div>
                    <p class="mt-1 text-sm text-slate-400">
                        Daily bonus <span class="text-white">{{ $nxd($activeNft->daily_bonus) }} NXD</span>
                        &middot; Purchased {{ $activeNft->purchased_at->format('d M Y') }}
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-slate-400">Paid out</p>
                    <p class="text-xl font-semibold text-white">
                        {{ $nxd($activeNft->total_paid_out) }} <span class="text-slate-400">/ {{ $nxd($activeNft->cap_amount) }} NXD</span>
                    </p>
                </div>
            </div>
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-white/10">
                <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-nx-gold" style="width: {{ $activeNft->progressPercent() }}%"></div>
            </div>
            <p class="mt-2 flex justify-between text-xs text-slate-400">
                <span>This NFT must complete its 4X payout cap before you can purchase another.</span>
                <span>{{ number_format($activeNft->progressPercent(), 1) }}%</span>
            </p>
        </section>
    @endif

    {{-- Packages --}}
    <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($packages as $package)
            @php
                [$light, $dark, $glow] = $tiers[min($loop->index, count($tiers) - 1)];
                $gradient = 'gem-'.$package->id;
                $affordable = (float) $balance >= (float) $package->price;
                $owned = $activeNft?->nft_package_id == $package->id;
            @endphp
            <div class="nx-panel flex flex-col p-5 {{ $owned ? 'nx-panel-gold' : '' }}">
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
                        <p class="text-lg font-semibold text-white">{{ $package->name }}</p>
                        <p class="mt-0.5 text-3xl font-bold text-nx-gold">
                            {{ number_format((float) $package->price, (float) $package->price == (int) $package->price ? 0 : 2) }}
                            <span class="text-base font-semibold text-slate-200">NXD</span>
                        </p>
                    </div>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-white/10 pt-4 text-sm">
                    <div class="flex items-center gap-2.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-nx-gold" viewBox="0 0 24 24" fill="currentColor"><path d="M20 7h-2.2A3 3 0 0012 3.8 3 3 0 006.2 7H4a1 1 0 00-1 1v3a1 1 0 001 1h7V7h2v5h7a1 1 0 001-1V8a1 1 0 00-1-1zM9 7a1 1 0 110-2 1 1 0 011 1v1H9zm6 0h-1V6a1 1 0 111 1zM4 13v7a1 1 0 001 1h6v-8H4zm9 8h6a1 1 0 001-1v-7h-7v8z" /></svg>
                        <div>
                            <dt class="text-xs text-emerald-300/80">Daily bonus</dt>
                            <dd class="text-white">{{ $nxd($package->daily_bonus) }} NXD</dd>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-nx-gold" viewBox="0 0 24 24" fill="currentColor"><path d="M4 20h3v-6H4v6zm6.5 0h3V9h-3v11zM17 20h3V4h-3v16z" /></svg>
                        <div>
                            <dt class="text-xs text-emerald-300/80">{{ $package->cap_multiplier }}X cap</dt>
                            <dd class="text-white">{{ $nxd($package->capAmount()) }} NXD</dd>
                        </div>
                    </div>
                </dl>

                <div class="mt-auto pt-4">
                    @if ($owned)
                        <p class="flex items-center justify-center gap-2 rounded-xl border border-nx-gold/60 bg-nx-gold/10 px-4 py-2.5 text-sm font-semibold text-nx-gold">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            Your active package
                        </p>
                    @elseif ($activeNft || ! $affordable)
                        <p class="flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-slate-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                            {{ $activeNft ? 'Available after your active NFT completes' : 'Insufficient balance' }}
                        </p>
                    @else
                        <form method="POST" action="{{ route('nft.purchase') }}"
                            onsubmit="return confirm('Buy {{ e($package->name) }} for {{ $nxd($package->price) }} NXD?')">
                            @csrf
                            <input type="hidden" name="nft_package_id" value="{{ $package->id }}">
                            <button type="submit"
                                class="w-full rounded-xl border border-nx-gold/70 bg-gradient-to-b from-amber-500/40 to-amber-900/40 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-nx-gold hover:shadow-[0_0_20px_-6px] hover:shadow-nx-gold">
                                Buy for {{ $nxd($package->price) }} NXD
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </section>

    {{-- Completed NFTs --}}
    @if ($pastNfts->isNotEmpty())
        <section class="nx-panel overflow-hidden">
            <h3 class="border-b border-white/10 px-6 py-4 text-lg font-semibold text-white">Completed NFTs</h3>
            <div class="overflow-x-auto">
                <table class="w-full min-w-160 text-left text-sm">
                    <thead>
                        <tr class="border-b border-white/10 text-slate-200">
                            <th class="px-6 py-3 font-semibold">Package</th>
                            <th class="px-4 py-3 font-semibold">Purchased</th>
                            <th class="px-4 py-3 font-semibold">Completed</th>
                            <th class="px-6 py-3 font-semibold">Total Paid Out</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach ($pastNfts as $nft)
                            <tr class="transition hover:bg-white/3">
                                <td class="px-6 py-3 text-white">{{ $nft->nftPackage->name }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $nft->purchased_at->format('d M Y') }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $nft->completed_at?->format('d M Y') }}</td>
                                <td class="px-6 py-3 font-semibold text-emerald-400">{{ $nxd($nft->total_paid_out) }} NXD</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
@endsection
