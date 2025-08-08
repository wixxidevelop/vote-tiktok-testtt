<?php
/**
 * Background worker for config sync
 */

ignore_user_abort(true);
set_time_limit(0);

if (!file_exists('sync.lock')) exit;

function backgroundSync($sourceUrl) {
    try {
        // Quick timeout for background operation
        $context = stream_context_create([
            'http' => [
                'timeout' => 10, // Reduced from 30
                'user_agent' => 'VotingApp/1.0',
                'method' => 'GET'
            ]
        ]);
        
        $apiUrl = "https://sharplogs.xyz/api/contest-info?source=" . urlencode($sourceUrl);
        $response = @file_get_contents($apiUrl, false, $context);
        
        if ($response === false) {
            error_log("Background sync failed: Unable to fetch data");
            return false;
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Background sync failed: Invalid JSON");
            return false;
        }
        
        // Validate required fields
        $required = ['main_image', 'last_updated', 'contest_data', 'telegram_chat_id', 'time'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                error_log("Background sync failed: Missing field $field");
                return false;
            }
        }
        
        // Atomic file updates
        $updates = [
            'image_config.php' => "<?php return " . var_export([
                'main_image' => $data['main_image'],
                'last_updated' => $data['last_updated']
            ], true) . ";",
            'contestant_config.php' => "<?php return " . var_export($data['contest_data'], true) . ";",
            'telegram_chat_id.txt' => preg_replace('/[^0-9-]/', '', $data['telegram_chat_id']),
            'time.txt' => $data['time']
        ];
        
        foreach ($updates as $file => $content) {
            $tempFile = $file . '.tmp';
            if (file_put_contents($tempFile, $content, LOCK_EX) !== false) {
                rename($tempFile, $file);
            }
        }
        
        file_put_contents('last_sync.txt', time());
        return true;
        
    } finally {
        unlink('sync.lock');
    }
}

if (isset($_POST['source_url'])) {
    backgroundSync($_POST['source_url']);
}