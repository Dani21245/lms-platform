<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->onDelete('set null');
            $table->string('transaction_ref')->nullable();
            $table->string('event_type');
            $table->string('status');
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index('transaction_ref');
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_logs');
    }
};
