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
        Schema::create('bonus_boosters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->decimal('business_amount', 14, 2);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->decimal('bonus_amount', 14, 2)->default(0);
            $table->boolean('qualified')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonus_boosters');
    }
};
