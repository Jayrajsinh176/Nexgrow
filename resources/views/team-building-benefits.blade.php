@extends('layouts.dashboard', ['title' => 'Team Building Benefits', 'dark' => true])

@php
    $nxd = fn ($value) => number_format((float) $value, 2);
    $peopleIcon = 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4';

    if ($nextLevel === null) {
        $nextValue = 'All';
        $nextHint = "Every level up to L{$maxLevels} is unlocked";
    } else {
        $nextValue = 'L'.$nextLevel['level'];
        $nextHint = 'Get '.$nextLevel['shortfall'].' more direct '.str('referral')->plural($nextLevel['shortfall']).' to unlock';
    }

    $stats = [
        [
            'label' => 'Direct Referrals', 'value' => $directCount, 'hint' => 'People you personally referred',
            'tile' => 'border-emerald-400/50 bg-emerald-400/10 text-emerald-300', 'valueClass' => 'text-emerald-400',
            'icon' => $peopleIcon,
        ],
        [
            'label' => 'Levels Unlocked', 'value' => "{$unlockedLevels} / {$maxLevels}", 'hint' => 'Refer more people to earn from deeper levels',
            'tile' => 'border-nx-gold/60 bg-nx-gold/10 text-nx-gold', 'valueClass' => $unlockedLevels > 0 ? 'text-emerald-400' : 'text-slate-400',
            'icon' => 'M4 20V10m5 10V4m5 16v-7m5 7V8M3 20h18',
        ],
        [
            'label' => 'Next Level', 'value' => $nextValue, 'hint' => $nextHint,
            'tile' => 'border-nx-gold/60 bg-nx-gold/10 text-nx-gold', 'valueClass' => 'text-nx-gold',
            'icon' => 'M8 21h8m-4-4v4M7 4h10v5a5 5 0 01-10 0V4zm0 2H4v1a3 3 0 003 3m10-4h3v1a3 3 0 01-3 3',
        ],
    ];
@endphp

