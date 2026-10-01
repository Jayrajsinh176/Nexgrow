@extends('layouts.dashboard', ['title' => 'Token Wallet', 'dark' => true])

@php
    $nxd = fn ($value) => number_format((float) $value, 2);
    $reasonLabel = fn (string $reason) => str($reason)->replace('_', ' ')->ucfirst()->toString();

    $stats = [
        [
            'label' => 'Total Credits', 'value' => $nxd($totalCredits), 'sub' => 'NXD', 'valueClass' => 'text-emerald-400',
            'href' => route('wallet', ['filter' => 'credit']).'#history',
            'tile' => 'border-emerald-400/50 bg-emerald-400/10 text-emerald-300',
            'icon' => 'M7 17L17 7m0 0H9m8 0v8',
        ],
        [
            'label' => 'Total Debits', 'value' => $nxd($totalDebits), 'sub' => 'NXD', 'valueClass' => 'text-rose-400',
            'href' => route('wallet', ['filter' => 'debit']).'#history',
            'tile' => 'border-rose-400/50 bg-rose-500/15 text-rose-300',
            'icon' => 'M12 4v12m0 0l-5-5m5 5l5-5M5 20h14',
        ],
        [
            'label' => 'This Month Earned', 'value' => $nxd($thisMonthEarned), 'sub' => 'NXD', 'valueClass' => 'text-white',
            'href' => null,
            'tile' => 'border-nx-gold/60 bg-nx-gold/10 text-nx-gold',
            'icon' => 'M12 4c4.4 0 8 1.3 8 3s-3.6 3-8 3-8-1.3-8-3 3.6-3 8-3zm8 3v5c0 1.7-3.6 3-8 3s-8-1.3-8-3V7m16 5v5c0 1.7-3.6 3-8 3s-8-1.3-8-3v-5',
        ],
        [
            'label' => 'Last Transaction',
            'value' => $lastTransaction?->created_at->format('d M Y') ?? '—',
            'sub' => $lastTransaction?->created_at->format('h:i A') ?? 'No transactions yet', 'valueClass' => 'text-white',
            'href' => null,
            'tile' => 'border-nx-gold/60 bg-nx-gold/10 text-nx-gold',
            'icon' => 'M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1zm4 10l2 2 4-4',
        ],
    ];
@endphp

