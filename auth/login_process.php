<?php
session_start();
require_once "../includes/db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]); // can be email or phone
    $password = trim($_POST["password"]);

    // Find user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ? LIMIT 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user["password"])) {
        // Save session
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_name"] = $user["name"];
        $_SESSION["role"] = $user["role"];

        // Redirect by role
        if ($user["role"] == "admin") {
            header("Location: ../admin/dashboard.php");
        } elseif ($user["role"] == "seller") {
            header("Location: ../seller/dashboard.php");
        } else {
            header("Location: ../client/dashboard.php");
        }
        exit;
    } else {
        $_SESSION["error"] = "Invalid username or password!";
        header("Location: ../index.php");
        exit;
    }
}
?>
