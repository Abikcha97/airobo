<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispenser_inventory', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('kiosk_id');
            $table->unsignedInteger('denomination');
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('min_threshold')->default(20);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('kiosk_id')->references('id')->on('kiosks')->onDelete('cascade');
            $table->unique(['kiosk_id', 'denomination']);
        });

        Schema::create('dispenser_fill_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('kiosk_id');
            $table->unsignedInteger('denomination');
            $table->unsignedInteger('quantity_added');
            $table->unsignedBigInteger('filled_by');
            $table->timestamp('filled_at')->useCurrent();
            $table->text('notes')->nullable();

            $table->foreign('kiosk_id')->references('id')->on('kiosks')->onDelete('cascade');
            $table->foreign('filled_by')->references('id')->on('users');
            $table->index(['kiosk_id', 'filled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispenser_fill_history');
        Schema::dropIfExists('dispenser_inventory');
    }
};
