-- AR NOVALINK Billing System Database
-- Created: 2026

CREATE DATABASE IF NOT EXISTS ar_novalink_billing;
USE ar_novalink_billing;

-- Users Table (Admin, Accounting, Cashier)
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'accounting', 'cashier') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Areas/Barangays Table
CREATE TABLE areas (
    area_id INT PRIMARY KEY AUTO_INCREMENT,
    area_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_area_name (area_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Packages Table
CREATE TABLE packages (
    package_id INT PRIMARY KEY AUTO_INCREMENT,
    package_name VARCHAR(100) NOT NULL,
    bandwidth_mbps INT NOT NULL,
    monthly_fee DECIMAL(10,2) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_package_name (package_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customers/Subscribers Table
CREATE TABLE customers (
    customer_id INT PRIMARY KEY AUTO_INCREMENT,
    account_number VARCHAR(50) UNIQUE NOT NULL,
    subscriber_name VARCHAR(150) NOT NULL,
    account_name VARCHAR(150),
    address TEXT NOT NULL,
    area_id INT,
    tel_no VARCHAR(50),
    package_id INT,
    bandwidth_mbps INT,
    monthly_fee DECIMAL(10,2),
    installation_date DATE,
    date_connected DATE,
    disconnection_date DATE NULL,
    status ENUM('active', 'disconnected', 'hold_disconnection') DEFAULT 'active',
    router_serial VARCHAR(100),
    code_number VARCHAR(50),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (area_id) REFERENCES areas(area_id) ON DELETE SET NULL,
    FOREIGN KEY (package_id) REFERENCES packages(package_id) ON DELETE SET NULL,
    INDEX idx_account_number (account_number),
    INDEX idx_subscriber_name (subscriber_name),
    INDEX idx_area (area_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Billing/Subscriptions Table (Monthly Records)
CREATE TABLE billings (
    billing_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    billing_month INT NOT NULL,
    billing_year INT NOT NULL,
    internet_fee DECIMAL(10,2) DEFAULT 0.00,
    cable_fee DECIMAL(10,2) DEFAULT 0.00,
    service_fee DECIMAL(10,2) DEFAULT 0.00,
    material_fee DECIMAL(10,2) DEFAULT 0.00,
    previous_balance DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0.00,
    net_amount DECIMAL(10,2) NOT NULL,
    status ENUM('unpaid', 'paid', 'partial') DEFAULT 'unpaid',
    due_date DATE,
    auto_generated TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
    UNIQUE KEY unique_customer_month (customer_id, billing_month, billing_year),
    INDEX idx_customer (customer_id),
    INDEX idx_billing_period (billing_year, billing_month),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments Table
CREATE TABLE payments (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    billing_id INT NOT NULL,
    customer_id INT NOT NULL,
    or_number VARCHAR(50) UNIQUE NOT NULL,
    payment_date DATE NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'check', 'online', 'others') DEFAULT 'cash',
    cashier_id INT,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (billing_id) REFERENCES billings(billing_id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
    FOREIGN KEY (cashier_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_or_number (or_number),
    INDEX idx_payment_date (payment_date),
    INDEX idx_customer (customer_id),
    INDEX idx_billing (billing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity Logs Table
CREATE TABLE activity_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Default Admin User (password: admin123)
INSERT INTO users (username, password, full_name, email, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@arnovalink.com', 'admin');

-- Insert Sample Areas
INSERT INTO areas (area_name, description) VALUES
('Barangay 1', 'Barangay 1 - Zone 1'),
('Barangay 2', 'Barangay 2 - Zone 1'),
('Barangay 3', 'Barangay 3 - Zone 2'),
('Poblacion', 'Poblacion Area'),
('San Jose', 'San Jose District'),
('San Isidro', 'San Isidro District');

-- Insert Sample Packages
INSERT INTO packages (package_name, bandwidth_mbps, monthly_fee, description) VALUES
('Package 1', 25, 599.00, '25 Mbps Basic Internet Package'),
('Package 2', 50, 899.00, '50 Mbps Standard Internet Package'),
('Package 3', 100, 1299.00, '100 Mbps Premium Internet Package'),
('Package 4', 150, 1599.00, '150 Mbps Ultra Package'),
('Package 5', 200, 1999.00, '200 Mbps Ultimate Package');

-- Insert Sample Customers (5 customers demonstrating all features)
INSERT INTO customers (account_number, subscriber_name, account_name, address, area_id, tel_no, package_id, bandwidth_mbps, monthly_fee, installation_date, date_connected, router_serial, code_number, status, remarks) VALUES
-- Customer 1: Active customer with paid bills
('ACC-001', 'DELA CRUZ, JUAN', 'Juan dela Cruz', 'Purok 1, Barangay 1, Passi City', 1, '09171234567', 2, 50, 899.00, '2024-01-15', '2024-01-15', 'RTR-001-2024', 'C001', 'active', 'Regular paying customer'),

-- Customer 2: Active customer with unpaid bills (overdue)
('ACC-002', 'SANTOS, MARIA', 'Maria Santos', 'Purok 3, Barangay 2, Passi City', 2, '09182345678', 1, 25, 599.00, '2024-02-01', '2024-02-01', 'RTR-002-2024', 'C002', 'active', 'Has overdue payments'),

-- Customer 3: Active customer with partial payment
('ACC-003', 'REYES, PEDRO', 'Pedro Reyes', 'Zone 2, Poblacion, Passi City', 4, '09193456789', 3, 100, 1299.00, '2024-03-10', '2024-03-10', 'RTR-003-2024', 'C003', 'active', 'Premium customer - partial payment'),

-- Customer 4: Recently installed, all paid
('ACC-004', 'GARCIA, ANNA', 'Anna Garcia', 'Sitio Maharlika, San Jose, Passi City', 5, '09204567890', 4, 150, 1599.00, '2025-12-01', '2025-12-01', 'RTR-004-2025', 'C004', 'active', 'New installation - December 2025'),

-- Customer 5: Disconnected account (unpaid for 2 months)
('ACC-005', 'LOPEZ, RICARDO', 'Ricardo Lopez', 'Purok 5, San Isidro, Passi City', 6, '09215678901', 2, 50, 899.00, '2024-06-15', '2024-06-15', 'RTR-005-2024', 'C005', 'disconnected', 'Disconnected due to non-payment'),

-- Customer 6: Hold Disconnection - has unpaid but given grace period
('ACC-006', 'MARTINEZ, ELENA', 'Elena Martinez', 'Purok 2, Barangay 1, Passi City', 1, '09226789012', 2, 50, 899.00, '2024-05-20', '2024-05-20', 'RTR-006-2024', 'C006', 'hold_disconnection', 'Grace period for payment - balance carries over');

-- Update disconnection dates for disconnected customer
UPDATE customers SET disconnection_date = '2025-12-15' WHERE account_number = 'ACC-005';

-- Insert Billings for Sample Customers
-- Customer 1 (DELA CRUZ): All paid for December 2025 and January 2026
INSERT INTO billings (customer_id, billing_month, billing_year, internet_fee, cable_fee, service_fee, previous_balance, total_amount, discount, net_amount, status, due_date) VALUES
(1, 12, 2025, 899.00, 0.00, 0.00, 0.00, 899.00, 0.00, 899.00, 'paid', '2025-12-31'),
(1, 1, 2026, 899.00, 0.00, 0.00, 0.00, 899.00, 0.00, 899.00, 'paid', '2026-01-31'),
(1, 2, 2026, 899.00, 0.00, 0.00, 0.00, 899.00, 0.00, 899.00, 'unpaid', '2026-02-28');

-- Customer 2 (SANTOS): Unpaid with balance carryover (OVERDUE)
INSERT INTO billings (customer_id, billing_month, billing_year, internet_fee, cable_fee, service_fee, previous_balance, total_amount, discount, net_amount, status, due_date) VALUES
(2, 11, 2025, 599.00, 0.00, 0.00, 0.00, 599.00, 0.00, 599.00, 'unpaid', '2025-11-30'),
(2, 12, 2025, 599.00, 0.00, 0.00, 599.00, 1198.00, 0.00, 1198.00, 'unpaid', '2025-12-31'),
(2, 1, 2026, 599.00, 0.00, 0.00, 1198.00, 1797.00, 0.00, 1797.00, 'unpaid', '2026-01-31'),
(2, 2, 2026, 599.00, 0.00, 0.00, 1797.00, 2396.00, 0.00, 2396.00, 'unpaid', '2026-02-28');

-- Customer 3 (REYES): Partial payment for January 2026
INSERT INTO billings (customer_id, billing_month, billing_year, internet_fee, cable_fee, service_fee, material_fee, previous_balance, total_amount, discount, net_amount, status, due_date) VALUES
(3, 11, 2025, 1299.00, 0.00, 0.00, 0.00, 0.00, 1299.00, 0.00, 1299.00, 'paid', '2025-11-30'),
(3, 12, 2025, 1299.00, 0.00, 0.00, 0.00, 0.00, 1299.00, 0.00, 1299.00, 'paid', '2025-12-31'),
(3, 1, 2026, 1299.00, 0.00, 100.00, 0.00, 0.00, 1399.00, 0.00, 1399.00, 'partial', '2026-01-31'),
(3, 2, 2026, 1299.00, 0.00, 0.00, 0.00, 599.00, 1898.00, 0.00, 1898.00, 'unpaid', '2026-02-28');

-- Customer 4 (GARCIA): New customer - December and January paid
INSERT INTO billings (customer_id, billing_month, billing_year, internet_fee, cable_fee, service_fee, material_fee, previous_balance, total_amount, discount, net_amount, status, due_date) VALUES
(4, 12, 2025, 1599.00, 0.00, 0.00, 500.00, 0.00, 2099.00, 0.00, 2099.00, 'paid', '2025-12-31'),
(4, 1, 2026, 1599.00, 0.00, 0.00, 0.00, 0.00, 1599.00, 0.00, 1599.00, 'paid', '2026-01-31'),
(4, 2, 2026, 1599.00, 0.00, 0.00, 0.00, 0.00, 1599.00, 0.00, 1599.00, 'unpaid', '2026-02-28');

-- Customer 5 (LOPEZ): Disconnected - billing stopped after disconnection
INSERT INTO billings (customer_id, billing_month, billing_year, internet_fee, cable_fee, service_fee, previous_balance, total_amount, discount, net_amount, status, due_date) VALUES
(5, 10, 2025, 899.00, 0.00, 0.00, 0.00, 899.00, 0.00, 899.00, 'paid', '2025-10-31'),
(5, 11, 2025, 899.00, 0.00, 0.00, 0.00, 899.00, 0.00, 899.00, 'paid', '2025-11-30'),
(5, 12, 2025, 899.00, 0.00, 0.00, 0.00, 899.00, 0.00, 899.00, 'unpaid', '2025-12-31');
-- No billing after December because disconnected on 2025-12-15

-- Customer 6 (MARTINEZ): Hold Disconnection - balance carries over
INSERT INTO billings (customer_id, billing_month, billing_year, internet_fee, cable_fee, service_fee, previous_balance, total_amount, discount, net_amount, status, due_date) VALUES
(6, 12, 2025, 899.00, 0.00, 0.00, 0.00, 899.00, 0.00, 899.00, 'unpaid', '2025-12-31'),
(6, 1, 2026, 899.00, 0.00, 0.00, 899.00, 1798.00, 0.00, 1798.00, 'unpaid', '2026-01-31'),
(6, 2, 2026, 899.00, 0.00, 0.00, 1798.00, 2697.00, 0.00, 2697.00, 'unpaid', '2026-02-28');

-- Insert Sample Payments
-- Customer 1 payments (all paid on time)
INSERT INTO payments (billing_id, customer_id, or_number, payment_date, amount_paid, payment_method, cashier_id, remarks) VALUES
(1, 1, 'OR-2025-001', '2025-12-08', 899.00, 'cash', 1, 'December 2025 payment'),
(2, 1, 'OR-2026-015', '2026-01-09', 899.00, 'cash', 1, 'January 2026 payment');

-- Customer 3 partial payment
INSERT INTO payments (billing_id, customer_id, or_number, payment_date, amount_paid, payment_method, cashier_id, remarks) VALUES
(6, 3, 'OR-2025-050', '2025-11-08', 1299.00, 'online', 1, 'November 2025 payment - GCash'),
(7, 3, 'OR-2025-078', '2025-12-07', 1299.00, 'cash', 1, 'December 2025 payment'),
(8, 3, 'OR-2026-020', '2026-01-12', 800.00, 'cash', 1, 'Partial payment - will pay balance next week');

-- Customer 4 payments (new installation)
INSERT INTO payments (billing_id, customer_id, or_number, payment_date, amount_paid, payment_method, cashier_id, remarks) VALUES
(10, 4, 'OR-2025-095', '2025-12-05', 2099.00, 'check', 1, 'December installation + first month'),
(11, 4, 'OR-2026-018', '2026-01-08', 1599.00, 'cash', 1, 'January 2026 payment');

-- Customer 5 old payments (before suspension)
INSERT INTO payments (billing_id, customer_id, or_number, payment_date, amount_paid, payment_method, cashier_id, remarks) VALUES
(13, 5, 'OR-2025-065', '2025-10-09', 899.00, 'cash', 1, 'October 2025 payment'),
(14, 5, 'OR-2025-082', '2025-11-10', 899.00, 'cash', 1, 'November 2025 payment - last payment before suspension');

-- Insert Additional Users (Accounting and Cashier)
-- Password for all is: password123
INSERT INTO users (username, password, full_name, email, role) VALUES
('accounting', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Rachel Catalan', 'accounting@arnovalink.com', 'accounting'),
('cashier', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Balcena', 'cashier@arnovalink.com', 'cashier');

-- Insert Sample Activity Logs
INSERT INTO activity_logs (user_id, action, table_name, record_id, description, ip_address) VALUES
(1, 'LOGIN', 'users', 1, 'User logged in', '127.0.0.1'),
(1, 'ADD_CUSTOMER', 'customers', 1, 'Added customer: DELA CRUZ, JUAN', '127.0.0.1'),
(1, 'ADD_CUSTOMER', 'customers', 2, 'Added customer: SANTOS, MARIA', '127.0.0.1'),
(1, 'GENERATE_BILLING', 'billings', NULL, 'Generated billings for January 2026', '127.0.0.1'),
(1, 'RECORD_PAYMENT', 'payments', 1, 'Recorded payment OR-2025-001', '127.0.0.1'),
(1, 'RECORD_PAYMENT', 'payments', 2, 'Recorded payment OR-2026-015', '127.0.0.1'),
(2, 'LOGIN', 'users', 2, 'User logged in', '127.0.0.1'),
(3, 'LOGIN', 'users', 3, 'User logged in', '127.0.0.1'),
(3, 'RECORD_PAYMENT', 'payments', 8, 'Recorded payment OR-2026-020', '127.0.0.1');

-- Create View for Unpaid Subscriptions
CREATE VIEW v_unpaid_subscriptions AS
SELECT 
    c.customer_id,
    c.account_number,
    c.subscriber_name,
    c.address,
    a.area_name,
    c.tel_no,
    b.billing_id,
    b.billing_month,
    b.billing_year,
    b.net_amount,
    b.status,
    b.due_date,
    DATEDIFF(CURDATE(), b.due_date) as days_overdue
FROM customers c
JOIN billings b ON c.customer_id = b.customer_id
LEFT JOIN areas a ON c.area_id = a.area_id
WHERE b.status IN ('unpaid', 'partial')
ORDER BY b.billing_year DESC, b.billing_month DESC;

-- Create View for Payment Summary
CREATE VIEW v_payment_summary AS
SELECT 
    c.customer_id,
    c.account_number,
    c.subscriber_name,
    COUNT(DISTINCT b.billing_id) as total_billings,
    COUNT(DISTINCT CASE WHEN b.status = 'paid' THEN b.billing_id END) as paid_count,
    COUNT(DISTINCT CASE WHEN b.status = 'unpaid' THEN b.billing_id END) as unpaid_count,
    SUM(CASE WHEN b.status = 'paid' THEN b.net_amount ELSE 0 END) as total_paid,
    SUM(CASE WHEN b.status = 'unpaid' THEN b.net_amount ELSE 0 END) as total_unpaid
FROM customers c
LEFT JOIN billings b ON c.customer_id = b.customer_id
GROUP BY c.customer_id, c.account_number, c.subscriber_name;