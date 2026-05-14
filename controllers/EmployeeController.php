<?php

class EmployeeController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // GET /api/employees
    public function index(): void {
        requireAdmin();
        $p      = getPaginationParams();
        $search = $_GET['search'] ?? '';
        $role   = $_GET['role'] ?? '';
        $status = $_GET['status'] ?? '';

        $where  = ['deleted_at IS NULL'];
        $params = [];

        if ($search) {
            $where[] = "(full_name LIKE ? OR username LIKE ? OR email LIKE ? OR staff_code LIKE ?)";
            $s = "%$search%";
            array_push($params, $s, $s, $s, $s);
        }
        if ($role && in_array($role, ['admin','manager','staff'])) {
            $where[] = "role = ?"; $params[] = $role;
        }
        if ($status && in_array($status, ['active','inactive','locked'])) {
            $where[] = "status = ?"; $params[] = $status;
        }

        $sql = "WHERE " . implode(" AND ", $where);

        $total = $this->pdo->prepare("SELECT COUNT(*) FROM employees $sql");
        $total->execute($params);
        $total = (int) $total->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT id, staff_code, full_name, username, email, phone, role, status, position, avatar_url, last_login_at, created_at
             FROM employees $sql ORDER BY id DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}"
        );
        $stmt->execute($params);
        $employees = $stmt->fetchAll();

        paginated($employees, $total, $p['page'], $p['per_page']);
    }

    // GET /api/employees/{id}
    public function show(int $id): void {
        requireAdmin();
        $stmt = $this->pdo->prepare(
            "SELECT id, staff_code, full_name, username, email, phone, role, status, position, avatar_url, last_login_at, created_at
             FROM employees WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$id]);
        $employee = $stmt->fetch();
        if (!$employee) error('Không tìm thấy nhân viên.', 404);
        success($employee);
    }

    // POST /api/employees
    public function store(): void {
        $actor = requireAdmin();
        $body  = getJsonBody();

        $required = ['full_name', 'username', 'email', 'password'];
        foreach ($required as $f) {
            if (empty($body[$f])) error("Thiếu trường bắt buộc: $f.", 422);
        }

        if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) error('Email không hợp lệ.', 422);
        if (strlen($body['password']) < 6) error('Mật khẩu phải có ít nhất 6 ký tự.', 422);

        $role = $body['role'] ?? 'staff';
        if (!in_array($role, ['admin','manager','staff'])) error('Role không hợp lệ.', 422);

        // Check unique
        $check = $this->pdo->prepare("SELECT id FROM employees WHERE (username = ? OR email = ?) AND deleted_at IS NULL");
        $check->execute([$body['username'], $body['email']]);
        if ($check->fetch()) error('Username hoặc email đã tồn tại.', 409);

        // Auto generate staff code
        $staffCode = 'EMP' . str_pad($this->pdo->query("SELECT MAX(id)+1 FROM employees")->fetchColumn(), 4, '0', STR_PAD_LEFT);

        $stmt = $this->pdo->prepare(
            "INSERT INTO employees (staff_code, full_name, username, email, phone, password_hash, role, status, position, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)"
        );
        $stmt->execute([
            $staffCode,
            $body['full_name'],
            $body['username'],
            $body['email'],
            $body['phone'] ?? null,
            password_hash($body['password'], PASSWORD_BCRYPT),
            $role,
            $body['position'] ?? null,
            $actor['id'],
        ]);
        $id = $this->pdo->lastInsertId();
        $this->log($actor['id'], 'create_employee', 'employees', $id, "Tạo nhân viên: {$body['full_name']}");
        $this->show($id);
    }

    // PUT /api/employees/{id}
    public function update(int $id): void {
        $actor = requireAdmin();
        $this->_findOrFail($id);
        $body  = getJsonBody();

        $fields = []; $params = [];

        if (isset($body['full_name']))  { $fields[] = "full_name = ?";  $params[] = $body['full_name']; }
        if (isset($body['email'])) {
            if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) error('Email không hợp lệ.', 422);
            $fields[] = "email = ?"; $params[] = $body['email'];
        }
        if (isset($body['phone']))      { $fields[] = "phone = ?";      $params[] = $body['phone']; }
        if (isset($body['position']))   { $fields[] = "position = ?";   $params[] = $body['position']; }
        if (isset($body['role']) && in_array($body['role'], ['admin','manager','staff'])) {
            $fields[] = "role = ?"; $params[] = $body['role'];
        }
        if (isset($body['avatar_url'])) { $fields[] = "avatar_url = ?"; $params[] = $body['avatar_url']; }

        if (empty($fields)) error('Không có trường nào để cập nhật.', 422);

        $fields[] = "updated_by = ?"; $params[] = $actor['id'];
        $params[] = $id;

        $this->pdo->prepare("UPDATE employees SET " . implode(', ', $fields) . " WHERE id = ?")
                  ->execute($params);

        $this->log($actor['id'], 'update_employee', 'employees', $id, "Cập nhật nhân viên #$id");
        $this->show($id);
    }

    // DELETE /api/employees/{id}
    public function destroy(int $id): void {
        $actor = requireAdmin();
        $emp   = $this->_findOrFail($id);

        if ($id === $actor['id']) error('Không thể xóa chính mình.', 400);

        $this->pdo->prepare("UPDATE employees SET deleted_at = NOW(), updated_by = ? WHERE id = ?")
                  ->execute([$actor['id'], $id]);

        $this->log($actor['id'], 'delete_employee', 'employees', $id, "Xóa mềm nhân viên: {$emp['full_name']}");
        success(null, 'Đã xóa nhân viên thành công.');
    }

    // PATCH /api/employees/{id}/lock
    public function lock(int $id): void {
        $actor = requireAdmin();
        $emp   = $this->_findOrFail($id);
        if ($id === $actor['id']) error('Không thể khóa chính mình.', 400);
        $this->pdo->prepare("UPDATE employees SET status = 'locked', updated_by = ? WHERE id = ?")
                  ->execute([$actor['id'], $id]);
        $this->log($actor['id'], 'lock_employee', 'employees', $id, "Khóa tài khoản: {$emp['full_name']}");
        success(null, 'Đã khóa tài khoản.');
    }

    // PATCH /api/employees/{id}/unlock
    public function unlock(int $id): void {
        $actor = requireAdmin();
        $this->_findOrFail($id);
        $this->pdo->prepare("UPDATE employees SET status = 'active', updated_by = ? WHERE id = ?")
                  ->execute([$actor['id'], $id]);
        $this->log($actor['id'], 'unlock_employee', 'employees', $id, "Mở khóa tài khoản #$id");
        success(null, 'Đã mở khóa tài khoản.');
    }

    // PATCH /api/employees/{id}/reset-password
    public function resetPassword(int $id): void {
        $actor = requireAdmin();
        $this->_findOrFail($id);
        $body  = getJsonBody();
        $pwd   = $body['password'] ?? $body['new_password'] ?? null;
        if (empty($pwd)) error('Thiếu mật khẩu mới.', 422);
        if (strlen($pwd) < 6) error('Mật khẩu phải có ít nhất 6 ký tự.', 422);

        $hash = password_hash($pwd, PASSWORD_BCRYPT);
        $this->pdo->prepare("UPDATE employees SET password_hash = ?, updated_by = ? WHERE id = ?")
                  ->execute([$hash, $actor['id'], $id]);
        $this->log($actor['id'], 'reset_password', 'employees', $id, "Đặt lại mật khẩu nhân viên #$id");
        success(null, 'Đã đặt lại mật khẩu.');
    }

    // PATCH /api/employees/{id}/status
    public function setStatus(int $id): void {
        $actor = requireAdmin();
        $emp   = $this->_findOrFail($id);
        if ($id === $actor['id']) error('Không thể thay đổi trạng thái chính mình.', 400);
        $body  = getJsonBody();
        $status = $body['status'] ?? '';
        if (!in_array($status, ['active','inactive','locked'])) error('Trạng thái không hợp lệ.', 422);
        $this->pdo->prepare("UPDATE employees SET status = ?, updated_by = ? WHERE id = ?")
                  ->execute([$status, $actor['id'], $id]);
        $this->log($actor['id'], 'set_status', 'employees', $id, "Đổi trạng thái NV #{$id} -> {$status}");
        success(null, 'Đã cập nhật trạng thái.');
    }

    private function _findOrFail(int $id): array {
        $stmt = $this->pdo->prepare("SELECT * FROM employees WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $emp = $stmt->fetch();
        if (!$emp) error('Không tìm thấy nhân viên.', 404);
        return $emp;
    }

    private function log(int $employeeId, string $action, string $table, int $recordId, string $desc): void {
        $this->pdo->prepare("INSERT INTO activity_logs (employee_id, action, table_name, record_id, description) VALUES (?,?,?,?,?)")
                  ->execute([$employeeId, $action, $table, $recordId, $desc]);
    }
}
