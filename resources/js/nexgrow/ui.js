// Page widgets for the Nexgrow contracts. Blade renders the markup with
// data-nx-chain="<widget>" and data-nx-field / data-nx-action children;
// these functions fill in live values and wire up the buttons.
import { getAddress } from 'ethers';
import {
    config, contract, errorMessage, formatToken, isDeployed, linkWallet, shortAddress,
} from './chain.js';

// ---- Small DOM helpers ----

export function field(root, name) {
    return root.querySelector(`[data-nx-field="${name}"]`);
}

export function setText(root, name, text) {
    const el = field(root, name);
    if (el) el.textContent = text;
}

export function show(root, name, visible) {
    root.querySelectorAll(`[data-nx-field="${name}"], [data-nx-action="${name}"]`)
        .forEach((el) => el.classList.toggle('hidden', !visible));
}

export function setMessage(root, text, kind = 'info') {
    const el = field(root, 'message');
    if (!el) return;

    el.textContent = text;
    el.classList.toggle('hidden', !text);
    el.classList.toggle('text-rose-300', kind === 'error');
    el.classList.toggle('text-emerald-300', kind === 'success');
    el.classList.toggle('text-slate-300', kind === 'info');
}

// Tells every widget on the page that on-chain state changed.
export function chainUpdated() {
    document.dispatchEvent(new CustomEvent('nx:chain-updated'));
}

export function onChainUpdated(render) {
    document.addEventListener('nx:chain-updated', () => render().catch(() => {}));
}

// Runs an async button action with a busy state and error display.
export function onAction(root, name, handler) {
    root.querySelectorAll(`[data-nx-action="${name}"]`).forEach((button) => {
        button.addEventListener('click', async () => {
            if (button.disabled) return;

            button.disabled = true;
            button.classList.add('opacity-60');

            try {
                await handler(button);
            } catch (error) {
                setMessage(root, errorMessage(error), 'error');
            } finally {
                button.disabled = false;
                button.classList.remove('opacity-60');
            }
        });
    });
}

// ---- Widgets ----

async function walletLink(root) {
    async function render() {
        const wallet = config().user?.wallet;

        show(root, 'linked', Boolean(wallet));
        show(root, 'unlinked', !wallet);
        show(root, 'link', !wallet);

        if (!wallet) return;

        setText(root, 'address', getAddress(wallet));
        setText(root, 'address-short', shortAddress(getAddress(wallet)));

        if (isDeployed() && field(root, 'balance')) {
            try {
                setText(root, 'balance', formatToken(await contract('token').balanceOf(wallet)));
            } catch {
                setText(root, 'balance', '—');
            }
        }
    }

    onAction(root, 'link', async () => {
        setMessage(root, 'Confirm the signature request in your wallet…');
        await linkWallet();
        setMessage(root, 'Wallet linked to your account.', 'success');
        await render();
    });

    await render();
}

const WIDGETS = {
    'wallet-link': walletLink,
};

export function register(name, init) {
    WIDGETS[name] = init;
}

// Starts every widget on the page; resolves once they have all loaded.
export function init() {
    const started = [...document.querySelectorAll('[data-nx-chain]')].map((root) => {
        const widget = WIDGETS[root.dataset.nxChain];

        return widget
            ? widget(root).catch((error) => setMessage(root, errorMessage(error), 'error'))
            : Promise.resolve();
    });

    return Promise.all(started);
}
