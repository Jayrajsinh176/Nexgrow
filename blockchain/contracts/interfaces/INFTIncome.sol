// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

// The NFTIncome functions other Nexgrow contracts call.
interface INFTIncome {
    function activateNFT(uint256 tokenId, uint256 packageAmount) external;

    function onTransfer(uint256 tokenId) external;

    function hasActiveNFT(address account) external view returns (bool);
}
