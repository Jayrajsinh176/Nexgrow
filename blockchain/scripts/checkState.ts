import hre from "hardhat";
import { readFileSync } from "node:fs";

async function main() {
  const { ethers } = await hre.network.connect();
  const deployment = JSON.parse(readFileSync("deployments/31337.json", "utf8"));
  const nft = await ethers.getContractAt("NexgrowNFT", deployment.contracts.nft);
  const ref = await ethers.getContractAt("ReferralIncome", deployment.contracts.referralIncome);
  const teamBusiness = await ethers.getContractAt("TeamBusiness", deployment.contracts.teamBusiness);
  const j1 = "0x70997970c51812dc3a010c7d01b50e0d17dc79c8";
  const j2 = "0x86451c43b722f4f49a1134c1263cd1e5ce70fb03";

  console.log("J1 NFT balance:", (await nft.balanceOf(j1)).toString());
  console.log("J2 NFT balance:", (await nft.balanceOf(j2)).toString());
  console.log("J2 sponsor on-chain:", await ref.sponsorOf(j2));
  console.log("J1 directCount on-chain:", (await ref.directCount(j1)).toString());
  console.log("J1 total team business:", (await teamBusiness.teamBusinessOf(j1)).toString());
  console.log("Referral pending jobs:", (await ref.pendingJobs()).toString());
}

main().catch(console.error);

