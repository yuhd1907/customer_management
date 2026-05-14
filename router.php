<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;

// Nếu là file có thật (html, css, js, images), cho phép truy cập trực tiếp
if (file_exists($file) && !is_dir($file)) {
    return false; 
}

// Điều hướng các request bắt đầu bằng /api vào public/index.php
if (strpos($uri, '/api') === 0) {
    $_SERVER['SCRIPT_NAME'] = '/public/index.php';
    require_once __DIR__ . '/public/index.php';
    exit;
}

// Mặc định (truy cập / hoặc index.php) thì trả về app.html
if ($uri === '/' || $uri === '/index.php') {
    require_once __DIR__ . '/app.html';
    exit;
}

// Trả về 404 cho các trường hợp khác
http_response_code(404);
echo "404 Not Found";
