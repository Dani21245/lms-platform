<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // First, remove any existing duplicate records before adding the constraint
        // Keep the earliest enrollment (lowest id) and delete duplicates
        $duplicates = DB::table('enrollments')
            ->select('user_id', 'course_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('user_id', 'course_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            // Delete all enrollments for this user-course pair except the one we want to keep
            DB::table('enrollments')
                ->where('user_id', $duplicate->user_id)
                ->where('course_id', $duplicate->course_id)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
            
            // Log the cleanup for audit purposes
            \Log::info('Removed duplicate enrollments', [
                'user_id' => $duplicate->user_id,
                'course_id' => $duplicate->course_id,
                'kept_id' => $duplicate->keep_id
            ]);
        }

        // Now add the unique constraint if it doesn't already exist
        Schema::table('enrollments', function (Blueprint $table) {
            // Check if the constraint already exists by attempting to add it
            // Laravel will handle the case where it already exists
            try {
                $table->unique(['user_id', 'course_id'], 'enrollments_user_course_unique');
            } catch (\Exception $e) {
                // Constraint may already exist, which is fine
                if (!str_contains($e->getMessage(), 'already exists')) {
                    throw $e;
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropUnique('enrollments_user_course_unique');
        });
    }
};
