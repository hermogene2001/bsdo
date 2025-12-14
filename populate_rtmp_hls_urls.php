<?php
// Populate RTMP and HLS URLs for existing streams
require_once 'config.php';

try {
    echo "Populating RTMP and HLS URLs for existing streams...\n";
    
    // Get all streams that don't have rtmp_url or hls_url set
    $stmt = $pdo->prepare("SELECT id, stream_key FROM live_streams WHERE rtmp_url IS NULL OR hls_url IS NULL");
    $stmt->execute();
    $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updated = 0;
    
    foreach ($streams as $stream) {
        $stream_id = $stream['id'];
        $stream_key = $stream['stream_key'];
        
        // Generate RTMP and HLS URLs
        $rtmp_url = 'rtmp://www.bsdosale.com/live/' . $stream_key;
        $hls_url = 'https://www.bsdosale.com/live/' . $stream_key . '/index.m3u8';
        
        // Update the stream record
        $updateStmt = $pdo->prepare("UPDATE live_streams SET rtmp_url = ?, hls_url = ? WHERE id = ?");
        $result = $updateStmt->execute([$rtmp_url, $hls_url, $stream_id]);
        
        if ($result) {
            $updated++;
            echo "✓ Updated stream ID {$stream_id}\n";
        } else {
            echo "✗ Failed to update stream ID {$stream_id}\n";
        }
    }
    
    echo "Successfully updated {$updated} streams with RTMP/HLS URLs!\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>