@extends('layouts.dashboard', ['title' => 'My Team', 'dark' => true])

@php
    $stats = [
        [
            'label' => 'Total Team Size', 'value' => $totalTeamCount, 'hint' => 'All levels in your downline',
            'tile' => 'border-nx-gold/60 bg-nx-gold/10 text-nx-gold', 'panel' => 'nx-panel-gold',
            'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4',
        ],
        [
            'label' => 'Direct Referrals', 'value' => $directReferralsCount, 'hint' => 'Level 1 members',
            'tile' => 'border-emerald-400/50 bg-emerald-400/10 text-emerald-300', 'panel' => '',
            'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4',
        ],
        [
            'label' => 'Active Members', 'value' => $activeMembersCount, 'hint' => 'With an active NFT package',
            'tile' => 'border-violet-400/50 bg-violet-500/15 text-violet-300', 'panel' => '',
            'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4',
        ],
        [
            'label' => 'New This Month', 'value' => $newThisMonthCount, 'hint' => 'Joined in '.now()->format('F Y'),
            'tile' => 'border-nx-gold/60 bg-nx-gold/10 text-nx-gold', 'panel' => '',
            'icon' => 'M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1zm4 10l2 2 4-4',
        ],
    ];
@endphp

@section('content')
<div class="space-y-5 text-slate-200">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-slate-300">Everyone in your downline, across all referral levels.</p>

        <form method="GET" action="{{ route('team') }}" class="relative">
            <label for="level-filter" class="sr-only">Filter by level</label>
            {{-- color-scheme + option colours keep the native popup dark; otherwise it renders white text on white. --}}
            <select id="level-filter" name="level" onchange="this.form.submit()"
                class="min-w-48 cursor-pointer appearance-none rounded-xl border border-emerald-400/40 bg-nx-panel py-2.5 pr-10 pl-4 text-sm text-white scheme-dark hover:border-nx-gold/60 focus:border-nx-gold/70 focus:outline-none [&>option]:bg-nx-panel [&>option]:text-white">
                <option value="">All Generations</option>
                @foreach ($levels as $option)
                    <option value="{{ $option }}" @selected($level === $option)>Level {{ $option }}</option>
                @endforeach
            </select>
            <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute top-1/2 right-3.5 h-4 w-4 -translate-y-1/2 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
            <noscript><button type="submit" class="ml-2 text-sm text-nx-gold">Apply</button></noscript>
        </form>
    </div>

    {{-- Stat cards --}}
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="nx-panel {{ $stat['panel'] }} flex items-center gap-4 p-5">
                <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border {{ $stat['tile'] }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $stat['icon'] }}" />
                    </svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-slate-300">{{ $stat['label'] }}</p>
                    <p class="mt-1 truncate text-2xl font-bold text-white">{{ number_format($stat['value']) }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $stat['hint'] }}</p>
                </div>
            </div>
        @endforeach
    </section>

    {{-- Members --}}
    <section class="nx-panel overflow-hidden">
        @if ($members->isEmpty())
            <p class="px-6 py-12 text-center text-sm text-slate-400">
                @if ($level)
                    No team members at Level {{ $level }}.
                @else
                    No team members yet. Share your referral link from the dashboard to start building your team.
                @endif
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-225 text-left text-sm">
                    <thead>
                        <tr class="border-b border-white/10 text-emerald-300">
                            <th class="px-6 py-4 font-semibold">#</th>
                            <th class="px-4 py-4 font-semibold">Name</th>
                            <th class="px-4 py-4 font-semibold">Email</th>
                            <th class="px-4 py-4 font-semibold">Mobile</th>
                            <th class="px-4 py-4 font-semibold">Level</th>
                            <th class="px-4 py-4 font-semibold">Sponsor</th>
                            <th class="px-6 py-4 font-semibold">Joined</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach ($members as $member)
                            <tr class="transition hover:bg-white/3">
                                <td class="px-6 py-3 text-slate-300">{{ $members->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="font-medium text-white">{{ $member->name }}</span>
                                    <span class="text-sky-400/80">({{ $member->user_id }})</span>
                                </td>
                                <td class="px-4 py-3 text-slate-300">{{ $member->email }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $member->mobile }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex min-w-10 justify-center rounded-lg px-2 py-1 text-xs font-bold {{ $member->team_level === 1 ? 'bg-emerald-600/40 text-emerald-200 ring-1 ring-emerald-400/40' : 'bg-white/10 text-slate-200 ring-1 ring-white/10' }}">
                                        L{{ $member->team_level }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-300">{{ $member->sponsor?->name }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-slate-300">{{ $member->created_at->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 px-6 py-4">
                <p class="text-sm text-slate-400">
                    Showing <span class="text-white">{{ $members->firstItem() }}</span>–<span class="text-white">{{ $members->lastItem() }}</span>
                    of <span class="text-white">{{ $members->total() }}</span> members
                </p>
                {{ $members->links('pagination.nx') }}
            </div>
        @endif
    </section>
</div>
@endsection
