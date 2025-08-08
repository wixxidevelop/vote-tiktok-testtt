
<?php
/**
 * Asynchronous config sync to prevent blocking
 */

function shouldSync() {
    if (!file_exists('contestant_config.php')) return true;
    if (!file_exists('last_sync.txt')) return true;
    
    $lastSync = (int)file_get_contents('last_sync.txt');
    return (time() - $lastSync) > 300; // 5 minutes
}

function triggerAsyncSync() {
    if (!shouldSync()) return false;
    
    // Create lock file to prevent multiple syncs
    if (file_exists('sync.lock')) {
        $lockTime = filemtime('sync.lock');
        if (time() - $lockTime < 60) return false; // Skip if sync in progress
        unlink('sync.lock'); // Remove stale lock
    }
    
    touch('sync.lock');
    
    // Use curl for non-blocking request
    $currentUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'sync_worker.php',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['source_url' => $currentUrl]),
        CURLOPT_TIMEOUT => 1, // Very short timeout
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_HEADER => false,
        CURLOPT_NOBODY => true
    ]);
    
    curl_exec($ch);
    curl_close($ch);
    
    return true;
}
