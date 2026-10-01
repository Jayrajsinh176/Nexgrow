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
        Schema::table('user_nfts', function (Blueprint $table) {
            $table->date('last_paid_at')->nullable()->after('total_paid_out');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_nfts', function (Blueprint $table) {
            $table->dropColumn('last_paid_at');
        });
    }
};
