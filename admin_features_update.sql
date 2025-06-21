-- Admin Features Database Updates
-- Run these queries to add new features to your existing database

-- Add new columns to reports table
ALTER TABLE reports ADD COLUMN category VARCHAR(50) DEFAULT 'General';
ALTER TABLE reports ADD COLUMN admin_notes TEXT;
ALTER TABLE reports ADD COLUMN is_archived BOOLEAN DEFAULT FALSE;
ALTER TABLE reports ADD COLUMN archived_date DATETIME NULL;

-- Create admin activity log table
CREATE TABLE IF NOT EXISTS admin_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_username VARCHAR(100) NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Create upload activity log table
CREATE TABLE IF NOT EXISTS upload_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_name VARCHAR(255) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    status ENUM('success', 'failed') NOT NULL,
    error_message TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Add indexes for better performance
CREATE INDEX idx_reports_category ON reports(category);
CREATE INDEX idx_reports_archived ON reports(is_archived);
CREATE INDEX idx_reports_upload_date ON reports(upload_date);
CREATE INDEX idx_admin_activity_date ON admin_activity_log(created_at);
CREATE INDEX idx_upload_activity_date ON upload_activity_log(created_at);
