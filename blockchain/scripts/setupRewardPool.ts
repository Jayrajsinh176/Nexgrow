import hre from "hardhat";
import { readFileSync } from "node:fs";

async function main() {
  const { ethers } = await hre.network.connect();
  const { chainId } = await ethers.provider.getNetwork();
  const [deployer] = await ethers.getSigners();

  console.log(`[setupRewardPool] Chain ${chainId}, Deployer: ${deployer.address}`);

  const deployment = JSON.parse(readFileSync(`deployments/${chainId}.json`, "utf8"));
  const addr = deployment.contracts;

  const token = await ethers.getContractAt("TestNexadonToken", addr.paymentToken, deployer);
  const rewardPool = await ethers.getContractAt("RewardPool", addr.rewardPool, deployer);
  const nftIncome = await ethers.getContractAt("NFTIncome", addr.nftIncome, deployer);
  const referralIncome = await ethers.getContractAt("ReferralIncome", addr.referralIncome, deployer);

  const source = ethers.id("REVENUE_SALES");

  // 1. Add revenue source if not yet registered
  if (!(await rewardPool.isRevenueSource(source))) {
    console.log("Adding revenue source REVENUE_SALES...");
    const tx = await rewardPool.addRevenueSource(source, "NFT sales revenue");
    await tx.wait();
  }

  // 2. Set deployer as authorized depositor
  if (!(await rewardPool.isDepositor(deployer.address, source))) {
    console.log("Authorizing deployer as depositor...");
    const tx = await rewardPool.setDepositor(deployer.address, source, true);
    await tx.wait();
  }

  // 3. Set allocations for income modules (in basis points, max 10000 = 100%)
  const allocations: [string, string, bigint][] = [
    ["NFTIncome", addr.nftIncome, 4000n], // 40%
    ["ReferralIncome", addr.referralIncome, 1500n], // 15%
    ["BonusBooster", addr.bonusBooster, 1500n], // 15%
    ["TeamBuildingIncome", addr.teamBuildingIncome, 1000n], // 10%
    ["MonthlyLoyaltyBonus", addr.monthlyLoyaltyBonus, 500n], // 5%
    ["RoyaltyBonus", addr.royaltyBonus, 500n], // 5%
    ["TeamPerformanceBonus", addr.teamPerformanceBonus, 500n], // 5%
    ["TeamDevelopmentBonus", addr.teamDevelopmentBonus, 500n], // 5%
  ];

  for (const [name, moduleAddr, bps] of allocations) {
    if (!moduleAddr) continue;
    const curBps = await rewardPool.allocationBps(moduleAddr, source);
    if (curBps !== bps) {
      console.log(`Setting allocation for ${name} to ${bps} bps...`);
      const tx = await rewardPool.setAllocation(moduleAddr, source, bps);
      await tx.wait();
    }
    if (!(await rewardPool.isSpender(moduleAddr))) {
      console.log(`Setting spender for ${name}...`);
      const tx = await rewardPool.setSpender(moduleAddr, true);
      await tx.wait();
    }
  }

  // 4. Deposit 10,000 NXD into RewardPool
  const depositAmount = ethers.parseUnits("10000", 18);
  const poolAddress = await rewardPool.getAddress();
  const allowance = await token.allowance(deployer.address, poolAddress);
  if (allowance < depositAmount) {
    console.log("Approving RewardPool for NXD tokens...");
    const tx = await token.approve(poolAddress, ethers.MaxUint256);
    await tx.wait();
  }

  console.log("Depositing 10,000 NXD into RewardPool...");
  const depTx = await rewardPool.deposit(depositAmount, source);
  await depTx.wait();
  console.log(`Deposit tx: ${depTx.hash}`);

  // 5. Enable payouts on NFTIncome and ReferralIncome
  if (!(await nftIncome.payoutsEnabled())) {
    console.log("Enabling payouts on NFTIncome...");
    const tx = await nftIncome.setPayoutsEnabled(true);
    await tx.wait();
  }

  if (!(await referralIncome.payoutsEnabled())) {
    console.log("Enabling payouts on ReferralIncome...");
    const tx = await referralIncome.setPayoutsEnabled(true);
    await tx.wait();
  }

  // 6. Verify budgets
  const nftBudget = await rewardPool.budgetOf(addr.nftIncome);
  const refBudget = await rewardPool.budgetOf(addr.referralIncome);
  console.log(`RewardPool budget for NFTIncome: ${ethers.formatUnits(nftBudget, 18)} NXD`);
  console.log(`RewardPool budget for ReferralIncome: ${ethers.formatUnits(refBudget, 18)} NXD`);
  console.log(`NFTIncome payoutsEnabled: ${await nftIncome.payoutsEnabled()}`);
  console.log(`ReferralIncome payoutsEnabled: ${await referralIncome.payoutsEnabled()}`);
  console.log("Setup complete!");
}

main().catch((err) => {
  console.error("Error setting up RewardPool:", err);
  process.exitCode = 1;
});

