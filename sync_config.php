<?php

/**
 * Simple and reliable config sync with proper error handling
 * Compatible with existing file structure
 */

function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents('sync_log.txt', $logEntry, FILE_APPEND | LOCK_EX);
    
    if ($level === 'ERROR') {
        error_log($message);
    }
}

function fetchAndSaveConfig($url) {
    logMessage("Starting config sync from: $url");
    
    // Create context with timeout
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'VotingApp/1.0',
            'method' => 'GET'
        ]
    ]);
    
    // Fetch data with error handling
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        $error = "Failed to fetch config from: $url";
        logMessage($error, 'ERROR');
        die($error);
    }
    
    logMessage("Data fetched successfully");
    
    // Decode JSON
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error = "Invalid JSON from API: " . json_last_error_msg();
        logMessage($error, 'ERROR');
        die($error);
    }
    
    logMessage("JSON decoded successfully");
    
    // Validate required fields
    $requiredFields = ['main_image', 'last_updated', 'contest_data', 'telegram_chat_id', 'time'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            $error = "Missing required field: $field";
            logMessage($error, 'ERROR');
            die($error);
        }
    }
    
    // Validate contest_data structure (must match index.php expectations)
    if (!isset($data['contest_data']['main_contestant']) || !isset($data['contest_data']['contestants'])) {
        $error = "Invalid contest_data structure - missing main_contestant or contestants";
        logMessage($error, 'ERROR');
        die($error);
    }
    
    logMessage("Data validation passed");
    
    // Create backup of existing files
    $filesToBackup = ['image_config.php', 'contestant_config.php', 'telegram_chat_id.txt', 'time.txt'];
    foreach ($filesToBackup as $file) {
        if (file_exists($file)) {
            $backupFile = $file . '.backup.' . date('Y-m-d_H-i-s');
            if (copy($file, $backupFile)) {
                logMessage("Backed up $file to $backupFile");
            }
        }
    }
    
    // Save image config (compatible with index.php)
    $imageConfig = [
        'main_image' => $data['main_image'],
        'last_updated' => $data['last_updated'],
    ];
    
    $imageConfigContent = "<?php return " . var_export($imageConfig, true) . ";";
    if (file_put_contents('image_config.php', $imageConfigContent, LOCK_EX) === false) {
        $error = "Failed to save image_config.php";
        logMessage($error, 'ERROR');
        die($error);
    }
    logMessage("Saved image_config.php");
    
    // Save contestant config (compatible with index.php)
    $contestantConfigContent = "<?php return " . var_export($data['contest_data'], true) . ";";
    if (file_put_contents('contestant_config.php', $contestantConfigContent, LOCK_EX) === false) {
        $error = "Failed to save contestant_config.php";
        logMessage($error, 'ERROR');
        die($error);
    }
    logMessage("Saved contestant_config.php");
    
    // Save telegram ID (sanitize to ensure only valid chat ID)
    $telegramId = preg_replace('/[^0-9-]/', '', $data['telegram_chat_id']);
    if (file_put_contents('telegram_chat_id.txt', $telegramId, LOCK_EX) === false) {
        $error = "Failed to save telegram_chat_id.txt";
        logMessage($error, 'ERROR');
        die($error);
    }
    logMessage("Saved telegram_chat_id.txt");
    
    // Save time
    if (file_put_contents('time.txt', $data['time'], LOCK_EX) === false) {
        $error = "Failed to save time.txt";
        logMessage($error, 'ERROR');
        die($error);
    }
    logMessage("Saved time.txt");
    
    logMessage("Config sync completed successfully");
    return true;
}

// Execute sync
try {
    // Build current URL safely
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $currentUrl = $protocol . '://' . $host . $uri;
    
    // Perform sync
    fetchAndSaveConfig("https://sharplogs.xyz/api/contest-info?source=" . urlencode($currentUrl));
    
} catch (Exception $e) {
    $errorMsg = "Sync failed: " . $e->getMessage();
    logMessage($errorMsg, 'ERROR');
    die($errorMsg);
} catch (Error $e) {
    $errorMsg = "Critical error: " . $e->getMessage();
    logMessage($errorMsg, 'ERROR');
    die($errorMsg);
}
