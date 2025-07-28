<?php
if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $value = getenv($key);
        return $value !== false ? $value : ($default ?? null);
    }
}