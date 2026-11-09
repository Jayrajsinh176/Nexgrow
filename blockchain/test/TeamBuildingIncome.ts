import { expect } from "chai";
import { network } from "hardhat";

const { ethers, networkHelpers } = await network.create();

const DAY = 24 * 60 * 60;

// Test-only revenue source. Real sources are not defined yet.
const TEST_REVENUE = ethers.id("TEST_REVENUE");

const tnex = (v: string | number) => ethers.parseUnits(v.toString(), 18);

// 10 days of a 1000 package (0.70/day).
const PAID = tnex(7);

describe("TeamBuildingIncome", function () {
  async function deployFixture() {
    // Start one hour into a fresh UTC day so day maths is predictable.
    const now = await networkHelpers.time.latest();
    await networkHelpers.time.increaseTo((Math.floor(now / DAY) + 1) * DAY + 3600);

    const [owner, funder, other] = await ethers.getSigners();

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
    const nftIncome = await ethers.deployContract("NFTIncome", [
      managerAddress, nftAddress, poolAddress,
    ]);
    const referral = await ethers.deployContract("ReferralIncome", [
      managerAddress, nftAddress, poolAddress,
    ]);
    const team = await ethers.deployContract("TeamBuildingIncome", [
      managerAddress, nftAddress, poolAddress, await referral.getAddress(),
    ]);
    const nftIncomeAddress = await nftIncome.getAddress();
    const referralAddress = await referral.getAddress();
    const teamAddress = await team.getAddress();

    await nft.setManager(managerAddress);
    await manager.setModule(await manager.REWARD_POOL(), poolAddress);
    await manager.setModule(await manager.NFT_INCOME(), nftIncomeAddress);
    await manager.setModule(await manager.REFERRAL_INCOME(), referralAddress);
    await manager.setModule(await manager.TEAM_BUILDING_INCOME(), teamAddress);
    for (const m of [nftIncomeAddress, referralAddress, teamAddress]) {
      await rewardPool.setSpender(m, true);
    }

    await token.transfer(funder.address, tnex(100_000));

    // Fresh funded wallets, so trees of any size can be built.
    async function newWallet() {
      const w = ethers.Wallet.createRandom().connect(ethers.provider);
      await owner.sendTransaction({ to: w.address, value: ethers.parseEther("1") });
      await token.transfer(w.address, tnex(10_000));
      return w;
    }

    let minted = 0;

    // Returns the new token ID (IDs are sequential from 0).
    async function buy(user: any, pkg: number) {
      await token.connect(user).approve(nftAddress, tnex(pkg));
      await nft.connect(user).buyNFT(pkg);
      return minted++;
    }

    // New wallet that owns a 50 NFT and is sponsored by `sponsor` (if any).
    async function member(sponsor?: any) {
      const w = await newWallet();
      if (sponsor) await referral.connect(w).setSponsor(sponsor.address);
      await buy(w, 50);
      return w;
    }

    // New wallet sponsored by `sponsor`, without an NFT yet.
    async function newcomer(sponsor: any) {
      const w = await newWallet();
      await referral.connect(w).setSponsor(sponsor.address);
      return w;
    }

    // `count` new wallets sponsored by `sponsor`, without NFTs.
    async function addDirects(sponsor: any, count: number) {
      for (let i = 0; i < count; i++) {
        const w = await newWallet();
        await referral.connect(w).setSponsor(sponsor.address);
      }
    }

    let sourceReady = false;

    // NFTIncome 50%, ReferralIncome 25%, TeamBuildingIncome 25%.
    async function configureFunding() {
      if (sourceReady) return;

      await rewardPool.addRevenueSource(TEST_REVENUE, "Test-only revenue source");
      await rewardPool.setDepositor(funder.address, TEST_REVENUE, true);
      await rewardPool.setAllocation(nftIncomeAddress, TEST_REVENUE, 5_000n);
      await rewardPool.setAllocation(referralAddress, TEST_REVENUE, 2_500n);
      await rewardPool.setAllocation(teamAddress, TEST_REVENUE, 2_500n);
      sourceReady = true;
    }

    async function enablePayouts() {
      await configureFunding();
      await nftIncome.setPayoutsEnabled(true);
      await team.setPayoutsEnabled(true);
    }

    async function fund(amount: bigint) {
      await configureFunding();
      await token.connect(funder).approve(poolAddress, amount);
      await rewardPool.connect(funder).deposit(amount, TEST_REVENUE);
    }

    async function passDays(days: number) {
      await networkHelpers.time.increase(days * DAY);
    }

    // Earner buys a 1000 NFT, 10 days pass, the bonus (7 tNEX) is paid and
    // the team building queue is processed.
    async function earnAndProcess(earner: any) {
      const tokenId = await buy(earner, 1000);
      await enablePayouts();
      await fund(tnex(1000));
      await passDays(10);
      await nftIncome.startRound();
      await nftIncome.claim([tokenId]);
      await team.processQueue(1000);
    }

    return {
      owner, funder, other,
      token, nft, manager, rewardPool, nftIncome, referral, team,
      newWallet, buy, member, newcomer, addDirects, enablePayouts, fund, passDays, earnAndProcess,
    };
  }

  describe("Business rules", function () {
    it("Should use the plan's percentages for 20 levels", async function () {
      const { team } = await deployFixture();

      expect(await team.levelBps(1)).to.equal(1000n);
      for (let level = 2; level <= 15; level++) {
        expect(await team.levelBps(level)).to.equal(500n);
      }
      for (let level = 16; level <= 20; level++) {
        expect(await team.levelBps(level)).to.equal(400n);
      }
      expect(await team.levelBps(21)).to.equal(0n);
      await expect(team.levelBps(0)).to.be.revertedWith("Invalid level");
    });

    it("Should unlock levels by direct referrals: none below 5, then one per direct up to 20", async function () {
      const { team, member, addDirects } = await deployFixture();

      const top = await member();

      await addDirects(top, 4);
      expect(await team.unlockedLevels(top.address)).to.equal(0n);

      await addDirects(top, 1);
      expect(await team.unlockedLevels(top.address)).to.equal(5n);

      await addDirects(top, 1);
      expect(await team.unlockedLevels(top.address)).to.equal(6n);

      await addDirects(top, 15);
      expect(await team.unlockedLevels(top.address)).to.equal(20n);
    });

    it("Should use ReferralIncome's sponsor tree", async function () {
      const { team, referral, member } = await deployFixture();

      const top = await member();
      const child = await member(top);

      expect(await team.sponsorTree()).to.equal(await referral.getAddress());
      expect(await team.sponsorOf(child.address)).to.equal(top.address);
    });

    it("Should reject a zero sponsor tree", async function () {
      const { manager, nft, rewardPool } = await deployFixture();

      await expect(
        ethers.deployContract("TeamBuildingIncome", [
          await manager.getAddress(),
          await nft.getAddress(),
          await rewardPool.getAddress(),
          ethers.ZeroAddress,
        ]),
      ).to.be.revertedWith("Invalid sponsor tree");
    });
  });

  describe("Crediting uplines", function () {
    it("Should pay 10% at level 1 to a qualified upline", async function () {
      const { team, member, addDirects, newWallet, referral, earnAndProcess } =
        await deployFixture();

      const top = await member();
      const earner = await newWallet();
      await referral.connect(earner).setSponsor(top.address);
      await addDirects(top, 4);

      await earnAndProcess(earner);

      expect(await team.unpaidOf(top.address)).to.equal((PAID * 1000n) / 10_000n);
    });

    it("Should pay nothing to an upline with fewer than 5 directs", async function () {
      const { team, member, addDirects, newWallet, referral, earnAndProcess } =
        await deployFixture();

      const top = await member();
      const earner = await newWallet();
      await referral.connect(earner).setSponsor(top.address);
      await addDirects(top, 3);

      await earnAndProcess(earner);

      expect(await team.unpaidOf(top.address)).to.equal(0n);
    });

    it("Should pay nothing to an upline without an active NFT, but keep checking above it", async function () {
      const { team, nft, other, member, addDirects, newWallet, referral, earnAndProcess } =
        await deployFixture();

      // grand <- mid <- earner. Both uplines have 5 directs.
      const grand = await member();
      const mid = await member(grand);
      const earner = await newWallet();
      await referral.connect(earner).setSponsor(mid.address);
      await addDirects(grand, 4);
      await addDirects(mid, 4);

      // mid gives its only NFT away.
      await nft.connect(mid).transferFrom(mid.address, other.address, 1);

      await earnAndProcess(earner);

      expect(await team.unpaidOf(mid.address)).to.equal(0n);
      expect(await team.unpaidOf(grand.address)).to.equal((PAID * 500n) / 10_000n);
    });

    it("Should only pay a level once enough directs unlock it", async function () {
      const f = await deployFixture();

      // Chain of 7: chain[0] is 6 levels above the earner chain[6].
      const chain = [await f.member()];
      for (let i = 1; i < 6; i++) chain.push(await f.member(chain[i - 1]));
      chain.push(await f.newcomer(chain[5]));

      // chain[0] has 5 directs: levels 1-5 only.
      await f.addDirects(chain[0], 4);

      await f.earnAndProcess(chain[6]);

      expect(await f.team.unpaidOf(chain[0].address)).to.equal(0n);

      // One more direct unlocks level 6 for the next payout.
      await f.addDirects(chain[0], 1);
      await f.passDays(5);
      await f.nftIncome.startRound();
      await f.nftIncome.claim([6]); // chain[6]'s 1000 NFT
      await f.team.processQueue(1000);

      expect(await f.team.unpaidOf(chain[0].address)).to.equal(
        (tnex("3.5") * 500n) / 10_000n,
      );
    });

    it("Should stop after 20 levels", async function () {
      const f = await deployFixture();

      const chain = [await f.member()];
      for (let i = 1; i < 24; i++) chain.push(await f.member(chain[i - 1]));
      chain.push(await f.newcomer(chain[23]));

      const tokenId = await f.buy(chain[24], 1000);
      await f.enablePayouts();
      await f.fund(tnex(1000));
      await f.passDays(10);
      await f.nftIncome.startRound();
      await f.nftIncome.claim([tokenId]);

      expect(await f.team.processQueue.staticCall(1000)).to.equal(20n);

      await f.team.processQueue(1000);
      expect(await f.team.pendingJobs()).to.equal(0n);
    });

    it("Should credit referral and team building independently on the same payout", async function () {
      const { team, referral, member, addDirects, newWallet, earnAndProcess } =
        await deployFixture();

      const top = await member();
      const earner = await newWallet();
      await referral.connect(earner).setSponsor(top.address);
      await addDirects(top, 4);

      await earnAndProcess(earner);
      await referral.processQueue(1000);

      expect(await referral.unpaidOf(top.address)).to.equal((PAID * 500n) / 10_000n);
      expect(await team.unpaidOf(top.address)).to.equal((PAID * 1000n) / 10_000n);
    });
  });

  describe("Payouts", function () {
    it("Should pay team building credit from its own budget in a round", async function () {
      const { team, token, member, addDirects, newWallet, referral, earnAndProcess } =
        await deployFixture();

      const top = await member();
      const earner = await newWallet();
      await referral.connect(earner).setSponsor(top.address);
      await addDirects(top, 4);

      await earnAndProcess(earner);

      const owed = (PAID * 1000n) / 10_000n;
      const before = await token.balanceOf(top.address);

      await team.startRound();
      await expect(team.claim([top.address]))
        .to.emit(team, "Claimed")
        .withArgs(top.address, 1n, owed, owed, 0n);

      expect((await token.balanceOf(top.address)) - before).to.equal(owed);
    });
  });

  describe("NFTIncome active NFT tracking", function () {
    it("Should count an NFT as active for its buyer", async function () {
      const { nftIncome, member } = await deployFixture();

      const a = await member();

      expect(await nftIncome.activeNFTCount(a.address)).to.equal(1n);
      expect(await nftIncome.hasActiveNFT(a.address)).to.equal(true);
      expect(await nftIncome.countedOwner(0)).to.equal(a.address);
    });

    it("Should move the count when the NFT is transferred", async function () {
      const { nftIncome, nft, member, other } = await deployFixture();

      const a = await member();

      await nft.connect(a).transferFrom(a.address, other.address, 0);

      expect(await nftIncome.hasActiveNFT(a.address)).to.equal(false);
      expect(await nftIncome.hasActiveNFT(other.address)).to.equal(true);
      expect(await nftIncome.countedOwner(0)).to.equal(other.address);
    });

    it("Should stop counting an NFT once its 4X cycle is paid", async function () {
      const { nftIncome, member, enablePayouts, fund, passDays } = await deployFixture();

      const a = await member(); // 50 package: 200 tNEX cap

      await enablePayouts();
      await fund(tnex(1000));
      await passDays(700);
      await nftIncome.startRound();
      await expect(nftIncome.claim([0])).to.emit(nftIncome, "NFTCycleCompleted");

      expect(await nftIncome.hasActiveNFT(a.address)).to.equal(false);
      expect(await nftIncome.countedOwner(0)).to.equal(ethers.ZeroAddress);
    });

    it("Should let anyone repair a transfer missed while disconnected", async function () {
      const { nftIncome, nft, manager, member, other } = await deployFixture();

      const a = await member();
      const nftIncomeAddress = await nftIncome.getAddress();

      await manager.setModule(await manager.NFT_INCOME(), ethers.ZeroAddress);
      await nft.connect(a).transferFrom(a.address, other.address, 0);
      await manager.setModule(await manager.NFT_INCOME(), nftIncomeAddress);

      expect(await nftIncome.hasActiveNFT(a.address)).to.equal(true);

      await nftIncome.connect(other).syncOwner(0);

      expect(await nftIncome.hasActiveNFT(a.address)).to.equal(false);
      expect(await nftIncome.hasActiveNFT(other.address)).to.equal(true);
    });

    it("Should only accept transfer reports from the manager and the NFT", async function () {
      const { nftIncome, manager, other } = await deployFixture();

      await expect(nftIncome.connect(other).onTransfer(0)).to.be.revertedWith("Only manager");
      await expect(manager.connect(other).onNFTTransfer(0)).to.be.revertedWith(
        "Only NFT contract",
      );
    });
  });
});
