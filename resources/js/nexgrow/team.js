// Team downline page widget (chain mode): resolves active NFT package
// for each downline member's linked wallet.
import { isDeployed } from './chain.js';
import { onChainUpdated, register } from './ui.js';
import { getActivePackage } from './nft.js';

async function team(root) {
    async function render() {
        if (!isDeployed()) return;

        const rows = root.querySelectorAll('[data-nx-member-wallet]');
        let activeCount = 0;

        for (const row of rows) {
            const wallet = row.dataset.nxMemberWallet;
            const target = row.querySelector('[data-nx-field="package"]');
            if (!target) continue;

            if (!wallet) {
                target.className = 'inline-flex items-center rounded-lg border border-white/10 bg-white/5 px-2.5 py-1 text-xs text-slate-400';
                target.textContent = 'No Wallet';
                continue;
            }

            try {
                const pkg = await getActivePackage(wallet);
                if (pkg !== null) {
                    activeCount++;
                    target.className = 'inline-flex items-center gap-1.5 rounded-lg border border-nx-gold/50 bg-nx-gold/10 px-2.5 py-1 text-xs font-semibold text-nx-gold';
                    target.innerHTML = `<span class="h-1.5 w-1.5 rounded-full bg-nx-gold"></span>${pkg.toLocaleString()} NXD`;
                } else {
                    target.className = 'inline-flex items-center rounded-lg border border-white/10 bg-white/5 px-2.5 py-1 text-xs text-slate-400';
                    target.textContent = 'None';
                }
            } catch (error) {
                target.className = 'inline-flex items-center rounded-lg border border-white/10 bg-white/5 px-2.5 py-1 text-xs text-slate-400';
                target.textContent = 'None';
            }
        }

        const activeStat = document.querySelector('[data-nx-field="active-members"]');
        if (activeStat && activeCount > 0) {
            activeStat.textContent = String(activeCount);
        }
    }

    onChainUpdated(render);
    await render();
}

register('team', team);
export { team };
