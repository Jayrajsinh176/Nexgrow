@extends('layouts.dashboard', ['title' => 'Monthly Loyalty Bonus', 'dark' => true])

@php
    $nxd = fn ($value, $dec = 2) => number_format((float) $value, $dec);
    $benefit = config('monthly_loyalty_bonus.profit_share_percent', 5);
    $thresholdF = (float) $threshold;
    $progressF = (float) $progress;
    $heroImage = file_exists(public_path('monthly-hero.png')) ? asset('monthly-hero.png') : null;
    $milestones = [0, 25, 50, 75, 100];

    $badgeOk = 'border-emerald-400/50 bg-emerald-400/10 text-emerald-300';
    $badgeNo = 'border-rose-500/60 bg-rose-500/10 text-rose-300';
    $clock = 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z';
    $check = 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z';

    $rows = collect();
    if ($history->onFirstPage()) {
        $rows->push([
            'month' => $periodStart->format('F Y'),
            'business' => $currentBusiness,
            'qualified' => $qualified,
        ]);
    }
    foreach ($history as $record) {
        $rows->push([
            'month' => $record->period_start->format('F Y'),
            'business' => $record->direct_business,
            'qualified' => $record->qualified,
        ]);
    }
@endphp

@section('content')
<style>
    .ml-hero { display: flex; flex-direction: column; gap: 1.5rem; }
    .ml-main { display: flex; align-items: center; gap: 1.25rem; min-width: 0; }
    .ml-body { flex: 1 1 0%; min-width: 0; }
    .ml-status { width: 100%; }
    .ml-art { display: none; }
    .ml-grad { background: linear-gradient(90deg, #22c55e, #f2c14e); }
    .ml-bar { height: 6px; border-radius: 9999px; background: rgba(255,255,255,.12); overflow: hidden; }
    .ml-bar > span { display: block; height: 100%; border-radius: 9999px; }
    @media (min-width: 1024px) {
        .ml-hero { display: grid; grid-template-columns: minmax(0, 1fr) 20rem; align-items: center; }
        .ml-status { width: auto; }
    }
    @media (min-width: 1280px) {
        .ml-art { display: block; position: absolute; right: 21rem; bottom: 0; height: 100%; max-width: 35%; object-fit: contain; object-position: right bottom; pointer-events: none; }
    }
</style>

<div class="space-y-5 text-slate-200">

    {{-- Hero --}}
    <section class="nx-panel nx-panel-gold overflow-hidden">
        <div class="nx-stars pointer-events-none absolute inset-0"></div>
        <div class="nx-dots pointer-events-none absolute inset-x-0 bottom-0 h-2/3"></div>
        <div class="pointer-events-none absolute right-40 top-1/2 h-72 w-72 -translate-y-1/2 rounded-full bg-nx-gold/15 blur-3xl"></div>
        @if ($heroImage)
            <img src="{{ $heroImage }}" alt="" class="ml-art">
        @endif

        <div class="ml-hero relative p-5 sm:px-6">
            <div class="ml-main">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl sm:h-20 sm:w-20 border border-nx-gold/60 bg-nx-gold/10 text-nx-gold shadow-[0_0_20px_-8px] shadow-nx-gold">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 sm:h-11 sm:w-11" viewBox="0 0 24 24" fill="currentColor"><path d="M3 8l4.5 4L12 5l4.5 7L21 8l-2 11H5L3 8zm2.6 12h12.8v1.5H5.6V20z"/></svg>
                </span>
                <div class="ml-body">
                    <p class="font-medium text-white">Direct Monthly Business ({{ $periodStart->format('F Y') }})</p>
                    <p class="nx-gold-text mt-1 text-3xl font-bold break-words sm:text-5xl">{{ $nxd($currentBusiness) }} <span class="text-2xl">NXD</span></p>
                    <p class="mt-2 text-sm text-slate-300">Combined business from your direct (level-1) referrals this month.</p>
                    <p class="mt-1 flex flex-wrap items-center gap-x-2 text-sm font-medium">
                        <span class="text-nx-gold">Threshold to qualify: {{ $nxd($threshold, 0) }}</span>
                        <span class="text-slate-500">|</span>
                        <span class="text-emerald-400">Current Progress: {{ $nxd($currentBusiness) }} ({{ $nxd($progressF) }}%)</span>
                    </p>
                </div>
            </div>

            <div class="ml-status relative rounded-xl border border-white/10 bg-black/30 p-5">
                <span class="inline-flex items-center gap-2 rounded-lg border px-3.5 py-1.5 text-base font-semibold {{ $qualified ? $badgeOk : $badgeNo }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $qualified ? $check : $clock }}"/></svg>
                    {{ $qualified ? 'Achieved' : 'Not Achieved' }}
                </span>
                <p class="mt-3 text-sm text-slate-200">
                    @if ($qualified)
                        You qualified for the {{ $benefit }}% company-wide profit-sharing distribution this month.
                    @else
                        Reach {{ $nxd($threshold, 0) }} in Direct Monthly Business to qualify for {{ $benefit }}% company-wide profit-sharing distribution.
                    @endif
                </p>
            </div>
        </div>
    </section>

    {{-- Stat cards --}}
    <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="nx-panel flex items-center gap-4 p-5">
            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border border-emerald-400/50 bg-emerald-400/10 text-emerald-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-9 w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/></svg>
            </span>
            <div class="min-w-0">
                <p class="text-sm text-slate-300">Monthly Target</p>
                <p class="mt-1 text-3xl font-bold text-emerald-400">{{ $nxd($threshold, 0) }}</p>
                <p class="mt-1 text-xs text-slate-400">Direct monthly business required</p>
            </div>
        </div>

        <div class="nx-panel flex items-center gap-4 p-5">
            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border border-emerald-400/50 bg-emerald-400/10 text-emerald-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-9 w-9" viewBox="0 0 24 24" fill="currentColor"><path d="M4 20V11h4v9H4zm6 0V4h4v16h-4zm6 0v-6h4v6h-4z"/></svg>
            </span>
            <div class="min-w-0">
                <p class="text-sm text-slate-300">Current Business</p>
                <p class="mt-1 text-3xl font-bold text-emerald-400">{{ $nxd($currentBusiness) }}</p>
                <p class="mt-1 text-xs text-slate-400">This month ({{ $periodStart->format('F Y') }})</p>
            </div>
        </div>

        <div class="nx-panel nx-panel-gold flex items-center gap-4 p-5">
            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border border-nx-gold/60 bg-nx-gold/10 text-nx-gold">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-9 w-9" viewBox="0 0 24 24" fill="currentColor"><path d="M11 2.05V13h10.95A10 10 0 0011 2.05zM9 4.06A10 10 0 1019.94 15H9V4.06z"/></svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm text-slate-300">Progress</p>
                <p class="mt-1 text-3xl font-bold text-emerald-400">{{ $nxd($progressF) }}%</p>
                <div class="ml-bar mt-1.5"><span class="ml-grad" style="width: {{ min(100, $progressF) }}%"></span></div>
                <p class="mt-1 text-xs text-slate-400">{{ $nxd($currentBusiness, 0) }} / {{ $nxd($threshold, 0) }}</p>
            </div>
        </div>

        <div class="nx-panel nx-panel-gold flex items-center gap-4 p-5">
            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border border-nx-gold/60 bg-nx-gold/10 text-nx-gold">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-9 w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12v9H4v-9m16 0H4m16 0V8H4v4m8-4v13M12 8c-2-3-6-3-6-1s3 1 6 1zm0 0c2-3 6-3 6-1s-3 1-6 1z"/></svg>
            </span>
            <div class="min-w-0">
                <p class="text-sm text-slate-300">Expected Benefit</p>
                <p class="mt-1 text-3xl font-bold text-white">{{ $benefit }}%</p>
                <p class="mt-1 text-xs text-slate-400">Company-wide profit-sharing distribution on total project business</p>
            </div>
        </div>
    </section>

    {{-- Progress track --}}
    <section class="nx-panel p-5">
        <h3 class="mb-6 flex items-center gap-3 text-lg font-semibold text-white">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-nx-gold" viewBox="0 0 24 24" fill="currentColor"><path d="M5 3h2v18H5V3zm3 1h11l-2 4 2 4H8V4z"/></svg>
            Your Progress This Month
        </h3>

        <div style="padding: 0 2.5rem;">
            {{-- Track + markers --}}
            <div style="position:relative;height:32px;">
                <div style="position:absolute;left:0;right:0;top:50%;height:6px;margin-top:-3px;border-radius:9999px;background:rgba(255,255,255,.12);"></div>
                <div class="ml-grad" style="position:absolute;left:0;top:50%;height:6px;margin-top:-3px;border-radius:9999px;width:{{ min(100, $progressF) }}%;"></div>

                @foreach ($milestones as $m)
                    @php $reached = $m === 0 || $progressF >= $m; @endphp
                    <div style="position:absolute;top:50%;left:{{ $m }}%;transform:translate(-50%,-50%);">
                        @if ($m === 100)
                            <span style="display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:9999px;border:2px solid #f2c14e;background:{{ $reached ? '#f2c14e' : '#07150e' }};color:{{ $reached ? '#000' : '#f2c14e' }};box-shadow:0 0 14px -2px #f2c14e;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.9 7.5.7-5.7 5 1.7 7.4L12 18l-6.5 4 1.7-7.4-5.7-5 7.5-.7L12 2z"/></svg>
                            </span>
                        @else
                            <span style="display:block;width:24px;height:24px;border-radius:9999px;border:2px solid {{ $reached ? '#34d399' : 'rgba(110,231,183,.7)' }};background:{{ $reached ? '#34d399' : '#07150e' }};"></span>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Labels --}}
            <div style="position:relative;height:64px;margin-top:12px;">
                @foreach ($milestones as $m)
                    @php
                        $first = $m === 0;
                        $last = $m === 100;
                        $pos = $first ? 'left:-12px;align-items:flex-start;text-align:left;'
                            : ($last ? 'right:-16px;align-items:flex-end;text-align:right;'
                            : 'left:'.$m.'%;transform:translateX(-50%);align-items:center;text-align:center;');
                    @endphp
                    <div style="position:absolute;top:0;display:flex;flex-direction:column;white-space:nowrap;{{ $pos }}">
                        <p class="text-sm font-semibold text-white">{{ $first ? '0' : $nxd($thresholdF * $m / 100, 0) }}</p>
                        <p class="text-xs text-slate-400">{{ $first ? 'Start' : $m.'%' }}</p>
                        @if ($last)
                            <p class="text-xs text-slate-400">Qualify for {{ $benefit }}% Bonus</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-4 flex items-start gap-4 rounded-xl border border-emerald-400/25 bg-emerald-400/5 p-4 text-sm text-slate-200">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-400 text-black">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M11 10h2v8h-2v-8zm0-4h2v2h-2V6z"/></svg>
            </span>
            <p>
                Reach {{ $nxd($threshold, 0) }} in Direct Monthly Business qualifies you for that month's {{ $benefit }}% company-wide profit-sharing distribution on the entire project's business.
                The distribution amount for qualified members is still being finalized with the client &mdash; this page currently tracks eligibility only; payouts aren't credited yet.
            </p>
        </div>
    </section>

    {{-- Monthly history --}}
    <section id="history" class="nx-panel nx-panel-gold scroll-mt-28 overflow-hidden">
        <div class="flex items-center gap-4 px-6 pt-5 pb-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-nx-gold/60 bg-nx-gold/10 text-nx-gold">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"/></svg>
            </span>
            <div>
                <h3 class="text-lg font-semibold text-white">Monthly History</h3>
                <p class="text-sm text-slate-400">Track your direct monthly business and qualification status.</p>
            </div>
        </div>

        <div class="overflow-x-auto px-3 pb-3">
            <table class="w-full min-w-225 text-left text-sm">
                <thead>
                    <tr class="bg-emerald-950/60 text-slate-200">
                        <th class="px-4 py-3 font-medium">Month</th>
                        <th class="px-4 py-3 font-medium">Direct Business (NXD)</th>
                        <th class="px-4 py-3 font-medium">Target</th>
                        <th class="px-4 py-3 font-medium">Progress</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Benefit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @foreach ($rows as $row)
                        @php $rowPct = $thresholdF > 0 ? min(100, (float) $row['business'] / $thresholdF * 100) : 0; @endphp
                        <tr class="text-slate-200 transition hover:bg-white/3">
                            <td class="px-4 py-3 font-medium whitespace-nowrap text-white">{{ $row['month'] }}</td>
                            <td class="px-4 py-3">{{ $nxd($row['business']) }}</td>
                            <td class="px-4 py-3">{{ $nxd($threshold, 0) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="w-14 text-xs">{{ $nxd($rowPct) }}%</span>
                                    <div class="ml-bar" style="width: 11rem;"><span class="ml-grad" style="width: {{ $rowPct }}%"></span></div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-0.5 text-xs font-medium {{ $row['qualified'] ? $badgeOk : $badgeNo }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $row['qualified'] ? $check : $clock }}"/></svg>
                                    {{ $row['qualified'] ? 'Achieved' : 'Not Achieved' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $benefit }}% (Not Credited)</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($history->isEmpty())
            <p class="border-t border-white/10 px-6 py-4 text-sm text-slate-400">
                No completed months yet. {{ $periodStart->format('F Y') }} is your first tracked month, closing on {{ $periodEnd->format('d M Y') }}.
            </p>
        @endif

        @if ($history->hasPages())
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 px-6 py-4">
                <p class="text-sm text-slate-400">
                    Showing <span class="text-white">{{ $history->firstItem() }}</span>–<span class="text-white">{{ $history->lastItem() }}</span>
                    of <span class="text-white">{{ $history->total() }}</span> entries
                </p>
                {{ $history->fragment('history')->links('pagination.nx') }}
            </div>
        @endif
    </section>
</div>
@endsection