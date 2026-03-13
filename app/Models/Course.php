<?php

namespace App\Models;

use App\Enums\CourseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'instructor_id',
        'category_id',
        'title',
        'slug',
        'description',
        'requirements',
        'what_you_will_learn',
        'thumbnail',
        'price',
        'currency',
        'level',
        'language',
        'status',
        'is_featured',
        'duration_minutes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_featured' => 'boolean',
        'duration_minutes' => 'integer',
        'status' => CourseStatus::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (Course $course) {
            if (empty($course->slug)) {
                $course->slug = Str::slug($course->title).'-'.Str::random(6);
            }
        });
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('sort_order');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isPublished(): bool
    {
        return $this->status === CourseStatus::PUBLISHED;
    }

    public function isFree(): bool
    {
        return $this->price <= 0;
    }

    public function getStudentCountAttribute(): int
    {
        return $this->enrollments()->count();
    }

    public function recalculateDuration(): void
    {
        $this->update([
            'duration_minutes' => $this->lessons()->sum('duration_minutes'),
        ]);
    }
}
