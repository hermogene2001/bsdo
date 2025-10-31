<?php
session_start();
require_once 'config.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebRTC Test | BSDO Sale</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-shopping-bag me-2 text-primary"></i>BSDO SALE
            </a>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-video me-2"></i>WebRTC Streaming Test</h4>
                    </div>
                    <div class="card-body">
                        <h5 class="mb-4">Test WebRTC Video Streaming</h5>
                        
                        <?php if (!$is_logged_in): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                You need to be logged in to test WebRTC functionality.
                                <a href="login.php" class="alert-link ms-2">Login here</a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                This page tests the WebRTC video streaming functionality between seller and client.
                            </div>
                            
                            <div class="mb-4">
                                <h6 class="mb-3"><i class="fas fa-store me-2 text-primary"></i>For Sellers</h6>
                                <ol>
                                    <li>Go to your <a href="seller/live_stream.php">Live Stream Dashboard</a></li>
                                    <li>Start a new live stream</li>
                                    <li>Allow camera and microphone access when prompted</li>
                                    <li>Your video will be streamed to connected clients</li>
                                </ol>
                            </div>
                            
                            <div class="mb-4">
                                <h6 class="mb-3"><i class="fas fa-users me-2 text-primary"></i>For Clients</h6>
                                <ol>
                                    <li>Go to <a href="live_streams.php">Live Streams</a> page</li>
                                    <li>Find an active stream and click "Join Live Stream"</li>
                                    <li>Allow camera and microphone access when prompted</li>
                                    <li>You'll see the seller's video stream</li>
                                </ol>
                            </div>
                            
                            <div class="bg-light p-4 rounded">
                                <h6 class="mb-3"><i class="fas fa-cogs me-2 text-warning"></i>Technical Details</h6>
                                <ul>
                                    <li><i class="fas fa-check-circle me-1 text-success"></i> WebRTC peer-to-peer video streaming</li>
                                    <li><i class="fas fa-check-circle me-1 text-success"></i> STUN servers for NAT traversal</li>
                                    <li><i class="fas fa-check-circle me-1 text-success"></i> Signaling through PHP/AJAX</li>
                                    <li><i class="fas fa-check-circle me-1 text-success"></i> Real-time video and audio transmission</li>
                                    <li><i class="fas fa-check-circle me-1 text-success"></i> ICE candidate exchange</li>
                                </ul>
                            </div>
                            
                            <div class="text-center mt-4">
                                <a href="live_streams.php" class="btn btn-primary btn-lg me-2">
                                    <i class="fas fa-broadcast-tower me-2"></i>View Live Streams
                                </a>
                                <?php if ($user_role === 'seller'): ?>
                                    <a href="seller/live_stream.php" class="btn btn-success btn-lg">
                                        <i class="fas fa-video me-2"></i>Start Streaming
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-5 mt-4">
        <div class="container">
            <div class="text-center">
                <p>&copy; 2024 BSDO Sale. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>