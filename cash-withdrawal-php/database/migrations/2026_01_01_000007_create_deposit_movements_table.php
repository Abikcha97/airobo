<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_movements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('agent_deposit_id');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->string('type', 10);             // CREDIT|DEBIT
            $table->bigInteger('amount');
            $table->bigInteger('balance_before');
            $table->bigInteger('balance_after');
            $table->string('description', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('agent_deposit_id')->references('id')->on('agent_deposits')->onDelete('cascade');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null');

            $table->index(['agent_deposit_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_movements');
    }
};
