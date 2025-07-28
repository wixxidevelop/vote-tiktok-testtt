<?php
// router.php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Redirect /about.php → /about
if (preg_match('/^(.*)\.php$/', $uri, $matches)) {
    header("Location: {$matches[1]}", true, 301);
    exit;
}

// Serve existing static files
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Serve /about → about.php
if (file_exists(__DIR__ . "$uri.php")) {
    require __DIR__ . "$uri.php";
    exit;
}

// Fallback
require __DIR__ . '/index.php';
