-- ============================================================
-- Day 6 Migration: Lecturer Workflow Refinement
-- ============================================================
USE `qams_db`;

-- Modify submission_files file_type ENUM to include new types
ALTER TABLE `submission_files`
    MODIFY COLUMN `file_type` ENUM('attendance','midterm_question','final_question','course_outline','assignment','presentation','course_coverage') NOT NULL;

-- Add revert_requested column to submissions table
ALTER TABLE `submissions`
    ADD COLUMN `revert_requested` TINYINT(1) DEFAULT 0 AFTER `status`;
