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
        Schema::create('nft_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 14, 2);
            $table->decimal('daily_bonus', 14, 2);
            $table->unsignedTinyInteger('cap_multiplier')->default(4);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nft_packages');
    }
};
