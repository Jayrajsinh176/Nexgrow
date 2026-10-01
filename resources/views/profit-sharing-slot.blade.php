@extends('layouts.dashboard', ['title' => 'Profit-Sharing Slot', 'dark' => true])

@php
    $nxd = fn ($value, $dec = 2) => number_format((float) $value, $dec);
    $soldPct = $totalSlots > 0 ? min(100, $sold / $totalSlots * 100) : 0;

    $calendar = 'M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z';
    $coinsIcon = 'M12 4c4.4 0 8 1.3 8 3s-3.6 3-8 3-8-1.3-8-3 3.6-3 8-3zm8 3v5c0 1.7-3.6 3-8 3s-8-1.3-8-3V7m16 5v5c0 1.7-3.6 3-8 3s-8-1.3-8-3v-5';
    $usersIcon = 'M9 11a3 3 0 100-6 3 3 0 000 6zm6 0a3 3 0 100-6 3 3 0 000 6zM9 13c-2.7 0-6 1.3-6 4v2h12v-2c0-2.7-3.3-4-6-4zm6 0c-.4 0-.8 0-1.2.1 1.2.9 2.2 2.2 2.2 3.9v2h5v-2c0-2.7-3.3-4-6-4z';

    $penalties = [
        ['label' => 'Before 180 days', 'value' => '50%', 'box' => 'border-rose-500/60 bg-rose-500/10', 'text' => 'text-rose-400', 'icon' => 'border-rose-500/50 bg-rose-500/10 text-rose-300'],
        ['label' => 'Before 360 days', 'value' => '25%', 'box' => 'border-nx-gold/60 bg-nx-gold/5', 'text' => 'text-nx-gold', 'icon' => 'border-nx-gold/50 bg-nx-gold/10 text-nx-gold'],
        ['label' => 'Before 540 days', 'value' => '10%', 'box' => 'border-nx-gold/60 bg-nx-gold/5', 'text' => 'text-nx-gold', 'icon' => 'border-nx-gold/50 bg-nx-gold/10 text-nx-gold'],
        ['label' => '540 days & after', 'value' => 'No penalty', 'box' => 'border-emerald-400/50 bg-emerald-400/5', 'text' => 'text-emerald-400', 'icon' => 'border-emerald-400/50 bg-emerald-400/10 text-emerald-300'],
    ];
@endphp

@section('content')
<style>
    .psl-hero { display: flex; flex-wrap: wrap; align-items: center; gap: 1.25rem; }
    .psl-hero-body { flex: 1 1 22rem; min-width: 0; }
    .psl-hero-art { display: none; }
    .psl-sold { display: flex; flex-wrap: wrap; align-items: center; gap: 1.5rem; }
    .psl-sold-bar { flex: 1 1 14rem; min-width: 0; }
    @media (min-width: 1280px) {
        .psl-hero-art { display: flex; align-items: center; gap: 1rem; flex-shrink: 0; }
    }
</style>

