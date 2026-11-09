<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'Nexgrow') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <script type="application/json" id="nx-chain-config">@json(\App\Support\Chain::browserConfig(auth()->user()))</script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    // Pages opt into the full dark canvas with ['dark' => true]; the rest keep
    // the light content area inside the dark sidebar/header shell.
    $dark = $dark ?? false;

    $navItems = [
        ['route' => 'dashboard', 'active' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['route' => 'profile.edit', 'active' => 'profile.*', 'label' => 'My Profile', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
        ['route' => 'team', 'active' => 'team', 'label' => 'My Team', 'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4'],
        ['route' => 'wallet', 'active' => 'wallet', 'label' => 'Token Wallet', 'icon' => 'M3 10h18M7 15h1m4 0h1m-9 4h16a2 2 0 002-2V7a2 2 0 00-2-2H3a2 2 0 00-2 2v10a2 2 0 002 2z'],
        ['route' => 'nft.index', 'active' => 'nft.index', 'label' => 'NFT Packages', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
        ['route' => 'referral-income', 'active' => 'referral-income', 'label' => 'Referral Income', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m9-8a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['route' => 'team-building-benefits', 'active' => 'team-building-benefits', 'label' => 'Team Building Benefits', 'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4'],
        ['route' => 'booster', 'active' => 'booster', 'label' => '15-Day Booster', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
        ['route' => 'team-performance-bonus', 'active' => 'team-performance-bonus', 'label' => 'Team Performance Bonus', 'icon' => 'M4 20V10m5 10V4m5 16v-7m5 7V8M3 20h18'],
        ['route' => 'monthly-loyalty-bonus', 'active' => 'monthly-loyalty-bonus', 'label' => 'Monthly Loyalty Bonus', 'icon' => 'M11.48 3.5a.56.56 0 011.04 0l2.13 5.11 5.52.44c.5.04.7.66.32.99l-4.2 3.6 1.28 5.38a.56.56 0 01-.84.61L12 16.77l-4.73 2.86a.56.56 0 01-.84-.61l1.28-5.38-4.2-3.6a.56.56 0 01.32-.99l5.52-.44 2.13-5.11z'],
        ['route' => 'royalty-bonus', 'active' => 'royalty-bonus', 'label' => 'Royalty Bonus', 'icon' => 'M3 8l4 4 5-7 5 7 4-4-2 11H5L3 8z'],
        ['route' => 'team-development-bonus', 'active' => 'team-development-bonus', 'label' => 'Team Development Bonus', 'icon' => 'M12 3a2.5 2.5 0 100 5 2.5 2.5 0 000-5zM5 16a2.5 2.5 0 100 5 2.5 2.5 0 000-5zm14 0a2.5 2.5 0 100 5 2.5 2.5 0 000-5zM12 8v4m0 0l-5.5 4M12 12l5.5 4'],
        ['route' => 'profit-sharing-slot.index', 'active' => 'profit-sharing-slot.*', 'label' => 'Profit-Sharing Slot', 'icon' => 'M11 3.05A9 9 0 1020.95 13H11V3.05zM14 3.5V10h6.5A9 9 0 0014 3.5z'],
    ];
@endphp
<body class="min-h-screen bg-nx-bg text-slate-900 antialiased">
    <div class="flex min-h-screen">
        {{-- Mobile backdrop --}}
        <div id="nx-sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-black/60 backdrop-blur-sm lg:hidden" aria-hidden="true"></div>

        {{-- Sidebar: off-canvas drawer below lg, pinned column above --}}
        <aside id="nx-sidebar"
            class="fixed inset-y-0 left-0 z-40 flex h-dvh w-72 max-w-[85vw] shrink-0 -translate-x-full flex-col border-r border-nx-line bg-gradient-to-b from-[#07130c] to-nx-bg text-slate-300 transition-transform duration-300 ease-out lg:sticky lg:top-0 lg:h-screen lg:max-w-none lg:translate-x-0">
            <div class="flex h-20 shrink-0 items-center justify-between gap-2 px-5 lg:h-24">
                <a href="{{ auth()->user()?->is_admin ? route('admin.dashboard') : route('dashboard') }}" class="block min-w-0">
                    <img src="{{ asset('nexgrow.png') }}" alt="Nexgrow" class="h-auto w-full max-w-[200px] lg:max-w-[220px]">
                </a>
                <button type="button" data-nx-sidebar-close aria-label="Close menu"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-white/10 text-slate-300 hover:border-nx-gold/60 hover:text-white lg:hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <nav class="nx-sidebar-scroll min-h-0 flex-1 space-y-1.5 overflow-y-auto px-4 py-4">
                @if (auth()->user()?->is_admin)
                    {{-- Admin-only navigation --}}
                    <div class="mb-2 px-4 text-xs font-semibold tracking-wider text-nx-gold/70 uppercase">
                        Admin Console
                    </div>

                    <a href="{{ route('admin.dashboard') }}"
                        class="flex items-center gap-3.5 rounded-xl border border-transparent px-4 py-2.5 text-[15px] font-medium transition {{ request()->routeIs('admin.dashboard') ? 'nx-nav-active text-white' : 'text-slate-200 hover:bg-white/5 hover:text-white' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-nx-gold/80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        Admin Overview
                    </a>

                    <a href="{{ route('admin.users.index') }}"
                        class="flex items-center gap-3.5 rounded-xl border border-transparent px-4 py-2.5 text-[15px] font-medium transition {{ request()->routeIs('admin.users.*') ? 'nx-nav-active text-white' : 'text-slate-200 hover:bg-white/5 hover:text-white' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-nx-gold/80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        Manage Users
                    </a>

                    {{-- All Incomes dropdown: the overview plus one page per stream --}}
                    @php
                        $onIncomes = request()->routeIs('admin.incomes.*');
                        $currentStream = $onIncomes ? (request()->route('stream') ?? request()->query('stream')) : null;
                        $incomeStreams = \App\Http\Controllers\Admin\IncomeManagementController::STREAMS;
                        if (! isset($incomeStreams[$currentStream])) {
                            $currentStream = null;
                        }
                    @endphp
                    <details class="group" @if ($onIncomes) open @endif>
                        <summary
                            class="flex cursor-pointer list-none items-center gap-3.5 rounded-xl border border-transparent px-4 py-2.5 text-[15px] font-medium transition [&::-webkit-details-marker]:hidden {{ $onIncomes ? 'nx-nav-active text-white' : 'text-slate-200 hover:bg-white/5 hover:text-white' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-nx-gold/80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m9-8a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="flex-1">All Incomes</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </summary>

                        <div class="mt-1 ml-6 space-y-0.5 border-l border-white/10 pl-3">
                            <a href="{{ route('admin.incomes.index') }}"
                                class="block rounded-lg px-3 py-1.5 text-sm transition {{ $onIncomes && $currentStream === null ? 'bg-nx-gold/10 font-semibold text-nx-gold' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                                Overview
                            </a>
                            @foreach ($incomeStreams as $key => $streamInfo)
                                <a href="{{ route('admin.incomes.index', ['stream' => $key]) }}"
                                    class="block rounded-lg px-3 py-1.5 text-sm transition {{ $currentStream === $key ? 'bg-nx-gold/10 font-semibold text-nx-gold' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                                    {{ $streamInfo['title'] }}
                                </a>
                            @endforeach
                        </div>
                    </details>

                    <a href="{{ route('admin.blockchain.index') }}"
                        class="flex items-center gap-3.5 rounded-xl border border-transparent px-4 py-2.5 text-[15px] font-medium transition {{ request()->routeIs('admin.blockchain.*') ? 'nx-nav-active text-white' : 'text-slate-200 hover:bg-white/5 hover:text-white' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-nx-gold/80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Blockchain Ops
                    </a>

                    <a href="{{ route('admin.owner-actions') }}"
                        class="flex items-center gap-3.5 rounded-xl border border-transparent px-4 py-2.5 text-[15px] font-medium transition {{ request()->routeIs('admin.owner-actions') ? 'nx-nav-active text-white' : 'text-slate-200 hover:bg-white/5 hover:text-white' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-nx-gold/80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        Owner Actions
                    </a>
                @else
                    {{-- Regular member navigation --}}
                    @foreach ($navItems as $item)
                        @php $isActive = request()->routeIs($item['active']); @endphp
                        <a href="{{ route($item['route']) }}"
                            class="flex items-center gap-3.5 rounded-xl border border-transparent px-4 py-2.5 text-[15px] font-medium transition {{ $isActive ? 'nx-nav-active text-white' : 'text-slate-200 hover:bg-white/5 hover:text-white' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 {{ $isActive ? 'text-nx-gold' : 'text-nx-gold/80' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                            </svg>
                            {{ $item['label'] }}
                        </a>
                    @endforeach

                    <div class="flex cursor-not-allowed items-center justify-between rounded-xl px-4 py-2.5 text-[15px] text-slate-500">
                        <span class="flex items-center gap-3.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Wallet &amp; Withdrawal
                        </span>
                        <span class="rounded bg-white/5 px-1.5 py-0.5 text-[10px] font-medium text-slate-400">Soon</span>
                    </div>
                @endif
            </nav>

            <div class="shrink-0 border-t border-nx-line px-4 py-4">
                <div class="mb-3 flex items-center gap-3 px-2">
                    <span class="nx-avatar flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-lg font-semibold text-nx-gold">
                        {{ strtoupper(mb_substr(auth()->user()->name ?: auth()->user()->user_id, 0, 1)) }}
                    </span>
                    <div class="min-w-0">
                        <p class="truncate font-mono text-base font-semibold text-white">{{ auth()->user()->name ?: auth()->user()->user_id }}</p>
                        <p class="truncate font-mono text-xs text-slate-400">
                            {{ auth()->user()->wallet_address ? Str::substr(auth()->user()->wallet_address, 0, 6) . '…' . Str::substr(auth()->user()->wallet_address, -4) : '' }}
                        </p>
                    </div>
                </div>
                <form method="POST" action="{{ auth()->user()?->is_admin ? route('admin.logout') : route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="flex w-full items-center gap-3.5 rounded-xl px-4 py-2.5 text-[15px] font-medium text-slate-200 hover:bg-white/5 hover:text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Log out
                    </button>
                </form>
            </div>
        </aside>

        {{-- Main content --}}
        <div class="min-w-0 flex-1 {{ $dark ? 'nx-shell' : 'bg-slate-50' }}">
            <header class="sticky top-0 z-20 px-3 pt-3 sm:px-6 {{ $dark ? 'bg-nx-bg/80' : 'bg-slate-50/80' }} backdrop-blur">
                <div class="nx-panel flex h-16 items-center justify-between gap-3 !rounded-2xl px-3 sm:h-20 sm:gap-6 sm:px-6">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button" data-nx-sidebar-open aria-label="Open menu" aria-controls="nx-sidebar" aria-expanded="false"
                            class="relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-nx-gold/40 bg-nx-gold/10 text-nx-gold transition hover:border-nx-gold hover:bg-nx-gold/20 lg:hidden">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                        </button>
                        <div class="min-w-0">
                            <h1 class="truncate text-lg font-semibold text-white sm:text-2xl">{{ $title ?? 'Dashboard' }}</h1>
                            <p class="truncate text-xs text-slate-300 sm:text-sm">
                                <span class="sm:hidden">{{ now()->format('D, j M Y') }}</span>
                                <span class="hidden sm:inline">{{ now()->format('l, j F Y') }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        <span class="nx-avatar flex h-10 w-10 items-center justify-center rounded-full text-base font-semibold text-nx-gold sm:h-12 sm:w-12 sm:text-lg">
                            {{ strtoupper(mb_substr(auth()->user()->name ?: auth()->user()->user_id, 0, 1)) }}
                        </span>
                        <div class="hidden leading-tight sm:block">
                            @if (auth()->user()->name)
                                <p class="text-base font-semibold text-white">{{ auth()->user()->name }}</p>
                                <p class="font-mono text-xs text-slate-400">{{ auth()->user()->user_id }}</p>
                            @else
                                <p class="font-mono text-base font-semibold text-white">{{ auth()->user()->user_id }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </header>

            <main class="px-3 py-5 sm:px-6 sm:py-6">
                @if (session()->has(\App\Http\Controllers\Admin\ImpersonationController::SESSION_KEY))
                    {{-- An admin is viewing the app as this member. --}}
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-400/50 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                        <p>
                            Admin view: you are viewing as <strong class="text-white">{{ auth()->user()->name }}</strong>
                            <span class="font-mono text-nx-gold">({{ auth()->user()->user_id }})</span>. Changes are disabled.
                            @error('impersonation')
                                <span class="block text-rose-300">{{ $message }}</span>
                            @enderror
                        </p>
                        <form method="POST" action="{{ route('impersonate.stop') }}">
                            @csrf
                            <button type="submit"
                                class="rounded-xl border border-nx-gold/60 bg-nx-gold/15 px-4 py-2 text-sm font-semibold text-nx-gold hover:bg-nx-gold/25">
                                Return to admin
                            </button>
                        </form>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script>
        (function () {
            const sidebar = document.getElementById('nx-sidebar');
            const backdrop = document.getElementById('nx-sidebar-backdrop');
            const openBtn = document.querySelector('[data-nx-sidebar-open]');

            function setOpen(open) {
                sidebar.classList.toggle('-translate-x-full', !open);
                backdrop.classList.toggle('hidden', !open);
                document.body.classList.toggle('overflow-hidden', open);
                openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            openBtn.addEventListener('click', () => setOpen(true));
            backdrop.addEventListener('click', () => setOpen(false));
            document.querySelectorAll('[data-nx-sidebar-close]').forEach((el) => el.addEventListener('click', () => setOpen(false)));
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });
            window.matchMedia('(min-width: 1024px)').addEventListener('change', (e) => { if (e.matches) setOpen(false); });
        })();

        // Automatically log out if connected MetaMask wallet is disconnected or switched
        (function () {
            const userWallet = @json(auth()->user()?->wallet_address);
            if (userWallet && typeof window !== 'undefined' && window.ethereum) {
                window.ethereum.on('accountsChanged', function (accounts) {
                    if (!accounts || accounts.length === 0 || accounts[0].toLowerCase() !== userWallet.toLowerCase()) {
                        const logoutForm = document.querySelector('form[action*="logout"]');
                        if (logoutForm) logoutForm.submit();
                    }
                });
            }
        })();
    </script>
</body>
</html>
