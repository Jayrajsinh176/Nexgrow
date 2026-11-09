import hre from "hardhat";
import { mkdirSync, writeFileSync } from "node:fs";

// Deploys the Nexgrow system and wires everything together, then does one
// test purchase. No revenue sources are registered in the pools yet, so all
// payouts stay disabled.
//
// The payment token is PAYMENT_TOKEN if set; otherwise a TestNexadonToken
// is deployed (local and test networks only).
// All addresses are written to deployments/<chainId>.json for the keeper.
async function main() {
  const { ethers } = await hre.network.connect();

  const [deployer] = await ethers.getSigners();
  const { chainId } = await ethers.provider.getNetwork();

  console.log("Deployer:", deployer.address);
  console.log("Chain ID:", chainId.toString());

  // The keeper scans contract events from this block.
  const startBlock = await ethers.provider.getBlockNumber();

  let paymentTokenAddress = process.env.PAYMENT_TOKEN ?? "";

  if (paymentTokenAddress === "") {
    if (chainId !== 31337n && process.env.ALLOW_TEST_TOKEN !== "1") {
      throw new Error(
        "PAYMENT_TOKEN is not set. Refusing to deploy the test token on a live " +
        "network (set ALLOW_TEST_TOKEN=1 to allow it on a testnet)."
      );
    }

    const testToken = await ethers.deployContract("TestNexadonToken");
    await testToken.waitForDeployment();
    paymentTokenAddress = await testToken.getAddress();
    console.log("TestNexadonToken (deployed):", paymentTokenAddress);
  } else {
    console.log("Payment token:", paymentTokenAddress);
  }

  const token = await ethers.getContractAt(
    "TestNexadonToken",
    paymentTokenAddress
  );

  // 1. Treasury
  const treasury = await ethers.deployContract(
    "NexgrowTreasury",
    [paymentTokenAddress]
  );
  await treasury.waitForDeployment();
  const treasuryAddress = await treasury.getAddress();
  console.log("NexgrowTreasury:", treasuryAddress);

  // 2. NFT (pays into the Treasury contract)
  const nft = await ethers.deployContract(
    "NexgrowNFT",
    [paymentTokenAddress, treasuryAddress]
  );
  await nft.waitForDeployment();
  const nftAddress = await nft.getAddress();
  console.log("NexgrowNFT:", nftAddress);

  // 3. Manager
  const manager = await ethers.deployContract(
    "NexgrowManager",
    [nftAddress]
  );
  await manager.waitForDeployment();
  const managerAddress = await manager.getAddress();
  console.log("NexgrowManager:", managerAddress);

  // 4. Pools
  const rewardPool = await ethers.deployContract(
    "RewardPool",
    [paymentTokenAddress]
  );
  await rewardPool.waitForDeployment();
  const rewardPoolAddress = await rewardPool.getAddress();
  console.log("RewardPool:", rewardPoolAddress);

  const liquidityPool = await ethers.deployContract(
    "LiquidityPool",
    [paymentTokenAddress]
  );
  await liquidityPool.waitForDeployment();
  const liquidityPoolAddress = await liquidityPool.getAddress();
  console.log("LiquidityPool:", liquidityPoolAddress);

  // 5. NFTIncome (payouts stay disabled, pool allocation stays 0)
  const nftIncome = await ethers.deployContract(
    "NFTIncome",
    [managerAddress, nftAddress, rewardPoolAddress]
  );
  await nftIncome.waitForDeployment();
  const nftIncomeAddress = await nftIncome.getAddress();
  console.log("NFTIncome:", nftIncomeAddress);

  // 6. ReferralIncome (payouts stay disabled, pool allocation stays 0)
  const referralIncome = await ethers.deployContract(
    "ReferralIncome",
    [managerAddress, nftAddress, rewardPoolAddress]
  );
  await referralIncome.waitForDeployment();
  const referralIncomeAddress = await referralIncome.getAddress();
  console.log("ReferralIncome:", referralIncomeAddress);

  // TeamBuildingIncome (uses ReferralIncome sponsor tree; payouts stay disabled)
  const teamBuildingIncome = await ethers.deployContract(
    "TeamBuildingIncome",
    [managerAddress, nftAddress, rewardPoolAddress, referralIncomeAddress]
  );
  await teamBuildingIncome.waitForDeployment();
  const teamBuildingIncomeAddress = await teamBuildingIncome.getAddress();
  console.log("TeamBuildingIncome:", teamBuildingIncomeAddress);

  // TeamBusiness tracker (team purchase volume, read by business-based modules)
  const teamBusiness = await ethers.deployContract(
    "TeamBusiness",
    [managerAddress, referralIncomeAddress]
  );
  await teamBusiness.waitForDeployment();
  const teamBusinessAddress = await teamBusiness.getAddress();
  console.log("TeamBusiness:", teamBusinessAddress);

  // BonusBooster (15-day periods start today; payouts stay disabled)
  const bonusBooster = await ethers.deployContract(
    "BonusBooster",
    [managerAddress, nftAddress, rewardPoolAddress, teamBusinessAddress]
  );
  await bonusBooster.waitForDeployment();
  const bonusBoosterAddress = await bonusBooster.getAddress();
  console.log("BonusBooster:", bonusBoosterAddress);

  // TeamPerformanceBonus (payouts stay disabled)
  const teamPerformanceBonus = await ethers.deployContract(
    "TeamPerformanceBonus",
    [managerAddress, nftAddress, rewardPoolAddress, referralIncomeAddress, teamBusinessAddress]
  );
  await teamPerformanceBonus.waitForDeployment();
  const teamPerformanceBonusAddress = await teamPerformanceBonus.getAddress();
  console.log("TeamPerformanceBonus:", teamPerformanceBonusAddress);

  // MonthlyLoyaltyBonus (payouts stay disabled)
  const monthlyLoyaltyBonus = await ethers.deployContract(
    "MonthlyLoyaltyBonus",
    [managerAddress, nftAddress, rewardPoolAddress, teamBusinessAddress]
  );
  await monthlyLoyaltyBonus.waitForDeployment();
  const monthlyLoyaltyBonusAddress = await monthlyLoyaltyBonus.getAddress();
  console.log("MonthlyLoyaltyBonus:", monthlyLoyaltyBonusAddress);

  // RoyaltyBonus (payouts stay disabled)
  const royaltyBonus = await ethers.deployContract(
    "RoyaltyBonus",
    [managerAddress, nftAddress, rewardPoolAddress, teamBusinessAddress]
  );
  await royaltyBonus.waitForDeployment();
  const royaltyBonusAddress = await royaltyBonus.getAddress();
  console.log("RoyaltyBonus:", royaltyBonusAddress);

  // TeamDevelopmentBonus (5% extra for 12 months; payouts stay disabled)
  const teamDevelopmentBonus = await ethers.deployContract(
    "TeamDevelopmentBonus",
    [managerAddress, nftAddress, rewardPoolAddress, referralIncomeAddress, teamBusinessAddress]
  );
  await teamDevelopmentBonus.waitForDeployment();
  const teamDevelopmentBonusAddress = await teamDevelopmentBonus.getAddress();
  console.log("TeamDevelopmentBonus:", teamDevelopmentBonusAddress);

  // ProfitSharingSlot (separate product; deductions go to the Treasury)
  const profitSharingSlot = await ethers.deployContract(
    "ProfitSharingSlot",
    [paymentTokenAddress, treasuryAddress]
  );
  await profitSharingSlot.waitForDeployment();
  const profitSharingSlotAddress = await profitSharingSlot.getAddress();
  console.log("ProfitSharingSlot:", profitSharingSlotAddress);

  // 7. Wiring
  await (await nft.setManager(managerAddress)).wait();
  await (await manager.setModule(await manager.TREASURY(), treasuryAddress)).wait();
  await (await manager.setModule(await manager.REWARD_POOL(), rewardPoolAddress)).wait();
  await (await manager.setModule(await manager.LIQUIDITY_POOL(), liquidityPoolAddress)).wait();
  await (await manager.setModule(await manager.NFT_INCOME(), nftIncomeAddress)).wait();
  await (await manager.setModule(await manager.REFERRAL_INCOME(), referralIncomeAddress)).wait();
  await (await rewardPool.setSpender(nftIncomeAddress, true)).wait();
  await (await rewardPool.setSpender(referralIncomeAddress, true)).wait();
  await (await manager.setModule(await manager.TEAM_BUILDING_INCOME(), teamBuildingIncomeAddress)).wait();
  await (await rewardPool.setSpender(teamBuildingIncomeAddress, true)).wait();
  await (await manager.setModule(await manager.TEAM_BUSINESS(), teamBusinessAddress)).wait();
  await (await rewardPool.setSpender(bonusBoosterAddress, true)).wait();
  await (await rewardPool.setSpender(teamPerformanceBonusAddress, true)).wait();
  await (await rewardPool.setSpender(monthlyLoyaltyBonusAddress, true)).wait();
  await (await rewardPool.setSpender(royaltyBonusAddress, true)).wait();
  await (await manager.setModule(await manager.TEAM_DEVELOPMENT(), teamDevelopmentBonusAddress)).wait();
  await (await rewardPool.setSpender(teamDevelopmentBonusAddress, true)).wait();

  await (await manager.setModule(await manager.PROFIT_SHARING_SLOT(), profitSharingSlotAddress)).wait();

  // Bonuses that earn the Team Development extra (configurable later).
  for (const eligible of [teamBuildingIncomeAddress, bonusBoosterAddress, teamPerformanceBonusAddress]) {
    await (await teamDevelopmentBonus.setEligibleSource(eligible, true)).wait();
  }

  console.log("Wiring complete:");
  console.log("  NFT manager:", await nft.manager());
  console.log("  NFT treasury:", await nft.treasury());
  console.log("  Manager TREASURY:", await manager.modules(await manager.TREASURY()));
  console.log("  Manager REWARD_POOL:", await manager.modules(await manager.REWARD_POOL()));
  console.log("  Manager LIQUIDITY_POOL:", await manager.modules(await manager.LIQUIDITY_POOL()));
  console.log("  Manager NFT_INCOME:", await manager.modules(await manager.NFT_INCOME()));
  console.log("  Manager REFERRAL_INCOME:", await manager.modules(await manager.REFERRAL_INCOME()));
  console.log("  ReferralIncome payouts enabled:", await referralIncome.payoutsEnabled());
  console.log("  Manager TEAM_BUILDING_INCOME:", await manager.modules(await manager.TEAM_BUILDING_INCOME()));
  console.log("  TeamBuildingIncome payouts enabled:", await teamBuildingIncome.payoutsEnabled());
  console.log("  Manager TEAM_BUSINESS:", await manager.modules(await manager.TEAM_BUSINESS()));
  console.log("  BonusBooster payouts enabled:", await bonusBooster.payoutsEnabled());
  console.log("  TeamPerformanceBonus payouts enabled:", await teamPerformanceBonus.payoutsEnabled());
  console.log("  MonthlyLoyaltyBonus payouts enabled:", await monthlyLoyaltyBonus.payoutsEnabled());
  console.log("  RoyaltyBonus payouts enabled:", await royaltyBonus.payoutsEnabled());
  console.log("  Manager TEAM_DEVELOPMENT:", await manager.modules(await manager.TEAM_DEVELOPMENT()));
  console.log("  TeamDevelopmentBonus payouts enabled:", await teamDevelopmentBonus.payoutsEnabled());
  console.log("  Manager PROFIT_SHARING_SLOT:", await manager.modules(await manager.PROFIT_SHARING_SLOT()));
  console.log("  RewardPool revenue sources:", await rewardPool.getRevenueSources());
  console.log("  NFTIncome payouts enabled:", await nftIncome.payoutsEnabled());
  console.log(
    "  NFTIncome funding configured:",
    await rewardPool.hasFundingConfigured(nftIncomeAddress)
  );

  // Read-only check that enabling is blocked. This does NOT enable payouts.
  try {
    await nftIncome.setPayoutsEnabled.staticCall(true);
    console.log("  WARNING: payouts could be enabled");
  } catch (error: any) {
    const reason = /Funding not configured/.test(String(error?.message))
      ? "Funding not configured"
      : String(error?.message);

    console.log("  Enabling payouts is blocked:", reason);
  }

  // Addresses for the keeper and the backend.
  const deployment = {
    chainId: chainId.toString(),
    deployedAt: new Date().toISOString(),
    startBlock,
    contracts: {
      paymentToken: paymentTokenAddress,
      treasury: treasuryAddress,
      nft: nftAddress,
      manager: managerAddress,
      rewardPool: rewardPoolAddress,
      liquidityPool: liquidityPoolAddress,
      nftIncome: nftIncomeAddress,
      referralIncome: referralIncomeAddress,
      teamBuildingIncome: teamBuildingIncomeAddress,
      teamBusiness: teamBusinessAddress,
      bonusBooster: bonusBoosterAddress,
      teamPerformanceBonus: teamPerformanceBonusAddress,
      monthlyLoyaltyBonus: monthlyLoyaltyBonusAddress,
      royaltyBonus: royaltyBonusAddress,
      teamDevelopmentBonus: teamDevelopmentBonusAddress,
      profitSharingSlot: profitSharingSlotAddress,
    },
  };

  mkdirSync("deployments", { recursive: true });
  writeFileSync(
    `deployments/${chainId}.json`,
    JSON.stringify(deployment, null, 2) + "\n"
  );
  console.log(`Addresses written to deployments/${chainId}.json`);

  // 8. Test purchase
  const packageAmount = 100;
  const paymentAmount = ethers.parseUnits(packageAmount.toString(), 18);

  await (await token.approve(nftAddress, paymentAmount)).wait();
  await (await nft.buyNFT(packageAmount)).wait();

  const record = await manager.nftRecords(0);

  console.log("Test purchase:");
  console.log("  NFT #0 owner:", await nft.ownerOf(0));
  console.log("  Manager record: buyer", record.buyer, "package", record.packageAmount.toString());
  console.log(
    "  Treasury balance:",
    ethers.formatUnits(await treasury.balance(), 18),
    "tNEX"
  );
  console.log(
    "  RewardPool balance:",
    ethers.formatUnits(await rewardPool.balance(), 18),
    "tNEX"
  );

  const state = await nftIncome.nfts(0);

  console.log("NFTIncome for NFT #0:");
  console.log("  active:", state.active);
  console.log("  daily rate:", ethers.formatUnits(state.dailyRate, 18), "tNEX");
  console.log("  4X cap:", ethers.formatUnits(state.cap, 18), "tNEX");
  console.log("  entitled today:", ethers.formatUnits(await nftIncome.entitledOf(0), 18), "tNEX");
  console.log(
    "  funded budget:",
    ethers.formatUnits(await rewardPool.budgetOf(nftIncomeAddress), 18),
    "tNEX"
  );
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
