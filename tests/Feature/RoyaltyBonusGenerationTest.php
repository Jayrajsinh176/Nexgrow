<?php

namespace Tests\Feature;

use App\Models\NftPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoyaltyBonusGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_credits_a_real_royalty_bonus_to_qualified_users(): void
    {
        config(['blockchain.mode' => 'database']);

        $package = NftPackage::create([
            'name' => 'Royalty Test Package',
            'price' => 5000,
            'daily_bonus' => 25,
            'cap_multiplier' => 4,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $sponsor = User::factory()->create();
        $directReferral = User::factory()->create(['sponsor_id' => $sponsor->id]);

        $directReferral->nfts()->create([
            'nft_package_id' => $package->id,
            'purchase_price' => 5000,
            'daily_bonus' => 25,
            'cap_amount' => 20000,
            'total_paid_out' => 0,
            'status' => 'active',
            'purchased_at' => now(),
        ]);

        $this->artisan('app:process-royalty-bonus')->assertSuccessful();

        $this->assertDatabaseHas('royalty_qualifications', [
            'user_id' => $sponsor->id,
            'direct_business' => '5000.00',
        ]);

        $this->assertNotNull($sponsor->fresh()->royaltyQualification);
    }
}
