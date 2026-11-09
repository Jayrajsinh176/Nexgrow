import hre from "hardhat";

// Measures gas for the operations users and the keeper call most, on a fresh
// simulated network with the whole system wired as in deployNexgrow.ts.
// Run: npx hardhat run scripts/gasReport.ts
const DAY = 24 * 60 * 60;
const TEST_REVENUE = "0x" + "11".repeat(32);

async function main() {
  const { ethers, networkHelpers } = await hre.network.connect();
  const tnex = (v: number) => ethers.parseUnits(v.toString(), 18);
  const [owner, funder] = await ethers.getSigners();

  const deploy = (name: string, args: unknown[] = []) =>
    ethers.deployContract(name, args) as Promise<any>;

  const token = await deploy("TestNexadonToken");
  const t = await token.getAddress();
  const treasury = await deploy("NexgrowTreasury", [t]);
  const nft = await deploy("NexgrowNFT", [t, await treasury.getAddress()]);
  const n = await nft.getAddress();
  const manager = await deploy("NexgrowManager", [n]);
  const m = await manager.getAddress();
  const rewardPool = await deploy("RewardPool", [t]);
  const p = await rewardPool.getAddress();
  const nftIncome = await deploy("NFTIncome", [m, n, p]);
  const referral = await deploy("ReferralIncome", [m, n, p]);
  const r = await referral.getAddress();
  const teamBuilding = await deploy("TeamBuildingIncome", [m, n, p, r]);
  const teamBusiness = await deploy("TeamBusiness", [m, r]);
  const tb = await teamBusiness.getAddress();
  const booster = await deploy("BonusBooster", [m, n, p, tb]);
  const development = await deploy("TeamDevelopmentBonus", [m, n, p, r, tb]);
  const slot = await deploy("ProfitSharingSlot", [t, await treasury.getAddress()]);

  await nft.setManager(m);
  for (const [key, c] of [
    ["REWARD_POOL", rewardPool],
    ["NFT_INCOME", nftIncome],
    ["REFERRAL_INCOME", referral],
    ["TEAM_BUILDING_INCOME", teamBuilding],
    ["TEAM_BUSINESS", teamBusiness],
    ["TEAM_DEVELOPMENT", development],
  ] as [string, any][]) {
    await manager.setModule(await manager[key](), await c.getAddress());
  }
  for (const c of [teamBuilding, booster]) {
    await development.setEligibleSource(await c.getAddress(), true);
  }

  await rewardPool.addRevenueSource(TEST_REVENUE, "Gas report source");
  await rewardPool.setDepositor(funder.address, TEST_REVENUE, true);
  for (const [c, bps] of [[nftIncome, 5000n], [referral, 2500n], [teamBuilding, 2500n]] as [any, bigint][]) {
    await rewardPool.setSpender(await c.getAddress(), true);
    await rewardPool.setAllocation(await c.getAddress(), TEST_REVENUE, bps);
  }
  await token.transfer(funder.address, tnex(200_000));

  const rows: [string, bigint][] = [];
  const gas = async (label: string, tx: Promise<any>) => {
    const receipt = await (await tx).wait();
    rows.push([label, receipt.gasUsed]);
    return receipt.gasUsed;
  };

  async function wallet() {
    const w = ethers.Wallet.createRandom().connect(ethers.provider);
    await owner.sendTransaction({ to: w.address, value: ethers.parseEther("1") });
    await token.transfer(w.address, tnex(3_100));
    await token.connect(w).approve(n, tnex(3_100));
    return w;
  }

  // ---- A 60-level chain, each wallet with a 50 NFT ----
  const LEVELS = 60;
  const chain: any[] = [];
  for (let i = 0; i < LEVELS; i++) {
    const w = await wallet();
    if (i > 0) await referral.connect(w).setSponsor(chain[i - 1].address);
    if (i === 0) {
      await gas("buyNFT (first purchase, all modules connected)", nft.connect(w).buyNFT(50));
    } else if (i === LEVELS - 1) {
      await gas(`buyNFT (wallet ${LEVELS - 1} levels deep)`, nft.connect(w).buyNFT(50));
    } else {
      await nft.connect(w).buyNFT(50);
    }
    chain.push(w);
  }

  await gas(
    `TeamBusiness.processQueue: 1 purchase, ${LEVELS} wallets`,
    teamBusiness.processQueue(LEVELS),
  );
  await teamBusiness.processQueue(100_000);

  // ---- Transfer hook ----
  const outsider = await wallet();
  await gas("NFT transfer (active-NFT tracking hook)", nft.connect(chain[0]).transferFrom(chain[0].address, outsider.address, 0));

  // ---- Daily bonus round and claims ----
  await token.connect(funder).approve(p, tnex(100_000));
  await rewardPool.connect(funder).deposit(tnex(100_000), TEST_REVENUE);
  await nftIncome.setPayoutsEnabled(true);
  await networkHelpers.time.increase(10 * DAY);

  await gas("NFTIncome.startRound", nftIncome.startRound());
  await gas("NFTIncome.claim: 1 NFT (+2 upline jobs)", nftIncome.claim([LEVELS - 1]));
  await gas(
    "NFTIncome.claim: 20 NFTs",
    nftIncome.claim(Array.from({ length: 20 }, (_, i) => i + 1)),
  );

  // ---- Upline queues ----
  await gas(
    `ReferralIncome.processQueue: ${LEVELS - 1} levels (1 payout)`,
    referral.processQueue(LEVELS - 1),
  );
  await referral.processQueue(100_000);
  await gas(
    "TeamBuildingIncome.processQueue: 20 levels (1 payout)",
    teamBuilding.processQueue(20),
  );
  await teamBuilding.processQueue(100_000);

  // ---- Payment round ----
  await referral.setPayoutsEnabled(true);
  await gas("ReferralIncome.startRound", referral.startRound());
  await gas(
    "ReferralIncome.claim: 20 accounts",
    referral.claim(chain.slice(0, 20).map((w) => w.address)),
  );

  // ---- Profit-Sharing Slot with all 100 slots sold ----
  for (let i = 0; i < 100; i++) {
    const w = await wallet();
    await token.connect(w).approve(await slot.getAddress(), tnex(3_000));
    if (i === 0) {
      await gas("ProfitSharingSlot.buySlot", slot.connect(w).buySlot());
    } else {
      await slot.connect(w).buySlot();
    }
  }
  await slot.setProfitDepositor(funder.address, true);
  await token.connect(funder).approve(await slot.getAddress(), tnex(10_000));
  await gas("ProfitSharingSlot.depositProfit: 100 slots", slot.connect(funder).depositProfit(tnex(10_000)));

  // ---- Report ----
  const width = Math.max(...rows.map(([l]) => l.length));
  console.log("\nGas used".padEnd(width + 1), "     gas");
  for (const [label, used] of rows) {
    console.log(label.padEnd(width), used.toString().padStart(10));
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
