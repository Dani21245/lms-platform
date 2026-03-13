# Critical Bugs (Enrollment & Telebirr) Bugfix Design

## Overview

This design addresses two critical bugs in the LMS platform:

1. **Enrollment Constraint Bug**: The enrollments table has a unique constraint defined in the migration file, but it may not exist in the production database. This allows duplicate enrollments (same user_id and course_id) to be created, causing data integrity issues and potential payment problems.

2. **Telebirr Decryption Error**: The TelebirrService.decryptPayload() method incorrectly uses openssl_public_decrypt() with the public key, when asymmetric decryption requires openssl_private_decrypt() with a private key. This breaks webhook verification for payment notifications from Telebirr.

The fix strategy involves: (1) creating a migration to ensure the unique constraint exists in the database, and (2) updating TelebirrService to use the private key for decryption while maintaining public key usage for encryption.

## Glossary

- **Bug_Condition_1 (C1)**: The condition where duplicate enrollments can be created - when the unique constraint is missing from the database
- **Bug_Condition_2 (C2)**: The condition where decryption fails - when decryptPayload() uses the public key instead of private key
- **Property_1 (P1)**: The desired behavior for enrollments - duplicate (user_id, course_id) pairs should be rejected with a constraint violation
- **Property_2 (P2)**: The desired behavior for decryption - webhook payloads should decrypt successfully using the private key
- **Preservation**: Existing enrollment creation, encryption, and payment processing that must remain unchanged
- **Enrollment**: A record in the enrollments table linking a user to a course they are taking
- **TelebirrService**: The service class in `app/Services/TelebirrService.php` that handles payment integration with Telebirr API
- **decryptPayload()**: The private method that decrypts webhook notifications from Telebirr
- **encryptPayload()**: The private method that encrypts payment request data sent to Telebirr
- **Asymmetric Encryption**: Encryption using public/private key pairs where public key encrypts and private key decrypts

## Bug Details

### Bug Condition 1: Missing Unique Constraint

The bug manifests when the enrollments table in the production database lacks the unique constraint on (user_id, course_id), even though the migration file defines it. This can occur if the migration was run before the constraint was added to the file, or if the database was not migrated fresh after the constraint was added.

**Formal Specification:**
```
FUNCTION isBugCondition1(enrollmentRequest)
  INPUT: enrollmentRequest of type {user_id: integer, course_id: integer}
  OUTPUT: boolean
  
  RETURN existingEnrollment(enrollmentRequest.user_id, enrollmentRequest.course_id) EXISTS
         AND uniqueConstraint('enrollments', ['user_id', 'course_id']) NOT EXISTS IN DATABASE
         AND enrollmentCreation(enrollmentRequest) SUCCEEDS
END FUNCTION
```

### Bug Condition 2: Incorrect Decryption Key Usage

The bug manifests when the TelebirrService receives a webhook notification from Telebirr. The decryptPayload() method uses openssl_public_decrypt() with the public key, but asymmetric decryption requires the private key. Public keys can only encrypt data; private keys are needed to decrypt it.

**Formal Specification:**
```
FUNCTION isBugCondition2(webhookData)
  INPUT: webhookData of type {notification: string (encrypted)}
  OUTPUT: boolean
  
  RETURN webhookData.notification IS_ENCRYPTED
         AND decryptPayload() USES openssl_public_decrypt()
         AND decryptPayload() USES publicKey
         AND decryption FAILS
END FUNCTION
```

### Examples

**Bug 1 Examples:**
- Student with user_id=5 enrolls in course_id=10 successfully
- Same student attempts to enroll in course_id=10 again
- Without the constraint: Second enrollment is created (duplicate record)
- With the constraint: Second enrollment is rejected with "SQLSTATE[23505]: Unique violation"

