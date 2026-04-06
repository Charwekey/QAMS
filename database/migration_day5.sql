-- ============================================================
-- Day 5 Migration: Add missing columns and fix schema gaps
-- ============================================================
USE `qams_db`;

-- Add missing columns to recommendations table
ALTER TABLE `recommendations`
    ADD COLUMN `priority` ENUM('low','medium','high','critical') DEFAULT 'medium' AFTER `description`,
    ADD COLUMN `director_response` TEXT DEFAULT NULL AFTER `status`,
    ADD COLUMN `reviewed_at` TIMESTAMP NULL DEFAULT NULL AFTER `director_response`;

-- Update status column to support 'pending' and 'reviewed'
ALTER TABLE `recommendations`
    MODIFY COLUMN `status` VARCHAR(20) DEFAULT 'pending';

-- Update existing records to use 'pending' status
UPDATE `recommendations` SET `status` = 'pending' WHERE `status` = 'submitted';

-- Add phone column to users table if it doesn't exist
ALTER TABLE `users`
    ADD COLUMN `phone` VARCHAR(30) DEFAULT NULL AFTER `designation`;

-- Add session_id to facility_issues if missing
-- (Some pages reference this column)
-- ALTER TABLE `facility_issues` ADD COLUMN `session_id` INT DEFAULT NULL;
