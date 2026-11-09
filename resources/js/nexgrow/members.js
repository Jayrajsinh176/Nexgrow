// Admin member pages widget (chain mode): fills each linked wallet's NXD
// balance and active NFT package from the contracts. Each member element has
// data-nx-member-wallet and the package-loading / package-active /
// package-none, package, package-rate, package-paid, package-cap and
// balance fields.
import { contract, formatToken, isDeployed } from './chain.js';
import { onChainUpdated, register, setText, show } from './ui.js';
import { getActiveNft } from './nft.js';

async function tokenBalance(wallet) {
    if (!isDeployed()) return null;
    try {
        return await contract('token').balanceOf(wallet);
    } catch {
        return null;
    }
}

async function renderMember(el) {
    const wallet = el.dataset.nxMemberWallet;
    const [balance, nft] = await Promise.all([tokenBalance(wallet), getActiveNft(wallet)]);

    setText(el, 'balance', balance === null ? '—' : formatToken(balance));

    show(el, 'package-loading', false);
    show(el, 'package-active', nft !== null);
    show(el, 'package-none', nft === null);

    if (nft === null) return;

    setText(el, 'package', Number(nft.packageAmount).toLocaleString());
    setText(el, 'package-rate', formatToken(nft.dailyRate));
    setText(el, 'package-paid', formatToken(nft.paid));
    setText(el, 'package-cap', formatToken(nft.cap));
}

async function members(root) {
    async function render() {
        await Promise.all([...root.querySelectorAll('[data-nx-member-wallet]')].map(renderMember));
    }

    onChainUpdated(render);
    await render();
}

register('members', members);
