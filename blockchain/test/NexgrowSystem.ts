import { expect } from "chai";
import { network } from "hardhat";

const { ethers } = await network.create();

// End-to-end: Token -> NFT -> Treasury contract, NFT -> Manager,
// and the Manager's module directory.
describe("Nexgrow system", function () {
  async function deployFixture() {
    const [owner, user, other] = await ethers.getSigners();

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
    const liquidityPool = await ethers.deployContract("LiquidityPool", [tokenAddress]);

    await nft.setManager(await manager.getAddress());
    await manager.setModule(await manager.TREASURY(), await treasury.getAddress());
    await manager.setModule(await manager.REWARD_POOL(), await rewardPool.getAddress());
    await manager.setModule(await manager.LIQUIDITY_POOL(), await liquidityPool.getAddress());

    await token.transfer(user.address, ethers.parseUnits("10000", 18));
    await token.transfer(other.address, ethers.parseUnits("10000", 18));

    return { owner, user, other, token, treasury, nft, manager, rewardPool, liquidityPool };
  }

  describe("Module directory", function () {
    it("Should use keccak256 of the name as module keys", async function () {
      const { manager } = await deployFixture();

      expect(await manager.TREASURY()).to.equal(ethers.id("TREASURY"));
      expect(await manager.REWARD_POOL()).to.equal(ethers.id("REWARD_POOL"));
      expect(await manager.LIQUIDITY_POOL()).to.equal(ethers.id("LIQUIDITY_POOL"));
    });

    it("Should store every connected module", async function () {
      const { manager, treasury, rewardPool, liquidityPool } = await deployFixture();

      expect(await manager.modules(ethers.id("TREASURY"))).to.equal(
        await treasury.getAddress(),
      );
      expect(await manager.modules(ethers.id("REWARD_POOL"))).to.equal(
        await rewardPool.getAddress(),
      );
      expect(await manager.modules(ethers.id("LIQUIDITY_POOL"))).to.equal(
        await liquidityPool.getAddress(),
      );
    });

    it("Should emit ModuleUpdated and allow disconnecting", async function () {
      const { manager, rewardPool } = await deployFixture();

      const key = ethers.id("REWARD_POOL");

      await expect(manager.setModule(key, ethers.ZeroAddress))
        .to.emit(manager, "ModuleUpdated")
        .withArgs(key, await rewardPool.getAddress(), ethers.ZeroAddress);

      expect(await manager.modules(key)).to.equal(ethers.ZeroAddress);
    });

    it("Should block non-owners from setting modules", async function () {
      const { manager, other } = await deployFixture();

      await expect(
        manager.connect(other).setModule(ethers.id("TREASURY"), other.address),
      )
        .to.be.revertedWithCustomError(manager, "OwnableUnauthorizedAccount")
        .withArgs(other.address);
    });

    it("Should reject an empty key", async function () {
      const { manager, other } = await deployFixture();

      await expect(
        manager.setModule(ethers.ZeroHash, other.address),
      ).to.be.revertedWith("Invalid key");
    });
  });

  describe("Purchase through the full system", function () {
    it("Should send payment to the Treasury contract and register in the Manager", async function () {
      const { token, treasury, nft, manager, user } = await deployFixture();

      const amount = ethers.parseUnits("1000", 18);

      await token.connect(user).approve(await nft.getAddress(), amount);

      await expect(nft.connect(user).buyNFT(1000))
        .to.emit(manager, "NFTRegistered");

      expect(await treasury.balance()).to.equal(amount);
      expect(await nft.ownerOf(0)).to.equal(user.address);
      expect((await manager.nftRecords(0)).packageAmount).to.equal(1000n);
    });

    it("Should not move any purchase money into the pools", async function () {
      const { token, nft, user, rewardPool, liquidityPool } = await deployFixture();

      const amount = ethers.parseUnits("2500", 18);

      await token.connect(user).approve(await nft.getAddress(), amount);
      await nft.connect(user).buyNFT(2500);

      expect(await rewardPool.balance()).to.equal(0n);
      expect(await rewardPool.totalDeposited()).to.equal(0n);
      expect(await liquidityPool.balance()).to.equal(0n);
    });

    it("Should start with no revenue sources and reject any deposit", async function () {
      const { token, rewardPool, other } = await deployFixture();

      expect(await rewardPool.getRevenueSources()).to.deep.equal([]);

      await token.connect(other).approve(await rewardPool.getAddress(), 1n);

      await expect(
        rewardPool.connect(other).deposit(1n, ethers.id("ANY_SOURCE")),
      ).to.be.revertedWith("Not authorized for source");

      expect(await rewardPool.availableRevenue()).to.equal(0n);
    });

    it("Should let the treasury owner withdraw purchase funds", async function () {
      const { token, treasury, nft, user, other } = await deployFixture();

      const amount = ethers.parseUnits("500", 18);

      await token.connect(user).approve(await nft.getAddress(), amount);
      await nft.connect(user).buyNFT(500);

      const otherBefore = await token.balanceOf(other.address);

      await treasury.withdraw(other.address, amount);

      expect(await token.balanceOf(other.address)).to.equal(otherBefore + amount);
      expect(await treasury.balance()).to.equal(0n);
    });
  });
});
