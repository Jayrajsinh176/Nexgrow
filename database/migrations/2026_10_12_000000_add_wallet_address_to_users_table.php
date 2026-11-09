<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Lowercase 0x address, proven with a signed message.
            $table->string('wallet_address', 42)->nullable()->unique()->after('token_balance');
            $table->timestamp('wallet_linked_at')->nullable()->after('wallet_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['wallet_address']);
            $table->dropColumn(['wallet_address', 'wallet_linked_at']);
        });
    }
};
