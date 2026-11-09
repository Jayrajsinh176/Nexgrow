// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "@openzeppelin/contracts/token/ERC20/IERC20.sol";

// What a future revenue-producing contract needs from a pool.
interface IRevenuePool {
    function token() external view returns (IERC20);

    function deposit(uint256 amount, bytes32 source) external;
}
