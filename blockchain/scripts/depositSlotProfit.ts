import hre from "hardhat";
import { readFileSync } from "node:fs";

async function main() {
  const { ethers } = await hre.network.connect();
  const { chainId } = await ethers.provider.getNetwork();
  const [deployer] = await ethers.getSigners();

  console.log(`[depositSlotProfit] Chain ${chainId}, Deployer: ${deployer.address}`);

  const deployment = JSON.parse(readFileSync(`deployments/${chainId}.json`, "utf8"));
  const addr = deployment.contracts;

  const token = await ethers.getContractAt("TestNexadonToken", addr.paymentToken, deployer);
  const slot = await ethers.getContractAt("ProfitSharingSlot", addr.profitSharingSlot, deployer);
  const slotAddress = await slot.getAddress();

  // 1. Authorize deployer as profit depositor
  if (!(await slot.isProfitDepositor(deployer.address))) {
    console.log("Authorizing deployer as profit depositor...");
    const tx = await slot.setProfitDepositor(deployer.address, true);
    await tx.wait();
  }

  // 2. Approve tokens
  const profitAmount = ethers.parseUnits("500", 18);
  const allowance = await token.allowance(deployer.address, slotAddress);
  if (allowance < profitAmount) {
    console.log("Approving ProfitSharingSlot for NXD tokens...");
    const tx = await token.approve(slotAddress, ethers.MaxUint256);
    await tx.wait();
  }

  // 3. Deposit profit
  console.log("Depositing 500 NXD profit into ProfitSharingSlot...");
  const depTx = await slot.depositProfit(profitAmount);
  const receipt = await depTx.wait();
  console.log(`Deposit tx confirmed: ${depTx.hash} (gas: ${receipt.gasUsed})`);

  // 4. Verify Slot #0
  const s = await slot.getSlot(0);
  const preview = await slot.previewWithdraw(0);

  console.log("Slot #0 Updated State:");
  console.log(`  Holder: ${s.holder}`);
  console.log(`  Profit assigned: ${ethers.formatUnits(s.profit, 18)} NXD`);
  console.log(`  Total (Principal + Profit): ${ethers.formatUnits(preview[0], 18)} NXD`);
  console.log(`  Deduction bps: ${preview[1].toString()} (${Number(preview[1]) / 100}%)`);
  console.log(`  Withdrawal payout: ${ethers.formatUnits(preview[2], 18)} NXD`);
}

main().catch((err) => {
  console.error("Error depositing slot profit:", err);
  process.exitCode = 1;
});

