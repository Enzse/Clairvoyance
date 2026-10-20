-- Migration: 007_unit_group.sql
-- Description: Marks units made as groups. An empty group is waiting for units to be moved in
-- and doesn't report figures, whatever term it is given. Units already called "Group" are marked.

ALTER TABLE units ADD COLUMN is_group TINYINT(1) NOT NULL DEFAULT 0 AFTER icon;
UPDATE units SET is_group = 1 WHERE LOWER(kind) = 'group';
