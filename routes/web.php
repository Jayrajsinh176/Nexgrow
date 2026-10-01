<?php

use App\Http\Controllers\Admin\WalletManagementController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
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
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
    Route::get('register/check-referral', [RegisteredUserController::class, 'checkReferral'])->name('register.check-referral');
    Route::get('register/check-availability', [RegisteredUserController::class, 'checkAvailability'])->name('register.check-availability');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('team', TeamController::class)->name('team');
    Route::get('wallet', WalletController::class)->name('wallet');
    Route::get('nft', [NftController::class, 'index'])->name('nft.index');
    Route::post('nft/purchase', [NftController::class, 'purchase'])->name('nft.purchase');
    Route::get('referral-income', ReferralIncomeController::class)->name('referral-income');
    Route::get('team-building-benefits', TeamBuildingBenefitController::class)->name('team-building-benefits');
    Route::get('booster', BoosterController::class)->name('booster');
    Route::get('team-performance-bonus', TeamPerformanceBonusController::class)->name('team-performance-bonus');
    Route::get('monthly-loyalty-bonus', MonthlyLoyaltyBonusController::class)->name('monthly-loyalty-bonus');
    Route::get('royalty-bonus', RoyaltyBonusController::class)->name('royalty-bonus');
    Route::get('team-development-bonus', TeamDevelopmentBonusController::class)->name('team-development-bonus');
    Route::get('profit-sharing-slot', [ProfitSharingSlotController::class, 'index'])->name('profit-sharing-slot.index');
    Route::post('profit-sharing-slot/purchase', [ProfitSharingSlotController::class, 'purchase'])->name('profit-sharing-slot.purchase');
    Route::post('profit-sharing-slot/{slot}/withdraw', [ProfitSharingSlotController::class, 'withdraw'])->name('profit-sharing-slot.withdraw');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('wallet', [WalletManagementController::class, 'index'])->name('wallet');
        Route::post('wallet/credit', [WalletManagementController::class, 'credit'])->name('wallet.credit');
        Route::post('wallet/debit', [WalletManagementController::class, 'debit'])->name('wallet.debit');
    });
});
