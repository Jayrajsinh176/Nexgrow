import hre from "hardhat";
import { mkdirSync, writeFileSync } from "node:fs";

// Writes the ABIs the Laravel frontend uses to
// ../laravel/resources/js/nexgrow/abis.json. Run after contract changes:
//   npx hardhat build && npx hardhat run scripts/exportAbis.ts
const CONTRACTS: Record<string, string> = {
  token: "TestNexadonToken", // standard ERC-20 functions only are used
  nft: "NexgrowNFT",
  manager: "NexgrowManager",
  treasury: "NexgrowTreasury",
  rewardPool: "RewardPool",
  liquidityPool: "LiquidityPool",
  nftIncome: "NFTIncome",
  referralIncome: "ReferralIncome",
  teamBuildingIncome: "TeamBuildingIncome",
  teamBusiness: "TeamBusiness",
  bonusBooster: "BonusBooster",
  teamPerformanceBonus: "TeamPerformanceBonus",
  monthlyLoyaltyBonus: "MonthlyLoyaltyBonus",
  royaltyBonus: "RoyaltyBonus",
  teamDevelopmentBonus: "TeamDevelopmentBonus",
  profitSharingSlot: "ProfitSharingSlot",
};

async function main() {
  const abis: Record<string, unknown> = {};

  for (const [key, name] of Object.entries(CONTRACTS)) {
    abis[key] = (await hre.artifacts.readArtifact(name)).abi;
  }

  const dir = "../laravel/resources/js/nexgrow";
  mkdirSync(dir, { recursive: true });
  writeFileSync(`${dir}/abis.json`, JSON.stringify(abis) + "\n");

  console.log(`Wrote ${Object.keys(abis).length} ABIs to ${dir}/abis.json`);
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
