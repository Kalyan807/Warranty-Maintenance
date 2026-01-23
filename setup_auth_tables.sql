-- =====================================================
-- WarrantyMaintenance Database Setup Script
-- Run this in phpMyAdmin to create required tables
-- =====================================================

-- Make sure you're using the warrantymaintenance database
-- CREATE DATABASE IF NOT EXISTS warrantymaintenance;
-- USE warrantymaintenance;

-- =====================================================
-- SUPERVISORS TABLE - For Supervisor Login/Registration
-- =====================================================
CREATE TABLE IF NOT EXISTS supervisors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    address TEXT,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- UPDATE TECHNICIANS TABLE - Add password for login
-- =====================================================
-- Check if password column exists, if not add it
ALTER TABLE technicians 
ADD COLUMN IF NOT EXISTS password VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS address TEXT DEFAULT NULL;

-- If the above doesn't work (older MySQL), run these separately:
-- ALTER TABLE technicians ADD COLUMN password VARCHAR(255) DEFAULT NULL;
-- ALTER TABLE technicians ADD COLUMN address TEXT DEFAULT NULL;

-- =====================================================
-- USERS TABLE - For regular User Login (if not exists)
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    address TEXT,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'USER',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- SUMMARY OF TABLES AFTER RUNNING THIS SCRIPT:
-- =====================================================
-- 1. supervisors - For supervisor accounts (NEW)
-- 2. technicians - Updated with password & address columns
-- 3. users       - For regular user accounts (existing or new)
-- =====================================================
