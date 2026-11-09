import { expect } from "chai";
import { network } from "hardhat";

import { deploySystem as deploy, TEST_REVENUE } from "./helpers/system.js";

const { ethers, networkHelpers } = await network.create();

const deploySystem = () => deploy(ethers, networkHelpers);

const DAY = 24 * 60 * 60;

const tnex = (v: string | number) => ethers.parseUnits(v.toString(), 18);

// The whole system wired as in scripts/deployNexgrow.ts, run through every
// income module together, as the backend keeper would drive it.
describe("End to end", function () {
  it("Should run every income module together with exact amounts", async function () {
    const s = await deploySystem();
    const { root, A, B, C, D, E, a1, b1, c1 } = s;

    // ---- Tree and purchases (all on day 0 of the month) ----
    // root (5,000) has directs A..E (2,500 each); A, B, C each have one
    // direct (2,500 each). Sponsors are set before buying.
    await s.buy(root, 5000);
    for (const d of [A, B, C, D, E]) {
      await s.referral.connect(d).setSponsor(root.address);
      await s.buy(d, 2500);
    }
    for (const [child, parent] of [[a1, A], [b1, B], [c1, C]] as const) {
      await s.referral.connect(child).setSponsor(parent.address);
      await s.buy(child, 2500);
    }
    // Token IDs: root 0, A..E 1-5, a1 6, b1 7, c1 8.

    await s.teamBusiness.processQueue(1000);

    // Lifetime legs: A, B, C 5,000; D, E 2,500. Root 25,000 in total.
    expect(await s.teamBusiness.lifetimeBusiness(root.address)).to.equal(tnex(25_000));

    // ---- Team Development: 3 legs of 5,000 ----
    await s.development.qualify(root.address, [A.address, B.address, C.address]);

    // ---- Team Performance: 10,000 target (A 5,000 >= 4,000; D 2,500 >= 2,000) ----
    await s.performance.qualify(root.address, [A.address, B.address, C.address, D.address]);
    expect(await s.performance.unpaidOf(root.address)).to.equal(tnex(500));

    // ---- Funding (budgets per module by allocation) ----
    const deposit = tnex(200_000);
    await s.token.connect(s.funder).approve(await s.rewardPool.getAddress(), deposit);
    await s.rewardPool.connect(s.funder).deposit(deposit, TEST_REVENUE);

    for (const [c] of s.modules) await c.setPayoutsEnabled(true);

    // ---- Daily bonus after 10 days ----
    await networkHelpers.time.increase(10 * DAY);
    await s.nftIncome.startRound();
    await s.nftIncome.claim([0, 1, 2, 3, 4, 5, 6, 7, 8]);

    // root 10 x 1.00; everyone else 10 x 0.80.
    expect((await s.nftIncome.nfts(0)).paid).to.equal(tnex(10));
    expect((await s.nftIncome.nfts(1)).paid).to.equal(tnex(8));

    await s.referral.processQueue(1000);
    await s.teamBuilding.processQueue(1000);

    // Referral to root: 5% of 5 x 8 (level 1) + 6% of 3 x 8 (level 2).
    expect(await s.referral.unpaidOf(root.address)).to.equal(tnex("3.44"));
    // Referral to A: 5% of a1's 8.
    expect(await s.referral.unpaidOf(A.address)).to.equal(tnex("0.4"));

    // Team building to root (5 directs, active NFT): 10% of 40 + 5% of 24.
    expect(await s.teamBuilding.unpaidOf(root.address)).to.equal(tnex("5.2"));
    // A has only 1 direct: nothing.
    expect(await s.teamBuilding.unpaidOf(A.address)).to.equal(0n);

    // ---- 15-Day Booster: root's period business 25,000 -> 17% ----
    await s.to(await s.booster.periodEnd(0));
    await s.booster.settle(0, [root.address, A.address]);

    expect(await s.booster.unpaidOf(root.address)).to.equal(tnex(4_250));
    // A: 5,000 -> 12%.
    expect(await s.booster.unpaidOf(A.address)).to.equal(tnex(600));

    // ---- Team Development extra: 5% of every eligible root credit ----
    const eligible =
      (await s.teamBuilding.unpaidOf(root.address)) +
      (await s.booster.unpaidOf(root.address)) +
      (await s.performance.unpaidOf(root.address));
    expect(await s.development.unpaidOf(root.address)).to.equal((eligible * 500n) / 10_000n);
    // Referral is not eligible, and A is not qualified.
    expect(await s.development.unpaidOf(A.address)).to.equal(0n);

    // ---- Monthly Loyalty and Royalty for the month ----
    const month = await s.loyalty.monthOf(await networkHelpers.time.latest());
    await s.to((await s.loyalty.monthEndDay(month)) * BigInt(DAY) + 3600n);

    const everyone = [root, A, B, C, D, E].map((w) => w.address);
    await s.loyalty.register(month, everyone);
    await s.royalty.qualify(month, everyone);

    // Only root has direct business >= 3,000 / 5,000 (12,500).
    expect(await s.loyalty.qualifierCount(month)).to.equal(1n);
    expect(await s.royalty.eligibleCount(month)).to.equal(1n);

    await s.to((await s.loyalty.registrationEndDay(month)) * BigInt(DAY) + 3600n);
    await s.loyalty.settle(month, everyone);
    await s.royalty.settle(month, everyone);

    // 5% of 25,000 project business each.
    expect(await s.loyalty.unpaidOf(root.address)).to.equal(tnex(1_250));
    expect(await s.royalty.unpaidOf(root.address)).to.equal(tnex(1_250));

    // ---- Pay every module ----
    const before = await s.token.balanceOf(root.address);
    let expected = 0n;

    for (const [c] of s.modules.slice(1)) {
      const owed = await c.unpaidOf(root.address);

      expected += owed;
      await c.startRound();
      await c.claim([root.address]);

      expect(await c.unpaidOf(root.address)).to.equal(0n);
    }

    expect((await s.token.balanceOf(root.address)) - before).to.equal(expected);

    // The pool never pays out more than was deposited.
    expect(await s.rewardPool.totalReleased()).to.be.lessThanOrEqual(
      await s.rewardPool.totalDeposited(),
    );
    expect(await s.token.balanceOf(await s.rewardPool.getAddress())).to.equal(
      (await s.rewardPool.totalDeposited()) - (await s.rewardPool.totalReleased()),
    );
  });

  it("Should run the Profit-Sharing Slot next to the NFT system", async function () {
    const s = await deploySystem();
    const { root, A, funder } = s;
    const slotAddress = await s.slot.getAddress();

    await s.slot.setProfitDepositor(funder.address, true);

    for (const w of [root, A]) {
      await s.token.connect(w).approve(slotAddress, tnex(3_000));
      await s.slot.connect(w).buySlot();
    }

    await s.token.connect(funder).approve(slotAddress, tnex(1_000));
    await s.slot.connect(funder).depositProfit(tnex(1_000));

    // 540 days held: no deduction.
    await networkHelpers.time.increase(540 * DAY);

    const before = await s.token.balanceOf(root.address);
    await s.slot.connect(root).withdraw(0);

    expect((await s.token.balanceOf(root.address)) - before).to.equal(tnex(3_500));

    // A's principal and profit are still held for A.
    expect(await s.token.balanceOf(slotAddress)).to.equal(tnex(3_500));
  });
});
