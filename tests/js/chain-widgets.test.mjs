// Runs the chain-mode page widgets (resources/js/nexgrow: NFT, income, slot) against real contracts:
// starts a local Hardhat node, deploys the Nexgrow system, renders the
// widget markup in jsdom and uses a test wallet backed by the node's
// unlocked accounts. Run: npm run test:js
import { after, before, describe, test } from 'node:test';
import assert from 'node:assert/strict';
import { spawn, execSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';
import { JsonRpcProvider, getAddress, parseUnits } from 'ethers';

const BLOCKCHAIN = new URL('../../../blockchain/', import.meta.url);
const RPC = 'http://127.0.0.1:8545';

let node;
let deployment;
let accounts;
let modules;

async function rpc(method, params = []) {
    const response = await fetch(RPC, {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({ jsonrpc: '2.0', id: 1, method, params }),
    });
    const json = await response.json();

    if (json.error) {
        const error = new Error(json.error.message);
        error.code = json.error.code;
        error.data = json.error.data;
        throw error;
    }

    return json.result;
}

// EIP-1193 wallet for one unlocked node account.
function walletFor(account) {
    return {
        async request({ method, params = [] }) {
            if (method === 'eth_requestAccounts' || method === 'eth_accounts') return [account];
            if (method === 'eth_chainId') return '0x7a69';
            if (method === 'wallet_switchEthereumChain') return null;

            return rpc(method, params);
        },
    };
}

async function until(check, label, timeout = 30_000) {
    const start = Date.now();

    while (Date.now() - start < timeout) {
        if (await check()) return;
        await new Promise((r) => setTimeout(r, 50));
    }

    assert.fail(`Timed out waiting for: ${label}`);
}

const visible = (root, name) => {
    const el = root.querySelector(`[data-nx-field="${name}"]`);
    return el && !el.classList.contains('hidden');
};

const message = (root) => root.querySelector('[data-nx-field="message"]').textContent;

// Mirrors the data-nx hooks of resources/views/nft/chain.blade.php
// Mirrors the data-nx hooks of resources/views/nft/chain.blade.php
// (pinned from the PHP side by tests/Feature/ChainNftPageTest.php).
const PAGE = `
<section data-nx-chain="my-nfts" class="hidden">
  <span data-nx-field="balance"></span>
  <p data-nx-field="empty" class="hidden"></p>
  <div data-nx-field="list"></div>
  <template data-nx-field="item"><div>
    <span data-nx-item="package"></span><span data-nx-item="id"></span><span data-nx-item="status"></span>
    <span data-nx-item="rate"></span><span data-nx-item="earned"></span><span data-nx-item="paid"></span>
    <span data-nx-item="cap"></span><div data-nx-item="bar"></div>
  </div></template>
  <p data-nx-field="message" class="hidden"></p>
</section>
<section data-nx-chain="packages">
  <p data-nx-field="message" class="hidden"></p>
  <div data-nx-package-card="1000">
    <div data-nx-slot="action">
      <button type="button" data-nx-action="buy" data-nx-package="1000"></button>
    </div>
  </div>
  <div data-nx-package-card="100">
    <div data-nx-slot="action">
      <button type="button" data-nx-action="buy" data-nx-package="100"></button>
    </div>
  </div>
</section>`;

// Mirrors resources/views/income/chain.blade.php.
const incomePage = (contractKey) => `
<div data-nx-chain="income" data-nx-contract="${contractKey}">
  <span data-nx-field="credited-hero"></span><p data-nx-field="status" class="hidden"></p>
  <span data-nx-field="credited"></span><span data-nx-field="paid"></span><span data-nx-field="pending"></span>
  <table><tbody data-nx-field="credits"></tbody></table><p data-nx-field="credits-empty"></p>
  <table><tbody data-nx-field="payouts"></tbody></table><p data-nx-field="payouts-empty"></p>
  <p data-nx-field="message" class="hidden"></p>
</div>`;

// Mirrors resources/views/profit-sharing-slot-chain.blade.php.
const SLOT_PAGE = `
<div data-nx-chain="slot">
  <span data-nx-field="remaining"></span>
  <section data-nx-field="buy-panel" class="hidden"><button type="button" data-nx-action="buy"></button></section>
  <section data-nx-field="slot-panel" class="hidden">
    <span data-nx-field="slot-id"></span><span data-nx-field="slot-status"></span><span data-nx-field="purchased"></span>
    <span data-nx-field="days"></span><span data-nx-field="profit"></span><span data-nx-field="total"></span>
    <span data-nx-field="deduction"></span><span data-nx-field="payout"></span>
    <button type="button" data-nx-action="withdraw"></button>
  </section>
  <p data-nx-field="message" class="hidden"></p>
</div>`;

// The deployer (account 0) owns every contract.
async function owner() {
    const { Contract } = await import('ethers');
    const { default: abis } = await import('../../resources/js/nexgrow/abis.json', { with: { type: 'json' } });
    const signer = await new JsonRpcProvider(RPC).getSigner(0);

    return (name) => new Contract(
        deployment.contracts[name === 'token' ? 'paymentToken' : name], abis[name], signer,
    );
}

const rows = (root, name) => [...root.querySelectorAll(`[data-nx-field="${name}"] tr`)]
    .map((tr) => [...tr.querySelectorAll('td')].map((td) => td.textContent));

const text = (root, name) => root.querySelector(`[data-nx-field="${name}"]`).textContent;

// Renders the page for a user and starts the widgets.
async function openPage({
    wallet, connected = wallet, sponsorWallet = null, sponsorName = null, confirm = true, html = PAGE,
}) {
    const config = {
        mode: 'chain',
        chainId: 31337,
        rpcUrl: RPC,
        contracts: deployment.contracts,
        startBlock: deployment.startBlock,
        user: { userId: 'NEX00009', wallet, sponsorWallet, sponsorName },
        routes: {},
    };

    const dom = new JSDOM(
        `<!DOCTYPE html><body><script type="application/json" id="nx-chain-config">${JSON.stringify(config)}</script>${html}</body>`,
    );

    dom.window.ethereum = walletFor(connected);
    dom.window.confirm = () => confirm;
    globalThis.window = dom.window;
    globalThis.document = dom.window.document;
    globalThis.CustomEvent = dom.window.CustomEvent;

    modules.chain.resetConfig();
    await modules.index.init();

    const doc = dom.window.document;
    const widget = (name) => doc.querySelector(`[data-nx-chain="${name}"]`);

    return {
        sponsor: widget('sponsor'),
        nfts: widget('my-nfts'),
        packages: widget('packages'),
        income: widget('income'),
        slot: widget('slot'),
        async click(root, selector) {
            const button = root.querySelector(selector);
            button.click();
            await until(() => !button.disabled, `${selector} to finish`);
        },
    };
}

before(async () => {
    try {
        await rpc('eth_chainId');
    } catch {
        node = spawn('npx hardhat node', { cwd: BLOCKCHAIN, shell: true });
        await new Promise((resolve, reject) => {
            node.stdout.on('data', (d) => { if (String(d).includes('Started HTTP')) resolve(); });
            node.on('exit', (code) => reject(new Error(`hardhat node exited (${code})`)));
        });
    }

    execSync('npx hardhat run scripts/deployNexgrow.ts --network localhost', { cwd: BLOCKCHAIN, stdio: 'ignore' });
    deployment = JSON.parse(readFileSync(new URL('deployments/31337.json', BLOCKCHAIN), 'utf8'));
    accounts = (await rpc('eth_accounts')).map((a) => a.toLowerCase());

    // The deployer (account 0) bought NFT #0 during deployment, so it can
    // sponsor. Give the test users some NXD.
    const provider = new JsonRpcProvider(RPC);
    const deployer = await provider.getSigner(0);
    const { Contract } = await import('ethers');
    const token = new Contract(deployment.contracts.paymentToken, ['function transfer(address,uint256) returns (bool)'], deployer);

    for (const a of accounts.slice(1, 5)) {
        await (await token.transfer(a, parseUnits('10000', 18))).wait();
    }

    modules = {
        chain: await import('../../resources/js/nexgrow/chain.js'),
        index: await import('../../resources/js/nexgrow/index.js'),
    };
});

after(() => {
    if (!node) return;

    try {
        if (process.platform === 'win32') {
            execSync(`taskkill /pid ${node.pid} /T /F`, { stdio: 'ignore' });
        } else {
            node.kill();
        }
    } catch {}
});

describe('NFT page widgets', () => {
    test('a referred user buys; sponsor is set and other packages lock', async () => {
        const [sponsor, user] = accounts;
        const page = await openPage({ wallet: user, sponsorWallet: sponsor, sponsorName: 'Root' });

        assert.ok(visible(page.nfts, 'empty'));

        await page.click(page.packages, '[data-nx-package="1000"]');
        assert.match(message(page.packages), /You bought the 1000 NXD NFT/);

        await until(() => page.nfts.querySelectorAll('[data-nx-item="package"]').length === 1, 'NFT listed');
        assert.equal(page.nfts.querySelector('[data-nx-item="package"]').textContent, '1000');
        assert.equal(page.nfts.querySelector('[data-nx-item="status"]').textContent, 'Active');
        assert.equal(page.nfts.querySelector('[data-nx-item="cap"]').textContent, '4,000.00');
        assert.equal(page.nfts.querySelector('[data-nx-field="balance"]').textContent, '9,000.00');

        // Other packages lock, and the bought one is marked active:
        await until(() => /Locked/.test(page.packages.querySelector('[data-nx-package-card="100"]').textContent), 'packages locked');
        const otherCard = page.packages.querySelector('[data-nx-package-card="100"]');
        assert.ok(otherCard.classList.contains('opacity-50'));
        assert.equal(otherCard.querySelector('button'), null);
        assert.match(page.packages.querySelector('[data-nx-package-card="1000"]').textContent, /Your active package/);
    });

    test('the wrong wallet account is refused', async () => {
        // A wallet without an NFT, so the buy buttons are shown.
        const [, , , user, , other] = accounts;
        const page = await openPage({ wallet: user, connected: other });

        await page.click(page.packages, '[data-nx-package="100"]');
        assert.match(message(page.packages), /Switch your wallet to the linked account/);
    });
});

describe('Income page widget', () => {
    test('the sponsor sees the referral credit from their direct and its payout', async () => {
        const [sponsor, buyer] = accounts;
        const c = await owner();
        const source = '0x' + '22'.repeat(32);

        // Fund NFTIncome and ReferralIncome from a test revenue source.
        await (await c('rewardPool').addRevenueSource(source, 'Widget test source')).wait();
        await (await c('rewardPool').setDepositor(sponsor, source, true)).wait();
        await (await c('rewardPool').setAllocation(deployment.contracts.nftIncome, source, 5000n)).wait();
        await (await c('rewardPool').setAllocation(deployment.contracts.referralIncome, source, 5000n)).wait();
        await (await c('rewardPool').setSpender(deployment.contracts.referralIncome, true)).wait();
        await (await c('token').approve(deployment.contracts.rewardPool, parseUnits('1000', 18))).wait();
        await (await c('rewardPool').deposit(parseUnits('1000', 18), source)).wait();
        await (await c('nftIncome').setPayoutsEnabled(true)).wait();
        await (await c('referralIncome').setPayoutsEnabled(true)).wait();

        // 10 days of the buyer's 1000 NFT (#1): 7.00 NXD, 5% to the sponsor.
        await rpc('evm_increaseTime', [10 * 86400]);
        await rpc('evm_mine');
        await (await c('nftIncome').startRound()).wait();
        await (await c('nftIncome').claim([1n])).wait();
        await (await c('referralIncome').processQueue(100n)).wait();
        await (await c('referralIncome').startRound()).wait();
        await (await c('referralIncome').claim([sponsor])).wait();

        const page = await openPage({ wallet: sponsor, html: incomePage('referralIncome') });

        assert.equal(text(page.income, 'credited'), '0.35');
        assert.equal(text(page.income, 'credited-hero'), '0.35');
        assert.equal(text(page.income, 'paid'), '0.35');
        assert.equal(text(page.income, 'pending'), '0.00');
        assert.match(text(page.income, 'status'), /You have 1 direct referrals on-chain/);

        const [credit] = rows(page.income, 'credits');
        const from = getAddress(buyer);
        assert.deepEqual(credit.slice(1), ['Level 1', `${from.slice(0, 6)}…${from.slice(-4)}`, '7.00', '0.35']);
        assert.deepEqual(rows(page.income, 'payouts').map((r) => r.slice(1)), [['#1', '0.35', '0.00']]);
        assert.ok(visible(page.income, 'status'));
    });
});

describe('Profit-Sharing Slot widget', () => {
    test('a wallet buys a slot, sees deposited profit and the early deduction, then withdraws', async () => {
        const [depositor, , user] = accounts;
        const c = await owner();
        const page = await openPage({ wallet: user, html: SLOT_PAGE });

        assert.equal(text(page.slot, 'remaining'), '100');
        assert.ok(visible(page.slot, 'buy-panel'));

        await page.click(page.slot, '[data-nx-action="buy"]');
        assert.match(message(page.slot), /You bought a Profit-Sharing Slot/);
        await until(() => visible(page.slot, 'slot-panel'), 'slot panel');
        assert.equal(text(page.slot, 'remaining'), '99');

        await (await c('profitSharingSlot').setProfitDepositor(depositor, true)).wait();
        await (await c('token').approve(deployment.contracts.profitSharingSlot, parseUnits('500', 18))).wait();
        await (await c('profitSharingSlot').depositProfit(parseUnits('500', 18))).wait();

        const fresh = await openPage({ wallet: user, html: SLOT_PAGE });

        assert.equal(text(fresh.slot, 'profit'), '500.00');
        assert.equal(text(fresh.slot, 'total'), '3,500.00');
        assert.equal(text(fresh.slot, 'deduction'), '50%');
        assert.equal(text(fresh.slot, 'payout'), '1,750.00');
        assert.equal(text(fresh.slot, 'slot-status'), 'Earning');

        await fresh.click(fresh.slot, '[data-nx-action="withdraw"]');
        assert.match(message(fresh.slot), /Slot withdrawn: 1,750.00 NXD sent to your wallet/);
        await until(() => text(fresh.slot, 'slot-status') === 'Withdrawn', 'withdrawn state');
    });
});

// Mirrors resources/views/partials/chain-dashboard.blade.php.
const DASHBOARD = `
<section data-nx-chain="income-summary">
  <span data-nx-field="balance"></span>
  <table><tbody>
    ${['nftIncome', 'referralIncome', 'teamBuildingIncome', 'bonusBooster', 'teamPerformanceBonus',
        'monthlyLoyaltyBonus', 'royaltyBonus', 'teamDevelopmentBonus'].map((key) => `
      <tr data-nx-row="${key}"><td data-nx-cell="earned"></td><td data-nx-cell="paid"></td><td data-nx-cell="pending"></td></tr>`).join('')}
  </tbody><tfoot><tr>
    <td data-nx-field="earned"></td><td data-nx-field="paid"></td><td data-nx-field="pending"></td>
  </tr></tfoot></table>
  <p data-nx-field="message" class="hidden"></p>
</section>`;

describe('Dashboard income summary widget', () => {
    test('it totals the daily bonus and every income module for the wallet', async () => {
        const [sponsor] = accounts;
        const c = await owner();
        const { formatToken } = modules.chain;
        await openPage({ wallet: sponsor, html: DASHBOARD });
        const root = globalThis.document.querySelector('[data-nx-chain="income-summary"]');
        const cell = (row, name) => root.querySelector(`[data-nx-row="${row}"] [data-nx-cell="${name}"]`).textContent;

        // The sponsor owns NFT #0 (100 package, never claimed) and earned 0.35 referral, paid out.
        const nftEarned = await c('nftIncome').entitledOf(0n);
        const referral = parseUnits('0.35', 18);

        assert.equal(cell('nftIncome', 'earned'), formatToken(nftEarned));
        assert.equal(cell('nftIncome', 'paid'), '0.00');
        assert.equal(cell('referralIncome', 'earned'), '0.35');
        assert.equal(cell('referralIncome', 'pending'), '0.00');
        assert.equal(cell('royaltyBonus', 'earned'), '0.00');

        assert.equal(text(root, 'earned'), formatToken(nftEarned + referral));
        assert.equal(text(root, 'paid'), '0.35');
        assert.equal(text(root, 'pending'), formatToken(nftEarned));
    });
});

// Mirrors resources/views/admin/owner/index.blade.php
// (pinned from the PHP side by tests/Feature/AdminPanelTest.php).
const adminRow = (key, label, items, actions, extra = '') => `
  <div data-nx-contract="${key}" data-nx-label="${label}" ${extra}>
    ${items.map((i) => `<span data-nx-item="${i}"></span>`).join('')}
    ${actions.map((a) => `<button type="button" data-nx-action="${a}" class="hidden"></button>`).join('')}
  </div>`;

const MONTH_ITEMS = ['month', 'pool', 'last-month', 'last-pool', 'registered', 'registration'];

const ADMIN_PAGE = `
<section data-nx-chain="admin-owner">
  <span data-nx-field="owner"></span>
  <p data-nx-field="message" class="hidden"></p>
</section>
<section data-nx-chain="admin-pause">
  <button type="button" data-nx-action="pause-all"></button>
  ${adminRow('profitSharingSlot', 'Profit-Sharing Slot', ['state'], ['pause', 'unpause'])}
  ${adminRow('liquidityPool', 'Liquidity Pool', ['state'], ['pause', 'unpause'])}
  <p data-nx-field="message" class="hidden"></p>
</section>
<section data-nx-chain="admin-payouts">
  ${['nftIncome', 'teamDevelopmentBonus'].map((key) => adminRow(key, key,
        ['enabled', 'funding', 'budget', 'unpaid', 'round'], ['start-round', 'enable'])).join('')}
  <p data-nx-field="message" class="hidden"></p>
</section>
<section data-nx-chain="admin-treasury">
  <span data-nx-field="balance"></span><span data-nx-field="withdrawn"></span>
  <input data-nx-input="treasury-amount"><input data-nx-input="treasury-to">
  <button type="button" data-nx-action="withdraw"></button>
  <table><tbody data-nx-field="withdrawals"></tbody></table><p data-nx-field="withdrawals-empty" class="hidden"></p>
  <p data-nx-field="message" class="hidden"></p>
</section>
<section data-nx-chain="admin-funding">
  <span data-nx-field="deposited"></span><span data-nx-field="released"></span>
  <span data-nx-field="budgeted"></span><span data-nx-field="unassigned"></span>
  <p data-nx-field="no-sources" class="hidden"></p>
  <input data-nx-input="source-name"><input data-nx-input="source-description">
  <button type="button" data-nx-action="add-source"></button>
  <div data-nx-field="source-panel" class="hidden">
    <select data-nx-input="source"></select>
    <span data-nx-field="source-id"></span><span data-nx-field="source-deposited"></span>
    <span data-nx-field="source-allocated"></span><span data-nx-field="source-free"></span>
    <span data-nx-field="depositors"></span>
    <input data-nx-input="pool-depositor">
    <button type="button" data-nx-action="add-depositor"></button><button type="button" data-nx-action="remove-depositor"></button>
    ${['nftIncome', 'teamDevelopmentBonus'].map((key) => `
      <div data-nx-contract="${key}" data-nx-label="${key}">
        <span data-nx-item="share"></span><span data-nx-item="budget"></span>
        <input data-nx-item="share-input"><button type="button" data-nx-action="save-share"></button>
      </div>`).join('')}
    <input data-nx-input="pool-amount"><button type="button" data-nx-action="deposit"></button>
  </div>
  <p data-nx-field="message" class="hidden"></p>
</section>
<section data-nx-chain="admin-slot">
  <span data-nx-field="sold"></span><span data-nx-field="remaining"></span><span data-nx-field="principal"></span>
  <span data-nx-field="assigned"></span><span data-nx-field="undistributed"></span><span data-nx-field="depositors"></span>
  <input data-nx-input="amount"><button type="button" data-nx-action="deposit"></button>
  <input data-nx-input="depositor"><button type="button" data-nx-action="add-depositor"></button>
  <button type="button" data-nx-action="remove-depositor"></button>
  <input data-nx-input="recover-to"><button type="button" data-nx-action="recover"></button>
  <p data-nx-field="message" class="hidden"></p>
</section>
<section data-nx-chain="admin-development">
  <span data-nx-field="extra"></span><span data-nx-field="window"></span>
  <input data-nx-input="extra-percent"><button type="button" data-nx-action="save-extra"></button>
  <input data-nx-input="window-months"><button type="button" data-nx-action="save-window"></button>
  ${adminRow('bonusBooster', 'Booster', ['eligible'], ['make-eligible', 'remove-eligible'])}
  <p data-nx-field="message" class="hidden"></p>
</section>
<section data-nx-chain="admin-monthly">
  ${adminRow('monthlyLoyaltyBonus', 'Loyalty', MONTH_ITEMS, [], 'data-nx-count="qualifierCount"')}
  ${adminRow('royaltyBonus', 'Royalty', MONTH_ITEMS, [], 'data-nx-count="eligibleCount"')}
  <p data-nx-field="message" class="hidden"></p>
</section>`;

describe('Owner Actions page widgets', () => {
    async function openAdmin(connected) {
        const page = await openPage({ wallet: null, connected, html: ADMIN_PAGE });
        const widget = (name) => globalThis.document.querySelector(`[data-nx-chain="admin-${name}"]`);
        const row = (name, key) => widget(name).querySelector(`[data-nx-contract="${key}"]`);
        const itemText = (name, key, i) => row(name, key).querySelector(`[data-nx-item="${i}"]`).textContent;
        const fill = (name, input, value) => { widget(name).querySelector(`[data-nx-input="${input}"]`).value = value; };

        return { ...page, widget, row, itemText, fill };
    }

    test('the owner pauses and resumes a contract; another wallet is refused', async () => {
        const [deployer, , , , , other] = accounts;
        const page = await openAdmin(deployer);
        const pause = page.widget('pause');

        assert.equal(text(page.widget('owner'), 'owner').toLowerCase(), deployer);
        assert.equal(page.itemText('pause', 'profitSharingSlot', 'state'), 'Running');

        await page.click(page.row('pause', 'profitSharingSlot'), '[data-nx-action="pause"]');
        assert.match(message(pause), /Profit-Sharing Slot paused/);
        await until(() => page.itemText('pause', 'profitSharingSlot', 'state') === 'Paused', 'paused state');
        assert.ok(!page.row('pause', 'profitSharingSlot').querySelector('[data-nx-action="unpause"]').classList.contains('hidden'));

        await page.click(page.row('pause', 'profitSharingSlot'), '[data-nx-action="unpause"]');
        await until(() => page.itemText('pause', 'profitSharingSlot', 'state') === 'Running', 'running state');

        const stranger = await openAdmin(other);
        await stranger.click(stranger.row('pause', 'liquidityPool'), '[data-nx-action="pause"]');
        assert.match(message(stranger.widget('pause')), /Only the contract owner/);
        assert.equal(await (await owner())('liquidityPool').paused(), false);
    });

    test('payouts cannot be turned on before funding is set up', async () => {
        const page = await openAdmin(accounts[0]);

        assert.equal(page.itemText('payouts', 'teamDevelopmentBonus', 'funding'), 'Not set up');
        assert.equal(page.itemText('payouts', 'teamDevelopmentBonus', 'enabled'), 'Off');

        await page.click(page.row('payouts', 'teamDevelopmentBonus'), '[data-nx-action="enable"]');
        assert.match(message(page.widget('payouts')), /Funding not configured/);
    });

    test('the owner adds a depositor, deposits slot profit and recovers what no slot took', async () => {
        const [deployer, , , , , , recipient, depositor] = accounts;
        const c = await owner();
        const slots = c('profitSharingSlot');
        await (await c('token').transfer(depositor, parseUnits('100', 18))).wait();

        let page = await openAdmin(depositor);
        let slot = page.widget('slot');
        page.fill('slot', 'amount', '50');
        await page.click(slot, '[data-nx-action="deposit"]');
        assert.match(message(slot), /is not a profit depositor/);

        page = await openAdmin(deployer);
        slot = page.widget('slot');
        page.fill('slot', 'depositor', depositor);
        await page.click(slot, '[data-nx-action="add-depositor"]');
        await until(() => text(slot, 'depositors').toLowerCase().includes(depositor), 'depositor listed');

        const before = (await slots.totalProfitAssigned()) + (await slots.undistributedProfit());
        page = await openAdmin(depositor);
        slot = page.widget('slot');
        page.fill('slot', 'amount', '50');
        await page.click(slot, '[data-nx-action="deposit"]');
        assert.match(message(slot), /50\.00 NXD profit deposited/);
        const after = (await slots.totalProfitAssigned()) + (await slots.undistributedProfit());
        assert.equal(after - before, parseUnits('50', 18));

        const undistributed = await slots.undistributedProfit();
        page = await openAdmin(deployer);
        slot = page.widget('slot');
        page.fill('slot', 'recover-to', recipient);
        await page.click(slot, '[data-nx-action="recover"]');

        if (undistributed > 0n) {
            assert.equal(await c('token').balanceOf(recipient), undistributed);
            await until(() => text(slot, 'undistributed') === '0.00', 'undistributed cleared');
        } else {
            assert.match(message(slot), /no undistributed profit/);
        }
    });

    test('the owner changes the Team Development settings', async () => {
        const c = await owner();
        const page = await openAdmin(accounts[0]);
        const dev = page.widget('development');

        assert.equal(text(dev, 'extra'), '5%');
        assert.equal(text(dev, 'window'), '12 months');

        page.fill('development', 'extra-percent', '7.5');
        await page.click(dev, '[data-nx-action="save-extra"]');
        await until(() => text(dev, 'extra') === '7.5%', 'extra updated');
        assert.equal(await c('teamDevelopmentBonus').extraBps(), 750n);

        page.fill('development', 'window-months', '0');
        await page.click(dev, '[data-nx-action="save-window"]');
        assert.match(message(dev), /from 1 to 120/);

        const eligible = page.itemText('development', 'bonusBooster', 'eligible');
        const action = eligible === 'Gets the extra' ? 'remove-eligible' : 'make-eligible';
        await page.click(page.row('development', 'bonusBooster'), `[data-nx-action="${action}"]`);
        await until(() => page.itemText('development', 'bonusBooster', 'eligible') !== eligible, 'eligibility toggled');

        // Put the defaults back.
        await (await c('teamDevelopmentBonus').setExtraBps(500n)).wait();
        await (await c('teamDevelopmentBonus').setEligibleSource(deployment.contracts.bonusBooster, eligible === 'Gets the extra')).wait();
    });

    test('the owner sets up a revenue source, its depositor and shares; a deposit fills the budgets', async () => {
        const [deployer, , , , , , , , depositor] = accounts;
        const c = await owner();
        const pool = c('rewardPool');
        await (await c('token').transfer(depositor, parseUnits('100', 18))).wait();
        const budget = (key) => pool.budgetOf(deployment.contracts[key]);
        const [nftBefore, devBefore] = [await budget('nftIncome'), await budget('teamDevelopmentBonus')];

        let page = await openAdmin(deployer);
        let funding = page.widget('funding');
        const shareRow = (key) => funding.querySelector(`[data-nx-contract="${key}"]`);
        const setShare = async (key, value) => {
            shareRow(key).querySelector('[data-nx-item="share-input"]').value = value;
            await page.click(shareRow(key), '[data-nx-action="save-share"]');
        };

        page.fill('funding', 'source-name', 'bad name');
        await page.click(funding, '[data-nx-action="add-source"]');
        assert.match(message(funding), /short name/);

        page.fill('funding', 'source-name', 'test_sales');
        page.fill('funding', 'source-description', 'Widget test, see the project report');
        await page.click(funding, '[data-nx-action="add-source"]');
        assert.match(message(funding), /TEST_SALES added/);
        const { id } = await import('ethers');
        await until(() => funding.querySelector('[data-nx-input="source"]').value === id('TEST_SALES'), 'new source selected');
        assert.equal(await pool.sourceDescription(id('TEST_SALES')), 'TEST_SALES: Widget test, see the project report');

        page.fill('funding', 'pool-depositor', depositor);
        await page.click(funding, '[data-nx-action="add-depositor"]');
        await until(() => text(funding, 'depositors').toLowerCase().includes(depositor), 'depositor listed');

        await setShare('nftIncome', '30');
        await until(() => shareRow('nftIncome').querySelector('[data-nx-item="share"]').textContent === '30%', 'share saved');
        await setShare('teamDevelopmentBonus', '80');
        assert.match(message(funding), /Only 70% is left/);
        await setShare('teamDevelopmentBonus', '20');
        await until(() => text(funding, 'source-allocated') === '50%', 'total shared');
        assert.equal(text(funding, 'source-free'), '50%');

        page = await openAdmin(depositor);
        funding = page.widget('funding');
        funding.querySelector('[data-nx-input="source"]').value = id('TEST_SALES');
        page.fill('funding', 'pool-amount', '100');
        await page.click(funding, '[data-nx-action="deposit"]');
        assert.match(message(funding), /100\.00 NXD deposited/);

        assert.equal(await budget('nftIncome') - nftBefore, parseUnits('30', 18));
        assert.equal(await budget('teamDevelopmentBonus') - devBefore, parseUnits('20', 18));
        await until(() => text(funding, 'source-deposited') === '100.00', 'source total shown');

        // The Team Development payouts can now be turned on.
        page = await openAdmin(deployer);
        assert.equal(page.itemText('payouts', 'teamDevelopmentBonus', 'funding'), 'Set up');
    });

    test('the owner sends NXD out of the Treasury; others cannot', async () => {
        const [deployer, , , , , other, , , , recipient] = accounts;
        const c = await owner();
        const { formatToken } = modules.chain;
        const balance = await c('treasury').balance();

        let page = await openAdmin(other);
        let treasury = page.widget('treasury');
        assert.equal(text(treasury, 'balance'), formatToken(balance));
        assert.ok(visible(treasury, 'withdrawals-empty'));
        page.fill('treasury', 'treasury-amount', '10');
        page.fill('treasury', 'treasury-to', recipient);
        await page.click(treasury, '[data-nx-action="withdraw"]');
        assert.match(message(treasury), /Only the contract owner/);

        page = await openAdmin(deployer);
        treasury = page.widget('treasury');
        page.fill('treasury', 'treasury-amount', '999999999');
        page.fill('treasury', 'treasury-to', recipient);
        await page.click(treasury, '[data-nx-action="withdraw"]');
        assert.match(message(treasury), /The Treasury holds only/);

        page.fill('treasury', 'treasury-amount', '10');
        await page.click(treasury, '[data-nx-action="withdraw"]');
        assert.match(message(treasury), /10\.00 NXD sent/);
        assert.equal(await c('token').balanceOf(recipient), parseUnits('10', 18));
        await until(() => text(treasury, 'withdrawn') === '10.00', 'withdrawn total');
        assert.deepEqual(rows(treasury, 'withdrawals')[0].slice(1), [getAddress(recipient), '10.00']);
    });

    test('the monthly pools show this month and last month', async () => {
        const page = await openAdmin(accounts[0]);

        for (const key of ['monthlyLoyaltyBonus', 'royaltyBonus']) {
            assert.match(page.itemText('monthly', key, 'month'), /\d{4}/);
            assert.match(page.itemText('monthly', key, 'last-month'), /\d{4}/);
            assert.match(page.itemText('monthly', key, 'registered'), /^\d+$/);
            assert.match(page.itemText('monthly', key, 'registration'), /^(Open until|Closed, ready to settle)/);
        }
    });
});