@section('content')
<div class="space-y-5 text-slate-200">

    {{-- Balance / Quick actions --}}
    <section class="grid grid-cols-1 gap-4 xl:grid-cols-[1.65fr_1fr]">
        <div class="nx-panel nx-panel-gold overflow-hidden">
            <div class="nx-stars pointer-events-none absolute inset-0"></div>
            <div class="nx-dots pointer-events-none absolute inset-x-0 bottom-0 h-2/3"></div>
            <div class="pointer-events-none absolute -right-10 top-1/2 h-72 w-72 -translate-y-1/2 rounded-full bg-nx-gold/15 blur-3xl"></div>

            <div class="relative flex items-center justify-between gap-6 p-6 sm:p-8">
                <div class="min-w-0">
                    <p class="flex items-center gap-3 text-lg text-slate-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-nx-gold" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.26L22 9.27l-5.2 4.73L18.18 21 12 17.27 5.82 21l1.38-7L2 9.27l7.1-1.01L12 2z" /></svg>
                        Nexadon Token Balance
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-4">
                        <p class="nx-gold-text text-3xl font-bold break-words sm:text-5xl">{{ $nxd($user->token_balance) }}</p>
                        <span class="inline-flex items-center gap-2 rounded-full border border-nx-gold/60 bg-nx-gold/10 px-3 py-1 text-sm font-semibold text-nx-gold">
                            <span class="nx-coin block h-5 w-5"><img src="{{ asset('coin_nexadon.png') }}" alt="" class="h-full w-full scale-[1.06] object-cover"></span>
                            NXD
                        </span>
                    </div>
                    <p class="mt-3 text-sm text-slate-400">Available to spend on NFT packages and Profit-Sharing Slots</p>
                </div>

                <div class="nx-coin-scene hidden h-36 w-36 shrink-0 sm:block">
                    <div class="nx-coin-flip">
                        <span class="nx-coin nx-coin-face"><img src="{{ asset('coin_nexadon.png') }}" alt="Nexadon coin" class="h-full w-full scale-[1.06] object-cover"></span>
                        <span class="nx-coin nx-coin-face nx-coin-back" aria-hidden="true"><img src="{{ asset('coin_nexadon.png') }}" alt="" class="h-full w-full scale-[1.06] object-cover"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="nx-panel p-5">
            <h3 class="flex items-center gap-3 text-lg font-semibold text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-nx-gold" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z" /></svg>
                Quick Actions
            </h3>

            <div class="grid grid-cols-2 gap-3 pt-4 sm:grid-cols-4">
                <a href="{{ route('nft.index') }}"
                    class="flex flex-col items-center justify-center gap-2 rounded-xl border border-nx-gold/60 bg-gradient-to-b from-amber-600/40 to-amber-950/60 px-2 py-4 text-center text-sm text-white transition hover:border-nx-gold hover:shadow-[0_0_20px_-6px] hover:shadow-nx-gold">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-nx-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3c-.6.6-.2 1.7.7 1.7H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                    Buy NFT
                </a>

                <div title="Withdrawals are coming soon"
                    class="relative flex cursor-not-allowed flex-col items-center justify-center gap-2 rounded-xl border border-emerald-400/40 bg-gradient-to-b from-emerald-700/30 to-emerald-950/50 px-2 py-4 text-center text-sm text-white/70">
                    <span class="absolute top-1.5 right-1.5 rounded bg-white/10 px-1.5 py-0.5 text-[10px] text-slate-300">Soon</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-emerald-300/80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20V4m0 0l-6 6m6-6l6 6" /></svg>
                    Withdraw
                </div>

                <a href="#history"
                    class="flex flex-col items-center justify-center gap-2 rounded-xl border border-sky-400/50 bg-gradient-to-b from-sky-700/40 to-sky-950/60 px-2 py-4 text-center text-sm text-white transition hover:border-sky-300 hover:shadow-[0_0_20px_-6px] hover:shadow-sky-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-sky-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    History
                </a>

                <a href="{{ route('profit-sharing-slot.index') }}"
                    class="flex flex-col items-center justify-center gap-2 rounded-xl border border-violet-400/50 bg-gradient-to-b from-violet-700/40 to-violet-950/60 px-2 py-4 text-center text-sm text-white transition hover:border-violet-300 hover:shadow-[0_0_20px_-6px] hover:shadow-violet-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-violet-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 3.05A9 9 0 1020.95 13H11V3.05zM14 3.5V10h6.5A9 9 0 0014 3.5z" /></svg>
                    Profit Slot
                </a>
            </div>
        </div>
    </section>

    {{-- Stat cards --}}
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            @php $tag = $stat['href'] ? 'a' : 'div'; @endphp
            <{{ $tag }} @if ($stat['href']) href="{{ $stat['href'] }}" @endif class="nx-panel group flex items-center gap-4 p-5 {{ $stat['href'] ? 'transition hover:-translate-y-0.5 hover:border-nx-gold/60' : '' }}">
                <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border {{ $stat['tile'] }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $stat['icon'] }}" />
                    </svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-slate-300">{{ $stat['label'] }}</p>
                    <p class="mt-1 truncate text-2xl font-bold {{ $stat['valueClass'] }}">{{ $stat['value'] }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $stat['sub'] }}</p>
                </div>
                @if ($stat['href'])
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center self-start rounded-full border border-white/15 text-slate-300 transition group-hover:border-nx-gold/60 group-hover:text-nx-gold">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                    </span>
                @endif
            </{{ $tag }}>
        @endforeach
    </section>

    {{-- Transaction history --}}
    <section id="history" class="nx-panel scroll-mt-28 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-6 py-4">
            <h3 class="flex items-center gap-3 text-lg font-semibold text-white">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg border border-nx-gold/50 bg-nx-gold/10 text-nx-gold">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" /></svg>
                </span>
                Transaction History
            </h3>

            <form method="GET" action="{{ route('wallet') }}#history" class="relative">
                <label for="transaction-filter" class="sr-only">Filter transactions</label>
                <select id="transaction-filter" name="filter" onchange="this.form.submit()"
                    class="min-w-48 cursor-pointer appearance-none rounded-xl border border-emerald-400/40 bg-nx-panel py-2.5 pr-10 pl-4 text-sm text-white scheme-dark hover:border-nx-gold/60 focus:border-nx-gold/70 focus:outline-none [&_option]:bg-nx-panel [&_option]:text-white">
                    <option value="">All Transactions</option>
                    <option value="credit" @selected($filter === 'credit')>Credits only</option>
                    <option value="debit" @selected($filter === 'debit')>Debits only</option>
                    @if ($reasons->isNotEmpty())
                        <optgroup label="By reason">
                            @foreach ($reasons as $reason)
                                <option value="{{ $reason }}" @selected($filter === $reason)>{{ $reasonLabel($reason) }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                </select>
                <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute top-1/2 right-3.5 h-4 w-4 -translate-y-1/2 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
                <noscript><button type="submit" class="ml-2 text-sm text-nx-gold">Apply</button></noscript>
            </form>
        </div>

        @if ($transactions->isEmpty())
            <p class="px-6 py-12 text-center text-sm text-slate-400">
                {{ $filter ? 'No transactions match this filter.' : 'No transactions yet.' }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-225 text-left text-sm">
                    <thead>
                        <tr class="border-b border-white/10 text-slate-200">
                            <th class="px-6 py-4 font-semibold">Date &amp; Time</th>
                            <th class="px-4 py-4 font-semibold">Type</th>
                            <th class="px-4 py-4 font-semibold">Amount (NXD)</th>
                            <th class="px-4 py-4 font-semibold">Balance (NXD)</th>
                            <th class="px-4 py-4 font-semibold">Reason</th>
                            <th class="px-6 py-4 font-semibold">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach ($transactions as $transaction)
                            @php $isCredit = $transaction->type === 'credit'; @endphp
                            <tr class="transition hover:bg-white/3">
                                <td class="px-6 py-3 whitespace-nowrap text-slate-300">{{ $transaction->created_at->format('d M Y, h:i A') }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold ring-1 {{ $isCredit ? 'bg-emerald-600/40 text-emerald-200 ring-emerald-400/40' : 'bg-rose-600/30 text-rose-200 ring-rose-400/40' }}">
                                        {{ ucfirst($transaction->type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-semibold whitespace-nowrap {{ $isCredit ? 'text-emerald-400' : 'text-rose-400' }}">
                                    {{ $isCredit ? '+' : '-' }}{{ $nxd($transaction->amount) }}
                                </td>
                                <td class="px-4 py-3 text-slate-200">{{ $nxd($transaction->balance_after) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-slate-200">{{ $reasonLabel($transaction->reason) }}</td>
                                <td class="px-6 py-3 text-slate-400">{{ $transaction->note }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 px-6 py-4">
                <p class="text-sm text-slate-400">
                    Showing <span class="text-white">{{ $transactions->firstItem() }}</span>–<span class="text-white">{{ $transactions->lastItem() }}</span>
                    of <span class="text-white">{{ $transactions->total() }}</span> transactions
                </p>
                {{ $transactions->fragment('history')->links('pagination.nx') }}
            </div>
        @endif
    </section>
</div>
@endsection