**Bug 2 Examples:**
- Telebirr sends webhook notification with encrypted payment confirmation
- decryptPayload() attempts: openssl_public_decrypt($chunk, $decryptedChunk, $publicKey)
- Decryption fails because public keys cannot decrypt data
- Webhook verification returns ['valid' => false, 'message' => 'decryption error']
- Expected: openssl_private_decrypt($chunk, $decryptedChunk, $privateKey) should succeed

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- First-time enrollments for any user-course combination must continue to work
- Students enrolling in different courses must continue to work
- Different students enrolling in the same course must continue to work
- TelebirrService.encryptPayload() must continue using the public key for encryption
- Payment initiation flow must continue to work correctly
- All other Telebirr API interactions must continue to function

**Scope:**
All inputs that do NOT involve duplicate enrollments or webhook decryption should be completely unaffected by this fix. This includes:
- Valid enrollment creation (first enrollment for a user-course pair)
- Payment initiation and encryption
- Other database operations on enrollments table
- Telebirr API calls that don't involve decryption

## Hypothesized Root Cause

### Bug 1: Enrollment Constraint

Based on the bug description and code analysis, the root cause is:

1. **Migration-Database Mismatch**: The migration file at `database/migrations/2024_01_01_000007_create_enrollments_table.php` contains the unique constraint definition on line 15: `$table->unique(['user_id', 'course_id']);`, but the production database does not have this constraint applied.

2. **Possible Scenarios**:
   - The database was created before the constraint was added to the migration file
   - The migration was run, then rolled back, then the constraint was added, but not re-run
   - The database was manually created or modified without running migrations

3. **Impact**: Without the constraint in the database, Laravel's Eloquent ORM has no way to prevent duplicate enrollments at the database level, allowing multiple identical records.

### Bug 2: Telebirr Decryption

Based on the code analysis, the root causes are:

1. **Incorrect OpenSSL Function**: Line 163 in TelebirrService.php uses `openssl_public_decrypt()` which is designed to decrypt data that was encrypted with the corresponding private key (reverse of normal asymmetric encryption). For standard asymmetric encryption where the public key encrypts, `openssl_private_decrypt()` must be used.

2. **Wrong Key Type**: Line 159 loads the public key, but decryption requires the private key.

3. **Missing Configuration**: The config/telebirr.php file only defines `public_key`, but does not have a `private_key` configuration entry.

4. **Asymmetric Encryption Flow Misunderstanding**: 
   - Correct flow: Telebirr encrypts with their private key → We decrypt with Telebirr's public key (signature verification)
   - OR: Telebirr encrypts with our public key → We decrypt with our private key (confidential data)
   - Current implementation attempts: Decrypt with our public key (impossible)

## Correctness Properties

Property 1: Bug Condition 1 - Duplicate Enrollment Prevention

_For any_ enrollment request where a user attempts to enroll in a course they are already enrolled in (same user_id and course_id combination exists), the fixed database schema SHALL reject the duplicate enrollment with a unique constraint violation error (SQLSTATE[23505]).

**Validates: Requirements 2.1, 2.2**

Property 2: Bug Condition 2 - Webhook Decryption Success

_For any_ webhook notification received from Telebirr containing an encrypted payload, the fixed decryptPayload() method SHALL successfully decrypt the payload using the private key and openssl_private_decrypt(), allowing webhook verification to complete.

**Validates: Requirements 2.3, 2.4**

Property 3: Preservation - Valid Enrollment Creation

_For any_ enrollment request where the user is NOT already enrolled in the course (no existing user_id and course_id combination), the fixed database schema SHALL allow the enrollment to be created successfully, preserving the ability to create first-time enrollments.

**Validates: Requirements 3.1, 3.2, 3.3**

Property 4: Preservation - Encryption and Payment Flow

_For any_ payment initiation request, the fixed TelebirrService SHALL continue to use the public key with openssl_public_encrypt() for encrypting payment data, and all payment processing flows SHALL continue to function correctly.

**Validates: Requirements 3.4, 3.5, 3.6**

## Fix Implementation

### Changes Required

#### Fix 1: Enrollment Unique Constraint

**File**: `database/migrations/[timestamp]_add_unique_constraint_to_enrollments_table.php` (new migration)

**Purpose**: Ensure the unique constraint exists in the database, even if the original migration was run without it.

