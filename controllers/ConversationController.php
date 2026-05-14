<?php

class ConversationController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // GET /api/conversations
    public function index(): void {
        $actor  = requireAuth();
        $p      = getPaginationParams();
        $status = $_GET['status'] ?? '';
        $custId = $_GET['customer_id'] ?? '';

        $where = []; $params = [];
        if ($actor['role'] === 'staff') {
            $where[] = "(c.employee_id = ? OR c.employee_id IS NULL)"; $params[] = $actor['id'];
        }
        if ($status) { $where[] = "c.status = ?"; $params[] = $status; }
        if ($custId) { $where[] = "c.customer_id = ?"; $params[] = $custId; }

        $sql   = $where ? "WHERE " . implode(" AND ", $where) : "";
        $total = $this->pdo->prepare("SELECT COUNT(*) FROM conversations c $sql");
        $total->execute($params);
        $total = (int) $total->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT c.*, cu.full_name as customer_name, cu.phone as customer_phone,
                    e.full_name as employee_name,
                    (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND is_read = 0) as unread_count
             FROM conversations c
             LEFT JOIN customers cu ON c.customer_id = cu.id
             LEFT JOIN employees e  ON c.employee_id = e.id
             $sql ORDER BY c.updated_at DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}"
        );
        $stmt->execute($params);
        paginated($stmt->fetchAll(), $total, $p['page'], $p['per_page']);
    }

    // GET /api/conversations/{id}
    public function show(int $id): void {
        requireAuth();
        success($this->_findOrFail($id));
    }

    // POST /api/conversations
    public function store(): void {
        $actor = requireAuth();
        $body  = getJsonBody();

        if (empty($body['customer_id'])) error('Thiếu customer_id.', 422);
        $type  = $body['type'] ?? 'general_support';
        if (!in_array($type, ['product_consulting','return_request','exchange_request','general_support'])) {
            error('Loại hội thoại không hợp lệ.', 422);
        }

        $this->pdo->prepare(
            "INSERT INTO conversations (customer_id, employee_id, title, type) VALUES (?,?,?,?)"
        )->execute([
            $body['customer_id'],
            $actor['id'],
            $body['title'] ?? null,
            $type,
        ]);
        $id = $this->pdo->lastInsertId();
        $this->show($id);
    }

    // PUT /api/conversations/{id}
    public function update(int $id): void {
        requireAuth();
        $this->_findOrFail($id);
        $body = getJsonBody();

        $fields = []; $params = [];
        if (isset($body['title'])) { $fields[] = "title = ?"; $params[] = $body['title']; }
        if (isset($body['type'])  && in_array($body['type'], ['product_consulting','return_request','exchange_request','general_support'])) {
            $fields[] = "type = ?"; $params[] = $body['type'];
        }
        if (empty($fields)) error('Không có trường nào để cập nhật.', 422);
        $fields[] = "updated_at = NOW()"; $params[] = $id;
        $this->pdo->prepare("UPDATE conversations SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
        $this->show($id);
    }

    // PATCH /api/conversations/{id}/close
    public function close(int $id): void {
        requireAuth();
        $this->_findOrFail($id);
        $this->pdo->prepare("UPDATE conversations SET status = 'closed', updated_at = NOW() WHERE id = ?")->execute([$id]);
        success(null, 'Đã đóng hội thoại.');
    }

    // PATCH /api/conversations/{id}/assign
    public function assign(int $id): void {
        requireAdminOrManager();
        $this->_findOrFail($id);
        $body = getJsonBody();
        if (empty($body['employee_id'])) error('Thiếu employee_id.', 422);
        $this->pdo->prepare("UPDATE conversations SET employee_id = ?, updated_at = NOW() WHERE id = ?")->execute([$body['employee_id'], $id]);
        $this->show($id);
    }

    private function _findOrFail(int $id): array {
        $stmt = $this->pdo->prepare(
            "SELECT c.*, cu.full_name as customer_name, e.full_name as employee_name
             FROM conversations c
             LEFT JOIN customers cu ON c.customer_id = cu.id
             LEFT JOIN employees e  ON c.employee_id = e.id
             WHERE c.id = ?"
        );
        $stmt->execute([$id]);
        $conv = $stmt->fetch();
        if (!$conv) error('Không tìm thấy hội thoại.', 404);
        return $conv;
    }
}
