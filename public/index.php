<?php
/**
 * ================================================================
 * public/index.php — API Router (Bộ định tuyến trung tâm)
 * ================================================================
 * Đây là điểm vào (entry point) DUY NHẤT của toàn bộ Backend API.
 * Mọi request đến /api/* đều được chuyển hướng đến file này
 * bởi Apache thông qua file .htaccess.
 *
 * Quy trình xử lý mỗi request:
 *   1. Load dependencies (database, helpers, middlewares, controllers)
 *   2. Thiết lập CORS headers
 *   3. Phân tích URL để lấy path sau /api (VD: /customers, /orders/5)
 *   4. So khớp method + path với route table → gọi Controller tương ứng
 *   5. Nếu không khớp → trả về 404
 *
 * URL: http://localhost/customer_management/api/...
 */

/** Đường dẫn gốc của dự án (thư mục cha của /public) */
define('BASE_PATH', dirname(__DIR__));

// ── Load Core ───────────────────────────────────────────────────
// Thứ tự quan trọng: database.php phải chạy đầu tiên (tạo $pdo)
// sau đó mới load helpers, middlewares (dùng $pdo), rồi controllers
require_once BASE_PATH . '/config/database.php';     // Kết nối DB, tạo $pdo
require_once BASE_PATH . '/helpers/response.php';    // Hàm success(), error(), paginated()
require_once BASE_PATH . '/helpers/jwt.php';         // Hàm jwtEncode(), jwtDecode()
require_once BASE_PATH . '/middlewares/auth.php';    // requireAuth(), requireRole()
require_once BASE_PATH . '/middlewares/admin.php';   // requireAdmin(), requireAdminOrManager()

// ── Load Controllers ─────────────────────────────────────────────
// Mỗi controller là 1 class PHP xử lý 1 nhóm resource
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

// ── CORS Headers ─────────────────────────────────────────────────
// CORS = Cross-Origin Resource Sharing: cho phép browser từ origin khác gọi API
// Cần thiết khi frontend và backend chạy trên port/domain khác nhau

header('Content-Type: application/json; charset=utf-8'); // Mọi response đều là JSON
header('Access-Control-Allow-Origin: *');                // Cho phép mọi origin (thay * bằng domain cụ thể khi production)
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Preflight request: Browser gửi OPTIONS trước khi gửi request thật
// Phải trả về 200 để browser biết CORS được cho phép
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

// ── Parse Request ────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD']; // GET, POST, PUT, PATCH, DELETE

// Lấy đường dẫn đầy đủ từ URL
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = str_replace('\\', '/', $uri); // Chuẩn hóa separator trên Windows

// Chiến lược trích path: tìm phần sau "/api" trong URL
// Hỗ trợ nhiều cách server cấu hình khác nhau:

// Cách 1: Server truyền PATH_INFO (VD: gọi index.php/api/login)
if (!empty($_SERVER['PATH_INFO'])) {
    $pathInfo = rtrim($_SERVER['PATH_INFO'], '/') ?: '/';
    if (preg_match('#^/api(/.*)$#', $pathInfo, $m)) {
        $path = $m[1];
    } else {
        $path = $pathInfo;
    }
}
// Cách 2: URL có /api/... (mod_rewrite chuyển hướng đến index.php)
// VD: /customer_management/api/customers → path = /customers
elseif (preg_match('#/api(/.+)$#', $uri, $m)) {
    $path = rtrim($m[1], '/') ?: '/';
}
// Cách 3: URL kết thúc bằng /api (trang gốc của API)
elseif (preg_match('#/api/?$#', $uri)) {
    $path = '/';
}
// Fallback: strip thư mục script ra khỏi URL để lấy path
else {
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $path = $uri;
    if ($scriptDir && str_starts_with($path, $scriptDir)) {
        $path = substr($path, strlen($scriptDir));
    }
    $path = '/' . ltrim($path, '/');
}

// Loại bỏ query string ra khỏi path (VD: /customers?page=1 → /customers)
$path = strtok($path, '?');
if (!str_starts_with($path, '/')) $path = '/' . $path;

