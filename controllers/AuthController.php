<?php
/**
 * ================================================================
 * controllers/AuthController.php — Xác thực người dùng
 * ================================================================
 * Xử lý 3 endpoint:
 *   POST /api/login   — Đăng nhập, trả về JWT token
 *   GET  /api/me      — Lấy thông tin user hiện tại
 *   POST /api/logout  — Đăng xuất (xóa phiên phía client)
 */
class AuthController {
    private PDO $pdo;

    /** @param PDO $pdo Kết nối database được inject từ index.php */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * POST /api/login — Đăng nhập và lấy JWT token.
     *
     * Quy trình xác thực:
     *  1. Đọc username + password từ JSON body
     *  2. Tìm nhân viên theo username HOẶC email (hỗ trợ cả 2)
     *  3. Xác thực mật khẩu bằng password_verify() (bcrypt)
     *  4. Kiểm tra trạng thái tài khoản (không bị khóa/vô hiệu hóa)
     *  5. Cập nhật thời gian đăng nhập cuối (last_login_at)
     *  6. Tạo JWT token và trả về cùng thông tin cơ bản của nhân viên
     *
     * Lý do không trả về toàn bộ thông tin nhân viên:
     *  - Bảo mật: không lộ password_hash, created_by...
     *  - Hiệu suất: frontend chỉ cần các trường thiết yếu để hiển thị
     */
    public function login(): void {
        $body     = getJsonBody();        // Đọc { username, password } từ request body
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';

        // Kiểm tra đầu vào cơ bản trước khi truy vấn DB
        if (!$username || !$password) {
            error('Vui lòng nhập username và password.', 422); // 422 Unprocessable Entity
        }

        // Tìm nhân viên theo username HOẶC email, loại trừ tài khoản đã xóa mềm
        $stmt = $this->pdo->prepare(
            "SELECT * FROM employees WHERE (username = ? OR email = ?) AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([$username, $username]); // Dùng $username cho cả 2 tham số (email = username)
        $employee = $stmt->fetch();

        // password_verify() so sánh mật khẩu thô với hash bcrypt trong DB
        // Không so sánh thẳng chuỗi để tránh timing attack và để tương thích bcrypt
        if (!$employee || !password_verify($password, $employee['password_hash'])) {
            error('Tên đăng nhập hoặc mật khẩu không đúng.', 401);
        }

        // Kiểm tra trạng thái tài khoản (theo thứ tự ưu tiên)
        if ($employee['status'] === 'locked') {
            error('Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên.', 403);
        }
        if ($employee['status'] !== 'active') {
            error('Tài khoản chưa được kích hoạt.', 403);
        }

        // Ghi lại thời gian đăng nhập để hiển thị trong trang quản lý nhân viên
        $this->pdo->prepare("UPDATE employees SET last_login_at = NOW() WHERE id = ?")
                  ->execute([$employee['id']]);

        // Tạo JWT token với payload tối giản: chỉ cần id và role
        // Không nhúng dữ liệu nhạy cảm vào token vì token có thể bị decode
        $token = jwtEncode([
            'id'   => $employee['id'],
            'role' => $employee['role'],
        ]);

        // Trả về token + thông tin hiển thị (KHÔNG bao gồm password_hash)
        success([
            'token'    => $token,
            'employee' => [
                'id'         => $employee['id'],
                'staff_code' => $employee['staff_code'],
                'full_name'  => $employee['full_name'],
                'username'   => $employee['username'],
                'email'      => $employee['email'],
                'role'       => $employee['role'],
                'position'   => $employee['position'],
                'avatar_url' => $employee['avatar_url'],
            ],
        ], 'Đăng nhập thành công.');
    }

    /**
     * GET /api/me — Lấy thông tin đầy đủ của user đang đăng nhập.
     *
     * Dùng khi: tải lại trang (F5), frontend cần khôi phục session từ token
     * đã lưu trong localStorage mà không cần đăng nhập lại.
     *
     * requireAuth() sẽ xác thực token và lấy id user → dùng id đó query DB
     * để lấy thông tin mới nhất (tránh dùng thông tin cũ trong token).
     */
    public function me(): void {
        $user = requireAuth(); // Xác thực token, lấy thông tin user cơ bản

        // Query lại DB để lấy thông tin mới nhất (bao gồm last_login_at)
        // Không dùng thông tin trong token vì có thể đã cũ
        $stmt = $this->pdo->prepare(
            "SELECT id, staff_code, full_name, username, email, phone,
                    role, status, position, avatar_url, last_login_at, created_at
             FROM employees WHERE id = ?"
        );
        $stmt->execute([$user['id']]);
        $employee = $stmt->fetch();

        success($employee);
    }

    /**
     * POST /api/logout — Đăng xuất.
     *
     * Tại sao không xóa token phía server?
     * JWT là "stateless": server không lưu danh sách token hợp lệ,
     * nên không có gì để xóa ở server. Token sẽ tự vô hiệu khi hết hạn.
     *
     * → Việc đăng xuất thực sự xảy ra ở CLIENT: xóa token khỏi localStorage
     *   (logic này nằm trong app/main.js hàm logout())
     *
     * Hàm này chỉ xác thực token còn hợp lệ và trả về thông báo thành công
     * để frontend biết có thể an toàn chuyển về màn hình đăng nhập.
     */
    public function logout(): void {
        requireAuth(); // Xác thực token vẫn còn hợp lệ
        success(null, 'Đăng xuất thành công.');
        // Frontend (main.js) sẽ xóa token khỏi localStorage sau khi nhận response này
    }
}
