<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\TelebirrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug Condition Exploration Tests
 * 
 * These tests are designed to FAIL on unfixed code to demonstrate the bugs exist.
 * When they fail, it confirms the bugs are present.
 * After fixes are applied, these same tests should PASS.
 */
class BugConditionExplorationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bug Condition 1: Duplicate Enrollment Test
     * 
     * **Validates: Requirements 2.1, 2.2**
     * 
     * This test attempts to create duplicate enrollments (same user_id and course_id).
     * 
     * EXPECTED BEHAVIOR (after fix):
     * - First enrollment should succeed
     * - Second enrollment should fail with unique constraint violation (SQLSTATE[23505])
     * 
     * CURRENT BEHAVIOR (unfixed):
     * - Both enrollments succeed (bug exists)
     * - No constraint violation is thrown
     * 
     * COUNTEREXAMPLE: When this test fails, it proves duplicate enrollments are allowed.
     */
    public function test_duplicate_enrollment_should_be_rejected(): void
    {
        // Arrange: Create a user and course
        $user = User::factory()->create();
        $course = Course::factory()->create();

        // Act: Create first enrollment (should always succeed)
        $firstEnrollment = Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'progress' => 0,
        ]);

        $this->assertNotNull($firstEnrollment->id, 'First enrollment should be created successfully');

        // Assert: Attempting to create duplicate enrollment should throw constraint violation
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->expectExceptionMessage('SQLSTATE[23505]'); // Unique violation error code

        // Act: Attempt to create duplicate enrollment (should fail with constraint violation)
        Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'progress' => 0,
        ]);

        // If we reach here without exception, the bug exists (no unique constraint)
    }

    /**
     * Bug Condition 2: Webhook Decryption Test
     * 
     * **Validates: Requirements 2.3, 2.4**
     * 
     * This test attempts to decrypt a webhook payload using the current implementation.
     * 
     * EXPECTED BEHAVIOR (after fix):
     * - Decryption should succeed using private key
     * - Webhook verification should return valid=true
     * 
     * CURRENT BEHAVIOR (unfixed):
     * - Decryption fails because it uses public key instead of private key
     * - openssl_public_decrypt() cannot decrypt data encrypted with public key
     * 
     * COUNTEREXAMPLE: When this test fails, it proves decryption is using wrong key.
     */
    public function test_webhook_decryption_should_succeed(): void
    {
        // Arrange: Set up test keys (simulating Telebirr's encryption)
        // Generate a test key pair for this test
        $keyPair = $this->generateTestKeyPair();
        
        // Set the public key in config (this is what the current buggy code uses)
        config(['telebirr.public_key' => $keyPair['public_key_base64']]);
        
        // Set the private key in config (this is what the fixed code should use)
        config(['telebirr.private_key' => $keyPair['private_key_base64']]);

        // Create test webhook data
        $webhookPayload = [
            'outTradeNo' => 'TEST123456',
            'tradeNo' => 'TELEBIRR789',
            'totalAmount' => '100.00',
            'tradeStatus' => 'SUCCESS',
        ];

        // Encrypt the payload using the public key (simulating Telebirr's encryption)
        $encryptedData = $this->encryptWithPublicKey(
            json_encode($webhookPayload),
            $keyPair['public_key']
        );

        // Act: Attempt to verify the webhook using TelebirrService
        $service = new TelebirrService();
        $result = $service->verifyWebhook(['notification' => $encryptedData]);

        // Assert: Webhook verification should succeed
        $this->assertTrue($result['valid'], 'Webhook verification should succeed with correct decryption');
        $this->assertEquals('TEST123456', $result['out_trade_no']);
        $this->assertEquals('SUCCESS', $result['transaction_status']);

        // If this assertion fails, it proves the decryption is using the wrong key
    }

    /**
     * Helper: Generate a test RSA key pair for testing
     */
    private function generateTestKeyPair(): array
    {
        $config = [
            'private_key_bits' => 1024, // Smaller key for faster test execution
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $privateKey);
        $publicKey = openssl_pkey_get_details($res)['key'];

        // Extract base64 versions (without headers/footers)
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
     * Helper: Extract base64 content from PEM key (remove headers/footers)
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

    /**
     * Helper: Encrypt data with public key (simulating Telebirr's encryption)
     */
    private function encryptWithPublicKey(string $data, string $publicKey): string
    {
        $encrypted = '';
        $dataChunks = str_split($data, 117); // RSA 1024-bit can encrypt up to 117 bytes

        foreach ($dataChunks as $chunk) {
            $encryptedChunk = '';
            openssl_public_encrypt($chunk, $encryptedChunk, $publicKey);
            $encrypted .= $encryptedChunk;
        }

        return base64_encode($encrypted);
    }
}
