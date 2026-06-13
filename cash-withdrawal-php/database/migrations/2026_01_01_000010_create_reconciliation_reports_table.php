<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('agent_id');
            $table->date('report_date');
            $table->unsignedInteger('total_transactions')->default(0);
            $table->bigInteger('total_amount')->default(0);
            $table->bigInteger('total_commission')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('discrepancy_count')->default(0);
            $table->jsonb('discrepancies')->nullable();
            $table->string('file_path_pdf', 500)->nullable();
            $table->string('file_path_csv', 500)->nullable();
            $table->timestamp('generated_at')->useCurrent();

            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('cascade');
            $table->unique(['agent_id', 'report_date']);
            $table->index(['agent_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_reports');
    }
};
