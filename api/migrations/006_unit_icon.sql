-- Migration: 006_unit_icon.sql
-- Description: An icon chosen for each unit (a name from the web app's icon set, e.g.
-- "store"). NULL shows the default: a building for the organisation, a folder for groups,
-- a pin for the units that report.

ALTER TABLE units ADD COLUMN icon VARCHAR(32) NULL AFTER kind;
