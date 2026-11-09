import { expect } from "chai";
import { network } from "hardhat";
import { runKeeper } from "../scripts/lib/keeper.js";
import { deploySystem, TEST_REVENUE } from "./helpers/system.js";

const { ethers, networkHelpers } = await network.create();

const DAY = 24 * 60 * 60;

const tnex = (v: string | number) => ethers.parseUnits(v.toString(), 18);

describe("Keeper", function () {
  // Runs the keeper and returns how many transactions it sent.
  async function keep(deployment: any, tasks?: string[]) {
    let sent = 0;

    await runKeeper({
      ethers,
      deployment,
      tasks,
      batch: 3,
      log: (...args: unknown[]) => {
        if (String(args[0]).includes("(tx ")) sent++;
      },
    });

    return sent;
  }

  it("Should do all routine work for a full month of activity", async function () {
    const s = await deploySystem(ethers, networkHelpers);
    const { root, A, B, C, D, E, a1, b1, c1, deployment } = s;

    // Same tree and purchases as the end-to-end test.
    await s.buy(root, 5000);
    for (const d of [A, B, C, D, E]) {
      await s.referral.connect(d).setSponsor(root.address);
      await s.buy(d, 2500);
    }
    for (const [child, parent] of [[a1, A], [b1, B], [c1, C]] as const) {
      await s.referral.connect(child).setSponsor(parent.address);
      await s.buy(child, 2500);
    }

    // ---- Queues, Team Development and Team Performance ----
    await keep(deployment);

    expect(await s.teamBusiness.pendingJobs()).to.equal(0n);
    expect(await s.development.isQualified(root.address)).to.equal(true);
    expect(await s.performance.nextTier(root.address)).to.equal(1n);
    expect(await s.performance.unpaidOf(root.address)).to.equal(tnex(500));
    // Development qualified first, so the performance bonus earned its extra.
    expect(await s.development.unpaidOf(root.address)).to.equal(tnex(25));

    // ---- Daily bonus round (owner), claims and upline queues (keeper) ----
    const deposit = tnex(200_000);
    await s.token.connect(s.funder).approve(await s.rewardPool.getAddress(), deposit);
    await s.rewardPool.connect(s.funder).deposit(deposit, TEST_REVENUE);
    for (const [c] of s.modules) await c.setPayoutsEnabled(true);

    await networkHelpers.time.increase(10 * DAY);
    await s.nftIncome.startRound();

    await keep(deployment, ["claims", "queues"]);

    for (let id = 0; id < 9; id++) {
      expect((await s.nftIncome.nfts(id)).lastClaimedRound).to.equal(1n);
    }
    expect(await s.referral.unpaidOf(root.address)).to.equal(tnex("3.44"));
    expect(await s.teamBuilding.unpaidOf(root.address)).to.equal(tnex("5.2"));

    // ---- 15-Day Booster ----
    await s.to(await s.booster.periodEnd(0));
    await keep(deployment);

    expect(await s.booster.isSettled(root.address, 0)).to.equal(true);
    expect(await s.booster.unpaidOf(root.address)).to.equal(tnex(4_250));
    expect(await s.booster.unpaidOf(A.address)).to.equal(tnex(600));

    // ---- Monthly Loyalty and Royalty: registration, then settlement ----
    const month = await s.loyalty.monthOf(await networkHelpers.time.latest());

    await s.to((await s.loyalty.monthEndDay(month)) * BigInt(DAY) + 3600n);
    await keep(deployment);

    expect(await s.loyalty.isRegistered(month, root.address)).to.equal(true);
    expect(await s.royalty.isQualified(root.address)).to.equal(true);

    await s.to((await s.loyalty.registrationEndDay(month)) * BigInt(DAY) + 3600n);
    await keep(deployment);

    expect(await s.loyalty.unpaidOf(root.address)).to.equal(tnex(1_250));
    expect(await s.royalty.unpaidOf(root.address)).to.equal(tnex(1_250));

    // ---- Payment rounds (owner) and claims (keeper) ----
    const accountModules = s.modules.slice(1).map(([c]) => c);
    let owed = 0n;

    for (const c of accountModules) {
      owed += await c.unpaidOf(root.address);
      await c.startRound();
    }

    const before = await s.token.balanceOf(root.address);

    await keep(deployment, ["claims"]);

    expect((await s.token.balanceOf(root.address)) - before).to.equal(owed);
    for (const c of accountModules) {
      expect(await c.unpaidOf(root.address)).to.equal(0n);
    }

    // ---- Nothing left to do: a further run sends no transactions ----
    expect(await keep(deployment, ["queues", "development", "performance", "booster", "monthly", "claims"])).to.equal(0);
  });

  it("Should reject an unknown task", async function () {
    const s = await deploySystem(ethers, networkHelpers);

    let error: unknown;

    try {
      await keep(s.deployment, ["nope"]);
    } catch (e) {
      error = e;
    }

    expect(String(error)).to.contain("Unknown keeper task: nope");
  });
});
