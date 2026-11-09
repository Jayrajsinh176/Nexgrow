// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

// Only the NexgrowNFT functions that other contracts need to read.
interface INexgrowNFT {
    function ownerOf(uint256 tokenId) external view returns (address);

    function balanceOf(address owner) external view returns (uint256);

    function nftPackage(uint256 tokenId) external view returns (uint256);
}
