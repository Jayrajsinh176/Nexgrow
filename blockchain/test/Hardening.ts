import { expect } from "chai";
import { network } from "hardhat";

const { ethers, networkHelpers } = await network.create();

const DAY = 24 * 60 * 60;
const SOURCE = ethers.id("TEST_SOURCE");
const tnex = (v: string | number) => ethers.parseUnits(v.toString(), 18);

async function deploySystem() {
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
  const manager = await ethers.deployContract("NexgrowManager", [await nft.getAddress()]);
  const rewardPool = await ethers.deployContract("RewardPool", [tokenAddress]);
  const nftIncome = await ethers.deployContract("NFTIncome", [
    await manager.getAddress(),
    await nft.getAddress(),
    await rewardPool.getAddress(),
  ]);

  await nft.setManager(await manager.getAddress());
  await manager.setModule(await manager.NFT_INCOME(), await nftIncome.getAddress());
  await rewardPool.setSpender(await nftIncome.getAddress(), true);

  for (const s of [alice, bob, funder, other]) {
    await token.transfer(s.address, tnex(50_000));
  }

  async function buy(user: any, pkg: number) {
    await token.connect(user).approve(await nft.getAddress(), tnex(pkg));
    return nft.connect(user).buyNFT(pkg);
  }

  async function configureAndFund(amount: bigint) {
    await rewardPool.addRevenueSource(SOURCE, "Test-only revenue source");
    await rewardPool.setDepositor(funder.address, SOURCE, true);
    await rewardPool.setAllocation(await nftIncome.getAddress(), SOURCE, 10_000n);
    await token.connect(funder).approve(await rewardPool.getAddress(), amount);
    await rewardPool.connect(funder).deposit(amount, SOURCE);
  }

  return {
    owner, alice, bob, funder, other,
    token, treasury, nft, manager, rewardPool, nftIncome,
    buy, configureAndFund,
    passDays: (d: number) => networkHelpers.time.increase(d * DAY),
  };
}

