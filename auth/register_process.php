<?php
session_start();
require_once "../includes/db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name  = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $password = trim($_POST["password"]);
    $role = ($_POST["role"] == "seller") ? "seller" : "client"; // default client

    // Check if email or phone exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
    $stmt->execute([$email, $phone]);

    if ($stmt->rowCount() > 0) {
        $_SESSION["error"] = "Email or phone already exists!";
        header("Location: ../index.php");
        exit;
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // Insert user
    $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $email, $phone, $hashedPassword, $role]);

    $_SESSION["success"] = "Account created successfully! Please login.";
    header("Location: ../index.php");
    exit;
}
?>
