<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->string('payment_method')->default('telebirr');
            $table->string('transaction_ref')->unique();
            $table->string('merchant_order_id')->unique()->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('ETB');
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded', 'expired'])->default('pending');
            $table->json('payment_data')->nullable();
            $table->json('webhook_data')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('transaction_ref');
            $table->index('merchant_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
