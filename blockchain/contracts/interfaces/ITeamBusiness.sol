// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

// The TeamBusiness tracker, used by the manager and by income modules.
interface ITeamBusiness {
    function recordPurchase(address buyer, uint256 amount) external;

    function lifetimeBusiness(address account) external view returns (uint256);

    function businessBetween(
        address account,
        uint256 fromDay,
        uint256 toDay
    ) external view returns (uint256);

    function directBusinessBetween(
        address account,
        uint256 fromDay,
        uint256 toDay
    ) external view returns (uint256);

    function totalBusinessBetween(
        uint256 fromDay,
        uint256 toDay
    ) external view returns (uint256);

    function isProcessedBefore(uint256 day) external view returns (bool);
}
