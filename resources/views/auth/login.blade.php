@extends('layouts.guest', ['title' => 'Log in · Nexgrow'])

@section('content')
<div class="mb-7">
    <span class="inline-flex items-center gap-2 rounded-full border border-emerald-400/40 bg-emerald-400/10 px-3 py-1 text-xs font-medium text-emerald-200">
        <span class="h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_8px] shadow-emerald-400"></span>
        Web3 Portal
    </span>
    <h1 class="mt-4 text-2xl font-bold text-white sm:text-3xl">Welcome <span class="nx-gold-text">back</span></h1>
    <p class="mt-1.5 text-sm text-slate-400">Connect your crypto wallet (MetaMask) to sign in or register instantly.</p>
</div>

<div class="space-y-5">
    <div id="wallet-auth-error" class="hidden rounded-xl border border-rose-500/40 bg-rose-500/10 p-4 text-sm text-rose-300"></div>
    <div id="wallet-auth-status" class="hidden rounded-xl border border-nx-gold/40 bg-nx-gold/10 p-4 text-sm text-nx-gold"></div>

    <button type="button" id="btn-wallet-connect"
        class="nx-btn-gold w-full flex items-center justify-center gap-3 py-3.5 text-base font-semibold transition shadow-[0_0_20px_rgba(234,179,8,0.15)] hover:shadow-[0_0_25px_rgba(234,179,8,0.3)]">
        <svg class="h-6 w-6" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M29.04 16.32l-2.73-9.52a1.8 1.8 0 00-1.22-1.22L16.48 2.3a1.8 1.8 0 00-1 0L6.87 5.58a1.8 1.8 0 00-1.22 1.22L2.92 16.32a1.8 1.8 0 00.37 1.69l11.75 12.87a1.8 1.8 0 002.66 0l11.75-12.87a1.8 1.8 0 00.37-1.69z" fill="#E2761B" stroke="#E2761B" stroke-width="0.5"/>
            <path d="M16 2.3v13.68L3.29 16.32 16 2.3z" fill="#E4761B"/>
            <path d="M16 2.3v13.68l12.71.34L16 2.3z" fill="#D7C1B3"/>
            <path d="M16 23.36V30.9l12.67-14.58L16 23.36z" fill="#233447"/>
            <path d="M16 30.9v-7.54L3.33 16.32 16 30.9z" fill="#CD6116"/>
        </svg>
        <span>Connect MetaMask</span>
    </button>

    <div class="rounded-xl border border-white/10 bg-black/20 p-4 text-xs text-slate-400 space-y-1.5">
        <p class="font-medium text-slate-300 flex items-center gap-2">
            <svg class="h-4 w-4 text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            1-Click Registration &amp; Login
        </p>
        <p>Connecting will sign a gas-free verification message to authenticate your wallet. No password, email or phone number required.</p>
    </div>
</div>

<div class="my-6 flex items-center gap-3 text-xs text-slate-500">
    <span class="h-px flex-1 bg-white/10"></span>
    Join with Referral Code
    <span class="h-px flex-1 bg-white/10"></span>
</div>

<a href="{{ route('register') }}"
    class="flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-400/40 bg-emerald-400/5 px-4 py-3 text-sm font-semibold text-emerald-200 transition hover:border-nx-gold/60 hover:text-white">
    Enter Sponsor Code &amp; Register &rarr;
</a>

<script>
    (function () {
        const connectBtn = document.getElementById('btn-wallet-connect');
        const errorEl = document.getElementById('wallet-auth-error');
        const statusEl = document.getElementById('wallet-auth-status');

        function setError(msg) {
            statusEl.classList.add('hidden');
            if (!msg) {
                errorEl.classList.add('hidden');
                return;
            }
            errorEl.textContent = msg;
            errorEl.classList.remove('hidden');
        }

        function setStatus(msg) {
            errorEl.classList.add('hidden');
            if (!msg) {
                statusEl.classList.add('hidden');
                return;
            }
            statusEl.textContent = msg;
            statusEl.classList.remove('hidden');
        }

        function getEthereumProvider() {
            if (typeof window === 'undefined' || typeof window.ethereum === 'undefined') {
                return undefined;
            }
            if (Array.isArray(window.ethereum.providers) && window.ethereum.providers.length > 0) {
                const mm = window.ethereum.providers.find((p) => p.isMetaMask && !p.isBackpack && !p.isPhantom);
                if (mm) return mm;
                const anyMm = window.ethereum.providers.find((p) => p.isMetaMask);
                if (anyMm) return anyMm;
                return window.ethereum.providers[0];
            }
            return window.ethereum;
        }

        connectBtn.addEventListener('click', async function () {
            setError('');
            const eth = getEthereumProvider();

            if (!eth) {
                setError('MetaMask is not detected. Please install the MetaMask extension or open this page in your Web3 wallet browser.');
                return;
            }

            connectBtn.disabled = true;
            connectBtn.classList.add('opacity-70');
            setStatus('Connecting to MetaMask…');

            try {
                // 1. Request active account
                const accounts = await eth.request({ method: 'eth_requestAccounts' });
                if (!accounts || accounts.length === 0) {
                    throw new Error('No account found or permission denied in MetaMask.');
                }
                const address = accounts[0];

                setStatus('Fetching authentication challenge…');
                // 2. Request signing nonce message
                const msgRes = await fetch('{{ route('wallet.auth.message') }}', {
                    headers: { 'Accept': 'application/json' }
                });
                const msgData = await msgRes.json();

                if (!msgData || !msgData.message) {
                    throw new Error('Failed to retrieve signing challenge from server.');
                }

                setStatus('Please sign the verification message in MetaMask (no gas fee)…');
                // 3. Request user signature via EIP-191 personal_sign
                const signature = await eth.request({
                    method: 'personal_sign',
                    params: [msgData.message, address],
                });

                setStatus('Verifying signature and signing in…');
                // 4. Send signature to backend
                const authRes = await fetch('{{ route('wallet.auth') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        address: address,
                        signature: signature
                    })
                });

                const authData = await authRes.json();

                if (!authRes.ok) {
                    throw new Error(authData.message || 'Authentication failed. Please try again.');
                }

                setStatus('Success! Redirecting to dashboard…');
                window.location.href = authData.redirect || '{{ route('dashboard') }}';

            } catch (err) {
                console.error(err);
                if (err.code === 4001 || err.code === 'ACTION_REJECTED') {
                    setError('Signature request was cancelled in MetaMask.');
                } else {
                    setError(err.message || 'Failed to authenticate with MetaMask.');
                }
                connectBtn.disabled = false;
                connectBtn.classList.remove('opacity-70');
            }
        });
    })();
</script>
@endsection