**Specific Changes**:
1. **Create New Migration**: Generate a new migration file that adds the unique constraint if it doesn't exist
   - Use `Schema::table('enrollments', ...)` to modify existing table
   - Add unique constraint: `$table->unique(['user_id', 'course_id'], 'enrollments_user_course_unique');`
   - Use named constraint for easier identification and management

2. **Handle Existing Data**: Before adding the constraint, remove any existing duplicate records
   - Query for duplicate (user_id, course_id) pairs
   - Keep the earliest enrollment (lowest id) and delete duplicates
   - Log the cleanup for audit purposes

3. **Down Method**: Implement rollback that drops the constraint
   - Use `$table->dropUnique('enrollments_user_course_unique');`

#### Fix 2: Telebirr Decryption Key

**File**: `config/telebirr.php`

**Specific Changes**:
1. **Add Private Key Configuration**: Add new configuration entry
   - Add line: `'private_key' => env('TELEBIRR_PRIVATE_KEY'),`
   - This allows the private key to be stored in .env file

**File**: `app/Services/TelebirrService.php`

**Function**: `decryptPayload()`

**Specific Changes**:
1. **Change Key Type**: Replace public key with private key (line 159)
   - FROM: `$publicKey = config('telebirr.public_key');`
   - TO: `$privateKey = config('telebirr.private_key');`

2. **Update Key Format**: Change the key wrapper to private key format (line 160)
   - FROM: `$key = "-----BEGIN PUBLIC KEY-----\n".wordwrap($publicKey, 64, "\n", true)."\n-----END PUBLIC KEY-----";`
   - TO: `$key = "-----BEGIN PRIVATE KEY-----\n".wordwrap($privateKey, 64, "\n", true)."\n-----END PRIVATE KEY-----";`

3. **Change Decryption Function**: Replace openssl_public_decrypt with openssl_private_decrypt (line 167)
   - FROM: `openssl_public_decrypt($chunk, $decryptedChunk, $key);`
   - TO: `openssl_private_decrypt($chunk, $decryptedChunk, $key);`

4. **Add Error Handling**: Add validation to ensure private key is configured
   - Check if private key exists before attempting decryption
   - Throw descriptive exception if missing

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate the bugs on unfixed code, then verify the fixes work correctly and preserve existing behavior.

### Exploratory Bug Condition Checking

**Goal**: Surface counterexamples that demonstrate both bugs BEFORE implementing the fixes. Confirm or refute the root cause analysis.

**Test Plan for Bug 1**: Write tests that attempt to create duplicate enrollments. Run these tests on the UNFIXED database schema to observe whether duplicates are allowed.

**Test Cases for Bug 1**:
1. **Duplicate Enrollment Test**: Create enrollment for user_id=1, course_id=1, then attempt to create another (will succeed on unfixed DB, should fail after fix)
2. **Multiple Duplicates Test**: Attempt to create 3 enrollments with same user_id and course_id (will create 3 records on unfixed DB)
3. **Different Course Test**: Create enrollment for user_id=1, course_id=1, then user_id=1, course_id=2 (should succeed both before and after fix)
4. **Different User Test**: Create enrollment for user_id=1, course_id=1, then user_id=2, course_id=1 (should succeed both before and after fix)

**Test Plan for Bug 2**: Write tests that simulate Telebirr webhook notifications with encrypted payloads. Run these tests on the UNFIXED code to observe decryption failures.

**Test Cases for Bug 2**:
1. **Webhook Decryption Test**: Create encrypted payload using public key, attempt to decrypt with current implementation (will fail on unfixed code)
2. **Webhook Verification Test**: Call verifyWebhook() with encrypted notification (will return ['valid' => false] on unfixed code)
3. **Encryption Test**: Verify encryptPayload() still works correctly (should pass both before and after fix)
4. **End-to-End Test**: Simulate full payment flow with webhook callback (will fail at webhook verification on unfixed code)

