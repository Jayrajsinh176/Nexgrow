// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "./BasePool.sol";

// Holds tokens set aside for liquidity.
contract LiquidityPool is BasePool {
    constructor(address _token) BasePool(_token) {}
}
