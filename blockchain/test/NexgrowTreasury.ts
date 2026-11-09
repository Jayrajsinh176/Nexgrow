import { expect } from "chai";
import { network } from "hardhat";

const { ethers } = await network.create();

describe("NexgrowTreasury", function () {
  async function deployFixture() {
    const [owner, user, other] = await ethers.getSigners();

    const token = await ethers.deployContract("TestNexadonToken");
    const treasury = await ethers.deployContract("NexgrowTreasury", [
      await token.getAddress(),
    ]);

    // Put 1,000 tNEX in the treasury
    await token.transfer(
      await treasury.getAddress(),
      ethers.parseUnits("1000", 18),
    );

    return { owner, user, other, token, treasury };
  }

  it("Should set owner and token", async function () {
    const { owner, token, treasury } = await deployFixture();

    expect(await treasury.owner()).to.equal(owner.address);
    expect(await treasury.token()).to.equal(await token.getAddress());
  });

  it("Should reject the zero token address", async function () {
    await expect(
      ethers.deployContract("NexgrowTreasury", [ethers.ZeroAddress]),
    ).to.be.revertedWith("Invalid token address");
  });

  it("Should report its token balance", async function () {
    const { treasury } = await deployFixture();

    expect(await treasury.balance()).to.equal(ethers.parseUnits("1000", 18));
  });

  it("Should let the owner withdraw and emit Withdrawn", async function () {
    const { token, treasury, user } = await deployFixture();

    const amount = ethers.parseUnits("250", 18);

    await expect(treasury.withdraw(user.address, amount))
      .to.emit(treasury, "Withdrawn")
      .withArgs(user.address, amount);

    expect(await token.balanceOf(user.address)).to.equal(amount);
    expect(await treasury.balance()).to.equal(ethers.parseUnits("750", 18));
  });

  it("Should block non-owners from withdrawing", async function () {
    const { treasury, other } = await deployFixture();

    await expect(treasury.connect(other).withdraw(other.address, 1n))
      .to.be.revertedWithCustomError(treasury, "OwnableUnauthorizedAccount")
      .withArgs(other.address);
  });

  it("Should reject invalid withdrawals", async function () {
    const { treasury, user, token } = await deployFixture();

    await expect(
      treasury.withdraw(ethers.ZeroAddress, 1n),
    ).to.be.revertedWith("Invalid recipient");

    await expect(
      treasury.withdraw(user.address, 0n),
    ).to.be.revertedWith("Invalid amount");

    await expect(
      treasury.withdraw(user.address, ethers.parseUnits("1001", 18)),
    ).to.be.revertedWithCustomError(token, "ERC20InsufficientBalance");
  });
});
