<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('evaluation_logs')) {
            Schema::create('evaluation_logs', function (Blueprint $table) {
                $table->unsignedInteger('id_evaluation')->autoIncrement();
                $table->dateTime('evaluated_at');
                $table->string('method', 64)->default('ibcf_cosine_time_holdout');
                $table->unsignedInteger('k_value');
                $table->unsignedInteger('users_evaluated')->default(0);
                $table->decimal('precision_at_k', 10, 6)->nullable();
                $table->decimal('recall_at_k', 10, 6)->nullable();
                $table->decimal('f1_at_k', 10, 6)->nullable();
                $table->decimal('hit_rate_at_k', 10, 6)->nullable();
                $table->decimal('catalog_coverage_at_k', 8, 4)->nullable();
                $table->decimal('duration_seconds', 10, 4)->nullable();
                $table->text('notes')->nullable();
                $table->dateTime('created_at')->useCurrent();

                $table->index(['evaluated_at', 'k_value']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_logs');
    }
};
