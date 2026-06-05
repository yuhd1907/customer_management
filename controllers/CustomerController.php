<?php
/**
 * ================================================================
 * controllers/CustomerController.php — Quản lý Khách hàng
 * ================================================================
 * Xử lý toàn bộ nghiệp vụ CRUD cho bảng customers:
 *   GET    /api/customers           — Danh sách (filter + phân trang)
 *   GET    /api/customers/{id}      — Chi tiết 1 khách hàng
 *   POST   /api/customers           — Tạo mới
 *   PUT    /api/customers/{id}      — Cập nhật thông tin
 *   DELETE /api/customers/{id}      — Xóa mềm (chỉ Admin)
 *   PATCH  /api/customers/{id}/tier   — Cập nhật hạng KH
 *   PATCH  /api/customers/{id}/assign — Gán nhân viên phụ trách
 *   GET    /api/customers/export    — Xuất danh sách ra CSV
 *   POST   /api/customers/import    — Nhập hàng loạt từ CSV
 */
class CustomerController {
    private PDO $pdo;

    /** @param PDO $pdo Kết nối database được inject từ index.php */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * GET /api/customers — Lấy danh sách khách hàng.
     *
     * Hỗ trợ lọc theo nhiều tiêu chí:
     *   ?search=Lan              — Tìm theo tên, SĐT hoặc email (LIKE)
     *   ?status=active           — Lọc theo trạng thái (active/inactive/blocked)
     *   ?tier_id=2               — Lọc theo hạng KH
     *   ?assigned_employee_id=3  — Lọc theo nhân viên phụ trách
     *   ?customer_group_id=1     — Lọc theo nhóm KH
     *   ?source=facebook         — Lọc theo nguồn KH
     *   ?page=2&per_page=10      — Phân trang
     *
     * Kết quả JOIN thêm: tên hạng (tier_name), phần trăm giảm giá, tên NV phụ trách.
     */
    public function index(): void {
        $actor = requireAuth();          // Mọi nhân viên đều có quyền xem
        $p     = getPaginationParams();  // Lấy page, per_page, offset từ URL

        // Đọc các tham số lọc từ query string
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $tier   = $_GET['tier_id'] ?? '';
        $emp    = $_GET['assigned_employee_id'] ?? '';
        $group  = $_GET['customer_group_id'] ?? '';
        $source = $_GET['source'] ?? '';

        // Xây dựng mệnh đề WHERE động — bắt đầu với điều kiện cơ bản
        $where  = ['c.deleted_at IS NULL']; // Luôn lọc bỏ bản ghi đã xóa mềm
        $params = [];

        // Tìm kiếm text: OR trên 3 cột, dùng LIKE để tìm gần đúng
        if ($search) {
            $where[] = "(c.full_name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
            $s = "%$search%";
            array_push($params, $s, $s, $s); // 3 tham số cho 3 dấu ?
        }

        // Chỉ chấp nhận các giá trị status hợp lệ (whitelist) để tránh SQL injection
        if ($status && in_array($status, ['active', 'inactive', 'blocked'])) {
            $where[] = "c.status = ?"; $params[] = $status;
        }
        if ($tier)   { $where[] = "c.tier_id = ?";              $params[] = $tier; }
        if ($emp)    { $where[] = "c.assigned_employee_id = ?"; $params[] = $emp; }
        if ($group)  { $where[] = "c.customer_group_id = ?";    $params[] = $group; }
        if ($source) { $where[] = "c.source = ?";               $params[] = $source; }

        // Ghép các điều kiện thành chuỗi WHERE bằng AND
        $sql = "WHERE " . implode(" AND ", $where);

        // Đếm tổng bản ghi (để tính số trang) — chạy TRƯỚC khi thêm LIMIT
        $total = $this->pdo->prepare("SELECT COUNT(*) FROM customers c $sql");
        $total->execute($params);
        $total = (int) $total->fetchColumn();

        // Lấy dữ liệu thực sự với JOIN để bổ sung tên hạng và tên NV phụ trách
        // LEFT JOIN: vẫn trả về KH dù không có hạng hoặc NV phụ trách
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

        // Trả về response có phân trang
        paginated($customers, $total, $p['page'], $p['per_page']);
    }

