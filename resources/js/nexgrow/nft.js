// NFT page widgets (chain mode): my-nfts, and packages with auto-sponsor linking and locked state.
import { ZeroAddress, getAddress, parseUnits } from 'ethers';
import {
    config, connectLinked, contract, errorMessage, formatToken, isDeployed, send,
} from './chain.js';
import {
    chainUpdated, field, register, setMessage, setText, show,
} from './ui.js';

function onChainUpdated(render) {
    document.addEventListener('nx:chain-updated', () => render().catch(() => {}));
}

// Where the linked wallet stands with its sponsor on-chain.
async function sponsorState() {
    const user = config().user ?? {};
    const wallet = user.wallet;

    const [onChainSponsor, hasPurchased] = await Promise.all([
        contract('referralIncome').sponsorOf(wallet),
        contract('manager').hasPurchased(wallet),
    ]);

    let sponsorReady = false;

    if (user.sponsorWallet) {
        sponsorReady = (await contract('nft').balanceOf(user.sponsorWallet)) > 0n;
    }

    return {
        hasLaravelSponsor: Boolean(user.sponsorName),
        joined: onChainSponsor !== ZeroAddress,
        hasPurchased,
        sponsorReady,
    };
}

const OWNER_BATCH = 25;

// Token IDs the wallet owns. Every NFT is registered with the manager under
// IDs 0..registeredCount-1, so this checks ownerOf from the newest ID down,
// in parallel batches, and stops once it has found the wallet's whole balance.
// (Scanning Transfer events from the deployment block took thousands of RPC calls.)
export async function ownedTokenIds(wallet) {
    if (!wallet) return [];
    const nft = contract('nft');
    try {
        const [balance, total] = await Promise.all([
            nft.balanceOf(wallet),
            contract('manager').registeredCount(),
        ]);
        if (balance === 0n) return [];

        const owned = [];
        for (let top = Number(total) - 1; top >= 0 && owned.length < Number(balance); top -= OWNER_BATCH) {
            const ids = [];
            for (let id = top; id > top - OWNER_BATCH && id >= 0; id--) ids.push(BigInt(id));

            const owners = await Promise.all(ids.map((id) => nft.ownerOf(id).catch(() => null)));
            ids.forEach((id, i) => {
                if (owners[i]?.toLowerCase() === wallet.toLowerCase()) owned.push(id);
            });
        }

        return owned.sort((a, b) => (a < b ? -1 : 1));
    } catch (err) {
        console.error('Error fetching ownedTokenIds:', err);
        return [];
    }
}

// The NFTIncome state of the wallet's currently active NFT, or null if none is active.
export async function getActiveNft(wallet) {
    if (!wallet || !isDeployed()) return null;
    try {
        const ids = await ownedTokenIds(wallet);
        const nftIncome = contract('nftIncome');

        for (const id of ids) {
            const state = await nftIncome.nfts(id);
            const completed = state.active && state.paid === state.cap;
            if (state.active && !completed) {
                return state;
            }
        }
        return null;
    } catch (err) {
        console.warn('getActiveNft failed:', err);
        return null;
    }
}

// Returns the package amount of the user's currently active NFT, or null if none is active.
export async function getActivePackage(wallet) {
    const state = await getActiveNft(wallet);
    return state === null ? null : Number(state.packageAmount);
}

// ---- Widgets ----

async function myNfts(root) {
    const list = field(root, 'list');
    const template = field(root, 'item');

    async function render() {
        const wallet = config().user?.wallet;
        if (!wallet || !isDeployed()) return;

        root.classList.remove('hidden');
        try {
            setText(root, 'balance', formatToken(await contract('token').balanceOf(wallet)));
        } catch (e) {
            console.warn('Failed to fetch token balance:', e);
        }

        try {
            const nftIncome = contract('nftIncome');
            const ids = await ownedTokenIds(wallet);

            list.replaceChildren();
            show(root, 'empty', ids.length === 0);

            for (const id of ids) {
                const [state, earned] = await Promise.all([nftIncome.nfts(id), nftIncome.entitledOf(id)]);
                const item = template.content.cloneNode(true);
                const set = (name, text) => { item.querySelector(`[data-nx-item="${name}"]`).textContent = text; };
                const completed = state.active && state.paid === state.cap;
                const status = item.querySelector('[data-nx-item="status"]');

                set('id', id.toString());
                set('package', state.packageAmount.toString());
                set('rate', formatToken(state.dailyRate));
                set('earned', formatToken(earned));
                set('paid', formatToken(state.paid));
                set('cap', formatToken(state.cap));

                status.textContent = !state.active ? 'Not active' : completed ? 'Completed' : 'Active';
                status.classList.add(...(completed || !state.active
                    ? ['border-white/20', 'text-slate-300']
                    : ['border-emerald-400/50', 'bg-emerald-400/10', 'text-emerald-300']));

                const percent = state.cap > 0n ? Number((state.paid * 10000n) / state.cap) / 100 : 0;
                item.querySelector('[data-nx-item="bar"]').style.width = `${percent}%`;

                list.appendChild(item);
            }
        } catch (err) {
            console.error('Failed to render myNfts:', err);
            setMessage(root, errorMessage(err), 'error');
        }
    }

    onChainUpdated(render);
    await render();
}

