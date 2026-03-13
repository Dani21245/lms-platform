# Implementation Plan

- [x] 1. Write bug condition exploration tests
  - **Property 1: Bug Condition** - Duplicate Enrollment and Decryption Failure
  - **CRITICAL**: These tests MUST FAIL on unfixed code - failure confirms the bugs exist
  - **DO NOT attempt to fix the tests or the code when they fail**
  - **NOTE**: These tests encode the expected behavior - they will validate the fixes when they pass after implementation
  - **GOAL**: Surface counterexamples that demonstrate both bugs exist
  - **Scoped PBT Approach**: Scope properties to concrete failing cases for reproducibility
  - Test Bug 1: Attempt to create duplicate enrollment (user_id=1, course_id=1) twice - should be rejected but currently succeeds
  - Test Bug 2: Attempt to decrypt webhook payload using current implementation - should succeed but currently fails
  - The test assertions should match the Expected Behavior Properties from design (Properties 1 and 2)
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests FAIL (this is correct - it proves the bugs exist)
  - Document counterexamples found:
    - Bug 1: Duplicate enrollment record created without constraint violation
    - Bug 2: openssl_public_decrypt() fails with decryption error
  - Mark task complete when tests are written, run, and failures are documented
  - _Requirements: 2.1, 2.2, 2.3, 2.4_

- [x] 2. Write preservation property tests (BEFORE implementing fixes)
  - **Property 2: Preservation** - Valid Enrollments and Encryption
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code for non-buggy inputs:
    - First-time enrollment creation (user_id=1, course_id=1) succeeds
    - Different course enrollment (user_id=1, course_id=2) succeeds
    - Different user enrollment (user_id=2, course_id=1) succeeds
    - encryptPayload() with payment data succeeds
    - Payment initiation flow completes successfully
  - Write property-based tests capturing observed behavior patterns from Preservation Requirements (Properties 3 and 4)
  - Property-based testing generates many test cases for stronger guarantees
  - Test: For all non-duplicate enrollment requests, enrollment creation succeeds
  - Test: For all payment requests, encryption with public key succeeds
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_

- [x] 3. Fix for duplicate enrollment constraint and Telebirr decryption

  - [x] 3.1 Create migration to add unique constraint to enrollments table
    - Generate new migration: `php artisan make:migration add_unique_constraint_to_enrollments_table`
    - In up() method: Remove existing duplicates before adding constraint
    - Query for duplicate (user_id, course_id) pairs
    - Keep earliest enrollment (lowest id), delete duplicates
    - Add unique constraint: `$table->unique(['user_id', 'course_id'], 'enrollments_user_course_unique');`
    - In down() method: Drop constraint using `$table->dropUnique('enrollments_user_course_unique');`
    - _Bug_Condition: isBugCondition1(enrollmentRequest) where duplicate (user_id, course_id) exists and constraint is missing_
    - _Expected_Behavior: Duplicate enrollments rejected with SQLSTATE[23505] (Property 1)_
    - _Preservation: First-time enrollments, different courses, different users continue to work (Property 3)_
    - _Requirements: 2.1, 2.2, 3.1, 3.2, 3.3_

  - [x] 3.2 Add private key configuration to config/telebirr.php
    - Add new configuration entry: `'private_key' => env('TELEBIRR_PRIVATE_KEY'),`
    - This allows private key to be stored in .env file
    - _Requirements: 2.3, 2.4_

  - [x] 3.3 Update TelebirrService.decryptPayload() to use private key
    - Change line 159: FROM `$publicKey = config('telebirr.public_key');` TO `$privateKey = config('telebirr.private_key');`
    - Change line 160: FROM `"-----BEGIN PUBLIC KEY-----\n"` TO `"-----BEGIN PRIVATE KEY-----\n"` and FROM `"-----END PUBLIC KEY-----"` TO `"-----END PRIVATE KEY-----"`
    - Change line 167: FROM `openssl_public_decrypt($chunk, $decryptedChunk, $key);` TO `openssl_private_decrypt($chunk, $decryptedChunk, $key);`
    - Add validation: Check if private key exists before attempting decryption, throw descriptive exception if missing
    - _Bug_Condition: isBugCondition2(webhookData) where webhook is encrypted and decryptPayload() uses public key_
    - _Expected_Behavior: Webhook payloads decrypt successfully using private key (Property 2)_
    - _Preservation: encryptPayload() continues using public key, payment flow unchanged (Property 4)_
    - _Requirements: 2.3, 2.4, 3.4, 3.5, 3.6_

  - [x] 3.4 Update .env.example with TELEBIRR_PRIVATE_KEY
    - Add line: `TELEBIRR_PRIVATE_KEY=your_private_key_here`
    - Add comment explaining this is required for webhook decryption
    - _Requirements: 2.3, 2.4_

  - [x] 3.5 Verify bug condition exploration tests now pass
    - **Property 1: Expected Behavior** - Duplicate Rejection and Decryption Success
    - **IMPORTANT**: Re-run the SAME tests from task 1 - do NOT write new tests
    - The tests from task 1 encode the expected behavior
    - When these tests pass, it confirms the expected behavior is satisfied
    - Run bug condition exploration tests from step 1
    - **EXPECTED OUTCOME**: Tests PASS (confirms bugs are fixed)
    - Verify Bug 1 test: Duplicate enrollment now rejected with constraint violation
    - Verify Bug 2 test: Webhook payload now decrypts successfully
    - _Requirements: 2.1, 2.2, 2.3, 2.4_

  - [x] 3.6 Verify preservation tests still pass
    - **Property 2: Preservation** - Valid Enrollments and Encryption
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Run preservation property tests from step 2
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm first-time enrollments still work
    - Confirm different course/user enrollments still work
    - Confirm encryption and payment flow still work
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_

- [x] 4. Checkpoint - Ensure all tests pass
  - Run full test suite: `php artisan test`
  - Verify all bug condition tests pass (duplicates rejected, decryption works)
  - Verify all preservation tests pass (valid enrollments work, encryption works)
  - Verify no regressions in other parts of the application
  - Ask the user if questions arise
