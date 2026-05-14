<?php

class AuthController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // POST /api/login
    public function login(): void {
        $body     = getJsonBody();
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';

        if (!$username || !$password) {
            error('Vui lòng nhập username và password.', 422);
        }

        $stmt = $this->pdo->prepare(
            "SELECT * FROM employees WHERE (username = ? OR email = ?) AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([$username, $username]);
        $employee = $stmt->fetch();

        if (!$employee || !password_verify($password, $employee['password_hash'])) {
            error('Tên đăng nhập hoặc mật khẩu không đúng.', 401);
        }

        if ($employee['status'] === 'locked') {
            error('Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên.', 403);
        }

        if ($employee['status'] !== 'active') {
            error('Tài khoản chưa được kích hoạt.', 403);
        }

        // Update last login
        $this->pdo->prepare("UPDATE employees SET last_login_at = NOW() WHERE id = ?")
                  ->execute([$employee['id']]);

        $token = jwtEncode([
            'id'   => $employee['id'],
            'role' => $employee['role'],
        ]);

        success([
            'token' => $token,
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

    // GET /api/me
    public function me(): void {
        $user = requireAuth();

        $stmt = $this->pdo->prepare(
            "SELECT id, staff_code, full_name, username, email, phone, role, status, position, avatar_url, last_login_at, created_at
             FROM employees WHERE id = ?"
        );
        $stmt->execute([$user['id']]);
        $employee = $stmt->fetch();

        success($employee);
    }

    // POST /api/logout
    public function logout(): void {
        requireAuth();
        // JWT is stateless; client should discard the token
        success(null, 'Đăng xuất thành công.');
    }
}
