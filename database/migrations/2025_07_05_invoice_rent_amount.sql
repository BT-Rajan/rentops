-- Store the actual rent amount used when generating each invoice.
-- Previously only amount_due (rent+gst+eb+other) was stored; this column
-- lets any view reconstruct rent without math that can't account for
-- mid-period pro-ration, rent changes, or compound corrections.
ALTER TABLE `rent_invoices`
    ADD COLUMN IF NOT EXISTS `rent_amount` DECIMAL(10,2) NOT NULL DEFAULT 0
        COMMENT 'Effective rent for this period (from rent_changes or tenancy agreed_rent)'
    AFTER `amount_paid`;

-- Back-fill: derive rent from amount_due - gst - eb - other for existing rows.
-- This is an approximation; rows without the new column populated are marked 0.
UPDATE `rent_invoices`
SET `rent_amount` = GREATEST(0,
        `amount_due` - COALESCE(`rent_gst`,0) - COALESCE(`eb_amount`,0) - COALESCE(`other_charges`,0))
WHERE `rent_amount` = 0;
