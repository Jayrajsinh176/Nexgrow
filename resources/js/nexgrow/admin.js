// Owner Actions page widgets (admin): emergency pause, payment rounds,
// Profit-Sharing Slot profit and Team Development settings. Every action is
// signed by the connected wallet, which must own the contract (or be a
// profit depositor, for deposits). Markup: resources/views/admin/owner/index.blade.php.
import { getAddress, id, isAddress, parseUnits } from 'ethers';
import {
    blockDate, config, connect, contract, ensureNetwork, errorMessage, formatToken, isDeployed, queryEvents, reader, send, shortAddress,
} from './chain.js';
import {
    chainUpdated, onAction, onChainUpdated, register, setMessage, setText, show,
} from './ui.js';

// ---- Helpers ----

const rowsOf = (root) => [...root.querySelectorAll('[data-nx-contract]')];
const rowOf = (button) => button.closest('[data-nx-contract]');
const item = (row, name) => row.querySelector(`[data-nx-item="${name}"]`);
const progress = (root) => (text) => setMessage(root, text);

function setItem(row, name, text) {
    const el = item(row, name);
    if (el) el.textContent = text;
}

// Shows or hides a row's buttons.
function showActions(row, name, visible) {
    row.querySelectorAll(`[data-nx-action="${name}"]`).forEach((el) => el.classList.toggle('hidden', !visible));
}

function input(root, name) {
    return root.querySelector(`[data-nx-input="${name}"]`).value.trim();
}

function addressInput(root, name) {
    const value = input(root, name);

    if (!isAddress(value)) throw new Error('Enter a valid wallet address (0x…).');

    return getAddress(value);
}

function amountInput(root, name) {
    const value = input(root, name);

    if (!/^\d+(\.\d{1,18})?$/.test(value) || Number(value) <= 0) throw new Error('Enter an amount above 0.');

    return parseUnits(value, 18);
}

// Connects on the right network and checks the wallet owns the contract.
async function connectOwner(name) {
    await ensureNetwork();
    const session = await connect();
    const owner = await contract(name).owner();

    if (owner.toLowerCase() !== session.address.toLowerCase()) {
        throw new Error(`Only the contract owner ${shortAddress(owner)} can do this. Your wallet is ${shortAddress(session.address)}.`);
    }

    return session;
}

async function ownerSend(root, name, label, call) {
    const { signer } = await connectOwner(name);

    await send(label, call(contract(name, signer)), progress(root));
    chainUpdated();
}

const percent = (bps) => `${Number(bps) / 100}%`;

// "12.5" -> 1250 basis points.
function percentInput(value) {
    if (!/^\d+(\.\d{1,2})?$/.test(value) || Number(value) > 100) throw new Error('Enter a percentage from 0 to 100.');

    return BigInt(Math.round(Number(value) * 100));
}

const monthLabel = (day) => new Date(Number(day) * 86_400_000)
    .toLocaleDateString(undefined, { month: 'long', year: 'numeric', timeZone: 'UTC' });

const dayLabel = (day) => new Date(Number(day) * 86_400_000)
    .toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' });

// ---- Widgets ----

// Shows the owner address; each action checks the connected wallet itself.
async function ownerWallet(root) {
    if (isDeployed()) {
        setText(root, 'owner', getAddress(await contract('manager').owner()));
    }
}

async function pauseControls(root) {
    async function render() {
        if (!isDeployed()) return;

        await Promise.all(rowsOf(root).map(async (row) => {
            const paused = await contract(row.dataset.nxContract).paused();
            const state = item(row, 'state');

            row.dataset.nxPaused = paused ? '1' : '';
            state.textContent = paused ? 'Paused' : 'Running';
            state.classList.toggle('text-rose-300', paused);
            state.classList.toggle('text-emerald-300', !paused);
            showActions(row, 'pause', !paused);
            showActions(row, 'unpause', paused);
        }));
    }

    onAction(root, 'pause', async (button) => {
        const row = rowOf(button);

        await ownerSend(root, row.dataset.nxContract, `Pause ${row.dataset.nxLabel}`, (c) => c.pause());
        setMessage(root, `${row.dataset.nxLabel} paused.`, 'success');
    });

    onAction(root, 'unpause', async (button) => {
        const row = rowOf(button);

        await ownerSend(root, row.dataset.nxContract, `Resume ${row.dataset.nxLabel}`, (c) => c.unpause());
        setMessage(root, `${row.dataset.nxLabel} resumed.`, 'success');
    });

    onAction(root, 'pause-all', async () => {
        await render();
        const running = rowsOf(root).filter((row) => !row.dataset.nxPaused);

        if (running.length === 0) {
            setMessage(root, 'Everything is already paused.', 'info');
            return;
        }

        if (!window.confirm(`Pause ${running.length} contracts? Your wallet asks you to confirm one transaction for each.`)) return;

        for (const row of running) {
            await ownerSend(root, row.dataset.nxContract, `Pause ${row.dataset.nxLabel}`, (c) => c.pause());
        }

        setMessage(root, `Paused ${running.length} contracts.`, 'success');
    });

    onChainUpdated(render);
    await render();
}

