-- RentOps Seed — run AFTER all migrations
-- ─────────────────────────────────────────
-- Default login credentials:
--   Email   : owner@rentops.local
--   Password: RentOps@2024
--
-- IMPORTANT: Change the password immediately after first login
--            via Settings → Change password
-- ─────────────────────────────────────────

SET NAMES utf8mb4;

-- ─── Owner user ───────────────────────────────────────────────────────────────
INSERT IGNORE INTO users (id, name, email, password_hash) VALUES (
    '00000000-0000-0000-0000-000000000001',
    'Property Owner',
    'owner@rentops.local',
    '$2y$10$6aohplgnHoG9cYHA.sWkLu9fbNiXAe9Ae4OjJVmpCtEftn0tGPHNi'
);

-- ─── Property ─────────────────────────────────────────────────────────────────
INSERT IGNORE INTO properties (id, name, address, total_rooms, default_due_day) VALUES (
    '00000000-0000-0000-0000-000000000002',
    'Main Property — 22 Rooms',
    '123 Main Street, Chennai, Tamil Nadu',
    22,
    5
);

-- ─── 22 rooms ─────────────────────────────────────────────────────────────────
INSERT IGNORE INTO rooms (id, property_id, room_number, room_type, base_rent, status) VALUES
('2eea5546-7d09-5cea-b98d-0e0ec843b774', '00000000-0000-0000-0000-000000000002', '101', 'single',  9000.00, 'vacant'),
('bcf02df4-b810-5ee2-9535-63a591d2b664', '00000000-0000-0000-0000-000000000002', '102', 'single',  9000.00, 'vacant'),
('6e0c49b0-0b19-56bd-a400-8dc7c7331db1', '00000000-0000-0000-0000-000000000002', '103', 'single',  9000.00, 'vacant'),
('b5c42331-e514-5186-bd86-8c95c687e8af', '00000000-0000-0000-0000-000000000002', '104', 'single',  9000.00, 'vacant'),
('73fff802-f083-5d77-bc4c-76263a8c6011', '00000000-0000-0000-0000-000000000002', '105', 'sharing', 6000.00, 'vacant'),
('ac1cc84b-e14c-5920-82d7-b5894aa3f0b4', '00000000-0000-0000-0000-000000000002', '106', 'sharing', 6000.00, 'vacant'),
('e61a698f-aec2-5718-8abe-f628398b1fc8', '00000000-0000-0000-0000-000000000002', '201', 'single',  9500.00, 'vacant'),
('bbc47748-4100-5725-adfd-6345cb5e98f9', '00000000-0000-0000-0000-000000000002', '202', 'single',  9500.00, 'vacant'),
('4afd216c-f1db-589b-be87-45a9f4d49856', '00000000-0000-0000-0000-000000000002', '203', 'single',  9500.00, 'vacant'),
('e9fbc69e-d3e4-5e21-bf36-ba0211891fda', '00000000-0000-0000-0000-000000000002', '204', 'single',  9500.00, 'vacant'),
('3e74d4c5-4d5a-5b54-9611-1847903457cb', '00000000-0000-0000-0000-000000000002', '205', 'sharing', 6500.00, 'vacant'),
('2bf1a6ae-ac1c-5e0f-a008-8087627aad48', '00000000-0000-0000-0000-000000000002', '206', 'sharing', 6500.00, 'vacant'),
('21bd281e-c37d-5039-bcd8-0c881472a094', '00000000-0000-0000-0000-000000000002', '301', 'single', 10000.00, 'vacant'),
('1f325ddc-9cb7-591b-93a4-d13dd6e57fc6', '00000000-0000-0000-0000-000000000002', '302', 'single', 10000.00, 'vacant'),
('9e8b03e2-e05d-5cb2-8872-285f351e0ece', '00000000-0000-0000-0000-000000000002', '303', 'single', 10000.00, 'vacant'),
('9e8bd826-9d71-50b0-ba63-f01545740f9c', '00000000-0000-0000-0000-000000000002', '304', 'single', 10000.00, 'vacant'),
('def9308f-8aae-54fb-b55e-c57a78bdbe2c', '00000000-0000-0000-0000-000000000002', '305', 'sharing', 7000.00, 'vacant'),
('4ea6c657-0e22-5962-98b1-ad3b5a2fa7bd', '00000000-0000-0000-0000-000000000002', '306', 'sharing', 7000.00, 'vacant'),
('695c70e1-0bfa-56bd-a173-70b9b2d31332', '00000000-0000-0000-0000-000000000002', 'G01', 'single',  8000.00, 'vacant'),
('286d7b0c-618e-5f66-900a-169a381b85fc', '00000000-0000-0000-0000-000000000002', 'G02', 'single',  8000.00, 'vacant'),
('77025690-bca5-5d6f-81be-c4b9e432948f', '00000000-0000-0000-0000-000000000002', 'G03', 'dorm',    4500.00, 'vacant'),
('0b6e42b7-8fb2-5fbd-84a5-7a12f12390e9', '00000000-0000-0000-0000-000000000002', 'G04', 'dorm',    4500.00, 'vacant');
