import hre from "hardhat";
import { readFileSync } from "node:fs";

async function main() {
  const { ethers } = await hre.network.connect();
  const [deployer] = await ethers.getSigners();
  const { chainId } = await ethers.provider.getNetwork();

  // Load deployed payment token address
  const deployment = JSON.parse(readFileSync(`deployments/${chainId}.json`, "utf8"));
  const tokenAddress = deployment.contracts.paymentToken;

  const targetAddress = process.env.TO ?? "0x70997970C51812dc3A010C7d01b50e0d17dc79C8";
  const amountNumber = process.env.AMOUNT ?? "5000";

  const token = await ethers.getContractAt("TestNexadonToken", tokenAddress);
  const amount = ethers.parseUnits(amountNumber, 18);

  console.log(`Funding ${targetAddress} with ${amountNumber} tNEX...`);
  const tx = await token.transfer(targetAddress, amount);
  await tx.wait();

  const balance = await token.balanceOf(targetAddress);
  console.log(`Success! New balance of ${targetAddress}: ${ethers.formatUnits(balance, 18)} tNEX`);

  // Also send 10 ETH for gas if balance is low
  const ethBalance = await ethers.provider.getBalance(targetAddress);
  if (ethBalance < ethers.parseEther("1")) {
    const ethTx = await deployer.sendTransaction({
      to: targetAddress,
      value: ethers.parseEther("10"),
    });
    await ethTx.wait();
    console.log(`Sent 10 ETH for gas to ${targetAddress}`);
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});

