<?php
require_once 'includes/db.php';

// First, let's check if we have any sellers in the database
try {
    echo "Checking for sellers in the database...\n";
    
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE role = 'seller' LIMIT 1");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $seller = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Found seller: " . $seller['first_name'] . " " . $seller['last_name'] . " (" . $seller['email'] . ")\n";
        
        // Get the seller code
        $codeStmt = $pdo->prepare("SELECT seller_code FROM seller_codes WHERE seller_id = ?");
        $codeStmt->execute([$seller['id']]);
        
        if ($codeStmt->rowCount() > 0) {
            $sellerCode = $codeStmt->fetch(PDO::FETCH_ASSOC);
            echo "Seller code: " . $sellerCode['seller_code'] . "\n";
            
            // Now test the login query
            echo "\nTesting seller login query...\n";
            
            $loginStmt = $pdo->prepare("
                SELECT u.*, sc.seller_code 
                FROM users u 
                LEFT JOIN seller_codes sc ON u.id = sc.seller_id 
                WHERE u.email = ? AND u.role = 'seller' AND sc.seller_code = ?
            ");
            
            $result = $loginStmt->execute([$seller['email'], $sellerCode['seller_code']]);
            echo "Query executed successfully\n";
            
            $rowCount = $loginStmt->rowCount();
            echo "Rows found: " . $rowCount . "\n";
            
            if ($rowCount > 0) {
                $user = $loginStmt->fetch(PDO::FETCH_ASSOC);
                echo "Login query successful!\n";
                echo "User data:\n";
                print_r($user);
            } else {
                echo "Login query failed - no matching records\n";
            }
        } else {
            echo "No seller code found for this seller\n";
        }
    } else {
        echo "No sellers found in the database\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>