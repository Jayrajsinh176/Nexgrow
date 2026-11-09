import hre from "hardhat";
import { readFileSync } from "node:fs";

async function main() {
  const { ethers } = await hre.network.connect();
  const deployment = JSON.parse(readFileSync("deployments/31337.json", "utf8"));
  const token = await ethers.getContractAt("TestNexadonToken", deployment.contracts.paymentToken);
  const nft = await ethers.getContractAt("NexgrowNFT", deployment.contracts.nft);
  const ref = await ethers.getContractAt("ReferralIncome", deployment.contracts.referralIncome);

  const j1 = "0x70997970C51812dc3A010C7d01b50e0d17dc79C8";
  const j2 = "0x86451c43b722f4f49a1134c1263cd1e5ce70fb03";

  await ethers.provider.send("hardhat_impersonateAccount", [j2]);
  const j2Signer = await ethers.getSigner(j2);

  console.log("J2 setting sponsor J1...");
  await (await ref.connect(j2Signer).setSponsor(j1)).wait();
  console.log("J2 sponsor set:", await ref.sponsorOf(j2));

  console.log("J2 approving 500 NXD...");
  await (await token.connect(j2Signer).approve(deployment.contracts.nft, ethers.parseUnits("500", 18))).wait();

  console.log("J2 buying 500 NXD NFT...");
  await (await nft.connect(j2Signer).buyNFT(500)).wait();

  console.log("J2 NFT balance:", (await nft.balanceOf(j2)).toString());
  console.log("J1 direct count:", (await ref.directCount(j1)).toString());
}

main().catch(console.error);
