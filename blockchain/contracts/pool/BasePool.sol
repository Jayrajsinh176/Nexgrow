// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "@openzeppelin/contracts/utils/Pausable.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "../access/NexgrowOwnable.sol";
import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/token/ERC20/utils/SafeERC20.sol";
import "../interfaces/IRevenuePool.sol";
import "../interfaces/IPoolSpender.sol";

// Shared logic for every Nexgrow pool.
//
// Revenue accounting (deposits) is kept separate from payouts (releases):
// - Revenue sources are registered by the owner, e.g. keccak256("SOURCE_NAME").
// - Only depositors authorized for a given source can deposit as that source.
// - The owner sets what share (in basis points) of each source's future
//   deposits is allocated to each module. Default is 0 for everyone.
// - Each deposit adds the allocated share to the module's budget.
// - Only approved spenders can release funds, never more than their own
//   budget and never more than the accounted revenue still available.
// - The owner can pause all payouts (release) in an emergency. Deposits stay
//   open so revenue is never lost.
abstract contract BasePool is IRevenuePool, IPoolSpender, NexgrowOwnable, Pausable, ReentrancyGuard {
    using SafeERC20 for IERC20;

    uint256 public constant BPS_DENOMINATOR = 10_000;

    IERC20 public immutable token;

    // ---- Revenue sources ----

    bytes32[] private _revenueSources;

    mapping(bytes32 => bool) public isRevenueSource;

    // source => what it is and where it is documented
    mapping(bytes32 => string) public sourceDescription;

    // depositor => source => allowed
    mapping(address => mapping(bytes32 => bool)) public isDepositor;

    // source => number of currently authorized depositors
    mapping(bytes32 => uint256) public depositorCount;

    // ---- Accounting ----

    mapping(bytes32 => uint256) public depositedBySource;

    uint256 public totalDeposited;
    uint256 public totalReleased;

    // ---- Allocation ----

    // module => source => share of that source's deposits, in bps
    mapping(address => mapping(bytes32 => uint256)) public allocationBps;

    // source => sum of all modules' bps (max 10000)
    mapping(bytes32 => uint256) public totalAllocationBps;

    // source => modules that have ever had an allocation for it
    mapping(bytes32 => address[]) private _allocatedModules;
    mapping(bytes32 => mapping(address => bool)) private _isAllocatedModule;

    // module => funded amount it may still release
    mapping(address => uint256) public budgetOf;

    // ---- Payouts ----

    mapping(address => bool) public isSpender;

    event RevenueSourceAdded(bytes32 indexed source, string description);
    event DepositorUpdated(address indexed depositor, bytes32 indexed source, bool allowed);
    event SpenderUpdated(address indexed spender, bool allowed);
    event AllocationUpdated(address indexed module, bytes32 indexed source, uint256 bps);
    event Deposited(address indexed from, bytes32 indexed source, uint256 amount);
    event BudgetAllocated(address indexed module, bytes32 indexed source, uint256 amount);
    event Released(address indexed spender, address indexed to, uint256 amount);

    modifier onlySpender() {
        require(isSpender[msg.sender], "Not an approved spender");
        _;
    }

    constructor(address _token) {
        require(_token != address(0), "Invalid token address");

        token = IERC20(_token);
    }

    // ---- Administration ----

    // description should say what the revenue is and where it is documented.
    function addRevenueSource(
        bytes32 source,
        string calldata description
    ) external onlyOwner {
        require(source != bytes32(0), "Invalid source");
        require(!isRevenueSource[source], "Source already added");
        require(bytes(description).length > 0, "Description required");

        isRevenueSource[source] = true;
        sourceDescription[source] = description;
        _revenueSources.push(source);

        emit RevenueSourceAdded(source, description);
    }

    function setDepositor(
        address depositor,
        bytes32 source,
        bool allowed
    ) external onlyOwner {
        require(depositor != address(0), "Invalid depositor");
        require(isRevenueSource[source], "Unknown source");

        if (isDepositor[depositor][source] != allowed) {
            if (allowed) {
                depositorCount[source] += 1;
            } else {
                depositorCount[source] -= 1;
            }

            isDepositor[depositor][source] = allowed;
        }

        emit DepositorUpdated(depositor, source, allowed);
    }

    function setSpender(address spender, bool allowed) external onlyOwner {
        require(spender != address(0), "Invalid spender");

        isSpender[spender] = allowed;

        emit SpenderUpdated(spender, allowed);
    }

    // Emergency stop for payouts.
    function pause() external onlyOwner {
        _pause();
    }

    function unpause() external onlyOwner {
        _unpause();
    }

    // Applies to future deposits only. Set bps to 0 to stop an allocation.
    function setAllocation(
        address module,
        bytes32 source,
        uint256 bps
    ) external onlyOwner {
        require(module != address(0), "Invalid module");
        require(isRevenueSource[source], "Unknown source");

        uint256 newTotal = totalAllocationBps[source] - allocationBps[module][source] + bps;

        require(newTotal <= BPS_DENOMINATOR, "Allocation exceeds 100%");

        totalAllocationBps[source] = newTotal;
        allocationBps[module][source] = bps;

        if (!_isAllocatedModule[source][module]) {
            _isAllocatedModule[source][module] = true;
            _allocatedModules[source].push(module);
        }

        emit AllocationUpdated(module, source, bps);
    }

    // ---- Views ----

    function getRevenueSources() external view returns (bytes32[] memory) {
        return _revenueSources;
    }

    function getAllocatedModules(bytes32 source) external view returns (address[] memory) {
        return _allocatedModules[source];
    }

    // True only when the module is an approved spender and at least one
    // registered source has both an authorized depositor and a non-zero
    // allocation to this module.
    function hasFundingConfigured(address module) external view returns (bool) {
        if (!isSpender[module]) return false;

        for (uint256 i = 0; i < _revenueSources.length; i++) {
            bytes32 source = _revenueSources[i];

            if (depositorCount[source] > 0 && allocationBps[module][source] > 0) {
                return true;
            }
        }

        return false;
    }

    function balance() external view returns (uint256) {
        return token.balanceOf(address(this));
    }

    // Accounted revenue that has not been paid out yet. Tokens sent to the
    // pool without deposit() are not counted.
    function availableRevenue() public view returns (uint256) {
        return totalDeposited - totalReleased;
    }

    // ---- Revenue in ----

    function deposit(uint256 requested, bytes32 source) external nonReentrant {
        require(isDepositor[msg.sender][source], "Not authorized for source");
        require(requested > 0, "Invalid amount");

        // Record what actually arrived, not what was requested.
        uint256 balanceBefore = token.balanceOf(address(this));

        token.safeTransferFrom(msg.sender, address(this), requested);

        uint256 amount = token.balanceOf(address(this)) - balanceBefore;

        require(amount > 0, "Nothing received");

        depositedBySource[source] += amount;
        totalDeposited += amount;

        emit Deposited(msg.sender, source, amount);

        // Split the configured shares into module budgets. Any rounding
        // remainder and any unallocated share stays unassigned in the pool.
        address[] storage modules = _allocatedModules[source];

        for (uint256 i = 0; i < modules.length; i++) {
            uint256 bps = allocationBps[modules[i]][source];

            if (bps == 0) continue;

            uint256 share = (amount * bps) / BPS_DENOMINATOR;

            if (share == 0) continue;

            budgetOf[modules[i]] += share;

            emit BudgetAllocated(modules[i], source, share);
        }
    }

    // ---- Payouts out ----

    function release(
        address to,
        uint256 amount
    ) external onlySpender whenNotPaused nonReentrant {
        require(to != address(0), "Invalid recipient");
        require(amount > 0, "Invalid amount");
        require(amount <= budgetOf[msg.sender], "Exceeds module budget");
        require(amount <= availableRevenue(), "Insufficient pool revenue");

        budgetOf[msg.sender] -= amount;
        totalReleased += amount;

        token.safeTransfer(to, amount);

        emit Released(msg.sender, to, amount);
    }
}
