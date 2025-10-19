<?php
// Script to fix the payment slip verification issue

$file = 'admin/payment_slips.php';
$content = file_get_contents($file);

$old_code = '                    $stmt = $pdo->prepare("UPDATE payment_slips SET status = ?, admin_notes = ? WHERE id = ?");
                    $stmt->execute([$status, $admin_notes, $id]);
                    
                    // Get slip details for logging
                    $stmt = $pdo->prepare("SELECT ps.*, p.name as product_name, u.first_name, u.last_name FROM payment_slips ps JOIN products p ON ps.product_id = p.id JOIN users u ON ps.seller_id = u.id WHERE ps.id = ?");
                    $stmt->execute([$id]);
                    $slip = $stmt->fetch(PDO::FETCH_ASSOC);';

$new_code = '                    // Get slip details first
                    $stmt = $pdo->prepare("SELECT ps.*, p.name as product_name, u.first_name, u.last_name FROM payment_slips ps JOIN products p ON ps.product_id = p.id JOIN users u ON ps.seller_id = u.id WHERE ps.id = ?");
                    $stmt->execute([$id]);
                    $slip = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$slip) {
                        throw new Exception("Payment slip not found");
                    }
                    
                    // Update payment slip status
                    $stmt = $pdo->prepare("UPDATE payment_slips SET status = ?, admin_notes = ? WHERE id = ?");
                    $stmt->execute([$status, $admin_notes, $id]);
                    
                    // Update product verification_payment_status based on payment slip status
                    if ($status === \'verified\') {
                        $product_status = \'paid\';
                    } elseif ($status === \'rejected\') {
                        $product_status = \'rejected\';
                    } else {
                        $product_status = \'pending\';
                    }
                    
                    $stmt = $pdo->prepare("UPDATE products SET verification_payment_status = ? WHERE id = ?");
                    $stmt->execute([$product_status, $slip[\'product_id\']])';;

$new_content = str_replace($old_code, $new_code, $content);

if ($new_content === $content) {
    echo "ERROR: Pattern not found in file!\n";
    exit(1);
}

file_put_contents($file, $new_content);
echo "SUCCESS: File updated successfully!\n";
echo "The admin payment slip verification now updates the product status.\n";
?>