@section('content')
<div class="space-y-5 text-slate-200">

    {{-- Hero --}}
    <section class="nx-panel nx-panel-gold overflow-hidden">
        <div class="nx-stars pointer-events-none absolute inset-0"></div>
        <div class="nx-dots pointer-events-none absolute inset-x-0 bottom-0 h-2/3"></div>
        <div class="pointer-events-none absolute top-1/2 right-10 h-64 w-64 -translate-y-1/2 rounded-full bg-nx-gold/15 blur-3xl"></div>

        <div class="relative flex items-center justify-between gap-6 p-6 sm:px-8">
            <div class="flex min-w-0 items-center gap-4 sm:gap-6">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl sm:h-20 sm:w-20 border border-nx-gold/60 bg-nx-gold/10 text-nx-gold shadow-[0_0_20px_-8px] shadow-nx-gold">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 sm:h-11 sm:w-11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $peopleIcon }}" /></svg>
                </span>
                <div class="min-w-0">
                    <p class="font-medium text-white">Total Team Building Benefits Earned</p>
                    <p class="nx-gold-text mt-1 text-3xl font-bold break-words sm:text-5xl">{{ $nxd($total) }} NXD</p>
                    <p class="mt-2 text-sm text-slate-300">
                        @foreach (config('team_building_benefits.tiers') as $tier)
                            {{ $tier['percentage'] }}% on level{{ $tier['from'] === $tier['to'] ? ' '.$tier['from'] : 's '.$tier['from'].'–'.$tier['to'] }}{{ $loop->last ? '' : ',' }}
                        @endforeach
                        of your downline's daily NFT bonus. Separate from Referral Income.
                    </p>
                </div>
            </div>

            {{-- Network illustration: you at the top, levels fanning out below --}}
            <svg class="hidden h-32 w-56 shrink-0 lg:block" viewBox="0 0 220 128" aria-hidden="true">
                <g stroke="#22c55e" stroke-opacity=".45" stroke-width="1.2">
                    <path d="M110 30L55 72M110 30l55 42M110 30v42M55 72l-30 38M55 72l0 38M165 72l30 38M165 72v38M110 72l-20 38M110 72l20 38" />
                </g>
                @foreach ([[25, 110], [55, 110], [90, 110], [130, 110], [165, 110], [195, 110]] as [$x, $y])
                    <circle cx="{{ $x }}" cy="{{ $y }}" r="7" fill="#0f2a18" stroke="#4ade80" stroke-width="1.5" />
                @endforeach
                @foreach ([[55, 72], [110, 72], [165, 72]] as [$x, $y])
                    <circle cx="{{ $x }}" cy="{{ $y }}" r="10" fill="#1a1405" stroke="#f2c14e" stroke-width="1.8" />
                @endforeach
                <circle cx="110" cy="30" r="16" fill="#f2c14e" opacity=".2" />
                <circle cx="110" cy="30" r="12" fill="#1a1405" stroke="#f2c14e" stroke-width="2.5" />
                <path d="M110 24a3.5 3.5 0 110 7 3.5 3.5 0 010-7zm-6 13a6 6 0 0112 0" stroke="#f2c14e" stroke-width="1.8" fill="none" stroke-linecap="round" />
            </svg>
        </div>
    </section>

    @if (! $hasActiveNft)
        <div class="flex flex-col items-start justify-between gap-2 rounded-xl border border-amber-400/40 sm:flex-row sm:items-center sm:gap-4 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
            <span>You don't have an active NFT package, so no levels are earning right now — even unlocked ones.</span>
            <a href="{{ route('nft.index') }}" class="shrink-0 font-semibold text-nx-gold hover:underline">Buy NFT</a>
        </div>
    @elseif ($directCount < $minimumDirects)
        <div class="rounded-xl border border-amber-400/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
            You need {{ $minimumDirects - $directCount }} more direct {{ str('referral')->plural($minimumDirects - $directCount) }} to activate this benefit.
        </div>
    @endif

    {{-- Stat cards --}}
    <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
        @foreach ($stats as $stat)
            <div class="nx-panel flex items-center gap-4 p-5">
                <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border {{ $stat['tile'] }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $stat['icon'] }}" />
                    </svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-slate-300">{{ $stat['label'] }}</p>
                    <p class="mt-1 truncate text-2xl font-bold {{ $stat['valueClass'] }}">{{ $stat['value'] }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $stat['hint'] }}</p>
                </div>
            </div>
        @endforeach
    </section>

    {{-- Level benefits --}}
    <section class="nx-panel overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-6 py-4">
            <h3 class="flex items-center gap-3 text-lg font-semibold text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-nx-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $peopleIcon }}" /></svg>
                Level Benefits
            </h3>
            <p class="text-xs text-slate-400">
                {{ $minimumDirects }} directs unlock L1–L{{ $minimumDirects }}; each extra direct unlocks one more level, up to L{{ $maxLevels }}.
            </p>
        </div>

        <input id="referral-link" type="hidden" value="{{ route('register', ['ref' => $user->user_id]) }}">

        <div class="overflow-x-auto">
            <table class="w-full min-w-200 text-left text-sm">
                <thead>
                    <tr class="border-b border-white/10 bg-white/3 text-emerald-300">
                        <th class="px-6 py-3.5 font-semibold">Level</th>
                        <th class="px-4 py-3.5 font-semibold">Required Direct Referrals</th>
                        <th class="px-4 py-3.5 font-semibold">Percentage</th>
                        <th class="px-4 py-3.5 font-semibold">Status</th>
                        <th class="px-6 py-3.5 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @foreach ($levels as $row)
                        <tr class="transition hover:bg-white/3">
                            <td class="px-6 py-2.5 font-semibold {{ $row['unlocked'] ? 'text-emerald-400' : 'text-slate-300' }}">L{{ $row['level'] }}</td>
                            <td class="px-4 py-2.5 text-slate-200">{{ $row['required'] }}</td>
                            <td class="px-4 py-2.5 text-slate-200">{{ $row['percentage'] }}%</td>
                            <td class="px-4 py-2.5">
                                @if ($row['unlocked'])
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-medium text-emerald-300 ring-1 ring-emerald-400/30">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M12 2a10 10 0 100 20 10 10 0 000-20zm4.7 7.7l-5.5 5.5a1 1 0 01-1.4 0l-2.5-2.5a1 1 0 111.4-1.4l1.8 1.8 4.8-4.8a1 1 0 111.4 1.4z" clip-rule="evenodd" /></svg>
                                        Unlocked
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/5 px-3 py-1 text-xs font-medium text-slate-300 ring-1 ring-white/10">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                        Locked
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-2.5 text-right">
                                @if ($row['unlocked'])
                                    <span class="text-slate-500">—</span>
                                @else
                                    @php $label = 'Get '.$row['shortfall'].' more '.str('referral')->plural($row['shortfall']); @endphp
                                    <button type="button" data-copy-referral data-label="{{ $label }}" title="Copy your referral link"
                                        class="min-w-44 rounded-lg border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium text-slate-200 transition hover:border-nx-gold/60 hover:text-nx-gold">
                                        {{ $label }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- Benefit history --}}
    <section id="history" class="nx-panel scroll-mt-28 overflow-hidden">
        <h3 class="flex items-center gap-3 border-b border-white/10 px-6 py-4 text-lg font-semibold text-white">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-nx-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3M3.05 11a9 9 0 11.5 4M3 4v5h5" /></svg>
            Benefit History
        </h3>

        @if ($history->isEmpty())
            <p class="px-6 py-12 text-center text-sm text-slate-400">No Team Building Benefits yet. It's earned automatically when someone in your unlocked levels receives a daily NFT bonus.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-225 text-left text-sm">
                    <thead>
                        <tr class="border-b border-white/10 bg-white/3 text-slate-200">
                            <th class="px-6 py-3.5 font-semibold">Date</th>
                            <th class="px-4 py-3.5 font-semibold">From</th>
                            <th class="px-4 py-3.5 font-semibold">Level</th>
                            <th class="px-4 py-3.5 font-semibold">Percentage</th>
                            <th class="px-4 py-3.5 font-semibold">Base Amount</th>
                            <th class="px-6 py-3.5 font-semibold">Earned</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach ($history as $benefit)
                            <tr class="transition hover:bg-white/3">
                                <td class="px-6 py-3 whitespace-nowrap text-slate-300">{{ $benefit->created_at->format('d M Y, h:i A') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="text-white">{{ $benefit->sourceUser->name }}</span>
                                    <span class="text-sky-400/80">({{ $benefit->sourceUser->user_id }})</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex min-w-10 justify-center rounded-lg px-2 py-1 text-xs font-bold ring-1 {{ $benefit->level == 1 ? 'bg-emerald-600/40 text-emerald-200 ring-emerald-400/40' : 'bg-sky-600/30 text-sky-200 ring-sky-400/40' }}">
                                        L{{ $benefit->level }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-200">{{ number_format((float) $benefit->percentage, 2) }}%</td>
                                <td class="px-4 py-3 text-slate-200">{{ $nxd($benefit->base_amount) }}</td>
                                <td class="px-6 py-3 font-semibold text-emerald-400">+{{ $nxd($benefit->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 px-6 py-4">
                <p class="text-sm text-slate-400">
                    Showing <span class="text-white">{{ $history->firstItem() }}</span>–<span class="text-white">{{ $history->lastItem() }}</span>
                    of <span class="text-white">{{ $history->total() }}</span> entries
                </p>
                {{ $history->links('pagination.nx') }}
            </div>
        @endif
    </section>
</div>

<script>
    (function () {
        const link = document.getElementById('referral-link').value;

        document.querySelectorAll('[data-copy-referral]').forEach(function (button) {
            button.addEventListener('click', function () {
                const done = function () {
                    button.textContent = 'Referral link copied!';
                    setTimeout(function () { button.textContent = button.dataset.label; }, 1500);
                };

                navigator.clipboard.writeText(link).then(done).catch(function () {
                    window.prompt('Copy your referral link:', link);
                });
            });
        });
    })();
</script>
@endsection
