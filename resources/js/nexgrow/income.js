// Income page widget (chain mode): one income contract's totals for the
// linked wallet, how each credit arose, payouts, and a status line.
// Column order matches App\Support\Chain::INCOME['...']['columns'].
import { getAddress } from 'ethers';
import {
    blockDate, config, contract, formatToken, isDeployed, queryEvents, shortAddress,
} from './chain.js';
import { field, register, setText, show } from './ui.js';
import { ownedTokenIds } from './nft.js';

const pct = (bps) => `${Number(bps) / 100}%`;

const dateLabel = (date) => date.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });

// Contract month ID (year * 12 + month index) as "October 2026".
const monthLabel = (id) => new Date(Date.UTC(Number(id / 12n), Number(id % 12n), 1))
    .toLocaleString(undefined, { month: 'long', year: 'numeric', timeZone: 'UTC' });

const MODULE_NAMES = {
    teamBuildingIncome: 'Team Building',
    bonusBooster: '15-Day Booster',
    teamPerformanceBonus: 'Team Performance',
    referralIncome: 'Referral',
    monthlyLoyaltyBonus: 'Monthly Loyalty',
    royaltyBonus: 'Royalty',
};

function moduleName(address) {
    const entry = Object.entries(config().contracts ?? {})
        .find(([, a]) => a.toLowerCase() === address.toLowerCase());

    return MODULE_NAMES[entry?.[0]] ?? shortAddress(getAddress(address));
}

const upline = {
    history: (c, w) => queryEvents(c, c.filters.Credited(w)),
    cells: (a) => [`Level ${a.level}`, shortAddress(getAddress(a.source)), formatToken(a.baseAmount), formatToken(a.amount)],
};

const monthlyShare = {
    history: (c, w) => queryEvents(c, c.filters.Settled(null, w)),
    cells: (a) => [monthLabel(a.month), formatToken(a.share)],
};

// Per module: credit history (event + cells) and the status line.
const MODULES = {
    referralIncome: {
        ...upline,
        async status(c, w) {
            return `You have ${await c.directCount(w)} direct referrals on-chain.`;
        },
    },
    teamBuildingIncome: {
        ...upline,
        async status(c, w) {
            const [active, unlocked, directs] = await Promise.all([
                contract('nftIncome').hasActiveNFT(w),
                c.unlockedLevels(w),
                contract('referralIncome').directCount(w),
            ]);

            if (!active) return 'You need an active NFT to earn Team Building Benefits.';
            if (unlocked === 0n) return `You need 5 direct referrals (you have ${directs}).`;

            return `You currently earn from ${unlocked} levels.`;
        },
    },
    bonusBooster: {
        history: (c, w) => queryEvents(c, c.filters.Settled(w)),
        cells: (a) => [`#${a.period + 1n}`, formatToken(a.business), pct(a.bps), formatToken(a.bonus)],
        async status(c, w) {
            const period = await c.currentPeriod();
            const [business, end] = await Promise.all([c.businessOf(w, period), c.periodEnd(period)]);
            const bps = await c.tierBps(business);
            const tier = bps > 0n ? `${pct(bps)} tier` : 'below the 500 tier';

            return `This period: ${formatToken(business)} team business (${tier}). Ends ${dateLabel(new Date(Number(end) * 1000))}.`;
        },
    },
    teamPerformanceBonus: {
        history: (c, w) => queryEvents(c, c.filters.TargetReached(w)),
        cells: (a) => [formatToken(a.target, 0), formatToken(a.bonus, 0)],
        async status(c, w) {
            const next = await c.nextTier(w);

            if (next >= await c.TIER_COUNT()) return 'You have reached every team target.';

            const [target, bonus] = await c.tier(next);

            return `Next target: ${formatToken(target, 0)} team business for a ${formatToken(bonus, 0)} NXD bonus.`;
        },
    },
    monthlyLoyaltyBonus: {
        ...monthlyShare,
        async status(c, w) {
            const business = await c.directBusinessOf(w, await c.currentMonth());

            return `This month's direct business: ${formatToken(business)} / 3,000.00.`;
        },
    },
    royaltyBonus: {
        ...monthlyShare,
        async status(c, w) {
            if (await c.isQualified(w)) {
                const start = await c.windowStart(w);

                return `Qualified: you share the pool from ${monthLabel(start)} to ${monthLabel(start + 14n)}.`;
            }

            const business = await c.directBusinessOf(w, await c.currentMonth());

            return `This month's direct business: ${formatToken(business)} / 5,000.00.`;
        },
    },
    teamDevelopmentBonus: {
        history: (c, w) => queryEvents(c, c.filters.ExtraCredited(w)),
        cells: (a) => [moduleName(a.source), formatToken(a.bonusAmount), formatToken(a.extra)],
        async status(c, w) {
            if (!(await c.isQualified(w))) {
                return 'You qualify with 3 direct legs of 5,000 lifetime business each.';
            }

            const [bps, end] = await Promise.all([c.extraBps(), c.windowEnd(w)]);
            const endDate = new Date(Number(end) * 1000);

            return endDate > new Date()
                ? `Qualified: +${pct(bps)} on eligible bonuses until ${dateLabel(endDate)}.`
                : `Your window ended on ${dateLabel(endDate)}.`;
        },
    },
};

