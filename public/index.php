<?php

/**
 * ============================================================
 * Customer Management API — Router
 * URL: http://localhost/customer_management/public/index.php
 * ============================================================
 */

define('BASE_PATH', dirname(__DIR__));

// Load core
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/helpers/response.php';
require_once BASE_PATH . '/helpers/jwt.php';
require_once BASE_PATH . '/middlewares/auth.php';
require_once BASE_PATH . '/middlewares/admin.php';

// Load controllers
require_once BASE_PATH . '/controllers/AuthController.php';
require_once BASE_PATH . '/controllers/EmployeeController.php';
require_once BASE_PATH . '/controllers/CustomerController.php';
require_once BASE_PATH . '/controllers/CustomerTierController.php';
require_once BASE_PATH . '/controllers/ProductController.php';
require_once BASE_PATH . '/controllers/OrderController.php';
require_once BASE_PATH . '/controllers/ConversationController.php';
require_once BASE_PATH . '/controllers/MessageController.php';
require_once BASE_PATH . '/controllers/ReturnExchangeController.php';
require_once BASE_PATH . '/controllers/StatisticsController.php';
require_once BASE_PATH . '/controllers/CustomerGroupController.php';
require_once BASE_PATH . '/controllers/CustomerTagController.php';
require_once BASE_PATH . '/controllers/CustomerActivityController.php';
require_once BASE_PATH . '/controllers/CustomerTaskController.php';

// CORS Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

// Parse request
$method = $_SERVER['REQUEST_METHOD'];

// Strategy: extract the path segment after /api
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = str_replace('\\', '/', $uri);

// Support PATH_INFO (called as index.php/api/login)
if (!empty($_SERVER['PATH_INFO'])) {
    $pathInfo = rtrim($_SERVER['PATH_INFO'], '/') ?: '/';
    // Strip leading /api if present
    if (preg_match('#^/api(/.*)$#', $pathInfo, $m)) {
        $path = $m[1];
    } else {
        $path = $pathInfo;
    }
}
// Extract path segment after /api (works for both mod_rewrite and direct /api/ URLs)
elseif (preg_match('#/api(/.+)$#', $uri, $m)) {
    $path = rtrim($m[1], '/') ?: '/';
}
elseif (preg_match('#/api/?$#', $uri)) {
    $path = '/';
}
// Fallback: no /api prefix found, use as-is after stripping script dir
else {
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $path = $uri;
    if ($scriptDir && str_starts_with($path, $scriptDir)) {
        $path = substr($path, strlen($scriptDir));
    }
    $path = '/' . ltrim($path, '/');
}

$path = strtok($path, '?');
if (!str_starts_with($path, '/')) $path = '/' . $path;

// ============================================================
// Route Table
// ============================================================
$auth  = new AuthController($pdo);
$emp   = new EmployeeController($pdo);
$cust  = new CustomerController($pdo);
$tier  = new CustomerTierController($pdo);
$prod  = new ProductController($pdo);
$order = new OrderController($pdo);
$conv  = new ConversationController($pdo);
$msg   = new MessageController($pdo);
$ret   = new ReturnExchangeController($pdo);
$stats  = new StatisticsController($pdo);
$cgroup = new CustomerGroupController($pdo);
$ctag   = new CustomerTagController($pdo);
$cact   = new CustomerActivityController($pdo);
$ctask  = new CustomerTaskController($pdo);

// Helper to match patterns like /employees/123/lock → [123, 'lock']
function matchPath(string $pattern, string $path): bool|array {
    $regex = '#^' . preg_replace('/\{id\}/', '(\d+)', $pattern) . '$#';
    if (preg_match($regex, $path, $m)) {
        array_shift($m);
        return $m ?: true;
    }
    return false;
}

// ─── AUTH ────────────────────────────────────────────────────
if ($method === 'POST' && $path === '/login')  { $auth->login();  exit; }
if ($method === 'GET'  && $path === '/me')     { $auth->me();     exit; }
if ($method === 'POST' && $path === '/logout') { $auth->logout(); exit; }

// ─── EMPLOYEES ───────────────────────────────────────────────
if ($method === 'GET'   && $path === '/employees')               { $emp->index();                    exit; }
if ($m = matchPath('/employees/{id}', $path)) {
    if ($method === 'GET')    { $emp->show((int)$m[0]);           exit; }
    if ($method === 'PUT')    { $emp->update((int)$m[0]);         exit; }
    if ($method === 'DELETE') { $emp->destroy((int)$m[0]);        exit; }
}
if ($method === 'POST'  && $path === '/employees')               { $emp->store();                    exit; }
if ($m = matchPath('/employees/{id}/lock', $path))           { $emp->lock((int)$m[0]);              exit; }
if ($m = matchPath('/employees/{id}/unlock', $path))         { $emp->unlock((int)$m[0]);            exit; }
if ($m = matchPath('/employees/{id}/reset-password', $path)) { $emp->resetPassword((int)$m[0]);     exit; }
if ($m = matchPath('/employees/{id}/status', $path))         { $emp->setStatus((int)$m[0]);          exit; }

