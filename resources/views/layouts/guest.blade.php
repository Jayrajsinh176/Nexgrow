<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'Nexgrow') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="nx-shell min-h-screen text-slate-200 antialiased">
    <div class="mx-auto grid min-h-screen w-full max-w-7xl grid-cols-1 gap-6 px-4 py-6 sm:px-6 sm:py-10 lg:grid-cols-[1.05fr_1fr] lg:items-center lg:gap-10 lg:px-8">

        {{-- Brand panel: full showcase on desktop, compact banner on mobile --}}
        <aside class="nx-panel nx-panel-gold overflow-hidden lg:min-h-[640px]">
            <div class="nx-stars pointer-events-none absolute inset-0"></div>
            <div class="nx-dots pointer-events-none absolute inset-x-0 bottom-0 h-2/3"></div>
            <div class="pointer-events-none absolute -right-16 top-1/3 h-80 w-80 rounded-full bg-nx-gold/15 blur-3xl"></div>
            <div class="pointer-events-none absolute -left-20 bottom-0 h-72 w-72 rounded-full bg-emerald-500/15 blur-3xl"></div>

            <div class="relative flex h-full flex-col gap-6 p-5 sm:p-8 lg:justify-between lg:p-10">
                <a href="{{ url('/') }}" class="block">
                    <img src="{{ asset('nexgrow.png') }}" alt="Nexgrow" class="h-auto w-40 sm:w-52 lg:w-60">
                </a>

                <div class="flex items-center justify-between gap-5 lg:flex-col lg:items-start lg:gap-8">
                    <div class="min-w-0">
                        <p class="text-xl font-bold text-white sm:text-3xl lg:text-4xl">One Token.<br class="hidden lg:block"> A World of Utility.</p>
                        <p class="mt-2 text-sm text-nx-gold sm:text-lg">
                            Play <span class="text-slate-400">&bull;</span> Shop <span class="text-slate-400">&bull;</span> Use <span class="text-slate-400">&bull;</span> Reward
                        </p>
                    </div>

                    <div class="nx-coin-scene h-20 w-20 shrink-0 sm:h-28 sm:w-28 lg:order-first lg:h-40 lg:w-40">
                        <div class="nx-coin-flip">
                            <span class="nx-coin nx-coin-face"><img src="{{ asset('coin_nexadon.png') }}" alt="Nexadon coin" class="h-full w-full scale-[1.06] object-cover"></span>
                            <span class="nx-coin nx-coin-face nx-coin-back" aria-hidden="true"><img src="{{ asset('coin_nexadon.png') }}" alt="" class="h-full w-full scale-[1.06] object-cover"></span>
                        </div>
                    </div>
                </div>

                <ul class="hidden space-y-3 lg:block">
                    @foreach ([
                        ['Daily NFT bonus', 'Earn every day from your active NFT package.', 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
                        ['Team rewards', 'Referral, team building and performance bonuses.', 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4'],
                        ['NXD token wallet', 'Track every credit and payout in one place.', 'M3 10h18M7 15h1m4 0h1m-9 4h16a2 2 0 002-2V7a2 2 0 00-2-2H3a2 2 0 00-2 2v10a2 2 0 002 2z'],
                    ] as [$heading, $text, $icon])
                        <li class="flex items-start gap-4 rounded-xl border border-white/10 bg-black/20 p-4 backdrop-blur-sm">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-nx-gold/50 bg-nx-gold/10 text-nx-gold">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" /></svg>
                            </span>
                            <div>
                                <p class="font-semibold text-white">{{ $heading }}</p>
                                <p class="text-sm text-slate-400">{{ $text }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>

        {{-- Form card --}}
        <main class="w-full lg:mx-auto lg:max-w-lg">
            <div class="nx-panel p-5 sm:p-8">
                @yield('content')
            </div>

            <p class="mt-5 text-center text-xs text-slate-500">&copy; {{ date('Y') }} Nexgrow. All rights reserved.</p>
        </main>
    </div>

    <script>
        // Show / hide for any password input with a [data-toggle-password="id"] button.
        document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const input = document.getElementById(btn.dataset.togglePassword);
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                btn.querySelector('[data-eye-open]').classList.toggle('hidden', show);
                btn.querySelector('[data-eye-closed]').classList.toggle('hidden', !show);
            });
        });
    </script>
</body>
</html>
