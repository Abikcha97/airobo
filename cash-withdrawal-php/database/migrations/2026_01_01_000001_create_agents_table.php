<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('legal_name');
            $table->string('phone', 20);
            $table->string('email')->unique();
            $table->string('inn', 20)->unique();
            $table->string('tier', 20)->default('STANDARD'); // STANDARD|SILVER|GOLD
            $table->decimal('reward_rate', 8, 5)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