    /**
     * GET /api/customers/{id} — Lấy chi tiết một khách hàng.
     *
     * JOIN thêm tên hạng và tên nhân viên phụ trách.
     * Dùng _findOrFail() để kiểm tra KH tồn tại trước.
     *
     * @param int $id  ID của khách hàng
     */
    public function show(int $id): void {
        $actor    = requireAuth();
        $customer = $this->_findOrFail($id, $actor); // Ném 404 nếu không tìm thấy

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

    /**
     * POST /api/customers — Tạo khách hàng mới.
     *
     * Validate:
     *  - full_name và phone là bắt buộc
     *  - phone phải là duy nhất trong hệ thống
     *  - gender phải là male/female/other (mặc định: other)
     *
     * Nếu không chỉ định assigned_employee_id, tự động gán cho người tạo.
     * Ghi activity_log sau khi tạo thành công.
     */
    public function store(): void {
        $actor = requireAuth();
        $body  = getJsonBody();

        // Validate các trường bắt buộc
        if (empty($body['full_name'])) error('Thiếu họ tên khách hàng.', 422);
        if (empty($body['phone']))     error('Thiếu số điện thoại.', 422);

        // Kiểm tra số điện thoại đã tồn tại (không tính bản ghi đã xóa mềm)
        $chk = $this->pdo->prepare("SELECT id FROM customers WHERE phone = ? AND deleted_at IS NULL");
        $chk->execute([$body['phone']]);
        if ($chk->fetch()) error('Số điện thoại đã tồn tại.', 409); // 409 Conflict

        // Validate và chuẩn hóa giá trị gender
        $gender = $body['gender'] ?? 'other';
        if (!in_array($gender, ['male', 'female', 'other'])) $gender = 'other';

        // Nếu không chỉ định NV phụ trách, gán cho người đang đăng nhập
        $assignedId = $body['assigned_employee_id'] ?? null;
        if (!$assignedId) {
            $assignedId = $actor['id'];
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO customers
             (tier_id, assigned_employee_id, customer_group_id, full_name, phone, email,
              gender, date_of_birth, address, source, note, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $body['tier_id']           ?? null,      // Hạng KH (tùy chọn)
            $assignedId,                              // NV phụ trách
            $body['customer_group_id'] ?? null,      // Nhóm KH (tùy chọn)
            $body['full_name'],
            $body['phone'],
            $body['email']             ?? null,
            $gender,
            $body['date_of_birth']     ?? null,
            $body['address']           ?? null,
            $body['source']            ?? 'other',   // Nguồn KH (mặc định: other)
            $body['note']              ?? null,
            $actor['id'],                            // Người tạo (audit trail)
        ]);

        $id = $this->pdo->lastInsertId();

        // Ghi nhật ký hoạt động để theo dõi ai đã tạo KH này
        $this->log($actor['id'], 'create_customer', 'customers', $id, "Tạo khách hàng: {$body['full_name']}");

        // Trả về chi tiết KH vừa tạo (dùng lại hàm show)
        $this->show($id);
    }

    /**
     * PUT /api/customers/{id} — Cập nhật thông tin khách hàng.
     *
     * Chỉ cập nhật các trường được gửi lên (dynamic UPDATE).
     * Tự động ghi updated_by = người đang thực hiện.
     *
     * @param int $id  ID của khách hàng cần cập nhật
     */
    public function update(int $id): void {
        $actor = requireAuth();
        $this->_findOrFail($id, $actor); // Đảm bảo KH tồn tại và có quyền truy cập
        $body  = getJsonBody();

        $fields = []; $params = [];

        // Chỉ cập nhật các trường được khai báo trong $map
        // Dùng isset() thay vì empty() để hỗ trợ cập nhật giá trị rỗng/null
        $map = ['full_name', 'phone', 'email', 'gender', 'date_of_birth',
                'address', 'note', 'status', 'source', 'customer_group_id'];
        foreach ($map as $f) {
            if (isset($body[$f])) {
                $fields[] = "$f = ?";
                $params[] = $body[$f];
            }
        }
        if (empty($fields)) error('Không có trường nào để cập nhật.', 422);

        // Ghi audit: ai đã cập nhật cuối cùng
        $fields[] = "updated_by = ?"; $params[] = $actor['id'];
        $params[] = $id;

        // Xây dựng câu UPDATE động — chỉ update các cột cần thiết
        $this->pdo->prepare("UPDATE customers SET " . implode(', ', $fields) . " WHERE id = ?")
                  ->execute($params);

        $this->log($actor['id'], 'update_customer', 'customers', $id, "Cập nhật khách hàng #$id");
        $this->show($id); // Trả về dữ liệu mới nhất
    }

    /**
     * DELETE /api/customers/{id} — Xóa mềm khách hàng (chỉ Admin).
     *
     * KHÔNG xóa dữ liệu thật khỏi DB. Chỉ ghi thời gian vào deleted_at.
     * Lý do: giữ lại lịch sử đơn hàng, không bị lỗi khóa ngoại.
     *
     * @param int $id  ID của khách hàng cần xóa
     */
    public function destroy(int $id): void {
        $actor = requireAdmin(); // Chỉ Admin mới được xóa
        $cust  = $this->_findOrFail($id, $actor);

        // Soft delete: đánh dấu thời gian xóa thay vì xóa dòng thật
        $this->pdo->prepare("UPDATE customers SET deleted_at = NOW(), updated_by = ? WHERE id = ?")
                  ->execute([$actor['id'], $id]);

        $this->log($actor['id'], 'delete_customer', 'customers', $id, "Xóa mềm khách hàng: {$cust['full_name']}");
        success(null, 'Đã xóa khách hàng thành công.');
    }

    /**
     * PATCH /api/customers/{id}/tier — Cập nhật hạng khách hàng.
     *
     * Chỉ Admin và Manager được phép thay đổi hạng.
     * Kiểm tra tier_id phải tồn tại trong bảng customer_tiers.
     * Cho phép gán null (gỡ hạng khỏi KH).
     *
     * @param int $id  ID của khách hàng
     */
    public function updateTier(int $id): void {
        $actor = requireAdminOrManager(); // Admin hoặc Manager
        $this->_findOrFail($id, $actor);
        $body  = getJsonBody();

        if (!isset($body['tier_id'])) error('Thiếu tier_id.', 422);

        // Nếu không phải null, kiểm tra tier_id có hợp lệ không
        if ($body['tier_id'] !== null) {
            $t = $this->pdo->prepare("SELECT id FROM customer_tiers WHERE id = ?");
            $t->execute([$body['tier_id']]);
            if (!$t->fetch()) error('Hạng khách hàng không tồn tại.', 404);
        }

        $this->pdo->prepare("UPDATE customers SET tier_id = ?, updated_by = ? WHERE id = ?")
                  ->execute([$body['tier_id'], $actor['id'], $id]);

        $this->log($actor['id'], 'update_tier', 'customers', $id,
                   "Cập nhật hạng khách hàng #$id → tier_id={$body['tier_id']}");
        $this->show($id);
    }

    /**
     * PATCH /api/customers/{id}/assign — Gán nhân viên phụ trách.
     *
     * Chỉ Admin và Manager được phép phân công.
     * Kiểm tra employee_id phải tồn tại và chưa bị xóa.
     *
     * @param int $id  ID của khách hàng
     */
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

        $this->log($actor['id'], 'assign_customer', 'customers', $id,
                   "Phân công nhân viên #{$body['assigned_employee_id']} cho khách hàng #$id");
        $this->show($id);
    }

