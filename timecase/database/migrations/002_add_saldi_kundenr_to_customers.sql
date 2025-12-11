-- Migration: Add saldi_kundenr field to customers table
-- Description: Adds a varchar(20) saldi_kundenr column to track customer SALDi customer number
-- Date: 2025-12-11

ALTER TABLE `customers` ADD COLUMN `saldi_kundenr` varchar(20) DEFAULT NULL AFTER `description`;