async function packages(root) {
    async function renderLockState() {
        const wallet = config().user?.wallet;
        if (!wallet || !isDeployed()) return;

        try {
            const activePackage = await getActivePackage(wallet);
            const cards = root.querySelectorAll('[data-nx-package-card]');

            cards.forEach((card) => {
                const pkg = Number(card.dataset.nxPackageCard);
                const slot = card.querySelector('[data-nx-slot="action"]');
                if (!slot) return;

                if (activePackage !== null) {
                    if (pkg === activePackage) {
                        card.classList.remove('opacity-50');
                        slot.innerHTML = `
                            <div class="flex items-center justify-center gap-2 rounded-xl border border-nx-gold/60 bg-nx-gold/10 px-4 py-2.5 text-sm font-semibold text-nx-gold">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                Your active package
                            </div>
                        `;
                    } else {
                        card.classList.add('opacity-50');
                        slot.innerHTML = `
                            <div class="flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-slate-400 cursor-not-allowed select-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                Locked &middot; Active package running
                            </div>
                        `;
                    }
                } else {
                    card.classList.remove('opacity-50');
                    slot.innerHTML = `
                        <button type="button" data-nx-action="buy" data-nx-package="${pkg}"
                            class="w-full rounded-xl border border-nx-gold/70 bg-gradient-to-b from-amber-500/40 to-amber-900/40 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-nx-gold hover:shadow-[0_0_20px_-6px] hover:shadow-nx-gold disabled:cursor-not-allowed disabled:opacity-50">
                            Buy with wallet &middot; ${pkg.toLocaleString()} NXD
                        </button>
                    `;
                }
            });
        } catch (err) {
            console.error('Failed to render package lock state:', err);
        }
    }

    onChainUpdated(renderLockState);
    await renderLockState();

    root.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-nx-action="buy"]');
        if (!button || button.disabled) return;

        button.disabled = true;
        button.classList.add('opacity-60');

        try {
            const pkg = Number(button.dataset.nxPackage);
            const { signer, address } = await connectLinked();
            const state = await sponsorState();
            const status = (text) => setMessage(root, text);

            // Automatically record sponsor on-chain before buying if user signed up with sponsor
            if (state.hasLaravelSponsor && !state.joined && !state.hasPurchased) {
                if (state.sponsorReady) {
                    const referral = contract('referralIncome', signer);
                    await send(
                        `Record Sponsor (${config().user?.sponsorName || 'Sponsor'})`,
                        referral.setSponsor(getAddress(config().user.sponsorWallet)),
                        status
                    );
                }
            }

            if (!(await contract('manager').canPurchase(address))) {
                throw new Error('Your current NFT must complete its 4X cycle before you can buy another.');
            }

            const amount = parseUnits(String(pkg), 18);
            const token = contract('token', signer);
            const nft = contract('nft', signer);
            const nftAddress = await nft.getAddress();

            if ((await token.balanceOf(address)) < amount) {
                throw new Error(`Your wallet does not hold ${pkg} NXD.`);
            }

            if ((await token.allowance(address, nftAddress)) < amount) {
                await send('Approve NXD', token.approve(nftAddress, amount), status);
            }

            await send(`Buy ${pkg} NXD NFT`, nft.buyNFT(pkg), status);
            setMessage(root, `You bought the ${pkg} NXD NFT. Your daily bonus starts tomorrow.`, 'success');
            chainUpdated();
        } catch (error) {
            setMessage(root, errorMessage(error), 'error');
        } finally {
            button.disabled = false;
            button.classList.remove('opacity-60');
        }
    });
}

register('my-nfts', myNfts);
register('packages', packages);
