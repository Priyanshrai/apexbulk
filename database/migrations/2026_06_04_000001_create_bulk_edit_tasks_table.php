<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_edit_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('task_type'); // price, inventory, tags
            $table->string('status')->default('pending'); // pending, running, completed, failed, reverting, reverted
            $table->json('parameters')->nullable(); // operation-specific config
            $table->json('product_ids')->nullable(); // targeted product IDs, null = all
            $table->timestamp('scheduled_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('task_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_edit_tasks');
    }
};