// ─── CUSTOMERS ───────────────────────────────────────────────
if ($method === 'GET'  && $path === '/customers')                { $cust->index();                   exit; }
if ($method === 'POST' && $path === '/customers')                { $cust->store();                   exit; }
if ($m = matchPath('/customers/{id}', $path)) {
    if ($method === 'GET')    { $cust->show((int)$m[0]);          exit; }
    if ($method === 'PUT')    { $cust->update((int)$m[0]);        exit; }
    if ($method === 'DELETE') { $cust->destroy((int)$m[0]);       exit; }
}
if ($method === 'PATCH' && ($m = matchPath('/customers/{id}/tier', $path)))   { $cust->updateTier((int)$m[0]); exit; }
if ($method === 'PATCH' && ($m = matchPath('/customers/{id}/assign', $path))) { $cust->assign((int)$m[0]);     exit; }
if ($method === 'GET'   &&  $path === '/customers/export')                    { $cust->export();               exit; }
if ($method === 'POST'  &&  $path === '/customers/import')                    { $cust->import();               exit; }

// ─── CUSTOMER GROUPS ─────────────────────────────────────────
if ($method === 'GET'  && $path === '/customer-groups')                                         { $cgroup->index();                     exit; }
if ($method === 'POST' && $path === '/customer-groups')                                         { $cgroup->store();                     exit; }
if ($m = matchPath('/customer-groups/{id}', $path)) {
    if ($method === 'GET')    { $cgroup->show((int)$m[0]);    exit; }
    if ($method === 'PUT')    { $cgroup->update((int)$m[0]);  exit; }
    if ($method === 'DELETE') { $cgroup->destroy((int)$m[0]); exit; }
}
if ($method === 'POST' && ($m = matchPath('/customer-groups/{id}/add-customer', $path)))    { $cgroup->addCustomer((int)$m[0]);    exit; }
if ($method === 'POST' && ($m = matchPath('/customer-groups/{id}/remove-customer', $path))) { $cgroup->removeCustomer((int)$m[0]); exit; }

// ─── CUSTOMER TAGS ───────────────────────────────────────────
if ($method === 'GET'    && $path === '/customer-tags')                                  { $ctag->index();                       exit; }
if ($method === 'POST'   && $path === '/customer-tags')                                  { $ctag->store();                       exit; }
if ($m = matchPath('/customer-tags/{id}', $path)) {
    if ($method === 'PUT')    { $ctag->update((int)$m[0]);  exit; }
    if ($method === 'DELETE') { $ctag->destroy((int)$m[0]); exit; }
}
if ($method === 'GET'  && ($m = matchPath('/customers/{id}/tags', $path))) { $ctag->getCustomerTags((int)$m[0]); exit; }
if ($method === 'POST' && ($m = matchPath('/customers/{id}/tags', $path))) { $ctag->setCustomerTags((int)$m[0]); exit; }

// ─── CUSTOMER ACTIVITIES ─────────────────────────────────────
if ($m = matchPath('/customers/{id}/activities', $path)) {
    if ($method === 'GET')  { $cact->index((int)$m[0]); exit; }
    if ($method === 'POST') { $cact->store((int)$m[0]); exit; }
}
if ($method === 'DELETE' && ($m = matchPath('/customers/{id}/activities/{id}', $path))) {
    $cact->destroy((int)$m[0], (int)$m[1]); exit;
}

// ─── CUSTOMER TASKS ──────────────────────────────────────────
if ($m = matchPath('/customers/{id}/tasks', $path)) {
    if ($method === 'GET')  { $ctask->index((int)$m[0]); exit; }
    if ($method === 'POST') { $ctask->store((int)$m[0]); exit; }
}
if ($method === 'PATCH'  && ($m = matchPath('/customers/{id}/tasks/{id}/done',   $path))) { $ctask->markDone((int)$m[0],(int)$m[1]); exit; }
if ($method === 'PATCH'  && ($m = matchPath('/customers/{id}/tasks/{id}/cancel', $path))) { $ctask->cancel((int)$m[0],(int)$m[1]);   exit; }
if ($method === 'DELETE' && ($m = matchPath('/customers/{id}/tasks/{id}',        $path))) { $ctask->destroy((int)$m[0],(int)$m[1]);  exit; }
if ($method === 'GET'    &&  $path === '/tasks/upcoming')                                  { $ctask->upcoming();                      exit; }
if ($method === 'GET'    &&  $path === '/customers/birthdays')                             { $ctask->birthdays();                     exit; }

