import { expect } from "chai";
import { network } from "hardhat";

const { ethers, networkHelpers } = await network.create();

const DAY = 24 * 60 * 60;
const PERIOD = 15 * DAY;

// Test-only revenue source. Real sources are not defined yet.
const TEST_REVENUE = ethers.id("TEST_REVENUE");

const TIERS: [number, bigint][] = [
  [500, 500n],
  [1_000, 700n],
  [2_500, 1000n],
  [5_000, 1200n],
  [10_000, 1500n],
  [25_000, 1700n],
  [50_000, 2000n],
  [100_000, 2500n],
];

const tnex = (v: string | number) => ethers.parseUnits(v.toString(), 18);

describe("BonusBooster", function () {
  async function deployFixture() {
    // Start one hour into a fresh UTC day so periods are predictable.
    const now = await networkHelpers.time.latest();
    await networkHelpers.time.increaseTo((Math.floor(now / DAY) + 1) * DAY + 3600);

    const [owner, funder, other, a, b, c, d] = await ethers.getSigners();

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
    const booster = await ethers.deployContract("BonusBooster", [
      managerAddress, nftAddress, poolAddress, await teamBusiness.getAddress(),
    ]);
    const boosterAddress = await booster.getAddress();

    await nft.setManager(managerAddress);
    await manager.setModule(await manager.REWARD_POOL(), poolAddress);
    await manager.setModule(await manager.REFERRAL_INCOME(), await referral.getAddress());
    await manager.setModule(await manager.TEAM_BUSINESS(), await teamBusiness.getAddress());
    await rewardPool.setSpender(boosterAddress, true);

    await token.transfer(funder.address, tnex(200_000));
    for (const u of [other, a, b, c, d]) {
      await token.transfer(u.address, tnex(50_000));
    }

    async function buy(user: any, pkg: number) {
      await token.connect(user).approve(nftAddress, tnex(pkg));
      return nft.connect(user).buyNFT(pkg);
    }

    async function toNextPeriod() {
      const now = BigInt(await networkHelpers.time.latest());
      const period = await booster.periodOf(now);
      await networkHelpers.time.increaseTo(await booster.periodEnd(period));
    }

    async function fund(amount: bigint) {
      await rewardPool.addRevenueSource(TEST_REVENUE, "Test-only revenue source");
      await rewardPool.setDepositor(funder.address, TEST_REVENUE, true);
      await rewardPool.setAllocation(boosterAddress, TEST_REVENUE, 10_000n);
      await token.connect(funder).approve(poolAddress, amount);
      await rewardPool.connect(funder).deposit(amount, TEST_REVENUE);
    }

    // a <- b <- c : a is the top.
    async function buildChain() {
      await buy(a, 50);
      await referral.connect(b).setSponsor(a.address);
      await buy(b, 50);
      await referral.connect(c).setSponsor(b.address);
    }

    return {
      owner, funder, other, a, b, c, d,
      token, nft, manager, rewardPool, referral, booster, teamBusiness,
      buy, toNextPeriod, fund, buildChain,
    };
  }

  describe("Business rules", function () {
    it("Should use the report's tiers", async function () {
      const { booster } = await deployFixture();

      for (const [business, bps] of TIERS) {
        expect(await booster.tierBps(tnex(business))).to.equal(bps);
        expect(await booster.tierBps(tnex(business) - 1n)).to.not.equal(bps);
      }
      expect(await booster.tierBps(tnex(499))).to.equal(0n);
      expect(await booster.tierBps(tnex(1_000_000))).to.equal(2500n);
    });

    it("Should use shared 15-day periods of whole UTC days from deployment", async function () {
      const { booster } = await deployFixture();

      const start = (await booster.startDay()) * BigInt(DAY);

      expect(await booster.PERIOD_DAYS()).to.equal(15n);
      expect(await booster.periodOf(start)).to.equal(0n);
      expect(await booster.periodOf(start + BigInt(PERIOD) - 1n)).to.equal(0n);
      expect(await booster.periodOf(start + BigInt(PERIOD))).to.equal(1n);
      expect(await booster.periodEnd(0)).to.equal(start + BigInt(PERIOD));
      await expect(booster.periodOf(start - 1n)).to.be.revertedWith("Before start");
    });

    it("Should reject a zero team business tracker", async function () {
      const { manager, nft, rewardPool } = await deployFixture();

      await expect(
        ethers.deployContract("BonusBooster", [
          await manager.getAddress(),
          await nft.getAddress(),
          await rewardPool.getAddress(),
          ethers.ZeroAddress,
        ]),
      ).to.be.revertedWith("Invalid team business");
    });
  });

  describe("Team business", function () {
    it("Should only accept purchases from the manager", async function () {
      const { teamBusiness, other } = await deployFixture();

      await expect(
        teamBusiness.connect(other).recordPurchase(other.address, tnex(100)),
      ).to.be.revertedWith("Only manager");
    });

    it("Should record every purchase at its package price", async function () {
      const { teamBusiness, buy, a } = await deployFixture();

      const day = await teamBusiness.currentDay();

      await expect(buy(a, 1000))
        .to.emit(teamBusiness, "PurchaseRecorded")
        .withArgs(0n, a.address, tnex(1000), day);

      expect(await teamBusiness.pendingJobs()).to.equal(1n);
    });

    it("Should keep lifetime and per-day business", async function () {
      const { teamBusiness, buildChain, buy, toNextPeriod, a, c } = await deployFixture();

      await buildChain();
      const day = await teamBusiness.currentDay();
      await buy(c, 1000);
      await toNextPeriod();
      await buy(c, 500);
      await teamBusiness.processQueue(100);

      expect(await teamBusiness.lifetimeBusiness(a.address)).to.equal(tnex(1600));
      expect(await teamBusiness.businessOnDay(a.address, day)).to.equal(tnex(1100));
      expect(await teamBusiness.businessBetween(a.address, day, day + 1n)).to.equal(tnex(1100));
      expect(await teamBusiness.businessBetween(a.address, day, day + 100n)).to.equal(tnex(1600));
    });

    it("Should report whether all purchases before a day are processed", async function () {
      const { teamBusiness, buy, a } = await deployFixture();

      const day = await teamBusiness.currentDay();

      expect(await teamBusiness.isProcessedBefore(day + 1n)).to.equal(true);

      await buy(a, 50);

      expect(await teamBusiness.isProcessedBefore(day)).to.equal(true);
      expect(await teamBusiness.isProcessedBefore(day + 1n)).to.equal(false);

      await teamBusiness.processQueue(10);

      expect(await teamBusiness.isProcessedBefore(day + 1n)).to.equal(true);
    });

    it("Should add a purchase to the buyer and every upline", async function () {
      const { booster, teamBusiness, buildChain, buy, a, b, c } = await deployFixture();

      await buildChain();
      await buy(c, 1000);
      await teamBusiness.processQueue(100);

      // a: 50 + 50 + 1000, b: 50 + 1000, c: 1000
      expect(await booster.businessOf(a.address, 0)).to.equal(tnex(1100));
      expect(await booster.businessOf(b.address, 0)).to.equal(tnex(1050));
      expect(await booster.businessOf(c.address, 0)).to.equal(tnex(1000));
      expect(await teamBusiness.pendingJobs()).to.equal(0n);
    });

    it("Should process in small batches", async function () {
      const { booster, teamBusiness, buildChain, buy, a, c } = await deployFixture();

      await buildChain();
      await teamBusiness.processQueue(100);
      await buy(c, 500);

      expect(await teamBusiness.processQueue.staticCall(2)).to.equal(2n);
      await teamBusiness.processQueue(2);
      expect(await teamBusiness.pendingJobs()).to.equal(1n);
      expect(await booster.businessOf(a.address, 0)).to.equal(tnex(100));

      await teamBusiness.processQueue(2);
      expect(await booster.businessOf(a.address, 0)).to.equal(tnex(600));
      expect(await teamBusiness.pendingJobs()).to.equal(0n);
    });

    it("Should keep each period's business separate", async function () {
      const { booster, teamBusiness, buy, toNextPeriod, a } = await deployFixture();

      await buy(a, 1000);
      await toNextPeriod();
      await buy(a, 500);
      await teamBusiness.processQueue(100);

      expect(await booster.businessOf(a.address, 0)).to.equal(tnex(1000));
      expect(await booster.businessOf(a.address, 1)).to.equal(tnex(500));
    });
  });

  describe("Settlement", function () {
    it("Should not settle a period that has not ended", async function () {
      const { booster, teamBusiness, buy, a } = await deployFixture();

      await buy(a, 1000);
      await teamBusiness.processQueue(100);

      expect(await booster.isSettleable(0)).to.equal(false);
      await expect(booster.settle(0, [a.address])).to.be.revertedWith(
        "Period not settleable",
      );
    });

    it("Should not settle while purchases of the period are unprocessed", async function () {
      const { booster, teamBusiness, buy, toNextPeriod, a } = await deployFixture();

      await buy(a, 1000);
      await toNextPeriod();

      expect(await booster.isSettleable(0)).to.equal(false);

      await teamBusiness.processQueue(100);

      expect(await booster.isSettleable(0)).to.equal(true);
    });

    it("Should not wait for purchases from later periods", async function () {
      const { booster, teamBusiness, buy, toNextPeriod, a } = await deployFixture();

      await buy(a, 1000);
      await teamBusiness.processQueue(100);
      await toNextPeriod();
      await buy(a, 500); // period 1, unprocessed

      expect(await booster.isSettleable(0)).to.equal(true);
    });

    it("Should credit business times the reached tier", async function () {
      const { booster, teamBusiness, buildChain, buy, toNextPeriod, a, b, c } = await deployFixture();

      await buildChain();
      await buy(c, 2500);
      await teamBusiness.processQueue(100);
      await toNextPeriod();

      // a: 2600 -> 10%, b: 2550 -> 10%, c: 2500 -> 10%
      await expect(booster.settle(0, [a.address, b.address, c.address]))
        .to.emit(booster, "Settled")
        .withArgs(a.address, 0n, tnex(2600), 1000n, tnex(260));

      expect(await booster.unpaidOf(a.address)).to.equal(tnex(260));
      expect(await booster.unpaidOf(b.address)).to.equal(tnex(255));
      expect(await booster.unpaidOf(c.address)).to.equal(tnex(250));
    });

    it("Should credit nothing below the lowest tier", async function () {
      const { booster, teamBusiness, buy, toNextPeriod, a } = await deployFixture();

      await buy(a, 250);
      await teamBusiness.processQueue(100);
      await toNextPeriod();

      await expect(booster.settle(0, [a.address]))
        .to.emit(booster, "Settled")
        .withArgs(a.address, 0n, tnex(250), 0n, 0n);

      expect(await booster.unpaidOf(a.address)).to.equal(0n);
    });

    it("Should settle each account once per period", async function () {
      const { booster, teamBusiness, buy, toNextPeriod, a } = await deployFixture();

      await buy(a, 1000);
      await teamBusiness.processQueue(100);
      await toNextPeriod();

      await booster.settle(0, [a.address, a.address]);
      await booster.settle(0, [a.address]);

      expect(await booster.unpaidOf(a.address)).to.equal(tnex(70));
      expect(await booster.isSettled(a.address, 0)).to.equal(true);
    });

    it("Should start every period from zero", async function () {
      const { booster, teamBusiness, buy, toNextPeriod, a } = await deployFixture();

      await buy(a, 1000);
      await toNextPeriod();
      await buy(a, 250);
      await teamBusiness.processQueue(100);
      await toNextPeriod();

      await booster.settle(0, [a.address]);
      await booster.settle(1, [a.address]);

      // Period 0: 1000 -> 7% = 70. Period 1: 250 -> no tier.
      expect(await booster.unpaidOf(a.address)).to.equal(tnex(70));
    });
  });

  describe("Payouts", function () {
    it("Should pay a settled bonus from its own budget in a round", async function () {
      const { booster, teamBusiness, token, buy, toNextPeriod, fund, a } = await deployFixture();

      await buy(a, 1000);
      await teamBusiness.processQueue(100);
      await toNextPeriod();
      await booster.settle(0, [a.address]);

      await fund(tnex(1000));
      await booster.setPayoutsEnabled(true);
      await booster.startRound();

      const before = await token.balanceOf(a.address);

      await booster.claim([a.address]);

      expect((await token.balanceOf(a.address)) - before).to.equal(tnex(70));
    });

    it("Should start with payouts disabled and require funding to enable", async function () {
      const { booster } = await deployFixture();

      expect(await booster.payoutsEnabled()).to.equal(false);
      await expect(booster.setPayoutsEnabled(true)).to.be.revertedWith(
        "Funding not configured",
      );
    });
  });
});
