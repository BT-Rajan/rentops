-- ─────────────────────────────────────────────────────────────────────────────
-- RentOps — Test Data Seed (002_test_data.sql)
-- Run AFTER all migrations + 001_seed.sql
--
-- Tenants & scenarios:
--   Mani Aavudaiyappan    — active, fully paid up, single room, UPI payer
--   Arun Kasi Viswanathan — active, partial payment this month, sharing room
--   Santhosh Vijayan      — active, overdue 2 months, single room
--   Guruprasad Ramarao    — active, mid-tenancy rent hike recorded, single room
--   Mohammed Rizwan       — active, dorm room, paid cash, overpayment scenario
--   Sayon Sankar          — vacated last month, deposit partially deducted
-- ─────────────────────────────────────────────────────────────────────────────

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── IDs (stable, easy to reference) ─────────────────────────────────────────

-- Tenants
SET @mani        = 'tenant-mani-0000-0000-000000000001';
SET @arun        = 'tenant-arun-0000-0000-000000000002';
SET @santhosh    = 'tenant-sant-0000-0000-000000000003';
SET @guru        = 'tenant-guru-0000-0000-000000000004';
SET @rizwan      = 'tenant-rizw-0000-0000-000000000005';
SET @sayon       = 'tenant-sayo-0000-0000-000000000006';

-- Tenancies
SET @ten_mani    = 'tenancy-mani-000-0000-000000000001';
SET @ten_arun    = 'tenancy-arun-000-0000-000000000002';
SET @ten_sant    = 'tenancy-sant-000-0000-000000000003';
SET @ten_guru    = 'tenancy-guru-000-0000-000000000004';
SET @ten_rizw    = 'tenancy-rizw-000-0000-000000000005';
SET @ten_sayo    = 'tenancy-sayo-000-0000-000000000006';

-- Property (already seeded as 00000000-0000-0000-0000-000000000002)
SET @prop        = '00000000-0000-0000-0000-000000000002';

-- ─── TENANTS ─────────────────────────────────────────────────────────────────

INSERT IGNORE INTO tenants
    (id, full_name, phone, email, id_proof_type, id_proof_number, emergency_contact, status)
VALUES
-- 1. Mani — reliable payer, Aadhaar verified
(   @mani, 'Mani Aavudaiyappan', '9841001001', 'mani.aavudaiyappan@gmail.com',
    'Aadhaar', '2345 6789 0123', 'Wife: Priya — 9841002002', 'active'),

-- 2. Arun — sharing room, partial payer
(   @arun, 'Arun Kasi Viswanathan', '9841003003', 'arun.kv@gmail.com',
    'Aadhaar', '3456 7890 1234', 'Father: Kasi — 9841004004', 'active'),

-- 3. Santhosh — overdue tenant, two months pending
(   @santhosh, 'Santhosh Vijayan', '9841005005', NULL,
    'PAN', 'ABCDE1234F', 'Brother: Vijay — 9841006006', 'active'),

-- 4. Guruprasad — rent was revised mid-tenancy
(   @guru, 'Guruprasad Ramarao', '9841007007', 'guru.ramarao@outlook.com',
    'Passport', 'P1234567', 'Mother: Radha — 9841008008', 'active'),

-- 5. Mohammed Rizwan — dorm, paid extra (overpayment)
(   @rizwan, 'Mohammed Rizwan', '9841009009', 'rizwan.m@gmail.com',
    'Aadhaar', '4567 8901 2345', 'Friend: Imran — 9841010010', 'active'),

-- 6. Sayon — vacated, deposit partially refunded
(   @sayon, 'Sayon Sankar', '9841011011', 'sayon.sankar@gmail.com',
    'Other', 'DL-TN-1234567', 'Sister: Sreya — 9841012012', 'vacated');

-- ─── TENANCIES ───────────────────────────────────────────────────────────────
-- Rooms from seed: r01–r22 (using r01,r02 single; r05 sharing; r21 dorm; r13 single for guru)

INSERT IGNORE INTO tenancies
    (id, tenant_id, room_id, move_in_date, move_out_date,
     agreed_rent, security_deposit, deposit_deduction, deposit_refund,
     rent_due_day, status)
