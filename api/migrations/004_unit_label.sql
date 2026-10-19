-- Migration: 004_unit_label.sql
-- Description: The word an organisation uses for the parts that report figures ("branch",
-- "department", "team"), so the app can say "2 of 3 branches reported" instead of "units".
-- Stored singular and lower case; the web app makes the plural.

ALTER TABLE organizations ADD COLUMN unit_label VARCHAR(40) NOT NULL DEFAULT 'team' AFTER name;

-- Existing organisations: the most common kind among the units that report (the leaves)
UPDATE organizations o
SET unit_label = COALESCE((
    SELECT LOWER(u.kind) FROM units u
    WHERE u.organization_id = o.id AND u.kind IS NOT NULL AND u.kind <> ''
      AND NOT EXISTS (SELECT 1 FROM units c WHERE c.parent_id = u.id)
    GROUP BY LOWER(u.kind) ORDER BY COUNT(*) DESC LIMIT 1
), 'team');
