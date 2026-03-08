<?php

namespace App\Jobs;

use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessEnrollment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly int $userId,
        private readonly int $courseId,
    ) {}

    public function handle(): void
    {
        $enrollment = Enrollment::firstOrCreate([
            'user_id' => $this->userId,
            'course_id' => $this->courseId,
        ]);

        Log::info('Enrollment processed', [
            'enrollment_id' => $enrollment->id,
            'user_id' => $this->userId,
            'course_id' => $this->courseId,
        ]);
    }
}
