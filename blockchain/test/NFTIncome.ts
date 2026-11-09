import { expect } from "chai";
import { network } from "hardhat";

const { ethers, networkHelpers } = await network.create();

const DAY = 24 * 60 * 60;

// Test-only revenue source. Real sources are not defined yet.
const TEST_REVENUE = ethers.id("TEST_REVENUE");

const RATES: Record<number, string> = {
  50: "0.30",
  100: "0.40",
  250: "0.50",
  500: "0.60",
  1000: "0.70",
  2500: "0.80",
  5000: "1.00",
};

const tnex = (v: string | number) => ethers.parseUnits(v.toString(), 18);

describe("NFTIncome", function () {
  async function deployFixture() {
    // Start one hour into a fresh UTC day so day maths is predictable.
    const now = await networkHelpers.time.latest();
    await networkHelpers.time.increaseTo((Math.floor(now / DAY) + 1) * DAY + 3600);

    const [owner, alice, bob, funder, other] = await ethers.getSigners();

    const token = await ethers.deployContract("TestNexadonToken");
    const tokenAddress = await token.getAddress();

    const treasury = await ethers.deployContract("NexgrowTreasury", [tokenAddress]);
    const nft = await ethers.deployContract("NexgrowNFT", [
      tokenAddress,
      await treasury.getAddress(),
    ]);
    const manager = await ethers.deployContract("NexgrowManager", [
      await nft.getAddress(),
    ]);
    const rewardPool = await ethers.deployContract("RewardPool", [tokenAddress]);
    const nftIncome = await ethers.deployContract("NFTIncome", [
      await manager.getAddress(),
      await nft.getAddress(),
      await rewardPool.getAddress(),
    ]);
    const nftIncomeAddress = await nftIncome.getAddress();

    await nft.setManager(await manager.getAddress());
    await manager.setModule(await manager.REWARD_POOL(), await rewardPool.getAddress());
    await manager.setModule(await manager.NFT_INCOME(), nftIncomeAddress);
    await rewardPool.setSpender(nftIncomeAddress, true);

    for (const s of [alice, bob, funder, other]) {
      await token.transfer(s.address, tnex(50_000));
    }

    async function buy(user: any, pkg: number) {
      await token.connect(user).approve(await nft.getAddress(), tnex(pkg));
      return nft.connect(user).buyNFT(pkg);
    }

    let sourceReady = false;

    // The 3 required steps, using a test-only source with 100% to NFTIncome.
    async function configureFunding() {
      if (sourceReady) return;

      await rewardPool.addRevenueSource(TEST_REVENUE, "Test-only revenue source");
      await rewardPool.setDepositor(funder.address, TEST_REVENUE, true);
      await rewardPool.setAllocation(nftIncomeAddress, TEST_REVENUE, 10_000n);
      sourceReady = true;
    }

    async function enablePayouts() {
      await configureFunding();
      await nftIncome.setPayoutsEnabled(true);
    }

    async function fundIncome(amount: bigint) {
      await configureFunding();
      await token.connect(funder).approve(await rewardPool.getAddress(), amount);
      await rewardPool.connect(funder).deposit(amount, TEST_REVENUE);
    }

    async function passDays(days: number) {
      await networkHelpers.time.increase(days * DAY);
    }

    return {
      owner, alice, bob, funder, other,
      token, treasury, nft, manager, rewardPool, nftIncome, nftIncomeAddress,
      buy, fundIncome, configureFunding, enablePayouts, passDays,
    };
  }

  describe("Business rules", function () {
    it("Should use the report's daily rates for every package", async function () {
      const { nftIncome } = await deployFixture();

      for (const [pkg, rate] of Object.entries(RATES)) {
        expect(await nftIncome.dailyRateFor(pkg)).to.equal(tnex(rate));
      }
    });

    it("Should use a 4X cap", async function () {
      const { nftIncome } = await deployFixture();

      expect(await nftIncome.CAP_MULTIPLIER()).to.equal(4n);
      expect(await nftIncome.capFor(1000)).to.equal(tnex(4000));
      expect(await nftIncome.capFor(50)).to.equal(tnex(200));
    });

    it("Should reject unknown packages", async function () {
      const { nftIncome } = await deployFixture();

      await expect(nftIncome.dailyRateFor(300)).to.be.revertedWith("Invalid package");
    });
  });

  describe("One active NFT at a time", function () {
    it("Should block a second purchase while the first cycle is not completed", async function () {
      const { manager, buy, alice, bob } = await deployFixture();

      await buy(alice, 50);

      expect(await manager.canPurchase(alice.address)).to.equal(false);
      await expect(buy(alice, 100)).to.be.revertedWith("Active NFT not completed");

      // Other wallets are not affected.
      await buy(bob, 100);
    });

    it("Should allow the next purchase once the 4X cycle is completed", async function () {
      const { nftIncome, manager, buy, alice, enablePayouts, fundIncome, passDays } =
        await deployFixture();

      await buy(alice, 50); // 4X = 200
      await enablePayouts();
      await fundIncome(tnex(250));
      await passDays(700);
      await nftIncome.startRound();
      await expect(nftIncome.claim([0])).to.emit(nftIncome, "NFTCycleCompleted");

      expect(await manager.canPurchase(alice.address)).to.equal(true);
      await buy(alice, 100);
    });
  });

  describe("Activation", function () {
    it("Should activate an NFT through the manager on purchase", async function () {
      const { nftIncome, buy, alice } = await deployFixture();

      const day = await nftIncome.currentDay();

      await expect(buy(alice, 1000))
        .to.emit(nftIncome, "NFTActivated")
        .withArgs(0n, 1000n, tnex("0.70"), tnex(4000), day);

      const s = await nftIncome.nfts(0);

      expect(s.active).to.equal(true);
      expect(s.packageAmount).to.equal(1000n);
      expect(s.paid).to.equal(0n);
    });

    it("Should only let the manager activate NFTs", async function () {
      const { nftIncome, other } = await deployFixture();

      await expect(
        nftIncome.connect(other).activateNFT(99, 1000),
      ).to.be.revertedWith("Only manager");
    });

    it("Should keep purchases working when NFTIncome is not connected", async function () {
      const { manager, nftIncome, buy, alice, nft } = await deployFixture();

      await manager.setModule(await manager.NFT_INCOME(), ethers.ZeroAddress);
      await buy(alice, 500);

      expect(await nft.ownerOf(0)).to.equal(alice.address);
      expect((await nftIncome.nfts(0)).active).to.equal(false);
    });
  });

  describe("Entitlement (business rule)", function () {
    it("Should accrue the daily rate per completed day", async function () {
      const { nftIncome, buy, alice, passDays } = await deployFixture();

      await buy(alice, 1000);

      expect(await nftIncome.entitledOf(0)).to.equal(0n);

      await passDays(1);
      expect(await nftIncome.entitledOf(0)).to.equal(tnex("0.70"));

      await passDays(9);
      expect(await nftIncome.entitledOf(0)).to.equal(tnex("7.00"));
      expect(await nftIncome.unpaidOf(0)).to.equal(tnex("7.00"));
    });

    it("Should stop accruing at 4X of the package", async function () {
      const { nftIncome, buy, alice, passDays } = await deployFixture();

      await buy(alice, 50); // 0.30/day, cap 200

      await passDays(666);
      expect(await nftIncome.entitledOf(0)).to.equal(tnex("199.8"));

      await passDays(1);
      expect(await nftIncome.entitledOf(0)).to.equal(tnex(200));

      await passDays(500);
      expect(await nftIncome.entitledOf(0)).to.equal(tnex(200));
    });

    it("Should keep the total equal to the sum of all NFTs, including capped ones", async function () {
      const { nftIncome, buy, alice, bob, other, passDays } = await deployFixture();

      await buy(alice, 50);   // caps at 200 on day 667
      await passDays(3);
      await buy(bob, 5000);   // 1.00/day
      await buy(other, 250);  // 0.50/day

      await passDays(700);

      const a = await nftIncome.entitledOf(0);
      const b = await nftIncome.entitledOf(1);
      const c = await nftIncome.entitledOf(2);

      expect(a).to.equal(tnex(200));
      expect(b).to.equal(tnex(700));
      expect(c).to.equal(tnex(350));
      expect(await nftIncome.totalEntitled()).to.equal(a + b + c);

      // Same result after checkpointing in small steps.
      await nftIncome.checkpoint(100);
      await nftIncome.checkpoint(1000);
      expect(await nftIncome.totalEntitled()).to.equal(a + b + c);
    });
  });

  describe("Funding safeguards", function () {
    it("Should have payouts disabled by default", async function () {
      const { nftIncome } = await deployFixture();

      expect(await nftIncome.payoutsEnabled()).to.equal(false);

      await expect(nftIncome.startRound()).to.be.revertedWith("Payouts disabled");
      await expect(nftIncome.claim([0])).to.be.revertedWith("Payouts disabled");
    });

    it("Should not fund rewards from NFT purchases", async function () {
      const { rewardPool, treasury, nftIncomeAddress, buy, alice, bob } = await deployFixture();

      await buy(alice, 5000);
      await buy(bob, 2500);

      expect(await treasury.balance()).to.equal(tnex(7500));
      expect(await rewardPool.balance()).to.equal(0n);
      expect(await rewardPool.budgetOf(nftIncomeAddress)).to.equal(0n);
    });

    it("Should refuse a round without a funded budget", async function () {
      const { enablePayouts, nftIncome, buy, alice, passDays } = await deployFixture();

      await enablePayouts();
      await buy(alice, 1000);
      await passDays(5);

      await expect(nftIncome.startRound()).to.be.revertedWith("No funded budget");
    });

    it("Should refuse a round when nothing is owed", async function () {
      const { enablePayouts, nftIncome, fundIncome, buy, alice } = await deployFixture();

      await enablePayouts();
      await fundIncome(tnex(100));
      await buy(alice, 1000);

      await expect(nftIncome.startRound()).to.be.revertedWith("Nothing owed");
    });

    it("Should refuse to enable payouts with no funding configured", async function () {
      const { nftIncome } = await deployFixture();

      await expect(nftIncome.setPayoutsEnabled(true)).to.be.revertedWith(
        "Funding not configured",
      );
    });

    it("Should refuse to enable payouts until all 3 funding steps are done", async function () {
      const { nftIncome, rewardPool, nftIncomeAddress, funder } = await deployFixture();

      // Step 1: documented source only.
      await rewardPool.addRevenueSource(TEST_REVENUE, "Test-only revenue source");
      await expect(nftIncome.setPayoutsEnabled(true)).to.be.revertedWith(
        "Funding not configured",
      );

      // Step 2: authorized depositor, still no allocation.
      await rewardPool.setDepositor(funder.address, TEST_REVENUE, true);
      await expect(nftIncome.setPayoutsEnabled(true)).to.be.revertedWith(
        "Funding not configured",
      );

      // Step 3: allocation to NFTIncome. Now it can be enabled.
      await rewardPool.setAllocation(nftIncomeAddress, TEST_REVENUE, 2_500n);

      await expect(nftIncome.setPayoutsEnabled(true))
        .to.emit(nftIncome, "PayoutsEnabledUpdated")
        .withArgs(true);

      expect(await nftIncome.payoutsEnabled()).to.equal(true);
    });

    it("Should refuse to enable payouts if NFTIncome is not a pool spender", async function () {
      const { nftIncome, rewardPool, nftIncomeAddress, configureFunding } = await deployFixture();

      await configureFunding();
      await rewardPool.setSpender(nftIncomeAddress, false);

      await expect(nftIncome.setPayoutsEnabled(true)).to.be.revertedWith(
        "Funding not configured",
      );
    });

    it("Should refuse to enable payouts if the allocation is on a source with no depositor", async function () {
      const { nftIncome, rewardPool, nftIncomeAddress } = await deployFixture();

      await rewardPool.addRevenueSource(TEST_REVENUE, "Test-only revenue source");
      await rewardPool.setAllocation(nftIncomeAddress, TEST_REVENUE, 10_000n);

      await expect(nftIncome.setPayoutsEnabled(true)).to.be.revertedWith(
        "Funding not configured",
      );
    });

    it("Should always allow disabling payouts", async function () {
      const { enablePayouts, nftIncome, rewardPool, nftIncomeAddress } = await deployFixture();

      await enablePayouts();

      // Funding is removed later; disabling must still work.
      await rewardPool.setAllocation(nftIncomeAddress, TEST_REVENUE, 0n);

      await expect(nftIncome.setPayoutsEnabled(false))
        .to.emit(nftIncome, "PayoutsEnabledUpdated")
        .withArgs(false);

      expect(await nftIncome.payoutsEnabled()).to.equal(false);
      await expect(nftIncome.startRound()).to.be.revertedWith("Payouts disabled");
    });

    it("Should restrict admin functions to the owner", async function () {
      const { nftIncome, other } = await deployFixture();

      await expect(nftIncome.connect(other).setPayoutsEnabled(true))
        .to.be.revertedWithCustomError(nftIncome, "OwnableUnauthorizedAccount")
        .withArgs(other.address);

      await expect(nftIncome.connect(other).startRound())
        .to.be.revertedWithCustomError(nftIncome, "OwnableUnauthorizedAccount")
        .withArgs(other.address);
    });
  });

  describe("Payment rounds", function () {
    it("Should pay the full amount when the budget covers it", async function () {
      const { enablePayouts, token, nftIncome, rewardPool, nftIncomeAddress, fundIncome, buy, alice, passDays } =
        await deployFixture();

      await enablePayouts();
      await buy(alice, 1000);
      await passDays(10);
      await fundIncome(tnex(100));

      await nftIncome.startRound();

      const before = await token.balanceOf(alice.address);

      await expect(nftIncome.claim([0]))
        .to.emit(nftIncome, "Claimed")
        .withArgs(0n, 1n, alice.address, tnex(7), tnex(7), 0n);

      expect(await token.balanceOf(alice.address)).to.equal(before + tnex(7));
      expect(await nftIncome.unpaidOf(0)).to.equal(0n);
      expect(await rewardPool.budgetOf(nftIncomeAddress)).to.equal(tnex(93));
    });

    it("Should pay proportionally and carry forward the rest (10 owed, 6 funded)", async function () {
      const { enablePayouts, token, nftIncome, fundIncome, buy, alice, passDays } = await deployFixture();

      await enablePayouts();
      await buy(alice, 5000); // 1.00/day
      await passDays(10);     // 10 owed
      await fundIncome(tnex(6));

      await expect(nftIncome.startRound())
        .to.emit(nftIncome, "RoundStarted")
        .withArgs(1n, await nftIncome.currentDay(), tnex(6), tnex(10));

      const before = await token.balanceOf(alice.address);

      await expect(nftIncome.claim([0]))
        .to.emit(nftIncome, "Claimed")
        .withArgs(0n, 1n, alice.address, tnex(10), tnex(6), tnex(4));

      expect(await token.balanceOf(alice.address)).to.equal(before + tnex(6));
      expect(await nftIncome.unpaidOf(0)).to.equal(tnex(4));

      // The listed rate did not change.
      expect((await nftIncome.nfts(0)).dailyRate).to.equal(tnex("1.00"));

      // Later funding pays off the carried-forward amount.
      await fundIncome(tnex(10));
      await nftIncome.startRound();

      await expect(nftIncome.claim([0]))
        .to.emit(nftIncome, "Claimed")
        .withArgs(0n, 2n, alice.address, tnex(4), tnex(4), 0n);

      expect(await nftIncome.unpaidOf(0)).to.equal(0n);
    });

    it("Should give every NFT the same percentage in a round, whatever the claim order", async function () {
      const { enablePayouts, token, nftIncome, fundIncome, buy, alice, bob, passDays } = await deployFixture();

      await enablePayouts();
      await buy(alice, 5000); // owes 10 after 10 days
      await buy(bob, 1000);   // owes 7 after 10 days
      await passDays(10);
      await fundIncome(tnex("8.5")); // 50% of 17

      await nftIncome.startRound();

      const aliceBefore = await token.balanceOf(alice.address);
      const bobBefore = await token.balanceOf(bob.address);

      // Bob claims first, then Alice.
      await nftIncome.claim([1]);
      await nftIncome.claim([0]);

      expect(await token.balanceOf(bob.address)).to.equal(bobBefore + tnex("3.5"));
      expect(await token.balanceOf(alice.address)).to.equal(aliceBefore + tnex(5));
      expect(await nftIncome.unpaidOf(1)).to.equal(tnex("3.5"));
      expect(await nftIncome.unpaidOf(0)).to.equal(tnex(5));
    });

    it("Should never pay more than the funded budget", async function () {
      const { enablePayouts, nftIncome, rewardPool, nftIncomeAddress, fundIncome, buy, alice, bob, passDays } =
        await deployFixture();

      await enablePayouts();
      await buy(alice, 5000);
      await buy(bob, 2500);
      await passDays(30);
      await fundIncome(tnex(13));

      await nftIncome.startRound();
      await nftIncome.claim([0, 1]);

      const round = await nftIncome.rounds(1);

      expect(round.paid).to.be.lte(tnex(13));
      expect(await rewardPool.budgetOf(nftIncomeAddress)).to.equal(tnex(13) - round.paid);
    });

    it("Should let each NFT claim only once per round", async function () {
      const { enablePayouts, token, nftIncome, fundIncome, buy, alice, passDays } = await deployFixture();

      await enablePayouts();
      await buy(alice, 5000);
      await passDays(10);
      await fundIncome(tnex(6));
      await nftIncome.startRound();

      await nftIncome.claim([0]);

      const after = await token.balanceOf(alice.address);

      await expect(nftIncome.claim([0])).to.not.emit(nftIncome, "Claimed");

      expect(await token.balanceOf(alice.address)).to.equal(after);
    });

    it("Should pay the NFT's current owner", async function () {
      const { enablePayouts, token, nft, nftIncome, fundIncome, buy, alice, bob, passDays } = await deployFixture();

      await enablePayouts();
      await buy(alice, 1000);
      await passDays(10);
      await nft.connect(alice).transferFrom(alice.address, bob.address, 0);
      await fundIncome(tnex(100));
      await nftIncome.startRound();

      const before = await token.balanceOf(bob.address);

      await nftIncome.connect(alice).claim([0]);

      expect(await token.balanceOf(bob.address)).to.equal(before + tnex(7));
    });

    it("Should complete the 4X cycle and pay nothing more after it", async function () {
      const { enablePayouts, nftIncome, fundIncome, buy, alice, passDays } = await deployFixture();

      await enablePayouts();
      await buy(alice, 50); // cap 200
      await passDays(800);
      await fundIncome(tnex(1000));
      await nftIncome.startRound();

      await expect(nftIncome.claim([0]))
        .to.emit(nftIncome, "NFTCycleCompleted")
        .withArgs(0n);

      expect((await nftIncome.nfts(0)).paid).to.equal(tnex(200));
      expect(await nftIncome.isCycleCompleted(0)).to.equal(true);

      await passDays(30);
      expect(await nftIncome.unpaidOf(0)).to.equal(0n);
    });

    it("Should reject claims for NFTs that were never activated", async function () {
      const { enablePayouts, nftIncome, fundIncome, buy, alice, passDays } = await deployFixture();

      await enablePayouts();
      await buy(alice, 1000);
      await passDays(2);
      await fundIncome(tnex(10));
      await nftIncome.startRound();

      await expect(nftIncome.claim([42])).to.be.revertedWith("NFT not active");
    });
  });
});