// ─── CUSTOMER TIERS ──────────────────────────────────────────
if ($method === 'GET'  && $path === '/customer-tiers')           { $tier->index();                   exit; }
if ($method === 'POST' && $path === '/customer-tiers')           { $tier->store();                   exit; }
if ($m = matchPath('/customer-tiers/{id}', $path)) {
    if ($method === 'GET')    { $tier->show((int)$m[0]);          exit; }
    if ($method === 'PUT')    { $tier->update((int)$m[0]);        exit; }
    if ($method === 'DELETE') { $tier->destroy((int)$m[0]);       exit; }
}

// ─── PRODUCTS ────────────────────────────────────────────────
if ($method === 'GET'  && $path === '/products')                 { $prod->index();                   exit; }
if ($method === 'POST' && $path === '/products')                 { $prod->store();                   exit; }
if ($m = matchPath('/products/{id}', $path)) {
    if ($method === 'GET')    { $prod->show((int)$m[0]);          exit; }
    if ($method === 'PUT')    { $prod->update((int)$m[0]);        exit; }
    if ($method === 'DELETE') { $prod->destroy((int)$m[0]);       exit; }
}

// ─── ORDERS ──────────────────────────────────────────────────
if ($method === 'GET'  && $path === '/orders')                   { $order->index();                  exit; }
if ($method === 'POST' && $path === '/orders')                   { $order->store();                  exit; }
if ($m = matchPath('/orders/{id}', $path)) {
    if ($method === 'GET')    { $order->show((int)$m[0]);         exit; }
    if ($method === 'PUT')    { $order->update((int)$m[0]);       exit; }
    if ($method === 'DELETE') { $order->destroy((int)$m[0]);      exit; }
}
if ($method === 'PATCH' && ($m = matchPath('/orders/{id}/status', $path))) { $order->updateStatus((int)$m[0]); exit; }

// ─── CONVERSATIONS ───────────────────────────────────────────
if ($method === 'GET'  && $path === '/conversations')            { $conv->index();                   exit; }
if ($method === 'POST' && $path === '/conversations')            { $conv->store();                   exit; }
if ($m = matchPath('/conversations/{id}', $path)) {
    if ($method === 'GET')    { $conv->show((int)$m[0]);          exit; }
    if ($method === 'PUT')    { $conv->update((int)$m[0]);        exit; }
}
if ($method === 'PATCH' && ($m = matchPath('/conversations/{id}/close', $path)))  { $conv->close((int)$m[0]);  exit; }
if ($method === 'PATCH' && ($m = matchPath('/conversations/{id}/assign', $path))) { $conv->assign((int)$m[0]); exit; }

// ─── MESSAGES ────────────────────────────────────────────────
if ($m = matchPath('/conversations/{id}/messages', $path)) {
    if ($method === 'GET')  { $msg->index((int)$m[0]);  exit; }
    if ($method === 'POST') { $msg->store((int)$m[0]);  exit; }
}

// ─── RETURN / EXCHANGE ───────────────────────────────────────
if ($method === 'GET'  && $path === '/return-exchange-requests')  { $ret->index();                   exit; }
if ($method === 'POST' && $path === '/return-exchange-requests')  { $ret->store();                   exit; }
if ($m = matchPath('/return-exchange-requests/{id}', $path)) {
    if ($method === 'GET')  { $ret->show((int)$m[0]);             exit; }
}
if ($method === 'PATCH' && ($m = matchPath('/return-exchange-requests/{id}/approve', $path)))  { $ret->approve((int)$m[0]);  exit; }
if ($method === 'PATCH' && ($m = matchPath('/return-exchange-requests/{id}/reject', $path)))   { $ret->reject((int)$m[0]);   exit; }
if ($method === 'PATCH' && ($m = matchPath('/return-exchange-requests/{id}/complete', $path))) { $ret->complete((int)$m[0]); exit; }

// ─── STATISTICS ──────────────────────────────────────────────
if ($method === 'GET' && $path === '/statistics/dashboard') { $stats->dashboard(); exit; }

// ─── 404 ─────────────────────────────────────────────────────
error("Endpoint không tồn tại: [$method] /api$path", 404);
