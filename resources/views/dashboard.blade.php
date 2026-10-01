@extends('layouts.dashboard', ['title' => 'Dashboard', 'dark' => true])

@php
    $nxd = fn ($value) => number_format((float) $value, 2);

    $stats = [
        [
            'label' => 'Token Balance', 'coin' => true, 'value' => $nxd($user->token_balance), 'unit' => 'NXD',
            'hint' => 'Available in your wallet', 'href' => route('wallet'),
            'tile' => 'border-nx-gold/60 bg-nx-gold/10 text-nx-gold', 'panel' => 'nx-panel-gold',
            'icon' => 'M12 4c4.4 0 8 1.3 8 3s-3.6 3-8 3-8-1.3-8-3 3.6-3 8-3zm8 3v5c0 1.7-3.6 3-8 3s-8-1.3-8-3V7m16 5v5c0 1.7-3.6 3-8 3s-8-1.3-8-3v-5',
        ],
        [
            'label' => 'Total Earnings', 'value' => $nxd($totalEarnings), 'unit' => 'NXD',
            'hint' => 'All time income', 'href' => route('wallet'),
            'tile' => 'border-emerald-400/50 bg-emerald-400/10 text-emerald-300', 'panel' => '',
            'icon' => 'M3 10h18M7 15h1m4 0h1m-9 4h16a2 2 0 002-2V7a2 2 0 00-2-2H3a2 2 0 00-2 2v10a2 2 0 002 2z',
        ],
        [
            'label' => 'Direct Referrals', 'value' => number_format($directReferralsCount), 'unit' => null,
            'hint' => 'People you personally referred', 'href' => route('team'),
            'tile' => 'border-violet-400/50 bg-violet-500/15 text-violet-300', 'panel' => '',
            'icon' => 'M12 3a3 3 0 100 6 3 3 0 000-6zM5 15a2.5 2.5 0 100 5 2.5 2.5 0 000-5zm14 0a2.5 2.5 0 100 5 2.5 2.5 0 000-5zM12 9v3m0 0l-5 3m5-3l5 3',
        ],
        [
            'label' => 'Total Team Size', 'value' => number_format($totalTeamCount), 'unit' => null,
            'hint' => 'All levels in your downline', 'href' => route('team'),
            'tile' => 'border-indigo-400/50 bg-indigo-500/15 text-indigo-300', 'panel' => '',
            'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4',
        ],
    ];

    $incomeStyles = [
        'daily_bonus' => ['text-nx-gold', 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
        'referral_income' => ['text-sky-400', 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zm11 0v3m0 0v3m0-3h3m-3 0h-3'],
        'team_building_benefit' => ['text-violet-400', 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4'],
        'bonus_booster' => ['text-amber-300', 'M13 10V3L4 14h7v7l9-11h-7z'],
        'team_performance_bonus' => ['text-emerald-400', 'M4 20V10m5 10V4m5 16v-7m5 7V8M3 20h18'],
    ];

    $activityStyles = [
        'credit' => ['bg-emerald-500/15 text-emerald-300', 'bg-emerald-400', 'M12 4v16m8-8H4'],
        'debit' => ['bg-rose-500/15 text-rose-300', 'bg-rose-400', 'M20 12H4'],
        'member' => ['bg-sky-500/15 text-sky-300', 'bg-sky-400', 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zm11 0v3m0 0v3m0-3h3m-3 0h-3'],
    ];

    $maxLevelCount = max(1, $teamByLevel->max() ?? 1);
    $levelColors = ['text-amber-300', 'text-violet-300', 'text-orange-300', 'text-sky-300', 'text-emerald-300'];
@endphp

@section('content')
<div class="space-y-5 text-slate-200">

    {{-- Welcome hero --}}
    <section class="nx-panel nx-panel-gold overflow-hidden">
        <div class="nx-stars pointer-events-none absolute inset-0"></div>
        <div class="nx-dots pointer-events-none absolute inset-x-0 bottom-0 h-2/3"></div>
        <div class="pointer-events-none absolute -right-10 top-1/2 h-72 w-72 -translate-y-1/2 rounded-full bg-nx-gold/15 blur-3xl"></div>

        <div class="relative flex flex-col gap-6 p-6 sm:p-8 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-center gap-4 sm:gap-6">
                <span class="nx-avatar flex h-16 w-16 shrink-0 items-center justify-center rounded-full text-3xl font-semibold text-nx-gold sm:h-28 sm:w-28 sm:text-5xl">
                    {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                </span>
                <div class="min-w-0">
                    <p class="text-base text-slate-300 sm:text-lg">Welcome back,</p>
                    <h2 class="mt-1 flex flex-wrap items-baseline gap-x-3 text-2xl font-bold text-white sm:text-4xl">
                        <span class="truncate">{{ $user->name }}</span>
                        <span class="nx-gold-text text-xl font-bold sm:text-2xl">({{ $user->user_id }})</span>
                    </h2>
                    @if ($activeNft)
                        <span class="mt-3 inline-flex items-center gap-2 rounded-full border border-emerald-400/40 bg-emerald-400/10 px-3.5 py-1 text-sm text-emerald-100">
                            <span class="h-2.5 w-2.5 rounded-full bg-emerald-400 shadow-[0_0_8px] shadow-emerald-400"></span>
                            Active Member
                        </span>
                    @else
                        <a href="{{ route('nft.index') }}" class="mt-3 inline-flex items-center gap-2 rounded-full border border-slate-500/40 bg-white/5 px-3.5 py-1 text-sm text-slate-300 hover:border-nx-gold/60 hover:text-white">
                            <span class="h-2.5 w-2.5 rounded-full bg-slate-500"></span>
                            No active package &middot; Get started
                        </a>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-6 lg:gap-8">
                <div class="text-right max-lg:text-left">
                    <p class="text-xl font-bold text-white sm:text-[28px]">One Token. A World of Utility.</p>
                    <p class="mt-1 text-lg text-nx-gold">
                        Play <span class="text-slate-400">&bull;</span> Shop <span class="text-slate-400">&bull;</span> Use <span class="text-slate-400">&bull;</span> Reward
                    </p>
                </div>

                {{-- Nexadon coin --}}
                <div class="nx-coin-scene hidden h-36 w-36 shrink-0 sm:block">
                    <div class="nx-coin-flip">
                        <span class="nx-coin nx-coin-face"><img src="{{ asset('coin_nexadon.png') }}" alt="Nexadon coin" class="h-full w-full scale-[1.06] object-cover"></span>
                        <span class="nx-coin nx-coin-face nx-coin-back" aria-hidden="true"><img src="{{ asset('coin_nexadon.png') }}" alt="" class="h-full w-full scale-[1.06] object-cover"></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Stat cards --}}
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <a href="{{ $stat['href'] }}" class="nx-panel {{ $stat['panel'] }} group flex items-center gap-4 p-5 transition hover:-translate-y-0.5 hover:border-nx-gold/60 xl:gap-3 xl:p-4 2xl:gap-4 2xl:p-5">
                <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border xl:h-12 xl:w-12 2xl:h-16 2xl:w-16 {{ $stat['tile'] }}">
                    @if ($stat['coin'] ?? false)
                        <span class="nx-coin nx-coin-static block h-12 w-12 xl:h-9 xl:w-9 2xl:h-12 2xl:w-12"><img src="{{ asset('coin_nexadon.png') }}" alt="" class="h-full w-full scale-[1.06] object-cover"></span>
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 xl:h-6 xl:w-6 2xl:h-8 2xl:w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $stat['icon'] }}" />
                    </svg>
                    @endif
                </span>
                <div class="min-w-0 flex-1">
                    {{-- Right padding keeps the label clear of the corner arrow; the value gets the full width. --}}
                    <p class="pr-8 text-sm text-slate-300">{{ $stat['label'] }}</p>
                    <p class="mt-1 flex flex-wrap items-baseline gap-x-1.5 font-bold text-white">
                        <span class="text-2xl leading-tight tabular-nums wrap-anywhere xl:text-xl 2xl:text-2xl">{{ $stat['value'] }}</span>
                        @if ($stat['unit'])<span class="text-sm font-semibold text-slate-300 2xl:text-base">{{ $stat['unit'] }}</span>@endif
                    </p>
                    <p class="mt-1 text-xs text-slate-400">{{ $stat['hint'] }}</p>
                </div>
                <span class="absolute top-3 right-3 z-2 flex h-7 w-7 items-center justify-center rounded-full border border-white/15 text-slate-300 transition group-hover:border-nx-gold/60 group-hover:text-nx-gold">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                </span>
            </a>
        @endforeach
    </section>

    {{-- Plan / Team growth / Income --}}
    <section class="grid grid-cols-1 gap-4 lg:grid-cols-2 2xl:grid-cols-[1.3fr_1fr_1fr]">
        {{-- Plan details --}}
        <div class="nx-panel p-5 lg:col-span-2 2xl:col-span-1">
            <div class="flex items-center justify-between border-b border-white/10 pb-4">
                <h3 class="flex items-center gap-3 text-lg font-semibold text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-nx-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l9 4.5-9 4.5-9-4.5L12 3zm-9 9l9 4.5 9-4.5M3 16.5L12 21l9-4.5" /></svg>
                    Your Plan Details
                </h3>
                <a href="{{ route('nft.index') }}" class="rounded-full border border-emerald-400/40 px-4 py-1.5 text-sm text-slate-200 hover:border-nx-gold/60 hover:text-white">View Plan</a>
            </div>

            @if ($activeNft)
                <div class="flex flex-col items-center gap-6 pt-4 sm:flex-row">
                    {{-- Package cube --}}
                    <svg class="h-36 w-36 shrink-0 drop-shadow-[0_8px_20px_rgba(34,197,94,0.35)]" viewBox="0 0 140 140" aria-hidden="true">
                        <defs>
                            <linearGradient id="cubeTop" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#3a3320" /><stop offset="1" stop-color="#141209" /></linearGradient>
                            <linearGradient id="cubeLeft" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1c1a12" /><stop offset="1" stop-color="#0a0906" /></linearGradient>
                            <linearGradient id="cubeRight" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#131109" /><stop offset="1" stop-color="#050403" /></linearGradient>
                        </defs>
                        <ellipse cx="70" cy="122" rx="52" ry="12" fill="#22c55e" opacity=".25" />
                        <polygon points="70,112 18,86 70,60 122,86" fill="#0f2a18" stroke="#22c55e" stroke-width="1.5" />
                        <polygon points="70,20 118,44 70,68 22,44" fill="url(#cubeTop)" stroke="#f2c14e" stroke-width="1.5" />
                        <polygon points="22,44 70,68 70,110 22,86" fill="url(#cubeLeft)" stroke="#f2c14e" stroke-width="1.5" />
                        <polygon points="118,44 70,68 70,110 118,86" fill="url(#cubeRight)" stroke="#f2c14e" stroke-width="1.5" />
                        <ellipse cx="70" cy="44" rx="11" ry="5.5" fill="#f2c14e" />
                        <text x="46" y="84" font-size="11" font-weight="800" fill="#f2c14e" transform="skewY(26) translate(0 -22)" font-family="Instrument Sans, sans-serif">NEX</text>
                        <text x="78" y="84" font-size="11" font-weight="800" fill="#e5e7eb" transform="skewY(-26) translate(0 38)" font-family="Instrument Sans, sans-serif">GROW</text>
                    </svg>

                    <div class="w-full min-w-0 flex-1">
                        <p class="text-xs text-slate-400">NFT Package</p>
                        <div class="flex items-center gap-3">
                            <p class="text-lg font-semibold text-white">{{ $activeNft->nftPackage->name }}</p>
                            <span class="rounded-full border border-emerald-400/50 bg-emerald-400/10 px-3 py-0.5 text-sm text-emerald-300">Active</span>
                        </div>
                        <dl class="mt-2 divide-y divide-white/5 text-sm">
                            @foreach ([
                                'Purchase Date' => $activeNft->purchased_at->format('d M Y'),
                                'Plan Price' => $nxd($activeNft->purchase_price).' NXD',
                                'Daily Bonus' => $nxd($activeNft->daily_bonus).' NXD',
                                'Earned So Far' => $nxd($activeNft->total_paid_out).' NXD',
                                'Total Earning Cap' => $nxd($activeNft->cap_amount).' NXD',
                            ] as $label => $value)
                                <div class="flex justify-between py-1.5">
                                    <dt class="text-slate-400">{{ $label }}</dt>
                                    <dd class="font-medium text-white">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10">
                            <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-nx-gold" style="width: {{ $activeNft->progressPercent() }}%"></div>
                        </div>
                        <p class="mt-1 text-right text-xs text-slate-400">{{ number_format($activeNft->progressPercent(), 1) }}% of cap reached</p>
                    </div>
                </div>
            @else
                <div class="flex flex-col items-center justify-center gap-3 py-10 text-center">
                    <p class="text-slate-300">You don't have an active NFT package yet.</p>
                    <a href="{{ route('nft.index') }}" class="rounded-lg border border-nx-gold/60 bg-nx-gold/15 px-5 py-2 text-sm font-medium text-nx-gold hover:bg-nx-gold/25">Browse NFT Packages</a>
                </div>
            @endif
        </div>

        {{-- Team growth --}}
        <div class="nx-panel p-5">
            <div class="flex items-center justify-between border-b border-white/10 pb-4">
                <h3 class="flex items-center gap-3 text-lg font-semibold text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-nx-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4" /></svg>
                    Team Growth
                </h3>
                <a href="{{ route('team') }}" class="rounded-full border border-emerald-400/40 px-4 py-1.5 text-sm text-slate-200 hover:border-nx-gold/60 hover:text-white">View Team</a>
            </div>

            @if ($teamByLevel->isEmpty())
                <p class="py-10 text-center text-sm text-slate-400">No team members yet. Share your referral link to start growing.</p>
            @else
                <ul class="space-y-5 pt-5">
                    @foreach ($teamByLevel as $level => $count)
                        <li class="flex items-center gap-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 {{ $levelColors[($level - 1) % count($levelColors)] }}" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12a4 4 0 100-8 4 4 0 000 8zm-7 8a7 7 0 0114 0v1H5v-1z" /></svg>
                            <span class="w-14 shrink-0 text-sm text-slate-200">Level {{ $level }}</span>
                            <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-white/10">
                                <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-emerald-300 shadow-[0_0_10px] shadow-emerald-500/50" style="width: {{ round($count / $maxLevelCount * 100) }}%"></div>
                            </div>
                            <span class="w-8 shrink-0 text-right text-lg font-semibold text-white">{{ $count }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Income summary --}}
        <div class="nx-panel p-5">
            <div class="flex items-center justify-between border-b border-white/10 pb-4">
                <h3 class="flex items-center gap-3 text-lg font-semibold text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-nx-gold" viewBox="0 0 24 24" fill="currentColor"><path d="M4 20h3V11H4v9zm6.5 0h3V4h-3v16zM17 20h3v-7h-3v7z" /></svg>
                    Income Summary
                </h3>
                <a href="{{ route('wallet') }}" class="rounded-full border border-emerald-400/40 px-4 py-1.5 text-sm text-slate-200 hover:border-nx-gold/60 hover:text-white">View All</a>
            </div>

            <ul class="divide-y divide-white/5 pt-2">
                @foreach ($incomeSummary as $income)
                    @php [$color, $icon] = $incomeStyles[$income['reason']]; @endphp
                    <li class="flex items-center gap-3 py-2.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 {{ $color }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" /></svg>
                        <span class="flex-1 text-sm text-slate-200">{{ $income['label'] }}</span>
                        <span class="text-sm font-semibold text-emerald-400">{{ $nxd($income['total']) }} <span class="text-xs font-normal text-slate-400">NXD</span></span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Activities / Quick actions --}}
    <section class="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_1.1fr]">
        {{-- Recent activities --}}
        <div class="nx-panel p-5">
            <div class="flex items-center justify-between border-b border-white/10 pb-4">
                <h3 class="flex items-center gap-3 text-lg font-semibold text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-nx-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    Recent Activities
                </h3>
                <a href="{{ route('wallet') }}" class="rounded-full border border-emerald-400/40 px-4 py-1.5 text-sm text-slate-200 hover:border-nx-gold/60 hover:text-white">View All</a>
            </div>

            @if ($activities->isEmpty())
                <p class="py-8 text-center text-sm text-slate-400">No activity yet.</p>
            @else
                <ul class="grid grid-cols-1 gap-x-6 gap-y-4 pt-4 sm:grid-cols-2">
                    @foreach ($activities as $activity)
                        @php [$tile, $dot, $icon] = $activityStyles[$activity['kind']]; @endphp
                        <li class="flex items-center gap-3">
                            <span class="h-2 w-2 shrink-0 rounded-full {{ $dot }}"></span>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $tile }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" /></svg>
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm text-white">{{ $activity['title'] }}</p>
                                <p class="truncate text-xs text-slate-400">
                                    <span class="{{ $activity['kind'] === 'credit' ? 'text-emerald-400' : '' }}">{{ $activity['detail'] }}</span>
                                    &bull; {{ $activity['at']->diffForHumans() }}
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Quick actions --}}
        <div class="nx-panel p-5">
            <h3 class="flex items-center gap-3 border-b border-white/10 pb-4 text-lg font-semibold text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-nx-gold" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z" /></svg>
                Quick Actions
            </h3>

            <input id="referral-link" type="hidden" value="{{ route('register', ['ref' => $user->user_id]) }}">

            <div class="grid grid-cols-2 gap-3 pt-4 md:grid-cols-4">
                <button type="button" id="copy-referral-link"
                    class="flex flex-col items-center justify-center gap-2 rounded-xl border border-emerald-400/50 bg-gradient-to-b from-emerald-700/40 to-emerald-950/60 px-3 py-4 text-sm text-white transition hover:border-emerald-300 hover:shadow-[0_0_20px_-6px] hover:shadow-emerald-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-emerald-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                    <span data-label>Copy Referral Link</span>
                </button>

                <a href="{{ route('nft.index') }}"
                    class="flex flex-col items-center justify-center gap-2 rounded-xl border border-nx-gold/60 bg-gradient-to-b from-amber-600/40 to-amber-950/60 px-3 py-4 text-center text-sm text-white transition hover:border-nx-gold hover:shadow-[0_0_20px_-6px] hover:shadow-nx-gold">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-nx-gold" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l9 4.5v11L12 22l-9-4.5v-11L12 2zm0 2.2L5.6 7.4 12 10.6l6.4-3.2L12 4.2z" /></svg>
                    Buy NFT Package
                </a>

                <a href="{{ route('wallet') }}"
                    class="flex flex-col items-center justify-center gap-2 rounded-xl border border-sky-400/50 bg-gradient-to-b from-sky-700/40 to-sky-950/60 px-3 py-4 text-center text-sm text-white transition hover:border-sky-300 hover:shadow-[0_0_20px_-6px] hover:shadow-sky-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-sky-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-9 4h16a2 2 0 002-2V7a2 2 0 00-2-2H3a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    View Wallet
                </a>

                <div title="Withdrawals are coming soon"
                    class="relative flex cursor-not-allowed flex-col items-center justify-center gap-2 rounded-xl border border-violet-400/40 bg-gradient-to-b from-violet-700/30 to-violet-950/50 px-3 py-4 text-center text-sm text-white/70">
                    <span class="absolute top-1.5 right-1.5 rounded bg-white/10 px-1.5 py-0.5 text-[10px] text-slate-300">Soon</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-violet-300/80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20V4m0 0l-6 6m6-6l6 6" /></svg>
                    Withdraw Earnings
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    document.getElementById('copy-referral-link').addEventListener('click', function () {
        const link = document.getElementById('referral-link').value;
        const label = this.querySelector('[data-label]');

        const done = function () {
            label.textContent = 'Copied!';
            setTimeout(function () { label.textContent = 'Copy Referral Link'; }, 1500);
        };

        navigator.clipboard.writeText(link).then(done).catch(function () {
            window.prompt('Copy your referral link:', link);
        });
    });
</script>
@endsection
