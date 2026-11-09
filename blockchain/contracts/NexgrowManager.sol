// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "@openzeppelin/contracts/utils/math/SafeCast.sol";
import "./access/NexgrowOwnable.sol";
import "./interfaces/INexgrowNFT.sol";
import "./interfaces/INexgrowManager.sol";
import "./interfaces/INFTIncome.sol";
import "./interfaces/ITeamBusiness.sol";

// Coordinates the Nexgrow modules. Income calculations live in their
// own contracts under contracts/income, not here.
contract NexgrowManager is INexgrowManager, NexgrowOwnable {

    // Packed into 1 storage slot.
    struct NFTRecord {
        address buyer;
        uint32 packageAmount;
        uint64 registeredAt;
    }

    // Module keys. Income modules will get their own keys when added.
    bytes32 public constant TREASURY = keccak256("TREASURY");
    bytes32 public constant REWARD_POOL = keccak256("REWARD_POOL");
    bytes32 public constant LIQUIDITY_POOL = keccak256("LIQUIDITY_POOL");
    bytes32 public constant NFT_INCOME = keccak256("NFT_INCOME");
    bytes32 public constant REFERRAL_INCOME = keccak256("REFERRAL_INCOME");
    bytes32 public constant TEAM_BUILDING_INCOME = keccak256("TEAM_BUILDING_INCOME");
    bytes32 public constant TEAM_BUSINESS = keccak256("TEAM_BUSINESS");
    bytes32 public constant TEAM_DEVELOPMENT = keccak256("TEAM_DEVELOPMENT");
    bytes32 public constant PROFIT_SHARING_SLOT = keccak256("PROFIT_SHARING_SLOT");

    INexgrowNFT public nftContract;

    mapping(uint256 => NFTRecord) public nftRecords;

    uint256 public registeredCount;

    // wallet => has bought at least one NFT
    mapping(address => bool) public hasPurchased;

    // Directory of connected modules (treasury, pools, income modules).
    mapping(bytes32 => address) public modules;

    event NFTContractUpdated(address indexed oldNFT, address indexed newNFT);

    event ModuleUpdated(
        bytes32 indexed key,
        address indexed oldModule,
        address indexed newModule
    );

    event NFTRegistered(
        uint256 indexed tokenId,
        address indexed buyer,
        uint256 packageAmount,
        uint256 registeredAt
    );

    modifier onlyNFT() {
        require(msg.sender == address(nftContract), "Only NFT contract");
        _;
    }

    constructor(address _nftContract) {
        require(_nftContract != address(0), "Invalid NFT address");

        nftContract = INexgrowNFT(_nftContract);

        emit NFTContractUpdated(address(0), _nftContract);
    }

    // Only allowed before the first sale, so NFT IDs from two contracts
    // can never be mixed in the records or in the income modules.
    function setNFTContract(address _nftContract) external onlyOwner {
        require(_nftContract != address(0), "Invalid NFT address");
        require(registeredCount == 0, "NFTs already registered");

        address oldNFT = address(nftContract);

        nftContract = INexgrowNFT(_nftContract);

        emit NFTContractUpdated(oldNFT, _nftContract);
    }

    // Pass address(0) to disconnect a module.
    function setModule(bytes32 key, address module) external onlyOwner {
        require(key != bytes32(0), "Invalid key");

        address oldModule = modules[key];

        modules[key] = module;

        emit ModuleUpdated(key, oldModule, module);
    }

    function registerNFT(
        uint256 tokenId,
        address buyer,
        uint256 packageAmount
    ) external onlyNFT {
        require(buyer != address(0), "Invalid buyer");
        require(packageAmount > 0, "Invalid package");
        require(nftRecords[tokenId].buyer == address(0), "Already registered");

        nftRecords[tokenId] = NFTRecord({
            buyer: buyer,
            packageAmount: SafeCast.toUint32(packageAmount),
            registeredAt: SafeCast.toUint64(block.timestamp)
        });

        registeredCount++;
        hasPurchased[buyer] = true;

        emit NFTRegistered(tokenId, buyer, packageAmount, block.timestamp);

        // Activate the NFT in the daily bonus module, if connected.
        address nftIncome = modules[NFT_INCOME];

        if (nftIncome != address(0)) {
            INFTIncome(nftIncome).activateNFT(tokenId, packageAmount);
        }

        // Count the purchase price as team business, if connected.
        address teamBusiness = modules[TEAM_BUSINESS];

        if (teamBusiness != address(0)) {
            ITeamBusiness(teamBusiness).recordPurchase(buyer, packageAmount * 1 ether);
        }
    }

    // Called by the NFT contract on every transfer between wallets, so the
    // daily bonus module can keep track of who owns active NFTs.
    function onNFTTransfer(uint256 tokenId) external onlyNFT {
        address nftIncome = modules[NFT_INCOME];

        if (nftIncome != address(0)) {
            INFTIncome(nftIncome).onTransfer(tokenId);
        }
    }

    // Plan rule (as in the Laravel backend): a wallet holding an NFT whose
    // 4X cycle is not completed cannot buy another one.
    function canPurchase(address buyer) external view returns (bool) {
        address nftIncome = modules[NFT_INCOME];

        return nftIncome == address(0) || !INFTIncome(nftIncome).hasActiveNFT(buyer);
    }

    function isRegistered(uint256 tokenId) external view returns (bool) {
        return nftRecords[tokenId].buyer != address(0);
    }

    function getNFTPackage(uint256 tokenId) external view returns (uint256) {
        return nftContract.nftPackage(tokenId);
    }

    function getNFTOwner(uint256 tokenId) external view returns (address) {
        return nftContract.ownerOf(tokenId);
    }
}