async function payoutControls(root) {
    async function render() {
        if (!isDeployed()) return;

        const pool = contract('rewardPool');

        await Promise.all(rowsOf(root).map(async (row) => {
            const name = row.dataset.nxContract;
            const c = contract(name);
            const address = config().contracts[name];
            const [enabled, funded, budget, unpaid, round] = await Promise.all([
                c.payoutsEnabled(), pool.hasFundingConfigured(address), pool.budgetOf(address), c.totalUnpaid(), c.currentRound(),
            ]);

            setItem(row, 'enabled', enabled ? 'On' : 'Off');
            setItem(row, 'funding', funded ? 'Set up' : 'Not set up');
            setItem(row, 'budget', formatToken(budget));
            setItem(row, 'unpaid', formatToken(unpaid));
            setItem(row, 'round', round.toString());
            showActions(row, 'enable', !enabled);
            showActions(row, 'start-round', enabled);
        }));
    }

    onAction(root, 'enable', async (button) => {
        const row = rowOf(button);

        await ownerSend(root, row.dataset.nxContract, `Turn on ${row.dataset.nxLabel} payouts`, (c) => c.setPayoutsEnabled(true));
        setMessage(root, `${row.dataset.nxLabel} payouts turned on.`, 'success');
    });

    onAction(root, 'start-round', async (button) => {
        const row = rowOf(button);
        const name = row.dataset.nxContract;
        const [budget, unpaid] = await Promise.all([
            contract('rewardPool').budgetOf(config().contracts[name]), contract(name).totalUnpaid(),
        ]);

        if (unpaid === 0n) throw new Error(`Nothing is owed for ${row.dataset.nxLabel}.`);
        if (budget === 0n) throw new Error(`${row.dataset.nxLabel} has no funded budget in the Reward Pool.`);

        const share = budget >= unpaid ? 'everything owed' : `${(Number((budget * 10_000n) / unpaid) / 100).toFixed(2)}% of what is owed`;

        if (!window.confirm(`Start a payment round for ${row.dataset.nxLabel}? Budget ${formatToken(budget)} NXD pays ${share} (${formatToken(unpaid)} NXD).`)) return;

        await ownerSend(root, name, `Start ${row.dataset.nxLabel} round`, (c) => c.startRound());
        setMessage(root, `${row.dataset.nxLabel} round started. Members can claim now, or run the keeper's claims task.`, 'success');
    });

    onChainUpdated(render);
    await render();
}

// Treasury: NFT sale proceeds. Only the owner can send them out, e.g. to a
// Reward Pool depositor wallet.
async function treasuryControls(root) {
    async function render() {
        if (!isDeployed()) return;

        const treasury = contract('treasury');
        const [balance, events] = await Promise.all([
            treasury.balance(), queryEvents(treasury, treasury.filters.Withdrawn()),
        ]);
        const list = root.querySelector('[data-nx-field="withdrawals"]');

        setText(root, 'balance', formatToken(balance));
        setText(root, 'withdrawn', formatToken(events.reduce((sum, e) => sum + e.args.amount, 0n)));
        show(root, 'withdrawals-empty', events.length === 0);

        list.innerHTML = '';
        for (const e of events.slice(-10).reverse()) {
            const tr = document.createElement('tr');
            for (const value of [(await blockDate(e.blockNumber)).toLocaleString(), e.args.to, formatToken(e.args.amount)]) {
                const td = document.createElement('td');
                td.textContent = value;
                tr.append(td);
            }
            list.append(tr);
        }
    }

    onAction(root, 'withdraw', async () => {
        const amount = amountInput(root, 'treasury-amount');
        const to = addressInput(root, 'treasury-to');
        const balance = await contract('treasury').balance();

        if (amount > balance) throw new Error(`The Treasury holds only ${formatToken(balance)} NXD.`);
        if (!window.confirm(`Send ${formatToken(amount)} NXD from the Treasury to ${to}? This cannot be undone.`)) return;

        await ownerSend(root, 'treasury', 'Treasury withdraw', (c) => c.withdraw(to, amount));
        setMessage(root, `${formatToken(amount)} NXD sent to ${shortAddress(to)}.`, 'success');
    });

    onChainUpdated(render);
    await render();
}