VALUES
-- Mani → Room 101 (single, ₹9000), moved in 8 months ago
(   @ten_mani, @mani, '2eea5546-7d09-5cea-b98d-0e0ec843b774',
    DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 8 MONTH), '%Y-%m-01'),
    NULL, 9000.00, 18000.00, NULL, NULL, 5, 'active'),

-- Arun → Room 105 (sharing, ₹6000), moved in 5 months ago
(   @ten_arun, @arun, '73fff802-f083-5d77-bc4c-76263a8c6011',
    DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01'),
    NULL, 6000.00, 12000.00, NULL, NULL, 5, 'active'),

-- Santhosh → Room 102 (single, ₹9000), moved in 4 months ago
(   @ten_sant, @santhosh, 'bcf02df4-b810-5ee2-9535-63a591d2b664',
    DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01'),
    NULL, 9000.00, 18000.00, NULL, NULL, 5, 'active'),

-- Guruprasad → Room 301 (single, now ₹10500 after hike), moved in 6 months ago
(   @ten_guru, @guru, '21bd281e-c37d-5039-bcd8-0c881472a094',
    DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 6 MONTH), '%Y-%m-01'),
    NULL, 10500.00, 21000.00, NULL, NULL, 5, 'active'),

-- Mohammed Rizwan → Room G03 (dorm, ₹4500), moved in 3 months ago
(   @ten_rizw, @rizwan, '77025690-bca5-5d6f-81be-c4b9e432948f',
    DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), '%Y-%m-01'),
    NULL, 4500.00, 9000.00, NULL, NULL, 5, 'active'),

-- Sayon → Room 102 — wait, r02 is taken by Santhosh; Sayon used r03, now vacated
-- Move-out happened last month, deposit partially deducted (₹2000 for damages)
(   @ten_sayo, @sayon, '6e0c49b0-0b19-56bd-a400-8dc7c7331db1',
    DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 7 MONTH), '%Y-%m-01'),
    DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-28'),
    9000.00, 18000.00, 2000.00, 16000.00, 5, 'closed');

-- Update room statuses to match tenancies
UPDATE rooms SET status = 'occupied'       WHERE id IN ('2eea5546-7d09-5cea-b98d-0e0ec843b774','bcf02df4-b810-5ee2-9535-63a591d2b664','73fff802-f083-5d77-bc4c-76263a8c6011','21bd281e-c37d-5039-bcd8-0c881472a094','77025690-bca5-5d6f-81be-c4b9e432948f');
UPDATE rooms SET status = 'vacant'         WHERE id = '6e0c49b0-0b19-56bd-a400-8dc7c7331db1';  -- Sayon vacated

-- ─── RENT CHANGES — Guruprasad had a hike 3 months ago ───────────────────────

INSERT IGNORE INTO rent_changes
    (id, tenancy_id, old_rent, new_rent, effective_from, note, created_by)
VALUES (
    'rentchg-guru-0000-0000-000000000001',
    @ten_guru,
    10000.00, 10500.00,
    DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), '%Y-%m-01'),
    'Annual revision — market rate adjustment',
    'Property Owner'
);

-- ─── RENT INVOICES ────────────────────────────────────────────────────────────
-- Helper: period_month is always the 1st of that month

-- ── MANI (8 months) — all paid ───────────────────────────────────────────────
INSERT IGNORE INTO rent_invoices
    (id, tenancy_id, period_month, amount_due, amount_paid, overpayment, due_date, status)
VALUES
('inv-mani-01', @ten_mani, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 7 MONTH),'%Y-%m-01'), 9000,9000,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 7 MONTH),'%Y-%m-05'), 'paid'),
('inv-mani-02', @ten_mani, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 6 MONTH),'%Y-%m-01'), 9000,9000,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 6 MONTH),'%Y-%m-05'), 'paid'),
('inv-mani-03', @ten_mani, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'%Y-%m-01'), 9000,9000,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'%Y-%m-05'), 'paid'),
('inv-mani-04', @ten_mani, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH),'%Y-%m-01'), 9000,9000,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH),'%Y-%m-05'), 'paid'),
('inv-mani-05', @ten_mani, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 MONTH),'%Y-%m-01'), 9000,9000,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 MONTH),'%Y-%m-05'), 'paid'),
('inv-mani-06', @ten_mani, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'%Y-%m-01'), 9000,9000,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'%Y-%m-05'), 'paid'),
('inv-mani-07', @ten_mani, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m-01'), 9000,9000,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m-05'), 'paid'),
('inv-mani-08', @ten_mani, DATE_FORMAT(CURDATE(),'%Y-%m-01'),                             9000,9000,0, DATE_FORMAT(CURDATE(),'%Y-%m-05'),                             'paid');

