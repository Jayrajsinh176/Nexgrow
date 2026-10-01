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
        Schema::create('referral_incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficiary_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_nft_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->decimal('percentage', 5, 2);
            $table->decimal('base_amount', 14, 2);
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_incomes');
    }
};
