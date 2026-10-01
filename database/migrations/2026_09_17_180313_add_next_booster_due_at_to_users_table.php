<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('booster_period_started_at')->nullable()->after('token_balance');
            $table->timestamp('next_booster_due_at')->nullable()->after('booster_period_started_at');
        });

        // Existing users start a fresh 15-day window from today.
        DB::table('users')->update([
            'booster_period_started_at' => now(),
            'next_booster_due_at' => now()->addDays(15),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['booster_period_started_at', 'next_booster_due_at']);
        });
    }
};
