// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "@openzeppelin/contracts/token/ERC721/ERC721.sol";
import "@openzeppelin/contracts/token/ERC721/utils/ERC721Utils.sol";
import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/token/ERC20/utils/SafeERC20.sol";
import "@openzeppelin/contracts/utils/Pausable.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "../access/NexgrowOwnable.sol";
import "../interfaces/INexgrowManager.sol";

contract NexgrowNFT is ERC721, NexgrowOwnable, Pausable, ReentrancyGuard {
    using SafeERC20 for IERC20;

    uint256 private _nextTokenId;

    IERC20 public immutable paymentToken;
    address public immutable treasury;

    INexgrowManager public manager;

    mapping(uint256 => uint256) public nftPackage;

    event ManagerUpdated(address indexed oldManager, address indexed newManager);

    constructor(
        address _paymentToken,
        address _treasury
    )
        ERC721("Nexgrow NFT", "NXNFT")
    {
        require(_paymentToken != address(0), "Invalid token address");
        require(_treasury != address(0), "Invalid treasury address");

        paymentToken = IERC20(_paymentToken);
        treasury = _treasury;
    }

    function setManager(address _manager) external onlyOwner {
        require(_manager != address(0), "Invalid manager address");

        address oldManager = address(manager);

        manager = INexgrowManager(_manager);

        emit ManagerUpdated(oldManager, _manager);
    }

    // Emergency stop for purchases. NFT transfers are not affected.
    function pause() external onlyOwner {
        _pause();
    }

    function unpause() external onlyOwner {
        _unpause();
    }

    function buyNFT(uint256 packageAmount) public whenNotPaused nonReentrant {
        require(address(manager) != address(0), "Manager not set");
        require(manager.canPurchase(msg.sender), "Active NFT not completed");

        require(
            packageAmount == 50 ||
            packageAmount == 100 ||
            packageAmount == 250 ||
            packageAmount == 500 ||
            packageAmount == 1000 ||
            packageAmount == 2500 ||
            packageAmount == 5000,
            "Invalid package"
        );

        uint256 paymentAmount = packageAmount * 10 ** 18;

        // The treasury must receive the full package price.
        uint256 treasuryBefore = paymentToken.balanceOf(treasury);

        paymentToken.safeTransferFrom(msg.sender, treasury, paymentAmount);

        require(
            paymentToken.balanceOf(treasury) - treasuryBefore == paymentAmount,
            "Payment amount mismatch"
        );

        uint256 tokenId = _nextTokenId;

        _nextTokenId++;

        nftPackage[tokenId] = packageAmount;

        _mint(msg.sender, tokenId);

        // Tell the manager about the new NFT so future modules can use it.
        manager.registerNFT(tokenId, msg.sender, packageAmount);

        // Receiver check last (same check as _safeMint), so a buying contract
        // cannot act in its callback before the purchase is fully recorded.
        ERC721Utils.checkOnERC721Received(msg.sender, address(0), msg.sender, tokenId, "");
    }

    // Reports transfers between wallets (not mints) to the manager.
    function _update(
        address to,
        uint256 tokenId,
        address auth
    ) internal override returns (address from) {
        from = super._update(to, tokenId, auth);

        if (from != address(0) && to != address(0) && address(manager) != address(0)) {
            manager.onNFTTransfer(tokenId);
        }
    }
}
