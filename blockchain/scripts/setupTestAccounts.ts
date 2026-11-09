import hre from "hardhat";
import { readFileSync } from "node:fs";

async function main() {
  const { ethers } = await hre.network.connect();
  const [deployer] = await ethers.getSigners();
  const deployment = JSON.parse(readFileSync("deployments/31337.json", "utf8"));
  const token = await ethers.getContractAt("TestNexadonToken", deployment.contracts.paymentToken);
  const nft = await ethers.getContractAt("NexgrowNFT", deployment.contracts.nft);

  const j1 = "0x70997970C51812dc3A010C7d01b50e0d17dc79C8";
  const j2 = "0x86451c43b722f4f49a1134c1263cd1e5ce70fb03";

  // Transfer tokens to j1 and j2
  await (await token.transfer(j1, ethers.parseUnits("10000", 18))).wait();
  await (await token.transfer(j2, ethers.parseUnits("10000", 18))).wait();

  // Send ETH for gas
  await (await deployer.sendTransaction({ to: j1, value: ethers.parseEther("10") })).wait();
  await (await deployer.sendTransaction({ to: j2, value: ethers.parseEther("10") })).wait();

  console.log("Tokens and ETH sent to J1 and J2.");

  // J1 buys NFT #1 (500 NXD) so J1 is an active sponsor
  const j1Signer = await ethers.getSigner(j1);
  await (await token.connect(j1Signer).approve(deployment.contracts.nft, ethers.parseUnits("500", 18))).wait();
  await (await nft.connect(j1Signer).buyNFT(500)).wait();

  console.log("J1 bought 500 NXD NFT. J1 NFT balance:", (await nft.balanceOf(j1)).toString());
}

main().catch(console.error);