-- ── ARUN (5 months) — 4 paid, current month partial ─────────────────────────
INSERT IGNORE INTO rent_invoices
    (id, tenancy_id, period_month, amount_due, amount_paid, overpayment, due_date, status)
VALUES
('inv-arun-01', @ten_arun, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH),'%Y-%m-01'), 6000,6000,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH),'%Y-%m-05'), 'paid'),
('inv-arun-02', @ten_arun, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 MONTH),'%Y-%m-01'), 6000,6000,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 MONTH),'%Y-%m-05'), 'paid'),
('inv-arun-03', @ten_arun, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'%Y-%m-01'), 6000,6000,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'%Y-%m-05'), 'paid'),
('inv-arun-04', @ten_arun, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m-01'), 6000,6000,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m-05'), 'paid'),
-- Current month: paid only ₹3000 of ₹6000
('inv-arun-05', @ten_arun, DATE_FORMAT(CURDATE(),'%Y-%m-01'),                             6000,3000,0, DATE_FORMAT(CURDATE(),'%Y-%m-05'),                             'partial');

-- ── SANTHOSH (4 months) — first 2 paid, last 2 overdue ──────────────────────
INSERT IGNORE INTO rent_invoices
    (id, tenancy_id, period_month, amount_due, amount_paid, overpayment, due_date, status)
VALUES
('inv-sant-01', @ten_sant, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 MONTH),'%Y-%m-01'), 9000,9000,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 MONTH),'%Y-%m-05'), 'paid'),
('inv-sant-02', @ten_sant, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'%Y-%m-01'), 9000,9000,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'%Y-%m-05'), 'paid'),
-- Overdue — past due date, nothing paid
('inv-sant-03', @ten_sant, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m-01'), 9000,   0,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m-05'), 'overdue'),
('inv-sant-04', @ten_sant, DATE_FORMAT(CURDATE(),'%Y-%m-01'),                             9000,   0,0, DATE_FORMAT(CURDATE(),'%Y-%m-05'),                             'overdue');

-- ── GURUPRASAD (6 months) — first 3 at old rent ₹10000, last 3 at ₹10500 ────
INSERT IGNORE INTO rent_invoices
    (id, tenancy_id, period_month, amount_due, amount_paid, overpayment, due_date, status)
VALUES
('inv-guru-01', @ten_guru, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'%Y-%m-01'), 10000,10000,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'%Y-%m-05'), 'paid'),
('inv-guru-02', @ten_guru, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH),'%Y-%m-01'), 10000,10000,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH),'%Y-%m-05'), 'paid'),
('inv-guru-03', @ten_guru, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 MONTH),'%Y-%m-01'), 10000,10000,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 MONTH),'%Y-%m-05'), 'paid'),
-- Post-hike invoices at ₹10500
('inv-guru-04', @ten_guru, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'%Y-%m-01'), 10500,10500,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'%Y-%m-05'), 'paid'),
('inv-guru-05', @ten_guru, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m-01'), 10500,10500,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m-05'), 'paid'),
('inv-guru-06', @ten_guru, DATE_FORMAT(CURDATE(),'%Y-%m-01'),                             10500,10500,0, DATE_FORMAT(CURDATE(),'%Y-%m-05'),                             'paid');

-- ── MOHAMMED RIZWAN (3 months) — month 2 overpaid by ₹500 ───────────────────
INSERT IGNORE INTO rent_invoices
    (id, tenancy_id, period_month, amount_due, amount_paid, overpayment, due_date, status)
