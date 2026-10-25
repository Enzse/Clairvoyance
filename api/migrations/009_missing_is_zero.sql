-- Migration: 009_missing_is_zero.sql
-- Description: Per KPI, whether a day, week or month with no figure counts as 0 (sales,
-- complaints) rather than being left out (satisfaction, headcount).

ALTER TABLE kpis ADD COLUMN missing_is_zero TINYINT(1) NOT NULL DEFAULT 0 AFTER tolerance_pct;
