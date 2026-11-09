// Profit-Sharing Slot widget (chain mode): buy and withdraw with the wallet.
import { parseUnits } from 'ethers';
import { config, connectLinked, contract, formatToken, isDeployed, send } from './chain.js';
import {
    chainUpdated, onAction, register, setMessage, setText, show,
} from './ui.js';

const PRICE = parseUnits('3000', 18);

async function slot(root) {
    async function render() {
        if (!isDeployed()) return;

        const slots = contract('profitSharingSlot');
        setText(root, 'remaining', (await slots.slotsRemaining()).toString());

        const wallet = config().user?.wallet;
        if (!wallet) return;

        const has = await slots.hasSlot(wallet);

        show(root, 'buy-panel', !has);
        show(root, 'slot-panel', has);

        if (!has) return;

        const id = await slots.slotOf(wallet);
        const [state, preview, matured, days] = await Promise.all([
            slots.getSlot(id), slots.previewWithdraw(id), slots.isMatured(id), slots.daysHeld(id),
        ]);
        const [total, deductionBps, payout] = preview;

        setText(root, 'slot-id', id.toString());
        setText(root, 'slot-status', state.withdrawn ? 'Withdrawn' : matured ? 'Matured' : 'Earning');
        setText(root, 'purchased', new Date(Number(state.purchasedAt) * 1000).toLocaleDateString());
        setText(root, 'days', days.toString());
        setText(root, 'profit', formatToken(state.profit));
        setText(root, 'total', formatToken(total));
        setText(root, 'deduction', deductionBps > 0n ? `${Number(deductionBps) / 100}%` : 'None');
        setText(root, 'payout', formatToken(payout));
        show(root, 'withdraw', !state.withdrawn);
    }

    onAction(root, 'buy', async () => {
        const { signer, address } = await connectLinked();
        const slots = contract('profitSharingSlot', signer);
        const token = contract('token', signer);
        const slotsAddress = await slots.getAddress();

        if (await slots.hasSlot(address)) throw new Error('This wallet already has a slot.');
        if ((await slots.slotsRemaining()) === 0n) throw new Error('All slots are sold.');
        if ((await token.balanceOf(address)) < PRICE) throw new Error('Your wallet does not hold 3,000 NXD.');

        const status = (text) => setMessage(root, text);

        if ((await token.allowance(address, slotsAddress)) < PRICE) {
            await send('Approve NXD', token.approve(slotsAddress, PRICE), status);
        }

        await send('Buy slot', slots.buySlot(), status);
        setMessage(root, 'You bought a Profit-Sharing Slot.', 'success');
        chainUpdated();
    });

    onAction(root, 'withdraw', async () => {
        const { signer, address } = await connectLinked();
        const slots = contract('profitSharingSlot', signer);
        const id = await slots.slotOf(address);
        const [, deductionBps, payout] = await slots.previewWithdraw(id);
        const deduction = deductionBps > 0n ? ` after a ${Number(deductionBps) / 100}% early-withdrawal deduction` : '';

        if (!window.confirm(`Withdraw your slot now? You receive ${formatToken(payout)} NXD${deduction}. This ends the slot.`)) {
            return;
        }

        await send('Withdraw slot', slots.withdraw(id), (text) => setMessage(root, text));
        setMessage(root, `Slot withdrawn: ${formatToken(payout)} NXD sent to your wallet.`, 'success');
        chainUpdated();
    });

    document.addEventListener('nx:chain-updated', () => render().catch(() => {}));
    await render();
}

register('slot', slot);