    /**
     * GET /api/customers/export — Xuất toàn bộ danh sách KH ra file CSV.
     *
     * CSV được stream trực tiếp ra trình duyệt (không lưu file trên server).
     * BOM (EF BB BF) được thêm vào đầu file để Excel đọc đúng UTF-8.
     * Tên file tự động gồm timestamp: customers_20240514_153000.csv
     */
    public function export(): void {
        requireAuth();
        // Lấy toàn bộ KH (không phân trang) kèm tên hạng, nhóm, NV phụ trách
        $stmt = $this->pdo->query(
            "SELECT c.full_name, c.phone, c.email, c.gender, c.date_of_birth, c.address,
                    c.status, c.source, c.note,
                    ct.name as tier_name, cg.name as group_name,
                    e.full_name as assigned_employee
             FROM customers c
             LEFT JOIN customer_tiers ct  ON c.tier_id = ct.id
             LEFT JOIN customer_groups cg ON c.customer_group_id = cg.id
             LEFT JOIN employees e        ON c.assigned_employee_id = e.id
             WHERE c.deleted_at IS NULL
             ORDER BY c.created_at DESC"
        );
        $rows = $stmt->fetchAll();

        // Ghi đè Content-Type để trình duyệt tải file thay vì hiển thị JSON
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="customers_' . date('Ymd_His') . '.csv"');

        $out = fopen('php://output', 'w'); // Stream thẳng ra output buffer

        // BOM giúp Excel nhận biết file là UTF-8 (không bị lỗi font tiếng Việt)
        fputs($out, "\xEF\xBB\xBF");

        // Dòng tiêu đề CSV
        fputcsv($out, ['Họ tên', 'SĐT', 'Email', 'Giới tính', 'Ngày sinh', 'Địa chỉ',
                       'Trạng thái', 'Nguồn KH', 'Ghi chú', 'Hạng KH', 'Nhóm KH', 'NV phụ trách']);

        // Map code sang nhãn tiếng Việt
        $genderMap = ['male' => 'Nam', 'female' => 'Nữ', 'other' => 'Khác'];
        $statusMap = ['active' => 'Hoạt động', 'inactive' => 'Không HĐ', 'blocked' => 'Bị chặn'];

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['full_name'],
                $r['phone'],
                $r['email']    ?? '',
                $genderMap[$r['gender']] ?? $r['gender'],
                $r['date_of_birth'] ?? '',
                $r['address']  ?? '',
                $statusMap[$r['status']] ?? $r['status'],
                $r['source']   ?? '',
                $r['note']     ?? '',
                $r['tier_name']        ?? '',
                $r['group_name']       ?? '',
                $r['assigned_employee'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    /**
     * POST /api/customers/import — Nhập hàng loạt từ file CSV.
     *
     * Chỉ Admin và Manager được upload.
     * Định dạng CSV yêu cầu (header):
     *   full_name, phone, email, gender(Nam/Nữ/Khác), date_of_birth, address, status, source, note
     *
     * Xử lý từng dòng:
     *  - Bỏ qua dòng thiếu full_name hoặc phone
     *  - Bỏ qua nếu SĐT đã tồn tại trong hệ thống
     *  - Ghi nhận lỗi nhưng tiếp tục xử lý các dòng còn lại (không dừng giữa chừng)
     *
     * Trả về: số dòng thêm thành công, số dòng bỏ qua, danh sách lỗi (tối đa 10)
     */
    public function import(): void {
        $actor = requireAdminOrManager();

        if (empty($_FILES['file'])) error('Không có file được upload.', 422);

        $file   = $_FILES['file']['tmp_name']; // Đường dẫn file tạm do PHP tạo
        $handle = fopen($file, 'r');
        if (!$handle) error('Không thể đọc file.', 500);

        // Bỏ qua dòng header của CSV
        $header = fgetcsv($handle);
        // Xử lý BOM ở đầu file (một số editor tự thêm BOM)
        if ($header && isset($header[0])) {
            $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
        }

        $inserted = 0; $skipped = 0; $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) continue; // Bỏ qua dòng không đủ cột

            // Map vị trí cột CSV → biến (theo thứ tự header)
            $fullName = trim($row[0] ?? '');
            $phone    = trim($row[1] ?? '');
            $email    = trim($row[2] ?? '') ?: null;
            // Chuyển đổi giới tính từ tiếng Việt sang code database
            $gender   = ['Nam' => 'male', 'Nữ' => 'female', 'Khác' => 'other'][trim($row[3] ?? '')] ?? 'other';
            $dob      = trim($row[4] ?? '') ?: null;
            $address  = trim($row[5] ?? '') ?: null;
            $note     = trim($row[8] ?? '') ?: null;
            $source   = trim($row[7] ?? '') ?: 'other';

            // Bỏ qua dòng thiếu thông tin bắt buộc
            if (!$fullName || !$phone) { $skipped++; continue; }

            // Kiểm tra trùng SĐT trước khi INSERT
            $chk = $this->pdo->prepare("SELECT id FROM customers WHERE phone = ? AND deleted_at IS NULL");
            $chk->execute([$phone]);
            if ($chk->fetch()) {
                $skipped++;
                $errors[] = "Trùng SĐT: $phone";
                continue;
            }

            try {
                $this->pdo->prepare(
                    "INSERT INTO customers
                     (full_name, phone, email, gender, date_of_birth, address, note, source, created_by)
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
            'errors'   => array_slice($errors, 0, 10), // Giới hạn tối đa 10 lỗi trả về
        ], "Import hoàn tất: thêm $inserted, bỏ qua $skipped.");
    }

    /**
     * Tìm khách hàng theo ID hoặc ném lỗi 404.
     *
     * Hàm private dùng nội bộ, gọi trước mọi thao tác cần xác nhận
     * KH tồn tại (show, update, destroy, updateTier, assign).
     *
     * @param int   $id    ID khách hàng
     * @param array $actor Thông tin người dùng hiện tại (không dùng hiện tại)
     * @return array       Bản ghi khách hàng
     */
    private function _findOrFail(int $id, array $actor): array {
        $stmt = $this->pdo->prepare("SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $cust = $stmt->fetch();
        if (!$cust) error('Không tìm thấy khách hàng.', 404);
        return $cust;
    }

    /**
     * Ghi nhật ký hoạt động vào bảng activity_logs.
     *
     * Mọi thao tác quan trọng (tạo, sửa, xóa) đều được ghi lại để:
     *  - Theo dõi ai đã thực hiện thao tác gì
     *  - Hỗ trợ kiểm tra, debug sau sự cố
     *
     * @param int    $employeeId  ID nhân viên thực hiện
     * @param string $action      Mã hành động (VD: 'create_customer')
     * @param string $table       Tên bảng liên quan ('customers')
     * @param int    $recordId    ID bản ghi bị tác động
     * @param string $desc        Mô tả chi tiết hành động
     */
    private function log(int $employeeId, string $action, string $table, int $recordId, string $desc): void {
        $this->pdo->prepare(
            "INSERT INTO activity_logs (employee_id, action, table_name, record_id, description) VALUES (?,?,?,?,?)"
        )->execute([$employeeId, $action, $table, $recordId, $desc]);
    }
}