// Reward Pool revenue: sources, who may deposit for each, what share of each
// source's deposits goes to every income module's budget, and deposits.
async function fundingControls(root) {
    const POOL = 'rewardPool';
    const select = root.querySelector('[data-nx-input="source"]');
    let preferred = null;

    async function render() {
        if (!isDeployed()) return;

        const pool = contract(POOL);
        const ids = await pool.getRevenueSources();
        const descriptions = await Promise.all(ids.map((s) => pool.sourceDescription(s)));
        const keep = preferred ?? select.value;

        select.innerHTML = '';
        ids.forEach((source, i) => {
            const option = document.createElement('option');
            option.value = source;
            option.textContent = descriptions[i];
            select.append(option);
        });
        if (ids.includes(keep)) select.value = keep;
        preferred = null;

        show(root, 'no-sources', ids.length === 0);
        show(root, 'source-panel', ids.length > 0);

        const rows = rowsOf(root);
        const [deposited, released, available, budgets] = await Promise.all([
            pool.totalDeposited(), pool.totalReleased(), pool.availableRevenue(),
            Promise.all(rows.map((row) => pool.budgetOf(config().contracts[row.dataset.nxContract]))),
        ]);
        const budgeted = budgets.reduce((sum, b) => sum + b, 0n);

        setText(root, 'deposited', formatToken(deposited));
        setText(root, 'released', formatToken(released));
        setText(root, 'budgeted', formatToken(budgeted));
        setText(root, 'unassigned', formatToken(available > budgeted ? available - budgeted : 0n));
        rows.forEach((row, i) => setItem(row, 'budget', formatToken(budgets[i])));

        if (ids.length > 0) await renderSource(select.value);
    }

    async function renderSource(source) {
        const pool = contract(POOL);
        const [deposited, allocated, events, shares] = await Promise.all([
            pool.depositedBySource(source), pool.totalAllocationBps(source),
            queryEvents(pool, pool.filters.DepositorUpdated(null, source)),
            Promise.all(rowsOf(root).map((row) => pool.allocationBps(config().contracts[row.dataset.nxContract], source))),
        ]);

        const depositors = new Map();
        events.forEach((e) => depositors.set(e.args.depositor, e.args.allowed));
        const allowed = [...depositors].filter(([, ok]) => ok).map(([address]) => address);

        setText(root, 'source-id', source);
        setText(root, 'source-deposited', formatToken(deposited));
        setText(root, 'source-allocated', percent(allocated));
        setText(root, 'source-free', percent(10_000n - allocated));
        setText(root, 'depositors', allowed.length ? allowed.join(', ') : 'None yet');
        rowsOf(root).forEach((row, i) => setItem(row, 'share', percent(shares[i])));
    }

    function selectedSource() {
        if (!select.value) throw new Error('Add a revenue source first.');

        return select.value;
    }

    select.addEventListener('change', () => {
        renderSource(select.value).catch((error) => setMessage(root, errorMessage(error), 'error'));
    });

    onAction(root, 'add-source', async () => {
        const name = input(root, 'source-name').toUpperCase();
        const description = input(root, 'source-description');

        if (!/^[A-Z0-9_]{3,32}$/.test(name)) throw new Error('Enter a short name of 3–32 letters, digits or _ (e.g. NFT_SALES).');
        if (!description) throw new Error('Enter what this revenue is and where it is documented.');

        const source = id(name);

        if (await contract(POOL).isRevenueSource(source)) throw new Error(`A source named ${name} already exists.`);

        preferred = source;
        await ownerSend(root, POOL, 'Add revenue source', (c) => c.addRevenueSource(source, `${name}: ${description}`));
        setMessage(root, `Revenue source ${name} added. Now set its depositor and shares.`, 'success');
    });

    for (const [action, allowed] of [['add-depositor', true], ['remove-depositor', false]]) {
        onAction(root, action, async () => {
            const source = selectedSource();
            const depositor = addressInput(root, 'pool-depositor');

            preferred = source;
            await ownerSend(root, POOL, allowed ? 'Add depositor' : 'Remove depositor', (c) => c.setDepositor(depositor, source, allowed));
            setMessage(root, `${shortAddress(depositor)} ${allowed ? 'can now deposit' : 'can no longer deposit'} for this source.`, 'success');
        });
    }

    onAction(root, 'save-share', async (button) => {
        const row = rowOf(button);
        const source = selectedSource();
        const bps = percentInput(row.querySelector('[data-nx-item="share-input"]').value.trim());
        const module = config().contracts[row.dataset.nxContract];
        const pool = contract(POOL);
        const [current, total] = await Promise.all([pool.allocationBps(module, source), pool.totalAllocationBps(source)]);

        if (total - current + bps > 10_000n) {
            throw new Error(`Only ${percent(10_000n - total + current)} is left to give ${row.dataset.nxLabel} for this source.`);
        }

        preferred = source;
        await ownerSend(root, POOL, `Save ${row.dataset.nxLabel} share`, (c) => c.setAllocation(module, source, bps));
        setMessage(root, `${row.dataset.nxLabel} now gets ${percent(bps)} of future deposits from this source.`, 'success');
    });

    onAction(root, 'deposit', async () => {
        const source = selectedSource();
        const amount = amountInput(root, 'pool-amount');
        await ensureNetwork();
        const { signer, address } = await connect();
        const pool = contract(POOL, signer);
        const token = contract('token', signer);
        const poolAddress = await pool.getAddress();

        if (!(await pool.isDepositor(address, source))) {
            throw new Error(`${shortAddress(address)} is not a depositor for this source. Add it above first.`);
        }
        if ((await token.balanceOf(address)) < amount) throw new Error('Your wallet does not hold that much NXD.');

        if ((await token.allowance(address, poolAddress)) < amount) {
            await send('Approve NXD', token.approve(poolAddress, amount), progress(root));
        }

        preferred = source;
        await send('Deposit revenue', pool.deposit(amount, source), progress(root));
        setMessage(root, `${formatToken(amount)} NXD deposited and shared into the income budgets.`, 'success');
        chainUpdated();
    });

    onChainUpdated(render);
    await render();
}

