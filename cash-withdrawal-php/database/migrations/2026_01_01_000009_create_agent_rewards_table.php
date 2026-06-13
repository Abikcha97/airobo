<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_rewards', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('agent_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->bigInteger('total_turnover')->default(0);
            $table->bigInteger('reward_amount')->default(0);
            $table->decimal('reward_rate', 8, 5);
            $table->string('status', 20)->default('CALCULATED'); // CALCULATED|CREDITED
            $table->timestamp('calculated_at')->useCurrent();
            $table->timestamp('credited_at')->nullable();

            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('cascade');
            $table->unique(['agent_id', 'year', 'month']);
            $table->index(['agent_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_rewards');
    }
};
