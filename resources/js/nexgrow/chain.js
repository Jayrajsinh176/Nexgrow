// Nexgrow contracts in the browser: wallet connection, network checks,
// contract instances and formatting. Settings come from the
// #nx-chain-config JSON embedded by the dashboard layout (App\Support\Chain).
import { BrowserProvider, Contract, JsonRpcProvider, formatUnits, getAddress } from 'ethers';
import abis from './abis.json' with { type: 'json' };

let cachedConfig = null;
let readProvider = null;

export function config() {
    if (cachedConfig === null) {
        const el = document.getElementById('nx-chain-config');
        cachedConfig = el ? JSON.parse(el.textContent) : {};
    }

    return cachedConfig;
}

// Forgets the cached settings (for tests that switch users).
export function resetConfig() {
    cachedConfig = null;
}

export function isChainMode() {
    return config().mode === 'chain';
}

export function isDeployed() {
    return Boolean(config().contracts?.nft);
}

export function getEthereumProvider() {
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

export function hasWallet() {
    return typeof getEthereumProvider() !== 'undefined';
}

// Provider for reads: the public RPC, so pages work without a wallet.
// Result caching is off: ethers otherwise reuses answers for 250 ms, so a
// refresh right after a transaction could read the block before it.
export function reader() {
    if (readProvider === null) {
        readProvider = new JsonRpcProvider(config().rpcUrl, config().chainId, { staticNetwork: true, cacheTimeout: -1 });
    }

    return readProvider;
}

export function contract(name, runner = reader()) {
    const address = config().contracts?.[name === 'token' ? 'paymentToken' : name];

    if (!address) {
        throw new Error(`The ${name} contract is not deployed on this network.`);
    }

    return new Contract(address, abis[name], runner);
}

// Asks the wallet for an account and returns { provider, signer, address }.
export async function connect() {
    const eth = getEthereumProvider();

    if (!eth) {
        throw new Error('No browser wallet found. Install MetaMask (or open this page in your wallet app) and try again.');
    }

    const provider = new BrowserProvider(eth);
    await provider.send('eth_requestAccounts', []);
    const signer = await provider.getSigner();

    return { provider, signer, address: getAddress(await signer.getAddress()) };
}

// Switches the wallet to the configured network, adding it if needed.
export async function ensureNetwork() {
    const eth = getEthereumProvider();
    if (!eth) return;

    const { chainId, chainName, rpcUrl, explorerUrl, currency } = config();
    const hexId = '0x' + Number(chainId).toString(16);
    const current = await eth.request({ method: 'eth_chainId' });

    if (parseInt(current, 16) === Number(chainId)) return;

    try {
        await eth.request({ method: 'wallet_switchEthereumChain', params: [{ chainId: hexId }] });
    } catch (error) {
        if (error?.code !== 4902) throw error;

        await eth.request({
            method: 'wallet_addEthereumChain',
            params: [{
                chainId: hexId,
                chainName,
                rpcUrls: [rpcUrl],
                nativeCurrency: { name: currency, symbol: currency, decimals: 18 },
                blockExplorerUrls: explorerUrl ? [explorerUrl] : undefined,
            }],
        });
    }
}

// Connects on the right network, and checks it is the account's linked wallet.
export async function connectLinked() {
    const linked = config().user?.wallet;

    if (!linked) {
        throw new Error('Link your wallet on the Token Wallet page first.');
    }

    await ensureNetwork();
    const session = await connect();

    if (session.address.toLowerCase() !== linked.toLowerCase()) {
        throw new Error(`Switch your wallet to the linked account ${shortAddress(linked)}.`);
    }

    return session;
}

// Proves wallet ownership to the server with a signed one-time message.
export async function linkWallet() {
    await ensureNetwork();
    const { signer, address } = await connect();
    const { data } = await window.axios.get(config().routes.linkMessage);
    const signature = await signer.signMessage(data.message);
    const response = await window.axios.post(config().routes.link, { address, signature });

    config().user.wallet = response.data.wallet_address;

    return response.data.wallet_address;
}

const LOG_CHUNK = 2_000;
const blockTimes = new Map();

// Events matching a filter since deployment, in chunks the RPC accepts.
export async function queryEvents(target, filter) {
    try {
        const latest = await reader().getBlockNumber();
        const start = Number(config().startBlock) > 0
            ? Number(config().startBlock)
            : Math.max(0, latest - 5000);
        const found = [];

        for (let from = start; from <= latest; from += LOG_CHUNK) {
            const to = Math.min(from + LOG_CHUNK - 1, latest);
            try {
                const logs = await target.queryFilter(filter, from, to);
                found.push(...logs);
            } catch (chunkError) {
                console.warn(`queryFilter failed for range ${from}-${to}:`, chunkError);
            }
        }

        return found;
    } catch (error) {
        console.warn('queryEvents failed:', error);
        return [];
    }
}

// Block timestamp as a Date (cached).
export async function blockDate(blockNumber) {
    if (!blockTimes.has(blockNumber)) {
        blockTimes.set(blockNumber, (await reader().getBlock(blockNumber)).timestamp);
    }

    return new Date(blockTimes.get(blockNumber) * 1000);
}

// Sends a transaction and waits for it; onStatus receives progress text.
export async function send(label, txPromise, onStatus = () => {}) {
    onStatus(`${label}: confirm in your wallet…`);
    const tx = await txPromise;
    onStatus(`${label}: waiting for confirmation…`);
    const receipt = await tx.wait();
    onStatus(`${label}: done.`);

    return receipt;
}

// ---- Formatting ----

export function formatToken(value, decimals = 2) {
    const n = Number(formatUnits(value ?? 0n, 18));

    return n.toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

export function shortAddress(address) {
    return address ? `${address.slice(0, 6)}…${address.slice(-4)}` : '';
}

export function explorerTx(hash) {
    const base = config().explorerUrl;

    return base ? `${base.replace(/\/$/, '')}/tx/${hash}` : null;
}

// Human-readable reason for a failed wallet or contract call.
export function errorMessage(error) {
    if (error?.code === 'ACTION_REJECTED' || error?.code === 4001) {
        return 'Request cancelled in your wallet.';
    }

    const server = error?.response?.data?.message;
    if (server) return server;

    return error?.reason
        || error?.revert?.args?.[0]
        || error?.shortMessage
        || error?.info?.error?.message
        || error?.message
        || 'Something went wrong.';
}