async function slotControls(root) {
    async function render() {
        if (!isDeployed()) return;

        const slots = contract('profitSharingSlot');
        const [sold, remaining, principal, assigned, undistributed, events] = await Promise.all([
            slots.slotCount(), slots.slotsRemaining(), slots.totalPrincipal(), slots.totalProfitAssigned(),
            slots.undistributedProfit(), queryEvents(slots, slots.filters.ProfitDepositorUpdated()),
        ]);

        const depositors = new Map();
        events.forEach((e) => depositors.set(e.args.depositor, e.args.allowed));
        const allowed = [...depositors].filter(([, ok]) => ok).map(([address]) => address);

        setText(root, 'sold', sold.toString());
        setText(root, 'remaining', remaining.toString());
        setText(root, 'principal', formatToken(principal));
        setText(root, 'assigned', formatToken(assigned));
        setText(root, 'undistributed', formatToken(undistributed));
        setText(root, 'depositors', allowed.length ? allowed.join(', ') : 'None yet');
    }

    onAction(root, 'deposit', async () => {
        const amount = amountInput(root, 'amount');
        await ensureNetwork();
        const { signer, address } = await connect();
        const slots = contract('profitSharingSlot', signer);
        const token = contract('token', signer);
        const slotsAddress = await slots.getAddress();

        if (!(await slots.isProfitDepositor(address))) {
            throw new Error(`${shortAddress(address)} is not a profit depositor. Add it below first.`);
        }
        if ((await token.balanceOf(address)) < amount) throw new Error('Your wallet does not hold that much NXD.');

        if ((await token.allowance(address, slotsAddress)) < amount) {
            await send('Approve NXD', token.approve(slotsAddress, amount), progress(root));
        }

        await send('Deposit profit', slots.depositProfit(amount), progress(root));
        setMessage(root, `${formatToken(amount)} NXD profit deposited and shared across earning slots.`, 'success');
        chainUpdated();
    });

    onAction(root, 'add-depositor', async () => {
        const depositor = addressInput(root, 'depositor');

        await ownerSend(root, 'profitSharingSlot', 'Add depositor', (c) => c.setProfitDepositor(depositor, true));
        setMessage(root, `${shortAddress(depositor)} can now deposit profit.`, 'success');
    });

    onAction(root, 'remove-depositor', async () => {
        const depositor = addressInput(root, 'depositor');

        await ownerSend(root, 'profitSharingSlot', 'Remove depositor', (c) => c.setProfitDepositor(depositor, false));
        setMessage(root, `${shortAddress(depositor)} can no longer deposit profit.`, 'success');
    });

    onAction(root, 'recover', async () => {
        const to = addressInput(root, 'recover-to');
        const amount = await contract('profitSharingSlot').undistributedProfit();

        if (amount === 0n) throw new Error('There is no undistributed profit to recover.');
        if (!window.confirm(`Send ${formatToken(amount)} NXD undistributed profit to ${to}?`)) return;

        await ownerSend(root, 'profitSharingSlot', 'Recover profit', (c) => c.recoverUndistributedProfit(to));
        setMessage(root, `${formatToken(amount)} NXD sent to ${shortAddress(to)}.`, 'success');
    });

    onChainUpdated(render);
    await render();
}

