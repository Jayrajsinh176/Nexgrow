import { expect } from "chai";
import { network } from "hardhat";

const { ethers } = await network.create();

// Test-only revenue source. Real sources are not defined yet.
const TEST_REVENUE = ethers.id("TEST_REVENUE");

const TIERS: [number, number][] = [
  [10_000, 500],
  [50_000, 2_000],
  [200_000, 6_000],
  [1_000_000, 20_000],
  [5_000_000, 100_000],
  [50_000_000, 1_000_000],
  [500_000_000, 10_000_000],
];

const tnex = (v: string | number) => ethers.parseUnits(v.toString(), 18);

describe("TeamPerformanceBonus", function () {
  async function deployFixture() {
    const [owner, funder, other, top, l1, l2, l3, l4, child] = await ethers.getSigners();

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
    const performance = await ethers.deployContract("TeamPerformanceBonus", [
      managerAddress, nftAddress, poolAddress, referralAddress,
      await teamBusiness.getAddress(),
    ]);
    const performanceAddress = await performance.getAddress();

    await nft.setManager(managerAddress);
    await manager.setModule(await manager.REWARD_POOL(), poolAddress);
    await manager.setModule(await manager.REFERRAL_INCOME(), referralAddress);
    await manager.setModule(await manager.TEAM_BUSINESS(), await teamBusiness.getAddress());
    await rewardPool.setSpender(performanceAddress, true);

    await token.transfer(funder.address, tnex(100_000));
    for (const u of [top, l1, l2, l3, l4, child]) {
      await token.transfer(u.address, tnex(50_000));
    }

    async function buy(user: any, pkg: number, times = 1) {
      for (let i = 0; i < times; i++) {
        await token.connect(user).approve(nftAddress, tnex(pkg));
        await nft.connect(user).buyNFT(pkg);
      }
    }

    // top has 4 legs: l1..l4. l4 sponsors child.
    async function buildLegs() {
      await buy(top, 50);
      for (const leg of [l1, l2, l3, l4]) {
        await referral.connect(leg).setSponsor(top.address);
      }
      await buy(l4, 50);
      await referral.connect(child).setSponsor(l4.address);
    }

    const legs = [l1, l2, l3, l4].map((l) => l.address) as [string, string, string, string];

    async function fund(amount: bigint) {
      await rewardPool.addRevenueSource(TEST_REVENUE, "Test-only revenue source");
      await rewardPool.setDepositor(funder.address, TEST_REVENUE, true);
      await rewardPool.setAllocation(performanceAddress, TEST_REVENUE, 10_000n);
      await token.connect(funder).approve(poolAddress, amount);
      await rewardPool.connect(funder).deposit(amount, TEST_REVENUE);
    }

    return {
      owner, funder, other, top, l1, l2, l3, l4, child, legs,
      token, nft, manager, rewardPool, referral, teamBusiness, performance,
      buy, buildLegs, fund,
    };
  }

  describe("Business rules", function () {
    it("Should use the report's targets and bonuses", async function () {
      const { performance } = await deployFixture();

      for (let i = 0; i < TIERS.length; i++) {
        const [target, bonus] = await performance.tier(i);
        expect(target).to.equal(tnex(TIERS[i][0]));
        expect(bonus).to.equal(tnex(TIERS[i][1]));
      }
      expect(await performance.TIER_COUNT()).to.equal(BigInt(TIERS.length));
      await expect(performance.tier(TIERS.length)).to.be.revertedWith("Invalid tier");
    });

    it("Should need 40% in the largest leg and 20% in the smallest", async function () {
      const { performance } = await deployFixture();

      const t = tnex(10_000);

      expect(await performance.meetsTarget(tnex(4_000), tnex(2_000), t)).to.equal(true);
      expect(await performance.meetsTarget(tnex(4_000) - 1n, tnex(2_000), t)).to.equal(false);
      expect(await performance.meetsTarget(tnex(4_000), tnex(2_000) - 1n, t)).to.equal(false);
      // A huge leg cannot cover a small one.
      expect(await performance.meetsTarget(tnex(1_000_000), tnex(1_999), t)).to.equal(false);
    });

    it("Should reject zero addresses for its dependencies", async function () {
      const { manager, nft, rewardPool, referral, teamBusiness } = await deployFixture();

      const base = [
        await manager.getAddress(),
        await nft.getAddress(),
        await rewardPool.getAddress(),
      ];

      await expect(
        ethers.deployContract("TeamPerformanceBonus", [
          ...base, ethers.ZeroAddress, await teamBusiness.getAddress(),
        ]),
      ).to.be.revertedWith("Invalid sponsor tree");
      await expect(
        ethers.deployContract("TeamPerformanceBonus", [
          ...base, await referral.getAddress(), ethers.ZeroAddress,
        ]),
      ).to.be.revertedWith("Invalid team business");
    });
  });

  describe("Qualification", function () {
    it("Should award the first target when 4 legs reach their shares", async function () {
      const { performance, teamBusiness, buildLegs, buy, top, l1, l2, l3, l4, legs } =
        await deployFixture();

      await buildLegs();
      await buy(l1, 2500, 2); // 5,000 >= 4,000
      await buy(l2, 2500);
      await buy(l3, 2500);
      await buy(l4, 2500); // l4: 50 + 2,500
      await teamBusiness.processQueue(1000);

      await expect(performance.qualify(top.address, legs))
        .to.emit(performance, "TargetReached")
        .withArgs(top.address, 0n, tnex(10_000), tnex(500));

      expect(await performance.unpaidOf(top.address)).to.equal(tnex(500));
      expect(await performance.nextTier(top.address)).to.equal(1n);
    });

    it("Should count a leg's downline in its business", async function () {
      const { performance, teamBusiness, buildLegs, buy, top, l1, l2, l3, child, legs } =
        await deployFixture();

      await buildLegs();
      await buy(l1, 5000);
      await buy(l2, 2500);
      await buy(l3, 2500);
      await buy(child, 2500); // counts for l4's leg
      await teamBusiness.processQueue(1000);

      await performance.qualify(top.address, legs);

      expect(await performance.unpaidOf(top.address)).to.equal(tnex(500));
    });

    it("Should not let one leg's surplus cover another's shortfall", async function () {
      const { performance, teamBusiness, buildLegs, buy, top, l1, l2, l3, legs } =
        await deployFixture();

      await buildLegs();
      await buy(l1, 5000, 4); // 20,000
      await buy(l2, 2500);
      await buy(l3, 2500);
      // l4 only has 50.
      await teamBusiness.processQueue(1000);

      await expect(performance.qualify(top.address, legs)).to.be.revertedWith(
        "No new target reached",
      );
    });

    it("Should award several targets in one call and each only once", async function () {
      const { performance, teamBusiness, buildLegs, buy, top, l1, l2, l3, l4, legs } =
        await deployFixture();

      await buildLegs();
      await buy(l1, 5000, 4); // 20,000 = 40% of 50,000
      await buy(l2, 5000, 2); // 10,000 = 20% of 50,000
      await buy(l3, 5000, 2);
      await buy(l4, 5000, 2);
      await teamBusiness.processQueue(1000);

      await performance.qualify(top.address, legs);

      expect(await performance.unpaidOf(top.address)).to.equal(tnex(2_500));
      expect(await performance.nextTier(top.address)).to.equal(2n);

      await expect(performance.qualify(top.address, legs)).to.be.revertedWith(
        "No new target reached",
      );
    });

    it("Should only count processed purchases", async function () {
      const { performance, teamBusiness, buildLegs, buy, top, l1, l2, l3, l4, legs } =
        await deployFixture();

      await buildLegs();
      await buy(l1, 5000);
      await buy(l2, 2500);
      await buy(l3, 2500);
      await buy(l4, 2500);

      await expect(performance.qualify(top.address, legs)).to.be.revertedWith(
        "No new target reached",
      );

      await teamBusiness.processQueue(1000);
      await performance.qualify(top.address, legs);
    });

    it("Should reject legs that are not direct referrals", async function () {
      const { performance, buildLegs, top, l1, l2, l3, child } = await deployFixture();

      await buildLegs();

      await expect(
        performance.qualify(top.address, [l1.address, l2.address, l3.address, child.address]),
      ).to.be.revertedWith("Not a direct leg");
    });

    it("Should reject the same leg twice", async function () {
      const { performance, buildLegs, top, l1, l2, l3 } = await deployFixture();

      await buildLegs();

      await expect(
        performance.qualify(top.address, [l1.address, l2.address, l3.address, l1.address]),
      ).to.be.revertedWith("Duplicate leg");
    });
  });

  describe("Payouts", function () {
    it("Should pay an awarded bonus from its own budget in a round", async function () {
      const { performance, teamBusiness, token, buildLegs, buy, fund, top, l1, l2, l3, l4, legs } =
        await deployFixture();

      await buildLegs();
      await buy(l1, 5000);
      await buy(l2, 2500);
      await buy(l3, 2500);
      await buy(l4, 2500);
      await teamBusiness.processQueue(1000);
      await performance.qualify(top.address, legs);

      await fund(tnex(1_000));
      await performance.setPayoutsEnabled(true);
      await performance.startRound();

      const before = await token.balanceOf(top.address);

      await performance.claim([top.address]);

      expect((await token.balanceOf(top.address)) - before).to.equal(tnex(500));
    });
  });
});
