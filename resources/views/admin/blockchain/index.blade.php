@extends('layouts.dashboard', ['title' => 'Blockchain Operations', 'dark' => true])

@section('content')
<div class="space-y-6 text-slate-200">

    {{-- Feedback messages --}}
    @if (session('status'))
        <div class="rounded-xl border border-emerald-400/50 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-300">
            {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border border-rose-500/50 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">
            @foreach ($errors->all() as $err)
                <p>{{ $err }}</p>
            @endforeach
        </div>
    @endif

    {{-- System Status Banner --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="nx-panel flex flex-col justify-between p-5">
            <div>
                <p class="text-xs text-slate-400">Target Network</p>
                <p class="mt-1 text-xl font-bold text-white">{{ $chainConfig['name'] ?? 'Local Chain' }}</p>
                <p class="mt-1 font-mono text-xs text-nx-gold">Chain ID: {{ $chainConfig['id'] ?? 31337 }}</p>
            </div>
            <div class="mt-4 border-t border-white/10 pt-3 text-xs text-slate-400">
                RPC: <span class="font-mono text-slate-300">{{ $chainConfig['rpc_url'] }}</span>
            </div>
        </div>

        <div class="nx-panel p-5">
            <p class="text-xs text-slate-400">Platform Operating Mode</p>
            <p class="mt-1 text-xl font-bold text-emerald-400 uppercase">{{ $mode }} mode</p>
            <p class="mt-1 text-xs text-slate-400">
                {{ $mode === 'chain' ? 'Users purchase and earn through smart contracts.' : 'Operating in off-chain database mode.' }}
            </p>
        </div>

        <div class="nx-panel flex flex-col justify-between p-5">
            <div>
                <p class="text-xs text-slate-400">Deployment Status</p>
                <p class="mt-1 text-xl font-bold {{ $isDeployed ? 'text-nx-gold' : 'text-amber-400' }}">
                    {{ $isDeployed ? 'Contracts Active' : 'Not Deployed' }}
                </p>
                <p class="mt-1 text-xs text-slate-400">
                    {{ $isDeployed ? count($contracts) . ' contracts registered' : 'Run deployNexgrow.ts to deploy' }}
                </p>
            </div>
            <div class="mt-4 border-t border-white/10 pt-3 text-xs text-slate-400">
                Keeper:
                @if ($keeperConfig['enabled'])
                    <span class="text-emerald-400">Enabled</span> &middot; scheduled every 10 minutes (needs the server's <span class="font-mono">schedule:run</span> cron)
                @else
                    <span class="text-slate-300">Disabled</span> &middot; run it manually below
                @endif
            </div>
        </div>
    </div>

    {{-- Live Pools & Balances Monitor --}}
    <section class="nx-panel p-6" id="admin-chain-monitor">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 pb-4">
            <div>
                <h3 class="text-lg font-bold text-white">Live On-Chain Core Pools</h3>
                <p class="text-xs text-slate-400">Real-time balances in the system contracts</p>
            </div>
            <button type="button" onclick="loadLiveBalances()"
                class="rounded-lg border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:text-white">
                Refresh Balances
            </button>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-white/10 bg-white/2 p-4">
                <p class="text-xs text-slate-400">Nexgrow Treasury</p>
                <p class="mt-2 text-2xl font-bold text-white" id="treasury-balance">-- <span class="text-xs font-normal text-slate-400">NXD</span></p>
                <p class="mt-1 text-xs text-slate-400">Accumulates 100% of NFT sales</p>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/2 p-4">
                <p class="text-xs text-slate-400">Reward Pool</p>
                <p class="mt-2 text-2xl font-bold text-emerald-400" id="reward-pool-balance">-- <span class="text-xs font-normal text-slate-400">NXD</span></p>
                <p class="mt-1 text-xs text-slate-400">Total held, including income budgets. Split shown in <a href="{{ route('admin.owner-actions') }}" class="text-nx-gold hover:underline">Owner Actions</a>.</p>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/2 p-4">
                <p class="text-xs text-slate-400">Liquidity Pool Reserve</p>
                <p class="mt-2 text-2xl font-bold text-sky-400" id="liquidity-pool-balance">-- <span class="text-xs font-normal text-slate-400">NXD</span></p>
                <p class="mt-1 text-xs text-slate-400">Secondary liquidity backing</p>
            </div>
        </div>

        <p id="chain-status-note" class="mt-4 text-xs text-slate-400">
            Loading balances…
        </p>
    </section>

    {{-- Keeper Trigger Form --}}
    <section>
        <div class="nx-panel p-6">
            <h3 class="font-bold text-white">Trigger Blockchain Keeper</h3>
            <p class="mt-1 text-xs text-slate-400">
                Executes on-chain routines: drains referral queues, settles 15-day boosters, registers monthly loyalties, and checks performance leg qualifications.
            </p>

            <form method="POST" action="{{ route('admin.blockchain.keeper') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                <div class="flex-1">
                    <label for="keeper-tasks" class="block text-xs font-medium text-slate-300">Select Tasks to Run</label>
                    <select id="keeper-tasks" name="tasks"
                        class="mt-1 w-full rounded-xl border border-white/15 bg-nx-panel px-4 py-2.5 text-sm text-white focus:border-nx-gold focus:outline-none">
                        @foreach ($keeperTasks as $value => $label)
                            <option value="{{ $value }}" @selected(($keeperStatus['tasks'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" @disabled($keeperRunning)
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-nx-gold/70 bg-gradient-to-b from-amber-500/40 to-amber-900/40 px-5 py-2.5 text-sm font-semibold whitespace-nowrap text-white transition hover:border-nx-gold hover:shadow-lg disabled:cursor-not-allowed disabled:opacity-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-nx-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    {{ $keeperRunning ? 'Running…' : 'Run Keeper Now' }}
                </button>
            </form>

            {{-- Last run (scheduled or manual), from App\Support\KeeperStatus --}}
            <div class="mt-5 rounded-xl border border-white/10 bg-white/2 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs font-semibold text-slate-300">Last run</p>
                    @if ($keeperStatus)
                        @php
                            $state = $keeperRunning ? 'running' : ($keeperStatus['state'] === 'running' ? 'stopped' : $keeperStatus['state']);
                            $badge = [
                                'running' => 'border-amber-400/50 bg-amber-400/10 text-amber-300',
                                'finished' => 'border-emerald-400/50 bg-emerald-400/10 text-emerald-300',
                                'failed' => 'border-rose-400/50 bg-rose-500/10 text-rose-300',
                                'stopped' => 'border-rose-400/50 bg-rose-500/10 text-rose-300',
                            ][$state];
                        @endphp
                        <span class="rounded-lg border px-2.5 py-0.5 text-xs font-semibold {{ $badge }}">
                            {{ ['running' => 'Running…', 'finished' => 'Finished', 'failed' => 'Failed', 'stopped' => 'Stopped without finishing'][$state] }}
                        </span>
                    @endif
                </div>

                @if ($keeperStatus)
                    <p class="mt-2 text-xs text-slate-400">
                        {{ ucfirst($keeperStatus['trigger']) }} run of <span class="text-slate-200">{{ $keeperTasks[$keeperStatus['tasks']] ?? $keeperStatus['tasks'] }}</span>
                        &middot; started {{ \Illuminate\Support\Carbon::parse($keeperStatus['started_at'])->diffForHumans() }}
                        @if ($keeperStatus['finished_at'])
                            &middot; took {{ \Illuminate\Support\Carbon::parse($keeperStatus['started_at'])->diffForHumans(\Illuminate\Support\Carbon::parse($keeperStatus['finished_at']), true) }}
                        @endif
                    </p>
                    @if ($keeperStatus['output'] !== '')
                        <pre class="mt-3 max-h-64 overflow-auto rounded-lg bg-black/40 p-3 font-mono text-[11px] whitespace-pre-wrap text-slate-300">{{ $keeperStatus['output'] }}</pre>
                    @endif
                @else
                    <p class="mt-2 text-xs text-slate-400">The keeper has not run since this panel was added.</p>
                @endif
            </div>
        </div>
    </section>

    @if ($keeperRunning)
        {{-- Reload until the run finishes. --}}
        <script>setTimeout(() => window.location.reload(), 5000);</script>
    @endif

    {{-- Smart Contracts Directory --}}
    <section class="nx-panel overflow-hidden">
        <div class="border-b border-white/10 px-6 py-4">
            <h3 class="font-bold text-white">Deployed Smart Contracts Directory</h3>
            <p class="text-xs text-slate-400">All registered modules connected through the Nexgrow Manager</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-white/10 text-xs font-semibold text-slate-400">
                        <th class="px-6 py-4">Contract Role</th>
                        <th class="px-6 py-4">Key</th>
                        <th class="px-6 py-4">Contract Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($contracts as $key => $address)
                        <tr class="transition hover:bg-white/2">
                            <td class="px-6 py-4 font-semibold text-white">
                                {{ $contractRoles[$key] ?? Str::headline($key) }}
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-nx-gold">
                                {{ $key }}
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-slate-300">
                                <span class="rounded bg-white/5 px-2.5 py-1 text-emerald-300">{{ $address }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-8 text-center text-slate-400">
                                No deployed contracts found in deployments/{{ $chainConfig['id'] }}.json.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
    async function rpcBalanceOf(rpcUrl, tokenAddress, accountAddress) {
        if (!tokenAddress || !accountAddress) return 0n;
        const cleanAccount = accountAddress.toLowerCase().replace('0x', '').padStart(64, '0');
        const data = '0x70a08231' + cleanAccount;
        const res = await fetch(rpcUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                jsonrpc: '2.0',
                id: 1,
                method: 'eth_call',
                params: [{ to: tokenAddress, data: data }, 'latest']
            })
        });
        const json = await res.json();
        if (json.error) throw new Error(json.error.message);
        return BigInt(json.result || '0x0');
    }

    function formatNxd(bigintVal) {
        const str = bigintVal.toString().padStart(19, '0');
        const whole = str.slice(0, -18) || '0';
        const frac = str.slice(-18, -16);
        return Number(whole).toLocaleString() + '.' + frac;
    }

    async function loadLiveBalances() {
        const note = document.getElementById('chain-status-note');
        try {
            const configEl = document.getElementById('nx-chain-config');
            if (!configEl) return;
            const config = JSON.parse(configEl.textContent || '{}');
            const contracts = config.contracts || {};
            const rpcUrl = config.rpcUrl;
            const tokenAddr = contracts.paymentToken;

            if (!rpcUrl || !tokenAddr) {
                if (note) note.textContent = 'Chain configuration or token address missing.';
                return;
            }

            if (note) note.textContent = 'Streaming live balances from ' + (config.chainName || 'BSC Testnet') + '...';

            const [treasuryBal, rewardPoolBal, liquidityBal] = await Promise.all([
                contracts.treasury ? rpcBalanceOf(rpcUrl, tokenAddr, contracts.treasury).catch(() => 0n) : 0n,
                contracts.rewardPool ? rpcBalanceOf(rpcUrl, tokenAddr, contracts.rewardPool).catch(() => 0n) : 0n,
                contracts.liquidityPool ? rpcBalanceOf(rpcUrl, tokenAddr, contracts.liquidityPool).catch(() => 0n) : 0n,
            ]);

            const tEl = document.getElementById('treasury-balance');
            const rEl = document.getElementById('reward-pool-balance');
            const lEl = document.getElementById('liquidity-pool-balance');

            if (tEl) tEl.innerHTML = `${formatNxd(treasuryBal)} <span class="text-xs font-normal text-slate-400">NXD</span>`;
            if (rEl) rEl.innerHTML = `${formatNxd(rewardPoolBal)} <span class="text-xs font-normal text-slate-400">NXD</span>`;
            if (lEl) lEl.innerHTML = `${formatNxd(liquidityBal)} <span class="text-xs font-normal text-slate-400">NXD</span>`;

            if (note) note.innerHTML = `<span class="inline-block h-2 w-2 rounded-full bg-emerald-400 mr-1.5"></span>Connected to <strong>${config.chainName || 'BNB Smart Chain Testnet'}</strong> (Chain ID ${config.chainId}). Streaming live on-chain balances.`;
        } catch (e) {
            console.error('Error loading balances:', e);
            if (note) note.textContent = 'Failed to load balances: ' + e.message;
        }
    }

    document.addEventListener('DOMContentLoaded', loadLiveBalances);
</script>
@endsection

