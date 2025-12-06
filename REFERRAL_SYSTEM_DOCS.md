# BSDO Sale Referral System Documentation

## Overview
The referral system allows sellers to earn rewards when they invite other users to join the platform. The system has been updated to provide percentage-based rewards instead of fixed amounts.

## How It Works

### Previous System
- When a seller invited another seller: $0.20 immediate reward
- When a seller invited a client: $0.50 reward to the client

### Updated System
- When a seller invites another seller: No immediate reward
- When the invited seller posts a product: The inviter gets 0.5% of the product price
- When a seller invites a client: $0.50 reward to the client (unchanged)

## Implementation Details

### Registration Process
1. When a new user registers with a referral code:
   - The system checks if the referral code belongs to an existing seller
   - If valid, a referral record is created in the `referrals` table
   - For seller-to-seller invitations, no immediate reward is given

### Product Posting Process
1. When a referred seller posts a product:
   - The system checks if the seller was referred by another seller
   - If yes, it calculates 0.5% of the product price as the referral bonus
   - The bonus is added to the inviter's wallet balance
   - The referral record is updated with the bonus amount

### Database Structure

#### `referrals` Table
```sql
CREATE TABLE IF NOT EXISTS `referrals` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `inviter_id` INT NOT NULL,
    `invitee_id` INT NOT NULL,
    `invitee_role` ENUM('seller','client') NOT NULL,
    `referral_code` VARCHAR(255),
    `reward_to_inviter` DECIMAL(10,2) DEFAULT 0.00,
    `reward_to_invitee` DECIMAL(10,2) DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_inviter` (`inviter_id`),
    INDEX `idx_invitee` (`invitee_id`),
    CONSTRAINT `fk_ref_inviter` FOREIGN KEY (`inviter_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ref_invitee` FOREIGN KEY (`invitee_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `user_wallets` Table
```sql
CREATE TABLE IF NOT EXISTS `user_wallets` (
    `user_id` INT PRIMARY KEY,
    `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_wallet_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Files Modified

### 1. `seller/products.php`
- Added referral bonus calculation when a referred seller posts a product
- Calculates 0.5% of the product price as bonus
- Awards the bonus to the inviter's wallet
- Updates the referral record with the bonus amount

### 2. `seller/rental_products.php`
- Added similar referral bonus functionality for rental products
- Calculates 0.5% of the average rental price as bonus
- Awards the bonus to the inviter's wallet
- Updates the referral record with the bonus amount

## Testing

To test the referral system, visit `/test_referral_system.php` which shows:
- All sellers and their referrers
- Wallet balances
- Referral records with rewards

## Notes
- The referral bonus is calculated at the time of product posting, not at registration
- Only applies to sellers who were referred by other sellers
- The bonus accumulates in the inviter's wallet balance
- All transactions are wrapped in database transactions for data integrity