**Expected Counterexamples**:
- Bug 1: Duplicate enrollment records are created without constraint violation
- Bug 2: openssl_private_decrypt() fails with error "error:0200006E:rsa routines:RSA_padding_check_PKCS1_type_2:data too large for key size" or similar
- Possible causes: Missing database constraint, incorrect OpenSSL function, wrong key type

### Fix Checking

**Goal**: Verify that for all inputs where the bug conditions hold, the fixed code produces the expected behavior.

**Pseudocode for Bug 1:**
```
FOR ALL enrollmentRequest WHERE isBugCondition1(enrollmentRequest) DO
  result := createEnrollment_fixed(enrollmentRequest)
  ASSERT result.error IS UniqueConstraintViolation
  ASSERT result.success IS FALSE
END FOR
```

**Pseudocode for Bug 2:**
```
FOR ALL webhookData WHERE isBugCondition2(webhookData) DO
  result := decryptPayload_fixed(webhookData.notification)
  ASSERT result IS_DECRYPTED_SUCCESSFULLY
  ASSERT verifyWebhook(webhookData).valid IS TRUE
END FOR
```

### Preservation Checking

**Goal**: Verify that for all inputs where the bug conditions do NOT hold, the fixed code produces the same result as the original code.

**Pseudocode for Bug 1:**
```
FOR ALL enrollmentRequest WHERE NOT isBugCondition1(enrollmentRequest) DO
  ASSERT createEnrollment_original(enrollmentRequest).success = createEnrollment_fixed(enrollmentRequest).success
END FOR
```

**Pseudocode for Bug 2:**
```
FOR ALL paymentRequest WHERE requiresEncryption(paymentRequest) DO
  ASSERT encryptPayload_original(paymentRequest) = encryptPayload_fixed(paymentRequest)
END FOR
```

**Testing Approach**: Property-based testing is recommended for preservation checking because:
- It generates many test cases automatically across the input domain
- It catches edge cases that manual unit tests might miss
- It provides strong guarantees that behavior is unchanged for all non-buggy inputs

**Test Plan**: Observe behavior on UNFIXED code first for valid enrollments and encryption, then write property-based tests capturing that behavior.

**Test Cases for Preservation**:
1. **First Enrollment Preservation**: Observe that creating first enrollment works on unfixed code, verify it continues after fix
2. **Multiple Courses Preservation**: Observe that one user enrolling in multiple courses works, verify it continues after fix
3. **Multiple Users Preservation**: Observe that multiple users enrolling in one course works, verify it continues after fix
4. **Encryption Preservation**: Observe that encryptPayload() works correctly on unfixed code, verify it continues after fix
5. **Payment Initiation Preservation**: Observe that initiatePayment() works correctly, verify it continues after fix

### Unit Tests

**For Bug 1:**
- Test creating first enrollment succeeds
- Test creating duplicate enrollment fails with constraint violation
- Test creating enrollments for different courses succeeds
- Test creating enrollments for different users succeeds
- Test migration removes existing duplicates before adding constraint

**For Bug 2:**
- Test decryptPayload() with valid encrypted data succeeds
- Test decryptPayload() throws exception when private key is missing
- Test verifyWebhook() returns valid=true for correct webhook data
- Test verifyWebhook() returns valid=false for invalid webhook data
- Test encryptPayload() continues to work with public key

### Property-Based Tests

**For Bug 1:**
- Generate random user_id and course_id pairs, verify first enrollment always succeeds
- Generate random duplicate enrollment attempts, verify all are rejected
- Generate random combinations of users and courses, verify constraint only blocks exact duplicates

**For Bug 2:**
- Generate random payment data, encrypt with public key, verify decryption with private key succeeds
- Generate random webhook payloads, verify all decrypt correctly
- Generate random payment requests, verify encryption continues to work

### Integration Tests

**For Bug 1:**
- Test full enrollment flow from API request to database insertion
- Test enrollment with payment processing doesn't create duplicates
- Test concurrent enrollment requests for same user-course pair

**For Bug 2:**
- Test full payment flow from initiation through webhook callback
- Test webhook processing updates payment status correctly
- Test failed decryption is logged and handled gracefully
