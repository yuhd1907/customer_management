<?php
/**
 * ================================================================
 * middlewares/auth.php — Xác thực người dùng qua JWT Token
 * ================================================================
 * "Middleware" là lớp kiểm tra chạy TRƯỚC khi xử lý nghiệp vụ chính.
 *
 * File này trả lời câu hỏi: "Người gọi API này là ai?"
 *
 * Cách dùng trong Controller:
 *   $user = requireAuth();              // Yêu cầu đăng nhập
 *   $user = requireRole('admin');       // Yêu cầu là admin
 *   $user = requireRole('admin','manager'); // admin HOẶC manager
 */

/**
 * Bắt buộc người dùng phải đăng nhập (có JWT token hợp lệ).
 *
 * Quy trình xác thực:
 *  1. Tìm token trong HTTP Header Authorization: Bearer <token>
 *     hoặc trong query string: ?token=<token> (dự phòng)
 *  2. Giải mã và xác thực chữ ký của token bằng jwtDecode()
 *  3. Truy vấn DB xem nhân viên có còn tồn tại và đang "active" không
 *     (Lý do: token có thể vẫn còn hạn dù tài khoản đã bị khóa)
 *  4. Lưu thông tin user vào $GLOBALS['auth_user'] để dùng ở bất kỳ đâu
 *
 * Kết thúc sớm với HTTP 401/403 nếu bất kỳ bước nào thất bại.
 *
 * @return array  Thông tin nhân viên đang đăng nhập
 *                ['id', 'full_name', 'username', 'email', 'role', 'status']
 */
function requireAuth(): array {
    // Lấy toàn bộ HTTP headers từ request
    $headers = getallheaders();

    // Hỗ trợ cả 'Authorization' (chuẩn) và 'authorization' (một số server viết thường)
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = '';

    if ($auth && str_starts_with($auth, 'Bearer ')) {
        // Định dạng chuẩn: "Authorization: Bearer eyJhbGci..."
        // Cắt bỏ tiền tố "Bearer " (7 ký tự) để lấy token thuần
        $token = substr($auth, 7);
    } elseif (!empty($_GET['token'])) {
        // Phương án dự phòng: token trên URL ?token=... (ít dùng hơn)
        $token = $_GET['token'];
    }

    // Không có token → chưa đăng nhập → 401 Unauthorized
    if (!$token) {
        error('Chưa xác thực. Vui lòng đăng nhập.', 401);
    }

    // Giải mã và xác thực chữ ký JWT
    // Trả về null nếu token sai, giả mạo hoặc đã hết hạn
    $payload = jwtDecode($token);

    if (!$payload) {
        error('Token không hợp lệ hoặc đã hết hạn.', 401);
    }

    // Tại sao phải truy vấn DB lại?
    // Token vẫn hợp lệ về mặt mã hóa nhưng tài khoản có thể đã bị:
    //   - Xóa mềm (deleted_at NOT NULL)
    //   - Khóa (status = 'locked' hoặc 'inactive')
    // → Cần kiểm tra DB để đảm bảo tài khoản vẫn còn hiệu lực
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT id, full_name, username, email, role, status
         FROM employees
         WHERE id = ? AND deleted_at IS NULL"
    );
    $stmt->execute([$payload['id']]);
    $employee = $stmt->fetch();

    // Tài khoản không còn tồn tại trong DB (đã bị xóa sau khi đăng nhập)
    if (!$employee) {
        error('Tài khoản không tồn tại.', 401);
    }

    // Tài khoản bị khóa hoặc vô hiệu hóa sau khi đăng nhập
    // HTTP 403 Forbidden: đã xác thực nhưng không được phép truy cập
    if ($employee['status'] !== 'active') {
        error('Tài khoản đã bị khóa hoặc vô hiệu hóa.', 403);
    }

    // Lưu thông tin người dùng vào biến toàn cục để các hàm khác dùng
    // thông qua authUser()
    $GLOBALS['auth_user'] = $employee;
    return $employee;
}

/**
 * Bắt buộc người dùng phải có một trong các vai trò được chỉ định.
 *
 * Gọi requireAuth() trước để xác thực, sau đó kiểm tra vai trò.
 *
 * Ví dụ:
 *   requireRole('admin');              // Chỉ admin
 *   requireRole('admin', 'manager');   // Admin hoặc Manager
 *
 * @param string ...$roles  Danh sách vai trò cho phép (spread params)
 * @return array            Thông tin user nếu hợp lệ, ngừng với 403 nếu không đủ quyền
 */
function requireRole(string ...$roles): array {
    $user = requireAuth(); // Xác thực token trước

    // Kiểm tra vai trò của user có nằm trong danh sách cho phép không
    if (!in_array($user['role'], $roles)) {
        error('Bạn không có quyền thực hiện thao tác này.', 403);
    }

    return $user;
}

/**
 * Lấy thông tin user đang đăng nhập (không cần kiểm tra lại).
 *
 * Dùng sau khi đã gọi requireAuth() hoặc requireRole() ở đầu hàm.
 * Tránh phải query DB nhiều lần trong cùng một request.
 *
 * @return array|null  Thông tin user hoặc null nếu chưa xác thực
 */
function authUser(): ?array {
    return $GLOBALS['auth_user'] ?? null;
}
