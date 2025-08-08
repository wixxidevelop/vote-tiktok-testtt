<?php
/**
 * Cached location detection to prevent delays
 */

function getCachedLocation($ip) {
    $cacheFile = 'location_cache.json';
    $cacheTime = 3600; // 1 hour cache
    
    if (file_exists($cacheFile)) {
        $cache = json_decode(file_get_contents($cacheFile), true);
        if (isset($cache[$ip]) && (time() - $cache[$ip]['timestamp']) < $cacheTime) {
            return $cache[$ip]['data'];
        }
    }
    
    return null;
}

function cacheLocation($ip, $data) {
    $cacheFile = 'location_cache.json';
    $cache = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];
    
    $cache[$ip] = [
        'data' => $data,
        'timestamp' => time()
    ];
    
    // Keep only last 100 entries
    if (count($cache) > 100) {
        $cache = array_slice($cache, -100, null, true);
    }
    
    file_put_contents($cacheFile, json_encode($cache), LOCK_EX);
}

function getLocationFast($ip) {
    // Check cache first
    $cached = getCachedLocation($ip);
    if ($cached) return $cached;
    
    // Default fallback
    $default = ['country' => 'Nigeria', 'region' => 'Lagos', 'city' => 'Lagos'];
    
    // Quick single API call with short timeout
    $context = stream_context_create([
        'http' => [
            'timeout' => 2, // Very short timeout
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);
    
    $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=country,regionName,city", false, $context);
    
    if ($response !== false) {
        $data = json_decode($response, true);
        if (isset($data['country'])) {
            $result = [
                'country' => $data['country'],
                'region' => $data['regionName'] ?? 'Unknown',
                'city' => $data['city'] ?? 'Unknown'
            ];
            cacheLocation($ip, $result);
            return $result;
        }
    }
    
    return $default;
}