async function developmentSettings(root) {
    const NAME = 'teamDevelopmentBonus';

    async function render() {
        if (!isDeployed()) return;

        const c = contract(NAME);
        const [bps, months] = await Promise.all([c.extraBps(), c.windowMonths()]);

        setText(root, 'extra', `${Number(bps) / 100}%`);
        setText(root, 'window', `${months} months`);

        await Promise.all(rowsOf(root).map(async (row) => {
            const eligible = await c.isEligibleSource(config().contracts[row.dataset.nxContract]);

            setItem(row, 'eligible', eligible ? 'Gets the extra' : 'No extra');
            showActions(row, 'make-eligible', !eligible);
            showActions(row, 'remove-eligible', eligible);
        }));
    }

    onAction(root, 'save-extra', async () => {
        const percent = input(root, 'extra-percent');

        const bps = percentInput(percent);

        await ownerSend(root, NAME, 'Save extra %', (c) => c.setExtraBps(bps));
        setMessage(root, `Team Development extra set to ${percent}%.`, 'success');
    });

    onAction(root, 'save-window', async () => {
        const months = Number(input(root, 'window-months'));

        if (!Number.isInteger(months) || months < 1 || months > 120) throw new Error('Enter a whole number of months from 1 to 120.');

        await ownerSend(root, NAME, 'Save window', (c) => c.setWindowMonths(months));
        setMessage(root, `Team Development window set to ${months} months.`, 'success');
    });

    for (const [action, eligible] of [['make-eligible', true], ['remove-eligible', false]]) {
        onAction(root, action, async (button) => {
            const row = rowOf(button);
            const module = config().contracts[row.dataset.nxContract];

            await ownerSend(root, NAME, `Update ${row.dataset.nxLabel}`, (c) => c.setEligibleSource(module, eligible));
            setMessage(root, `${row.dataset.nxLabel} ${eligible ? 'now gets' : 'no longer gets'} the Team Development extra.`, 'success');
        });
    }

    onChainUpdated(render);
    await render();
}

// Monthly Loyalty / Royalty: this month's pool so far and last month's
// registration. Settling is done by the keeper's monthly task.
async function monthlyStatus(root) {
    async function render() {
        if (!isDeployed()) return;

        const today = BigInt(Math.floor((await reader().getBlock('latest')).timestamp / 86_400));

        await Promise.all(rowsOf(root).map(async (row) => {
            const c = contract(row.dataset.nxContract);
            const month = await c.currentMonth();
            const last = month - 1n;
            const [startDay, pool, lastStart, lastPool, registered, registrationEnd] = await Promise.all([
                c.monthStartDay(month), c.poolOf(month), c.monthStartDay(last), c.poolOf(last),
                c[row.dataset.nxCount](last), c.registrationEndDay(last),
            ]);

            setItem(row, 'month', monthLabel(startDay));
            setItem(row, 'pool', formatToken(pool));
            setItem(row, 'last-month', monthLabel(lastStart));
            setItem(row, 'last-pool', formatToken(lastPool));
            setItem(row, 'registered', registered.toString());
            setItem(row, 'registration', today < registrationEnd
                ? `Open until ${dayLabel(registrationEnd - 1n)}`
                : 'Closed, ready to settle');
        }));
    }

    onChainUpdated(render);
    await render();
}

register('admin-owner', ownerWallet);
register('admin-pause', pauseControls);
register('admin-payouts', payoutControls);
register('admin-treasury', treasuryControls);
register('admin-funding', fundingControls);
register('admin-slot', slotControls);
register('admin-development', developmentSettings);
register('admin-monthly', monthlyStatus);
