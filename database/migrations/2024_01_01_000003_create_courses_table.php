<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->text('what_you_will_learn')->nullable();
            $table->string('thumbnail')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency')->default('ETB');
            $table->string('level')->default('beginner');
            $table->string('language')->default('en');
            $table->string('status')->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->integer('duration_minutes')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('instructor_id');
            $table->index('category_id');
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