// ── Khởi tạo Controllers ─────────────────────────────────────────
// Inject $pdo vào constructor mỗi controller (Dependency Injection)
$auth   = new AuthController($pdo);
$emp    = new EmployeeController($pdo);
$cust   = new CustomerController($pdo);
$tier   = new CustomerTierController($pdo);
$prod   = new ProductController($pdo);
$order  = new OrderController($pdo);
$conv   = new ConversationController($pdo);
$msg    = new MessageController($pdo);
$ret    = new ReturnExchangeController($pdo);
$stats  = new StatisticsController($pdo);
$cgroup = new CustomerGroupController($pdo);
$ctag   = new CustomerTagController($pdo);
$cact   = new CustomerActivityController($pdo);
$ctask  = new CustomerTaskController($pdo);

/**
 * Hàm helper: so khớp URL pattern chứa {id} với path thực.
 *
 * Chuyển pattern dạng '/employees/{id}/lock'
 * thành regex: #^/employees/(\d+)/lock$#
 * rồi so khớp với $path.
 *
 * @param string $pattern  URL pattern (VD: '/orders/{id}/status')
 * @param string $path     Path thực của request (VD: '/orders/42/status')
 * @return bool|array      false nếu không khớp, mảng các {id} nếu khớp
 *                         VD: khớp /customers/5/tasks/3 → [5, 3]
 */
function matchPath(string $pattern, string $path): bool|array {
    // Thay {id} bằng pattern regex bắt số nguyên: (\d+)
    $regex = '#^' . preg_replace('/\{id\}/', '(\d+)', $pattern) . '$#';
    if (preg_match($regex, $path, $m)) {
        array_shift($m); // Bỏ $m[0] (toàn bộ match), chỉ giữ các capture group
        return $m ?: true; // true nếu không có {id}, mảng nếu có
    }
    return false;
}

// ================================================================
// ROUTE TABLE — Bảng định tuyến
// ================================================================
// Cú pháp: if (method === X && path === Y) { controller->action(); exit; }
// exit sau mỗi route để dừng kiểm tra các route tiếp theo

// ─── AUTH ────────────────────────────────────────────────────────
// Không yêu cầu token — đây là điểm vào trước khi có xác thực
if ($method === 'POST' && $path === '/login')  { $auth->login();  exit; } // Đăng nhập → nhận token
if ($method === 'GET'  && $path === '/me')     { $auth->me();     exit; } // Lấy thông tin user hiện tại
if ($method === 'POST' && $path === '/logout') { $auth->logout(); exit; } // Đăng xuất

// ─── EMPLOYEES ───────────────────────────────────────────────────
// Tất cả yêu cầu requireAdmin() bên trong controller
if ($method === 'GET'  && $path === '/employees')               { $emp->index();          exit; }
if ($m = matchPath('/employees/{id}', $path)) {
    if ($method === 'GET')    { $emp->show((int)$m[0]);         exit; }
    if ($method === 'PUT')    { $emp->update((int)$m[0]);       exit; }
    if ($method === 'DELETE') { $emp->destroy((int)$m[0]);      exit; }
}
if ($method === 'POST'  && $path === '/employees')               { $emp->store();                 exit; }
if ($m = matchPath('/employees/{id}/lock', $path))           { $emp->lock((int)$m[0]);            exit; }
if ($m = matchPath('/employees/{id}/unlock', $path))         { $emp->unlock((int)$m[0]);          exit; }
if ($m = matchPath('/employees/{id}/reset-password', $path)) { $emp->resetPassword((int)$m[0]);   exit; }
if ($m = matchPath('/employees/{id}/status', $path))         { $emp->setStatus((int)$m[0]);       exit; }

// ─── CUSTOMERS ───────────────────────────────────────────────────
if ($method === 'GET'  && $path === '/customers')               { $cust->index();                  exit; }
if ($method === 'POST' && $path === '/customers')               { $cust->store();                  exit; }
if ($m = matchPath('/customers/{id}', $path)) {
    if ($method === 'GET')    { $cust->show((int)$m[0]);        exit; }
    if ($method === 'PUT')    { $cust->update((int)$m[0]);      exit; }
    if ($method === 'DELETE') { $cust->destroy((int)$m[0]);     exit; }
}
// Các route đặc biệt phải đặt TRƯỚC route chung /customers/{id} nếu cùng prefix
if ($method === 'PATCH' && ($m = matchPath('/customers/{id}/tier',   $path))) { $cust->updateTier((int)$m[0]); exit; }
if ($method === 'PATCH' && ($m = matchPath('/customers/{id}/assign', $path))) { $cust->assign((int)$m[0]);     exit; }
if ($method === 'GET'   &&  $path === '/customers/export')                     { $cust->export();               exit; }
if ($method === 'POST'  &&  $path === '/customers/import')                     { $cust->import();               exit; }

