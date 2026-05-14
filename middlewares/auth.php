<?php
/**
 * Auth Middleware — Verify JWT token
 * Sets $GLOBALS['auth_user'] on success, returns 401 on failure
 */
function requireAuth(): array {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = '';

    if ($auth && str_starts_with($auth, 'Bearer ')) {
        $token = substr($auth, 7);
    } elseif (!empty($_GET['token'])) {
        $token = $_GET['token'];
    }

    if (!$token) {
        error('Chưa xác thực. Vui lòng đăng nhập.', 401);
    }
    $payload = jwtDecode($token);

    if (!$payload) {
        error('Token không hợp lệ hoặc đã hết hạn.', 401);
    }

    // Re-verify employee still active in DB
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, full_name, username, email, role, status FROM employees WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$payload['id']]);
    $employee = $stmt->fetch();

    if (!$employee) {
        error('Tài khoản không tồn tại.', 401);
    }

    if ($employee['status'] !== 'active') {
        error('Tài khoản đã bị khóa hoặc vô hiệu hóa.', 403);
    }

    $GLOBALS['auth_user'] = $employee;
    return $employee;
}

function requireRole(string ...$roles): array {
    $user = requireAuth();
    if (!in_array($user['role'], $roles)) {
        error('Bạn không có quyền thực hiện thao tác này.', 403);
    }
    return $user;
}

function authUser(): ?array {
    return $GLOBALS['auth_user'] ?? null;
}
