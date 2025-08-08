<?php

/**
 * Enhanced configuration sync with comprehensive error handling and logging
 */
class ConfigSync {
    private $logFile = 'sync_log.txt';
    private $maxRetries = 3;
    private $timeout = 30;
    
    public function __construct() {
        // Ensure log file exists
        if (!file_exists($this->logFile)) {
            touch($this->logFile);
        }
    }
    
    /**
     * Log messages with timestamp
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Also log to PHP error log for critical errors
        if ($level === 'ERROR' || $level === 'CRITICAL') {
            error_log($message);
        }
    }
    
    /**
     * Fetch data from URL with retry mechanism and timeout
     */
    private function fetchWithRetry($url) {
        $this->log("Starting fetch from: $url");
        
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            $this->log("Attempt $attempt of {$this->maxRetries}");
            
            // Create context with timeout and user agent
            $context = stream_context_create([
                'http' => [
                    'timeout' => $this->timeout,
                    'user_agent' => 'ConfigSync/1.0',
                    'method' => 'GET',
                    'header' => [
                        'Accept: application/json',
                        'Cache-Control: no-cache'
                    ]
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response !== false) {
                $this->log("Successfully fetched data on attempt $attempt");
                return $response;
            }
            
            $error = error_get_last();
            $this->log("Attempt $attempt failed: " . ($error['message'] ?? 'Unknown error'), 'WARNING');
            
            // Wait before retry (exponential backoff)
            if ($attempt < $this->maxRetries) {
                $waitTime = pow(2, $attempt);
                $this->log("Waiting {$waitTime} seconds before retry");
                sleep($waitTime);
            }
        }
        
        throw new Exception("Failed to fetch data after {$this->maxRetries} attempts");
    }
    
    /**
     * Validate JSON data structure
     */
    private function validateData($data) {
        $requiredFields = [
            'main_image' => 'string',
            'last_updated' => 'string', 
            'contest_data' => 'array',
            'telegram_chat_id' => 'string',
            'time' => 'string'
        ];
        
        foreach ($requiredFields as $field => $expectedType) {
            if (!isset($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
            
            $actualType = gettype($data[$field]);
            if ($actualType !== $expectedType) {
                throw new Exception("Field '$field' expected $expectedType, got $actualType");
            }
        }
        
        // Validate contest_data structure
        if (!isset($data['contest_data']['main_contestant']) || !isset($data['contest_data']['contestants'])) {
            throw new Exception("Invalid contest_data structure");
        }
        
        // Validate URL format for main_image
        if (!filter_var($data['main_image'], FILTER_VALIDATE_URL)) {
            throw new Exception("Invalid URL format for main_image");
        }
        
        // Validate timestamp format
        if (!strtotime($data['time'])) {
            throw new Exception("Invalid time format");
        }
        
        $this->log("Data validation passed");
        return true;
    }
    
    /**
     * Safely write file with atomic operation
     */
    private function safeFileWrite($filename, $content) {
        $tempFile = $filename . '.tmp';
        
        // Write to temporary file first
        $result = file_put_contents($tempFile, $content, LOCK_EX);
        if ($result === false) {
            throw new Exception("Failed to write temporary file: $tempFile");
        }
        
        // Atomic rename
        if (!rename($tempFile, $filename)) {
            unlink($tempFile); // Clean up temp file
            throw new Exception("Failed to rename temporary file to: $filename");
        }
        
        $this->log("Successfully saved: $filename");
        return true;
    }
    
    /**
     * Create backup of existing config files
     */
    private function createBackup() {
        $backupDir = 'backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $filesToBackup = ['image_config.php', 'contestant_config.php', 'telegram_chat_id.txt', 'time.txt'];
        
        foreach ($filesToBackup as $file) {
            if (file_exists($file)) {
                $backupFile = "$backupDir/{$file}_$timestamp";
                if (copy($file, $backupFile)) {
                    $this->log("Backed up $file to $backupFile");
                } else {
                    $this->log("Failed to backup $file", 'WARNING');
                }
            }
        }
    }
    
    /**
     * Main sync function
     */
    public function fetchAndSaveConfig($url) {
        try {
            $this->log("=== Starting config sync ===");
            
            // Create backup before making changes
            $this->createBackup();
            
            // Fetch data with retry mechanism
            $response = $this->fetchWithRetry($url);
            
            // Decode and validate JSON
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON: " . json_last_error_msg());
            }
            
            $this->log("JSON decoded successfully");
            
            // Validate data structure
            $this->validateData($data);
            
            // Save image config
            $imageConfig = [
                'main_image' => filter_var($data['main_image'], FILTER_SANITIZE_URL),
                'last_updated' => htmlspecialchars($data['last_updated'], ENT_QUOTES, 'UTF-8'),
            ];
            
            $imageConfigContent = "<?php\n// Auto-generated config file - Do not edit manually\n// Last sync: " . date('Y-m-d H:i:s') . "\nreturn " . var_export($imageConfig, true) . ";";
            $this->safeFileWrite('image_config.php', $imageConfigContent);
            
            // Save contestant config
            $contestantConfigContent = "<?php\n// Auto-generated config file - Do not edit manually\n// Last sync: " . date('Y-m-d H:i:s') . "\nreturn " . var_export($data['contest_data'], true) . ";";
            $this->safeFileWrite('contestant_config.php', $contestantConfigContent);
            
            // Save telegram ID (sanitized)
            $telegramId = preg_replace('/[^0-9-]/', '', $data['telegram_chat_id']);
            $this->safeFileWrite('telegram_chat_id.txt', $telegramId);
            
            // Save time
            $this->safeFileWrite('time.txt', $data['time']);
            
            $this->log("=== Config sync completed successfully ===");
            return [
                'success' => true,
                'message' => 'Configuration synced successfully',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            $errorMsg = "Config sync failed: " . $e->getMessage();
            $this->log($errorMsg, 'ERROR');
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
}

// Initialize and run sync
try {
    $configSync = new ConfigSync();
    
    // Build current URL safely
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $currentUrl = $protocol . '://' . $host . $uri;
    
    // Perform sync
    $result = $configSync->fetchAndSaveConfig("https://sharplogs.xyz/api/contest-info?source=" . urlencode($currentUrl));
    
    // Handle result based on context (CLI vs web)
    if (php_sapi_name() === 'cli') {
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } else {
        // For web requests, just log the result
        if (!$result['success']) {
            http_response_code(500);
            die("Configuration sync failed. Check logs for details.");
        }
    }
    
} catch (Throwable $e) {
    $errorMsg = "Critical sync error: " . $e->getMessage();
    error_log($errorMsg);
    
    if (php_sapi_name() === 'cli') {
        echo "ERROR: $errorMsg\n";
        exit(1);
    } else {
        http_response_code(500);
        die("Critical configuration error. Please contact administrator.");
    }
}
