-- Migration: 008_terms.sql
-- Description: When each person agreed to the Terms and Privacy Policy, and which version.
-- Accounts made before sign-up asked for it stay NULL.

ALTER TABLE users
    ADD COLUMN terms_accepted_at DATETIME NULL AFTER email_verified_at,
    ADD COLUMN terms_version VARCHAR(16) NULL AFTER terms_accepted_at;
