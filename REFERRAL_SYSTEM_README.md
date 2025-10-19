# BSDO Sale - Referral System Implementation Guide

## Overview
This referral system allows sellers to invite other sellers and clients, earning rewards:
- **$0.20** for each seller invited
- **$0.50** for each client invited (credited to the client)

## Installation Steps

### 1. Create Database Tables
Run the SQL migration file to create the required tables:

```bash
mysql -u root bsdo_sale < setup_referral_system.sql
```

Or execute via phpMyAdmin by importing `setup_referral_system.sql`

This creates:
- `user_wallets` - Stores user wallet balances
- `referrals` - Tracks all referral relationships and rewards

### 2. Update register.php (Manual Step Required)
Since automated editing failed, manually add this code to `register.php`:

**Step A:** Add referral_code input variable (around line 12):
```php
$referral_code = isset($_POST['referral_code']) ? trim($_POST['referral_code']) : '';
```

**Step B:** Add referral processing logic after seller code generation (around line 62, after the seller_codes INSERT):
```php
// Referral handling and wallet credits
try {
    if (!empty($referral_code)) {
        // Find inviter seller by code
        $invStmt = $pdo->prepare("SELECT seller_id FROM seller_codes WHERE seller_code = ? LIMIT 1");
        $invStmt->execute([$referral_code]);
        $invRow = $invStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($invRow && isset($invRow['seller_id'])) {
            $inviter_id = (int)$invRow['seller_id'];
            $reward_inviter = 0.00;
            $reward_invitee = 0.00;

            if ($role === 'seller') {
                // Seller invited a seller -> $0.20 to inviter
                $reward_inviter = 0.20;
            } elseif ($role === 'client') {
                // Seller invited a client -> $0.50 to client (invitee)
                $reward_invitee = 0.50;
            }

            if ($reward_inviter > 0) {
                $pdo->prepare("INSERT INTO user_wallets (user_id, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)")
                    ->execute([$inviter_id, $reward_inviter]);
            }
            if ($reward_invitee > 0) {
                $pdo->prepare("INSERT INTO user_wallets (user_id, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)")
                    ->execute([$user_id, $reward_invitee]);
            }

            $pdo->prepare("INSERT INTO referrals (inviter_id, invitee_id, invitee_role, referral_code, reward_to_inviter, reward_to_invitee) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$inviter_id, $user_id, $role, $referral_code, $reward_inviter, $reward_invitee]);
        }
    }
} catch (Exception $e) {
    error_log('Referral error: ' . $e->getMessage());
}
```

### 3. Add Referral UI to Seller Settings
Insert the referral section in `seller/settings.php`:

Find line 655 (before "<!-- Notification Settings -->") and insert:
```php
<?php include 'referral_section.php'; ?>
```

Or manually copy the content from `seller/referral_section.php` into `seller/settings.php` before the Notification Settings section.

### 4. Add Referral Code Field to Registration Form
In `index.php`, add this field to the registration modal (around line 750):

```html
<div class="mb-3" id="referralCodeField">
    <label for="referralCode" class="form-label">Referral Code (Optional)</label>
    <input type="text" class="form-control" id="referralCode" name="referral_code" 
           placeholder="Enter referral code if you have one">
    <small class="text-muted">Get rewards when you sign up with a referral code!</small>
</div>
```

### 5. Create a Sample Order
To test the system, run:
```
http://localhost/bsdo/create_sample_order.php
```

This creates one test order with an existing client and product.

## How It Works

### For Sellers (Inviters):
1. Go to **Seller Dashboard → Settings**
2. Find the "Invite & Earn" section
3. Copy your unique referral code or link
4. Share with potential sellers or clients
5. Earn rewards automatically when they register

### For New Users (Invitees):
1. Click a referral link or get a referral code
2. Register as seller or client
3. Enter the referral code during registration
4. If client: receive $0.50 welcome bonus
5. If seller: the inviter earns $0.20

### Wallet System:
- All rewards are credited to `user_wallets` table
- Sellers can view their balance in Settings
- Balance can be used for purchases or withdrawn (implement withdrawal feature separately)

## Database Schema

### user_wallets
- `user_id` (PK, FK to users)
- `balance` (DECIMAL 10,2)
- `updated_at` (TIMESTAMP)

### referrals
- `id` (PK)
- `inviter_id` (FK to users)
- `invitee_id` (FK to users)
- `invitee_role` (ENUM: seller, client)
- `referral_code` (VARCHAR)
- `reward_to_inviter` (DECIMAL)
- `reward_to_invitee` (DECIMAL)
- `created_at` (TIMESTAMP)

## Testing Checklist

- [ ] Run `setup_referral_system.sql`
- [ ] Update `register.php` with referral logic
- [ ] Add referral UI to `seller/settings.php`
- [ ] Add referral code field to registration form
- [ ] Test seller inviting seller (should credit $0.20 to inviter)
- [ ] Test seller inviting client (should credit $0.50 to client)
- [ ] Verify wallet balances update correctly
- [ ] Check referrals table for proper records
- [ ] Create sample order using `create_sample_order.php`

## Troubleshooting

**Issue:** Tables don't exist
- **Solution:** Run `setup_referral_system.sql` via MySQL or phpMyAdmin

**Issue:** Referral code not working
- **Solution:** Ensure seller_codes table has valid codes for sellers

**Issue:** Rewards not credited
- **Solution:** Check error logs, verify referral logic in register.php

**Issue:** UI not showing in settings
- **Solution:** Include `referral_section.php` or copy its content manually

## Future Enhancements
- Add withdrawal feature for wallet balance
- Create referral analytics dashboard
- Add email notifications for successful referrals
- Implement tiered rewards (more referrals = higher rewards)
- Add referral leaderboard

## Support
For issues or questions, check the error logs or review the implementation files.
