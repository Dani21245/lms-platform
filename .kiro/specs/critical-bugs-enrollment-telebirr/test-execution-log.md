# Bug Condition Exploration Test Execution Log

## Test Execution Date
2026-03-09

## Environment
- Database: PostgreSQL 15
- PHP: 8.1.34
- Laravel Framework
- Docker containers

## Test Results Summary

### Bug 1: Duplicate Enrollment Test
**Test Name**: `test_duplicate_enrollment_should_be_rejected`
**Status**: ✅ PASSED (UNEXPECTED)
**Expected Outcome**: FAIL (to prove bug exists)
**Actual Outcome**: PASS (constraint violation was thrown)

**Analysis**:
The test PASSED, which means the unique constraint on (user_id, course_id) IS working correctly in the test database. When attempting to create a duplicate enrollment, the database threw a `QueryException` with `SQLSTATE[23505]` (unique violation), which is the expected behavior AFTER the fix.

**Database Verification**:
```sql
\d enrollments
```
Shows: `"enrollments_user_id_course_id_unique" UNIQUE CONSTRAINT, btree (user_id, course_id)`

**Conclusion**: 
The unique constraint EXISTS in the current database. This suggests one of the following:
1. The bug description refers to a production database issue that doesn't exist in the development/test environment
2. The migration was run correctly in this environment
3. The root cause analysis may need revision - the bug might not be about a missing constraint but about something else

**Recommendation**: 
This is an UNEXPECTED_PASS scenario. The test was designed to fail on unfixed code, but it passed, indicating the constraint is already in place.

---

### Bug 2: Webhook Decryption Test
**Test Name**: `test_webhook_decryption_should_succeed`
**Status**: ❌ FAILED (EXPECTED)
**Expected Outcome**: FAIL (to prove bug exists)
**Actual Outcome**: FAIL (webhook verification returned false)

**Error Details**:
```
Failed asserting that false is true.
/var/www/tests/Feature/BugConditionExplorationTest.php:117
```

**Analysis**:
The test FAILED as expected, confirming that Bug 2 exists. The webhook verification returned `['valid' => false]`, which means the decryption is failing. This aligns with the bug description that states `decryptPayload()` is using `openssl_public_decrypt()` with the public key instead of `openssl_private_decrypt()` with the private key.

**Code Verification**:
In `app/Services/TelebirrService.php`:
- Line 159: Uses `config('telebirr.public_key')`
- Line 160: Wraps key as `BEGIN PUBLIC KEY`
- Line 167: Uses `openssl_public_decrypt()` 

This confirms the root cause: the code is attempting to decrypt with a public key, which is cryptographically incorrect for standard asymmetric encryption.

**Counterexample**:
When encrypting data with a public key (as Telebirr would do), decryption MUST use the corresponding private key. The current implementation attempts to use `openssl_public_decrypt()` with a public key, which fails.

**Conclusion**: 
Bug 2 is CONFIRMED. The decryption logic is incorrect and needs to be fixed as described in the design document.

---

## Summary

| Bug | Test Status | Bug Exists? | Action Required |
|-----|-------------|-------------|-----------------|
| Bug 1: Duplicate Enrollment | PASSED (Unexpected) | NO - Constraint exists | Re-investigate or skip |
| Bug 2: Webhook Decryption | FAILED (Expected) | YES - Confirmed | Proceed with fix |

---

# Preservation Property Test Execution Log

## Test Execution Date
2026-03-13

## Test Results Summary

### Preservation Tests on Unfixed Code
**Test Suite**: `PreservationPropertyTest`
**Status**: ✅ ALL PASSED (EXPECTED)
**Purpose**: Establish baseline behavior that must be preserved after implementing fixes

**Test Results**:
1. ✅ `test_property_first_time_enrollment_succeeds` - 2.27s (54 assertions)
2. ✅ `test_property_different_course_enrollments_succeed` - 0.14s
3. ✅ `test_property_different_user_enrollments_succeed` - 0.10s
4. ✅ `test_property_encryption_with_public_key_succeeds` - 0.07s
5. ✅ `test_property_payment_initiation_flow_succeeds` - 0.10s

**Total Duration**: 2.88s
**Total Assertions**: 54

**Analysis**:
All preservation tests PASSED on the unfixed code, which is the expected outcome. These tests validate:
- Valid first-time enrollments work correctly
- Users can enroll in different courses
- Different users can enroll in the same course
- Encryption with public key succeeds (for outgoing data)
- Payment initiation flow works end-to-end

**Conclusion**:
The baseline behavior is confirmed and documented. When implementing fixes for Bug 1 (duplicate enrollment) and Bug 2 (webhook decryption), these tests must continue to pass to ensure no regression in valid functionality.

---

## Next Steps

**For Bug 1**: 
The test passed unexpectedly, indicating the unique constraint is already in place. Options:
1. **Continue anyway**: Implement remaining tasks (the migration will be idempotent and won't cause issues)
2. **Re-investigate**: Check if the bug exists in a different way or if the root cause is different
3. **Skip Bug 1**: Focus only on Bug 2 since Bug 1 doesn't appear to exist in this environment

**For Bug 2**:
Proceed with the fix as planned in Task 3 - update TelebirrService to use private key for decryption.

**Preservation Tests**:
✅ Task 2 Complete - All preservation tests pass on unfixed code, baseline behavior established.
