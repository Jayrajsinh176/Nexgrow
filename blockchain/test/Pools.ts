import { expect } from "chai";
import { network } from "hardhat";
import type { RewardPool } from "../types/ethers-contracts/index.js";

const { ethers } = await network.create();

const SOURCE_A = ethers.id("TEST_SOURCE_A");
const SOURCE_B = ethers.id("TEST_SOURCE_B");

// RewardPool and LiquidityPool share BasePool, so run the same tests on both.
for (const poolName of ["RewardPool", "LiquidityPool"]) {
  describe(poolName, function () {
    async function deployFixture() {
      const [owner, funder, module, user, other] = await ethers.getSigners();

      const token = await ethers.deployContract("TestNexadonToken");

      // Both pools have the same BasePool ABI, so one type covers them.
      const pool = (await ethers.deployContract(poolName, [
        await token.getAddress(),
      ])) as unknown as RewardPool;

      // Two sources; funder is authorized only for SOURCE_A.
      await pool.addRevenueSource(SOURCE_A, "Test source");
      await pool.addRevenueSource(SOURCE_B, "Test source");
      await pool.setDepositor(funder.address, SOURCE_A, true);

      await token.transfer(funder.address, ethers.parseUnits("10000", 18));
      await token.transfer(other.address, ethers.parseUnits("10000", 18));

      return { owner, funder, module, user, other, token, pool };
    }

    async function fund(
      token: any,
      pool: RewardPool,
      funder: any,
      amount: bigint,
      source: string,
    ) {
      await token.connect(funder).approve(await pool.getAddress(), amount);

      return pool.connect(funder).deposit(amount, source);
    }

    it("Should set owner and token", async function () {
      const { owner, token, pool } = await deployFixture();

      expect(await pool.owner()).to.equal(owner.address);
      expect(await pool.token()).to.equal(await token.getAddress());
    });

    it("Should reject the zero token address", async function () {
      await expect(
        ethers.deployContract(poolName, [ethers.ZeroAddress]),
      ).to.be.revertedWith("Invalid token address");
    });

    describe("Revenue sources", function () {
      it("Should register sources and list them", async function () {
        const { pool } = await deployFixture();

        expect(await pool.isRevenueSource(SOURCE_A)).to.equal(true);
        expect(await pool.getRevenueSources()).to.deep.equal([
          SOURCE_A,
          SOURCE_B,
        ]);
      });

      it("Should emit RevenueSourceAdded", async function () {
        const { pool } = await deployFixture();

        const SOURCE_C = ethers.id("TEST_SOURCE_C");

        const description = "Test revenue, see docs/revenue/test-source.md";

        await expect(pool.addRevenueSource(SOURCE_C, description))
          .to.emit(pool, "RevenueSourceAdded")
          .withArgs(SOURCE_C, description);

        expect(await pool.sourceDescription(SOURCE_C)).to.equal(description);
      });

      it("Should reject duplicate, empty and undocumented sources", async function () {
        const { pool } = await deployFixture();

        await expect(
          pool.addRevenueSource(SOURCE_A, "Test source"),
        ).to.be.revertedWith("Source already added");

        await expect(
          pool.addRevenueSource(ethers.ZeroHash, "Test source"),
        ).to.be.revertedWith("Invalid source");

        await expect(
          pool.addRevenueSource(ethers.id("NEW"), ""),
        ).to.be.revertedWith("Description required");
      });

      it("Should block non-owners from adding sources", async function () {
        const { pool, other } = await deployFixture();

        await expect(pool.connect(other).addRevenueSource(ethers.id("X"), "Test source"))
          .to.be.revertedWithCustomError(pool, "OwnableUnauthorizedAccount")
          .withArgs(other.address);
      });
    });

    describe("Depositors", function () {
      it("Should authorize a depositor for one source and emit DepositorUpdated", async function () {
        const { pool, other } = await deployFixture();

        await expect(pool.setDepositor(other.address, SOURCE_B, true))
          .to.emit(pool, "DepositorUpdated")
          .withArgs(other.address, SOURCE_B, true);

        expect(await pool.isDepositor(other.address, SOURCE_B)).to.equal(true);
        expect(await pool.isDepositor(other.address, SOURCE_A)).to.equal(false);
      });

      it("Should reject unknown sources and the zero address", async function () {
        const { pool, other } = await deployFixture();

        await expect(
          pool.setDepositor(other.address, ethers.id("UNKNOWN"), true),
        ).to.be.revertedWith("Unknown source");

        await expect(
          pool.setDepositor(ethers.ZeroAddress, SOURCE_A, true),
        ).to.be.revertedWith("Invalid depositor");
      });

      it("Should block non-owners from setting depositors", async function () {
        const { pool, other } = await deployFixture();

        await expect(
          pool.connect(other).setDepositor(other.address, SOURCE_A, true),
        )
          .to.be.revertedWithCustomError(pool, "OwnableUnauthorizedAccount")
          .withArgs(other.address);
      });
    });

    describe("Deposits", function () {
      it("Should accept an authorized deposit and track it by source", async function () {
        const { token, pool, funder } = await deployFixture();

        const amount = ethers.parseUnits("300", 18);

        await expect(fund(token, pool, funder, amount, SOURCE_A))
          .to.emit(pool, "Deposited")
          .withArgs(funder.address, SOURCE_A, amount);

        expect(await pool.balance()).to.equal(amount);
        expect(await pool.totalDeposited()).to.equal(amount);
        expect(await pool.availableRevenue()).to.equal(amount);
        expect(await pool.depositedBySource(SOURCE_A)).to.equal(amount);
        expect(await pool.depositedBySource(SOURCE_B)).to.equal(0n);
      });

      it("Should keep separate totals per source", async function () {
        const { token, pool, funder, other } = await deployFixture();

        await pool.setDepositor(other.address, SOURCE_B, true);

        await fund(token, pool, funder, ethers.parseUnits("100", 18), SOURCE_A);
        await fund(token, pool, other, ethers.parseUnits("40", 18), SOURCE_B);
        await fund(token, pool, funder, ethers.parseUnits("60", 18), SOURCE_A);

        expect(await pool.depositedBySource(SOURCE_A)).to.equal(
          ethers.parseUnits("160", 18),
        );
        expect(await pool.depositedBySource(SOURCE_B)).to.equal(
          ethers.parseUnits("40", 18),
        );
        expect(await pool.totalDeposited()).to.equal(ethers.parseUnits("200", 18));
      });

      it("Should reject deposits from unauthorized addresses", async function () {
        const { token, pool, other } = await deployFixture();

        await expect(
          fund(token, pool, other, 1n, SOURCE_A),
        ).to.be.revertedWith("Not authorized for source");
      });

      it("Should reject a depositor using a source it is not authorized for", async function () {
        const { token, pool, funder } = await deployFixture();

        await expect(
          fund(token, pool, funder, 1n, SOURCE_B),
        ).to.be.revertedWith("Not authorized for source");
      });

      it("Should reject deposits after authorization is removed", async function () {
        const { token, pool, funder } = await deployFixture();

        await pool.setDepositor(funder.address, SOURCE_A, false);

        await expect(
          fund(token, pool, funder, 1n, SOURCE_A),
        ).to.be.revertedWith("Not authorized for source");
      });

      it("Should reject a zero amount and missing approval", async function () {
        const { token, pool, funder } = await deployFixture();

        await expect(
          fund(token, pool, funder, 0n, SOURCE_A),
        ).to.be.revertedWith("Invalid amount");

        await expect(
          pool.connect(funder).deposit(1n, SOURCE_A),
        ).to.be.revertedWithCustomError(token, "ERC20InsufficientAllowance");
      });
    });

    describe("Allocation", function () {
      it("Should start with no allocation", async function () {
        const { pool, module } = await deployFixture();

        expect(await pool.allocationBps(module.address, SOURCE_A)).to.equal(0n);
        expect(await pool.totalAllocationBps(SOURCE_A)).to.equal(0n);
        expect(await pool.getAllocatedModules(SOURCE_A)).to.deep.equal([]);
      });

      it("Should set an allocation and emit AllocationUpdated", async function () {
        const { pool, module } = await deployFixture();

        await expect(pool.setAllocation(module.address, SOURCE_A, 3_000n))
          .to.emit(pool, "AllocationUpdated")
          .withArgs(module.address, SOURCE_A, 3_000n);

        expect(await pool.allocationBps(module.address, SOURCE_A)).to.equal(3_000n);
        expect(await pool.totalAllocationBps(SOURCE_A)).to.equal(3_000n);
        expect(await pool.getAllocatedModules(SOURCE_A)).to.deep.equal([module.address]);
      });

      it("Should split deposits into module budgets by share", async function () {
        const { token, pool, funder, module, user } = await deployFixture();

        await pool.setAllocation(module.address, SOURCE_A, 3_000n); // 30%
        await pool.setAllocation(user.address, SOURCE_A, 2_000n); // 20%

        const tx = fund(token, pool, funder, ethers.parseUnits("1000", 18), SOURCE_A);

        await expect(tx)
          .to.emit(pool, "BudgetAllocated")
          .withArgs(module.address, SOURCE_A, ethers.parseUnits("300", 18));

        expect(await pool.budgetOf(module.address)).to.equal(ethers.parseUnits("300", 18));
        expect(await pool.budgetOf(user.address)).to.equal(ethers.parseUnits("200", 18));

        // The unallocated 50% stays in the pool, assigned to nobody.
        expect(await pool.availableRevenue()).to.equal(ethers.parseUnits("1000", 18));
      });

      it("Should only allocate from the matching source", async function () {
        const { token, pool, funder, other, module } = await deployFixture();

        await pool.setAllocation(module.address, SOURCE_A, 10_000n);
        await pool.setDepositor(other.address, SOURCE_B, true);

        await fund(token, pool, other, ethers.parseUnits("500", 18), SOURCE_B);

        expect(await pool.budgetOf(module.address)).to.equal(0n);

        await fund(token, pool, funder, ethers.parseUnits("100", 18), SOURCE_A);

        expect(await pool.budgetOf(module.address)).to.equal(ethers.parseUnits("100", 18));
      });

      it("Should apply only to deposits made after it is set", async function () {
        const { token, pool, funder, module } = await deployFixture();

        await fund(token, pool, funder, ethers.parseUnits("100", 18), SOURCE_A);
        await pool.setAllocation(module.address, SOURCE_A, 10_000n);

        expect(await pool.budgetOf(module.address)).to.equal(0n);

        await fund(token, pool, funder, ethers.parseUnits("40", 18), SOURCE_A);

        expect(await pool.budgetOf(module.address)).to.equal(ethers.parseUnits("40", 18));
      });

      it("Should stop allocating after it is set back to 0", async function () {
        const { token, pool, funder, module } = await deployFixture();

        await pool.setAllocation(module.address, SOURCE_A, 10_000n);
        await fund(token, pool, funder, ethers.parseUnits("10", 18), SOURCE_A);
        await pool.setAllocation(module.address, SOURCE_A, 0n);
        await fund(token, pool, funder, ethers.parseUnits("10", 18), SOURCE_A);

        expect(await pool.budgetOf(module.address)).to.equal(ethers.parseUnits("10", 18));
        expect(await pool.totalAllocationBps(SOURCE_A)).to.equal(0n);
      });

      it("Should reject allocations above 100% per source", async function () {
        const { pool, module, user } = await deployFixture();

        await pool.setAllocation(module.address, SOURCE_A, 6_000n);

        await expect(
          pool.setAllocation(user.address, SOURCE_A, 4_001n),
        ).to.be.revertedWith("Allocation exceeds 100%");

        // Changing an existing allocation replaces it rather than adding.
        await pool.setAllocation(module.address, SOURCE_A, 10_000n);
        expect(await pool.totalAllocationBps(SOURCE_A)).to.equal(10_000n);
      });

      it("Should reject unknown sources, zero module and non-owners", async function () {
        const { pool, module, other } = await deployFixture();

        await expect(
          pool.setAllocation(module.address, ethers.id("UNKNOWN"), 1n),
        ).to.be.revertedWith("Unknown source");

        await expect(
          pool.setAllocation(ethers.ZeroAddress, SOURCE_A, 1n),
        ).to.be.revertedWith("Invalid module");

        await expect(pool.connect(other).setAllocation(other.address, SOURCE_A, 1n))
          .to.be.revertedWithCustomError(pool, "OwnableUnauthorizedAccount")
          .withArgs(other.address);
      });
    });

    describe("Depositor count", function () {
      it("Should count each authorized depositor once", async function () {
        const { pool, funder, other } = await deployFixture();

        expect(await pool.depositorCount(SOURCE_A)).to.equal(1n);

        // Setting the same value twice does not double count.
        await pool.setDepositor(funder.address, SOURCE_A, true);
        expect(await pool.depositorCount(SOURCE_A)).to.equal(1n);

        await pool.setDepositor(other.address, SOURCE_A, true);
        expect(await pool.depositorCount(SOURCE_A)).to.equal(2n);

        await pool.setDepositor(funder.address, SOURCE_A, false);
        await pool.setDepositor(funder.address, SOURCE_A, false);
        expect(await pool.depositorCount(SOURCE_A)).to.equal(1n);
      });
    });

    describe("hasFundingConfigured", function () {
      it("Should be false until spender, depositor and allocation are all set", async function () {
        const { pool, module, other } = await deployFixture();

        // Nothing for module yet.
        expect(await pool.hasFundingConfigured(module.address)).to.equal(false);

        // Spender only.
        await pool.setSpender(module.address, true);
        expect(await pool.hasFundingConfigured(module.address)).to.equal(false);

        // Allocation on SOURCE_B, which has no depositor.
        await pool.setAllocation(module.address, SOURCE_B, 1_000n);
        expect(await pool.hasFundingConfigured(module.address)).to.equal(false);

        // SOURCE_B gets a depositor: now configured.
        await pool.setDepositor(other.address, SOURCE_B, true);
        expect(await pool.hasFundingConfigured(module.address)).to.equal(true);
      });

      it("Should become false again when any piece is removed", async function () {
        const { pool, module } = await deployFixture();

        // SOURCE_A already has funder as depositor.
        await pool.setSpender(module.address, true);
        await pool.setAllocation(module.address, SOURCE_A, 1_000n);
        expect(await pool.hasFundingConfigured(module.address)).to.equal(true);

        await pool.setAllocation(module.address, SOURCE_A, 0n);
        expect(await pool.hasFundingConfigured(module.address)).to.equal(false);

        await pool.setAllocation(module.address, SOURCE_A, 1_000n);
        await pool.setSpender(module.address, false);
        expect(await pool.hasFundingConfigured(module.address)).to.equal(false);
      });

      it("Should be false when the depositor is removed", async function () {
        const { pool, module, funder } = await deployFixture();

        await pool.setSpender(module.address, true);
        await pool.setAllocation(module.address, SOURCE_A, 1_000n);
        await pool.setDepositor(funder.address, SOURCE_A, false);

        expect(await pool.hasFundingConfigured(module.address)).to.equal(false);
      });
    });

    describe("Spenders and releases", function () {
      it("Should let the owner approve and remove spenders", async function () {
        const { pool, module } = await deployFixture();

        await expect(pool.setSpender(module.address, true))
          .to.emit(pool, "SpenderUpdated")
          .withArgs(module.address, true);
        expect(await pool.isSpender(module.address)).to.equal(true);

        await pool.setSpender(module.address, false);
        expect(await pool.isSpender(module.address)).to.equal(false);
      });

      it("Should block non-owners from setting spenders", async function () {
        const { pool, other } = await deployFixture();

        await expect(pool.connect(other).setSpender(other.address, true))
          .to.be.revertedWithCustomError(pool, "OwnableUnauthorizedAccount")
          .withArgs(other.address);
      });

      it("Should reject the zero address as spender", async function () {
        const { pool } = await deployFixture();

        await expect(
          pool.setSpender(ethers.ZeroAddress, true),
        ).to.be.revertedWith("Invalid spender");
      });

      it("Should let an approved spender release up to its budget", async function () {
        const { token, pool, funder, module, user } = await deployFixture();

        // 100% of SOURCE_A goes to module.
        await pool.setAllocation(module.address, SOURCE_A, 10_000n);
        await fund(token, pool, funder, ethers.parseUnits("500", 18), SOURCE_A);
        await pool.setSpender(module.address, true);

        const amount = ethers.parseUnits("120", 18);

        await expect(pool.connect(module).release(user.address, amount))
          .to.emit(pool, "Released")
          .withArgs(module.address, user.address, amount);

        expect(await token.balanceOf(user.address)).to.equal(amount);
        expect(await pool.totalReleased()).to.equal(amount);
        expect(await pool.budgetOf(module.address)).to.equal(ethers.parseUnits("380", 18));
        expect(await pool.availableRevenue()).to.equal(ethers.parseUnits("380", 18));
        expect(await pool.balance()).to.equal(ethers.parseUnits("380", 18));

        // Revenue history is not reduced by payouts.
        expect(await pool.depositedBySource(SOURCE_A)).to.equal(
          ethers.parseUnits("500", 18),
        );
      });

      it("Should never release more than the module budget", async function () {
        const { token, pool, funder, module, user } = await deployFixture();

        await pool.setAllocation(module.address, SOURCE_A, 10_000n);
        await fund(token, pool, funder, ethers.parseUnits("100", 18), SOURCE_A);
        await pool.setSpender(module.address, true);

        await expect(
          pool.connect(module).release(user.address, ethers.parseUnits("101", 18)),
        ).to.be.revertedWith("Exceeds module budget");

        // Release everything, then even 1 wei more must fail.
        await pool.connect(module).release(user.address, ethers.parseUnits("100", 18));

        await expect(
          pool.connect(module).release(user.address, 1n),
        ).to.be.revertedWith("Exceeds module budget");
      });

      it("Should give a spender with no allocation a zero budget", async function () {
        const { token, pool, funder, module, user } = await deployFixture();

        await fund(token, pool, funder, ethers.parseUnits("100", 18), SOURCE_A);
        await pool.setSpender(module.address, true);

        expect(await pool.availableRevenue()).to.equal(ethers.parseUnits("100", 18));
        expect(await pool.budgetOf(module.address)).to.equal(0n);

        await expect(
          pool.connect(module).release(user.address, 1n),
        ).to.be.revertedWith("Exceeds module budget");
      });

      it("Should not count tokens sent directly to the pool as revenue", async function () {
        const { token, pool, module, user, other } = await deployFixture();

        await pool.setAllocation(module.address, SOURCE_A, 10_000n);
        await token
          .connect(other)
          .transfer(await pool.getAddress(), ethers.parseUnits("1000", 18));
        await pool.setSpender(module.address, true);

        expect(await pool.balance()).to.equal(ethers.parseUnits("1000", 18));
        expect(await pool.availableRevenue()).to.equal(0n);
        expect(await pool.budgetOf(module.address)).to.equal(0n);

        await expect(
          pool.connect(module).release(user.address, 1n),
        ).to.be.revertedWith("Exceeds module budget");
      });

      it("Should block release from non-spenders, including the owner", async function () {
        const { token, pool, funder, owner, other } = await deployFixture();

        await fund(token, pool, funder, ethers.parseUnits("100", 18), SOURCE_A);

        await expect(
          pool.connect(other).release(other.address, 1n),
        ).to.be.revertedWith("Not an approved spender");

        await expect(
          pool.connect(owner).release(owner.address, 1n),
        ).to.be.revertedWith("Not an approved spender");
      });

      it("Should block release after a spender is removed", async function () {
        const { token, pool, funder, module, user } = await deployFixture();

        await fund(token, pool, funder, ethers.parseUnits("100", 18), SOURCE_A);
        await pool.setSpender(module.address, true);
        await pool.setSpender(module.address, false);

        await expect(
          pool.connect(module).release(user.address, 1n),
        ).to.be.revertedWith("Not an approved spender");
      });

      it("Should reject invalid releases", async function () {
        const { token, pool, funder, module, user } = await deployFixture();

        await fund(token, pool, funder, ethers.parseUnits("100", 18), SOURCE_A);
        await pool.setSpender(module.address, true);

        await expect(
          pool.connect(module).release(ethers.ZeroAddress, 1n),
        ).to.be.revertedWith("Invalid recipient");

        await expect(
          pool.connect(module).release(user.address, 0n),
        ).to.be.revertedWith("Invalid amount");
      });
    });
  });
}
