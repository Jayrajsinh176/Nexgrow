import hre from "hardhat";

async function main() {
  const { ethers } = await hre.network.connect();

  const [deployer, treasurySigner] = await ethers.getSigners();

  console.log("User:", deployer.address);

  const paymentTokenAddress =
    "0x5FbDB2315678afecb367f032d93F642f64180aa3";

  const treasury = treasurySigner.address;

  // Get test token
  const token = await ethers.getContractAt(
    "TestNexadonToken",
    paymentTokenAddress
  );

  // Deploy NFT
  const NexgrowNFT = await ethers.getContractFactory(
    "NexgrowNFT"
  );

  const nft = await NexgrowNFT.deploy(
    paymentTokenAddress,
    treasury
  );

  await nft.waitForDeployment();

  const nftAddress = await nft.getAddress();

  console.log("NexgrowNFT:", nftAddress);

  // Deploy Manager (needs the NFT address)
  const manager = await ethers.deployContract(
    "NexgrowManager",
    [nftAddress]
  );

  await manager.waitForDeployment();

  const managerAddress = await manager.getAddress();

  console.log("NexgrowManager:", managerAddress);

  // Link NFT -> Manager so purchases get registered
  const setManagerTx = await nft.setManager(managerAddress);

  await setManagerTx.wait();

  console.log("NFT manager set to:", await nft.manager());

  const packageAmount = 500;

  // Convert 500 tNEX to blockchain units
  const paymentAmount = ethers.parseUnits(
    packageAmount.toString(),
    18
  );

  console.log(
    "User balance before:",
    ethers.formatUnits(
      await token.balanceOf(deployer.address),
      18
    ),
    "tNEX"
  );

  console.log(
    "Treasury balance before:",
    ethers.formatUnits(
      await token.balanceOf(treasury),
      18
    ),
    "tNEX"
  );

  // STEP 1: Approve NFT contract
  const approveTx = await token.approve(
    nftAddress,
    paymentAmount
  );

  await approveTx.wait();

  console.log("500 tNEX approved to NFT contract.");

  // Check allowance
  const allowance = await token.allowance(
    deployer.address,
    nftAddress
  );

  console.log(
    "NFT contract allowance:",
    ethers.formatUnits(allowance, 18),
    "tNEX"
  );

  // STEP 2: Buy NFT
  const buyTx = await nft.buyNFT(packageAmount);

  await buyTx.wait();

  console.log("NFT purchased successfully.");

  // Check balances
  console.log(
    "User balance after:",
    ethers.formatUnits(
      await token.balanceOf(deployer.address),
      18
    ),
    "tNEX"
  );

  console.log(
    "Treasury balance after:",
    ethers.formatUnits(
      await token.balanceOf(treasury),
      18
    ),
    "tNEX"
  );

  // Check NFT ownership
  const owner = await nft.ownerOf(0);

  console.log(
    "NFT #0 owner:",
    owner
  );

  // Check package
  const storedPackage = await nft.nftPackage(0);

  console.log(
    "NFT #0 package:",
    storedPackage.toString()
  );

  // Check the record registered in the manager
  const record = await manager.nftRecords(0);

  console.log("Manager record for NFT #0:");
  console.log("  buyer:", record.buyer);
  console.log("  package:", record.packageAmount.toString());
  console.log(
    "  registeredAt:",
    new Date(Number(record.registeredAt) * 1000).toISOString()
  );
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});