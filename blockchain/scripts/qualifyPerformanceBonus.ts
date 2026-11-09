import hre from "hardhat";
import { readFileSync } from "node:fs";

async function main() {
  const { ethers } = await hre.network.connect();
  const { chainId } = await ethers.provider.getNetwork();
  const [deployer] = await ethers.getSigners();

  console.log(`[qualifyPerformanceBonus] Chain ${chainId}, Deployer: ${deployer.address}`);

  const deployment = JSON.parse(readFileSync(`deployments/${chainId}.json`, "utf8"));
  const addr = deployment.contracts;

  const token = await ethers.getContractAt("TestNexadonToken", addr.paymentToken, deployer);
  const nft = await ethers.getContractAt("NexgrowNFT", addr.nft, deployer);
  const ref = await ethers.getContractAt("ReferralIncome", addr.referralIncome, deployer);
  const teamBusiness = await ethers.getContractAt("TeamBusiness", addr.teamBusiness, deployer);
  const performance = await ethers.getContractAt("TeamPerformanceBonus", addr.teamPerformanceBonus, deployer);
  const nftAddress = await nft.getAddress();

  // The 4 direct legs of Jayraj-1
  const leg0 = "0xb74dee18E6d49fCd85D7460A2fABFB382d3299b1"; // Jayraj-2 (currently 500 NXD)
  const leg1 = "0xC6D0A913A493a8aaFeF93c7e7455A21aa5f349E9"; // Jayraj-3 (currently 250 NXD)
  const leg2 = "0x1BCAd269bFf1b15c6F6961F36dF5A4b583Fc664F"; // Jayraj-5 (currently 50 NXD)
  const leg3 = "0xadb31661ABd82590de282535DFfb98aee6C7a5a4"; // Jayraj-6 (currently 50 NXD)

  const legsConfig: [string, number][] = [
    [leg0, 5000], // Needs >= 4000 NXD for 40% lead leg
    [leg1, 2500], // Needs >= 2000 NXD for 20%
    [leg2, 2500], // Needs >= 2000 NXD for 20%
    [leg3, 2500], // Needs >= 2000 NXD for 20%
  ];

  console.log("Creating 4 downline purchases under the 4 legs...");

  for (let i = 0; i < legsConfig.length; i++) {
    const [sponsor, pkg] = legsConfig[i];
    const w = ethers.Wallet.createRandom(ethers.provider);

    console.log(`\n--- Downline under Leg #${i} (${sponsor.slice(0, 8)}...): buying ${pkg} NXD ---`);

    // 1. Gas
    const gasTx = await deployer.sendTransaction({
      to: w.address,
      value: ethers.parseEther("0.003"),
    });
    await gasTx.wait();

    // 2. Token
    const tokenTx = await token.transfer(w.address, ethers.parseUnits(String(pkg + 10), 18));
    await tokenTx.wait();

    // 3. Set Sponsor
    const spTx = await ref.connect(w).setSponsor(sponsor);
    await spTx.wait();

    // 4. Approve
    const appTx = await token.connect(w).approve(nftAddress, ethers.parseUnits(String(pkg), 18));
    await appTx.wait();

    // 5. Buy NFT
    const buyTx = await nft.connect(w).buyNFT(pkg);
    await buyTx.wait();
    console.log(`Purchase confirmed: tx ${buyTx.hash}`);
  }

  // Process all team business queues
  console.log("\nProcessing all team business queues...");
  while ((await teamBusiness.pendingJobs()) > 0n) {
    const qTx = await teamBusiness.processQueue(100);
    await qTx.wait();
  }

  // Check the 4 legs
  console.log("\nChecking the 4 legs' lifetime business:");
  for (let i = 0; i < 4; i++) {
    const leg = [leg0, leg1, leg2, leg3][i];
    const biz = await teamBusiness.lifetimeBusiness(leg);
    console.log(`  Leg ${i} (${leg.slice(0, 8)}...): ${ethers.formatUnits(biz, 18)} NXD`);
  }

  // Call qualify() on TeamPerformanceBonus
  console.log("\nCalling TeamPerformanceBonus.qualify() for Jayraj-1...");
  const qualifyTx = await performance.qualify(deployer.address, [leg0, leg1, leg2, leg3]);
  const receipt = await qualifyTx.wait();
  console.log(`Qualify tx confirmed: ${qualifyTx.hash} (gas: ${receipt.gasUsed})`);

  // Verify updated state
  const next = await performance.nextTier(deployer.address);
  const earner = await performance.earners(deployer.address);

  console.log("\n==========================================");
  console.log("QUALIFICATION SUCCESSFUL!");
  console.log(`  Jayraj-1 Next Tier: ${next} (Tier 1: 50,000 NXD Target)`);
  console.log(`  Jayraj-1 Credited Bonus: ${ethers.formatUnits(earner.credited, 18)} NXD`);
  console.log("==========================================");
}

main().catch((err) => {
  console.error("Error qualifying performance bonus:", err);
  process.exitCode = 1;
});

