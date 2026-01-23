-- SQL Schema for Separate Login/Registration Tables
-- Run these commands in your MySQL database

-- Supervisors Table
CREATE TABLE IF NOT EXISTS supervisors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    address TEXT,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Add password column to existing technicians table (if not exists)
ALTER TABLE technicians ADD COLUMN IF NOT EXISTS password VARCHAR(255);
ALTER TABLE technicians ADD COLUMN IF NOT EXISTS address TEXT;

-- Alternative: If the above doesn't work (MySQL version), use this:
-- ALTER TABLE technicians ADD COLUMN password VARCHAR(255);
-- ALTER TABLE technicians ADD COLUMN address TEXT;

-- Note: Users table remains for regular USER role accounts
-- The 'users' table already exists with: id, full_name, email, phone, address, password, role
