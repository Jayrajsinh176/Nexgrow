import hre from "hardhat";

async function main() {
  const { ethers } = await hre.network.connect();

  const [deployer] = await ethers.getSigners();

  console.log("Deploying with account:", deployer.address);

  const TestNexadonToken = await ethers.getContractFactory(
    "TestNexadonToken"
  );

  const token = await TestNexadonToken.deploy();

  await token.waitForDeployment();

  const tokenAddress = await token.getAddress();

  console.log("TestNexadonToken deployed to:", tokenAddress);
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});