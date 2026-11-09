{{-- On-chain wallet: link a wallet to this account (signed message), then
     show it and, once contracts are deployed, its token balance.
     Filled in by resources/js/nexgrow/ui.js (widget "wallet-link"). --}}
@php $chainMode = \App\Support\Chain::enabled(); @endphp
<section class="nx-panel p-5" data-nx-chain="wallet-link">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h3 class="flex items-center gap-3 text-lg font-semibold text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-nx-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12V7H5a2 2 0 010-4h14v4M3 5v14a2 2 0 002 2h16v-5M18 12a2 2 0 000 4h4v-4h-4z" /></svg>
                On-chain Wallet
            </h3>

            <p data-nx-field="unlinked" class="mt-2 text-sm text-slate-300 {{ $user->wallet_address ? 'hidden' : '' }}">
                Link your crypto wallet (e.g. MetaMask) to this account.
                {{ $chainMode ? 'You need it to buy NFTs, Profit-Sharing Slots and to receive income.' : 'You will use it to buy NFTs and receive income on-chain.' }}
                Linking only asks you to sign a message — it costs nothing.
            </p>

            <div data-nx-field="linked" class="mt-2 space-y-1 {{ $user->wallet_address ? '' : 'hidden' }}">
                <p class="text-sm text-slate-300">Linked wallet</p>
                <p data-nx-field="address" class="font-mono text-sm break-all text-white">{{ $user->wallet_address }}</p>
            </div>
        </div>

        @if ($chainMode)
            <div data-nx-field="linked" class="text-right {{ $user->wallet_address ? '' : 'hidden' }}">
                <p class="text-sm text-slate-300">On-chain balance</p>
                <p class="mt-1 text-2xl font-bold text-white"><span data-nx-field="balance">…</span> <span class="text-sm font-semibold text-nx-gold">NXD</span></p>
            </div>
        @endif
    </div>

    <button type="button" data-nx-action="link"
        class="mt-4 flex w-fit items-center gap-2 rounded-xl border border-nx-gold/60 bg-nx-gold/10 px-4 py-2.5 text-sm font-semibold text-nx-gold transition hover:border-nx-gold hover:bg-nx-gold/20 {{ $user->wallet_address ? 'hidden' : '' }}">
        Connect &amp; link wallet
    </button>

    <p data-nx-field="message" class="mt-3 hidden text-sm"></p>
</section>
