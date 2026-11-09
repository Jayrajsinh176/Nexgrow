import { expect } from "chai";
import { network } from "hardhat";

const { ethers, networkHelpers } = await network.create();

const DAY = 24 * 60 * 60;

// Test-only revenue source. Real sources are not defined yet.
const TEST_REVENUE = ethers.id("TEST_REVENUE");

const tnex = (v: string | number) => ethers.parseUnits(v.toString(), 18);

const monthId = (d: Date) => BigInt(d.getUTCFullYear() * 12 + d.getUTCMonth());
const dayOf = (ms: number) => BigInt(Math.floor(ms / 1000 / DAY));

describe("MonthlyLoyaltyBonus", function () {
  async function deployFixture() {
    // Start one hour into the first day of the next calendar month.
    const now = new Date((await networkHelpers.time.latest()) * 1000);
    const nextMonth = Date.UTC(now.getUTCFullYear(), now.getUTCMonth() + 1, 1);
    await networkHelpers.time.increaseTo(nextMonth / 1000 + 3600);

    const [owner, funder, other, a, b, c, d1, d2, e, f, g] = await ethers.getSigners();

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
    const loyalty = await ethers.deployContract("MonthlyLoyaltyBonus", [
      managerAddress, nftAddress, poolAddress, await teamBusiness.getAddress(),
    ]);
    const loyaltyAddress = await loyalty.getAddress();

    await nft.setManager(managerAddress);
    await manager.setModule(await manager.REWARD_POOL(), poolAddress);
    await manager.setModule(await manager.REFERRAL_INCOME(), await referral.getAddress());
    await manager.setModule(await manager.TEAM_BUSINESS(), await teamBusiness.getAddress());
    await rewardPool.setSpender(loyaltyAddress, true);

    await token.transfer(funder.address, tnex(100_000));
    for (const u of [other, a, b, c, d1, d2, e, f, g]) {
      await token.transfer(u.address, tnex(20_000));
    }

    async function buy(user: any, pkg: number) {
      await token.connect(user).approve(nftAddress, tnex(pkg));
      await nft.connect(user).buyNFT(pkg);
    }

    async function sponsor(child: any, parent: any) {
      await referral.connect(child).setSponsor(parent.address);
    }

    // Moves to `days` days after the end of the current month (+1 hour).
    async function afterMonthEnd(days = 0) {
      const month = await loyalty.currentMonth();
      const end = await loyalty.monthEndDay(month);
      await networkHelpers.time.increaseTo(Number(end + BigInt(days)) * DAY + 3600);
      return month;
    }

    // In one month:
    //   a: directs d1 (2,500) and d2 (500)  -> 3,000, qualifies
    //   c: direct f (5,000)                 -> 5,000, qualifies
    //   b: direct e (2,500)                 -> 2,500, does not
    // Total business: a 50 + b 50 + c 50 + 2,500 + 500 + 5,000 + 2,500 = 10,650
    async function standardMonth() {
      for (const top of [a, b, c]) await buy(top, 50);
      await sponsor(d1, a);
      await sponsor(d2, a);
      await sponsor(e, b);
      await sponsor(f, c);
      await buy(d1, 2500);
      await buy(d2, 500);
      await buy(e, 2500);
      await buy(f, 5000);
    }

    async function fund(amount: bigint) {
      await rewardPool.addRevenueSource(TEST_REVENUE, "Test-only revenue source");
      await rewardPool.setDepositor(funder.address, TEST_REVENUE, true);
      await rewardPool.setAllocation(loyaltyAddress, TEST_REVENUE, 10_000n);
      await token.connect(funder).approve(poolAddress, amount);
      await rewardPool.connect(funder).deposit(amount, TEST_REVENUE);
    }

    return {
      owner, funder, other, a, b, c, d1, d2, e, f, g,
      token, nft, manager, rewardPool, referral, teamBusiness, loyalty,
      buy, sponsor, afterMonthEnd, standardMonth, fund,
    };
  }

  describe("Calendar months", function () {
    it("Should match UTC calendar months, including leap years and year ends", async function () {
      const { loyalty } = await deployFixture();

      const dates = [
        Date.UTC(2026, 9, 10, 12),
        Date.UTC(2026, 11, 31, 23, 59, 59),
        Date.UTC(2027, 0, 1),
        Date.UTC(2028, 1, 29, 10),
        Date.UTC(2028, 2, 1),
        Date.UTC(2100, 1, 28, 23),
        Date.UTC(2100, 2, 1),
        Date.UTC(2400, 1, 29),
      ];

      for (const ms of dates) {
        expect(await loyalty.monthOf(BigInt(ms / 1000))).to.equal(monthId(new Date(ms)));
      }
    });

    it("Should give each month's first and following day", async function () {
      const { loyalty } = await deployFixture();

      const feb2028 = BigInt(2028 * 12 + 1);
      const feb2100 = BigInt(2100 * 12 + 1);
      const dec2026 = BigInt(2026 * 12 + 11);

      expect(await loyalty.monthStartDay(feb2028)).to.equal(dayOf(Date.UTC(2028, 1, 1)));
      expect(await loyalty.monthEndDay(feb2028)).to.equal(dayOf(Date.UTC(2028, 2, 1)));
      expect((await loyalty.monthEndDay(feb2028)) - (await loyalty.monthStartDay(feb2028))).to.equal(29n);
      expect((await loyalty.monthEndDay(feb2100)) - (await loyalty.monthStartDay(feb2100))).to.equal(28n);
      expect(await loyalty.monthEndDay(dec2026)).to.equal(dayOf(Date.UTC(2027, 0, 1)));
    });
  });

  describe("Business", function () {
    it("Should count only direct referrals' purchases, not own or deeper", async function () {
      const { loyalty, buy, sponsor, a, d1, g } = await deployFixture();

      await buy(a, 1000);
      await sponsor(d1, a);
      await buy(d1, 500);
      await sponsor(g, d1);
      await buy(g, 2500); // level 2 for a

      const month = await loyalty.currentMonth();

      expect(await loyalty.directBusinessOf(a.address, month)).to.equal(tnex(500));
      expect(await loyalty.directBusinessOf(d1.address, month)).to.equal(tnex(2500));
    });

    it("Should take 5% of the whole project's business as the pool", async function () {
      const { loyalty, standardMonth } = await deployFixture();

      await standardMonth();

      const month = await loyalty.currentMonth();

      expect(await loyalty.poolOf(month)).to.equal((tnex(10_650) * 500n) / 10_000n);
    });

    it("Should keep months separate", async function () {
      const { loyalty, buy, sponsor, afterMonthEnd, a, d1 } = await deployFixture();

      await buy(a, 50);
      await sponsor(d1, a);
      await buy(d1, 2500);
      const month = await afterMonthEnd();
      await buy(d1, 500);

      expect(await loyalty.directBusinessOf(a.address, month)).to.equal(tnex(2500));
      expect(await loyalty.directBusinessOf(a.address, month + 1n)).to.equal(tnex(500));
    });
  });

  describe("Registration", function () {
    it("Should only open after the month ends, for 7 days", async function () {
      const { loyalty, standardMonth, afterMonthEnd, a } = await deployFixture();

      await standardMonth();
      const month = await loyalty.currentMonth();

      await expect(loyalty.register(month, [a.address])).to.be.revertedWith("Month not ended");

      await afterMonthEnd(7);

      await expect(loyalty.register(month, [a.address])).to.be.revertedWith(
        "Registration closed",
      );
    });

    it("Should register qualifiers and skip others and duplicates", async function () {
      const { loyalty, standardMonth, afterMonthEnd, a, b, c } = await deployFixture();

      await standardMonth();
      const month = await afterMonthEnd();

      await expect(loyalty.register(month, [a.address, b.address, a.address]))
        .to.emit(loyalty, "Registered")
        .withArgs(month, a.address, tnex(3000));

      await loyalty.register(month, [c.address, a.address]);

      expect(await loyalty.isRegistered(month, a.address)).to.equal(true);
      expect(await loyalty.isRegistered(month, b.address)).to.equal(false);
      expect(await loyalty.isRegistered(month, c.address)).to.equal(true);
      expect(await loyalty.qualifierCount(month)).to.equal(2n);
    });
  });

  describe("Settlement", function () {
    it("Should not settle while registration is open", async function () {
      const { loyalty, standardMonth, afterMonthEnd, a } = await deployFixture();

      await standardMonth();
      const month = await afterMonthEnd();
      await loyalty.register(month, [a.address]);

      await expect(loyalty.settle(month, [a.address])).to.be.revertedWith(
        "Registration open",
      );
    });

    it("Should split the pool equally among registered qualifiers", async function () {
      const { loyalty, standardMonth, afterMonthEnd, a, b, c } = await deployFixture();

      await standardMonth();
      const month = await afterMonthEnd();
      await loyalty.register(month, [a.address, b.address, c.address]);
      await afterMonthEnd(7);

      const share = (tnex(10_650) * 500n) / 10_000n / 2n;

      expect(await loyalty.shareOf(month)).to.equal(share);

      await expect(loyalty.settle(month, [a.address, b.address, c.address, a.address]))
        .to.emit(loyalty, "Settled")
        .withArgs(month, a.address, share);

      expect(await loyalty.unpaidOf(a.address)).to.equal(share);
      expect(await loyalty.unpaidOf(c.address)).to.equal(share);
      expect(await loyalty.unpaidOf(b.address)).to.equal(0n);

      await loyalty.settle(month, [a.address]);
      expect(await loyalty.unpaidOf(a.address)).to.equal(share);
    });
  });

  describe("Payouts", function () {
    it("Should pay a settled share from its own budget in a round", async function () {
      const { loyalty, token, standardMonth, afterMonthEnd, fund, a } = await deployFixture();

      await standardMonth();
      const month = await afterMonthEnd();
      await loyalty.register(month, [a.address]);
      await afterMonthEnd(7);
      await loyalty.settle(month, [a.address]);

      await fund(tnex(10_000));
      await loyalty.setPayoutsEnabled(true);
      await loyalty.startRound();

      const before = await token.balanceOf(a.address);

      await loyalty.claim([a.address]);

      // Only a registered: the whole 532.5 pool.
      expect((await token.balanceOf(a.address)) - before).to.equal(
        (tnex(10_650) * 500n) / 10_000n,
      );
    });
  });
});
