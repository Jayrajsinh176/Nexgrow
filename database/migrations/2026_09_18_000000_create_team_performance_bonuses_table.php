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
        Schema::create('team_performance_bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('team_target', 16, 2);
            $table->decimal('qualifying_business', 16, 2);
            $table->decimal('bonus_amount', 14, 2)->default(0);
            $table->json('leg_breakdown');
            $table->timestamps();

            $table->unique(['user_id', 'team_target']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_performance_bonuses');
    }
};
