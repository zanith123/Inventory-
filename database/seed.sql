-- ============================================================================
-- Inventory Management System - Demo Seed Data
-- For local development, testing, and initial setup
-- ============================================================================

USE inventory_db;

-- 1. Ensure Roles Exist
INSERT IGNORE INTO roles (id, name) VALUES
(1, 'Admin'),
(2, 'User'),
(3, 'Viewer');

-- 2. Demo Users
-- Passwords:
-- admin@inventory.com -> admin123 (forced password reset on first login enabled)
-- staff@inventory.com -> user123
INSERT INTO users (id, name, email, password, role_id, avatar, must_change_password, created_at) VALUES
(1, 'Admin User', 'admin@inventory.com', '$2y$10$n1DcUESZaxgiwPgvwlmDWuywkh4Is55hLFWNnuh6G4hWYfwRe6mne', 1, NULL, 1, NOW()),
(2, 'Staff Member', 'staff@inventory.com', '$2y$10$hmfFsptMpCcF1PAseyH2VOogt74YVsSS.aTHoPUIqnKDSYy/NX0U.', 2, NULL, 0, NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name), password=VALUES(password), role_id=VALUES(role_id);

-- 3. Categories
INSERT INTO categories (id, name, slug, note, created_at) VALUES
(1, 'Cases', 'cases', 'Protective phone, tablet, and laptop cases', NOW()),
(2, 'Screen Protectors', 'screen-protectors', 'Tempered glass, matte, and privacy screen protectors', NOW()),
(3, 'Chargers', 'chargers', 'Wall adapters, GaN fast chargers, and power banks', NOW()),
(4, 'Cables', 'cables', 'USB-C, Lightning, Thunderbolt, and audio cables', NOW()),
(5, 'Audio', 'audio', 'True wireless earbuds, headphones, and portable speakers', NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name), note=VALUES(note);

-- 4. Units
INSERT INTO units (id, name, note) VALUES
(1, 'pcs', 'Individual pieces'),
(2, 'box', 'Packaged retail box'),
(3, 'set', 'Matched accessory set'),
(4, 'pack', 'Multi-item blister pack')
ON DUPLICATE KEY UPDATE name=VALUES(name), note=VALUES(note);

-- 5. Suppliers
INSERT INTO suppliers (id, name, phone, email, address, note) VALUES
(1, 'Anker Official', '+855 12 345 678', 'sales@anker-demo.com', 'Building 12, Tech Zone, Phnom Penh', 'Official distributor of Anker charging and audio gear'),
(2, 'Baseus Tech', '+855 15 888 999', 'support@baseus-demo.com', 'St. 2004, Sen Sok, Phnom Penh', 'Supplier for Baseus mobile accessories and styling'),
(3, 'Ugreen Direct', '+855 98 765 432', 'contact@ugreen-demo.com', 'Monivong Blvd, Chamkarmon, Phnom Penh', 'Direct importer for Ugreen accessories, cables, and hubs')
ON DUPLICATE KEY UPDATE name=VALUES(name), phone=VALUES(phone), email=VALUES(email), address=VALUES(address);

-- 6. Products
INSERT INTO products (id, name, sku, barcode, category_id, supplier_id, unit_id, note, cost_price, sale_price, min_stock, current_stock, created_at) VALUES
(1, 'Anker Nano II 65W Fast Charger', 'ANK-CH-65W', '8934500101', 3, 1, 1, 'Compact GaN II USB-C charger for phone and laptop', 22.00, 34.99, 5, 25, NOW()),
(2, 'Anker PowerLine III USB-C to USB-C (1.8m)', 'ANK-CB-CC1', '8934500102', 4, 1, 1, '100W PD charging cable with durable braided finish', 6.50, 12.99, 10, 40, NOW()),
(3, 'Baseus Magnetic Matte Case (iPhone 15)', 'BAS-CS-IP15', '8934500201', 1, 2, 1, 'Translucent matte MagSafe-compatible phone case', 4.20, 9.99, 8, 30, NOW()),
(4, 'Baseus 9H Tempered Glass (2-Pack)', 'BAS-SP-IP15', '8934500202', 2, 2, 4, 'Full coverage edge-to-edge protector with install frame', 3.50, 8.50, 10, 35, NOW()),
(5, 'Baseus 20,000mAh 65W Power Bank', 'BAS-PB-20K', '8934500203', 3, 2, 1, 'High capacity digital display portable battery pack', 28.00, 45.00, 4, 15, NOW()),
(6, 'Ugreen MFi Lightning to USB-C Cable (1m)', 'UGR-CB-LT1', '8934500301', 4, 3, 1, 'Apple MFi certified fast charging silicone cable', 5.00, 11.00, 8, 30, NOW()),
(7, 'Ugreen HiTune T3 ANC Wireless Earbuds', 'UGR-AUD-HP1', '8934500302', 5, 3, 3, 'Active noise cancellation Bluetooth 5.2 earphones', 18.00, 29.99, 5, 18, NOW()),
(8, 'Ugreen 7-in-1 USB-C Multiport Hub', 'UGR-HUB-7IN1', '8934500303', 3, 3, 2, '4K HDMI, 100W PD, SD/TF, 3x USB 3.0 aluminum hub', 19.50, 32.00, 3, 12, NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name), cost_price=VALUES(cost_price), sale_price=VALUES(sale_price), current_stock=VALUES(current_stock);

-- 7. Sample Stock In Transactions
INSERT INTO stock_transactions (id, reference, type, transaction_date, note, supplier_id, user_id, created_at) VALUES
(1, 'IN-20260801-001', 'in', '2026-08-01', 'Initial stock shipment from Anker', 1, 1, NOW()),
(2, 'IN-20260802-002', 'in', '2026-08-02', 'Monthly store inventory intake from Baseus', 2, 1, NOW()),
(3, 'IN-20260803-003', 'in', '2026-08-03', 'Restock cables and audio accessories from Ugreen', 3, 2, NOW())
ON DUPLICATE KEY UPDATE reference=VALUES(reference);

-- 8. Sample Stock In Transaction Items
INSERT INTO stock_transaction_items (id, transaction_id, product_id, qty, unit_price, subtotal) VALUES
(1, 1, 1, 25, 22.00, 550.00),
(2, 1, 2, 40, 6.50, 260.00),
(3, 2, 3, 30, 4.20, 126.00),
(4, 2, 4, 35, 3.50, 122.50),
(5, 2, 5, 15, 28.00, 420.00),
(6, 3, 6, 30, 5.00, 150.00),
(7, 3, 7, 18, 18.00, 324.00),
(8, 3, 8, 12, 19.50, 234.00)
ON DUPLICATE KEY UPDATE qty=VALUES(qty), unit_price=VALUES(unit_price), subtotal=VALUES(subtotal);
