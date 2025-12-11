-- Migration: Add onsite field to time_entries table
-- Description: Adds a boolean onsite column to track whether time entries were completed onsite or remotely
-- Date: 2025-12-11

ALTER TABLE `time_entries` ADD COLUMN `onsite` tinyint(1) NOT NULL DEFAULT '0' AFTER `invoiced`;
