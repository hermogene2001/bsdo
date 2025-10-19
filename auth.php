<?php
// config/database.php
class Database {
    private $host = 'localhost';
    private $db_name = 'bsdo_sale';
    private $username = 'root';
    private $password = '';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// classes/User.php
class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $full_name;
    public $email;
    public $password;
    public $created_at;
    public $updated_at;
    public $email_verified;
    public $remember_token;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Register new user
    public function register() {
        $query = "INSERT INTO " . $this->table_name . " 
                 SET full_name=:full_name, email=:email, password=:password, created_at=:created_at";

        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $this->full_name = htmlspecialchars(strip_tags($this->full_name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        $this->created_at = date('Y-m-d H:i:s');

        // Bind values
        $stmt->bindParam(":full_name", $this->full_name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":created_at", $this->created_at);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    // Login user
    public function login($email, $password) {
        $query = "SELECT id, full_name, email, password FROM " . $this->table_name . " 
                 WHERE email = :email LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        $num = $stmt->rowCount();

        if($num > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if(password_verify($password, $row['password'])) {
                $this->id = $row['id'];
                $this->full_name = $row['full_name'];
                $this->email = $row['email'];
                return true;
            }
        }
        return false;
    }

    // Check if email exists
    public function emailExists($email) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = :email LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // Update remember token
    public function updateRememberToken($token) {
        $query = "UPDATE " . $this->table_name . " SET remember_token = :token WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":token", $token);
        $stmt->bindParam(":id", $this->id);
        return $stmt->execute();
    }

    // Get user by remember token
    public function getUserByRememberToken($token) {
        $query = "SELECT id, full_name, email FROM " . $this->table_name . " 
                 WHERE remember_token = :token LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":token", $token);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->full_name = $row['full_name'];
            $this->email = $row['email'];
            return true;
        }
        return false;
    }
}

// api/register.php
<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../classes/User.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (empty($input['fullName']) || empty($input['email']) || empty($input['password'])) {
        throw new Exception('All fields are required');
    }

    // Validate email
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Validate password strength
    if (strlen($input['password']) < 8) {
        throw new Exception('Password must be at least 8 characters long');
    }

    if (!preg_match('/[A-Z]/', $input['password'])) {
        throw new Exception('Password must contain at least one uppercase letter');
    }

    if (!preg_match('/[a-z]/', $input['password'])) {
        throw new Exception('Password must contain at least one lowercase letter');
    }

    if (!preg_match('/\d/', $input['password'])) {
        throw new Exception('Password must contain at least one number');
    }

    // Password confirmation check
    if ($input['password'] !== $input['confirmPassword']) {
        throw new Exception('Passwords do not match');
    }

    // Terms agreement check
    if (!isset($input['agreeTerms']) || !$input['agreeTerms']) {
        throw new Exception('You must agree to the Terms of Service');
    }

    // Database connection
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);

    // Check if email already exists
    if ($user->emailExists($input['email'])) {
        throw new Exception('Email already exists');
    }

    // Set user properties
    $user->full_name = $input['fullName'];
    $user->email = $input['email'];
    $user->password = $input['password'];

    // Register user
    if ($user->register()) {
        // Create session
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_name'] = $user->full_name;
        $_SESSION['user_email'] = $user->email;

        // Log registration
        error_log("New user registered: " . $user->email . " at " . date('Y-m-d H:i:s'));

        echo json_encode([
            'success' => true,
            'message' => 'Account created successfully!',
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email
            ]
        ]);
    } else {
        throw new Exception('Registration failed. Please try again.');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

// api/login.php
<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../classes/User.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (empty($input['email']) || empty($input['password'])) {
        throw new Exception('Email and password are required');
    }

    // Validate email format
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Database connection
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);

    // Attempt login
    if ($user->login($input['email'], $input['password'])) {
        // Create session
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_name'] = $user->full_name;
        $_SESSION['user_email'] = $user->email;

        // Handle remember me
        if (isset($input['rememberMe']) && $input['rememberMe']) {
            $remember_token = bin2hex(random_bytes(32));
            $user->updateRememberToken($remember_token);
            setcookie('remember_token', $remember_token, time() + (30 * 24 * 60 * 60), '/'); // 30 days
        }

        // Log successful login
        error_log("User login: " . $user->email . " at " . date('Y-m-d H:i:s'));

        echo json_encode([
            'success' => true,
            'message' => 'Login successful!',
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email
            ]
        ]);
    } else {
        // Log failed login attempt
        error_log("Failed login attempt for: " . $input['email'] . " at " . date('Y-m-d H:i:s'));
        
        throw new Exception('Invalid email or password');
    }

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

