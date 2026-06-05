<?php
/**
 * ================================================================
 * config/database.php — Cấu hình kết nối Database & JWT
 * ================================================================
 * File này được load đầu tiên bởi public/index.php.
 * Sau khi chạy xong, biến $pdo có thể dùng ở mọi nơi.
 *
 * ⚠️ QUAN TRỌNG: File này chứa thông tin nhạy cảm.
 *    - Không commit file này lên Git nếu dùng DB thật/production
 *    - Thay JWT_SECRET bằng chuỗi ngẫu nhiên dài trước khi triển khai
 */

// ── Thông tin kết nối MySQL ─────────────────────────────────────
define('DB_HOST',    '127.0.0.1');          // Địa chỉ MySQL server (localhost)
define('DB_NAME',    'customer_management'); // Tên database
define('DB_USER',    'root');               // Username MySQL
define('DB_PASS',    '1907');              // Mật khẩu MySQL — ĐỔI KHI DEPLOY
define('DB_CHARSET', 'utf8mb4');            // Charset hỗ trợ đầy đủ Unicode + emoji

// ── Cấu hình JWT ───────────────────────────────────────────────
// JWT_SECRET: Khóa bí mật dùng để ký token. Phải là chuỗi ngẫu nhiên dài.
// Nếu lộ key này, ai cũng có thể tự tạo token hợp lệ!
define('JWT_SECRET', 'crm_jwt_secret_key_2024_change_this'); // ĐỔI TRƯỚC KHI DEPLOY

// JWT_EXPIRE: Thời gian sống của token tính bằng giây
// 86400 = 60s × 60m × 24h = 24 giờ
define('JWT_EXPIRE', 86400);

// ── Khởi tạo kết nối PDO ────────────────────────────────────────
// PDO (PHP Data Objects) là lớp abstraction database của PHP.
// Cho phép thay đổi loại DB (MySQL → PostgreSQL) mà không cần sửa nhiều code.
try {
    $pdo = new PDO(
        // DSN (Data Source Name): chuỗi xác định loại DB, host, tên DB và charset
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            // Ném exception khi có lỗi SQL thay vì trả về false âm thầm
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

            // Tự động trả về mảng kết hợp (key => value) thay vì mảng số
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // Dùng Prepared Statements thật (không giả lập)
            // Quan trọng cho bảo mật: ngăn SQL injection hoàn toàn
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Nếu không kết nối được DB, dừng toàn bộ ứng dụng và báo lỗi JSON
    // Không để lộ chi tiết lỗi ra ngoài ở môi trường production
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}