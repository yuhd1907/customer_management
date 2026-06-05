<?php
/**
 * ================================================================
 * controllers/EmployeeController.php — Quản lý Nhân viên
 * ================================================================
 * Toàn bộ module quản lý nhân viên — chỉ Admin mới có quyền truy cập.
 *
 * Các endpoint:
 *   GET    /api/employees                      — Danh sách nhân viên
 *   GET    /api/employees/{id}                 — Chi tiết nhân viên
 *   POST   /api/employees                      — Tạo tài khoản mới
 *   PUT    /api/employees/{id}                 — Cập nhật thông tin
 *   DELETE /api/employees/{id}                 — Xóa mềm (không thể tự xóa mình)
 *   PATCH  /api/employees/{id}/lock            — Khóa tài khoản
 *   PATCH  /api/employees/{id}/unlock          — Mở khóa tài khoản
 *   PATCH  /api/employees/{id}/reset-password  — Đặt lại mật khẩu
 *   PATCH  /api/employees/{id}/status          — Thay đổi trạng thái tùy ý
 *
 * Bảo mật: Tất cả endpoint yêu cầu requireAdmin() — chỉ admin truy cập được.
 * Phân quyền self-restriction: admin không thể tự khóa/xóa/thay đổi status của chính mình.
 */
class EmployeeController {
    private PDO $pdo;

    /** @param PDO $pdo Kết nối database được inject từ index.php */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * GET /api/employees — Danh sách nhân viên (chỉ Admin).
     *
     * Không trả về password_hash — chỉ các trường an toàn.
     * Hỗ trợ filter:
     *   ?search=Lan       — Tìm theo tên, username, email, hoặc mã NV
     *   ?role=staff       — Lọc theo vai trò (admin/manager/staff)
     *   ?status=active    — Lọc theo trạng thái (active/inactive/locked)
     */
    public function index(): void {
        requireAdmin(); // Toàn bộ module này chỉ dành cho Admin
        $p      = getPaginationParams();
        $search = $_GET['search'] ?? '';
        $role   = $_GET['role']   ?? '';
        $status = $_GET['status'] ?? '';

        $where  = ['deleted_at IS NULL']; // Luôn lọc bỏ tài khoản đã xóa mềm
        $params = [];

        // Tìm kiếm trên 4 cột: tên, username, email, mã nhân viên
        if ($search) {
            $where[] = "(full_name LIKE ? OR username LIKE ? OR email LIKE ? OR staff_code LIKE ?)";
            $s = "%$search%";
            array_push($params, $s, $s, $s, $s);
        }

        // Whitelist để tránh SQL injection
        if ($role && in_array($role, ['admin', 'manager', 'staff'])) {
            $where[] = "role = ?"; $params[] = $role;
        }
        if ($status && in_array($status, ['active', 'inactive', 'locked'])) {
            $where[] = "status = ?"; $params[] = $status;
        }

        $sql = "WHERE " . implode(" AND ", $where);

        $total = $this->pdo->prepare("SELECT COUNT(*) FROM employees $sql");
        $total->execute($params);
        $total = (int) $total->fetchColumn();

        // Chỉ SELECT các cột cần thiết — KHÔNG bao giờ SELECT password_hash
        $stmt = $this->pdo->prepare(
            "SELECT id, staff_code, full_name, username, email, phone,
                    role, status, position, avatar_url, last_login_at, created_at
             FROM employees $sql
             ORDER BY id DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}"
        );
        $stmt->execute($params);
        $employees = $stmt->fetchAll();

