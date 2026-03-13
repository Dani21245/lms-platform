# Bugfix Requirements Document

## Introduction

This document addresses two critical bugs in the LMS platform that affect core payment and enrollment functionality:

1. **Enrollment Constraint Bug**: The enrollments table lacks a unique constraint on (user_id, course_id), allowing students to enroll multiple times in the same course, causing duplicate enrollments and potential payment issues.

2. **Telebirr Decryption Error**: The TelebirrService incorrectly uses the public key for decryption in the decryptPayload() method, when it should use a private key, breaking webhook verification for payment notifications.

These bugs are blocking issues that must be resolved to ensure data integrity and payment processing reliability.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN a student attempts to enroll in a course they are already enrolled in THEN the system allows the duplicate enrollment to be created in the database

1.2 WHEN multiple enrollment requests are submitted for the same user and course combination THEN the system creates multiple enrollment records with identical user_id and course_id values

1.3 WHEN the TelebirrService receives a webhook notification and calls decryptPayload() THEN the system attempts to decrypt using the public key instead of the private key

1.4 WHEN decryptPayload() uses the public key for decryption THEN the decryption operation fails and webhook verification cannot be completed

### Expected Behavior (Correct)

2.1 WHEN a student attempts to enroll in a course they are already enrolled in THEN the system SHALL reject the duplicate enrollment with a constraint violation error

2.2 WHEN multiple enrollment requests are submitted for the same user and course combination THEN the system SHALL enforce uniqueness and prevent duplicate records from being created

2.3 WHEN the TelebirrService receives a webhook notification and calls decryptPayload() THEN the system SHALL use the private key for decryption

2.4 WHEN decryptPayload() uses the private key for decryption THEN the decryption operation SHALL succeed and webhook verification SHALL complete successfully

### Unchanged Behavior (Regression Prevention)

3.1 WHEN a student enrolls in a course for the first time THEN the system SHALL CONTINUE TO create the enrollment record successfully

3.2 WHEN a student enrolls in different courses THEN the system SHALL CONTINUE TO allow multiple enrollment records for the same user with different course_id values

3.3 WHEN different students enroll in the same course THEN the system SHALL CONTINUE TO allow multiple enrollment records for the same course with different user_id values

3.4 WHEN the TelebirrService encrypts data using encryptPayload() THEN the system SHALL CONTINUE TO use the public key for encryption

3.5 WHEN the TelebirrService processes valid webhook notifications THEN the system SHALL CONTINUE TO handle payment confirmations correctly after successful decryption

3.6 WHEN the TelebirrService interacts with other Telebirr API endpoints THEN the system SHALL CONTINUE TO function correctly with proper key usage
