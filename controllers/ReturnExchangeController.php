<?php

class ReturnExchangeController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // GET /api/return-exchange-requests
    public function index(): void {
        $actor  = requireAuth();
        $p      = getPaginationParams();
        $status = $_GET['status'] ?? '';
        $type   = $_GET['request_type'] ?? '';
        $custId = $_GET['customer_id'] ?? '';

        $where = []; $params = [];
        if ($status) { $where[] = "r.status = ?"; $params[] = $status; }
        if ($type && in_array($type, ['return','exchange'])) { $where[] = "r.request_type = ?"; $params[] = $type; }
        if ($custId) { $where[] = "r.customer_id = ?"; $params[] = $custId; }

        $sql   = $where ? "WHERE " . implode(" AND ", $where) : "";
        $total = $this->pdo->prepare("SELECT COUNT(*) FROM return_exchange_requests r $sql");
        $total->execute($params);
        $total = (int) $total->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT r.*, c.full_name as customer_name, o.order_code, e.full_name as employee_name
             FROM return_exchange_requests r
             LEFT JOIN customers c ON r.customer_id = c.id
             LEFT JOIN orders o    ON r.order_id = o.id
             LEFT JOIN employees e ON r.employee_id = e.id
             $sql ORDER BY r.created_at DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}"
        );
        $stmt->execute($params);
        paginated($stmt->fetchAll(), $total, $p['page'], $p['per_page']);
    }

    // GET /api/return-exchange-requests/{id}
    public function show(int $id): void {
        requireAuth();
        $req = $this->_findOrFail($id);

        // Load items
        $items = $this->pdo->prepare(
            "SELECT rei.*, oi.quantity as ordered_quantity, p_old.name as old_product_name, p_new.name as new_product_name
             FROM return_exchange_items rei
             LEFT JOIN order_items oi ON rei.order_item_id = oi.id
             LEFT JOIN products p_old ON rei.old_product_id = p_old.id
             LEFT JOIN products p_new ON rei.new_product_id = p_new.id
             WHERE rei.request_id = ?"
        );
        $items->execute([$id]);
        $req['items'] = $items->fetchAll();

        success($req);
    }

    // POST /api/return-exchange-requests
    public function store(): void {
        $actor = requireAuth();
        $body  = getJsonBody();

        $required = ['customer_id', 'order_id', 'request_type', 'reason'];
        foreach ($required as $f) {
            if (empty($body[$f])) error("Thiếu trường bắt buộc: $f.", 422);
        }
        if (!in_array($body['request_type'], ['return','exchange'])) error('Loại yêu cầu không hợp lệ.', 422);

        $this->pdo->prepare(
            "INSERT INTO return_exchange_requests (customer_id, order_id, conversation_id, request_type, reason, employee_id, customer_note)
             VALUES (?,?,?,?,?,?,?)"
        )->execute([
            $body['customer_id'],
            $body['order_id'],
            $body['conversation_id'] ?? null,
            $body['request_type'],
            $body['reason'],
            $actor['id'],
            $body['customer_note'] ?? null,
        ]);
        $id = $this->pdo->lastInsertId();
        $this->show($id);
    }

    // PATCH /api/return-exchange-requests/{id}/approve
    public function approve(int $id): void {
        $actor = requireAuth();
        $this->_findOrFail($id);
        $body  = getJsonBody();
        $this->pdo->prepare(
            "UPDATE return_exchange_requests SET status = 'approved', admin_note = ?, employee_id = ? WHERE id = ?"
        )->execute([$body['admin_note'] ?? null, $actor['id'], $id]);
        $this->show($id);
    }

    // PATCH /api/return-exchange-requests/{id}/reject
    public function reject(int $id): void {
        $actor = requireAuth();
        $this->_findOrFail($id);
        $body  = getJsonBody();
        $this->pdo->prepare(
            "UPDATE return_exchange_requests SET status = 'rejected', admin_note = ?, employee_id = ? WHERE id = ?"
        )->execute([$body['admin_note'] ?? null, $actor['id'], $id]);
        $this->show($id);
    }

    // PATCH /api/return-exchange-requests/{id}/complete
    public function complete(int $id): void {
        requireAuth();
        $this->_findOrFail($id);
        $this->pdo->prepare("UPDATE return_exchange_requests SET status = 'completed' WHERE id = ?")->execute([$id]);
        $this->show($id);
    }

    private function _findOrFail(int $id): array {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, c.full_name as customer_name, o.order_code, e.full_name as employee_name
             FROM return_exchange_requests r
             LEFT JOIN customers c ON r.customer_id = c.id
             LEFT JOIN orders o    ON r.order_id = o.id
             LEFT JOIN employees e ON r.employee_id = e.id
             WHERE r.id = ?"
        );
        $stmt->execute([$id]);
        $req = $stmt->fetch();
        if (!$req) error('Không tìm thấy yêu cầu.', 404);
        return $req;
    }
}
