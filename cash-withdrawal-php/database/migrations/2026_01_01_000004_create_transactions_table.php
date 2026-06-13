<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('agent_transaction_id', 100)->unique();
            $table->bigInteger('provider_transaction_id')->nullable();
            $table->bigInteger('check_transaction_id')->nullable();
            $table->unsignedBigInteger('kiosk_id');
            $table->unsignedBigInteger('agent_id');
            $table->unsignedInteger('service_id');
            $table->string('card_account', 20);   // stored masked: 860014******5346
            $table->string('card_expire', 10);     // MM/YY
            $table->string('requestor_phone', 20)->nullable();
            $table->bigInteger('amount');           // in tiyin (BIGINT)
            $table->bigInteger('commission')->default(0);
            $table->unsignedSmallInteger('currency_id')->default(0);
            $table->string('status', 30);
            $table->string('provider_name', 20)->nullable(); // OSON|PAYNET
            $table->jsonb('provider_check_response')->nullable();
            $table->jsonb('provider_pay_response')->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->unsignedTinyInteger('otp_resend_count')->default(0);
            $table->timestamp('otp_expires_at')->nullable();
            $table->timestamps();

            $table->foreign('kiosk_id')->references('id')->on('kiosks');
            $table->foreign('agent_id')->references('id')->on('agents');

            // Indexes
            $table->index(['status', 'created_at']);
            $table->index(['kiosk_id', 'agent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
