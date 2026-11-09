import { expect } from "chai";
import { network } from "hardhat";

const { ethers, networkHelpers } = await network.create();

const DAY = 24 * 60 * 60;
const PRICE = ethers.parseUnits("3000", 18);

const tnex = (v: string | number) => ethers.parseUnits(v.toString(), 18);

describe("ProfitSharingSlot", function () {
  async function deployFixture() {
    const [owner, depositor, other, a, b, c] = await ethers.getSigners();

    const token = await ethers.deployContract("TestNexadonToken");
    const tokenAddress = await token.getAddress();
    const treasury = await ethers.deployContract("NexgrowTreasury", [tokenAddress]);
    const treasuryAddress = await treasury.getAddress();
    const slots = await ethers.deployContract("ProfitSharingSlot", [
      tokenAddress,
      treasuryAddress,
    ]);
    const slotsAddress = await slots.getAddress();

    await slots.setProfitDepositor(depositor.address, true);

    await token.transfer(depositor.address, tnex(200_000));
    for (const u of [other, a, b, c]) {
      await token.transfer(u.address, tnex(10_000));
    }

    async function buy(user: any) {
      await token.connect(user).approve(slotsAddress, PRICE);
      return slots.connect(user).buySlot();
    }

    async function deposit(amount: bigint) {
      await token.connect(depositor).approve(slotsAddress, amount);
      return slots.connect(depositor).depositProfit(amount);
    }

    async function passDays(days: number) {
      if (days > 0) await networkHelpers.time.increase(days * DAY);
    }

    return {
      owner, depositor, other, a, b, c,
      token, treasury, treasuryAddress, slots, slotsAddress,
      buy, deposit, passDays,
    };
  }

  describe("Business rules", function () {
    it("Should use the report's slot terms", async function () {
      const { slots } = await deployFixture();

      expect(await slots.SLOT_PRICE()).to.equal(PRICE);
      expect(await slots.TOTAL_SLOTS()).to.equal(100n);
      expect(await slots.PROFIT_CAP()).to.equal(tnex(30_000));
      expect(await slots.MATURITY()).to.equal(BigInt(750 * DAY));
    });

    it("Should use the report's early withdrawal deductions", async function () {
      const { slots } = await deployFixture();

      expect(await slots.deductionBpsFor(0)).to.equal(5000n);
      expect(await slots.deductionBpsFor(179)).to.equal(5000n);
      expect(await slots.deductionBpsFor(180)).to.equal(2500n);
      expect(await slots.deductionBpsFor(359)).to.equal(2500n);
      expect(await slots.deductionBpsFor(360)).to.equal(1000n);
      expect(await slots.deductionBpsFor(539)).to.equal(1000n);
      expect(await slots.deductionBpsFor(540)).to.equal(0n);
    });

    it("Should reject zero addresses", async function () {
      const { token, treasuryAddress } = await deployFixture();

      await expect(
        ethers.deployContract("ProfitSharingSlot", [ethers.ZeroAddress, treasuryAddress]),
      ).to.be.revertedWith("Invalid token address");
      await expect(
        ethers.deployContract("ProfitSharingSlot", [await token.getAddress(), ethers.ZeroAddress]),
      ).to.be.revertedWith("Invalid treasury address");
    });
  });

  describe("Purchase", function () {
    it("Should sell a slot for 3,000 and keep the principal", async function () {
      const { slots, slotsAddress, token, buy, a } = await deployFixture();

      await expect(buy(a)).to.emit(slots, "SlotPurchased").withArgs(0n, a.address);

      const slot = await slots.getSlot(0);

      expect(slot.holder).to.equal(a.address);
      expect(slot.profit).to.equal(0n);
      expect(await slots.hasSlot(a.address)).to.equal(true);
      expect(await slots.slotOf(a.address)).to.equal(0n);
      expect(await token.balanceOf(slotsAddress)).to.equal(PRICE);
      expect(await slots.totalPrincipal()).to.equal(PRICE);
      expect(await slots.slotsRemaining()).to.equal(99n);
    });

    it("Should allow one slot per wallet, even after withdrawing", async function () {
      const { slots, buy, a } = await deployFixture();

      await buy(a);
      await expect(buy(a)).to.be.revertedWith("Already has a slot");

      await slots.connect(a).withdraw(0);
      await expect(buy(a)).to.be.revertedWith("Already has a slot");
    });

    it("Should stop at 100 slots", async function () {
      const { slots, token, owner, buy, a } = await deployFixture();

      for (let i = 0; i < 100; i++) {
        const w = ethers.Wallet.createRandom().connect(ethers.provider);
        await owner.sendTransaction({ to: w.address, value: ethers.parseEther("1") });
        await token.transfer(w.address, PRICE);
        await buy(w);
      }

      expect(await slots.slotsRemaining()).to.equal(0n);
      await expect(buy(a)).to.be.revertedWith("All slots sold");
    });

    it("Should reject a token that delivers less than the price", async function () {
      const { treasuryAddress, a } = await deployFixture();

      const fee = await ethers.deployContract("FeeOnTransferToken");
      const slots = await ethers.deployContract("ProfitSharingSlot", [
        await fee.getAddress(),
        treasuryAddress,
      ]);

      await fee.transfer(a.address, tnex(10_000));
      await fee.connect(a).approve(await slots.getAddress(), PRICE);

      await expect(slots.connect(a).buySlot()).to.be.revertedWith("Payment amount mismatch");
    });
  });

  describe("Profit", function () {
    it("Should only accept deposits from authorized depositors", async function () {
      const { slots, token, slotsAddress, other } = await deployFixture();

      await token.connect(other).approve(slotsAddress, tnex(100));

      await expect(slots.connect(other).depositProfit(tnex(100))).to.be.revertedWith(
        "Not a profit depositor",
      );
    });

    it("Should let only the owner manage depositors", async function () {
      const { slots, other } = await deployFixture();

      await expect(slots.connect(other).setProfitDepositor(other.address, true))
        .to.be.revertedWithCustomError(slots, "OwnableUnauthorizedAccount")
        .withArgs(other.address);
      await expect(slots.setProfitDepositor(ethers.ZeroAddress, true)).to.be.revertedWith(
        "Invalid depositor",
      );
    });

    it("Should split a deposit equally across earning slots", async function () {
      const { slots, buy, deposit, depositor, a, b, c } = await deployFixture();

      await buy(a);
      await buy(b);
      await buy(c);

      await expect(deposit(tnex(900)))
        .to.emit(slots, "ProfitDeposited")
        .withArgs(depositor.address, tnex(900), 3n);

      for (let i = 0; i < 3; i++) {
        expect((await slots.getSlot(i)).profit).to.equal(tnex(300));
      }
      expect(await slots.undistributedProfit()).to.equal(0n);
    });

    it("Should keep profit for later when no slot is earning", async function () {
      const { slots, buy, deposit, a, b } = await deployFixture();

      await deposit(tnex(500));
      expect(await slots.undistributedProfit()).to.equal(tnex(500));

      await buy(a);
      await buy(b);
      await deposit(tnex(100));

      expect((await slots.getSlot(0)).profit).to.equal(tnex(300));
      expect((await slots.getSlot(1)).profit).to.equal(tnex(300));
      expect(await slots.undistributedProfit()).to.equal(0n);
    });

    it("Should skip withdrawn slots", async function () {
      const { slots, buy, deposit, a, b } = await deployFixture();

      await buy(a);
      await buy(b);
      await slots.connect(a).withdraw(0);
      await deposit(tnex(600));

      expect((await slots.getSlot(0)).profit).to.equal(0n);
      expect((await slots.getSlot(1)).profit).to.equal(tnex(600));
    });

    it("Should stop a slot at 10X and keep the excess for others", async function () {
      const { slots, buy, deposit, a, b } = await deployFixture();

      await buy(a);
      await deposit(tnex(29_000));
      await buy(b);

      // Share 1,500 each: a only has room for 1,000.
      await deposit(tnex(3_000));

      expect((await slots.getSlot(0)).profit).to.equal(tnex(30_000));
      expect((await slots.getSlot(1)).profit).to.equal(tnex(1_500));
      expect(await slots.undistributedProfit()).to.equal(tnex(500));
      expect(await slots.isMatured(0)).to.equal(true);

      // a is matured: the next deposit (plus the 500 kept) goes to b only.
      await deposit(tnex(100));
      expect((await slots.getSlot(1)).profit).to.equal(tnex(2_100));
      expect(await slots.undistributedProfit()).to.equal(0n);
    });

    it("Should stop earning after 750 days", async function () {
      const { slots, buy, deposit, passDays, a, b } = await deployFixture();

      await buy(a);
      await passDays(400);
      await buy(b);
      await passDays(350); // a: 750 days, b: 350 days

      expect(await slots.isMatured(0)).to.equal(true);
      expect(await slots.isEarning(1)).to.equal(true);

      await deposit(tnex(100));

      expect((await slots.getSlot(0)).profit).to.equal(0n);
      expect((await slots.getSlot(1)).profit).to.equal(tnex(100));
    });
  });

  describe("Withdrawal", function () {
    async function withProfit() {
      const f = await deployFixture();

      await f.buy(f.a);
      await f.deposit(tnex(1_000)); // total 4,000

      return f;
    }

    for (const [days, bps] of [[0, 5000n], [180, 2500n], [360, 1000n], [540, 0n]] as const) {
      it(`Should deduct ${Number(bps) / 100}% of principal + profit after ${days} days`, async function () {
        const { slots, token, treasuryAddress, passDays, a } = await withProfit();

        await passDays(days);

        const total = tnex(4_000);
        const payout = total - (total * bps) / 10_000n;

        const userBefore = await token.balanceOf(a.address);
        const treasuryBefore = await token.balanceOf(treasuryAddress);

        await expect(slots.connect(a).withdraw(0))
          .to.emit(slots, "SlotWithdrawn")
          .withArgs(0n, a.address, total, bps, payout);

        expect((await token.balanceOf(a.address)) - userBefore).to.equal(payout);
        expect((await token.balanceOf(treasuryAddress)) - treasuryBefore).to.equal(total - payout);
        expect((await slots.getSlot(0)).withdrawn).to.equal(true);
      });
    }

    it("Should not deduct anything once the slot reached 10X", async function () {
      const { slots, token, buy, deposit, a } = await deployFixture();

      await buy(a);
      await deposit(tnex(30_000));

      const before = await token.balanceOf(a.address);

      await slots.connect(a).withdraw(0);

      expect((await token.balanceOf(a.address)) - before).to.equal(tnex(33_000));
    });

    it("Should preview the same amount as it pays", async function () {
      const { slots, passDays, a } = await withProfit();

      await passDays(200);

      const [total, bps, payout] = await slots.previewWithdraw(0);

      expect(total).to.equal(tnex(4_000));
      expect(bps).to.equal(2500n);
      expect(payout).to.equal(tnex(3_000));

      await slots.connect(a).withdraw(0);
    });

    it("Should only let the holder withdraw, once", async function () {
      const { slots, a, b } = await withProfit();

      await expect(slots.connect(b).withdraw(0)).to.be.revertedWith("Not the slot holder");
      await expect(slots.connect(a).withdraw(5)).to.be.revertedWith("Unknown slot");

      await slots.connect(a).withdraw(0);

      await expect(slots.connect(a).withdraw(0)).to.be.revertedWith("Already withdrawn");
    });
  });

  describe("Undistributed profit", function () {
    it("Should let the owner recover only profit not assigned to any slot", async function () {
      const { slots, token, slotsAddress, buy, deposit, owner, other, a } = await deployFixture();

      await deposit(tnex(500)); // no slots yet: stays undistributed
      await buy(a);

      await expect(slots.connect(other).recoverUndistributedProfit(other.address))
        .to.be.revertedWithCustomError(slots, "OwnableUnauthorizedAccount")
        .withArgs(other.address);

      const before = await token.balanceOf(owner.address);

      await expect(slots.recoverUndistributedProfit(owner.address))
        .to.emit(slots, "UndistributedProfitRecovered")
        .withArgs(owner.address, tnex(500));

      expect((await token.balanceOf(owner.address)) - before).to.equal(tnex(500));
      expect(await slots.undistributedProfit()).to.equal(0n);
      // a's principal is untouched.
      expect(await token.balanceOf(slotsAddress)).to.equal(PRICE);

      await expect(slots.recoverUndistributedProfit(owner.address)).to.be.revertedWith(
        "Nothing to recover",
      );
    });
  });

  describe("Pause", function () {
    it("Should stop purchases and deposits but never withdrawals", async function () {
      const { slots, buy, deposit, a, b } = await deployFixture();

      await buy(a);
      await slots.pause();

      await expect(buy(b)).to.be.revertedWithCustomError(slots, "EnforcedPause");
      await expect(deposit(tnex(100))).to.be.revertedWithCustomError(slots, "EnforcedPause");

      await slots.connect(a).withdraw(0);
    });
  });
});
