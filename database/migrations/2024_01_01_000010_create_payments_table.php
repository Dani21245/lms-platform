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
            $table->string('transaction_ref')->unique();
            $table->string('payment_method')->default('telebirr');
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('ETB');
            $table->string('status')->default('pending');
            $table->string('telebirr_transaction_id')->nullable();
            $table->json('payment_data')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('user_id');
            $table->index('transaction_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
