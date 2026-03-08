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
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('preview_video')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->enum('level', ['beginner', 'intermediate', 'advanced'])->default('beginner');
            $table->string('language')->default('en');
            $table->enum('status', ['draft', 'pending', 'published', 'archived'])->default('draft');
            $table->integer('duration_hours')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->integer('max_students')->nullable();
            $table->json('requirements')->nullable();
            $table->json('what_you_learn')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index('instructor_id');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