<div class="space-y-5 text-slate-200">

    @if (session('status'))
        <div class="rounded-xl border border-emerald-400/40 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-300">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-rose-500/50 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">{{ $errors->first() }}</div>
    @endif

    {{-- Hero --}}
    <section class="nx-panel nx-panel-gold overflow-hidden">
        <div class="nx-stars pointer-events-none absolute inset-0"></div>
        <div class="nx-dots pointer-events-none absolute inset-x-0 bottom-0 h-2/3"></div>
        <div class="pointer-events-none absolute right-40 top-1/2 h-72 w-72 -translate-y-1/2 rounded-full bg-nx-gold/15 blur-3xl"></div>

        <div class="psl-hero relative p-5 sm:px-6">
            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl sm:h-24 sm:w-24 border border-nx-gold/60 bg-nx-gold/10 text-nx-gold shadow-[0_0_20px_-8px] shadow-nx-gold">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 sm:h-14 sm:w-14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $coinsIcon }}"/></svg>
            </span>

            <div class="psl-hero-body">
                <div class="flex flex-wrap items-baseline gap-x-3">
                    <span class="nx-gold-text text-3xl font-bold break-words sm:text-5xl">${{ number_format($slotAmount) }}</span>
                    <span class="text-xl font-semibold text-white sm:text-2xl">Profit-Sharing Slot</span>
                </div>
                <p class="mt-2 max-w-2xl text-sm text-slate-200">
                    A separate module from your NFT purchases. <span class="text-emerald-400">5%</span> sharing on the Nexgrow utility project,
                    staked up to <span class="text-emerald-400">10X</span> or <span class="text-emerald-400">750 days</span>.
                </p>
                <div class="mt-3 flex max-w-2xl items-start gap-3 rounded-lg border border-nx-gold/30 bg-black/30 px-4 py-2.5 text-xs text-slate-200">
                    <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-nx-gold text-[10px] font-bold text-black">i</span>
                    <p>One slot per account. The 5% profit-sharing rate is still being finalized with the client, so slots don't accrue or credit any profit yet &mdash; only the purchase and locked amount are tracked.</p>
                </div>
            </div>

            <div class="psl-hero-art">
                <div class="rounded-xl border border-white/15 bg-black/30 px-4 py-3 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-7 w-7 text-emerald-400" viewBox="0 0 24 24" fill="currentColor"><path d="M4 20V11h4v9H4zm6 0V4h4v16h-4zm6 0v-6h4v6h-4z"/></svg>
                    <p class="mt-1 text-sm font-bold text-white">5%</p>
                    <p class="text-xs text-slate-400">Profit Sharing</p>
                </div>

                <div class="nx-coin-scene h-36 w-36 shrink-0">
                    <div class="nx-coin-flip">
                        <span class="nx-coin nx-coin-face"><img src="{{ asset('coin_nexadon.png') }}" alt="Nexadon coin" class="h-full w-full scale-[1.06] object-cover"></span>
                        <span class="nx-coin nx-coin-face nx-coin-back" aria-hidden="true"><img src="{{ asset('coin_nexadon.png') }}" alt="" class="h-full w-full scale-[1.06] object-cover"></span>
                    </div>
                </div>

                <div class="rounded-xl border border-white/15 bg-black/30 px-4 py-3 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-7 w-7 text-nx-gold" viewBox="0 0 24 24" fill="currentColor"><path d="M17 9V7a5 5 0 00-10 0v2H5v12h14V9h-2zM9 7a3 3 0 016 0v2H9V7z"/></svg>
                    <p class="mt-1 text-sm font-bold text-white">Up to 10X</p>
                    <p class="text-xs text-slate-400">or 750 Days</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Slots sold --}}
    <section class="nx-panel">
        <div class="psl-sold p-5 sm:px-6">
            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border border-nx-gold/60 bg-nx-gold/10 text-nx-gold">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-9 w-9" viewBox="0 0 24 24" fill="currentColor"><path d="{{ $usersIcon }}"/></svg>
            </span>

            <div class="shrink-0">
                <p class="text-sm text-slate-300">Slots Sold</p>
                <p class="mt-1 text-4xl font-bold text-nx-gold">{{ $sold }} <span class="text-2xl font-normal text-slate-400">/ {{ $totalSlots }}</span></p>
                <p class="mt-1 text-xs text-slate-300">{{ $remaining }} remaining</p>
            </div>

            <div class="psl-sold-bar" style="display:flex;align-items:center;gap:1rem;">
                <div style="flex:1;height:12px;border-radius:9999px;background:rgba(255,255,255,.12);overflow:hidden;">
                    <div style="height:100%;width:{{ max($soldPct, $sold > 0 ? 1.5 : 0) }}%;border-radius:9999px;background:linear-gradient(90deg,#f2c14e,#22c55e);"></div>
                </div>
                <span style="color:#34d399;font-size:.875rem;font-weight:600;">{{ $nxd($soldPct, 0) }}%</span>
            </div>

            <form method="POST" action="{{ route('profit-sharing-slot.purchase') }}">
                @csrf
                @if ($alreadyOwnsSlot)
                    <button type="button" disabled
                        style="display:inline-flex;align-items:center;justify-content:center;gap:.75rem;padding:.75rem 2rem;border-radius:.75rem;border:1px solid rgba(52,211,153,.6);background:rgba(52,211,153,.12);color:#6ee7b7;font-weight:600;font-size:1rem;cursor:default;white-space:nowrap;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $check ?? 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' }}"/></svg>
                        Already Purchased
                    </button>
                @elseif ($remaining > 0)
                    <button type="submit"
                        style="display:inline-flex;align-items:center;justify-content:center;gap:.75rem;padding:.75rem 2rem;border-radius:.75rem;border:1px solid #f2c14e;background:linear-gradient(180deg,#f2c14e,#b8862b);color:#000;font-weight:600;font-size:1rem;white-space:nowrap;box-shadow:0 0 24px -8px #f2c14e;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3a1 1 0 00.7 1.7H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        Purchase Slot (${{ number_format($slotAmount) }})
                    </button>
                @else
                    <button type="button" disabled
                        style="display:inline-flex;align-items:center;justify-content:center;padding:.75rem 2rem;border-radius:.75rem;border:1px solid rgba(251,113,133,.6);background:rgba(251,113,133,.12);color:#fda4af;font-weight:600;font-size:1rem;cursor:default;">
                        Sold Out
                    </button>
                @endif
            </form>
        </div>
    </section>

    {{-- Early withdrawal schedule --}}
    <section class="nx-panel nx-panel-gold p-5">
        <div class="flex items-center gap-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-9 w-9 shrink-0 text-nx-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <h3 class="text-lg font-semibold text-white">Early Withdrawal Schedule</h3>
                <p class="text-xs text-slate-400">If you withdraw before the lock-in period, a penalty will be applied as per the below schedule.</p>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($penalties as $p)
                <div class="flex items-center gap-4 rounded-xl border p-4 {{ $p['box'] }}">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border {{ $p['icon'] }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $calendar }}"/></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm text-slate-200">{{ $p['label'] }}</p>
                        <p class="text-3xl font-bold {{ $p['text'] }}">{{ $p['value'] }}</p>
                        @if ($p['value'] !== 'No penalty')
                            <p class="text-xs text-slate-400">Penalty</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4 flex items-center gap-3 rounded-lg border border-emerald-400/25 bg-emerald-400/5 px-4 py-2.5 text-sm text-slate-200">
            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-400 text-xs font-bold text-black">i</span>
            Deductions apply to the total payout (locked amount + any accrued profit).
        </div>
    </section>

    {{-- Your slots --}}
    <section class="nx-panel nx-panel-gold overflow-hidden">
        <div class="flex items-center gap-4 px-6 pt-5 pb-3">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-nx-gold/60 bg-nx-gold/10 text-nx-gold">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $coinsIcon }}"/></svg>
            </span>
            <div>
                <h3 class="text-lg font-semibold text-white">Your Slots</h3>
                @if ($slots->isEmpty())
                    <p class="text-sm text-slate-400">You don't own a Profit-Sharing Slot yet.</p>
                @endif
            </div>
        </div>

        @if ($slots->isEmpty())
            <div class="flex flex-col items-center gap-2 px-6 pb-8 text-center">
                <p class="font-medium text-white">No slots purchased yet</p>
                <p class="text-sm text-slate-400">Click the button above to purchase your Profit-Sharing Slot.</p>
            </div>
        @else
            <div class="overflow-x-auto px-3 pb-3">
                <table class="w-full min-w-225 text-left text-sm">
                    <thead>
                        <tr class="bg-emerald-950/60 text-slate-200">
                            <th class="px-4 py-3 font-medium">Amount</th>
                            <th class="px-4 py-3 font-medium">Purchased</th>
                            <th class="px-4 py-3 font-medium">Matures</th>
                            <th class="px-4 py-3 font-medium">Days Held</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Payout</th>
                            <th class="px-4 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach ($slots as $slot)
                            <tr class="text-slate-200 transition hover:bg-white/3">
                                <td class="px-4 py-3 font-semibold text-white">{{ $nxd($slot->amount) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $slot->purchased_at->format('d M Y') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $slot->maturityDate()->format('d M Y') }}</td>
                                <td class="px-4 py-3">{{ $slot->days_held }}</td>
                                <td class="px-4 py-3">
                                    @if ($slot->status === 'withdrawn')
                                        <span class="rounded-full border border-slate-400/40 bg-white/5 px-2.5 py-0.5 text-xs font-medium text-slate-300">Withdrawn</span>
                                    @elseif ($slot->matured)
                                        <span class="rounded-full border border-emerald-400/50 bg-emerald-400/10 px-2.5 py-0.5 text-xs font-medium text-emerald-300">Matured</span>
                                    @else
                                        <span class="rounded-full border border-nx-gold/60 bg-nx-gold/10 px-2.5 py-0.5 text-xs font-medium text-nx-gold">Active</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($slot->status === 'active')
                                        <span class="font-semibold text-emerald-400">{{ $nxd($slot->projected['payout']) }}</span>
                                        <span class="text-xs text-slate-400">({{ $nxd($slot->projected['penalty_percent'], 0) }}% penalty)</span>
                                    @else
                                        <span class="font-semibold text-emerald-400">{{ $nxd($slot->withdrawal_amount) }}</span>
                                        <span class="text-xs text-slate-400">({{ $nxd($slot->penalty_percent, 0) }}% penalty)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($slot->status === 'active')
                                        <form method="POST" action="{{ route('profit-sharing-slot.withdraw', $slot) }}"
                                            onsubmit="return confirm('Withdraw now for {{ number_format($slot->projected['payout'], 2) }} ({{ number_format($slot->projected['penalty_percent'], 0) }}% penalty)?')">
                                            @csrf
                                            <button type="submit" class="rounded-lg border border-nx-gold/60 px-4 py-1.5 text-xs font-medium text-nx-gold transition hover:bg-nx-gold/15">
                                                Withdraw
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection