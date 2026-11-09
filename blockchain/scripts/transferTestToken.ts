import hre from "hardhat";

async function main() {
  const { ethers } = await hre.network.connect();

  const tokenAddress = "0x5FbDB2315678afecb367f032d93F642f64180aa3";

  const token = await ethers.getContractAt(
    "TestNexadonToken",
    tokenAddress
  );

  const [deployer] = await ethers.getSigners();

  // Create a second wallet
  const receiver = ethers.Wallet.createRandom();

  console.log("Sender:", deployer.address);
  console.log("Receiver:", receiver.address);

  console.log(
    "Sender balance before:",
    ethers.formatUnits(
      await token.balanceOf(deployer.address),
      18
    ),
    "tNEX"
  );

  console.log(
    "Receiver balance before:",
    ethers.formatUnits(
      await token.balanceOf(receiver.address),
      18
    ),
    "tNEX"
  );

  // Transfer 100 tNEX
  const amount = ethers.parseUnits("100", 18);

  const tx = await token.transfer(receiver.address, amount);

  console.log("Transaction hash:", tx.hash);

  await tx.wait();

  console.log("Transfer confirmed.");

  console.log(
    "Sender balance after:",
    ethers.formatUnits(
      await token.balanceOf(deployer.address),
      18
    ),
    "tNEX"
  );

  console.log(
    "Receiver balance after:",
    ethers.formatUnits(
      await token.balanceOf(receiver.address),
      18
    ),
    "tNEX"
  );
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});