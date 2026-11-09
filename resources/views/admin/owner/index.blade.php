@extends('layouts.dashboard', ['title' => 'Owner Actions', 'dark' => true])

{{-- Widgets: resources/js/nexgrow/admin.js. Every action is signed by the contract owner's wallet. --}}
@php
    $btn = 'rounded-lg border px-3 py-1.5 text-xs font-semibold transition';
    $btnGold = $btn.' border-nx-gold/70 bg-amber-500/20 text-white hover:border-nx-gold';
    $btnPlain = $btn.' border-white/15 bg-white/5 text-slate-200 hover:text-white';
    $btnDanger = $btn.' border-rose-400/60 bg-rose-500/15 text-rose-200 hover:border-rose-400';
    $inputCls = 'w-full rounded-xl border border-white/15 bg-nx-panel px-4 py-2.5 text-sm text-white focus:border-nx-gold focus:outline-none';
    $message = '<p data-nx-field="message" class="mt-4 hidden text-sm"></p>';
    // One-time setup is folded away in <details> so the page shows the regular actions first.
    $setupBox = 'group mt-4 rounded-xl border border-white/10';
    $setupSummary = 'flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-slate-200 hover:text-white [&::-webkit-details-marker]:hidden';
    $setupChevron = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>';
@endphp

@section('content')
<div class="space-y-6 text-slate-200">

    @unless ($deployed)
        <div class="rounded-xl border border-amber-400/50 bg-amber-400/10 px-4 py-3 text-sm text-amber-200">
            No contracts are deployed on this network yet. Deploy with blockchain/scripts/deployNexgrow.ts first.
        </div>
    @endunless

    {{-- Owner wallet --}}
    <section class="nx-panel px-6 py-4" data-nx-chain="admin-owner">
        <p class="text-xs text-slate-400">
            Actions on this page are blockchain transactions signed by your browser wallet (MetaMask), which must be the contract owner:
            <span data-nx-field="owner" class="font-mono break-all text-nx-gold">—</span>
        </p>
    </section>

    {{-- Emergency pause --}}
    <section class="nx-panel overflow-hidden" data-nx-chain="admin-pause">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-6 py-4">
            <div>
                <h3 class="font-bold text-white">Emergency Pause</h3>
                <p class="text-xs text-slate-400">Stops a contract until you resume it. Members' funds and credits are kept.</p>
            </div>
            <button type="button" data-nx-action="pause-all" class="{{ $btnDanger }}">Pause everything</button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-white/10 text-xs font-semibold text-slate-400">
                        <th class="px-6 py-3">Contract</th>
                        <th class="px-6 py-3">What pausing stops</th>
                        <th class="px-6 py-3">State</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @foreach ($pausable as $key => [$label, $effect])
                        <tr data-nx-contract="{{ $key }}" data-nx-label="{{ $label }}">
                            <td class="px-6 py-3 font-semibold text-white">{{ $label }}</td>
                            <td class="px-6 py-3 text-xs text-slate-400">{{ $effect }}</td>
                            <td class="px-6 py-3 text-xs font-semibold" data-nx-item="state">—</td>
                            <td class="px-6 py-3 text-right">
                                <button type="button" data-nx-action="pause" class="hidden {{ $btnDanger }}">Pause</button>
                                <button type="button" data-nx-action="unpause" class="hidden {{ $btnPlain }}">Resume</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-6 pb-4">{!! $message !!}</div>
    </section>

    {{-- Payouts and rounds --}}
    <section class="nx-panel overflow-hidden" data-nx-chain="admin-payouts">
        <div class="border-b border-white/10 px-6 py-4">
            <h3 class="font-bold text-white">Income Payouts &amp; Payment Rounds</h3>
            <p class="text-xs text-slate-400">
                A payment round pays members from the module's funded budget in the Reward Pool. If the budget is lower than what is owed, everyone gets the same share and the rest carries forward.
                The keeper never starts rounds; only the owner does, here. "Turn on" needs the module's funding set up first; to stop a module, use Emergency Pause.
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-white/10 text-xs font-semibold text-slate-400">
                        <th class="px-6 py-3">Income</th>
                        <th class="px-6 py-3">Payouts</th>
                        <th class="px-6 py-3">Funding</th>
                        <th class="px-6 py-3 text-right">Budget (NXD)</th>
                        <th class="px-6 py-3 text-right">Owed (NXD)</th>
                        <th class="px-6 py-3 text-right">Rounds</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @foreach ($payoutModules as $key => $label)
                        <tr data-nx-contract="{{ $key }}" data-nx-label="{{ $label }}">
                            <td class="px-6 py-3 font-semibold text-white">{{ $label }}</td>
                            <td class="px-6 py-3 text-xs" data-nx-item="enabled">—</td>
                            <td class="px-6 py-3 text-xs" data-nx-item="funding">—</td>
                            <td class="px-6 py-3 text-right font-mono text-xs" data-nx-item="budget">—</td>
                            <td class="px-6 py-3 text-right font-mono text-xs" data-nx-item="unpaid">—</td>
                            <td class="px-6 py-3 text-right font-mono text-xs" data-nx-item="round">—</td>
                            <td class="space-x-1 whitespace-nowrap px-6 py-3 text-right">
                                <button type="button" data-nx-action="start-round" class="hidden {{ $btnGold }}">Start round</button>
                                <button type="button" data-nx-action="enable" class="hidden {{ $btnPlain }}">Turn on</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-6 pb-4">{!! $message !!}</div>
    </section>

    {{-- Treasury --}}
    <section class="nx-panel p-6" data-nx-chain="admin-treasury">
        <h3 class="font-bold text-white">Treasury</h3>
        <p class="mt-1 text-xs text-slate-400">
            All NFT sale payments collect here. To pay income, send NXD to a Reward Pool depositor wallet, then deposit it in Revenue &amp; Funding below.
        </p>

        <dl class="mt-4 grid grid-cols-2 gap-3 text-xs">
            <div class="rounded-xl border border-white/10 p-3"><dt class="text-slate-400">Balance (NXD)</dt><dd class="mt-1 text-lg font-bold text-white" data-nx-field="balance">—</dd></div>
            <div class="rounded-xl border border-white/10 p-3"><dt class="text-slate-400">Sent out so far (NXD)</dt><dd class="mt-1 text-lg font-bold text-white" data-nx-field="withdrawn">—</dd></div>
        </dl>

        <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-[1fr_2fr_auto]">
            <input type="text" inputmode="decimal" placeholder="Amount in NXD" data-nx-input="treasury-amount" class="{{ $inputCls }}">
            <input type="text" placeholder="Send to 0x…" data-nx-input="treasury-to" class="{{ $inputCls }}">
            <button type="button" data-nx-action="withdraw" class="{{ $btnGold }}">Send</button>
        </div>

        <div class="mt-5 overflow-x-auto">
            <p class="text-xs font-semibold text-slate-300">Last 10 transfers</p>
            <table class="mt-2 w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-white/10 text-slate-400">
                        <th class="py-2 pr-4">Date</th>
                        <th class="py-2 pr-4">To</th>
                        <th class="py-2 text-right">Amount (NXD)</th>
                    </tr>
                </thead>
                <tbody data-nx-field="withdrawals" class="divide-y divide-white/5 font-mono text-slate-300 [&_td:last-child]:text-right [&_td]:py-2 [&_td]:pr-4"></tbody>
            </table>
            <p data-nx-field="withdrawals-empty" class="mt-2 hidden text-xs text-slate-500">No transfers yet.</p>
        </div>
        {!! $message !!}
    </section>

    {{-- Reward Pool revenue and shares --}}
    <section class="nx-panel p-6" data-nx-chain="admin-funding">
        <h3 class="font-bold text-white">Revenue &amp; Funding (Reward Pool)</h3>
        <p class="mt-1 text-xs text-slate-400">
            Income is paid from the Reward Pool. Each deposit is made "as" a revenue source and split into the income budgets by the shares you set for that source.
            Shares apply to future deposits only. Any part that is not shared stays in the pool unassigned.
        </p>

        <dl class="mt-4 grid grid-cols-2 gap-3 text-xs lg:grid-cols-4">
            <div class="rounded-xl border border-white/10 p-3"><dt class="text-slate-400">Total deposited</dt><dd class="mt-1 text-lg font-bold text-white" data-nx-field="deposited">—</dd></div>
            <div class="rounded-xl border border-white/10 p-3"><dt class="text-slate-400">Total paid out</dt><dd class="mt-1 text-lg font-bold text-white" data-nx-field="released">—</dd></div>
            <div class="rounded-xl border border-white/10 p-3"><dt class="text-slate-400">In income budgets</dt><dd class="mt-1 text-lg font-bold text-emerald-300" data-nx-field="budgeted">—</dd></div>
            <div class="rounded-xl border border-white/10 p-3"><dt class="text-slate-400">Unassigned</dt><dd class="mt-1 text-lg font-bold text-amber-300" data-nx-field="unassigned">—</dd></div>
        </dl>

        <p data-nx-field="no-sources" class="mt-4 hidden text-xs text-amber-300">No revenue source yet. Open "Setup: revenue sources" below and add one (for example NFT_SALES).</p>

        <details class="{{ $setupBox }}">
            <summary class="{{ $setupSummary }}">Setup: revenue sources {!! $setupChevron !!}</summary>
            <div class="grid grid-cols-1 gap-2 px-4 pb-4 sm:grid-cols-[1fr_2fr_auto]">
                <input type="text" placeholder="Name, e.g. NFT_SALES" data-nx-input="source-name" class="{{ $inputCls }}">
                <input type="text" placeholder="What it is and where it is documented" data-nx-input="source-description" class="{{ $inputCls }}">
                <button type="button" data-nx-action="add-source" class="{{ $btnGold }}">Add source</button>
            </div>
        </details>

        <div data-nx-field="source-panel" class="hidden">
            <div class="mt-4">
                <label for="funding-source" class="block text-xs font-medium text-slate-300">Source to manage</label>
                <select id="funding-source" data-nx-input="source" class="mt-1 {{ $inputCls }}"></select>
                <p class="mt-1 break-all font-mono text-[11px] text-slate-500" data-nx-field="source-id"></p>
                <p class="mt-1 text-xs text-slate-400">
                    Deposited from this source: <span class="text-white" data-nx-field="source-deposited">—</span> NXD ·
                    Shared: <span class="text-nx-gold" data-nx-field="source-allocated">—</span> ·
                    Not shared: <span class="text-white" data-nx-field="source-free">—</span>
                </p>
            </div>

            <details class="{{ $setupBox }}">
                <summary class="{{ $setupSummary }}">Setup: who may deposit for this source {!! $setupChevron !!}</summary>
                <div class="px-4 pb-4">
                    <p class="break-all font-mono text-xs text-slate-300" data-nx-field="depositors">—</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <input type="text" placeholder="0x…" data-nx-input="pool-depositor" class="{{ $inputCls }} sm:flex-1">
                        <button type="button" data-nx-action="add-depositor" class="{{ $btnPlain }}">Add</button>
                        <button type="button" data-nx-action="remove-depositor" class="{{ $btnPlain }}">Remove</button>
                    </div>
                </div>
            </details>

            {{-- Shares --}}
            <div class="mt-4 overflow-x-auto rounded-xl border border-white/10">
                <p class="px-4 pt-4 text-sm font-semibold text-white">Share of this source for each income</p>
                <table class="mt-2 w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-white/10 text-xs font-semibold text-slate-400">
                            <th class="px-4 py-2">Income</th>
                            <th class="px-4 py-2 text-right">Share now</th>
                            <th class="px-4 py-2 text-right">Budget (NXD)</th>
                            <th class="px-4 py-2">New share %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach ($payoutModules as $key => $label)
                            <tr data-nx-contract="{{ $key }}" data-nx-label="{{ $label }}">
                                <td class="px-4 py-2 text-white">{{ $label }}</td>
                                <td class="px-4 py-2 text-right font-mono text-xs text-nx-gold" data-nx-item="share">—</td>
                                <td class="px-4 py-2 text-right font-mono text-xs" data-nx-item="budget">—</td>
                                <td class="px-4 py-2">
                                    <div class="flex gap-2">
                                        <input type="text" inputmode="decimal" placeholder="e.g. 40" data-nx-item="share-input"
                                            class="w-24 rounded-lg border border-white/15 bg-nx-panel px-3 py-1.5 text-xs text-white focus:border-nx-gold focus:outline-none">
                                        <button type="button" data-nx-action="save-share" class="{{ $btnPlain }}">Save</button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Deposit --}}
            <div class="mt-4 rounded-xl border border-white/10 p-4">
                <p class="text-sm font-semibold text-white">Deposit revenue for this source</p>
                <div class="mt-2 flex gap-2">
                    <input type="text" inputmode="decimal" placeholder="Amount in NXD" data-nx-input="pool-amount" class="{{ $inputCls }}">
                    <button type="button" data-nx-action="deposit" class="{{ $btnGold }}">Deposit</button>
                </div>
                <p class="mt-1 text-xs text-slate-500">The connected wallet must be a depositor for this source and hold the NXD.</p>
            </div>
        </div>
        {!! $message !!}
    </section>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        {{-- Profit-Sharing Slot --}}
        <section class="nx-panel p-6" data-nx-chain="admin-slot">
            <h3 class="font-bold text-white">Profit-Sharing Slot</h3>
            <p class="mt-1 text-xs text-slate-400">Deposited profit is shared equally across slots that are still earning, up to each slot's 10X cap.</p>

            <dl class="mt-4 grid grid-cols-2 gap-3 text-xs sm:grid-cols-3">
                <div class="rounded-xl border border-white/10 p-3"><dt class="text-slate-400">Slots sold</dt><dd class="mt-1 text-lg font-bold text-white" data-nx-field="sold">—</dd></div>
                <div class="rounded-xl border border-white/10 p-3"><dt class="text-slate-400">Slots left</dt><dd class="mt-1 text-lg font-bold text-white" data-nx-field="remaining">—</dd></div>
                <div class="rounded-xl border border-white/10 p-3"><dt class="text-slate-400">Principal held</dt><dd class="mt-1 text-lg font-bold text-white" data-nx-field="principal">—</dd></div>
                <div class="rounded-xl border border-white/10 p-3"><dt class="text-slate-400">Profit given to slots</dt><dd class="mt-1 text-lg font-bold text-emerald-300" data-nx-field="assigned">—</dd></div>
                <div class="rounded-xl border border-white/10 p-3"><dt class="text-slate-400">Profit not given yet</dt><dd class="mt-1 text-lg font-bold text-amber-300" data-nx-field="undistributed">—</dd></div>
            </dl>

            <div class="mt-5">
                <label for="slot-amount" class="block text-xs font-medium text-slate-300">Deposit profit (NXD)</label>
                <div class="mt-1 flex gap-2">
                    <input id="slot-amount" type="text" inputmode="decimal" placeholder="e.g. 1500" data-nx-input="amount" class="{{ $inputCls }}">
                    <button type="button" data-nx-action="deposit" class="{{ $btnGold }}">Deposit</button>
                </div>
                <p class="mt-1 text-xs text-slate-500">The connected wallet must be a profit depositor and hold the NXD.</p>
            </div>

            <details class="{{ $setupBox }}">
                <summary class="{{ $setupSummary }}">Setup: profit depositors &amp; recovery {!! $setupChevron !!}</summary>
                <div class="space-y-5 px-4 pb-4">
                    <div>
                        <label for="slot-depositor" class="block text-xs font-medium text-slate-300">Profit depositors</label>
                        <p class="mt-1 break-all font-mono text-xs text-slate-300" data-nx-field="depositors">—</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <input id="slot-depositor" type="text" placeholder="0x…" data-nx-input="depositor" class="{{ $inputCls }} sm:flex-1">
                            <button type="button" data-nx-action="add-depositor" class="{{ $btnPlain }}">Add</button>
                            <button type="button" data-nx-action="remove-depositor" class="{{ $btnPlain }}">Remove</button>
                        </div>
                    </div>

                    <div>
                        <label for="slot-recover" class="block text-xs font-medium text-slate-300">Recover profit not given yet</label>
                        <div class="mt-1 flex gap-2">
                            <input id="slot-recover" type="text" placeholder="Send to 0x…" data-nx-input="recover-to" class="{{ $inputCls }}">
                            <button type="button" data-nx-action="recover" class="{{ $btnPlain }}">Recover</button>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Only profit no slot could take (e.g. sent while no slot was earning). Principal and slot profit cannot be touched.</p>
                    </div>
                </div>
            </details>
            {!! $message !!}
        </section>

        {{-- Team Development settings --}}
        <section class="nx-panel self-start p-6" data-nx-chain="admin-development">
            <details class="group">
            <summary class="flex cursor-pointer list-none items-start justify-between gap-3 [&::-webkit-details-marker]:hidden">
                <span>
                    <h3 class="font-bold text-white">Setup: Team Development Bonus</h3>
                    <span class="mt-1 block text-xs text-slate-400">Extra %, window and which bonuses get the extra. Changes apply from now on; bonuses already credited are not changed.</span>
                </span>
                {!! $setupChevron !!}
            </summary>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="dev-extra" class="block text-xs font-medium text-slate-300">Extra % (now <span data-nx-field="extra" class="text-nx-gold">—</span>)</label>
                    <div class="mt-1 flex gap-2">
                        <input id="dev-extra" type="text" inputmode="decimal" placeholder="5" data-nx-input="extra-percent" class="{{ $inputCls }}">
                        <button type="button" data-nx-action="save-extra" class="{{ $btnGold }}">Save</button>
                    </div>
                </div>
                <div>
                    <label for="dev-window" class="block text-xs font-medium text-slate-300">Window (now <span data-nx-field="window" class="text-nx-gold">—</span>)</label>
                    <div class="mt-1 flex gap-2">
                        <input id="dev-window" type="number" min="1" max="120" placeholder="12" data-nx-input="window-months" class="{{ $inputCls }}">
                        <button type="button" data-nx-action="save-window" class="{{ $btnGold }}">Save</button>
                    </div>
                </div>
            </div>

            <table class="mt-5 w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-white/10 text-xs font-semibold text-slate-400">
                        <th class="py-2">Bonus</th>
                        <th class="py-2">Extra</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @foreach ($developmentSources as $key => $label)
                        <tr data-nx-contract="{{ $key }}" data-nx-label="{{ $label }}">
                            <td class="py-2 text-white">{{ $label }}</td>
                            <td class="py-2 text-xs" data-nx-item="eligible">—</td>
                            <td class="py-2 text-right">
                                <button type="button" data-nx-action="make-eligible" class="hidden {{ $btnPlain }}">Give extra</button>
                                <button type="button" data-nx-action="remove-eligible" class="hidden {{ $btnPlain }}">Remove extra</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </details>
            {!! $message !!}
        </section>
    </div>
</div>
@endsection