describe("Hardening", function () {
  describe("NexgrowNFT: SafeERC20 and payment checks", function () {
    it("Should reject a zero token or treasury address", async function () {
      const [owner] = await ethers.getSigners();

      await expect(
        ethers.deployContract("NexgrowNFT", [ethers.ZeroAddress, owner.address]),
      ).to.be.revertedWith("Invalid token address");

      await expect(
        ethers.deployContract("NexgrowNFT", [owner.address, ethers.ZeroAddress]),
      ).to.be.revertedWith("Invalid treasury address");
    });

    it("Should surface the token's own error through SafeERC20", async function () {
      const { token, nft, alice } = await deploySystem();

      await token.connect(alice).approve(await nft.getAddress(), tnex(499));

      await expect(nft.connect(alice).buyNFT(500)).to.be.revertedWithCustomError(
        token,
        "ERC20InsufficientAllowance",
      );
    });

    it("Should revert if the treasury does not receive the full price", async function () {
      const [owner] = await ethers.getSigners();

      const feeToken = await ethers.deployContract("FeeOnTransferToken");
      const treasury = await ethers.deployContract("NexgrowTreasury", [
        await feeToken.getAddress(),
      ]);
      const nft = await ethers.deployContract("NexgrowNFT", [
        await feeToken.getAddress(),
        await treasury.getAddress(),
      ]);
      const manager = await ethers.deployContract("NexgrowManager", [await nft.getAddress()]);
      await nft.setManager(await manager.getAddress());

      await feeToken.approve(await nft.getAddress(), tnex(100));

      await expect(nft.buyNFT(100)).to.be.revertedWith("Payment amount mismatch");

      expect(await nft.balanceOf(owner.address)).to.equal(0n);
    });

    it("Should block a reentrant buyer", async function () {
      const { token, nft } = await deploySystem();

      const attacker = await ethers.deployContract("ReentrantBuyer", [
        await nft.getAddress(),
        await token.getAddress(),
      ]);
      await token.transfer(await attacker.getAddress(), tnex(1000));

      await expect(attacker.attack(50)).to.be.revertedWithCustomError(
        nft,
        "ReentrancyGuardReentrantCall",
      );

      expect(await nft.balanceOf(await attacker.getAddress())).to.equal(0n);
    });
  });

  describe("NexgrowNFT: pause", function () {
    it("Should block purchases while paused and emit Paused/Unpaused", async function () {
      const { owner, nft, buy, alice } = await deploySystem();

      await expect(nft.pause()).to.emit(nft, "Paused").withArgs(owner.address);
      expect(await nft.paused()).to.equal(true);

      await expect(buy(alice, 500)).to.be.revertedWithCustomError(nft, "EnforcedPause");

      await expect(nft.unpause()).to.emit(nft, "Unpaused").withArgs(owner.address);

      await buy(alice, 500);
      expect(await nft.ownerOf(0)).to.equal(alice.address);
    });

    it("Should still allow NFT transfers while paused", async function () {
      const { nft, buy, alice, bob } = await deploySystem();

      await buy(alice, 500);
      await nft.pause();

      await nft.connect(alice).transferFrom(alice.address, bob.address, 0);
      expect(await nft.ownerOf(0)).to.equal(bob.address);
    });

    it("Should only let the owner pause and unpause", async function () {
      const { nft, other } = await deploySystem();

      await expect(nft.connect(other).pause())
        .to.be.revertedWithCustomError(nft, "OwnableUnauthorizedAccount")
        .withArgs(other.address);

      await nft.pause();

      await expect(nft.connect(other).unpause())
        .to.be.revertedWithCustomError(nft, "OwnableUnauthorizedAccount")
        .withArgs(other.address);
    });
  });

  describe("NexgrowManager: NFT contract lock", function () {
    it("Should count registrations", async function () {
      const { manager, buy, alice, bob } = await deploySystem();

      expect(await manager.registeredCount()).to.equal(0n);
      await buy(alice, 50);
      await buy(bob, 100);
      expect(await manager.registeredCount()).to.equal(2n);
    });

    it("Should block changing the NFT contract after the first sale", async function () {
      const { manager, buy, alice, other } = await deploySystem();

      await buy(alice, 50);

      await expect(manager.setNFTContract(other.address)).to.be.revertedWith(
        "NFTs already registered",
      );
    });
  });

  describe("Ownership: two-step transfer, renounce disabled", function () {
    it("Should refuse renounceOwnership on every Nexgrow contract", async function () {
      const { nft, treasury, manager, rewardPool, nftIncome } = await deploySystem();

      for (const c of [nft, treasury, manager, rewardPool, nftIncome]) {
        await expect((c as any).renounceOwnership()).to.be.revertedWith("Renounce disabled");
      }
    });

    it("Should only transfer ownership after the new owner accepts", async function () {
      const { owner, treasury, alice, other } = await deploySystem();

      await treasury.transferOwnership(alice.address);

      expect(await treasury.owner()).to.equal(owner.address);
      expect(await treasury.pendingOwner()).to.equal(alice.address);

      await expect(treasury.connect(other).acceptOwnership())
        .to.be.revertedWithCustomError(treasury, "OwnableUnauthorizedAccount")
        .withArgs(other.address);

      await treasury.connect(alice).acceptOwnership();

      expect(await treasury.owner()).to.equal(alice.address);
      expect(await treasury.pendingOwner()).to.equal(ethers.ZeroAddress);
    });
  });

  describe("Pools: pause and received-amount accounting", function () {
    it("Should block payouts while paused but keep deposits open", async function () {
      const { owner, token, rewardPool, nftIncome, funder, configureAndFund, alice } =
        await deploySystem();

      await configureAndFund(tnex(100));

      // Use an EOA as spender so we can call release directly.
      await rewardPool.setSpender(alice.address, true);
      await rewardPool.setAllocation(await nftIncome.getAddress(), SOURCE, 5_000n);
      await rewardPool.setAllocation(alice.address, SOURCE, 5_000n);
      await token.connect(funder).approve(await rewardPool.getAddress(), tnex(10));
      await rewardPool.connect(funder).deposit(tnex(10), SOURCE);

      await expect(rewardPool.pause()).to.emit(rewardPool, "Paused").withArgs(owner.address);

      await expect(
        rewardPool.connect(alice).release(alice.address, tnex(1)),
      ).to.be.revertedWithCustomError(rewardPool, "EnforcedPause");

      // Deposits still work.
      await token.connect(funder).approve(await rewardPool.getAddress(), tnex(10));
      await rewardPool.connect(funder).deposit(tnex(10), SOURCE);
      expect(await rewardPool.totalDeposited()).to.equal(tnex(120));

      await expect(rewardPool.unpause()).to.emit(rewardPool, "Unpaused");

      await rewardPool.connect(alice).release(alice.address, tnex(1));
    });

    it("Should only let the owner pause the pool", async function () {
      const { rewardPool, other } = await deploySystem();

      await expect(rewardPool.connect(other).pause())
        .to.be.revertedWithCustomError(rewardPool, "OwnableUnauthorizedAccount")
        .withArgs(other.address);
    });

    it("Should record the amount actually received, not the amount requested", async function () {
      const [owner, module] = await ethers.getSigners();

      const feeToken = await ethers.deployContract("FeeOnTransferToken");
      const pool = await ethers.deployContract("RewardPool", [await feeToken.getAddress()]);

      await pool.addRevenueSource(SOURCE, "Test-only revenue source");
      await pool.setDepositor(owner.address, SOURCE, true);
      await pool.setAllocation(module.address, SOURCE, 10_000n);

      await feeToken.approve(await pool.getAddress(), tnex(100));

      await expect(pool.deposit(tnex(100), SOURCE))
        .to.emit(pool, "Deposited")
        .withArgs(owner.address, SOURCE, tnex(99));

      expect(await pool.totalDeposited()).to.equal(tnex(99));
      expect(await pool.depositedBySource(SOURCE)).to.equal(tnex(99));
      expect(await pool.budgetOf(module.address)).to.equal(tnex(99));
      expect(await pool.balance()).to.equal(tnex(99));
    });
  });

  describe("NFTIncome: validation", function () {
    it("Should reject an NFT address that differs from the manager's", async function () {
      const { manager, rewardPool, other } = await deploySystem();

      await expect(
        ethers.deployContract("NFTIncome", [
          await manager.getAddress(),
          other.address,
          await rewardPool.getAddress(),
        ]),
      ).to.be.revertedWith("NFT does not match manager");
    });

    it("Should reject an activation whose package does not match the NFT", async function () {
      const { nft, rewardPool, manager, buy, alice } = await deploySystem();

      // NFT #0 is a 500 package.
      await manager.setModule(await manager.NFT_INCOME(), ethers.ZeroAddress);
      await buy(alice, 500);

      const mock = await ethers.deployContract("MockManager", [await nft.getAddress()]);
      const income = await ethers.deployContract("NFTIncome", [
        await mock.getAddress(),
        await nft.getAddress(),
        await rewardPool.getAddress(),
      ]);

      await expect(
        mock.callActivate(await income.getAddress(), 0, 5000),
      ).to.be.revertedWith("Package mismatch");

      await mock.callActivate(await income.getAddress(), 0, 500);
      expect((await income.nfts(0)).packageAmount).to.equal(500n);
    });
  });

  describe("NFTIncome: activateExisting", function () {
    async function withUnactivatedNFTs() {
      const f = await deploySystem();

      // Disconnect NFTIncome, sell two NFTs, then reconnect.
      await f.manager.setModule(await f.manager.NFT_INCOME(), ethers.ZeroAddress);
      await f.buy(f.alice, 1000);
      await f.buy(f.bob, 50);
      await f.manager.setModule(await f.manager.NFT_INCOME(), await f.nftIncome.getAddress());

      return f;
    }

    it("Should leave NFTs bought while disconnected inactive", async function () {
      const { nftIncome } = await withUnactivatedNFTs();

      expect((await nftIncome.nfts(0)).active).to.equal(false);
      expect((await nftIncome.nfts(1)).active).to.equal(false);
    });

    it("Should activate them with the package read from the NFT contract", async function () {
      const { nftIncome, passDays } = await withUnactivatedNFTs();

      await passDays(5);

      const today = await nftIncome.currentDay();

      await expect(nftIncome.activateExisting([0, 1]))
        .to.emit(nftIncome, "NFTActivated")
        .withArgs(0n, 1000n, tnex("0.70"), tnex(4000), today);

      expect((await nftIncome.nfts(1)).packageAmount).to.equal(50n);

      // Activation day is today: no backdated entitlement.
      expect(await nftIncome.entitledOf(0)).to.equal(0n);

      await passDays(2);
      expect(await nftIncome.entitledOf(0)).to.equal(tnex("1.40"));
      expect(await nftIncome.entitledOf(1)).to.equal(tnex("0.60"));
      expect(await nftIncome.totalEntitled()).to.equal(tnex("2.00"));
    });

    it("Should skip NFTs that are already active", async function () {
      const { nftIncome, buy, alice } = await withUnactivatedNFTs();

      await buy(alice, 500); // NFT #2, activated on purchase

      const before = await nftIncome.nfts(2);

      await expect(nftIncome.activateExisting([2])).to.not.emit(nftIncome, "NFTActivated");

      expect((await nftIncome.nfts(2)).activationDay).to.equal(before.activationDay);
    });

    it("Should reject NFTs that were never registered", async function () {
      const { nftIncome } = await withUnactivatedNFTs();

      await expect(nftIncome.activateExisting([99])).to.be.revertedWith(
        "NFT not registered",
      );
    });

    it("Should only let the owner call it", async function () {
      const { nftIncome, other } = await withUnactivatedNFTs();

      await expect(nftIncome.connect(other).activateExisting([0]))
        .to.be.revertedWithCustomError(nftIncome, "OwnableUnauthorizedAccount")
        .withArgs(other.address);
    });
  });

  describe("NFTIncome: pause", function () {
    it("Should block startRound and claim while paused", async function () {
      const { owner, nftIncome, configureAndFund, buy, alice, passDays } = await deploySystem();

      await buy(alice, 1000);
      await passDays(5);
      await configureAndFund(tnex(100));
      await nftIncome.setPayoutsEnabled(true);
      await nftIncome.startRound();

      await expect(nftIncome.pause()).to.emit(nftIncome, "Paused").withArgs(owner.address);

      await expect(nftIncome.claim([0])).to.be.revertedWithCustomError(
        nftIncome,
        "EnforcedPause",
      );
      await expect(nftIncome.startRound()).to.be.revertedWithCustomError(
        nftIncome,
        "EnforcedPause",
      );

      await expect(nftIncome.unpause()).to.emit(nftIncome, "Unpaused");

      await expect(nftIncome.claim([0])).to.emit(nftIncome, "Claimed");
    });

    it("Should keep accruing entitlement and activating purchases while paused", async function () {
      const { nftIncome, buy, alice, bob, passDays } = await deploySystem();

      await buy(alice, 1000);
      await nftIncome.pause();

      await buy(bob, 50);
      expect((await nftIncome.nfts(1)).active).to.equal(true);

      await passDays(3);
      expect(await nftIncome.entitledOf(0)).to.equal(tnex("2.10"));
    });

    it("Should only let the owner pause", async function () {
      const { nftIncome, other } = await deploySystem();

      await expect(nftIncome.connect(other).pause())
        .to.be.revertedWithCustomError(nftIncome, "OwnableUnauthorizedAccount")
        .withArgs(other.address);
    });
  });

  describe("Payouts remain disabled by default", function () {
    it("Should start with payouts off and refuse to enable without funding", async function () {
      const { nftIncome } = await deploySystem();

      expect(await nftIncome.payoutsEnabled()).to.equal(false);
      await expect(nftIncome.setPayoutsEnabled(true)).to.be.revertedWith(
        "Funding not configured",
      );
    });
  });
});
