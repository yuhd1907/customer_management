<?php

class CustomerController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // GET /api/customers
    public function index(): void {
        $actor = requireAuth();
        $p     = getPaginationParams();

        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $tier   = $_GET['tier_id'] ?? '';
        $emp    = $_GET['assigned_employee_id'] ?? '';
        $group  = $_GET['customer_group_id'] ?? '';
        $source = $_GET['source'] ?? '';

        $where  = ['c.deleted_at IS NULL'];
        $params = [];

        // All users (including staff) can now see all customers.
        // The previous restriction assigning staff only to their own customers was removed per request.

        if ($search) {
            $where[] = "(c.full_name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
            $s = "%$search%"; array_push($params, $s, $s, $s);
        }
        if ($status && in_array($status, ['active','inactive','blocked'])) {
            $where[] = "c.status = ?"; $params[] = $status;
        }
        if ($tier)   { $where[] = "c.tier_id = ?";              $params[] = $tier; }
        if ($emp)    { $where[] = "c.assigned_employee_id = ?"; $params[] = $emp; }
        if ($group)  { $where[] = "c.customer_group_id = ?";    $params[] = $group; }
        if ($source) { $where[] = "c.source = ?";               $params[] = $source; }

        $sql = "WHERE " . implode(" AND ", $where);

        $total = $this->pdo->prepare("SELECT COUNT(*) FROM customers c $sql");
        $total->execute($params);
        $total = (int) $total->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT c.*, ct.name as tier_name, ct.discount_percent,
                    e.full_name as assigned_employee_name
             FROM customers c
             LEFT JOIN customer_tiers ct ON c.tier_id = ct.id
             LEFT JOIN employees e ON c.assigned_employee_id = e.id
             $sql ORDER BY c.created_at DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}"
        );
        $stmt->execute($params);
        $customers = $stmt->fetchAll();

        paginated($customers, $total, $p['page'], $p['per_page']);
    }

    // GET /api/customers/{id}
    public function show(int $id): void {
        $actor    = requireAuth();
        $customer = $this->_findOrFail($id, $actor);

        $stmt = $this->pdo->prepare(
            "SELECT c.*, ct.name as tier_name, ct.discount_percent,
                    e.full_name as assigned_employee_name
             FROM customers c
             LEFT JOIN customer_tiers ct ON c.tier_id = ct.id
             LEFT JOIN employees e ON c.assigned_employee_id = e.id
             WHERE c.id = ?"
        );
        $stmt->execute([$id]);
        success($stmt->fetch());
    }

    // POST /api/customers
    public function store(): void {
        $actor = requireAuth();
        $body  = getJsonBody();

        if (empty($body['full_name'])) error('Thiếu họ tên khách hàng.', 422);
        if (empty($body['phone']))     error('Thiếu số điện thoại.', 422);

        // Check unique phone
        $chk = $this->pdo->prepare("SELECT id FROM customers WHERE phone = ? AND deleted_at IS NULL");
        $chk->execute([$body['phone']]);
        if ($chk->fetch()) error('Số điện thoại đã tồn tại.', 409);

        $gender = $body['gender'] ?? 'other';
        if (!in_array($gender, ['male','female','other'])) $gender = 'other';

        // Auto-assign to self when creating a customer without specifying assignee
        $assignedId = $body['assigned_employee_id'] ?? null;
        if (!$assignedId) {
            $assignedId = $actor['id'];
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO customers (tier_id, assigned_employee_id, customer_group_id, full_name, phone, email, gender, date_of_birth, address, source, note, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $body['tier_id'] ?? null,
            $assignedId,
            $body['customer_group_id'] ?? null,
            $body['full_name'],
            $body['phone'],
            $body['email'] ?? null,
            $gender,
            $body['date_of_birth'] ?? null,
            $body['address'] ?? null,
            $body['source'] ?? 'other',
            $body['note'] ?? null,
            $actor['id'],
        ]);
        $id = $this->pdo->lastInsertId();
        $this->log($actor['id'], 'create_customer', 'customers', $id, "Tạo khách hàng: {$body['full_name']}");
        $this->show($id);
    }

    // PUT /api/customers/{id}
    public function update(int $id): void {
        $actor = requireAuth();
        $this->_findOrFail($id, $actor);
        $body  = getJsonBody();

        $fields = []; $params = [];

        $map = ['full_name','phone','email','gender','date_of_birth','address','note','status','source','customer_group_id'];
        foreach ($map as $f) {
            if (isset($body[$f])) { $fields[] = "$f = ?"; $params[] = $body[$f]; }
        }
        if (empty($fields)) error('Không có trường nào để cập nhật.', 422);

        $fields[] = "updated_by = ?"; $params[] = $actor['id'];
        $params[] = $id;

        $this->pdo->prepare("UPDATE customers SET " . implode(', ', $fields) . " WHERE id = ?")
                  ->execute($params);

        $this->log($actor['id'], 'update_customer', 'customers', $id, "Cập nhật khách hàng #$id");
        $this->show($id);
    }

    // DELETE /api/customers/{id}
    public function destroy(int $id): void {
        $actor = requireAdmin();
        $cust  = $this->_findOrFail($id, $actor);

        $this->pdo->prepare("UPDATE customers SET deleted_at = NOW(), updated_by = ? WHERE id = ?")
                  ->execute([$actor['id'], $id]);

        $this->log($actor['id'], 'delete_customer', 'customers', $id, "Xóa mềm khách hàng: {$cust['full_name']}");
        success(null, 'Đã xóa khách hàng thành công.');
    }

    // PATCH /api/customers/{id}/tier
    public function updateTier(int $id): void {
        $actor = requireAdminOrManager();
        $this->_findOrFail($id, $actor);
        $body  = getJsonBody();

        if (!isset($body['tier_id'])) error('Thiếu tier_id.', 422);

        if ($body['tier_id'] !== null) {
            $t = $this->pdo->prepare("SELECT id FROM customer_tiers WHERE id = ?");
            $t->execute([$body['tier_id']]);
            if (!$t->fetch()) error('Hạng khách hàng không tồn tại.', 404);
        }

        $this->pdo->prepare("UPDATE customers SET tier_id = ?, updated_by = ? WHERE id = ?")
                  ->execute([$body['tier_id'], $actor['id'], $id]);

        $this->log($actor['id'], 'update_tier', 'customers', $id, "Cập nhật hạng khách hàng #$id → tier_id={$body['tier_id']}");
        $this->show($id);
    }

    // PATCH /api/customers/{id}/assign
    public function assign(int $id): void {
        $actor = requireAdminOrManager();
        $this->_findOrFail($id, $actor);
        $body  = getJsonBody();

        if (!isset($body['assigned_employee_id'])) error('Thiếu assigned_employee_id.', 422);

        if ($body['assigned_employee_id'] !== null) {
            $e = $this->pdo->prepare("SELECT id FROM employees WHERE id = ? AND deleted_at IS NULL");
            $e->execute([$body['assigned_employee_id']]);
            if (!$e->fetch()) error('Nhân viên không tồn tại.', 404);
        }

        $this->pdo->prepare("UPDATE customers SET assigned_employee_id = ?, updated_by = ? WHERE id = ?")
                  ->execute([$body['assigned_employee_id'], $actor['id'], $id]);

        $this->log($actor['id'], 'assign_customer', 'customers', $id, "Phân công nhân viên #{$body['assigned_employee_id']} cho khách hàng #$id");
        $this->show($id);
    }

    // GET /api/customers/export  — Xuất CSV
    public function export(): void {
        requireAuth();
        $stmt = $this->pdo->query(
            "SELECT c.full_name, c.phone, c.email, c.gender, c.date_of_birth, c.address,
                    c.status, c.source, c.note,
                    ct.name as tier_name, cg.name as group_name,
                    e.full_name as assigned_employee
             FROM customers c
             LEFT JOIN customer_tiers ct ON c.tier_id = ct.id
             LEFT JOIN customer_groups cg ON c.customer_group_id = cg.id
             LEFT JOIN employees e ON c.assigned_employee_id = e.id
             WHERE c.deleted_at IS NULL
             ORDER BY c.created_at DESC"
        );
        $rows = $stmt->fetchAll();

        // Output CSV directly (override JSON header)
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="customers_' . date('Ymd_His') . '.csv"');

        $out = fopen('php://output', 'w');
        // BOM for Excel UTF-8
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Họ tên','SĐT','Email','Giới tính','Ngày sinh','Địa chỉ','Trạng thái','Nguồn KH','Ghi chú','Hạng KH','Nhóm KH','NV phụ trách']);
        $genderMap = ['male'=>'Nam','female'=>'Nữ','other'=>'Khác'];
        $statusMap = ['active'=>'Hoạt động','inactive'=>'Không HĐ','blocked'=>'Bị chặn'];
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['full_name'], $r['phone'], $r['email'] ?? '',
                $genderMap[$r['gender']] ?? $r['gender'],
                $r['date_of_birth'] ?? '', $r['address'] ?? '',
                $statusMap[$r['status']] ?? $r['status'],
                $r['source'] ?? '', $r['note'] ?? '',
                $r['tier_name'] ?? '', $r['group_name'] ?? '',
                $r['assigned_employee'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    // POST /api/customers/import — Nhập CSV
    public function import(): void {
        $actor = requireAdminOrManager();
        if (empty($_FILES['file'])) error('Không có file được upload.', 422);

        $file = $_FILES['file']['tmp_name'];
        $handle = fopen($file, 'r');
        if (!$handle) error('Không thể đọc file.', 500);

        $header = fgetcsv($handle); // bỏ dòng tiêu đề
        // Skip BOM if present
        if ($header && isset($header[0])) {
            $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
        }

        $inserted = 0; $skipped = 0; $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) continue;
            $fullName = trim($row[0] ?? '');
            $phone    = trim($row[1] ?? '');
            $email    = trim($row[2] ?? '') ?: null;
            $gender   = ['Nam'=>'male','Nữ'=>'female','Khác'=>'other'][trim($row[3]??'')] ?? 'other';
            $dob      = trim($row[4] ?? '') ?: null;
            $address  = trim($row[5] ?? '') ?: null;
            $note     = trim($row[8] ?? '') ?: null;
            $source   = trim($row[7] ?? '') ?: 'other';

            if (!$fullName || !$phone) { $skipped++; continue; }

            // Check duplicate phone
            $chk = $this->pdo->prepare("SELECT id FROM customers WHERE phone = ? AND deleted_at IS NULL");
            $chk->execute([$phone]);
            if ($chk->fetch()) { $skipped++; $errors[] = "Trùng SĐT: $phone"; continue; }

            try {
                $this->pdo->prepare(
                    "INSERT INTO customers (full_name, phone, email, gender, date_of_birth, address, note, source, created_by)
                     VALUES (?,?,?,?,?,?,?,?,?)"
                )->execute([$fullName, $phone, $email, $gender, $dob, $address, $note, $source, $actor['id']]);
                $inserted++;
            } catch (\Exception $e) {
                $skipped++;
                $errors[] = "Lỗi dòng $fullName: " . $e->getMessage();
            }
        }
        fclose($handle);

        success([
            'inserted' => $inserted,
            'skipped'  => $skipped,
            'errors'   => array_slice($errors, 0, 10),
        ], "Import hoàn tất: thêm $inserted, bỏ qua $skipped.");
    }

    private function _findOrFail(int $id, array $actor): array {
        $stmt = $this->pdo->prepare("SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $cust = $stmt->fetch();
        if (!$cust) error('Không tìm thấy khách hàng.', 404);

        // Staff access restriction has been removed per request.
        // All roles can now access all customer details.

        return $cust;
    }

    private function log(int $employeeId, string $action, string $table, int $recordId, string $desc): void {
        $this->pdo->prepare("INSERT INTO activity_logs (employee_id, action, table_name, record_id, description) VALUES (?,?,?,?,?)")
                  ->execute([$employeeId, $action, $table, $recordId, $desc]);
    }
}

