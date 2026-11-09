import { expect } from "chai";
import { network } from "hardhat";

const { ethers } = await network.create();

describe("NexgrowManager", function () {
  async function deployFixture() {
    const [owner, treasury, user, other] = await ethers.getSigners();

    const token = await ethers.deployContract("TestNexadonToken");
    const nft = await ethers.deployContract("NexgrowNFT", [
      await token.getAddress(),
      treasury.address,
    ]);
    const manager = await ethers.deployContract("NexgrowManager", [
      await nft.getAddress(),
    ]);

    await nft.setManager(await manager.getAddress());

    // Give the user 10,000 tNEX to buy with
    await token.transfer(user.address, ethers.parseUnits("10000", 18));

    return { owner, treasury, user, other, token, nft, manager };
  }

  async function buy(
    token: any,
    nft: any,
    buyer: any,
    packageAmount: number,
  ) {
    const amount = ethers.parseUnits(packageAmount.toString(), 18);

    await token.connect(buyer).approve(await nft.getAddress(), amount);

    return nft.connect(buyer).buyNFT(packageAmount);
  }

  describe("Setup", function () {
    it("Should set the deployer as owner", async function () {
      const { owner, manager } = await deployFixture();

      expect(await manager.owner()).to.equal(owner.address);
    });

    it("Should store the NFT contract address", async function () {
      const { nft, manager } = await deployFixture();

      expect(await manager.nftContract()).to.equal(await nft.getAddress());
    });

    it("Should reject the zero address on deploy", async function () {
      await expect(
        ethers.deployContract("NexgrowManager", [ethers.ZeroAddress]),
      ).to.be.revertedWith("Invalid NFT address");
    });

    it("Should let the owner update the NFT contract", async function () {
      const { nft, manager, token, treasury } = await deployFixture();

      const newNft = await ethers.deployContract("NexgrowNFT", [
        await token.getAddress(),
        treasury.address,
      ]);

      await expect(manager.setNFTContract(await newNft.getAddress()))
        .to.emit(manager, "NFTContractUpdated")
        .withArgs(await nft.getAddress(), await newNft.getAddress());

      expect(await manager.nftContract()).to.equal(await newNft.getAddress());
    });

    it("Should block non-owners from updating the NFT contract", async function () {
      const { manager, other } = await deployFixture();

      await expect(manager.connect(other).setNFTContract(other.address))
        .to.be.revertedWithCustomError(manager, "OwnableUnauthorizedAccount")
        .withArgs(other.address);
    });

    it("Should reject the zero address on update", async function () {
      const { manager } = await deployFixture();

      await expect(
        manager.setNFTContract(ethers.ZeroAddress),
      ).to.be.revertedWith("Invalid NFT address");
    });
  });

  describe("NFT -> Manager wiring", function () {
    it("Should set the deployer as NFT owner", async function () {
      const { owner, nft } = await deployFixture();

      expect(await nft.owner()).to.equal(owner.address);
    });

    it("Should store the manager in the NFT and emit ManagerUpdated", async function () {
      const { token, treasury, manager } = await deployFixture();

      const nft = await ethers.deployContract("NexgrowNFT", [
        await token.getAddress(),
        treasury.address,
      ]);

      await expect(nft.setManager(await manager.getAddress()))
        .to.emit(nft, "ManagerUpdated")
        .withArgs(ethers.ZeroAddress, await manager.getAddress());

      expect(await nft.manager()).to.equal(await manager.getAddress());
    });

    it("Should block non-owners from setting the manager", async function () {
      const { nft, other } = await deployFixture();

      await expect(nft.connect(other).setManager(other.address))
        .to.be.revertedWithCustomError(nft, "OwnableUnauthorizedAccount")
        .withArgs(other.address);
    });

    it("Should reject the zero address as manager", async function () {
      const { nft } = await deployFixture();

      await expect(
        nft.setManager(ethers.ZeroAddress),
      ).to.be.revertedWith("Invalid manager address");
    });

    it("Should block buyNFT when no manager is set", async function () {
      const { token, treasury, user } = await deployFixture();

      const nft = await ethers.deployContract("NexgrowNFT", [
        await token.getAddress(),
        treasury.address,
      ]);

      await expect(buy(token, nft, user, 500)).to.be.revertedWith(
        "Manager not set",
      );
    });

    it("Should only accept registrations from the NFT contract", async function () {
      const { manager, other } = await deployFixture();

      await expect(
        manager.connect(other).registerNFT(0, other.address, 500),
      ).to.be.revertedWith("Only NFT contract");
    });
  });

  describe("Purchase flow", function () {
    it("Should keep the payment flow unchanged", async function () {
      const { token, nft, treasury, user } = await deployFixture();

      const amount = ethers.parseUnits("500", 18);
      const userBefore = await token.balanceOf(user.address);

      await buy(token, nft, user, 500);

      expect(await token.balanceOf(user.address)).to.equal(userBefore - amount);
      expect(await token.balanceOf(treasury.address)).to.equal(amount);
      expect(await nft.ownerOf(0)).to.equal(user.address);
      expect(await nft.nftPackage(0)).to.equal(500n);
    });

    it("Should still reject invalid packages", async function () {
      const { token, nft, user } = await deployFixture();

      await expect(buy(token, nft, user, 300)).to.be.revertedWith(
        "Invalid package",
      );
    });

    it("Should register the NFT in the manager and emit NFTRegistered", async function () {
      const { token, nft, manager, user } = await deployFixture();

      const tx = await buy(token, nft, user, 500);
      const block = await ethers.provider.getBlock(tx.blockNumber);

      await expect(tx)
        .to.emit(manager, "NFTRegistered")
        .withArgs(0n, user.address, 500n, block!.timestamp);

      const record = await manager.nftRecords(0);

      expect(record.buyer).to.equal(user.address);
      expect(record.packageAmount).to.equal(500n);
      expect(record.registeredAt).to.equal(block!.timestamp);
      expect(await manager.isRegistered(0)).to.equal(true);
      expect(await manager.isRegistered(1)).to.equal(false);
    });

    it("Should register multiple purchases with the right IDs", async function () {
      const { token, nft, manager, user, owner } = await deployFixture();

      await buy(token, nft, user, 50);
      await buy(token, nft, owner, 5000);
      await buy(token, nft, user, 1000);

      expect((await manager.nftRecords(0)).packageAmount).to.equal(50n);
      expect((await manager.nftRecords(1)).buyer).to.equal(owner.address);
      expect((await manager.nftRecords(1)).packageAmount).to.equal(5000n);
      expect((await manager.nftRecords(2)).packageAmount).to.equal(1000n);
    });

    it("Should read a purchased NFT's package and owner through the manager", async function () {
      const { token, nft, manager, user } = await deployFixture();

      await buy(token, nft, user, 500);

      expect(await manager.getNFTPackage(0)).to.equal(500n);
      expect(await manager.getNFTOwner(0)).to.equal(user.address);
    });
  });
});
