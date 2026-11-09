import hre from "hardhat";
import { readFileSync } from "node:fs";

async function main() {
  const { ethers } = await hre.network.connect();
  const { chainId } = await ethers.provider.getNetwork();
  const [deployer] = await ethers.getSigners();
  const jayraj1 = deployer.address;

  console.log(`[checkAllIncomes] Chain: ${chainId}, Account: ${jayraj1}`);

  const deployment = JSON.parse(readFileSync(`deployments/${chainId}.json`, "utf8"));
  const addr = deployment.contracts;

  const rewardPool = await ethers.getContractAt("RewardPool", addr.rewardPool, deployer);
  const poolBalance = await rewardPool.balance();
  console.log(`\nRewardPool Total Token Balance: ${ethers.formatUnits(poolBalance, 18)} NXD`);

  const modules: [string, string][] = [
    ["ReferralIncome", addr.referralIncome],
    ["TeamBuildingIncome", addr.teamBuildingIncome],
    ["BonusBooster", addr.bonusBooster],
    ["TeamPerformanceBonus", addr.teamPerformanceBonus],
    ["MonthlyLoyaltyBonus", addr.monthlyLoyaltyBonus],
    ["RoyaltyBonus", addr.royaltyBonus],
    ["TeamDevelopmentBonus", addr.teamDevelopmentBonus],
  ];

  console.log("\n=========================================================================================");
  console.log("MODULE STATUS & JAYRAJ-1 BALANCES");
  console.log("=========================================================================================");

  for (const [name, moduleAddr] of modules) {
    if (!moduleAddr) continue;
    try {
      const c = await ethers.getContractAt("PayoutRounds", moduleAddr, deployer);
      const [budget, payoutsEnabled, earner, unpaid, curRound] = await Promise.all([
        rewardPool.budgetOf(moduleAddr),
        c.payoutsEnabled().catch(() => false),
        c.earners(jayraj1).catch(() => null),
        c.unpaidOf(jayraj1).catch(() => 0n),
        c.currentRound().catch(() => 0n),
      ]);

      const credited = earner ? ethers.formatUnits(earner.credited, 18) : "0.00";
      const paid = earner ? ethers.formatUnits(earner.paid, 18) : "0.00";
      const unpaidStr = ethers.formatUnits(unpaid, 18);
      const budgetStr = ethers.formatUnits(budget, 18);

      console.log(`\n[${name}] (${moduleAddr})`);
      console.log(`  Payouts Enabled : ${payoutsEnabled}`);
      console.log(`  Module Budget   : ${budgetStr} NXD`);
      console.log(`  Current Round   : #${curRound}`);
      console.log(`  Credited        : ${credited} NXD`);
      console.log(`  Paid to Wallet  : ${paid} NXD`);
      console.log(`  Pending (Unpaid): ${unpaidStr} NXD`);
    } catch (e: any) {
      console.log(`\n[${name}] Error reading: ${e.message}`);
    }
  }

  // Check NFTIncome separately
  try {
    const nftInc = await ethers.getContractAt("NFTIncome", addr.nftIncome, deployer);
    const nftBudget = await rewardPool.budgetOf(addr.nftIncome);
    const payoutsEnabled = await nftInc.payoutsEnabled();
    const curRound = await nftInc.currentRound();
    console.log(`\n[NFTIncome] (${addr.nftIncome})`);
    console.log(`  Payouts Enabled : ${payoutsEnabled}`);
    console.log(`  Module Budget   : ${ethers.formatUnits(nftBudget, 18)} NXD`);
    console.log(`  Current Round   : #${curRound}`);
  } catch (e: any) {
    console.log(`\n[NFTIncome] Error: ${e.message}`);
  }
}

main().catch((err) => {
  console.error("Error checking incomes:", err);
  process.exitCode = 1;
});

