<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use App\Services\TelebirrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Preservation Property Tests
 * 
 * These tests verify that valid (non-buggy) behavior is preserved after fixes.
 * They should PASS on both unfixed and fixed code.
 * 
 * Property 3: Valid Enrollment Creation (Requirements 3.1, 3.2, 3.3)
 * Property 4: Encryption and Payment Flow (Requirements 3.4, 3.5, 3.6)
 */
class PreservationPropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property 3.1: First-time enrollment creation succeeds
     * 
     * **Validates: Requirements 3.1**
     * 
     * For all valid user-course combinations where no enrollment exists,
     * the system SHALL create the enrollment successfully.
     * 
     * This property generates multiple test cases with different user/course IDs
     * to verify the behavior holds across the input domain.
     */
    public function test_property_first_time_enrollment_succeeds(): void
    {
        // Property-based approach: Test multiple cases
        $testCases = $this->generateFirstTimeEnrollmentCases();

        foreach ($testCases as $case) {
            // Arrange: Create user and course (without specifying IDs)
            $user = User::factory()->create();
            $course = Course::factory()->create();

            // Act: Create first-time enrollment
            $enrollment = Enrollment::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'progress' => 0,
            ]);

            // Assert: Enrollment should be created successfully
            $this->assertNotNull($enrollment->id, 
                "First-time enrollment should succeed for user {$user->id} and course {$course->id}");
            $this->assertDatabaseHas('enrollments', [
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);
        }
    }

    /**
     * Property 3.2: Different course enrollments succeed
     * 
     * **Validates: Requirements 3.2**
     * 
     * For all cases where a user enrolls in different courses,
     * the system SHALL allow multiple enrollment records with different course_id values.
     */
    public function test_property_different_course_enrollments_succeed(): void
    {
        // Property-based approach: Test multiple cases
        $testCases = $this->generateDifferentCourseEnrollmentCases();

        foreach ($testCases as $case) {
            // Arrange: Create one user and multiple courses
            $user = User::factory()->create();
            $courses = [];
            $courseCount = $case['course_count'];
            for ($i = 0; $i < $courseCount; $i++) {
                $courses[] = Course::factory()->create();
            }

            // Act: Enroll user in multiple different courses
            $enrollments = [];
            foreach ($courses as $course) {
                $enrollments[] = Enrollment::create([
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'progress' => 0,
                ]);
            }

            // Assert: All enrollments should succeed
            $this->assertCount(count($courses), $enrollments, 
                "User should be able to enroll in " . count($courses) . " different courses");
            
            foreach ($courses as $course) {
                $this->assertDatabaseHas('enrollments', [
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                ]);
            }
        }
    }

    /**
     * Property 3.3: Different user enrollments succeed
     * 
     * **Validates: Requirements 3.3**
     * 
     * For all cases where different users enroll in the same course,
     * the system SHALL allow multiple enrollment records with different user_id values.
     */
    public function test_property_different_user_enrollments_succeed(): void
    {
        // Property-based approach: Test multiple cases
        $testCases = $this->generateDifferentUserEnrollmentCases();

        foreach ($testCases as $case) {
            // Arrange: Create multiple users and one course
            $users = [];
            $userCount = $case['user_count'];
            for ($i = 0; $i < $userCount; $i++) {
                $users[] = User::factory()->create();
            }
            $course = Course::factory()->create();

            // Act: Enroll multiple different users in the same course
            $enrollments = [];
            foreach ($users as $user) {
                $enrollments[] = Enrollment::create([
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'progress' => 0,
                ]);
            }

            // Assert: All enrollments should succeed
            $this->assertCount(count($users), $enrollments, 
                count($users) . " different users should be able to enroll in the same course");
            
            foreach ($users as $user) {
                $this->assertDatabaseHas('enrollments', [
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                ]);
            }
        }
    }

    /**
     * Property 4.1: Encryption with public key succeeds
     * 
     * **Validates: Requirements 3.4**
     * 
     * For all payment data, the encryptPayload() method SHALL continue to use
     * the public key for encryption successfully.
     */
    public function test_property_encryption_with_public_key_succeeds(): void
    {
        // Arrange: Set up test public key
        $keyPair = $this->generateTestKeyPair();
        config(['telebirr.public_key' => $keyPair['public_key_base64']]);

        // Property-based approach: Test multiple payment data cases
        $testCases = $this->generatePaymentDataCases();

        foreach ($testCases as $case) {
            // Act: Encrypt payment data using TelebirrService
            $service = new TelebirrService();
            $reflection = new \ReflectionClass($service);
            $method = $reflection->getMethod('encryptPayload');
            $method->setAccessible(true);

            $paymentData = json_encode($case['payment_data']);
            $encrypted = $method->invoke($service, $paymentData);

            // Assert: Encryption should succeed and produce non-empty result
            $this->assertNotEmpty($encrypted, 
                "Encryption should succeed for payment data: {$case['description']}");
            $this->assertIsString($encrypted);
            
            // Verify it's base64 encoded
            $decoded = base64_decode($encrypted, true);
            $this->assertNotFalse($decoded, "Encrypted data should be valid base64");
        }
    }

    /**
     * Property 4.2: Payment initiation flow completes successfully
     * 
     * **Validates: Requirements 3.5, 3.6**
     * 
     * For all valid payment requests, the payment initiation flow SHALL
     * continue to function correctly with proper encryption.
     */
    public function test_property_payment_initiation_flow_succeeds(): void
    {
        // Arrange: Set up test configuration
        $keyPair = $this->generateTestKeyPair();
        config([
            'telebirr.public_key' => $keyPair['public_key_base64'],
            'telebirr.app_id' => 'TEST_APP_ID',
            'telebirr.app_key' => 'TEST_APP_KEY',
            'telebirr.short_code' => '123456',
            'telebirr.notify_url' => 'https://example.com/notify',
            'telebirr.return_url' => 'https://example.com/return',
            'telebirr.api_url' => 'https://api.telebirr.com/payment',
        ]);

        // Property-based approach: Test multiple payment scenarios
        $testCases = $this->generatePaymentInitiationCases();

        foreach ($testCases as $case) {
            // Arrange: Create payment record
            $user = User::factory()->create();
            $course = Course::factory()->create(['title' => $case['course_title']]);
            $payment = Payment::factory()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'amount' => $case['amount'],
                'transaction_ref' => 'TEST_' . uniqid(),
            ]);

            // Act: Initiate payment (will fail to connect to API, but encryption should work)
            $service = new TelebirrService();
            
            // We're testing that the encryption step works, not the API call
            // So we'll test the encryptPayload method directly
            $reflection = new \ReflectionClass($service);
            $method = $reflection->getMethod('encryptPayload');
            $method->setAccessible(true);

            $params = [
                'appId' => config('telebirr.app_id'),
                'outTradeNo' => $payment->transaction_ref,
                'totalAmount' => number_format((float) $payment->amount, 2, '.', ''),
                'subject' => 'Course Payment - ' . $course->title,
            ];

            $encrypted = $method->invoke($service, json_encode($params));

            // Assert: Encryption should succeed
            $this->assertNotEmpty($encrypted, 
                "Payment initiation encryption should succeed for amount {$case['amount']}");
            $this->assertIsString($encrypted);
        }
    }

    // ========== Test Case Generators ==========

    /**
     * Generate test cases for first-time enrollment property
     */
    private function generateFirstTimeEnrollmentCases(): array
    {
        // Generate 5 test cases
        return [
            ['case' => 1],
            ['case' => 2],
            ['case' => 3],
            ['case' => 4],
            ['case' => 5],
        ];
    }

    /**
     * Generate test cases for different course enrollment property
     */
    private function generateDifferentCourseEnrollmentCases(): array
    {
        return [
            ['course_count' => 2],
            ['course_count' => 3],
            ['course_count' => 4],
        ];
    }

    /**
     * Generate test cases for different user enrollment property
     */
    private function generateDifferentUserEnrollmentCases(): array
    {
        return [
            ['user_count' => 2],
            ['user_count' => 3],
            ['user_count' => 4],
        ];
    }

    /**
     * Generate test cases for payment data encryption property
     */
    private function generatePaymentDataCases(): array
    {
        return [
            [
                'description' => 'Small payment',
                'payment_data' => [
                    'outTradeNo' => 'TEST001',
                    'totalAmount' => '10.00',
                    'subject' => 'Test Course',
                ],
            ],
            [
                'description' => 'Large payment',
                'payment_data' => [
                    'outTradeNo' => 'TEST002',
                    'totalAmount' => '999.99',
                    'subject' => 'Premium Course',
                ],
            ],
            [
                'description' => 'Payment with special characters',
                'payment_data' => [
                    'outTradeNo' => 'TEST003',
                    'totalAmount' => '50.50',
                    'subject' => 'Course: Advanced PHP & Laravel',
                ],
            ],
            [
                'description' => 'Payment with long subject',
                'payment_data' => [
                    'outTradeNo' => 'TEST004',
                    'totalAmount' => '75.00',
                    'subject' => 'This is a very long course title that contains many words and characters to test encryption with larger payloads',
                ],
            ],
        ];
    }

    /**
     * Generate test cases for payment initiation property
     */
    private function generatePaymentInitiationCases(): array
    {
        return [
            ['course_title' => 'Basic PHP', 'amount' => '25.00'],
            ['course_title' => 'Advanced Laravel', 'amount' => '99.99'],
            ['course_title' => 'Web Development Bootcamp', 'amount' => '199.00'],
            ['course_title' => 'Database Design', 'amount' => '49.50'],
        ];
    }

    // ========== Helper Methods ==========

    /**
     * Generate a test RSA key pair for testing
     */
    private function generateTestKeyPair(): array
    {
        $config = [
            'private_key_bits' => 1024,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $privateKey);
        $publicKey = openssl_pkey_get_details($res)['key'];

        $privateKeyBase64 = $this->extractKeyBase64($privateKey);
        $publicKeyBase64 = $this->extractKeyBase64($publicKey);

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
            'private_key_base64' => $privateKeyBase64,
            'public_key_base64' => $publicKeyBase64,
        ];
    }

    /**
     * Extract base64 content from PEM key
     */
    private function extractKeyBase64(string $pemKey): string
    {
        $lines = explode("\n", $pemKey);
        $base64 = '';
        foreach ($lines as $line) {
            if (strpos($line, '-----') === false) {
                $base64 .= trim($line);
            }
        }
        return $base64;
    }
}
