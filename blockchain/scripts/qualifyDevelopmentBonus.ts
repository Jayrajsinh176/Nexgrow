import hre from "hardhat";
import { readFileSync } from "node:fs";

async function main() {
  const { ethers } = await hre.network.connect();
  const { chainId } = await ethers.provider.getNetwork();
  const [deployer] = await ethers.getSigners();

  console.log(`[qualifyDevelopmentBonus] Chain ${chainId}, Deployer: ${deployer.address}`);

  const deployment = JSON.parse(readFileSync(`deployments/${chainId}.json`, "utf8"));
  const addr = deployment.contracts;

  const token = await ethers.getContractAt("TestNexadonToken", addr.paymentToken, deployer);
  const nft = await ethers.getContractAt("NexgrowNFT", addr.nft, deployer);
  const ref = await ethers.getContractAt("ReferralIncome", addr.referralIncome, deployer);
  const teamBusiness = await ethers.getContractAt("TeamBusiness", addr.teamBusiness, deployer);
  const devBonus = await ethers.getContractAt("TeamDevelopmentBonus", addr.teamDevelopmentBonus, deployer);
  const nftAddress = await nft.getAddress();

  const leg0 = "0xb74dee18E6d49fCd85D7460A2fABFB382d3299b1"; // Jayraj-2 (currently 5500 NXD)
  const leg1 = "0xC6D0A913A493a8aaFeF93c7e7455A21aa5f349E9"; // Jayraj-3 (currently 2750 NXD)
  const leg2 = "0x1BCAd269bFf1b15c6F6961F36dF5A4b583Fc664F"; // Jayraj-5 (currently 2550 NXD)

  // Top up Leg 1 and Leg 2 so both reach >= 5000 NXD
  const topups: [string, number][] = [
    [leg1, 2500], // 2750 + 2500 = 5250 NXD
    [leg2, 2500], // 2550 + 2500 = 5050 NXD
  ];

  for (let i = 0; i < topups.length; i++) {
    const [sponsor, pkg] = topups[i];
    const w = ethers.Wallet.createRandom(ethers.provider);

    console.log(`\n--- Downline under ${sponsor.slice(0, 8)}...: buying ${pkg} NXD ---`);

    const gasTx = await deployer.sendTransaction({
      to: w.address,
      value: ethers.parseEther("0.003"),
    });
    await gasTx.wait();

    const tokenTx = await token.transfer(w.address, ethers.parseUnits(String(pkg + 10), 18));
    await tokenTx.wait();

    const spTx = await ref.connect(w).setSponsor(sponsor);
    await spTx.wait();

    const appTx = await token.connect(w).approve(nftAddress, ethers.parseUnits(String(pkg), 18));
    await appTx.wait();

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

  // Check the 3 legs' lifetime business
  console.log("\nVerifying 3 legs (each must be >= 5000 NXD):");
  const legs = [leg0, leg1, leg2];
  for (let i = 0; i < legs.length; i++) {
    const biz = await teamBusiness.lifetimeBusiness(legs[i]);
    console.log(`  Leg ${i} (${legs[i].slice(0, 8)}...): ${ethers.formatUnits(biz, 18)} NXD`);
  }

  // Call qualify() on TeamDevelopmentBonus
  console.log("\nCalling TeamDevelopmentBonus.qualify() for Jayraj-1...");
  const qualifyTx = await devBonus.qualify(deployer.address, [leg0, leg1, leg2]);
  const receipt = await qualifyTx.wait();
  console.log(`Qualify tx confirmed: ${qualifyTx.hash} (gas: ${receipt.gasUsed})`);

  // Verify qualification
  const qualified = await devBonus.isQualified(deployer.address);
  const start = await devBonus.windowStart(deployer.address);
  const end = await devBonus.windowEnd(deployer.address);
  const extraBps = await devBonus.extraBps();

  console.log("\n==========================================");
  console.log("TEAM DEVELOPMENT BONUS QUALIFICATION SUCCESSFUL!");
  console.log(`  Qualified: ${qualified}`);
  console.log(`  Extra bonus rate: ${Number(extraBps) / 100}%`);
  console.log(`  Window start: ${new Date(Number(start) * 1000).toUTCString()}`);
  console.log(`  Window end: ${new Date(Number(end) * 1000).toUTCString()}`);
  console.log("==========================================");
}

main().catch((err) => {
  console.error("Error qualifying development bonus:", err);
  process.exitCode = 1;
});

