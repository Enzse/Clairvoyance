-- Migration: 005_setup_skips.sql
-- Description: Getting-started steps an organisation chose to skip (e.g. "targets" for KPIs
-- tracked without one), as a comma-separated list of step keys.

ALTER TABLE organizations ADD COLUMN setup_skipped VARCHAR(255) NOT NULL DEFAULT '' AFTER setup_dismissed_at;
