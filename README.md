# BSDO Sale – MVP Sprint 1 (PHP/MySQL)

This is a production-ready starting point for your marketplace: clients, sellers, and admin. It includes role-based auth, product CRUD with **file uploads (stored on server, not URLs)**, simple chat, and an admin panel skeleton.

**Important Database Update:** If you're upgrading from an earlier version, run `update_database_rtmp_invitation.php` to update your database schema with RTMP/HLS streaming support and invitation code functionality.

---

## 1) Folder Structure

```
bsdo-sale/
├── .htaccess
├── README.md
├── config/
│   ├── config.example.php
│   └── config.php            # copy from example & set DB creds
├── includes/
│   ├── db.php                # PDO connection
│   ├── auth.php              # session, login-required & role guards
│   ├── helpers.php           # misc helpers (flash, csrf, sanitize)
│   ├── upload.php            # image upload handler
│   └── middleware.php        # route protections
├── public/
│   ├── index.php             # homepage listing
│   ├── product.php           # product details page
│   ├── search.php            # search/filter results
│   ├── assets/
│   │   ├── css/
│   │   │   └── style.css
│   │   ├── js/
│   │   │   └── app.js
│   │   └── img/
│   └── uploads/
│       └── products/         # uploaded product images (writable)
├── auth/
│   ├── login.php
│   ├── register.php
│   └── logout.php
├── seller/
│   ├── dashboard.php
│   ├── add_product.php
│   ├── edit_product.php
│   ├── my_products.php
│   └── chats.php
├── client/
│   └── chats.php
├── admin/
│   ├── dashboard.php
│   ├── users.php
│   └── products.php
└── sql/
    └── schema.sql
```

> Make sure `public/uploads/products` is **writable** by the web server (e.g., `chmod -R 775`).

---

## 2) New Feature: Live Streaming for Sellers

Sellers can now go live to showcase their products and interact with clients in real-time. This feature includes:

- Real-time video streaming with camera support
- Product showcasing during live sessions
- Special pricing and discounts for featured products
- Live chat between sellers and clients
- Automatic stream ending when seller finishes

### Live Streaming Files:
```
├── seller/
│   ├── live_stream.php       # Main live streaming interface
│   └── live_stream_webrtc.php # Advanced WebRTC streaming interface
├── live_streams.php          # Client view of all live streams
├── watch_stream.php          # Client interface for watching streams
└── database_schema.sql       # Updated schema with live streaming tables
```

### Database Tables for Live Streaming:
- `live_streams` - Stores information about live streams
- `live_stream_products` - Links products to live streams with special pricing
- `live_stream_viewers` - Tracks viewers watching streams
- `live_stream_comments` - Stores chat messages during streams
- `live_stream_analytics` - Stores analytics data for streams

---

## 3) Database Schema (MySQL/MariaDB): `sql/schema.sql`

