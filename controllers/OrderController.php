<?php

class OrderController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function index(): void {
        $actor  = requireAuth();
        $p      = getPaginationParams();
        $status = $_GET['status'] ?? '';
        $custId = $_GET['customer_id'] ?? '';

        $where = []; $params = [];
        if ($actor['role'] === 'staff') { $where[] = "o.employee_id = ?"; $params[] = $actor['id']; }
        if ($status) { $where[] = "o.status = ?"; $params[] = $status; }
        if ($custId) { $where[] = "o.customer_id = ?"; $params[] = $custId; }

        $sql   = $where ? "WHERE " . implode(" AND ", $where) : "";
        $total = $this->pdo->prepare("SELECT COUNT(*) FROM orders o $sql");
        $total->execute($params);
        $total = (int) $total->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT o.*, c.full_name as customer_name, c.phone as customer_phone,
                    e.full_name as employee_name
             FROM orders o
             LEFT JOIN customers c ON o.customer_id = c.id
             LEFT JOIN employees e ON o.employee_id = e.id
             $sql ORDER BY o.created_at DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}"
        );
        $stmt->execute($params);
        paginated($stmt->fetchAll(), $total, $p['page'], $p['per_page']);
    }

    public function show(int $id): void {
        $actor = requireAuth();
        $order = $this->_findOrFail($id, $actor);
        $itemStmt = $this->pdo->prepare(
            "SELECT oi.*, p.name as product_name, p.sku FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?"
        );
        $itemStmt->execute([$id]);
        $order['items'] = $itemStmt->fetchAll();
        success($order);
    }

    public function store(): void {
        $actor = requireAuth();
        $body  = getJsonBody();
        if (empty($body['customer_id'])) error('Thiếu customer_id.', 422);
        if (empty($body['items']) || !is_array($body['items'])) error('Thiếu danh sách sản phẩm.', 422);

        $c = $this->pdo->prepare("SELECT id FROM customers WHERE id = ? AND deleted_at IS NULL");
        $c->execute([$body['customer_id']]);
        if (!$c->fetch()) error('Khách hàng không tồn tại.', 404);

        $orderCode = 'ORD-' . date('Y') . '-' . str_pad(
            $this->pdo->query("SELECT COUNT(*)+1 FROM orders")->fetchColumn(), 4, '0', STR_PAD_LEFT
        );

        $total = 0; $items = [];
        foreach ($body['items'] as $item) {
            if (empty($item['product_id']) || empty($item['quantity'])) error('Thông tin sản phẩm không hợp lệ.', 422);
            $p = $this->pdo->prepare("SELECT * FROM products WHERE id = ? AND deleted_at IS NULL AND status = 'active'");
            $p->execute([$item['product_id']]);
            $product = $p->fetch();
            if (!$product) error("Sản phẩm #{$item['product_id']} không tồn tại.", 404);
            if ($product['stock_quantity'] < $item['quantity']) error("Sản phẩm '{$product['name']}' không đủ tồn kho.", 400);
            $subtotal = $product['price'] * $item['quantity'];
            $total   += $subtotal;
            $items[]  = ['product_id' => $item['product_id'], 'quantity' => $item['quantity'], 'price' => $product['price'], 'subtotal' => $subtotal];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO orders (customer_id, employee_id, order_code, total_amount, note) VALUES (?,?,?,?,?)");
            $stmt->execute([$body['customer_id'], $actor['id'], $orderCode, $total, $body['note'] ?? null]);
            $orderId  = $this->pdo->lastInsertId();
            $itemStmt = $this->pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) VALUES (?,?,?,?,?)");
            foreach ($items as $item) {
                $itemStmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['price'], $item['subtotal']]);
                $this->pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?")->execute([$item['quantity'], $item['product_id']]);
            }
            $this->pdo->commit();
            $this->show($orderId);
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            error('Tạo đơn hàng thất bại: ' . $e->getMessage(), 500);
        }
    }

    // PUT /api/orders/{id}
    public function update(int $id): void {
        $actor = requireAdminOrManager();
        $this->_findOrFail($id, $actor);
        $body = getJsonBody();
        $fields = []; $params = [];
        if (isset($body['note']))        { $fields[] = "note = ?";        $params[] = $body['note']; }
        if (!empty($body['employee_id'])) { $fields[] = "employee_id = ?"; $params[] = $body['employee_id']; }
        if (empty($fields)) error('Không có trường nào để cập nhật.', 422);
        $params[] = $id;
        $this->pdo->prepare("UPDATE orders SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
        $this->show($id);
    }

    // DELETE /api/orders/{id} — hủy đơn
    public function destroy(int $id): void {
        $actor = requireAdminOrManager();
        $order = $this->_findOrFail($id, $actor);
        if (in_array($order['status'], ['completed', 'returned'])) {
            error('Không thể hủy đơn hàng đã hoàn thành hoặc đã trả.', 400);
        }
        $this->pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$id]);
        success(null, 'Đã hủy đơn hàng.');
    }

    // PATCH /api/orders/{id}/status
    public function updateStatus(int $id): void {
        $actor  = requireAuth();
        $this->_findOrFail($id, $actor);
        $body   = getJsonBody();
        $valid  = ['pending','confirmed','shipping','completed','cancelled','returned'];
        $status = $body['status'] ?? '';
        if (!in_array($status, $valid)) error('Trạng thái không hợp lệ.', 422);
        $this->pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $id]);
        $this->show($id);
    }

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
        if ($actor['role'] === 'staff' && $order['employee_id'] !== $actor['id']) {
            error('Bạn không có quyền truy cập đơn hàng này.', 403);
        }
        return $order;
    }
}
