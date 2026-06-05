<?php
/**
 * ================================================================
 * controllers/OrderController.php — Quản lý Đơn hàng
 * ================================================================
 * Xử lý toàn bộ vòng đời của đơn hàng:
 *   GET   /api/orders              — Danh sách đơn hàng (filter + phân trang)
 *   GET   /api/orders/{id}         — Chi tiết đơn + danh sách sản phẩm
 *   POST  /api/orders              — Tạo đơn mới (dùng DB Transaction)
 *   PUT   /api/orders/{id}         — Sửa thông tin (Admin/Manager)
 *   DELETE /api/orders/{id}        — Hủy đơn (Admin/Manager)
 *   PATCH /api/orders/{id}/status  — Cập nhật trạng thái đơn
 *
 * Vòng đời trạng thái:
 *   pending → confirmed → shipping → completed
 *                                  → cancelled
 *                                  → returned
 */
class OrderController {
    private PDO $pdo;

    /** @param PDO $pdo Kết nối database được inject từ index.php */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * GET /api/orders — Danh sách đơn hàng có filter và phân trang.
     *
     * Phân quyền xem theo role:
     *   - staff:          chỉ thấy đơn do mình tạo (employee_id = chính mình)
     *   - admin/manager:  thấy tất cả đơn hàng
     *
     * Hỗ trợ filter:
     *   ?status=pending      — Lọc theo trạng thái
     *   ?customer_id=5       — Lọc theo khách hàng cụ thể
     */
    public function index(): void {
        $actor  = requireAuth();
        $p      = getPaginationParams();
        $status = $_GET['status']      ?? '';
        $custId = $_GET['customer_id'] ?? '';

        $where = []; $params = [];

        // Staff chỉ được xem đơn hàng mình tạo ra
        if ($actor['role'] === 'staff') {
            $where[] = "o.employee_id = ?";
            $params[] = $actor['id'];
        }
        if ($status) { $where[] = "o.status = ?";      $params[] = $status; }
        if ($custId) { $where[] = "o.customer_id = ?"; $params[] = $custId; }

        $sql   = $where ? "WHERE " . implode(" AND ", $where) : "";
        $total = $this->pdo->prepare("SELECT COUNT(*) FROM orders o $sql");
        $total->execute($params);
        $total = (int) $total->fetchColumn();

        // JOIN để lấy tên khách hàng và tên nhân viên tạo đơn
        $stmt = $this->pdo->prepare(
            "SELECT o.*, c.full_name as customer_name, c.phone as customer_phone,
                    e.full_name as employee_name
             FROM orders o
             LEFT JOIN customers c  ON o.customer_id  = c.id
             LEFT JOIN employees e  ON o.employee_id  = e.id
             $sql ORDER BY o.created_at DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}"
        );
        $stmt->execute($params);
        paginated($stmt->fetchAll(), $total, $p['page'], $p['per_page']);
    }

    /**
     * GET /api/orders/{id} — Chi tiết đơn hàng kèm danh sách sản phẩm.
     *
     * Trả về thông tin đơn hàng + mảng items[] gồm:
     *   - product_name, sku, quantity, price, subtotal
     *
     * @param int $id  ID đơn hàng
     */
    public function show(int $id): void {
        $actor = requireAuth();
        $order = $this->_findOrFail($id, $actor); // Kiểm tra quyền truy cập

        // Lấy danh sách sản phẩm trong đơn, JOIN để lấy tên và SKU sản phẩm
        $itemStmt = $this->pdo->prepare(
            "SELECT oi.*, p.name as product_name, p.sku
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.id
             WHERE oi.order_id = ?"
        );
        $itemStmt->execute([$id]);

        // Nhúng mảng items vào trong object đơn hàng để trả về 1 response gọn
        $order['items'] = $itemStmt->fetchAll();
        success($order);
    }

    /**
     * POST /api/orders — Tạo đơn hàng mới.
     *
     * Quy trình xử lý (dùng DB Transaction để đảm bảo toàn vẹn dữ liệu):
     *  1. Validate customer_id và danh sách items
     *  2. Với mỗi sản phẩm: kiểm tra tồn tại, còn active, đủ tồn kho
     *  3. Tính tổng tiền
     *  4. BEGIN TRANSACTION:
     *     - INSERT vào orders
     *     - INSERT từng dòng vào order_items
     *     - UPDATE stock_quantity của từng sản phẩm (giảm tồn kho)
     *  5. COMMIT nếu tất cả thành công, ROLLBACK nếu có lỗi
     *
     * Mã đơn hàng tự sinh: ORD-2024-0001, ORD-2024-0002, ...
     */
    public function store(): void {
        $actor = requireAuth();
        $body  = getJsonBody();

        // Validate đầu vào cơ bản
        if (empty($body['customer_id'])) error('Thiếu customer_id.', 422);
        if (empty($body['items']) || !is_array($body['items'])) error('Thiếu danh sách sản phẩm.', 422);

        // Xác nhận khách hàng tồn tại
        $c = $this->pdo->prepare("SELECT id FROM customers WHERE id = ? AND deleted_at IS NULL");
        $c->execute([$body['customer_id']]);
        if (!$c->fetch()) error('Khách hàng không tồn tại.', 404);

        // Tự sinh mã đơn hàng dạng: ORD-2024-0042
        // COUNT(*)+1 = số đơn hiện tại + 1 (chỉ gần đúng, không hoàn toàn sequence-safe)
        $orderCode = 'ORD-' . date('Y') . '-' . str_pad(
            $this->pdo->query("SELECT COUNT(*)+1 FROM orders")->fetchColumn(),
            4, '0', STR_PAD_LEFT
        );

        // Validate và tính giá cho từng sản phẩm TRƯỚC khi bắt đầu transaction
        $total = 0; $items = [];
        foreach ($body['items'] as $item) {
            if (empty($item['product_id']) || empty($item['quantity'])) {
                error('Thông tin sản phẩm không hợp lệ.', 422);
            }

            // Chỉ cho phép đặt sản phẩm còn active và chưa xóa
            $p = $this->pdo->prepare(
                "SELECT * FROM products WHERE id = ? AND deleted_at IS NULL AND status = 'active'"
            );
            $p->execute([$item['product_id']]);
            $product = $p->fetch();

            if (!$product) error("Sản phẩm #{$item['product_id']} không tồn tại.", 404);
            if ($product['stock_quantity'] < $item['quantity']) {
                error("Sản phẩm '{$product['name']}' không đủ tồn kho.", 400);
            }

            // Lấy giá từ DB, không tin giá từ client (tránh giả mạo giá)
            $subtotal = $product['price'] * $item['quantity'];
            $total   += $subtotal;
            $items[]  = [
                'product_id' => $item['product_id'],
                'quantity'   => $item['quantity'],
                'price'      => $product['price'], // Giá tại thời điểm đặt hàng
                'subtotal'   => $subtotal,
            ];
        }

        // Transaction: đảm bảo toàn bộ thao tác thành công hoặc rollback hết
        $this->pdo->beginTransaction();
        try {
            // Tạo đơn hàng chính
            $stmt = $this->pdo->prepare(
                "INSERT INTO orders (customer_id, employee_id, order_code, total_amount, note) VALUES (?,?,?,?,?)"
            );
            $stmt->execute([$body['customer_id'], $actor['id'], $orderCode, $total, $body['note'] ?? null]);
            $orderId = $this->pdo->lastInsertId();

            // Tạo từng dòng chi tiết sản phẩm
            $itemStmt = $this->pdo->prepare(
                "INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) VALUES (?,?,?,?,?)"
            );
            foreach ($items as $item) {
                $itemStmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['price'], $item['subtotal']]);

                // Trừ tồn kho ngay khi tạo đơn
                $this->pdo->prepare(
                    "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?"
                )->execute([$item['quantity'], $item['product_id']]);
            }

            $this->pdo->commit(); // Xác nhận toàn bộ thay đổi
            $this->show($orderId); // Trả về chi tiết đơn vừa tạo

        } catch (\Exception $e) {
            $this->pdo->rollBack(); // Hủy toàn bộ nếu có lỗi (tránh dữ liệu nửa vời)
            error('Tạo đơn hàng thất bại: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/orders/{id} — Cập nhật thông tin đơn hàng (Admin/Manager).
     *
     * Chỉ cho phép sửa: ghi chú (note) và nhân viên phụ trách (employee_id).
     * Không cho phép sửa sản phẩm hay tổng tiền sau khi tạo đơn.
     *
     * @param int $id  ID đơn hàng
     */
    public function update(int $id): void {
        $actor = requireAdminOrManager(); // Chỉ Admin/Manager được sửa
        $this->_findOrFail($id, $actor);
        $body = getJsonBody();

        $fields = []; $params = [];
        if (isset($body['note']))        { $fields[] = "note = ?";        $params[] = $body['note']; }
        if (!empty($body['employee_id'])) { $fields[] = "employee_id = ?"; $params[] = $body['employee_id']; }

        if (empty($fields)) error('Không có trường nào để cập nhật.', 422);

        $params[] = $id;
        $this->pdo->prepare("UPDATE orders SET " . implode(', ', $fields) . " WHERE id = ?")
                  ->execute($params);
        $this->show($id);
    }

    /**
     * DELETE /api/orders/{id} — Hủy đơn hàng (Admin/Manager).
     *
     * Không cho phép hủy đơn đã "completed" hoặc "returned"
     * vì đã ảnh hưởng đến tồn kho và doanh thu.
     *
     * Hủy = đổi status sang 'cancelled' (không xóa thật).
     *
     * @param int $id  ID đơn hàng
     */
    public function destroy(int $id): void {
        $actor = requireAdminOrManager();
        $order = $this->_findOrFail($id, $actor);

        // Bảo vệ tính toàn vẹn dữ liệu: không cho hủy đơn đã hoàn thành
        if (in_array($order['status'], ['completed', 'returned'])) {
            error('Không thể hủy đơn hàng đã hoàn thành hoặc đã trả.', 400);
        }

        $this->pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")
                  ->execute([$id]);
        success(null, 'Đã hủy đơn hàng.');
    }

    /**
     * PATCH /api/orders/{id}/status — Cập nhật trạng thái đơn hàng.
     *
     * Mọi nhân viên có quyền cập nhật trạng thái (nhưng _findOrFail kiểm tra
     * staff chỉ được cập nhật đơn của mình).
     *
     * Các trạng thái hợp lệ: pending, confirmed, shipping, completed, cancelled, returned
     *
     * @param int $id  ID đơn hàng
     */
    public function updateStatus(int $id): void {
        $actor  = requireAuth();
        $this->_findOrFail($id, $actor);
        $body   = getJsonBody();

        // Whitelist các trạng thái hợp lệ
        $valid  = ['pending', 'confirmed', 'shipping', 'completed', 'cancelled', 'returned'];
        $status = $body['status'] ?? '';

        if (!in_array($status, $valid)) error('Trạng thái không hợp lệ.', 422);

        $this->pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")
                  ->execute([$status, $id]);
        $this->show($id);
    }

    /**
     * Tìm đơn hàng theo ID hoặc ném lỗi 404.
     *
     * Kiểm tra thêm quyền của staff: staff chỉ được truy cập đơn hàng
     * do chính mình tạo (employee_id = actor.id).
     *
     * @param int   $id    ID đơn hàng
     * @param array $actor Thông tin người dùng hiện tại
     * @return array       Bản ghi đơn hàng kèm tên KH và tên NV
     */
    private function _findOrFail(int $id, array $actor): array {
        $stmt = $this->pdo->prepare(
            "SELECT o.*, c.full_name as customer_name, e.full_name as employee_name
             FROM orders o
             LEFT JOIN customers c ON o.customer_id = c.id
             LEFT JOIN employees e ON o.employee_id = e.id
             WHERE o.id = ?"
        );
        $stmt->execute([$id]);
        $order = $stmt->fetch();

        if (!$order) error('Không tìm thấy đơn hàng.', 404);

        // Staff chỉ được truy cập đơn của mình
        if ($actor['role'] === 'staff' && $order['employee_id'] !== $actor['id']) {
            error('Bạn không có quyền truy cập đơn hàng này.', 403);
        }

        return $order;
    }
}
