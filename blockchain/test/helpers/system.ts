import { ethers as ethersLib } from "ethers";

// Test-only revenue source. Real sources are not defined yet.
export const TEST_REVENUE = ethersLib.id("TEST_REVENUE");

const tnex = (v: string | number) => ethersLib.parseUnits(v.toString(), 18);

// The whole system wired as in scripts/deployNexgrow.ts, with a test revenue
// source allocated to every income module. Starts one hour into the first
// day of the next calendar month, so the first booster period and the
// calendar month start on the same day.
export async function deploySystem(ethers: any, networkHelpers: any) {
  const startBlock = await ethers.provider.getBlockNumber();

  const now = new Date((await networkHelpers.time.latest()) * 1000);
  const nextMonth = Date.UTC(now.getUTCFullYear(), now.getUTCMonth() + 1, 1);
  await networkHelpers.time.increaseTo(nextMonth / 1000 + 3600);

  const [owner, funder, root, A, B, C, D, E, a1, b1, c1] = await ethers.getSigners();

  const deploy = (name: string, args: unknown[] = []) =>
    ethers.deployContract(name, args) as Promise<any>;

  const token = await deploy("TestNexadonToken");
  const t = await token.getAddress();
  const treasury = await deploy("NexgrowTreasury", [t]);
  const nft = await deploy("NexgrowNFT", [t, await treasury.getAddress()]);
  const manager = await deploy("NexgrowManager", [await nft.getAddress()]);
  const m = await manager.getAddress();
  const n = await nft.getAddress();
  const rewardPool = await deploy("RewardPool", [t]);
  const p = await rewardPool.getAddress();
  const nftIncome = await deploy("NFTIncome", [m, n, p]);
  const referral = await deploy("ReferralIncome", [m, n, p]);
  const r = await referral.getAddress();
  const teamBuilding = await deploy("TeamBuildingIncome", [m, n, p, r]);
  const teamBusiness = await deploy("TeamBusiness", [m, r]);
  const tb = await teamBusiness.getAddress();
  const booster = await deploy("BonusBooster", [m, n, p, tb]);
  const performance = await deploy("TeamPerformanceBonus", [m, n, p, r, tb]);
  const loyalty = await deploy("MonthlyLoyaltyBonus", [m, n, p, tb]);
  const royalty = await deploy("RoyaltyBonus", [m, n, p, tb]);
  const development = await deploy("TeamDevelopmentBonus", [m, n, p, r, tb]);
  const slot = await deploy("ProfitSharingSlot", [t, await treasury.getAddress()]);

  await nft.setManager(m);
  const keys: [string, any][] = [
    ["TREASURY", treasury],
    ["REWARD_POOL", rewardPool],
    ["NFT_INCOME", nftIncome],
    ["REFERRAL_INCOME", referral],
    ["TEAM_BUILDING_INCOME", teamBuilding],
    ["TEAM_BUSINESS", teamBusiness],
    ["TEAM_DEVELOPMENT", development],
    ["PROFIT_SHARING_SLOT", slot],
  ];
  for (const [key, c] of keys) {
    await manager.setModule(await manager[key](), await c.getAddress());
  }

  // Income modules and their test allocation of the revenue source (bps).
  const modules: [any, bigint][] = [
    [nftIncome, 3000n],
    [referral, 1500n],
    [teamBuilding, 1000n],
    [booster, 1500n],
    [performance, 500n],
    [loyalty, 500n],
    [royalty, 500n],
    [development, 500n],
  ];

  await rewardPool.addRevenueSource(TEST_REVENUE, "Test-only revenue source");
  await rewardPool.setDepositor(funder.address, TEST_REVENUE, true);
  for (const [c, bps] of modules) {
    await rewardPool.setSpender(await c.getAddress(), true);
    await rewardPool.setAllocation(await c.getAddress(), TEST_REVENUE, bps);
  }
  for (const c of [teamBuilding, booster, performance]) {
    await development.setEligibleSource(await c.getAddress(), true);
  }

  await token.transfer(funder.address, tnex(300_000));
  for (const u of [root, A, B, C, D, E, a1, b1, c1]) {
    await token.transfer(u.address, tnex(20_000));
  }

  async function buy(user: any, pkg: number) {
    await token.connect(user).approve(n, tnex(pkg));
    await nft.connect(user).buyNFT(pkg);
  }

  async function to(timestamp: bigint | number) {
    await networkHelpers.time.increaseTo(Number(timestamp));
  }

  const deployment = {
    startBlock,
    contracts: {
      nft: n,
      manager: m,
      rewardPool: p,
      nftIncome: await nftIncome.getAddress(),
      referralIncome: r,
      teamBuildingIncome: await teamBuilding.getAddress(),
      teamBusiness: tb,
      bonusBooster: await booster.getAddress(),
      teamPerformanceBonus: await performance.getAddress(),
      monthlyLoyaltyBonus: await loyalty.getAddress(),
      royaltyBonus: await royalty.getAddress(),
      teamDevelopmentBonus: await development.getAddress(),
      profitSharingSlot: await slot.getAddress(),
    },
  };

  return {
    deployment,
    owner, funder, root, A, B, C, D, E, a1, b1, c1,
    token, treasury, nft, manager, rewardPool, nftIncome, referral, teamBuilding,
    teamBusiness, booster, performance, loyalty, royalty, development, slot,
    modules, buy, to,
  };
}
