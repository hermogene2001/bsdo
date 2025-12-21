<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo "Not authorized";
    exit();
}

try {
    // Test if referrals table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'referrals'");
    $stmt->execute();
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        echo "Referrals table does not exist";
        exit();
    }
    
    // Test query to get some referral data
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM referrals");
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Referrals table exists. Total records: " . $count['count'] . "\n";
    
    // Test a specific query
    $stmt = $pdo->prepare("
        SELECT r.*, u.first_name, u.last_name, u.email, u.role, u.created_at as user_created_at
        FROM referrals r
        JOIN users u ON r.invitee_id = u.id
        LIMIT 5
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Sample referral records:\n";
    print_r($results);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}