function row(cells) {
    const tr = document.createElement('tr');

    cells.forEach((text, index) => {
        const td = document.createElement('td');
        td.className = index === cells.length - 1 ? 'px-6 py-3 font-semibold text-emerald-400' : 'px-6 py-3 text-slate-300';
        td.textContent = text;
        tr.appendChild(td);
    });

    return tr;
}

async function fillTable(root, name, events, cells) {
    const body = field(root, name);
    const sorted = [...events].sort((a, b) => b.blockNumber - a.blockNumber || b.index - a.index);

    body.replaceChildren();

    for (const event of sorted) {
        body.appendChild(row([dateLabel(await blockDate(event.blockNumber)), ...cells(event.args)]));
    }

    show(root, `${name}-empty`, sorted.length === 0);
}

async function income(root) {
    const key = root.dataset.nxContract;
    const module = MODULES[key];

    async function render() {
        const wallet = config().user?.wallet;
        if (!wallet || !isDeployed() || !module) return;

        const c = contract(key);
        const earner = await c.earners(wallet);
        const credited = formatToken(earner.credited);

        setText(root, 'credited', credited);
        setText(root, 'credited-hero', credited);
        setText(root, 'paid', formatToken(earner.paid));
        setText(root, 'pending', formatToken(earner.credited - earner.paid));

        const [credits, payouts, status] = await Promise.all([
            module.history(c, wallet),
            queryEvents(c, c.filters.Claimed(wallet)),
            module.status(c, wallet),
        ]);

        setText(root, 'status', status);
        show(root, 'status', Boolean(status));

        await fillTable(root, 'credits', credits, module.cells);
        await fillTable(root, 'payouts', payouts, (a) => [`#${a.roundId}`, formatToken(a.paid), formatToken(a.carriedForward)]);
    }

    document.addEventListener('nx:chain-updated', () => render().catch(() => {}));
    await render();
}

// Dashboard: every module's totals for the linked wallet.
async function incomeSummary(root) {
    const cell = (row, name) => row.querySelector(`[data-nx-cell="${name}"]`);

    async function render() {
        const wallet = config().user?.wallet;
        if (!wallet || !isDeployed()) return;

        try {
            setText(root, 'balance', formatToken(await contract('token').balanceOf(wallet)));
        } catch (e) {
            console.warn('Failed reading token balance:', e);
        }

        let earnedTotal = 0n;
        let paidTotal = 0n;

        for (const row of root.querySelectorAll('[data-nx-row]')) {
            const key = row.dataset.nxRow;
            let earned = 0n;
            let paid = 0n;

            try {
                if (key === 'nftIncome') {
                    const nftIncome = contract('nftIncome');

                    for (const id of await ownedTokenIds(wallet)) {
                        const [state, entitled] = await Promise.all([nftIncome.nfts(id), nftIncome.entitledOf(id)]);
                        earned += entitled;
                        paid += state.paid;
                    }
                } else {
                    const earner = await contract(key).earners(wallet);
                    earned = earner.credited;
                    paid = earner.paid;
                }
            } catch (err) {
                console.warn(`Failed reading income for ${key}:`, err);
            }

            earnedTotal += earned;
            paidTotal += paid;

            cell(row, 'earned').textContent = formatToken(earned);
            cell(row, 'paid').textContent = formatToken(paid);
            cell(row, 'pending').textContent = formatToken(earned - paid);
        }

        setText(root, 'earned', formatToken(earnedTotal));
        setText(root, 'paid', formatToken(paidTotal));
        setText(root, 'pending', formatToken(earnedTotal - paidTotal));
    }

    document.addEventListener('nx:chain-updated', () => render().catch(() => {}));
    await render();
}

register('income', income);
register('income-summary', incomeSummary);
