// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

// What a payout module (e.g. NFTIncome) needs from a pool.
interface IPoolSpender {
    function budgetOf(address module) external view returns (uint256);

    function hasFundingConfigured(address module) external view returns (bool);

    function release(address to, uint256 amount) external;
}
