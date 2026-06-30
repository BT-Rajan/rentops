-- Migration: scheduled move-out support
-- Run once against your rentops database

ALTER TABLE `tenancies`
    ADD COLUMN IF NOT EXISTS `scheduled_move_out_date` DATE          DEFAULT NULL COMMENT 'Future move-out date — not yet executed',
    ADD COLUMN IF NOT EXISTS `scheduled_deduction`      DECIMAL(10,2) DEFAULT NULL COMMENT 'Deposit deduction to apply when the scheduled move-out executes';
