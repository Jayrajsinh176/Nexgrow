import hre from "hardhat";

async function main() {
  const { ethers } = await hre.network.connect();

  const tokenAddress = "0x5FbDB2315678afecb367f032d93F642f64180aa3";

  const token = await ethers.getContractAt(
    "TestNexadonToken",
    tokenAddress
  );

  const [deployer] = await ethers.getSigners();

  console.log("Token Address:", tokenAddress);

  console.log("Name:", await token.name());

  console.log("Symbol:", await token.symbol());

  console.log("Decimals:", await token.decimals());

  console.log(
    "Total Supply:",
    ethers.formatUnits(await token.totalSupply(), 18),
    "tNEX"
  );

  console.log(
    "Deployer Balance:",
    ethers.formatUnits(
      await token.balanceOf(deployer.address),
      18
    ),
    "tNEX"
  );
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});