```sql
-- Create database (optional)
-- CREATE DATABASE IF NOT EXISTS bsdo_sale DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE bsdo_sale;

-- =====================================================
-- BSDO SALE MARKETPLACE - COMPLETE DATABASE SCHEMA
-- Version: 1.0
-- Compatible: MySQL 5.7+ / MariaDB 10.3+
-- Author: System Administrator
-- Created: 2024
-- =====================================================

-- =====================================================
-- DATABASE CREATION AND CONFIGURATION
-- =====================================================

-- Create database (uncomment if needed)
-- CREATE DATABASE bsdo_sale CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE bsdo_sale;

-- Set SQL mode for compatibility
SET SQL_MODE = 'NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO';

-- =====================================================
-- 1. USERS TABLE - Main user registration and authentication
-- =====================================================

CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'seller', 'client') NOT NULL DEFAULT 'client',
    
    -- Profile Information
    profile_picture VARCHAR(255) DEFAULT NULL,
    location VARCHAR(100) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    date_of_birth DATE DEFAULT NULL,
    gender ENUM('male', 'female', 'other') DEFAULT NULL,
    
    -- Account Status
    is_active BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    email_verified BOOLEAN DEFAULT FALSE,
    phone_verified BOOLEAN DEFAULT FALSE,
    
    -- Security & Authentication
    verification_token VARCHAR(100) DEFAULT NULL,
    reset_token VARCHAR(100) DEFAULT NULL,
    reset_token_expires DATETIME DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    login_attempts INT DEFAULT 0,
    account_locked_until DATETIME DEFAULT NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL, -- Soft delete
    
    -- Indexing for performance
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_role (role),
    INDEX idx_location (location),
    INDEX idx_created_at (created_at),
    INDEX idx_active_users (is_active, deleted_at)
);

-- =====================================================
-- 2. USER PROFILES - Extended profile information
-- =====================================================

CREATE TABLE user_profiles (
    profile_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    
    -- Contact Information
    secondary_email VARCHAR(100) DEFAULT NULL,
    secondary_phone VARCHAR(20) DEFAULT NULL,
    website VARCHAR(255) DEFAULT NULL,
    
    -- Address Information
    address_line1 VARCHAR(255) DEFAULT NULL,
    address_line2 VARCHAR(255) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    district VARCHAR(100) DEFAULT NULL,
    province VARCHAR(100) DEFAULT NULL,
    postal_code VARCHAR(20) DEFAULT NULL,
    country VARCHAR(100) DEFAULT 'Rwanda',
    
    -- Social Media Links
    facebook_url VARCHAR(255) DEFAULT NULL,
    twitter_url VARCHAR(255) DEFAULT NULL,
    instagram_url VARCHAR(255) DEFAULT NULL,
    linkedin_url VARCHAR(255) DEFAULT NULL,
    whatsapp_number VARCHAR(20) DEFAULT NULL,
    
    -- Preferences
    language_preference VARCHAR(10) DEFAULT 'en',
    currency_preference VARCHAR(10) DEFAULT 'RWF',
    timezone VARCHAR(50) DEFAULT 'Africa/Kigali',
    notification_email BOOLEAN DEFAULT TRUE,
    notification_sms BOOLEAN DEFAULT TRUE,
    notification_push BOOLEAN DEFAULT TRUE,
    
    -- Marketing & Communication
    marketing_emails BOOLEAN DEFAULT FALSE,
    newsletter_subscription BOOLEAN DEFAULT FALSE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
);

-- =====================================================
-- 3. SELLER PROFILES - Specific information for sellers
-- =====================================================

CREATE TABLE seller_profiles (
    seller_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    
    -- Business Information
    business_name VARCHAR(200) DEFAULT NULL,
    business_type ENUM('individual', 'small_business', 'company', 'corporation') DEFAULT 'individual',
    business_registration_number VARCHAR(100) DEFAULT NULL,
    tax_identification_number VARCHAR(100) DEFAULT NULL,
    
    -- Verification & Trust
    identity_verified BOOLEAN DEFAULT FALSE,
    business_verified BOOLEAN DEFAULT FALSE,
    phone_verified BOOLEAN DEFAULT FALSE,
    address_verified BOOLEAN DEFAULT FALSE,
    
    -- Performance Metrics
    total_sales INT DEFAULT 0,
    total_products INT DEFAULT 0,
    average_rating DECIMAL(3,2) DEFAULT 0.00,
    total_reviews INT DEFAULT 0,
    response_rate DECIMAL(5,2) DEFAULT 0.00, -- Percentage
    response_time INT DEFAULT 0, -- Minutes
    
    -- Financial Information
    bank_name VARCHAR(100) DEFAULT NULL,
    bank_account_number VARCHAR(50) DEFAULT NULL,
    bank_account_name VARCHAR(100) DEFAULT NULL,
    mobile_money_number VARCHAR(20) DEFAULT NULL,
    mobile_money_provider ENUM('mtn', 'airtel', 'tigo') DEFAULT NULL,
    
    -- Store Settings
    store_name VARCHAR(200) DEFAULT NULL,
    store_description TEXT DEFAULT NULL,
    store_logo VARCHAR(255) DEFAULT NULL,
    store_banner VARCHAR(255) DEFAULT NULL,
    store_active BOOLEAN DEFAULT TRUE,
    
    -- Subscription & Membership
    membership_type ENUM('basic', 'premium', 'enterprise') DEFAULT 'basic',
    membership_expires DATE DEFAULT NULL,
    featured_seller BOOLEAN DEFAULT FALSE,
    
    -- Status
    seller_status ENUM('pending', 'approved', 'suspended', 'banned') DEFAULT 'pending',
    approval_date DATETIME DEFAULT NULL,
    suspension_reason TEXT DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_business_name (business_name),
    INDEX idx_seller_status (seller_status),
    INDEX idx_verification (identity_verified, business_verified),
    INDEX idx_rating (average_rating)
);

-- =====================================================
-- 4. ADMIN PROFILES - Administrative user information
-- =====================================================

CREATE TABLE admin_profiles (
    admin_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    
    -- Admin Role & Permissions
    admin_level ENUM('super_admin', 'admin', 'moderator', 'support') DEFAULT 'admin',
    department VARCHAR(100) DEFAULT NULL,
    employee_id VARCHAR(50) DEFAULT NULL,
    
    -- Permissions (stored as TEXT for compatibility)
    permissions TEXT DEFAULT NULL,
    
    -- Access Control
    can_manage_users BOOLEAN DEFAULT FALSE,
    can_manage_products BOOLEAN DEFAULT FALSE,
    can_manage_orders BOOLEAN DEFAULT FALSE,
    can_manage_payments BOOLEAN DEFAULT FALSE,
    can_manage_settings BOOLEAN DEFAULT FALSE,
    can_view_analytics BOOLEAN DEFAULT FALSE,
    can_moderate_content BOOLEAN DEFAULT FALSE,
    can_handle_disputes BOOLEAN DEFAULT FALSE,
    
    -- Activity Tracking
    last_activity DATETIME DEFAULT NULL,
    total_actions INT DEFAULT 0,
    
    -- Status
    admin_status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_admin_level (admin_level),
    INDEX idx_admin_status (admin_status)
);

-- =====================================================
-- 5. USER SESSIONS - Track user login sessions
-- =====================================================

CREATE TABLE user_sessions (
    session_id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    
    -- Session Information
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    device_type ENUM('desktop', 'mobile', 'tablet') DEFAULT NULL,
    browser VARCHAR(100) DEFAULT NULL,
    operating_system VARCHAR(100) DEFAULT NULL,
    location VARCHAR(100) DEFAULT NULL,
    
    -- Session Status
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at),
    INDEX idx_last_activity (last_activity)
);

-- =====================================================
-- 6. USER ACTIVITY LOG - Track user actions
-- =====================================================

CREATE TABLE user_activity_log (
    activity_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT DEFAULT NULL,
    
    -- Activity Details
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100) DEFAULT NULL,
    record_id INT DEFAULT NULL,
    old_values TEXT DEFAULT NULL,
    new_values TEXT DEFAULT NULL,
    
    -- Request Information
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT DEFAULT NULL,
    request_url TEXT DEFAULT NULL,
    request_method VARCHAR(10) DEFAULT NULL,
    
    -- Additional Context
    description TEXT DEFAULT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_severity (severity)
);

-- =====================================================
-- 7. USER NOTIFICATIONS - System notifications
-- =====================================================

CREATE TABLE user_notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    
    -- Notification Content
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    notification_type ENUM('info', 'success', 'warning', 'error', 'promotion') DEFAULT 'info',
    category ENUM('system', 'order', 'payment', 'security', 'marketing', 'social') DEFAULT 'system',
    
    -- Delivery
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME DEFAULT NULL,
    delivery_method ENUM('in_app', 'email', 'sms', 'push') DEFAULT 'in_app',
    
    -- Action & Context
    action_url VARCHAR(500) DEFAULT NULL,
    action_text VARCHAR(100) DEFAULT NULL,
    related_id INT DEFAULT NULL,
    related_type VARCHAR(50) DEFAULT NULL,
    
    -- Scheduling
    scheduled_at DATETIME DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_type_category (notification_type, category),
    INDEX idx_scheduled_at (scheduled_at),
    INDEX idx_created_at (created_at)
);

-- =====================================================
-- TRIGGERS FOR AUTOMATION
-- =====================================================

-- Trigger to automatically set session expiry
DELIMITER //
CREATE TRIGGER set_session_expiry 
BEFORE INSERT ON user_sessions
FOR EACH ROW
BEGIN
    -- Set expiry to 30 days from creation if not provided
    IF NEW.expires_at IS NULL THEN
        SET NEW.expires_at = DATE_ADD(COALESCE(NEW.created_at, NOW()), INTERVAL 30 DAY);
    END IF;
    
    -- Set last_activity to created_at on insert
    IF NEW.last_activity IS NULL THEN
        SET NEW.last_activity = COALESCE(NEW.created_at, NOW());
    END IF;
END //
DELIMITER ;

-- Trigger to log user updates
DELIMITER //
CREATE TRIGGER user_update_log 
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    INSERT INTO user_activity_log (
        user_id, 
        action, 
        table_name, 
        record_id, 
        old_values, 
        new_values,
        ip_address,
        description
    ) VALUES (
        NEW.user_id,
        'UPDATE',
        'users',
        NEW.user_id,
        CONCAT('{"email":"', COALESCE(OLD.email, ''), '","phone":"', COALESCE(OLD.phone, ''), '","role":"', COALESCE(OLD.role, ''), '","is_active":', COALESCE(OLD.is_active, 0), '}'),
        CONCAT('{"email":"', COALESCE(NEW.email, ''), '","phone":"', COALESCE(NEW.phone, ''), '","role":"', COALESCE(NEW.role, ''), '","is_active":', COALESCE(NEW.is_active, 0), '}'),
        '127.0.0.1',
        'User information updated'
    );
END //
DELIMITER ;

-- Trigger to update seller statistics
DELIMITER //
CREATE TRIGGER update_seller_stats
BEFORE UPDATE ON seller_profiles
FOR EACH ROW
BEGIN
    -- Update response rate calculation
    IF NEW.total_reviews > 0 THEN
        SET NEW.response_rate = (NEW.total_sales / NEW.total_reviews) * 100;
    END IF;
    
    -- Ensure valid rating range
    IF NEW.average_rating < 0 THEN
        SET NEW.average_rating = 0.00;
    ELSEIF NEW.average_rating > 5 THEN
        SET NEW.average_rating = 5.00;
    END IF;
END //
DELIMITER ;

-- =====================================================
-- STORED PROCEDURES
-- =====================================================

-- Procedure to create a new user with profile
DELIMITER //
CREATE PROCEDURE CreateUser(
    IN p_full_name VARCHAR(100),
    IN p_email VARCHAR(100),
    IN p_phone VARCHAR(20),
    IN p_password_hash VARCHAR(255),
    IN p_role ENUM('admin', 'seller', 'client'),
    IN p_location VARCHAR(100)
)
BEGIN
    DECLARE new_user_id INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    -- Start transaction
    START TRANSACTION;
    
    -- Insert user
    INSERT INTO users (full_name, email, phone, password_hash, role, location)
    VALUES (p_full_name, p_email, p_phone, p_password_hash, p_role, p_location);
    
    SET new_user_id = LAST_INSERT_ID();
    
    -- Create user profile
    INSERT INTO user_profiles (user_id, country) VALUES (new_user_id, 'Rwanda');
    
    -- Create role-specific profile
    IF p_role = 'seller' THEN
        INSERT INTO seller_profiles (user_id) VALUES (new_user_id);
    ELSEIF p_role = 'admin' THEN
        INSERT INTO admin_profiles (user_id) VALUES (new_user_id);
    END IF;
    
    -- Commit transaction
    COMMIT;
    
    -- Return new user ID
    SELECT new_user_id as user_id, 'User created successfully' as message;
END //
DELIMITER ;

-- Procedure to create user session
DELIMITER //
CREATE PROCEDURE CreateUserSession(
    IN p_session_id VARCHAR(128),
    IN p_user_id INT,
    IN p_ip_address VARCHAR(45),
    IN p_user_agent TEXT,
    IN p_device_type ENUM('desktop', 'mobile', 'tablet'),
    IN p_browser VARCHAR(100),
    IN p_operating_system VARCHAR(100),
    IN p_location VARCHAR(100),
    IN p_duration_hours INT
)
BEGIN
    DECLARE session_expiry DATETIME;
    
    -- Set default duration if null or 0
    IF p_duration_hours IS NULL OR p_duration_hours <= 0 THEN
        SET p_duration_hours = 24;
    END IF;
    
    -- Calculate expiry time
    SET session_expiry = DATE_ADD(NOW(), INTERVAL p_duration_hours HOUR);
    
    -- Insert session
    INSERT INTO user_sessions (
        session_id, 
        user_id, 
        ip_address, 
        user_agent, 
        device_type, 
        browser, 
        operating_system, 
        location,
        expires_at
    ) VALUES (
        p_session_id,
        p_user_id,
        p_ip_address,
        p_user_agent,
        p_device_type,
        p_browser,
        p_operating_system,
        p_location,
        session_expiry
    );
    
    -- Update user's last login
    UPDATE users 
    SET last_login = NOW() 
    WHERE user_id = p_user_id;
    
    SELECT 'Session created successfully' as result, session_expiry as expires_at;
END //
DELIMITER ;

-- Procedure with default session duration
DELIMITER //
CREATE PROCEDURE CreateUserSessionDefault(
    IN p_session_id VARCHAR(128),
    IN p_user_id INT,
    IN p_ip_address VARCHAR(45),
    IN p_user_agent TEXT,
    IN p_device_type ENUM('desktop', 'mobile', 'tablet'),
    IN p_browser VARCHAR(100),
    IN p_operating_system VARCHAR(100),
    IN p_location VARCHAR(100)
)
BEGIN
    CALL CreateUserSession(
        p_session_id,
        p_user_id,
        p_ip_address,
        p_user_agent,
        p_device_type,
        p_browser,
        p_operating_system,
        p_location,
        24 -- Default 24 hours
    );
END //
DELIMITER ;

-- Procedure to authenticate user
DELIMITER //
CREATE PROCEDURE AuthenticateUser(
    IN p_email_or_phone VARCHAR(100),
    IN p_password_hash VARCHAR(255)
)
BEGIN
    SELECT 
        u.user_id,
        u.full_name,
        u.email,
        u.phone,
        u.role,
        u.is_active,
        u.is_verified,
        u.account_locked_until,
        u.login_attempts
    FROM users u
    WHERE (u.email = p_email_or_phone OR u.phone = p_email_or_phone)
        AND u.password_hash = p_password_hash
        AND u.is_active = 1
        AND u.deleted_at IS NULL
        AND (u.account_locked_until IS NULL OR u.account_locked_until < NOW());
END //
DELIMITER ;

-- Procedure to update login attempts
DELIMITER //
CREATE PROCEDURE UpdateLoginAttempts(
    IN p_user_id INT,
    IN p_success BOOLEAN
)
BEGIN
    IF p_success = 1 THEN
        -- Reset login attempts on successful login
        UPDATE users 
        SET login_attempts = 0, 
            account_locked_until = NULL,
            last_login = NOW()
        WHERE user_id = p_user_id;
    ELSE
        -- Increment login attempts on failure
        UPDATE users 
        SET login_attempts = login_attempts + 1,
            account_locked_until = CASE 
                WHEN login_attempts + 1 >= 5 THEN DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                ELSE account_locked_until
            END
        WHERE user_id = p_user_id;
    END IF;
    
    SELECT ROW_COUNT() as affected_rows;
END //
DELIMITER ;

-- Procedure to get user by session
DELIMITER //
CREATE PROCEDURE GetUserBySession(
    IN p_session_id VARCHAR(128)
)
BEGIN
    SELECT 
        u.user_id,
        u.full_name,
        u.email,
        u.phone,
        u.role,
        u.is_active,
        u.is_verified,
        us.session_id,
        us.expires_at,
        us.last_activity
    FROM users u
    INNER JOIN user_sessions us ON u.user_id = us.user_id
    WHERE us.session_id = p_session_id
        AND us.is_active = 1
        AND us.expires_at > NOW()
        AND u.is_active = 1
        AND u.deleted_at IS NULL;
END //
DELIMITER ;

-- Procedure to cleanup expired sessions
DELIMITER //
CREATE PROCEDURE CleanupExpiredSessions()
BEGIN
    DECLARE deleted_count INT DEFAULT 0;
    DECLARE updated_count INT DEFAULT 0;
    
    -- Delete sessions that expired more than 7 days ago
    DELETE FROM user_sessions 
    WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
    
    SET deleted_count = ROW_COUNT();
    
    -- Mark recently expired sessions as inactive
    UPDATE user_sessions 
    SET is_active = 0 
    WHERE expires_at < NOW() AND is_active = 1;
    
    SET updated_count = ROW_COUNT();
    
    SELECT deleted_count as deleted_sessions, updated_count as deactivated_sessions;
END //
DELIMITER ;

-- =====================================================
-- FUNCTIONS
-- =====================================================

-- Function to check if session is valid
DELIMITER //
CREATE FUNCTION IsSessionValid(p_session_id VARCHAR(128))
RETURNS TINYINT(1)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE session_count INT DEFAULT 0;
    
    SELECT COUNT(*) INTO session_count
    FROM user_sessions 
    WHERE session_id = p_session_id 
        AND is_active = 1 
        AND expires_at > NOW();
    
    RETURN session_count > 0;
END //
DELIMITER ;

-- Function to get user role by ID
DELIMITER //
CREATE FUNCTION GetUserRole(p_user_id INT)
RETURNS VARCHAR(20)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE user_role VARCHAR(20) DEFAULT NULL;
    
    SELECT role INTO user_role
    FROM users 
    WHERE user_id = p_user_id 
        AND is_active = 1 
        AND deleted_at IS NULL;
    
    RETURN COALESCE(user_role, 'unknown');
END //
DELIMITER ;

-- =====================================================
-- VIEWS FOR DATA ACCESS
-- =====================================================

-- View for complete user information
CREATE VIEW user_complete_info AS
SELECT 
    u.user_id,
    u.full_name,
    u.email,
    u.phone,
    u.role,
    u.location,
    u.is_active,
    u.is_verified,
    u.last_login,
    u.created_at,
    
    -- Profile information
    up.city,
    up.district,
    up.country,
    up.language_preference,
    
    -- Seller information (if applicable)
    sp.business_name,
    sp.store_name,
    sp.average_rating,
    sp.total_sales,
    sp.seller_status,
    sp.membership_type,
    
    -- Admin information (if applicable)
    ap.admin_level,
    ap.department,
    ap.admin_status

FROM users u
LEFT JOIN user_profiles up ON u.user_id = up.user_id
LEFT JOIN seller_profiles sp ON u.user_id = sp.user_id
LEFT JOIN admin_profiles ap ON u.user_id = ap.user_id
WHERE u.deleted_at IS NULL;

-- View for active sellers only
CREATE VIEW active_sellers AS
SELECT 
    u.user_id,
    u.full_name,
    u.email,
    u.phone,
    u.location,
    sp.business_name,
    sp.store_name,
    sp.average_rating,
    sp.total_sales,
    sp.total_products,
    sp.membership_type,
    u.created_at
FROM users u
JOIN seller_profiles sp ON u.user_id = sp.user_id
WHERE u.is_active = TRUE 
    AND u.deleted_at IS NULL 
    AND sp.seller_status = 'approved';

-- View for active user sessions
CREATE VIEW active_sessions AS
SELECT 
    us.session_id,
    us.user_id,
    u.full_name,
    u.email,
    us.ip_address,
    us.device_type,
    us.browser,
    us.operating_system,
    us.location,
    us.created_at,
    us.last_activity,
    us.expires_at,
    TIMESTAMPDIFF(MINUTE, us.last_activity, NOW()) as minutes_inactive
FROM user_sessions us
JOIN users u ON us.user_id = u.user_id
WHERE us.is_active = TRUE 
    AND us.expires_at > NOW()
ORDER BY us.last_activity DESC;

-- =====================================================
-- PERFORMANCE INDEXES
-- =====================================================

-- Additional composite indexes for better performance
CREATE INDEX idx_user_role_status ON users(role, is_active, deleted_at);
CREATE INDEX idx_seller_performance ON seller_profiles(average_rating, total_sales);
CREATE INDEX idx_notification_user_unread ON user_notifications(user_id, is_read, created_at);
CREATE INDEX idx_session_active ON user_sessions(is_active, expires_at);
CREATE INDEX idx_activity_user_date ON user_activity_log(user_id, created_at);

-- =====================================================
-- SAMPLE DATA INSERTION
-- =====================================================

-- Insert default super admin (password: 'admin123' - should be changed immediately)
INSERT INTO users (
    full_name, 
    email, 
    phone, 
    password_hash, 
    role, 
    is_active, 
    is_verified, 
    email_verified, 
    phone_verified
) VALUES (
    'System Administrator',
    'admin@bsdosale.rw',
    '+250788000000',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Hash of 'admin123'
    'admin',
    TRUE,
    TRUE,
    TRUE,
    TRUE
);

-- Create admin profile for the default admin
INSERT INTO admin_profiles (
    user_id,
    admin_level,
    department,
    can_manage_users,
    can_manage_products,
    can_manage_orders,
    can_manage_payments,
    can_manage_settings,
    can_view_analytics,
    can_moderate_content,
    can_handle_disputes,
    admin_status
) VALUES (
    1, -- Assuming the admin user gets ID 1
    'super_admin',
    'IT & Development',
    TRUE,
    TRUE,
    TRUE,
    TRUE,
    TRUE,
    TRUE,
    TRUE,
    TRUE,
    'active'
);

-- Create user profile for admin
INSERT INTO user_profiles (user_id, country, city, district) 
VALUES (1, 'Rwanda', 'Kigali', 'Gasabo');

-- Sample Client User
INSERT INTO users (full_name, email, phone, password_hash, role, location, is_active, is_verified) 
VALUES ('John Mukiza', 'john.mukiza@example.com', '+250788123456', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'Kigali', TRUE, TRUE);

-- Create profile for client
INSERT INTO user_profiles (user_id, country, city, district) 
VALUES (2, 'Rwanda', 'Kigali', 'Nyarugenge');

-- Sample Seller User
INSERT INTO users (full_name, email, phone, password_hash, role, location, is_active, is_verified) 
VALUES ('Marie Uwimana', 'marie.uwimana@example.com', '+250788654321', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'seller', 'Kigali', TRUE, TRUE);

-- Create profiles for seller
INSERT INTO user_profiles (user_id, country, city, district) 
VALUES (3, 'Rwanda', 'Kigali', 'Kicukiro');

INSERT INTO seller_profiles (
    user_id, 
    business_name, 
    business_type, 
    store_name, 
    seller_status, 
    membership_type,
    identity_verified,
    phone_verified
) VALUES (
    3, 
    'Uwimana Electronics', 
    'small_business', 
    'Marie\'s Electronics Store', 
    'approved', 
    'basic',
    TRUE,
    TRUE
);

-- =====================================================
-- USAGE EXAMPLES AND TESTING
-- =====================================================

-- Example 1: Create a new user
/*
CALL CreateUser(
    'Test User',
    'test@example.com',
    '+250788999999',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'client',
    'Kigali'
);
*/

-- Example 2: Create a user session
/*
CALL CreateUserSession(
    'sess_abc123xyz789', 
    1, 
    '192.168.1.100', 
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', 
    'desktop', 
    'Chrome', 
    'Windows 10', 
    'Kigali, Rwanda',
    168
);
*/

-- Example 3: Authenticate user
/*
CALL AuthenticateUser('admin@bsdosale.rw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
*/

-- Example 4: Check session validity
/*
SELECT IsSessionValid('sess_abc123xyz789') as is_valid;
*/

-- Example 5: Get user by session
/*
CALL GetUserBySession('sess_abc123xyz789');
*/

-- Example 6: Update login attempts (failed login)
/*
CALL UpdateLoginAttempts(1, FALSE);
*/

-- Example 7: Update login attempts (successful login)
/*
CALL UpdateLoginAttempts(1, TRUE);
*/

-- Example 8: View all users with complete information
/*
SELECT * FROM user_complete_info LIMIT 10;
*/

-- Example 9: View active sellers
/*
SELECT * FROM active_sellers;
*/

-- Example 10: View active sessions
/*
SELECT * FROM active_sessions;
*/

-- Example 11: Cleanup expired sessions
/*
CALL CleanupExpiredSessions();
*/

-- Example 12: Check user role
/*
SELECT GetUserRole(1) as role;
*/

-- =====================================================
-- MAINTENANCE AND MONITORING QUERIES
-- =====================================================

-- Check database health
SELECT 
    'users' as table_name, 
    COUNT(*) as total_records,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_records,
    SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) as deleted_records
FROM users

UNION ALL

SELECT 
    'user_sessions' as table_name,
    COUNT(*) as total_records,
    SUM(CASE WHEN is_active = 1 AND expires_at > NOW() THEN 1 ELSE 0 END) as active_records,
    SUM(CASE WHEN expires_at < NOW() THEN 1 ELSE 0 END) as expired_records
FROM user_sessions

UNION ALL

SELECT 
    'seller_profiles' as table_name,
    COUNT(*) as total_records,
    SUM(CASE WHEN seller_status = 'approved' THEN 1 ELSE 0 END) as approved_records,
    SUM(CASE WHEN seller_status = 'pending' THEN 1 ELSE 0 END) as pending_records
FROM seller_profiles;

-- Check recent activity
SELECT 
    DATE(created_at) as date,
    COUNT(*) as activities,
    COUNT(DISTINCT user_id) as unique_users
FROM user_activity_log 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;

-- Check notification delivery status
SELECT 
    notification_type,
    delivery_method,
    COUNT(*) as total,
    SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read_count,
    ROUND(SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as read_percentage
FROM user_notifications
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY notification_type, delivery_method
ORDER BY total DESC;

-- =====================================================
-- SECURITY AND AUDIT QUERIES
-- =====================================================

-- Find suspicious login attempts
SELECT 
    u.user_id,
    u.full_name,
    u.email,
    u.login_attempts,
    u.account_locked_until,
    u.last_login
FROM users u
WHERE u.login_attempts >= 3 OR u.account_locked_until > NOW()
ORDER BY u.login_attempts DESC, u.account_locked_until DESC;

-- Check for multiple sessions from same user
SELECT 
    u.user_id,
    u.full_name,
    u.email,
    COUNT(us.session_id) as active_sessions,
    GROUP_CONCAT(DISTINCT us.ip_address) as ip_addresses
FROM users u
JOIN user_sessions us ON u.user_id = us.user_id
WHERE us.is_active = 1 AND us.expires_at > NOW()
GROUP BY u.user_id, u.full_name, u.email
HAVING active_sessions > 3
ORDER BY active_sessions DESC;

-- Audit critical actions
SELECT 
    ual.activity_id,
    u.full_name,
    u.email,
    ual.action,
    ual.table_name,
    ual.severity,
    ual.description,
    ual.created_at
FROM user_activity_log ual
LEFT JOIN users u ON ual.user_id = u.user_id
WHERE ual.severity IN ('high', 'critical')
    AND ual.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY ual.created_at DESC;

-- =====================================================
-- BACKUP AND RECOVERY CONSIDERATIONS
-- =====================================================

/*
BACKUP STRATEGY RECOMMENDATIONS:

1. Full Database Backup (Daily):
   - mysqldump --single-transaction --routines --triggers --all-databases

2. Incremental Backup (Every 4 hours):
   - Use binary log files for point-in-time recovery

3. Table-Specific Backup (Critical tables hourly):
   - users, user_sessions, seller_profiles, admin_profiles

4. Configuration Backup:
   - Store procedure definitions, triggers, views separately

RECOVERY TESTING:
   - Test backup restoration monthly
   - Verify data integrity after restoration
   - Document recovery procedures
*/

-- =====================================================
-- PERFORMANCE OPTIMIZATION QUERIES
-- =====================================================

-- Check table sizes and optimization needs
SELECT 
    TABLE_NAME,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) as table_size_mb,
    table_rows,
    ROUND((data_free / 1024 / 1024), 2) as fragmented_mb
FROM INFORMATION_SCHEMA.TABLES 
WHERE table_schema = DATABASE()
    AND TABLE_NAME IN ('users', 'user_profiles', 'seller_profiles', 'admin_profiles', 'user_sessions', 'user_activity_log', 'user_notifications')
ORDER BY table_size_mb DESC;

-- Check index usage
SHOW INDEX FROM users;
SHOW INDEX FROM user_sessions;
SHOW INDEX FROM seller_profiles;

-- Analyze slow queries (enable slow query log first)
/*
To enable slow query logging, add to my.cnf:
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2

Then analyze with:
mysqldumpslow /var/log/mysql/slow.log
*/

-- =====================================================
-- SCHEDULED MAINTENANCE TASKS
-- =====================================================

-- Clean up old activity logs (keep 90 days)
-- DELETE FROM user_activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Clean up old notifications (keep 30 days for read, 7 days for unread expired)
-- DELETE FROM user_notifications 
-- WHERE (is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY))
--    OR (is_read = 0 AND expires_at IS NOT NULL AND expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY));

-- Optimize tables (run weekly during low traffic)
-- OPTIMIZE TABLE users, user_profiles, seller_profiles, admin_profiles, user_sessions, user_activity_log, user_notifications;

-- Update table statistics
-- ANALYZE TABLE users, user_profiles, seller_profiles, admin_profiles, user_sessions, user_activity_log, user_notifications;

-- =====================================================
-- EVENT SCHEDULER (Optional Automation)
-- =====================================================

-- Enable event scheduler
-- SET GLOBAL event_scheduler = ON;

-- Auto-cleanup expired sessions (daily at 2 AM)
/*
CREATE EVENT IF NOT EXISTS cleanup_sessions
ON SCHEDULE EVERY 1 DAY
STARTS '2024-01-01 02:00:00'
DO
    CALL CleanupExpiredSessions();
*/

-- Auto-cleanup old activity logs (weekly on Sunday at 3 AM)
/*
CREATE EVENT IF NOT EXISTS cleanup_activity_logs
ON SCHEDULE EVERY 1 WEEK
STARTS '2024-01-07 03:00:00'
DO
    DELETE FROM user_activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
*/

-- Auto-optimize tables (monthly on 1st at 4 AM)
/*
CREATE EVENT IF NOT EXISTS optimize_tables
ON SCHEDULE EVERY 1 MONTH
STARTS '2024-01-01 04:00:00'
DO
BEGIN
    OPTIMIZE TABLE users;
    OPTIMIZE TABLE user_profiles;
    OPTIMIZE TABLE seller_profiles;
    OPTIMIZE TABLE admin_profiles;
    OPTIMIZE TABLE user_sessions;
    OPTIMIZE TABLE user_activity_log;
    OPTIMIZE TABLE user_notifications;
END
*/

-- =====================================================
-- VERIFICATION AND TESTING
-- =====================================================

-- Verify all tables were created
SELECT 
    TABLE_NAME,
    ENGINE,
    TABLE_ROWS,
    CREATE_TIME
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME;

-- Verify all stored procedures and functions
SELECT 
    ROUTINE_NAME,
    ROUTINE_TYPE,
    CREATED,
    LAST_ALTERED
FROM INFORMATION_SCHEMA.ROUTINES 
WHERE ROUTINE_SCHEMA = DATABASE()
ORDER BY ROUTINE_TYPE, ROUTINE_NAME;

-- Verify all triggers
SELECT 
    TRIGGER_NAME,
    EVENT_MANIPULATION,
    EVENT_OBJECT_TABLE,
    ACTION_TIMING
FROM INFORMATION_SCHEMA.TRIGGERS 
WHERE TRIGGER_SCHEMA = DATABASE()
ORDER BY EVENT_OBJECT_TABLE, ACTION_TIMING;

-- Verify all views
SELECT 
    TABLE_NAME as VIEW_NAME,
    IS_UPDATABLE
FROM INFORMATION_SCHEMA.VIEWS 
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME;

-- Test data integrity
SELECT 
    'users' as table_name,
    COUNT(*) as total_records,
    COUNT(DISTINCT email) as unique_emails,
    COUNT(DISTINCT phone) as unique_phones
FROM users

UNION ALL

SELECT 
    'user_profiles' as table_name,
    COUNT(*) as total_records,
    COUNT(DISTINCT user_id) as unique_users,
    0 as placeholder
FROM user_profiles

UNION ALL

SELECT 
    'seller_profiles' as table_name,
    COUNT(*) as total_records,
    COUNT(DISTINCT user_id) as unique_users,
    0 as placeholder
FROM seller_profiles

UNION ALL

SELECT 
    'admin_profiles' as table_name,
    COUNT(*) as total_records,
    COUNT(DISTINCT user_id) as unique_users,
    0 as placeholder
FROM admin_profiles;

-- =====================================================
-- FINAL SETUP CHECKLIST
-- =====================================================

/*
POST-INSTALLATION CHECKLIST:

1. SECURITY:
   ✓ Change default admin password immediately
   ✓ Create database user with limited privileges
   ✓ Enable SSL for database connections
   ✓ Configure firewall rules
   ✓ Set up regular security audits

2. PERFORMANCE:
   ✓ Configure MySQL/MariaDB memory settings
   ✓ Enable query cache if appropriate
   ✓ Set up monitoring for slow queries
   ✓ Configure log rotation

3. BACKUP:
   ✓ Set up automated daily backups
   ✓ Test backup restoration procedure
   ✓ Configure offsite backup storage
   ✓ Document recovery procedures

4. MONITORING:
   ✓ Set up database monitoring
   ✓ Configure alerts for critical issues
   ✓ Monitor disk space usage
   ✓ Track connection limits

5. MAINTENANCE:
   ✓ Schedule regular table optimization
   ✓ Set up log cleanup procedures
   ✓ Plan for data archiving
   ✓ Document maintenance procedures

6. TESTING:
   ✓ Test all stored procedures
   ✓ Verify trigger functionality
   ✓ Test user registration flow
   ✓ Verify session management
   ✓ Test authentication procedures

7. DOCUMENTATION:
   ✓ Document all custom procedures
   ✓ Create user management guide
   ✓ Document troubleshooting steps
   ✓ Create database schema diagram
*/

-- =====================================================
-- END OF COMPLETE DATABASE SCHEMA
-- Version: 1.0 - Production Ready
-- Total Tables: 7
-- Total Procedures: 6
-- Total Functions: 2
-- Total Triggers: 3
-- Total Views: 3
-- =====================================================