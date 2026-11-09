import hre from "hardhat";
import { readFileSync, writeFileSync } from "node:fs";

async function main() {
  const { ethers } = await hre.network.connect();
  const { chainId } = await ethers.provider.getNetwork();
  const [deployer] = await ethers.getSigners();

  console.log(`[unlockFiveLevels] Chain ${chainId}, Deployer: ${deployer.address}`);

  const deployment = JSON.parse(readFileSync(`deployments/${chainId}.json`, "utf8"));
  const addr = deployment.contracts;

  const token = await ethers.getContractAt("TestNexadonToken", addr.paymentToken, deployer);
  const nft = await ethers.getContractAt("NexgrowNFT", addr.nft, deployer);
  const ref = await ethers.getContractAt("ReferralIncome", addr.referralIncome, deployer);
  const teamBuilding = await ethers.getContractAt("TeamBuildingIncome", addr.teamBuildingIncome, deployer);
  const teamBusiness = await ethers.getContractAt("TeamBusiness", addr.teamBusiness, deployer);
  const nftAddress = await nft.getAddress();

  const initialDirects = await ref.directCount(deployer.address);
  console.log(`Current on-chain direct count for Jayraj-1: ${initialDirects}`);

  const needed = 5 - Number(initialDirects);
  console.log(`Need ${needed} more downline(s) to reach 5 directs.`);

  if (needed <= 0) {
    console.log("Already has 5 or more directs!");
    return;
  }

  const newWallets: string[] = [];

  for (let i = 1; i <= needed; i++) {
    const w = ethers.Wallet.createRandom(ethers.provider);
    console.log(`\n--- Downline #${Number(initialDirects) + i} (${w.address}) ---`);

    // 1. Send gas and NXD from deployer
    console.log("Sending gas (0.003 tBNB)...");
    const gasTx = await deployer.sendTransaction({
      to: w.address,
      value: ethers.parseEther("0.003"),
    });
    await gasTx.wait();

    console.log("Sending 60 NXD...");
    const tokenTx = await token.transfer(w.address, ethers.parseUnits("60", 18));
    await tokenTx.wait();

    // 2. Set sponsor to Jayraj-1
    console.log("Setting sponsor on-chain...");
    const sponsorTx = await ref.connect(w).setSponsor(deployer.address);
    await sponsorTx.wait();

    // 3. Approve NXD
    console.log("Approving 50 NXD...");
    const appTx = await token.connect(w).approve(nftAddress, ethers.parseUnits("50", 18));
    await appTx.wait();

    // 4. Buy 50 NXD NFT
    console.log("Buying 50 NXD NFT...");
    const buyTx = await nft.connect(w).buyNFT(50);
    const buyReceipt = await buyTx.wait();
    console.log(`Bought! Tx: ${buyTx.hash} (gas: ${buyReceipt?.gasUsed})`);

    newWallets.push(w.address);
  }

  // 5. Process queue
  console.log("\nProcessing team business queues...");
  while ((await teamBusiness.pendingJobs()) > 0n) {
    const qTx = await teamBusiness.processQueue(100);
    await qTx.wait();
  }

  // 6. Verify final state
  const finalDirects = await ref.directCount(deployer.address);
  const unlocked = await teamBuilding.unlockedLevels(deployer.address);

  console.log("\n==========================================");
  console.log(`Jayraj-1 On-Chain Direct Count: ${finalDirects}`);
  console.log(`Team Building Unlocked Levels: ${unlocked}`);
  console.log("==========================================");

  // Save new wallet addresses to file for Laravel database linking
  writeFileSync("scripts/new_downlines.json", JSON.stringify(newWallets, null, 2));
  console.log("New downline addresses saved to scripts/new_downlines.json");
}

main().catch((err) => {
  console.error("Error unlocking 5 levels:", err);
  process.exitCode = 1;
});

