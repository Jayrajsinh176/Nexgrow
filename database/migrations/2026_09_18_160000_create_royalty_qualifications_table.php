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
        Schema::create('royalty_qualifications', function (Blueprint $table) {
            $table->id();
            // One row per user, ever - qualification is a one-time lifetime
            // event per the plan, so this is unique rather than a history.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('direct_business', 16, 2);
            $table->date('qualified_at');
            $table->date('window_start');
            $table->date('window_end');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('royalty_qualifications');
    }
};
