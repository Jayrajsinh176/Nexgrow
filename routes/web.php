<?php

use App\Http\Controllers\Admin\AdminAuthenticatedSessionController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\BlockchainManagementController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\IncomeManagementController;
use App\Http\Controllers\Admin\OwnerActionsController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\WalletAuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BoosterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MonthlyLoyaltyBonusController;
use App\Http\Controllers\NftController;
use App\Http\Controllers\ProfitSharingSlotController;
use App\Http\Controllers\ReferralIncomeController;
use App\Http\Controllers\RoyaltyBonusController;
use App\Http\Controllers\TeamBuildingBenefitController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamDevelopmentBonusController;
use App\Http\Controllers\TeamPerformanceBonusController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WalletLinkController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return auth()->user()->is_admin
        ? redirect()->route('admin.dashboard')
        : redirect()->route('dashboard');
});

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
    Route::get('register/check-referral', [RegisteredUserController::class, 'checkReferral'])->name('register.check-referral');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Web3 Wallet Authentication
    Route::get('auth/wallet/message', [WalletAuthController::class, 'message'])->name('wallet.auth.message');
    Route::post('auth/wallet', [WalletAuthController::class, 'memberAuth'])->middleware('throttle:30,1')->name('wallet.auth');
});

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->middleware('not-impersonating')->name('profile.update');
    Route::get('team', TeamController::class)->name('team');
    Route::get('wallet', WalletController::class)->name('wallet');
    Route::get('wallet/link/message', [WalletLinkController::class, 'message'])->middleware('not-impersonating')->name('wallet.link.message');
    Route::post('wallet/link', [WalletLinkController::class, 'store'])->middleware(['not-impersonating', 'throttle:10,1'])->name('wallet.link');
    Route::get('nft', [NftController::class, 'index'])->name('nft.index');
    Route::post('nft/purchase', [NftController::class, 'purchase'])->middleware('not-impersonating')->name('nft.purchase');
    Route::get('referral-income', ReferralIncomeController::class)->name('referral-income');
    Route::get('team-building-benefits', TeamBuildingBenefitController::class)->name('team-building-benefits');
    Route::get('booster', BoosterController::class)->name('booster');
    Route::get('team-performance-bonus', TeamPerformanceBonusController::class)->name('team-performance-bonus');
    Route::get('monthly-loyalty-bonus', MonthlyLoyaltyBonusController::class)->name('monthly-loyalty-bonus');
    Route::get('royalty-bonus', RoyaltyBonusController::class)->name('royalty-bonus');
    Route::get('team-development-bonus', TeamDevelopmentBonusController::class)->name('team-development-bonus');
    Route::get('profit-sharing-slot', [ProfitSharingSlotController::class, 'index'])->name('profit-sharing-slot.index');
    Route::post('profit-sharing-slot/purchase', [ProfitSharingSlotController::class, 'purchase'])->middleware('not-impersonating')->name('profit-sharing-slot.purchase');
    Route::post('profit-sharing-slot/{slot}/withdraw', [ProfitSharingSlotController::class, 'withdraw'])->middleware('not-impersonating')->name('profit-sharing-slot.withdraw');
    Route::post('impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AdminAuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AdminAuthenticatedSessionController::class, 'store']);
    Route::post('auth/wallet', [WalletAuthController::class, 'adminAuth'])->middleware('throttle:15,1')->name('wallet.auth');
    Route::post('logout', [AdminAuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', AdminDashboardController::class)->name('dashboard');
    Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('users/{user}', [UserManagementController::class, 'show'])->name('users.show');
    Route::post('users/{user}/impersonate', [ImpersonationController::class, 'start'])->name('users.impersonate');
    Route::get('incomes/{stream?}', IncomeManagementController::class)
        ->whereIn('stream', array_keys(IncomeManagementController::STREAMS))
        ->name('incomes.index');
    Route::get('blockchain', [BlockchainManagementController::class, 'index'])->name('blockchain.index');
    Route::post('blockchain/keeper', [BlockchainManagementController::class, 'runKeeper'])->name('blockchain.keeper');    Route::get('owner-actions', OwnerActionsController::class)->name('owner-actions');
});
