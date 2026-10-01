<?php

namespace Database\Seeders;

use App\Models\NftPackage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoyaltyBonusTestSeeder extends Seeder
{
    /**
     * Seed test data to make the Royalty Bonus page display as achieved.
     */
    public function run(): void
    {
        $package = NftPackage::firstOrCreate(
            ['price' => 5000],
            [
                'name' => '$5,000 Package',
                'daily_bonus' => 1.00,
                'cap_multiplier' => 4,
                'is_active' => true,
                'sort_order' => 6,
            ]
        );

        $sponsor = User::firstOrCreate(
            ['email' => 'royalty-sponsor@test.com'],
            [
                'name' => 'Royalty Sponsor',
                'mobile' => '7000000001',
                'password' => Hash::make('password'),
                'user_id' => 'NEXROY01',
                'sponsor_id' => null,
                'token_balance' => 0,
                'is_admin' => false,
                'email_verified_at' => now(),
            ]
        );

        $referral = User::firstOrCreate(
            ['email' => 'royalty-referral@test.com'],
            [
                'name' => 'Royalty Referral',
                'mobile' => '7000000002',
                'password' => Hash::make('password'),
                'user_id' => 'NEXROY02',
                'sponsor_id' => $sponsor->id,
                'token_balance' => 0,
                'is_admin' => false,
                'email_verified_at' => now(),
            ]
        );

        $hasPurchase = $referral->nfts()->whereBetween('purchased_at', [now()->startOfMonth(), now()->endOfMonth()])->exists();

        if (! $hasPurchase) {
            $referral->nfts()->create([
                'nft_package_id' => $package->id,
                'purchase_price' => 5000,
                'daily_bonus' => 1.00,
                'cap_amount' => 20000,
                'total_paid_out' => 0,
                'status' => 'active',
                'purchased_at' => now(),
            ]);
        }

        $this->command->info('Royalty Bonus test data is ready.');
        $this->command->call('app:process-royalty-bonus');
        $this->command->info('Royalty Bonus payout processed for the current month.');
    }
}
