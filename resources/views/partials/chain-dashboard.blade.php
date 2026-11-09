{{-- Chain mode dashboard: on-chain income across every module for the
     linked wallet. Widget "income-summary" in resources/js/nexgrow/income.js. --}}
@php
    $rows = ['nftIncome' => ['label' => 'Daily NFT Bonus', 'route' => 'nft.index']];

    foreach (\App\Support\Chain::INCOME as $route => $income) {
        $rows[$income['contract']] = ['label' => $income['title'], 'route' => $route];
    }
@endphp

@if (! $user->wallet_address)
    @include('partials.chain-wallet')
@else
    <section class="nx-panel overflow-hidden" data-nx-chain="income-summary">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-6 py-4">
            <h3 class="text-lg font-semibold text-white">On-chain Income</h3>
            <p class="text-sm text-slate-400">Wallet balance <span class="font-semibold text-white" data-nx-field="balance">…</span> NXD</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-160 text-left text-sm">
                <thead>
                    <tr class="border-b border-white/10 text-slate-200">
                        <th class="px-6 py-3 font-semibold">Income</th>
                        <th class="px-6 py-3 font-semibold">Earned</th>
                        <th class="px-6 py-3 font-semibold">Paid to wallet</th>
                        <th class="px-6 py-3 font-semibold">Pending</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @foreach ($rows as $contract => $row)
                        <tr data-nx-row="{{ $contract }}">
                            <td class="px-6 py-3"><a href="{{ route($row['route']) }}" class="text-white hover:text-nx-gold">{{ $row['label'] }}</a></td>
                            <td class="px-6 py-3 text-slate-300" data-nx-cell="earned">…</td>
                            <td class="px-6 py-3 text-slate-300" data-nx-cell="paid">…</td>
                            <td class="px-6 py-3 font-semibold text-emerald-400" data-nx-cell="pending">…</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-white/10 font-semibold text-white">
                        <td class="px-6 py-3">Total</td>
                        <td class="px-6 py-3" data-nx-field="earned">…</td>
                        <td class="px-6 py-3" data-nx-field="paid">…</td>
                        <td class="px-6 py-3 text-emerald-400" data-nx-field="pending">…</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p data-nx-field="message" class="hidden px-6 pb-4 text-sm"></p>
    </section>
@endif
