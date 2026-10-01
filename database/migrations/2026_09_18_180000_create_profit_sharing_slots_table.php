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
        Schema::create('profit_sharing_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            // Reserved for the profit-sharing rate once it's finalized;
            // stays 0 until then, so withdrawal math already accounts for
            // it without a later schema change.
            $table->decimal('accrued_profit', 14, 2)->default(0);
            $table->enum('status', ['active', 'withdrawn'])->default('active');
            $table->timestamp('purchased_at');
            $table->timestamp('withdrawn_at')->nullable();
            $table->decimal('withdrawal_amount', 14, 2)->nullable();
            $table->decimal('penalty_percent', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profit_sharing_slots');
    }
};