        paginated($employees, $total, $p['page'], $p['per_page']);
    }

    /**
     * GET /api/employees/{id} — Chi tiết 1 nhân viên (chỉ Admin).
     *
     * @param int $id  ID nhân viên
     */
    public function show(int $id): void {
        requireAdmin();

        // Chỉ SELECT các trường an toàn, không lộ password_hash
        $stmt = $this->pdo->prepare(
            "SELECT id, staff_code, full_name, username, email, phone,
                    role, status, position, avatar_url, last_login_at, created_at
             FROM employees WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$id]);
        $employee = $stmt->fetch();

        if (!$employee) error('Không tìm thấy nhân viên.', 404);
        success($employee);
    }

    /**
     * POST /api/employees — Tạo tài khoản nhân viên mới (chỉ Admin).
     *
     * Validate:
     *  - full_name, username, email, password là bắt buộc
     *  - email phải đúng định dạng
     *  - password phải ít nhất 6 ký tự
     *  - username và email phải là duy nhất
     *  - role phải là admin/manager/staff
     *
     * Mã nhân viên (staff_code) tự động sinh: EMP0001, EMP0002...
     * Mật khẩu được mã hóa bcrypt trước khi lưu vào DB.
     */
    public function store(): void {
        $actor = requireAdmin();
        $body  = getJsonBody();

        // Validate tất cả trường bắt buộc
        $required = ['full_name', 'username', 'email', 'password'];
        foreach ($required as $f) {
            if (empty($body[$f])) error("Thiếu trường bắt buộc: $f.", 422);
        }

        // Validate định dạng email bằng filter_var (hàm built-in của PHP)
        if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) error('Email không hợp lệ.', 422);
        if (strlen($body['password']) < 6) error('Mật khẩu phải có ít nhất 6 ký tự.', 422);

        $role = $body['role'] ?? 'staff';
        if (!in_array($role, ['admin', 'manager', 'staff'])) error('Role không hợp lệ.', 422);

        // Kiểm tra username hoặc email đã tồn tại (không tính tài khoản đã xóa mềm)
        $check = $this->pdo->prepare(
            "SELECT id FROM employees WHERE (username = ? OR email = ?) AND deleted_at IS NULL"
        );
        $check->execute([$body['username'], $body['email']]);
        if ($check->fetch()) error('Username hoặc email đã tồn tại.', 409);

        // Tự sinh mã nhân viên: lấy MAX(id) + 1, pad với 0 thành 4 chữ số
        // VD: MAX id là 41 → staff_code = 'EMP0042'
        $staffCode = 'EMP' . str_pad(
            $this->pdo->query("SELECT MAX(id)+1 FROM employees")->fetchColumn(),
            4, '0', STR_PAD_LEFT
        );

        $stmt = $this->pdo->prepare(
            "INSERT INTO employees
             (staff_code, full_name, username, email, phone, password_hash, role, status, position, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)"
        );
        $stmt->execute([
            $staffCode,
            $body['full_name'],
            $body['username'],
            $body['email'],
            $body['phone']    ?? null,
            // password_hash() với bcrypt: tự động thêm salt, không thể reverse-engineer
            password_hash($body['password'], PASSWORD_BCRYPT),
            $role,
            $body['position'] ?? null,
            $actor['id'], // Ghi audit: admin nào đã tạo tài khoản này
        ]);

        $id = $this->pdo->lastInsertId();
        $this->log($actor['id'], 'create_employee', 'employees', $id, "Tạo nhân viên: {$body['full_name']}");
        $this->show($id);
    }

    /**
     * PUT /api/employees/{id} — Cập nhật thông tin nhân viên (chỉ Admin).
     *
     * Các trường có thể cập nhật: full_name, email, phone, position, role, avatar_url.
     * Không thể cập nhật password qua endpoint này (dùng /reset-password riêng).
     * Không thể cập nhật username (đây là định danh cố định).
     *
     * @param int $id  ID nhân viên cần cập nhật
     */
    public function update(int $id): void {
        $actor = requireAdmin();
        $this->_findOrFail($id);
        $body  = getJsonBody();

        $fields = []; $params = [];

        // Xây dựng SET động: chỉ update cột nào được gửi lên
        if (isset($body['full_name'])) {
            $fields[] = "full_name = ?"; $params[] = $body['full_name'];
        }
        if (isset($body['email'])) {
            if (!filter_var($body['email'], FILTER_VALIDATE_EMAIL)) error('Email không hợp lệ.', 422);
            $fields[] = "email = ?"; $params[] = $body['email'];
        }
        if (isset($body['phone']))    { $fields[] = "phone = ?";    $params[] = $body['phone']; }
        if (isset($body['position'])) { $fields[] = "position = ?"; $params[] = $body['position']; }
        if (isset($body['role']) && in_array($body['role'], ['admin', 'manager', 'staff'])) {
            $fields[] = "role = ?"; $params[] = $body['role'];
        }
        if (isset($body['avatar_url'])) { $fields[] = "avatar_url = ?"; $params[] = $body['avatar_url']; }

        if (empty($fields)) error('Không có trường nào để cập nhật.', 422);

        // Ghi audit: ai đã cập nhật lần cuối
        $fields[] = "updated_by = ?"; $params[] = $actor['id'];
        $params[] = $id;

        $this->pdo->prepare("UPDATE employees SET " . implode(', ', $fields) . " WHERE id = ?")
                  ->execute($params);

        $this->log($actor['id'], 'update_employee', 'employees', $id, "Cập nhật nhân viên #$id");
        $this->show($id);
    }

    /**
     * DELETE /api/employees/{id} — Xóa mềm tài khoản nhân viên (chỉ Admin).
     *
     * Admin không thể xóa chính mình (ngăn chặn khóa mình ra khỏi hệ thống).
     * Xóa mềm: ghi deleted_at, không xóa dữ liệu thật (giữ lịch sử hoạt động).
     *
     * @param int $id  ID nhân viên cần xóa
     */
    public function destroy(int $id): void {
        $actor = requireAdmin();
        $emp   = $this->_findOrFail($id);

        // Ngăn admin tự xóa tài khoản của mình — tránh khóa ra khỏi hệ thống
        if ($id === $actor['id']) error('Không thể xóa chính mình.', 400);

        // Soft delete: chỉ ghi thời gian xóa
        $this->pdo->prepare(
            "UPDATE employees SET deleted_at = NOW(), updated_by = ? WHERE id = ?"
        )->execute([$actor['id'], $id]);

        $this->log($actor['id'], 'delete_employee', 'employees', $id, "Xóa mềm nhân viên: {$emp['full_name']}");
        success(null, 'Đã xóa nhân viên thành công.');
    }

    /**
     * PATCH /api/employees/{id}/lock — Khóa tài khoản nhân viên.
     *
     * Sau khi khóa: nhân viên không thể đăng nhập (requireAuth kiểm tra status).
     * Admin không thể tự khóa mình.
     *
     * @param int $id  ID nhân viên cần khóa
     */
    public function lock(int $id): void {
        $actor = requireAdmin();
        $emp   = $this->_findOrFail($id);

        if ($id === $actor['id']) error('Không thể khóa chính mình.', 400);

        $this->pdo->prepare(
            "UPDATE employees SET status = 'locked', updated_by = ? WHERE id = ?"
        )->execute([$actor['id'], $id]);

        $this->log($actor['id'], 'lock_employee', 'employees', $id, "Khóa tài khoản: {$emp['full_name']}");
        success(null, 'Đã khóa tài khoản.');
    }

    /**
     * PATCH /api/employees/{id}/unlock — Mở khóa tài khoản nhân viên.
     *
     * Chuyển status về 'active', nhân viên có thể đăng nhập lại ngay lập tức.
     *
     * @param int $id  ID nhân viên cần mở khóa
     */
    public function unlock(int $id): void {
        $actor = requireAdmin();
        $this->_findOrFail($id);

        $this->pdo->prepare(
            "UPDATE employees SET status = 'active', updated_by = ? WHERE id = ?"
        )->execute([$actor['id'], $id]);

        $this->log($actor['id'], 'unlock_employee', 'employees', $id, "Mở khóa tài khoản #$id");
        success(null, 'Đã mở khóa tài khoản.');
    }

    /**
     * PATCH /api/employees/{id}/reset-password — Đặt lại mật khẩu (chỉ Admin).
     *
     * Admin có thể đặt mật khẩu mới cho bất kỳ nhân viên nào.
     * Mật khẩu mới được hash bcrypt trước khi lưu.
     * Chấp nhận cả key 'password' và 'new_password' từ client.
     *
     * @param int $id  ID nhân viên cần reset password
     */
    public function resetPassword(int $id): void {
        $actor = requireAdmin();
        $this->_findOrFail($id);
        $body  = getJsonBody();

        // Hỗ trợ cả 2 tên field từ client (linh hoạt)
        $pwd = $body['password'] ?? $body['new_password'] ?? null;

        if (empty($pwd)) error('Thiếu mật khẩu mới.', 422);
        if (strlen($pwd) < 6) error('Mật khẩu phải có ít nhất 6 ký tự.', 422);

        // Hash mật khẩu mới với bcrypt trước khi lưu vào DB
        $hash = password_hash($pwd, PASSWORD_BCRYPT);
        $this->pdo->prepare(
            "UPDATE employees SET password_hash = ?, updated_by = ? WHERE id = ?"
        )->execute([$hash, $actor['id'], $id]);

        $this->log($actor['id'], 'reset_password', 'employees', $id, "Đặt lại mật khẩu nhân viên #$id");
        success(null, 'Đã đặt lại mật khẩu.');
    }

    /**
     * PATCH /api/employees/{id}/status — Đổi trạng thái tùy ý (chỉ Admin).
     *
     * Cho phép chuyển tự do giữa: active ↔ inactive ↔ locked
     * Admin không thể thay đổi trạng thái của chính mình.
     *
     * @param int $id  ID nhân viên cần thay đổi status
     */
    public function setStatus(int $id): void {
        $actor  = requireAdmin();
        $emp    = $this->_findOrFail($id);

        if ($id === $actor['id']) error('Không thể thay đổi trạng thái chính mình.', 400);

        $body   = getJsonBody();
        $status = $body['status'] ?? '';

        // Chỉ chấp nhận các trạng thái hợp lệ
        if (!in_array($status, ['active', 'inactive', 'locked'])) {
            error('Trạng thái không hợp lệ.', 422);
        }

        $this->pdo->prepare(
            "UPDATE employees SET status = ?, updated_by = ? WHERE id = ?"
        )->execute([$status, $actor['id'], $id]);

        $this->log($actor['id'], 'set_status', 'employees', $id, "Đổi trạng thái NV #{$id} → {$status}");
        success(null, 'Đã cập nhật trạng thái.');
    }

    /**
     * Tìm nhân viên theo ID hoặc ném lỗi 404.
     *
     * Dùng nội bộ trước tất cả các thao tác cập nhật.
     * SELECT * để lấy đủ dữ liệu (bao gồm full_name cho logging).
     *
     * @param int $id  ID nhân viên cần tìm
     * @return array   Bản ghi nhân viên (bao gồm password_hash — chỉ dùng nội bộ)
     */
    private function _findOrFail(int $id): array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM employees WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$id]);
        $emp = $stmt->fetch();
        if (!$emp) error('Không tìm thấy nhân viên.', 404);
        return $emp;
    }

    /**
     * Ghi nhật ký hoạt động cho module nhân viên.
     *
     * Tương tự CustomerController::log() nhưng dùng cho bảng employees.
     *
     * @param int    $employeeId  ID admin thực hiện hành động
     * @param string $action      Mã hành động (create_employee, lock_employee...)
     * @param string $table       'employees'
     * @param int    $recordId    ID nhân viên bị tác động
     * @param string $desc        Mô tả chi tiết
     */
    private function log(int $employeeId, string $action, string $table, int $recordId, string $desc): void {
        $this->pdo->prepare(
            "INSERT INTO activity_logs (employee_id, action, table_name, record_id, description) VALUES (?,?,?,?,?)"
        )->execute([$employeeId, $action, $table, $recordId, $desc]);
    }
}
