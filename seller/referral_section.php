<!-- Invite & Earn Referral Program Section -->
<!-- Include this in seller/settings.php before Notification Settings -->

<div class="settings-card">
    <h4 class="section-title"><i class="fas fa-gift me-2"></i>Invite & Earn</h4>
    <div class="alert alert-success">
        <i class="fas fa-star me-2"></i>
        <strong>Referral Rewards:</strong> Earn $0.20 for every seller you invite and $0.50 for every client!
    </div>
    
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stats-card" style="background: linear-gradient(135deg, #1cc88a, #18a873);">
                <div class="h4 mb-1"><?php echo formatCurrency($wallet_balance); ?></div>
                <div class="small">Wallet Balance</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card" style="background: linear-gradient(135deg, #f6c23e, #e0a800);">
                <div class="h4 mb-1"><?php echo $referral_count; ?></div>
                <div class="small">Total Referrals</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card" style="background: linear-gradient(135deg, #36b9cc, #2c9faf);">
                <div class="h4 mb-1"><?php echo formatCurrency($referral_earnings); ?></div>
                <div class="small">Referral Earnings</div>
            </div>
        </div>
    </div>
    
    <div class="mb-4">
        <label class="form-label fw-bold">Your Referral Code</label>
        <div class="input-group">
            <input type="text" class="form-control form-control-lg" id="referralCode" 
                   value="<?php echo htmlspecialchars($seller['seller_code'] ?? 'Not assigned'); ?>" readonly>
            <button class="btn btn-primary" type="button" onclick="copyReferralCode()">
                <i class="fas fa-copy me-2"></i>Copy Code
            </button>
        </div>
        <small class="text-muted">Share this code with sellers and clients to earn rewards</small>
    </div>
    
    <div class="mb-4">
        <label class="form-label fw-bold">Referral Link</label>
        <div class="input-group">
            <input type="text" class="form-control" id="referralLink" 
                   value="<?php echo htmlspecialchars('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'], 2) . '/index.php?ref=' . ($seller['seller_code'] ?? '')); ?>" readonly>
            <button class="btn btn-success" type="button" onclick="copyReferralLink()">
                <i class="fas fa-link me-2"></i>Copy Link
            </button>
        </div>
        <small class="text-muted">Share this link on social media or with your network</small>
    </div>
    
    <div class="alert alert-info">
        <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>How It Works</h6>
        <ul class="mb-0">
            <li><strong>Invite Sellers:</strong> When a seller registers using your code, you earn $0.20</li>
            <li><strong>Invite Clients:</strong> When a client registers using your code, they get $0.50 welcome bonus</li>
            <li>Rewards are automatically added to your wallet</li>
            <li>Use your wallet balance for future purchases or withdraw</li>
        </ul>
    </div>
</div>

<script>
function copyReferralCode() {
    const codeInput = document.getElementById('referralCode');
    codeInput.select();
    codeInput.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(codeInput.value).then(() => {
        alert('Referral code copied to clipboard!');
    }).catch(err => {
        console.error('Failed to copy:', err);
    });
}

function copyReferralLink() {
    const linkInput = document.getElementById('referralLink');
    linkInput.select();
    linkInput.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(linkInput.value).then(() => {
        alert('Referral link copied to clipboard!');
    }).catch(err => {
        console.error('Failed to copy:', err);
    });
}
</script>
