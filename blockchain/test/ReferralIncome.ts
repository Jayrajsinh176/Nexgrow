import { expect } from "chai";
import { network } from "hardhat";

const { ethers, networkHelpers } = await network.create();

const DAY = 24 * 60 * 60;

// Test-only revenue source. Real sources are not defined yet.
const TEST_REVENUE = ethers.id("TEST_REVENUE");

const LEVEL_BPS = [500n, 600n, 700n, 800n, 900n, 1000n, 1000n, 1000n];

const tnex = (v: string | number) => ethers.parseUnits(v.toString(), 18);

describe("ReferralIncome", function () {
  async function deployFixture() {
    // Start one hour into a fresh UTC day so day maths is predictable.
    const now = await networkHelpers.time.latest();
    await networkHelpers.time.increaseTo((Math.floor(now / DAY) + 1) * DAY + 3600);

    const signers = await ethers.getSigners();
    const [owner, funder, other] = signers;
    // Wallets used to build sponsor chains: users[0] is the top.
    const users = signers.slice(3);

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
    const referral = await ethers.deployContract("ReferralIncome", [
      await manager.getAddress(),
      await nft.getAddress(),
      await rewardPool.getAddress(),
    ]);
    const nftIncomeAddress = await nftIncome.getAddress();
    const referralAddress = await referral.getAddress();

    await nft.setManager(await manager.getAddress());
    await manager.setModule(await manager.REWARD_POOL(), await rewardPool.getAddress());
    await manager.setModule(await manager.NFT_INCOME(), nftIncomeAddress);
    await manager.setModule(await manager.REFERRAL_INCOME(), referralAddress);
    await rewardPool.setSpender(nftIncomeAddress, true);
    await rewardPool.setSpender(referralAddress, true);

    await token.transfer(funder.address, tnex(100_000));
    for (const u of [other, ...users]) {
      await token.transfer(u.address, tnex(10_000));
    }

    async function buy(user: any, pkg: number) {
      await token.connect(user).approve(await nft.getAddress(), tnex(pkg));
      return nft.connect(user).buyNFT(pkg);
    }

    // Each wallet sets the previous one as sponsor, then buys a 50 NFT.
    async function buildChain(length: number) {
      for (let i = 0; i < length; i++) {
        if (i > 0) {
          await referral.connect(users[i]).setSponsor(users[i - 1].address);
        }
        await buy(users[i], 50);
      }
      return users.slice(0, length);
    }

    let sourceReady = false;

    // 50% of the test source to each income module.
    async function configureFunding() {
      if (sourceReady) return;

      await rewardPool.addRevenueSource(TEST_REVENUE, "Test-only revenue source");
      await rewardPool.setDepositor(funder.address, TEST_REVENUE, true);
      await rewardPool.setAllocation(nftIncomeAddress, TEST_REVENUE, 5_000n);
      await rewardPool.setAllocation(referralAddress, TEST_REVENUE, 5_000n);
      sourceReady = true;
    }

    async function enablePayouts() {
      await configureFunding();
      await nftIncome.setPayoutsEnabled(true);
      await referral.setPayoutsEnabled(true);
    }

    // Deposits `amount`; each module's budget grows by amount / 2.
    async function fund(amount: bigint) {
      await configureFunding();
      await token.connect(funder).approve(await rewardPool.getAddress(), amount);
      await rewardPool.connect(funder).deposit(amount, TEST_REVENUE);
    }

    async function passDays(days: number) {
      await networkHelpers.time.increase(days * DAY);
    }

    // Pays the daily bonus owed to the given NFTs in a new NFTIncome round.
    async function payBonus(tokenIds: number[]) {
      await nftIncome.startRound();
      await nftIncome.claim(tokenIds);
    }

    return {
      owner, funder, other, users,
      token, nft, manager, rewardPool, nftIncome, referral, referralAddress,
      buy, buildChain, configureFunding, enablePayouts, fund, passDays, payBonus,
    };
  }

  describe("Business rules", function () {
    it("Should use the report's referral percentages", async function () {
      const { referral } = await deployFixture();

      for (let level = 1; level <= LEVEL_BPS.length; level++) {
        expect(await referral.levelBps(level)).to.equal(LEVEL_BPS[level - 1]);
      }
      expect(await referral.levelBps(1000)).to.equal(1000n);
    });

    it("Should reject level 0", async function () {
      const { referral } = await deployFixture();

      await expect(referral.levelBps(0)).to.be.revertedWith("Invalid level");
    });
  });

  describe("Deployment", function () {
    it("Should reject an NFT that does not match the manager", async function () {
      const { manager, rewardPool, other } = await deployFixture();

      await expect(
        ethers.deployContract("ReferralIncome", [
          await manager.getAddress(),
          other.address,
          await rewardPool.getAddress(),
        ]),
      ).to.be.revertedWith("NFT does not match manager");
    });

    it("Should start with payouts disabled", async function () {
      const { referral } = await deployFixture();

      expect(await referral.payoutsEnabled()).to.equal(false);
    });
  });

  describe("Sponsor tree", function () {
    it("Should set a sponsor once and count the direct referral", async function () {
      const { referral, buy, users } = await deployFixture();
      const [a, b] = users;

      await buy(a, 50);

      await expect(referral.connect(b).setSponsor(a.address))
        .to.emit(referral, "SponsorSet")
        .withArgs(b.address, a.address);

      expect(await referral.sponsorOf(b.address)).to.equal(a.address);
      expect(await referral.directCount(a.address)).to.equal(1n);

      await expect(referral.connect(b).setSponsor(a.address)).to.be.revertedWith(
        "Sponsor already set",
      );
    });

    it("Should reject zero, self and sponsors without an NFT", async function () {
      const { referral, buy, users } = await deployFixture();
      const [a, b] = users;

      await buy(a, 50);

      await expect(referral.connect(b).setSponsor(ethers.ZeroAddress)).to.be.revertedWith(
        "Invalid sponsor",
      );
      await expect(referral.connect(a).setSponsor(a.address)).to.be.revertedWith(
        "Cannot sponsor yourself",
      );
      await expect(referral.connect(a).setSponsor(b.address)).to.be.revertedWith(
        "Sponsor has no NFT",
      );
    });

    it("Should refuse a sponsor after the wallet's first purchase", async function () {
      const { referral, buy, users } = await deployFixture();
      const [a, b] = users;

      await buy(a, 50);
      await buy(b, 50);

      await expect(referral.connect(b).setSponsor(a.address)).to.be.revertedWith(
        "Already purchased",
      );
    });

    it("Should refuse a sponsor set from inside the purchase callback", async function () {
      const { referral, nft, token, buy, users } = await deployFixture();
      const [a] = users;

      await buy(a, 50);

      const buyer = await ethers.deployContract("SponsorInCallbackBuyer", [
        await nft.getAddress(),
        await token.getAddress(),
        await referral.getAddress(),
        a.address,
      ]);
      await token.transfer(await buyer.getAddress(), tnex(100));

      await expect(buyer.buy(50)).to.be.revertedWith("Already purchased");
    });

    it("Should make sponsor loops impossible", async function () {
      const { referral, buy, users } = await deployFixture();
      const [a, b] = users;

      await buy(a, 50);
      await referral.connect(b).setSponsor(a.address);
      await buy(b, 50);

      // a -> b would close the loop a -> b -> a.
      await expect(referral.connect(a).setSponsor(b.address)).to.be.revertedWith(
        "Already has referrals",
      );
    });
  });

  describe("Recording payouts", function () {
    it("Should only accept payouts from the connected NFTIncome", async function () {
      const { referral, other } = await deployFixture();

      await expect(
        referral.connect(other).recordPayout(other.address, tnex(1)),
      ).to.be.revertedWith("Only NFT income");
    });

    it("Should create no job for a wallet without a sponsor", async function () {
      const { referral, enablePayouts, fund, passDays, payBonus, buy, users } =
        await deployFixture();

      await buy(users[0], 1000);
      await enablePayouts();
      await fund(tnex(1000));
      await passDays(10);
      await payBonus([0]);

      expect(await referral.pendingJobs()).to.equal(0n);
    });

    it("Should leave NFTIncome claims working when no referral module is connected", async function () {
      const { manager, nftIncome, referral, enablePayouts, fund, passDays, payBonus, buildChain } =
        await deployFixture();

      await buildChain(2);
      await manager.setModule(await manager.REFERRAL_INCOME(), ethers.ZeroAddress);
      await enablePayouts();
      await fund(tnex(1000));
      await passDays(10);
      await payBonus([1]);

      expect((await nftIncome.nfts(1)).paid).to.equal(tnex(3)); // 10 x 0.30
      expect(await referral.pendingJobs()).to.equal(0n);
    });
  });

  describe("Crediting uplines", function () {
    it("Should credit every upline level by the report's percentages", async function () {
      const { referral, enablePayouts, fund, passDays, payBonus, buildChain } =
        await deployFixture();

      const chain = await buildChain(9);
      const bottom = 8; // NFT #8, owned by chain[8]

      await enablePayouts();
      await fund(tnex(1000));
      await passDays(10);
      await payBonus([bottom]);

      const paid = tnex(3); // 10 days x 0.30

      expect(await referral.pendingJobs()).to.equal(1n);

      await expect(referral.processQueue(100))
        .to.emit(referral, "Credited")
        .withArgs(chain[7].address, chain[8].address, 1n, paid, (paid * 500n) / 10_000n);

      expect(await referral.pendingJobs()).to.equal(0n);

      // chain[7] is level 1, chain[0] is level 8.
      for (let level = 1; level <= 8; level++) {
        const upline = chain[8 - level];
        expect(await referral.unpaidOf(upline.address)).to.equal(
          (paid * LEVEL_BPS[level - 1]) / 10_000n,
        );
      }

      expect(await referral.unpaidOf(chain[8].address)).to.equal(0n);
    });

    it("Should credit beyond 15 levels at 10% (unlimited depth)", async function () {
      const { referral, enablePayouts, fund, passDays, payBonus, buildChain } =
        await deployFixture();

      const chain = await buildChain(17);

      await enablePayouts();
      await fund(tnex(1000));
      await passDays(10);
      await payBonus([16]);
      await referral.processQueue(1000);

      // chain[0] is 16 levels above chain[16].
      expect(await referral.unpaidOf(chain[0].address)).to.equal(tnex("0.3"));
    });

    it("Should process a long chain in small batches", async function () {
      const { referral, enablePayouts, fund, passDays, payBonus, buildChain } =
        await deployFixture();

      const chain = await buildChain(6);

      await enablePayouts();
      await fund(tnex(1000));
      await passDays(10);
      await payBonus([5]);

      await referral.processQueue(2);
      expect(await referral.pendingJobs()).to.equal(1n);
      expect(await referral.unpaidOf(chain[4].address)).to.be.greaterThan(0n);
      expect(await referral.unpaidOf(chain[3].address)).to.be.greaterThan(0n);
      expect(await referral.unpaidOf(chain[2].address)).to.equal(0n);

      await referral.processQueue(2);
      expect(await referral.pendingJobs()).to.equal(1n);

      await referral.processQueue(2);
      expect(await referral.pendingJobs()).to.equal(0n);
      expect(await referral.unpaidOf(chain[0].address)).to.equal(
        (tnex(3) * 900n) / 10_000n,
      );

      // Nothing left: a further call does nothing.
      expect(await referral.processQueue.staticCall(10)).to.equal(0n);
    });

    it("Should base referral on the amount actually paid, not the full entitlement", async function () {
      const { referral, nftIncome, enablePayouts, fund, passDays, payBonus, buildChain } =
        await deployFixture();

      const chain = await buildChain(2);

      await enablePayouts();
      // NFTIncome gets a 1 tNEX budget but is owed 3 tNEX for NFT #1 alone.
      await fund(tnex(2));
      await passDays(10);
      await nftIncome.startRound();
      await nftIncome.claim([1]);

      const paid = (await nftIncome.nfts(1)).paid;

      expect(paid).to.be.lessThan(tnex(3));

      await referral.processQueue(10);

      expect(await referral.unpaidOf(chain[0].address)).to.equal((paid * 500n) / 10_000n);
    });

    it("Should follow the current NFT owner's sponsor chain", async function () {
      const { referral, nft, enablePayouts, fund, passDays, payBonus, buildChain, users } =
        await deployFixture();

      const chain = await buildChain(2);
      const outsider = users[5];

      // chain[1] gives NFT #1 to a wallet sponsored by nobody.
      await nft.connect(chain[1]).transferFrom(chain[1].address, outsider.address, 1);

      await enablePayouts();
      await fund(tnex(1000));
      await passDays(10);
      await payBonus([1]);

      expect(await referral.pendingJobs()).to.equal(0n);
    });
  });

  describe("Payouts", function () {
    // Two-level chain with chain[0] credited 0.15 tNEX (5% of 3 tNEX).
    async function withCredit() {
      const f = await deployFixture();
      const chain = await f.buildChain(2);

      await f.enablePayouts();
      await f.fund(tnex(1000));
      await f.passDays(10);
      await f.payBonus([1]);
      await f.referral.processQueue(10);

      return { ...f, chain };
    }

    it("Should refuse to enable payouts until funding is configured", async function () {
      const { referral } = await deployFixture();

      await expect(referral.setPayoutsEnabled(true)).to.be.revertedWith(
        "Funding not configured",
      );
      await referral.setPayoutsEnabled(false);
    });

    it("Should block rounds and claims while payouts are disabled", async function () {
      const { referral, chain } = await withCredit();

      await referral.setPayoutsEnabled(false);

      await expect(referral.startRound()).to.be.revertedWith("Payouts disabled");
      await expect(referral.claim([chain[0].address])).to.be.revertedWith(
        "Payouts disabled",
      );
    });

    it("Should only let the owner start a round", async function () {
      const { referral, other } = await withCredit();

      await expect(referral.connect(other).startRound())
        .to.be.revertedWithCustomError(referral, "OwnableUnauthorizedAccount")
        .withArgs(other.address);
    });

    it("Should pay the full credit when the budget covers it", async function () {
      const { referral, token, chain } = await withCredit();

      const owed = tnex("0.15");

      await referral.startRound();

      const before = await token.balanceOf(chain[0].address);

      await expect(referral.claim([chain[0].address]))
        .to.emit(referral, "Claimed")
        .withArgs(chain[0].address, 1n, owed, owed, 0n);

      expect((await token.balanceOf(chain[0].address)) - before).to.equal(owed);

      expect(await referral.unpaidOf(chain[0].address)).to.equal(0n);
      expect(await referral.totalPaid()).to.equal(owed);
    });

    it("Should skip accounts already claimed in the round", async function () {
      const { referral, token, chain } = await withCredit();

      await referral.startRound();
      await referral.claim([chain[0].address]);

      await expect(
        referral.claim([chain[0].address, chain[0].address]),
      ).to.changeTokenBalance(ethers, token, chain[0], 0n);
    });

    it("Should share a short budget pro rata and carry the rest forward", async function () {
      const f = await deployFixture();
      const chain = await f.buildChain(3);

      await f.enablePayouts();
      // 0.1% of deposits to ReferralIncome: budgets NFTIncome 50, Referral 0.1.
      await f.rewardPool.setAllocation(f.referralAddress, TEST_REVENUE, 10n);
      await f.fund(tnex(100));
      await f.passDays(10);
      await f.payBonus([2]);
      await f.referral.processQueue(10);

      // 3 tNEX paid to chain[2]: chain[1] 5% = 0.15, chain[0] 6% = 0.18.
      const owed1 = tnex("0.15");
      const owed0 = tnex("0.18");
      const budget = tnex("0.1");
      const totalOwed = owed0 + owed1;

      await f.referral.startRound();

      const r = await f.referral.rounds(1);
      expect(r.budget).to.equal(budget);
      expect(r.totalOwed).to.equal(totalOwed);

      const pay1 = (owed1 * budget) / totalOwed;
      const pay0 = (owed0 * budget) / totalOwed;

      await expect(f.referral.claim([chain[1].address, chain[0].address]))
        .to.emit(f.referral, "Claimed")
        .withArgs(chain[1].address, 1n, owed1, pay1, owed1 - pay1)
        .and.to.emit(f.referral, "Claimed")
        .withArgs(chain[0].address, 1n, owed0, pay0, owed0 - pay0);

      expect(pay0 + pay1).to.be.lessThanOrEqual(budget);
      expect(await f.referral.unpaidOf(chain[0].address)).to.equal(owed0 - pay0);

      // A later deposit pays off the carried-forward amount.
      await f.rewardPool.setAllocation(f.referralAddress, TEST_REVENUE, 5_000n);
      await f.fund(tnex(10));
      await f.referral.startRound();
      await f.referral.claim([chain[1].address, chain[0].address]);

      expect(await f.referral.unpaidOf(chain[0].address)).to.equal(0n);
      expect(await f.referral.unpaidOf(chain[1].address)).to.equal(0n);
    });

    it("Should not let credit added after a round starts be paid in that round", async function () {
      const f = await withCredit();

      await f.referral.startRound();

      // New payout and credit after the round started.
      await f.passDays(5);
      await f.payBonus([1]);
      await f.referral.processQueue(10);

      const unpaid = await f.referral.unpaidOf(f.chain[0].address);

      expect(unpaid).to.equal(tnex("0.15") + (tnex("1.5") * 500n) / 10_000n);

      await f.referral.claim([f.chain[0].address]);

      // Only the 0.15 credited before round 1 was paid.
      expect(await f.referral.unpaidOf(f.chain[0].address)).to.equal(
        (tnex("1.5") * 500n) / 10_000n,
      );

      // The rest is paid in the next round.
      await f.referral.startRound();
      await f.referral.claim([f.chain[0].address]);
      expect(await f.referral.unpaidOf(f.chain[0].address)).to.equal(0n);
    });

    it("Should require something owed and a funded budget", async function () {
      const f = await deployFixture();

      await f.enablePayouts();

      await expect(f.referral.startRound()).to.be.revertedWith("Nothing owed");
    });

    it("Should block rounds and claims while paused but keep crediting", async function () {
      const { referral, chain, passDays, payBonus } = await withCredit();

      await referral.pause();

      await expect(referral.startRound()).to.be.revertedWithCustomError(
        referral,
        "EnforcedPause",
      );
      await expect(referral.claim([chain[0].address])).to.be.revertedWithCustomError(
        referral,
        "EnforcedPause",
      );

      await passDays(5);
      await payBonus([1]);
      await referral.processQueue(10);

      expect(await referral.unpaidOf(chain[0].address)).to.equal(
        tnex("0.15") + (tnex("1.5") * 500n) / 10_000n,
      );
    });
  });
});
