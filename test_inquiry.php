<?php
session_start();
require_once 'config.php';

// Simulate a client login for testing
$_SESSION['user_id'] = 2; // Assuming user ID 2 is a client
$_SESSION['user_role'] = 'client';
$_SESSION['user_name'] = 'Test Client';

// Test creating an inquiry
$_POST['product_id'] = 1;
$_POST['message'] = 'This is a test inquiry';

include 'create_inquiry.php';
?>