// ─── CUSTOMER GROUPS ─────────────────────────────────────────────
if ($method === 'GET'  && $path === '/customer-groups')                                         { $cgroup->index();                    exit; }
if ($method === 'POST' && $path === '/customer-groups')                                         { $cgroup->store();                    exit; }
if ($m = matchPath('/customer-groups/{id}', $path)) {
    if ($method === 'GET')    { $cgroup->show((int)$m[0]);    exit; }
    if ($method === 'PUT')    { $cgroup->update((int)$m[0]);  exit; }
    if ($method === 'DELETE') { $cgroup->destroy((int)$m[0]); exit; }
}
if ($method === 'POST' && ($m = matchPath('/customer-groups/{id}/add-customer',    $path))) { $cgroup->addCustomer((int)$m[0]);    exit; }
if ($method === 'POST' && ($m = matchPath('/customer-groups/{id}/remove-customer', $path))) { $cgroup->removeCustomer((int)$m[0]); exit; }

// ─── CUSTOMER TAGS ───────────────────────────────────────────────
if ($method === 'GET'  && $path === '/customer-tags')                                  { $ctag->index();                      exit; }
if ($method === 'POST' && $path === '/customer-tags')                                  { $ctag->store();                      exit; }
if ($m = matchPath('/customer-tags/{id}', $path)) {
    if ($method === 'PUT')    { $ctag->update((int)$m[0]);  exit; }
    if ($method === 'DELETE') { $ctag->destroy((int)$m[0]); exit; }
}
if ($method === 'GET'  && ($m = matchPath('/customers/{id}/tags', $path))) { $ctag->getCustomerTags((int)$m[0]); exit; }
if ($method === 'POST' && ($m = matchPath('/customers/{id}/tags', $path))) { $ctag->setCustomerTags((int)$m[0]); exit; }

// ─── CUSTOMER ACTIVITIES (Lịch sử hoạt động KH) ─────────────────
if ($m = matchPath('/customers/{id}/activities', $path)) {
    if ($method === 'GET')  { $cact->index((int)$m[0]); exit; }
    if ($method === 'POST') { $cact->store((int)$m[0]); exit; }
}
// Route với 2 {id}: customer_id và activity_id
if ($method === 'DELETE' && ($m = matchPath('/customers/{id}/activities/{id}', $path))) {
    $cact->destroy((int)$m[0], (int)$m[1]); exit;
}

// ─── CUSTOMER TASKS (Công việc theo dõi KH) ──────────────────────
if ($m = matchPath('/customers/{id}/tasks', $path)) {
    if ($method === 'GET')  { $ctask->index((int)$m[0]); exit; }
    if ($method === 'POST') { $ctask->store((int)$m[0]); exit; }
}
if ($method === 'PATCH'  && ($m = matchPath('/customers/{id}/tasks/{id}/done',   $path))) { $ctask->markDone((int)$m[0], (int)$m[1]); exit; }
if ($method === 'PATCH'  && ($m = matchPath('/customers/{id}/tasks/{id}/cancel', $path))) { $ctask->cancel((int)$m[0],   (int)$m[1]); exit; }
if ($method === 'DELETE' && ($m = matchPath('/customers/{id}/tasks/{id}',        $path))) { $ctask->destroy((int)$m[0],  (int)$m[1]); exit; }
if ($method === 'GET'    &&  $path === '/tasks/upcoming')                                  { $ctask->upcoming();                       exit; } // Tasks sắp đến hạn
if ($method === 'GET'    &&  $path === '/customers/birthdays')                             { $ctask->birthdays();                      exit; } // KH có sinh nhật gần đây