VALUES
('inv-rizw-01', @ten_rizw, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'%Y-%m-01'), 4500,4500,   0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'%Y-%m-05'), 'paid'),
-- Overpaid: sent ₹5000 for a ₹4500 invoice (₹500 excess)
('inv-rizw-02', @ten_rizw, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m-01'), 4500,5000, 500, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m-05'), 'paid'),
('inv-rizw-03', @ten_rizw, DATE_FORMAT(CURDATE(),'%Y-%m-01'),                             4500,4500,   0, DATE_FORMAT(CURDATE(),'%Y-%m-05'),                             'paid');

-- ── SAYON (7 months, closed — 6 paid, last month invoice auto-closed) ────────
INSERT IGNORE INTO rent_invoices
    (id, tenancy_id, period_month, amount_due, amount_paid, overpayment, due_date, status)
VALUES
('inv-sayo-01', @ten_sayo, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 6 MONTH),'%Y-%m-01'), 9000,9000,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 6 MONTH),'%Y-%m-05'), 'paid'),
('inv-sayo-02', @ten_sayo, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'%Y-%m-01'), 9000,9000,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'%Y-%m-05'), 'paid'),
('inv-sayo-03', @ten_sayo, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH),'%Y-%m-01'), 9000,9000,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH),'%Y-%m-05'), 'paid'),
('inv-sayo-04', @ten_sayo, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 MONTH),'%Y-%m-01'), 9000,9000,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 MONTH),'%Y-%m-05'), 'paid'),
('inv-sayo-05', @ten_sayo, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'%Y-%m-01'), 9000,9000,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'%Y-%m-05'), 'paid'),
('inv-sayo-06', @ten_sayo, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m-01'), 9000,9000,0, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m-05'), 'paid');

-- ─── PAYMENTS ────────────────────────────────────────────────────────────────
-- Mani — all UPI
INSERT IGNORE INTO payments (id, invoice_id, amount, payment_date, mode, note, recorded_by) VALUES
('pay-mani-01','inv-mani-01',9000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 7 MONTH),'%Y-%m-03'),'UPI',  'GPay transfer',          'Property Owner'),
('pay-mani-02','inv-mani-02',9000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 6 MONTH),'%Y-%m-04'),'UPI',  'PhonePe',                'Property Owner'),
('pay-mani-03','inv-mani-03',9000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 5 MONTH),'%Y-%m-02'),'UPI',  'GPay transfer',          'Property Owner'),
('pay-mani-04','inv-mani-04',9000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 4 MONTH),'%Y-%m-05'),'UPI',  'On due date — GPay',     'Property Owner'),
('pay-mani-05','inv-mani-05',9000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 3 MONTH),'%Y-%m-03'),'UPI',  'PhonePe',                'Property Owner'),
('pay-mani-06','inv-mani-06',9000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 2 MONTH),'%Y-%m-04'),'UPI',  'GPay transfer',          'Property Owner'),
('pay-mani-07','inv-mani-07',9000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 1 MONTH),'%Y-%m-02'),'UPI',  'GPay transfer',          'Property Owner'),
('pay-mani-08','inv-mani-08',9000,DATE_FORMAT(CURDATE(),'%Y-%m-01'),                           'UPI',  'Paid on 1st itself',     'Property Owner');

-- Arun — cash payments, current month partial (two separate payments)
INSERT IGNORE INTO payments (id, invoice_id, amount, payment_date, mode, note, recorded_by) VALUES
('pay-arun-01','inv-arun-01',6000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 4 MONTH),'%Y-%m-05'),'cash', 'Cash in hand',           'Property Owner'),
('pay-arun-02','inv-arun-02',6000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 3 MONTH),'%Y-%m-04'),'cash', 'Cash in hand',           'Property Owner'),
('pay-arun-03','inv-arun-03',6000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 2 MONTH),'%Y-%m-06'),'cash', 'One day late',           'Property Owner'),
('pay-arun-04','inv-arun-04',6000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 1 MONTH),'%Y-%m-05'),'UPI',  'Switched to GPay',       'Property Owner'),
-- Current month partial: ₹3000 so far
('pay-arun-05','inv-arun-05',3000,DATE_FORMAT(CURDATE(),'%Y-%m-03'),                           'cash', 'Partial — balance due',  'Property Owner');

