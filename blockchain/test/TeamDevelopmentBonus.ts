import { expect } from "chai";
import { network } from "hardhat";

const { ethers, networkHelpers } = await network.create();

const DAY = 24 * 60 * 60;

// Test-only revenue source. Real sources are not defined yet.
const TEST_REVENUE = ethers.id("TEST_REVENUE");

const tnex = (v: string | number) => ethers.parseUnits(v.toString(), 18);

// Same moment `months` calendar months later, clamped to the month's end.
function addMonths(ts: number, months: number) {
  const d = new Date(ts * 1000);
  const y = d.getUTCFullYear();
  const m = d.getUTCMonth() + months;
  const lastDay = new Date(Date.UTC(y, m + 1, 0)).getUTCDate();
  const day = Math.min(d.getUTCDate(), lastDay);
  return (
    Date.UTC(y, m, day, d.getUTCHours(), d.getUTCMinutes(), d.getUTCSeconds()) / 1000
  );
}

describe("TeamDevelopmentBonus", function () {
  async function deployFixture() {
    // Start one hour into a fresh UTC day so booster periods are predictable.
    const now = await networkHelpers.time.latest();
    await networkHelpers.time.increaseTo((Math.floor(now / DAY) + 1) * DAY + 3600);

    const [owner, funder, other, top, l1, l2, l3, l4] = await ethers.getSigners();

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
    const referralAddress = await referral.getAddress();
    const teamBusiness = await ethers.deployContract("TeamBusiness", [
      managerAddress, referralAddress,
    ]);
    const teamBusinessAddress = await teamBusiness.getAddress();
    const teamBuilding = await ethers.deployContract("TeamBuildingIncome", [
      managerAddress, nftAddress, poolAddress, referralAddress,
    ]);
    const booster = await ethers.deployContract("BonusBooster", [
      managerAddress, nftAddress, poolAddress, teamBusinessAddress,
    ]);
    const performance = await ethers.deployContract("TeamPerformanceBonus", [
      managerAddress, nftAddress, poolAddress, referralAddress, teamBusinessAddress,
    ]);
    const dev = await ethers.deployContract("TeamDevelopmentBonus", [
      managerAddress, nftAddress, poolAddress, referralAddress, teamBusinessAddress,
    ]);
    const devAddress = await dev.getAddress();

    await nft.setManager(managerAddress);
    await manager.setModule(await manager.REWARD_POOL(), poolAddress);
    await manager.setModule(await manager.REFERRAL_INCOME(), referralAddress);
    await manager.setModule(await manager.TEAM_BUSINESS(), teamBusinessAddress);
    await manager.setModule(await manager.TEAM_DEVELOPMENT(), devAddress);
    await rewardPool.setSpender(devAddress, true);

    for (const m of [teamBuilding, booster, performance]) {
      await dev.setEligibleSource(await m.getAddress(), true);
    }

    await token.transfer(funder.address, tnex(100_000));
    for (const u of [top, l1, l2, l3, l4]) {
      await token.transfer(u.address, tnex(20_000));
    }

    async function buy(user: any, pkg: number) {
      await token.connect(user).approve(nftAddress, tnex(pkg));
      await nft.connect(user).buyNFT(pkg);
    }

    const legs = [l1, l2, l3].map((l) => l.address) as [string, string, string];

    // top has legs l1..l4; l1..l3 each reach 5,000 (15,000 in total).
    async function qualifyingTeam() {
      await buy(top, 50);
      for (const leg of [l1, l2, l3, l4]) {
        await referral.connect(leg).setSponsor(top.address);
      }
      for (const leg of [l1, l2, l3]) await buy(leg, 5000);
      await teamBusiness.processQueue(1000);
    }

    async function fund(amount: bigint) {
      await rewardPool.addRevenueSource(TEST_REVENUE, "Test-only revenue source");
      await rewardPool.setDepositor(funder.address, TEST_REVENUE, true);
      await rewardPool.setAllocation(devAddress, TEST_REVENUE, 10_000n);
      await token.connect(funder).approve(poolAddress, amount);
      await rewardPool.connect(funder).deposit(amount, TEST_REVENUE);
    }

    return {
      owner, funder, other, top, l1, l2, l3, l4, legs,
      token, manager, rewardPool, referral, teamBusiness,
      teamBuilding, booster, performance, dev,
      buy, qualifyingTeam, fund,
    };
  }

  describe("Configuration", function () {
    it("Should start with 5%, 12 months and the three team bonuses eligible", async function () {
      const { dev, teamBuilding, booster, performance, referral } = await deployFixture();

      expect(await dev.extraBps()).to.equal(500n);
      expect(await dev.windowMonths()).to.equal(12n);
      expect(await dev.isEligibleSource(await teamBuilding.getAddress())).to.equal(true);
      expect(await dev.isEligibleSource(await booster.getAddress())).to.equal(true);
      expect(await dev.isEligibleSource(await performance.getAddress())).to.equal(true);
      expect(await dev.isEligibleSource(await referral.getAddress())).to.equal(false);
    });

    it("Should let only the owner change it, within limits", async function () {
      const { dev, other } = await deployFixture();

      await expect(dev.connect(other).setExtraBps(700))
        .to.be.revertedWithCustomError(dev, "OwnableUnauthorizedAccount")
        .withArgs(other.address);
      await expect(dev.connect(other).setWindowMonths(6))
        .to.be.revertedWithCustomError(dev, "OwnableUnauthorizedAccount")
        .withArgs(other.address);
      await expect(dev.connect(other).setEligibleSource(other.address, true))
        .to.be.revertedWithCustomError(dev, "OwnableUnauthorizedAccount")
        .withArgs(other.address);

      await expect(dev.setExtraBps(10_001)).to.be.revertedWith("Extra too high");
      await expect(dev.setWindowMonths(0)).to.be.revertedWith("Invalid window");
      await expect(dev.setWindowMonths(121)).to.be.revertedWith("Invalid window");
      await expect(dev.setEligibleSource(ethers.ZeroAddress, true)).to.be.revertedWith(
        "Invalid module",
      );
      await expect(dev.setEligibleSource(await dev.getAddress(), true)).to.be.revertedWith(
        "Cannot be eligible itself",
      );

      await expect(dev.setExtraBps(700)).to.emit(dev, "ExtraBpsUpdated").withArgs(700n);
      await expect(dev.setWindowMonths(6)).to.emit(dev, "WindowMonthsUpdated").withArgs(6n);
    });
  });

  describe("Qualification", function () {
    it("Should qualify with 3 legs of 5,000 and open a 12-month window", async function () {
      const { dev, qualifyingTeam, top, legs } = await deployFixture();

      await qualifyingTeam();

      const tx = await dev.qualify(top.address, legs);
      const start = (await ethers.provider.getBlock(tx.blockNumber!))!.timestamp;

      await expect(tx)
        .to.emit(dev, "Qualified")
        .withArgs(top.address, start, addMonths(start, 12));

      expect(await dev.isQualified(top.address)).to.equal(true);
      expect(await dev.isInWindow(top.address)).to.equal(true);
    });

    it("Should not pay anything for the qualification business itself", async function () {
      const { dev, qualifyingTeam, top, legs } = await deployFixture();

      await qualifyingTeam();
      await dev.qualify(top.address, legs);

      expect(await dev.unpaidOf(top.address)).to.equal(0n);
    });

    it("Should qualify only once for life", async function () {
      const { dev, qualifyingTeam, top, legs } = await deployFixture();

      await qualifyingTeam();
      await dev.qualify(top.address, legs);

      await expect(dev.qualify(top.address, legs)).to.be.revertedWith("Already qualified");
    });

    it("Should reject a leg below 5,000", async function () {
      const { dev, qualifyingTeam, top, l1, l2, l4 } = await deployFixture();

      await qualifyingTeam();

      await expect(
        dev.qualify(top.address, [l1.address, l2.address, l4.address]),
      ).to.be.revertedWith("Leg below 5,000");
    });

    it("Should reject legs that are not direct or are repeated", async function () {
      const { dev, qualifyingTeam, top, other, l1, l2 } = await deployFixture();

      await qualifyingTeam();

      await expect(
        dev.qualify(top.address, [l1.address, l2.address, other.address]),
      ).to.be.revertedWith("Not a direct leg");
      await expect(
        dev.qualify(top.address, [l1.address, l2.address, l1.address]),
      ).to.be.revertedWith("Duplicate leg");
    });

    it("Should clamp the window end to the last day of a shorter month", async function () {
      const { dev, qualifyingTeam, top, legs } = await deployFixture();

      await qualifyingTeam();
      await dev.setWindowMonths(1);

      // Move to the next 31 January, noon UTC.
      const now = new Date((await networkHelpers.time.latest()) * 1000);
      const jan31 = Date.UTC(now.getUTCFullYear() + 1, 0, 31, 12) / 1000;
      await networkHelpers.time.setNextBlockTimestamp(jan31);

      await dev.qualify(top.address, legs);

      const feb = new Date(Number(await dev.windowEnd(top.address)) * 1000);
      expect(feb.getUTCMonth()).to.equal(1);
      expect(feb.getUTCDate()).to.equal(
        new Date(Date.UTC(feb.getUTCFullYear(), 2, 0)).getUTCDate(),
      );
      expect(feb.getUTCHours()).to.equal(12);
    });
  });

  describe("Extra on eligible bonuses", function () {
    it("Should add 5% to a bonus from an eligible module (1,000 -> 50)", async function () {
      const { dev, qualifyingTeam, top, legs, other } = await deployFixture();

      await qualifyingTeam();
      await dev.qualify(top.address, legs);
      await dev.setEligibleSource(other.address, true);

      await expect(dev.connect(other).recordBonus(top.address, tnex(1000)))
        .to.emit(dev, "ExtraCredited")
        .withArgs(top.address, other.address, tnex(1000), tnex(50));

      expect(await dev.unpaidOf(top.address)).to.equal(tnex(50));
    });

    it("Should ignore bonuses from modules that are not eligible", async function () {
      const { dev, qualifyingTeam, top, legs, other } = await deployFixture();

      await qualifyingTeam();
      await dev.qualify(top.address, legs);

      await dev.connect(other).recordBonus(top.address, tnex(1000));

      expect(await dev.unpaidOf(top.address)).to.equal(0n);
    });

    it("Should ignore bonuses of wallets that are not qualified", async function () {
      const { dev, top, other } = await deployFixture();

      await dev.setEligibleSource(other.address, true);
      await dev.connect(other).recordBonus(top.address, tnex(1000));

      expect(await dev.unpaidOf(top.address)).to.equal(0n);
    });

    it("Should stop adding the extra when the window ends", async function () {
      const { dev, qualifyingTeam, top, legs, other } = await deployFixture();

      await qualifyingTeam();
      await dev.qualify(top.address, legs);
      await dev.setEligibleSource(other.address, true);

      await networkHelpers.time.increaseTo(await dev.windowEnd(top.address));

      expect(await dev.isInWindow(top.address)).to.equal(false);

      await dev.connect(other).recordBonus(top.address, tnex(1000));

      expect(await dev.unpaidOf(top.address)).to.equal(0n);
    });

    it("Should use the current percentage when a bonus is credited", async function () {
      const { dev, qualifyingTeam, top, legs, other } = await deployFixture();

      await qualifyingTeam();
      await dev.qualify(top.address, legs);
      await dev.setEligibleSource(other.address, true);
      await dev.setExtraBps(1000);

      await dev.connect(other).recordBonus(top.address, tnex(1000));

      expect(await dev.unpaidOf(top.address)).to.equal(tnex(100));
    });

    it("Should add 5% to a 15-Day Booster bonus", async function () {
      const { dev, booster, qualifyingTeam, top, legs } = await deployFixture();

      await qualifyingTeam();
      await dev.qualify(top.address, legs);

      // top's team business this period: 50 + 15,000 -> 15% tier.
      const now = BigInt(await networkHelpers.time.latest());
      const period = await booster.periodOf(now);
      await networkHelpers.time.increaseTo(await booster.periodEnd(period));
      await booster.settle(period, [top.address]);

      const bonus = (tnex(15_050) * 1500n) / 10_000n;

      expect(await booster.unpaidOf(top.address)).to.equal(bonus);
      expect(await dev.unpaidOf(top.address)).to.equal((bonus * 500n) / 10_000n);
    });

    it("Should add 5% to a Team Performance bonus", async function () {
      const { dev, performance, teamBusiness, qualifyingTeam, buy, top, legs, l4 } =
        await deployFixture();

      await qualifyingTeam();
      await dev.qualify(top.address, legs);

      await buy(l4, 2500);
      await teamBusiness.processQueue(1000);
      await performance.qualify(top.address, [...legs, l4.address]);

      expect(await performance.unpaidOf(top.address)).to.equal(tnex(500));
      expect(await dev.unpaidOf(top.address)).to.equal(tnex(25));
    });

    it("Should not add anything once a module is made ineligible", async function () {
      const { dev, performance, teamBusiness, qualifyingTeam, buy, top, legs, l4 } =
        await deployFixture();

      await qualifyingTeam();
      await dev.qualify(top.address, legs);
      await dev.setEligibleSource(await performance.getAddress(), false);

      await buy(l4, 2500);
      await teamBusiness.processQueue(1000);
      await performance.qualify(top.address, [...legs, l4.address]);

      expect(await dev.unpaidOf(top.address)).to.equal(0n);
    });
  });

  describe("Payouts", function () {
    it("Should pay the extra from its own budget in a round", async function () {
      const { dev, token, qualifyingTeam, fund, top, legs, other } = await deployFixture();

      await qualifyingTeam();
      await dev.qualify(top.address, legs);
      await dev.setEligibleSource(other.address, true);
      await dev.connect(other).recordBonus(top.address, tnex(1000));

      await fund(tnex(1000));
      await dev.setPayoutsEnabled(true);
      await dev.startRound();

      const before = await token.balanceOf(top.address);

      await dev.claim([top.address]);

      expect((await token.balanceOf(top.address)) - before).to.equal(tnex(50));
    });
  });
});
