{{-- Credit ledger table. Expects $transactions (paginated), $title, $description
     and $showStream (whether to show the stream column). --}}
@php
    $nxd = fn ($val) => number_format((float) $val, 2);
@endphp
<div class="nx-panel overflow-hidden">
    <div class="border-b border-white/10 px-6 py-4">
        <h3 class="font-bold text-white">{{ $title }}</h3>
        <p class="text-xs text-slate-400">{{ $description }}</p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-220 text-left text-sm">
            <thead>
                <tr class="border-b border-white/10 text-xs font-semibold text-slate-400">
                    <th class="px-6 py-4">Date &amp; Time</th>
                    <th class="px-6 py-4">Recipient</th>
                    @if ($showStream)
                        <th class="px-6 py-4">Stream</th>
                    @endif
                    <th class="px-6 py-4">Credit Amount</th>
                    <th class="px-6 py-4">Balance After</th>
                    <th class="px-6 py-4">Audit Note</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse ($transactions as $tx)
                    <tr class="transition hover:bg-white/2">
                        <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-300">
                            {{ $tx->created_at->format('d M Y, h:i A') }}
                        </td>
                        <td class="px-6 py-4">
                            @if ($tx->user)
                                <a href="{{ route('admin.users.show', $tx->user) }}" class="font-semibold text-white hover:text-nx-gold">
                                    {{ $tx->user->name }}
                                </a>
                                <span class="block font-mono text-xs text-slate-400">{{ $tx->user->user_id }}</span>
                            @else
                                <span class="text-slate-500">User #{{ $tx->user_id }}</span>
                            @endif
                        </td>
                        @if ($showStream)
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="{{ route('admin.incomes.index', ['stream' => $tx->reason]) }}"
                                    class="inline-flex rounded-lg border border-emerald-400/30 bg-emerald-400/10 px-2.5 py-1 text-xs font-semibold text-emerald-300 hover:border-emerald-300">
                                    {{ \App\Http\Controllers\Admin\IncomeManagementController::STREAMS[$tx->reason]['title'] ?? Str::headline($tx->reason) }}
                                </a>
                            </td>
                        @endif
                        <td class="px-6 py-4 font-bold whitespace-nowrap text-emerald-400">
                            +{{ $nxd($tx->amount) }} <span class="text-xs font-normal text-slate-300">NXD</span>
                        </td>
                        <td class="px-6 py-4 font-medium whitespace-nowrap text-white">
                            {{ $nxd($tx->balance_after) }} <span class="text-xs font-normal text-slate-400">NXD</span>
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-400">
                            {{ $tx->note ?? 'System credit' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showStream ? 6 : 5 }}" class="px-6 py-12 text-center text-slate-400">
                            No credit transactions recorded yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($transactions->hasPages())
        <div class="border-t border-white/10 px-6 py-4">
            {{ $transactions->links() }}
        </div>
    @endif
</div>
