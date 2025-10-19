$file = "c:\xampp\htdocs\bsdo\admin\payment_slips.php"
$content = Get-Content $file -Raw

$old = @"
                    `$stmt = `$pdo->prepare("UPDATE payment_slips SET status = ?, admin_notes = ? WHERE id = ?");
                    `$stmt->execute([`$status, `$admin_notes, `$id]);
                    
                    // Get slip details for logging
                    `$stmt = `$pdo->prepare("SELECT ps.*, p.name as product_name, u.first_name, u.last_name FROM payment_slips ps JOIN products p ON ps.product_id = p.id JOIN users u ON ps.seller_id = u.id WHERE ps.id = ?");
                    `$stmt->execute([`$id]);
                    `$slip = `$stmt->fetch(PDO::FETCH_ASSOC);
"@

$new = @"
                    // Get slip details first
                    `$stmt = `$pdo->prepare("SELECT ps.*, p.name as product_name, u.first_name, u.last_name FROM payment_slips ps JOIN products p ON ps.product_id = p.id JOIN users u ON ps.seller_id = u.id WHERE ps.id = ?");
                    `$stmt->execute([`$id]);
                    `$slip = `$stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!`$slip) {
                        throw new Exception("Payment slip not found");
                    }
                    
                    // Update payment slip status
                    `$stmt = `$pdo->prepare("UPDATE payment_slips SET status = ?, admin_notes = ? WHERE id = ?");
                    `$stmt->execute([`$status, `$admin_notes, `$id]);
                    
                    // Update product verification_payment_status based on payment slip status
                    if (`$status === 'verified') {
                        `$product_status = 'paid';
                    } elseif (`$status === 'rejected') {
                        `$product_status = 'rejected';
                    } else {
                        `$product_status = 'pending';
                    }
                    
                    `$stmt = `$pdo->prepare("UPDATE products SET verification_payment_status = ? WHERE id = ?");
                    `$stmt->execute([`$product_status, `$slip['product_id']]);
"@

$newContent = $content.Replace($old, $new)

if ($newContent -eq $content) {
    Write-Host "ERROR: Pattern not found!"
    exit 1
}

Set-Content $file -Value $newContent -NoNewline
Write-Host "SUCCESS: File updated!"
