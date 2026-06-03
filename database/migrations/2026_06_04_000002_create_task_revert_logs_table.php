<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_revert_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bulk_edit_task_id')->constrained()->cascadeOnDelete();
            $table->string('shopify_product_id');
            $table->string('shopify_variant_id')->nullable();
            $table->json('original_data'); // before-state values
            $table->timestamps();

            $table->index('bulk_edit_task_id');
            $table->index('shopify_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_revert_logs');
    }
};