// ─── CUSTOMER TIERS (Hạng khách hàng) ────────────────────────────
if ($method === 'GET'  && $path === '/customer-tiers')           { $tier->index();                  exit; }
if ($method === 'POST' && $path === '/customer-tiers')           { $tier->store();                  exit; }
if ($m = matchPath('/customer-tiers/{id}', $path)) {
    if ($method === 'GET')    { $tier->show((int)$m[0]);         exit; }
    if ($method === 'PUT')    { $tier->update((int)$m[0]);       exit; }
    if ($method === 'DELETE') { $tier->destroy((int)$m[0]);      exit; }
}

// ─── PRODUCTS ────────────────────────────────────────────────────
if ($method === 'GET'  && $path === '/products')                 { $prod->index();                  exit; }
if ($method === 'POST' && $path === '/products')                 { $prod->store();                  exit; }
if ($m = matchPath('/products/{id}', $path)) {
    if ($method === 'GET')    { $prod->show((int)$m[0]);         exit; }
    if ($method === 'PUT')    { $prod->update((int)$m[0]);       exit; }
    if ($method === 'DELETE') { $prod->destroy((int)$m[0]);      exit; }
}

// ─── ORDERS ──────────────────────────────────────────────────────
if ($method === 'GET'  && $path === '/orders')                   { $order->index();                 exit; }
if ($method === 'POST' && $path === '/orders')                   { $order->store();                 exit; }
if ($m = matchPath('/orders/{id}', $path)) {
    if ($method === 'GET')    { $order->show((int)$m[0]);        exit; }
    if ($method === 'PUT')    { $order->update((int)$m[0]);      exit; }
    if ($method === 'DELETE') { $order->destroy((int)$m[0]);     exit; }
}
if ($method === 'PATCH' && ($m = matchPath('/orders/{id}/status', $path))) { $order->updateStatus((int)$m[0]); exit; }

// ─── CONVERSATIONS (Chat CSKH) ────────────────────────────────────
if ($method === 'GET'  && $path === '/conversations')            { $conv->index();                  exit; }
if ($method === 'POST' && $path === '/conversations')            { $conv->store();                  exit; }
if ($m = matchPath('/conversations/{id}', $path)) {
    if ($method === 'GET') { $conv->show((int)$m[0]);            exit; }
    if ($method === 'PUT') { $conv->update((int)$m[0]);          exit; }
}
if ($method === 'PATCH' && ($m = matchPath('/conversations/{id}/close',  $path))) { $conv->close((int)$m[0]);  exit; }
if ($method === 'PATCH' && ($m = matchPath('/conversations/{id}/assign', $path))) { $conv->assign((int)$m[0]); exit; }

// ─── MESSAGES ────────────────────────────────────────────────────
if ($m = matchPath('/conversations/{id}/messages', $path)) {
    if ($method === 'GET')  { $msg->index((int)$m[0]); exit; }
    if ($method === 'POST') { $msg->store((int)$m[0]); exit; }
}

// ─── RETURN / EXCHANGE (Đổi / Hoàn trả) ─────────────────────────
if ($method === 'GET'  && $path === '/return-exchange-requests')  { $ret->index(); exit; }
if ($method === 'POST' && $path === '/return-exchange-requests')  { $ret->store(); exit; }
if ($m = matchPath('/return-exchange-requests/{id}', $path)) {
    if ($method === 'GET') { $ret->show((int)$m[0]); exit; }
}
if ($method === 'PATCH' && ($m = matchPath('/return-exchange-requests/{id}/approve',  $path))) { $ret->approve((int)$m[0]);  exit; }
if ($method === 'PATCH' && ($m = matchPath('/return-exchange-requests/{id}/reject',   $path))) { $ret->reject((int)$m[0]);   exit; }
if ($method === 'PATCH' && ($m = matchPath('/return-exchange-requests/{id}/complete', $path))) { $ret->complete((int)$m[0]); exit; }

// ─── STATISTICS (Thống kê Dashboard) ─────────────────────────────
if ($method === 'GET' && $path === '/statistics/dashboard') { $stats->dashboard(); exit; }

// ─── 404 — Không khớp route nào ──────────────────────────────────
// Nếu không có route nào trên khớp với method + path → báo lỗi 404
error("Endpoint không tồn tại: [$method] /api$path", 404);
