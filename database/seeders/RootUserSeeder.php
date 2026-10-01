<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RootUserSeeder extends Seeder
{
    /**
     * Seed the default root user that sits at the top of the referral tree.
     * Every other registration requires a referral code, so this is the
     * only user created without a sponsor.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@nexgrow.com')->first();

        if ($admin) {
            // Re-running the seeder always keeps this account promoted,
            // even if it was created before the is_admin column existed.
            $admin->forceFill(['is_admin' => true])->save();

            return;
        }

        // forceCreate bypasses mass-assignment guarding, since is_admin and
        // token_balance are intentionally excluded from $fillable.
        User::forceCreate([
            'name' => 'Nexgrow Admin',
            'mobile' => '9999999999',
            'password' => Hash::make('Nexgrow@123'),
            'user_id' => 'NEXGROW01',
            'sponsor_id' => null,
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);
    }
}
