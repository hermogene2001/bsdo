<?php
/**
 * Test Referral System Script
 * This script helps test the referral system functionality
 */

require_once 'config.php';

echo "<h2>Referral System Test</h2>\n";

// Get all sellers with their referral information
$stmt = $pdo->query("
    SELECT 
        u.id, 
        u.first_name, 
        u.last_name, 
        u.email,
        sc.seller_code,
        r.inviter_id,
        inviter.first_name as inviter_first_name,
        inviter.last_name as inviter_last_name
    FROM users u 
    LEFT JOIN seller_codes sc ON u.id = sc.seller_id 
    LEFT JOIN referrals r ON u.id = r.invitee_id AND r.invitee_role = 'seller'
    LEFT JOIN users inviter ON r.inviter_id = inviter.id
    WHERE u.role = 'seller' 
    ORDER BY u.id
");

echo "<h3>Sellers and Their Referrers</h3>\n";
echo "<table border='1' cellpadding='5'>\n";
echo "<tr><th>Seller ID</th><th>Name</th><th>Email</th><th>Seller Code</th><th>Referred By</th></tr>\n";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
    echo "<td>" . htmlspecialchars($row['seller_code'] ?? 'N/A') . "</td>";
    if ($row['inviter_id']) {
        echo "<td>" . htmlspecialchars($row['inviter_first_name'] . ' ' . $row['inviter_last_name']) . " (ID: " . $row['inviter_id'] . ")</td>";
    } else {
        echo "<td>Not referred</td>";
    }
    echo "</tr>\n";
}
echo "</table>\n";

// Get wallet balances
$stmt = $pdo->query("
    SELECT 
        uw.user_id,
        u.first_name,
        u.last_name,
        uw.balance
    FROM user_wallets uw
    JOIN users u ON uw.user_id = u.id
    ORDER BY uw.balance DESC
");

echo "<h3>Wallet Balances</h3>\n";
echo "<table border='1' cellpadding='5'>\n";
echo "<tr><th>User ID</th><th>Name</th><th>Balance</th></tr>\n";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>" . $row['user_id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
    echo "<td>$" . number_format($row['balance'], 2) . "</td>";
    echo "</tr>\n";
}
echo "</table>\n";

// Get referral records
$stmt = $pdo->query("
    SELECT 
        r.id,
        r.inviter_id,
        inviter.first_name as inviter_first_name,
        inviter.last_name as inviter_last_name,
        r.invitee_id,
        invitee.first_name as invitee_first_name,
        invitee.last_name as invitee_last_name,
        r.invitee_role,
        r.referral_code,
        r.reward_to_inviter,
        r.reward_to_invitee,
        r.created_at
    FROM referrals r
    JOIN users inviter ON r.inviter_id = inviter.id
    JOIN users invitee ON r.invitee_id = invitee.id
    ORDER BY r.created_at DESC
");

echo "<h3>Referral Records</h3>\n";
echo "<table border='1' cellpadding='5'>\n";
echo "<tr><th>ID</th><th>Inviter</th><th>Invitee</th><th>Role</th><th>Code</th><th>Reward to Inviter</th><th>Reward to Invitee</th><th>Date</th></tr>\n";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['inviter_first_name'] . ' ' . $row['inviter_last_name']) . " (" . $row['inviter_id'] . ")</td>";
    echo "<td>" . htmlspecialchars($row['invitee_first_name'] . ' ' . $row['invitee_last_name']) . " (" . $row['invitee_id'] . ")</td>";
    echo "<td>" . ucfirst($row['invitee_role']) . "</td>";
    echo "<td>" . htmlspecialchars($row['referral_code']) . "</td>";
    echo "<td>$" . number_format($row['reward_to_inviter'], 2) . "</td>";
    echo "<td>$" . number_format($row['reward_to_invitee'], 2) . "</td>";
    echo "<td>" . $row['created_at'] . "</td>";
    echo "</tr>\n";
}
echo "</table>\n";

echo "<p><a href='index.php'>Back to Home</a></p>\n";
?>