// api/logout.php
<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Clear session
$_SESSION = array();
session_destroy();

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

echo json_encode([
    'success' => true,
    'message' => 'Logged out successfully'
]);
?>

// api/check_session.php
<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';
require_once '../classes/User.php';

$response = ['authenticated' => false, 'user' => null];

// Check session first
if (isset($_SESSION['user_id'])) {
    $response['authenticated'] = true;
    $response['user'] = [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email']
    ];
} 
// Check remember me cookie
elseif (isset($_COOKIE['remember_token'])) {
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);
    
    if ($user->getUserByRememberToken($_COOKIE['remember_token'])) {
        // Recreate session
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_name'] = $user->full_name;
        $_SESSION['user_email'] = $user->email;
        
        $response['authenticated'] = true;
        $response['user'] = [
            'id' => $user->id,
            'name' => $user->full_name,
            'email' => $user->email
        ];
    }
}

echo json_encode($response);
?>

// database/schema.sql
CREATE DATABASE IF NOT EXISTS bsdo_sale;
USE bsdo_sale;

CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email_verified BOOLEAN DEFAULT FALSE,
    remember_token VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_remember_token (remember_token)
);

-- Optional: Create a sessions table for better session management
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Optional: Create login attempts table for security
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255),
    ip_address VARCHAR(45),
    success BOOLEAN DEFAULT FALSE,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_time (email, attempted_at),
    INDEX idx_ip_time (ip_address, attempted_at)
);

-- Insert sample data (optional)
INSERT INTO users (full_name, email, password, email_verified) VALUES
('John Doe', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', TRUE),
('Jane Smith', 'jane@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', TRUE);
-- Note: The password above is hashed version of 'password'

// utils/helpers.php
<?php
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    
    if (!preg_match('/\d/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
    
    return $errors;
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function logSecurityEvent($event, $email = '', $ip = '') {
    $log_entry = date('Y-m-d H:i:s') . " - {$event}";
    if ($email) $log_entry .= " - Email: {$email}";
    if ($ip) $log_entry .= " - IP: {$ip}";
    $log_entry .= "\n";
    
    file_put_contents('../logs/security.log', $log_entry, FILE_APPEND | LOCK_EX);
}

function getRateLimitKey($ip, $email = '') {
    return 'rate_limit_' . md5($ip . '_' . $email);
}

function checkRateLimit($key, $max_attempts = 5, $time_window = 300) {
    // This would typically use Redis or Memcached
    // For now, using file-based storage (not recommended for production)
    $file = '../temp/rate_limits.json';
    $limits = [];
    
    if (file_exists($file)) {
        $limits = json_decode(file_get_contents($file), true) ?: [];
    }
    
    $current_time = time();
    $window_start = $current_time - $time_window;
    
    // Clean old entries
    foreach ($limits as $k => $data) {
        $limits[$k]['attempts'] = array_filter($data['attempts'], function($timestamp) use ($window_start) {
            return $timestamp > $window_start;
        });
        
        if (empty($limits[$k]['attempts'])) {
            unset($limits[$k]);
        }
    }
    
    // Check current key
    if (!isset($limits[$key])) {
        $limits[$key] = ['attempts' => []];
    }
    
    $attempts_count = count($limits[$key]['attempts']);
    
    if ($attempts_count >= $max_attempts) {
        return false; // Rate limit exceeded
    }
    
    // Add current attempt
    $limits[$key]['attempts'][] = $current_time;
    
    // Save back to file
    file_put_contents($file, json_encode($limits), LOCK_EX);
    
    return true; // Within rate limit
}
?>

// config/security.php
<?php
// Security configuration
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_RATE_LIMIT_WINDOW', 300); // 5 minutes
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('REMEMBER_TOKEN_LIFETIME', 2592000); // 30 days

// CSRF Protection
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Password hashing options
define('PASSWORD_HASH_OPTIONS', [
    'cost' => 12
]);

// Secure headers
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('Content-Security-Policy: default-src \'self\'');
}
?>