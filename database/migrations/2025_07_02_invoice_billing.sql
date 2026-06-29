-- Migration: invoice billing enhancements
-- Run once against your rentops database

-- Add billing config fields to properties
ALTER TABLE `properties`
    ADD COLUMN IF NOT EXISTS `eb_unit_price`      DECIMAL(8,2) NOT NULL DEFAULT 0     COMMENT 'Price per EB unit (₹)',
    ADD COLUMN IF NOT EXISTS `rent_gst_rate`       DECIMAL(5,2) NOT NULL DEFAULT 18.00 COMMENT 'GST % on rent',
    ADD COLUMN IF NOT EXISTS `razorpay_key_id`     VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `razorpay_key_secret` VARCHAR(200) DEFAULT NULL COMMENT 'AES-256-CBC encrypted';

-- Add line-item columns to rent_invoices
ALTER TABLE `rent_invoices`
    ADD COLUMN IF NOT EXISTS `eb_units`           DECIMAL(8,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `eb_amount`          DECIMAL(10,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `rent_gst`           DECIMAL(10,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `other_charges`      DECIMAL(10,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `other_charges_desc` VARCHAR(255)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `notes`              TEXT          DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `overpayment`        DECIMAL(10,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `pdf_path`           VARCHAR(500)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `razorpay_link`      VARCHAR(500)  DEFAULT NULL;
