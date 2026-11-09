import hre from "hardhat";
import { readFileSync } from "node:fs";
import { ALL_TASKS, runKeeper } from "./lib/keeper.js";

// Nexgrow keeper: does the routine on-chain work the income modules rely on.
// Every task is safe to run repeatedly: it reads the current state first and
// only sends transactions for work that is still due.
//
// Run:  npx hardhat run scripts/keeper.ts --network <network>
//
// Environment:
//   KEEPER_TASKS  comma-separated tasks (default: all except "claims")
//                 queues, development, performance, booster, monthly, claims
//   KEEPER_BATCH  queue steps / accounts per transaction (default 100)
//
// Addresses come from deployments/<chainId>.json (written by deployNexgrow.ts).
// Wallets to process are found from the contracts' own events.
// The keeper never starts payment rounds: startRound is owner-only and stays a
// deliberate owner action. The "claims" task claims for everyone in a round
// the owner has already started.

async function main() {
  const { ethers } = await hre.network.connect();
  const { chainId } = await ethers.provider.getNetwork();

  const deployment = JSON.parse(
    readFileSync(`deployments/${chainId}.json`, "utf8")
  );

  const tasks = (process.env.KEEPER_TASKS ?? ALL_TASKS.join(","))
    .split(",")
    .map((t) => t.trim())
    .filter(Boolean);

  console.log(`[keeper] chain ${chainId}`);

  await runKeeper({
    ethers,
    deployment,
    tasks,
    batch: Number(process.env.KEEPER_BATCH ?? 100),
  });
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
