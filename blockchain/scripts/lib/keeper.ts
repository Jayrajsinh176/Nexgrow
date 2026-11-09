// Nexgrow keeper logic, shared by scripts/keeper.ts and the tests.
// See scripts/keeper.ts for what each task does.

// Development runs before the bonus tasks, so a wallet that just qualified
// already earns its extra on them.
export const ALL_TASKS = ["queues", "development", "performance", "booster", "monthly"];

const LOG_CHUNK = 10_000;

export interface Deployment {
  startBlock?: number;
  contracts: Record<string, string>;
}

export interface KeeperOptions {
  ethers: any;
  deployment: Deployment;
  tasks?: string[];
  batch?: number;
  log?: (...args: unknown[]) => void;
}

export async function runKeeper({
  ethers,
  deployment,
  tasks = ALL_TASKS,
  batch = 100,
  log = (...args: unknown[]) => console.log("[keeper]", ...args),
}: KeeperOptions) {
  const addr = deployment.contracts;
  const startBlock = Number(deployment.startBlock ?? 0);

  const [signer] = await ethers.getSigners();
  const at = (name: string, address: string) =>
    ethers.getContractAt(name, address, signer) as Promise<any>;

  const manager = await at("NexgrowManager", addr.manager);
  const nftIncome = await at("NFTIncome", addr.nftIncome);
  const referral = await at("ReferralIncome", addr.referralIncome);
  const teamBuilding = await at("TeamBuildingIncome", addr.teamBuildingIncome);
  const teamBusiness = await at("TeamBusiness", addr.teamBusiness);
  const booster = await at("BonusBooster", addr.bonusBooster);
  const performance = await at("TeamPerformanceBonus", addr.teamPerformanceBonus);
  const loyalty = await at("MonthlyLoyaltyBonus", addr.monthlyLoyaltyBonus);
  const royalty = await at("RoyaltyBonus", addr.royaltyBonus);
  const development = await at("TeamDevelopmentBonus", addr.teamDevelopmentBonus);

  log(`signer ${signer.address}, tasks: ${tasks.join(", ")}`);

  // ---- Event helpers ----

  const latestBlock = await ethers.provider.getBlockNumber();

  async function events(contract: any, filter: any) {
    const found: any[] = [];

    for (let from = startBlock; from <= latestBlock; from += LOG_CHUNK) {
      const to = Math.min(from + LOG_CHUNK - 1, latestBlock);
      found.push(...(await contract.queryFilter(filter, from, to)));
    }

    return found;
  }

  // Sponsor tree: sponsor => direct referrals.
  const directsOf = new Map<string, string[]>();
  const sponsorEvents = await events(referral, referral.filters.SponsorSet());

  for (const e of sponsorEvents) {
    const [account, sponsor] = e.args;
    const list = directsOf.get(sponsor) ?? [];
    list.push(account);
    directsOf.set(sponsor, list);
  }

  // Every wallet that ever bought (and their uplines).
  const purchaseEvents = await events(
    teamBusiness,
    teamBusiness.filters.PurchaseRecorded()
  );
  const buyers = [...new Set(purchaseEvents.map((e) => e.args[1] as string))];

  async function uplinesOf(account: string) {
    const chain: string[] = [];
    let current = await referral.sponsorOf(account);

    while (current !== ethers.ZeroAddress && !chain.includes(current)) {
      chain.push(current);
      current = await referral.sponsorOf(current);
    }

    return chain;
  }

  const teamMembers = new Set<string>(buyers);
  for (const b of buyers) for (const u of await uplinesOf(b)) teamMembers.add(u);

  async function send(label: string, tx: Promise<any>) {
    const receipt = await (await tx).wait();
    log(`${label} (tx ${receipt.hash}, gas ${receipt.gasUsed})`);
  }

  async function inBatches(items: string[], fn: (part: string[]) => Promise<void>) {
    for (let i = 0; i < items.length; i += batch) {
      await fn(items.slice(i, i + batch));
    }
  }

  const today = async () => BigInt((await ethers.provider.getBlock("latest"))!.timestamp);

  // ---- Tasks ----

  async function queues() {
    // TeamBusiness first: booster and performance read it.
    for (const [name, c] of [
      ["TeamBusiness", teamBusiness],
      ["ReferralIncome", referral],
      ["TeamBuildingIncome", teamBuilding],
    ] as [string, any][]) {
      while ((await c.pendingJobs()) > 0n) {
        await send(`${name}.processQueue(${batch})`, c.processQueue(batch));
      }
    }
  }

  async function boosterTask() {
    const current = await booster.periodOf(await today());

    for (let period = 0n; period < current; period++) {
      if (!(await booster.isSettleable(period))) continue;

      const due: string[] = [];

      for (const account of teamMembers) {
        if (await booster.isSettled(account, period)) continue;
        if ((await booster.businessOf(account, period)) === 0n) continue;
        due.push(account);
      }

      await inBatches(due, (part) =>
        send(`BonusBooster.settle(period ${period}, ${part.length})`, booster.settle(period, part))
      );
    }
  }

  async function monthlyTask() {
    const now = await today();
    const nowDay = now / 86_400n;
    const firstMonth = await loyalty.monthOf(
      (await ethers.provider.getBlock(startBlock))!.timestamp
    );
    const currentMonth = await loyalty.monthOf(now);
    const sponsors = [...directsOf.keys()];

    for (let month = firstMonth; month < currentMonth; month++) {
      const open =
        nowDay >= (await loyalty.monthEndDay(month)) &&
        nowDay < (await loyalty.registrationEndDay(month));

      if (open) {
        const loyal: string[] = [];
        const royal: string[] = [];

        for (const s of sponsors) {
          const direct = await loyalty.directBusinessOf(s, month);

          if (
            direct >= (await loyalty.DIRECT_BUSINESS_THRESHOLD()) &&
            !(await loyalty.isRegistered(month, s))
          ) {
            loyal.push(s);
          }
          if (
            direct >= (await royalty.DIRECT_BUSINESS_THRESHOLD()) &&
            !(await royalty.isQualified(s))
          ) {
            royal.push(s);
          }
        }

        await inBatches(loyal, (part) =>
          send(`MonthlyLoyaltyBonus.register(month ${month}, ${part.length})`, loyalty.register(month, part))
        );
        await inBatches(royal, (part) =>
          send(`RoyaltyBonus.qualify(month ${month}, ${part.length})`, royalty.qualify(month, part))
        );
      }

      if (nowDay < (await loyalty.registrationEndDay(month))) continue;

      const loyalDue: string[] = [];
      const royalDue: string[] = [];

      for (const s of sponsors) {
        if ((await loyalty.isRegistered(month, s)) && !(await loyalty.isSettled(month, s))) {
          loyalDue.push(s);
        }
        if ((await royalty.isEligible(s, month)) && !(await royalty.isSettled(month, s))) {
          royalDue.push(s);
        }
      }

      await inBatches(loyalDue, (part) =>
        send(`MonthlyLoyaltyBonus.settle(month ${month}, ${part.length})`, loyalty.settle(month, part))
      );
      await inBatches(royalDue, (part) =>
        send(`RoyaltyBonus.settle(month ${month}, ${part.length})`, royalty.settle(month, part))
      );
    }
  }

  // Direct legs of an account, largest lifetime team business first.
  async function rankedLegs(account: string) {
    const legs = directsOf.get(account) ?? [];
    const withBusiness = await Promise.all(
      legs.map(async (l) => ({ leg: l, business: await teamBusiness.lifetimeBusiness(l) as bigint }))
    );

    return withBusiness.sort((a, b) => (a.business < b.business ? 1 : a.business > b.business ? -1 : 0));
  }

  async function performanceTask() {
    const tierCount = await performance.TIER_COUNT();

    for (const [account, legs] of directsOf) {
      if (legs.length < 4) continue;

      const next = await performance.nextTier(account);
      if (next >= tierCount) continue;

      const top = (await rankedLegs(account)).slice(0, 4);
      const [target] = await performance.tier(next);

      if (!(await performance.meetsTarget(top[0].business, top[3].business, target))) continue;

      await send(
        `TeamPerformanceBonus.qualify(${account})`,
        performance.qualify(account, top.map((l) => l.leg))
      );
    }
  }

  async function developmentTask() {
    const required = await development.BUSINESS_PER_LEG();

    for (const [account, legs] of directsOf) {
      if (legs.length < 3) continue;
      if (await development.isQualified(account)) continue;

      const top = (await rankedLegs(account)).slice(0, 3);

      if (top[2].business < required) continue;

      await send(
        `TeamDevelopmentBonus.qualify(${account})`,
        development.qualify(account, top.map((l) => l.leg))
      );
    }
  }

  async function claimsTask() {
    const accountModules: [string, any][] = [
      ["ReferralIncome", referral],
      ["TeamBuildingIncome", teamBuilding],
      ["BonusBooster", booster],
      ["TeamPerformanceBonus", performance],
      ["MonthlyLoyaltyBonus", loyalty],
      ["RoyaltyBonus", royalty],
      ["TeamDevelopmentBonus", development],
    ];

    for (const [name, c] of accountModules) {
      const round = await c.currentRound();

      if (round === 0n || !(await c.payoutsEnabled()) || (await c.paused())) continue;

      const due: string[] = [];

      for (const account of teamMembers) {
        const e = await c.earners(account);
        if (e.lastClaimedRound !== round && e.credited > e.paid) due.push(account);
      }

      await inBatches(due, (part) => send(`${name}.claim(round ${round}, ${part.length})`, c.claim(part)));
    }

    // Daily bonus: claims are per NFT.
    const round = await nftIncome.currentRound();

    if (round !== 0n && (await nftIncome.payoutsEnabled()) && !(await nftIncome.paused())) {
      const count = Number(await manager.registeredCount());
      const due: string[] = [];

      for (let id = 0; id < count; id++) {
        const s = await nftIncome.nfts(id);
        if (s.active && s.lastClaimedRound !== round && s.paid < s.cap) due.push(id.toString());
      }

      await inBatches(due, (part) =>
        send(`NFTIncome.claim(round ${round}, ${part.length} NFTs)`, nftIncome.claim(part))
      );
    }
  }

  const runners: Record<string, () => Promise<void>> = {
    queues,
    booster: boosterTask,
    monthly: monthlyTask,
    performance: performanceTask,
    development: developmentTask,
    claims: claimsTask,
  };

  for (const task of tasks) {
    const run = runners[task];

    if (!run) throw new Error(`Unknown keeper task: ${task}`);

    log(`-- ${task}`);
    await run();

    // Queued work feeds the other tasks; drain again after each one.
    if (task !== "queues" && tasks.includes("queues")) await queues();
  }

  log("done");
}