-- Santhosh — only first 2 months paid (no payment rows for overdue invoices)
INSERT IGNORE INTO payments (id, invoice_id, amount, payment_date, mode, note, recorded_by) VALUES
('pay-sant-01','inv-sant-01',9000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 3 MONTH),'%Y-%m-05'),'bank_transfer','NEFT from savings','Property Owner'),
('pay-sant-02','inv-sant-02',9000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 2 MONTH),'%Y-%m-07'),'bank_transfer','NEFT — 2 days late','Property Owner');

-- Guruprasad — all bank transfer
INSERT IGNORE INTO payments (id, invoice_id, amount, payment_date, mode, note, recorded_by) VALUES
('pay-guru-01','inv-guru-01',10000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 5 MONTH),'%Y-%m-04'),'bank_transfer','IMPS',             'Property Owner'),
('pay-guru-02','inv-guru-02',10000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 4 MONTH),'%Y-%m-05'),'bank_transfer','IMPS',             'Property Owner'),
('pay-guru-03','inv-guru-03',10000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 3 MONTH),'%Y-%m-03'),'bank_transfer','IMPS — pre-hike',  'Property Owner'),
('pay-guru-04','inv-guru-04',10500,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 2 MONTH),'%Y-%m-05'),'bank_transfer','IMPS — new rate',  'Property Owner'),
('pay-guru-05','inv-guru-05',10500,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 1 MONTH),'%Y-%m-04'),'bank_transfer','IMPS',             'Property Owner'),
('pay-guru-06','inv-guru-06',10500,DATE_FORMAT(CURDATE(),'%Y-%m-02'),                           'bank_transfer','IMPS — early pay', 'Property Owner');

-- Mohammed Rizwan — cash; month 2 he sent ₹5000 (₹500 extra)
INSERT IGNORE INTO payments (id, invoice_id, amount, payment_date, mode, note, recorded_by) VALUES
('pay-rizw-01','inv-rizw-01',4500,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 2 MONTH),'%Y-%m-05'),'cash', 'Cash',                   'Property Owner'),
('pay-rizw-02','inv-rizw-02',5000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 1 MONTH),'%Y-%m-05'),'cash', 'Paid ₹5000 — ₹500 extra','Property Owner'),
('pay-rizw-03','inv-rizw-03',4500,DATE_FORMAT(CURDATE(),'%Y-%m-04'),                           'UPI',  'Switched to PhonePe',    'Property Owner');

-- Sayon — all paid before move-out
INSERT IGNORE INTO payments (id, invoice_id, amount, payment_date, mode, note, recorded_by) VALUES
('pay-sayo-01','inv-sayo-01',9000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 6 MONTH),'%Y-%m-04'),'UPI',  'GPay',                   'Property Owner'),
('pay-sayo-02','inv-sayo-02',9000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 5 MONTH),'%Y-%m-05'),'UPI',  'GPay',                   'Property Owner'),
('pay-sayo-03','inv-sayo-03',9000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 4 MONTH),'%Y-%m-03'),'UPI',  'GPay',                   'Property Owner'),
('pay-sayo-04','inv-sayo-04',9000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 3 MONTH),'%Y-%m-05'),'cash', 'Cash — GPay down',       'Property Owner'),
('pay-sayo-05','inv-sayo-05',9000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 2 MONTH),'%Y-%m-04'),'UPI',  'GPay',                   'Property Owner'),
('pay-sayo-06','inv-sayo-06',9000,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 1 MONTH),'%Y-%m-20'),'UPI',  'Final month before moveout','Property Owner');

SET FOREIGN_KEY_CHECKS = 1;

-- ─── QUICK SANITY CHECK ───────────────────────────────────────────────────────
SELECT
    t.full_name,
    COUNT(ri.id)                                    AS invoices,
    SUM(ri.amount_due)                              AS total_due,
    SUM(ri.amount_paid)                             AS total_paid,
    SUM(ri.amount_due - ri.amount_paid)             AS balance,
    GROUP_CONCAT(DISTINCT ri.status ORDER BY ri.status) AS statuses
FROM tenants t
JOIN tenancies ten ON ten.tenant_id = t.id
JOIN rent_invoices ri ON ri.tenancy_id = ten.id
WHERE t.id IN (@mani,@arun,@santhosh,@guru,@rizwan,@sayon)
GROUP BY t.id, t.full_name
ORDER BY t.full_name;
