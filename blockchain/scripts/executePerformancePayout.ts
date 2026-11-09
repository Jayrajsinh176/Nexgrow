import hre from "hardhat";
import { readFileSync } from "node:fs";

async function main() {
  const { ethers } = await hre.network.connect();
  const { chainId } = await ethers.provider.getNetwork();
  const [deployer] = await ethers.getSigners();
  const jayraj1 = deployer.address;

  console.log(`[executePerformancePayout] Chain: ${chainId}, Deployer / Jayraj-1: ${jayraj1}`);

  const deployment = JSON.parse(readFileSync(`deployments/${chainId}.json`, "utf8"));
  const addr = deployment.contracts;

  const token = await ethers.getContractAt("TestNexadonToken", addr.paymentToken, deployer);
  const rewardPool = await ethers.getContractAt("RewardPool", addr.rewardPool, deployer);
  const perfBonus = await ethers.getContractAt("TeamPerformanceBonus", addr.teamPerformanceBonus, deployer);

  // 1. Initial balances
  const initialTokenBalance = await token.balanceOf(jayraj1);
  const initialUnpaid = await perfBonus.unpaidOf(jayraj1);
  const perfBudget = await rewardPool.budgetOf(addr.teamPerformanceBonus);

  console.log("\n--- Before Payout ---");
  console.log(`Jayraj-1 Wallet NXD Balance: ${ethers.formatUnits(initialTokenBalance, 18)} NXD`);
  console.log(`Jayraj-1 Pending Bonus     : ${ethers.formatUnits(initialUnpaid, 18)} NXD`);
  console.log(`Module RewardPool Budget   : ${ethers.formatUnits(perfBudget, 18)} NXD`);

  // 2. Enable payouts if not enabled
  if (!(await perfBonus.payoutsEnabled())) {
    console.log("\nEnabling payouts on TeamPerformanceBonus...");
    const tx = await perfBonus.setPayoutsEnabled(true);
    await tx.wait();
    console.log(`Payouts enabled: tx ${tx.hash}`);
  }

  // 3. Start Round 1
  const curRound = await perfBonus.currentRound();
  console.log(`\nStarting Payment Round (current was #${curRound})...`);
  const roundTx = await perfBonus.startRound();
  await roundTx.wait();
  const newRound = await perfBonus.currentRound();
  console.log(`Round #${newRound} started! Tx: ${roundTx.hash}`);

  // 4. Claim for Jayraj-1
  console.log(`\nClaiming payout for Jayraj-1 (${jayraj1})...`);
  const claimTx = await perfBonus.claim([jayraj1]);
  const receipt = await claimTx.wait();
  console.log(`Claim confirmed! Tx: ${claimTx.hash} (gas: ${receipt.gasUsed})`);

  // 5. Final balances verification
  const finalTokenBalance = await token.balanceOf(jayraj1);
  const finalUnpaid = await perfBonus.unpaidOf(jayraj1);
  const earner = await perfBonus.earners(jayraj1);

  console.log("\n==========================================");
  console.log("GRAND PAYOUT SETTLED SUCCESSFULLY!");
  console.log(`  Initial Wallet Balance : ${ethers.formatUnits(initialTokenBalance, 18)} NXD`);
  console.log(`  Final Wallet Balance   : ${ethers.formatUnits(finalTokenBalance, 18)} NXD (+${ethers.formatUnits(finalTokenBalance - initialTokenBalance, 18)} NXD received!)`);
  console.log(`  Total Credited on-chain: ${ethers.formatUnits(earner.credited, 18)} NXD`);
  console.log(`  Total Paid on-chain    : ${ethers.formatUnits(earner.paid, 18)} NXD`);
  console.log(`  Remaining Pending      : ${ethers.formatUnits(finalUnpaid, 18)} NXD`);
  console.log("==========================================");
}

main().catch((err) => {
  console.error("Error executing performance payout:", err);
  process.exitCode = 1;
});

