import hre from "hardhat";

async function main() {
  const { ethers } = await hre.network.connect();

  const tokenAddress = "0x5FbDB2315678afecb367f032d93F642f64180aa3";

  const token = await ethers.getContractAt(
    "TestNexadonToken",
    tokenAddress
  );

  const [owner, spender] = await ethers.getSigners();

  const amount = ethers.parseUnits("100", 18);

  console.log("Owner:", owner.address);
  console.log("Spender:", spender.address);

  console.log(
    "Owner balance before:",
    ethers.formatUnits(
      await token.balanceOf(owner.address),
      18
    ),
    "tNEX"
  );

  console.log(
    "Spender balance before:",
    ethers.formatUnits(
      await token.balanceOf(spender.address),
      18
    ),
    "tNEX"
  );

  // STEP 1: Owner approves spender
  const approveTx = await token.approve(
    spender.address,
    amount
  );

  await approveTx.wait();

  console.log("Approval confirmed.");

  console.log(
    "Allowance:",
    ethers.formatUnits(
      await token.allowance(owner.address, spender.address),
      18
    ),
    "tNEX"
  );

  // STEP 2: Spender transfers tokens from owner
  const spenderToken = token.connect(spender);

  const transferTx = await spenderToken.transferFrom(
    owner.address,
    spender.address,
    amount
  );

  await transferTx.wait();

  console.log("transferFrom confirmed.");

  console.log(
    "Owner balance after:",
    ethers.formatUnits(
      await token.balanceOf(owner.address),
      18
    ),
    "tNEX"
  );

  console.log(
    "Spender balance after:",
    ethers.formatUnits(
      await token.balanceOf(spender.address),
      18
    ),
    "tNEX"
  );

  console.log(
    "Remaining allowance:",
    ethers.formatUnits(
      await token.allowance(owner.address, spender.address),
      18
    ),
    "tNEX"
  );
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});