-- ============================================
-- NovaLink Billing System - Database Upgrade
-- Version 2.0 (Compatible with MySQL 5.7+)
-- ============================================

USE ar_novalink_billing;

-- 1. Package Materials Table
CREATE TABLE IF NOT EXISTS package_materials (
    material_id INT PRIMARY KEY AUTO_INCREMENT,
    package_id INT NOT NULL,
    material_name VARCHAR(150) NOT NULL,
    quantity INT DEFAULT 1,
    unit VARCHAR(50) DEFAULT 'pcs',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (package_id) REFERENCES packages(package_id) ON DELETE CASCADE,
    INDEX idx_package (package_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Installation Sketches Table
CREATE TABLE IF NOT EXISTS installation_sketches (
    sketch_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    sketch_type ENUM('upload', 'drawing') NOT NULL DEFAULT 'upload',
    file_path VARCHAR(500),
    sketch_data LONGTEXT,
    remarks TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Customer Status History Log Table
CREATE TABLE IF NOT EXISTS customer_status_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50) NOT NULL,
    changed_by INT,
    change_date DATE NOT NULL,
    change_time TIME NOT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_customer (customer_id),
    INDEX idx_date (change_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. System Settings Table
CREATE TABLE IF NOT EXISTS system_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Billing Additional Fees Table
CREATE TABLE IF NOT EXISTS billing_fees (
    fee_id INT PRIMARY KEY AUTO_INCREMENT,
    billing_id INT NOT NULL,
    fee_type ENUM('installation', 'reconnection', 'adjustment', 'other') NOT NULL,
    fee_description VARCHAR(255),
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (billing_id) REFERENCES billings(billing_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_billing (billing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Alter customers table to add new statuses
ALTER TABLE customers MODIFY COLUMN status ENUM('active', 'disconnected', 'reconnected', 'pending_installation', 'hold_disconnection') DEFAULT 'active';

-- =====================================================================
-- 7. Safely add columns to customers table
--    Uses a stored procedure so it won't error if column already exists.
-- =====================================================================

DELIMITER //

DROP PROCEDURE IF EXISTS safe_add_column //

CREATE PROCEDURE safe_add_column(
    IN tbl_name VARCHAR(64),
    IN col_name VARCHAR(64),
    IN col_def VARCHAR(255),
    IN after_col VARCHAR(64)
)
BEGIN
    SET @col_exists = 0;
    SELECT COUNT(*) INTO @col_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = tbl_name
      AND COLUMN_NAME = col_name;

    IF @col_exists = 0 THEN
        IF after_col IS NOT NULL AND after_col != '' THEN
            SET @sql = CONCAT('ALTER TABLE `', tbl_name, '` ADD COLUMN `', col_name, '` ', col_def, ' AFTER `', after_col, '`');
        ELSE
            SET @sql = CONCAT('ALTER TABLE `', tbl_name, '` ADD COLUMN `', col_name, '` ', col_def);
        END IF;
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //

DELIMITER ;

CALL safe_add_column('customers', 'port_number',    'VARCHAR(50)',  'router_serial');
CALL safe_add_column('customers', 'lcp_number',     'VARCHAR(50)',  'port_number');
CALL safe_add_column('customers', 'nap_number',     'VARCHAR(50)',  'lcp_number');
CALL safe_add_column('customers', 'nap_output',     'VARCHAR(50)',  'nap_number');
CALL safe_add_column('customers', 'fiber_output',   'VARCHAR(50)',  'nap_output');
CALL safe_add_column('customers', 'serial_number',  'VARCHAR(100)', 'fiber_output');
CALL safe_add_column('customers', 'mac_address',    'VARCHAR(50)',  'serial_number');
CALL safe_add_column('customers', 'acct_name',      'VARCHAR(150)', 'account_name');
CALL safe_add_column('customers', 'password_field',  'VARCHAR(100)', 'acct_name');
CALL safe_add_column('customers', 'installed_by',   'VARCHAR(100)', 'mac_address');

-- Clean up
DROP PROCEDURE IF EXISTS safe_add_column;

-- 8. Insert default system settings
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES 
('billing_reminder', '1. Please disregard this bill if already paid.\n2. If you wish to clarify any item on this bill please come to our office.\n3. Due date every End of the Month with 7 days grace period.\n4. If payment is not made after a span of 7 days, automatically TEMPORARY DISCONNECTION.'),
('company_name', 'NOVA LINK DIGITAL SYSTEMS CORP.'),
('company_address', 'F. PALMARES STREET, PASSI CITY, ILOILO'),
('company_phone', '0962-782-9066'),
('company_tagline', 'Thank you for keeping your account current. We value your continued patronage.');

-- 9. Insert default materials for existing packages
INSERT IGNORE INTO package_materials (package_id, material_name, quantity, unit) VALUES 
(1, 'Fiber Optic Cable 1 Core', 1, 'roll'),
(1, 'SC Connector', 2, 'pcs'),
(1, 'RG6 Wire', 1, 'roll'),
(1, 'RG6 Connector', 2, 'pcs'),
(1, 'Patchcord', 1, 'pcs'),
(1, 'Coupler', 1, 'pcs'),
(1, 'F Clamp', 2, 'pcs'),
(1, 'Passive Node', 1, 'pcs'),
(1, 'Terminal Junction Box', 1, 'pcs'),
(2, 'Fiber Optic Cable 1 Core', 1, 'roll'),
(2, 'SC Connector', 2, 'pcs'),
(2, 'RG6 Wire', 1, 'roll'),
(2, 'RG6 Connector', 2, 'pcs'),
(2, 'Patchcord', 1, 'pcs'),
(2, 'Coupler', 1, 'pcs'),
(2, 'Coupler 2Way', 1, 'pcs'),
(2, '2Way Splitter', 1, 'pcs'),
(2, 'F Clamp', 2, 'pcs'),
(2, 'Passive Node', 1, 'pcs'),
(2, 'Active Node', 1, 'pcs'),
(2, 'Terminal Junction Box', 1, 'pcs'),
(3, 'Fiber Optic Cable 1 Core', 1, 'roll'),
(3, 'Fiber Optic Cable 2 Core', 1, 'roll'),
(3, 'SC Connector', 4, 'pcs'),
(3, 'RG6 Wire', 1, 'roll'),
(3, 'RG6 Connector', 4, 'pcs'),
(3, '2Way Splitter', 1, 'pcs'),
(3, '3Way Splitter', 1, 'pcs'),
(3, 'Patchcord', 1, 'pcs'),
(3, 'Coupler', 1, 'pcs'),
(3, 'Coupler 2Way', 1, 'pcs'),
(3, 'F Clamp', 4, 'pcs'),
(3, 'Passive Node', 1, 'pcs'),
(3, 'Active Node', 1, 'pcs'),
(3, 'Terminal Junction Box', 1, 'pcs');
