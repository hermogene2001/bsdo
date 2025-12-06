<?php
/**
 * Manual Referral Credit Tool
 * Use this to manually credit referral rewards for testing
 * Access: http://localhost/bsdo/manual_referral_credit.php
 */

require_once 'config.php';

$message = '';
$error = '';

// Ensure tables exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_wallets (
        user_id INT PRIMARY KEY,
        balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_wallet_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS referrals (
        id INT PRIMARY KEY AUTO_INCREMENT,
        inviter_id INT NOT NULL,
        invitee_id INT NOT NULL,
        invitee_role ENUM('seller','client') NOT NULL,
        referral_code VARCHAR(255),
        reward_to_inviter DECIMAL(10,2) DEFAULT 0.00,
        reward_to_invitee DECIMAL(10,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_inviter (inviter_id),
        INDEX idx_invitee (invitee_id),
        CONSTRAINT fk_ref_inviter FOREIGN KEY (inviter_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_ref_invitee FOREIGN KEY (invitee_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {
    $error = "Table creation error: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_referral'])) {
    try {
        $inviter_id = intval($_POST['inviter_id']);
        $invitee_id = intval($_POST['invitee_id']);
        $invitee_role = $_POST['invitee_role'];
        $referral_code = trim($_POST['referral_code']);
        
        $reward_inviter = 0.00;
        $reward_invitee = 0.00;
        
        if ($invitee_role === 'seller') {
            // No immediate reward for seller-to-seller referrals
            // Reward will be calculated when the invited seller posts a product
            $reward_inviter = 0.00;
        } elseif ($invitee_role === 'client') {
            $reward_invitee = 0.50;
        }
        
        $pdo->beginTransaction();
        
        // Credit inviter
        if ($reward_inviter > 0) {
            $pdo->prepare("INSERT INTO user_wallets (user_id, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = balance + ?")
                ->execute([$inviter_id, $reward_inviter, $reward_inviter]);
        }
        
        // Credit invitee
        if ($reward_invitee > 0) {
            $pdo->prepare("INSERT INTO user_wallets (user_id, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = balance + ?")
                ->execute([$invitee_id, $reward_invitee, $reward_invitee]);
        }
        
        // Record referral
        $pdo->prepare("INSERT INTO referrals (inviter_id, invitee_id, invitee_role, referral_code, reward_to_inviter, reward_to_invitee) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$inviter_id, $invitee_id, $invitee_role, $referral_code, $reward_inviter, $reward_invitee]);
        
        $pdo->commit();
        
        $message = "✅ Referral processed successfully! ";
        if ($invitee_role === 'seller') {
            $message .= "No immediate reward. Inviter will earn 0.5% when invited seller posts products.";
        } else {
            $message .= "Invitee earned $" . number_format($reward_invitee, 2);
        }
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error: " . $e->getMessage();
    }
}

// Get all sellers
$sellers_stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.email, sc.seller_code FROM users u LEFT JOIN seller_codes sc ON u.id = sc.seller_id WHERE u.role = 'seller' ORDER BY u.id");
$sellers_stmt->execute();
$sellers = $sellers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users
$users_stmt = $pdo->prepare("SELECT id, first_name, last_name, email, role FROM users ORDER BY id");
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get wallet balances
$wallets_stmt = $pdo->query("SELECT w.user_id, w.balance, u.first_name, u.last_name, u.role FROM user_wallets w JOIN users u ON w.user_id = u.id ORDER BY w.balance DESC");
$wallets = $wallets_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get referrals
$referrals_stmt = $pdo->query("SELECT r.*, u1.first_name as inviter_name, u2.first_name as invitee_name FROM referrals r JOIN users u1 ON r.inviter_id = u1.id JOIN users u2 ON r.invitee_id = u2.id ORDER BY r.created_at DESC");
$referrals = $referrals_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Referral Credit Tool - BSDO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <h1 class="mb-4"><i class="fas fa-gift me-2"></i>Manual Referral Credit Tool</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Process Referral</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Inviter (Seller)</label>
                                <select name="inviter_id" class="form-select" required>
                                    <option value="">Select Seller...</option>
                                    <?php foreach ($sellers as $seller): ?>
                                        <option value="<?php echo $seller['id']; ?>">
                                            <?php echo htmlspecialchars($seller['first_name'] . ' ' . $seller['last_name']); ?> 
                                            (<?php echo htmlspecialchars($seller['seller_code'] ?? 'No code'); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Invitee (New User)</label>
                                <select name="invitee_id" class="form-select" required>
                                    <option value="">Select User...</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>">
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?> 
                                            (<?php echo ucfirst($user['role']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Invitee Role</label>
                                <select name="invitee_role" class="form-select" required>
                                    <option value="seller">Seller (0.5% on product postings)</option>
                                    <option value="client">Client ($0.50 to invitee)</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Referral Code</label>
                                <input type="text" name="referral_code" class="form-control" placeholder="Enter seller code" required>
                            </div>
                            
                            <button type="submit" name="process_referral" class="btn btn-primary">
                                <i class="fas fa-check me-2"></i>Process Referral
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Wallet Balances</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Balance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($wallets as $wallet): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($wallet['first_name'] . ' ' . $wallet['last_name']); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo ucfirst($wallet['role']); ?></span></td>
                                            <td><strong>$<?php echo number_format($wallet['balance'], 2); ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($wallets)): ?>
                                        <tr><td colspan="3" class="text-center text-muted">No wallet entries yet</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Referral History</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Inviter</th>
                                <th>Invitee</th>
                                <th>Role</th>
                                <th>Code</th>
                                <th>Reward (Inviter)</th>
                                <th>Reward (Invitee)</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($referrals as $ref): ?>
                                <tr>
                                    <td><?php echo $ref['id']; ?></td>
                                    <td><?php echo htmlspecialchars($ref['inviter_name']); ?></td>
                                    <td><?php echo htmlspecialchars($ref['invitee_name']); ?></td>
                                    <td><span class="badge bg-primary"><?php echo ucfirst($ref['invitee_role']); ?></span></td>
                                    <td><code><?php echo htmlspecialchars($ref['referral_code']); ?></code></td>
                                    <td>$<?php echo number_format($ref['reward_to_inviter'], 2); ?></td>
                                    <td>$<?php echo number_format($ref['reward_to_invitee'], 2); ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($ref['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($referrals)): ?>
                                <tr><td colspan="8" class="text-center text-muted">No referrals yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="index.php" class="btn btn-secondary"><i class="fas fa-home me-2"></i>Back to Home</a>
            <a href="seller/settings.php" class="btn btn-primary"><i class="fas fa-cog me-2"></i>Seller Settings</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
