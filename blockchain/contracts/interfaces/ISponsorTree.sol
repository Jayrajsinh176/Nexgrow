// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

// The sponsor tree kept by ReferralIncome, read by other upline modules.
interface ISponsorTree {
    function sponsorOf(address account) external view returns (address);

    function directCount(address account) external view returns (uint256);
}
