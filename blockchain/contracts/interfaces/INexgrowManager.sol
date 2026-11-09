// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "./INexgrowNFT.sol";

// The NexgrowManager functions other Nexgrow contracts use.
interface INexgrowManager {
    function registerNFT(
        uint256 tokenId,
        address buyer,
        uint256 packageAmount
    ) external;

    function onNFTTransfer(uint256 tokenId) external;

    function nftContract() external view returns (INexgrowNFT);

    function isRegistered(uint256 tokenId) external view returns (bool);

    function hasPurchased(address buyer) external view returns (bool);

    function canPurchase(address buyer) external view returns (bool);

    function modules(bytes32 key) external view returns (address);
}
