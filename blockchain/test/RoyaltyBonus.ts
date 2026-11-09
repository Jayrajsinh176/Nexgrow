import { expect } from "chai";
import { network } from "hardhat";

const { ethers, networkHelpers } = await network.create();

const DAY = 24 * 60 * 60;

// Test-only revenue source. Real sources are not defined yet.
const TEST_REVENUE = ethers.id("TEST_REVENUE");

const tnex = (v: string | number) => ethers.parseUnits(v.toString(), 18);

describe("RoyaltyBonus", function () {
  async function deployFixture() {
    // Start one hour into the first day of the next calendar month.
    const now = new Date((await networkHelpers.time.latest()) * 1000);
    const nextMonth = Date.UTC(now.getUTCFullYear(), now.getUTCMonth() + 1, 1);
    await networkHelpers.time.increaseTo(nextMonth / 1000 + 3600);

    const [owner, funder, other, a, b, c, d1, e, f] = await ethers.getSigners();

    const token = await ethers.deployContract("TestNexadonToken");
    const tokenAddress = await token.getAddress();

    const treasury = await ethers.deployContract("NexgrowTreasury", [tokenAddress]);
    const nft = await ethers.deployContract("NexgrowNFT", [
      tokenAddress,
      await treasury.getAddress(),
    ]);
    const nftAddress = await nft.getAddress();
    const manager = await ethers.deployContract("NexgrowManager", [nftAddress]);
    const managerAddress = await manager.getAddress();
    const rewardPool = await ethers.deployContract("RewardPool", [tokenAddress]);
    const poolAddress = await rewardPool.getAddress();
    const referral = await ethers.deployContract("ReferralIncome", [
      managerAddress, nftAddress, poolAddress,
    ]);
    const teamBusiness = await ethers.deployContract("TeamBusiness", [
      managerAddress, await referral.getAddress(),
    ]);
    const royalty = await ethers.deployContract("RoyaltyBonus", [
      managerAddress, nftAddress, poolAddress, await teamBusiness.getAddress(),
    ]);
    const royaltyAddress = await royalty.getAddress();

    await nft.setManager(managerAddress);
    await manager.setModule(await manager.REWARD_POOL(), poolAddress);
    await manager.setModule(await manager.REFERRAL_INCOME(), await referral.getAddress());
    await manager.setModule(await manager.TEAM_BUSINESS(), await teamBusiness.getAddress());
    await rewardPool.setSpender(royaltyAddress, true);

    await token.transfer(funder.address, tnex(100_000));
    for (const u of [other, a, b, c, d1, e, f]) {
      await token.transfer(u.address, tnex(30_000));
    }

    async function buy(user: any, pkg: number) {
      await token.connect(user).approve(nftAddress, tnex(pkg));
      await nft.connect(user).buyNFT(pkg);
    }

    // a sponsors d1, b sponsors e, c sponsors f. Total: 150.
    async function buildTree() {
      for (const top of [a, b, c]) await buy(top, 50);
      await referral.connect(d1).setSponsor(a.address);
      await referral.connect(e).setSponsor(b.address);
      await referral.connect(f).setSponsor(c.address);
    }

    // Moves to `days` days after the end of the current month (+1 hour)
    // and returns the month that ended.
    async function afterMonthEnd(days = 0) {
      const month = await royalty.currentMonth();
      const end = await royalty.monthEndDay(month);
      await networkHelpers.time.increaseTo(Number(end + BigInt(days)) * DAY + 3600);
      return month;
    }

    async function afterRegistration(month: bigint) {
      const end = await royalty.registrationEndDay(month);
      await networkHelpers.time.increaseTo(Number(end) * DAY + 3600);
    }

    async function fund(amount: bigint) {
      await rewardPool.addRevenueSource(TEST_REVENUE, "Test-only revenue source");
      await rewardPool.setDepositor(funder.address, TEST_REVENUE, true);
      await rewardPool.setAllocation(royaltyAddress, TEST_REVENUE, 10_000n);
      await token.connect(funder).approve(poolAddress, amount);
      await rewardPool.connect(funder).deposit(amount, TEST_REVENUE);
    }

    return {
      owner, funder, other, a, b, c, d1, e, f,
      token, rewardPool, referral, teamBusiness, royalty,
      buy, buildTree, afterMonthEnd, afterRegistration, fund,
    };
  }

  describe("Qualification", function () {
    it("Should qualify a wallet whose direct business reached 5,000", async function () {
      const { royalty, buildTree, buy, afterMonthEnd, a, b, d1, e } = await deployFixture();

      await buildTree();
      await buy(d1, 5000);
      await buy(e, 2500);
      const month = await afterMonthEnd();

      await expect(royalty.qualify(month, [a.address, b.address]))
        .to.emit(royalty, "Qualified")
        .withArgs(a.address, month, tnex(5000));

      expect(await royalty.isQualified(a.address)).to.equal(true);
      expect(await royalty.isQualified(b.address)).to.equal(false);
      expect(await royalty.windowStart(a.address)).to.equal(month);
    });

    it("Should only allow qualifying in the 7 days after the month", async function () {
      const { royalty, buildTree, buy, afterMonthEnd, afterRegistration, a, d1 } =
        await deployFixture();

      await buildTree();
      await buy(d1, 5000);
      const month = await royalty.currentMonth();

      await expect(royalty.qualify(month, [a.address])).to.be.revertedWith("Month not ended");

      await afterMonthEnd();
      await afterRegistration(month);

      await expect(royalty.qualify(month, [a.address])).to.be.revertedWith(
        "Registration closed",
      );
    });

    it("Should give 15 months of eligibility starting with the qualifying month", async function () {
      const { royalty, buildTree, buy, afterMonthEnd, a, d1 } = await deployFixture();

      await buildTree();
      await buy(d1, 5000);
      const month = await afterMonthEnd();
      await royalty.qualify(month, [a.address]);

      expect(await royalty.isEligible(a.address, month - 1n)).to.equal(false);
      expect(await royalty.isEligible(a.address, month)).to.equal(true);
      expect(await royalty.isEligible(a.address, month + 14n)).to.equal(true);
      expect(await royalty.isEligible(a.address, month + 15n)).to.equal(false);
      expect(await royalty.eligibleCount(month + 14n)).to.equal(1n);
      expect(await royalty.eligibleCount(month + 15n)).to.equal(0n);
    });

    it("Should qualify only once for life", async function () {
      const { royalty, buildTree, buy, afterMonthEnd, a, d1 } = await deployFixture();

      await buildTree();
      await buy(d1, 5000);
      const first = await afterMonthEnd();
      await royalty.qualify(first, [a.address]);
      await royalty.qualify(first, [a.address]);

      await buy(d1, 5000);
      const second = await afterMonthEnd();
      await royalty.qualify(second, [a.address]);

      expect(await royalty.windowStart(a.address)).to.equal(first);
      expect(await royalty.eligibleCount(first)).to.equal(1n);
      expect(await royalty.eligibleCount(second)).to.equal(1n);
    });
  });

  describe("Settlement", function () {
    it("Should not settle while registration is open", async function () {
      const { royalty, buildTree, buy, afterMonthEnd, a, d1 } = await deployFixture();

      await buildTree();
      await buy(d1, 5000);
      const month = await afterMonthEnd();
      await royalty.qualify(month, [a.address]);

      await expect(royalty.settle(month, [a.address])).to.be.revertedWith(
        "Registration open",
      );
    });

    it("Should split each month's pool equally among wallets eligible that month", async function () {
      const { royalty, buildTree, buy, afterMonthEnd, afterRegistration, a, b, c, d1, f } =
        await deployFixture();

      // Month 0: a qualifies. Business 150 + 5,000.
      await buildTree();
      await buy(d1, 5000);
      const m0 = await afterMonthEnd();
      await royalty.qualify(m0, [a.address]);

      // Month 1: c qualifies. Business 5,000. a is still eligible.
      await buy(f, 5000);
      const m1 = await afterMonthEnd();
      await royalty.qualify(m1, [c.address]);
      await afterRegistration(m1);

      const share0 = (tnex(5150) * 500n) / 10_000n;
      const share1 = (tnex(5000) * 500n) / 10_000n / 2n;

      expect(await royalty.shareOf(m0)).to.equal(share0);
      expect(await royalty.shareOf(m1)).to.equal(share1);

      await royalty.settle(m0, [a.address, c.address]);
      await expect(royalty.settle(m1, [a.address, b.address, c.address]))
        .to.emit(royalty, "Settled")
        .withArgs(m1, c.address, share1);

      expect(await royalty.unpaidOf(a.address)).to.equal(share0 + share1);
      expect(await royalty.unpaidOf(c.address)).to.equal(share1);
      expect(await royalty.unpaidOf(b.address)).to.equal(0n);

      // Settling again changes nothing.
      await royalty.settle(m1, [a.address]);
      expect(await royalty.unpaidOf(a.address)).to.equal(share0 + share1);
    });
  });

  describe("Payouts", function () {
    it("Should pay a settled share from its own budget in a round", async function () {
      const { royalty, token, buildTree, buy, afterMonthEnd, afterRegistration, fund, a, d1 } =
        await deployFixture();

      await buildTree();
      await buy(d1, 5000);
      const month = await afterMonthEnd();
      await royalty.qualify(month, [a.address]);
      await afterRegistration(month);
      await royalty.settle(month, [a.address]);

      await fund(tnex(10_000));
      await royalty.setPayoutsEnabled(true);
      await royalty.startRound();

      const before = await token.balanceOf(a.address);

      await royalty.claim([a.address]);

      expect((await token.balanceOf(a.address)) - before).to.equal(
        (tnex(5150) * 500n) / 10_000n,
      );
    });
  });
});
