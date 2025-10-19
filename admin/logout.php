<?php
session_start();
require_once '../config.php';

// Initialize variables
$page_title = "Logging out...";
$logout_message = "You are being safely logged out.";

// Log the logout activity
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['user_role'] ?? 'unknown';
    $user_name = $_SESSION['user_name'] ?? 'Unknown User';
    
    // Determine which activity table to use based on user role
    if ($user_role === 'admin') {
        $stmt = $pdo->prepare("INSERT INTO admin_activities (admin_id, activity, ip_address) VALUES (?, ?, ?)");
        $activity = "Logged out from admin panel";
    } elseif ($user_role === 'seller') {
        $stmt = $pdo->prepare("INSERT INTO seller_activities (seller_id, activity, ip_address) VALUES (?, ?, ?)");
        $activity = "Logged out from seller dashboard";
    } else {
        // For clients - you can add client_activities table later if needed
        $stmt = null;
        $activity = "Logged out from website";
    }
    
    if ($stmt) {
        try {
            $stmt->execute([$user_id, $activity, $_SERVER['REMOTE_ADDR']]);
        } catch (Exception $e) {
            // Log error but don't break the logout process
            error_log("Logout activity logging failed: " . $e->getMessage());
        }
    }
    
    $logout_message = "Goodbye, $user_name! You are being safely logged out.";
}

// Clear all session data
$_SESSION = array();

// Destroy the session
if (session_id() != "") {
    session_destroy();
}

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], 
        $params["domain"], 
        $params["secure"], 
        $params["httponly"]
    );
}

// Set headers to prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - BSDO Sale</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Nunito', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logout-container {
            background: white;
            border-radius: 15px;
            padding: 3rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
        }
        
        .logout-icon {
            font-size: 4rem;
            color: #667eea;
            margin-bottom: 1.5rem;
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
            margin: 2rem 0;
        }
        
        .progress {
            height: 6px;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        
        <h2 class="mb-3">Logging Out</h2>
        <p class="text-muted mb-4"><?php echo htmlspecialchars($logout_message); ?></p>
        
        <!-- Animated spinner -->
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        
        <!-- Progress bar -->
        <div class="progress">
            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                 style="width: 100%"></div>
        </div>
        
        <p class="small text-muted mt-3">
            <i class="fas fa-shield-alt me-1"></i>
            Your session has been securely terminated
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Redirect to login page after 3 seconds
        setTimeout(function() {
            window.location.href = '../index.php?logout=success';
        }, 3000);

        // Optional: Countdown timer
        let seconds = 3;
        const countdownElement = document.createElement('div');
        countdownElement.className = 'small text-muted mt-2';
        countdownElement.innerHTML = `Redirecting in <strong>${seconds}</strong> seconds...`;
        document.querySelector('.logout-container').appendChild(countdownElement);

        const countdown = setInterval(function() {
            seconds--;
            countdownElement.innerHTML = `Redirecting in <strong>${seconds}</strong> second${seconds !== 1 ? 's' : ''}...`;
            
            if (seconds <= 0) {
                clearInterval(countdown);
            }
        }, 1000);

        // Clear any remaining session data
        if (window.localStorage) {
            // Remove any sensitive data from localStorage
            const keysToRemove = ['user_token', 'auth_data', 'session_data'];
            keysToRemove.forEach(key => {
                if (localStorage.getItem(key)) {
                    localStorage.removeItem(key);
                }
            });
        }

        // Prevent back button navigation to secured pages
        history.pushState(null, null, location.href);
        window.onpopstate = function() {
            history.go(1);
        };
    </script>
</body>
</html>