<?php

class CustomerGroupController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // GET /api/customer-groups
    public function index(): void {
        requireAuth();
        $stmt = $this->pdo->query(
            "SELECT g.*, COUNT(c.id) as customer_count
             FROM customer_groups g
             LEFT JOIN customers c ON c.customer_group_id = g.id AND c.deleted_at IS NULL
             GROUP BY g.id ORDER BY g.name ASC"
        );
        success($stmt->fetchAll());
    }

    // GET /api/customer-groups/{id}
    public function show(int $id): void {
        requireAuth();
        $g = $this->_findOrFail($id);
        // Lấy danh sách KH thuộc nhóm
        $stmt = $this->pdo->prepare(
            "SELECT c.id, c.full_name, c.phone, c.email, c.status, ct.name as tier_name
             FROM customers c
             LEFT JOIN customer_tiers ct ON c.tier_id = ct.id
             WHERE c.customer_group_id = ? AND c.deleted_at IS NULL
             ORDER BY c.full_name ASC"
        );
        $stmt->execute([$id]);
        $g['customers'] = $stmt->fetchAll();
        success($g);
    }

    // POST /api/customer-groups
    public function store(): void {
        $actor = requireAuth();
        $body  = getJsonBody();
        if (empty($body['name'])) error('Tên nhóm không được để trống.', 422);

        // Check duplicate name
        $chk = $this->pdo->prepare("SELECT id FROM customer_groups WHERE name = ?");
        $chk->execute([$body['name']]);
        if ($chk->fetch()) error('Tên nhóm đã tồn tại.', 409);

        $this->pdo->prepare(
            "INSERT INTO customer_groups (name, description, color, created_by) VALUES (?,?,?,?)"
        )->execute([
            $body['name'],
            $body['description'] ?? null,
            $body['color'] ?? '#2563eb',
            $actor['id'],
        ]);
        $id = $this->pdo->lastInsertId();
        $this->show($id);
    }

    // PUT /api/customer-groups/{id}
    public function update(int $id): void {
        requireAuth();
        $this->_findOrFail($id);
        $body = getJsonBody();
        $fields = []; $params = [];
        if (isset($body['name']))        { $fields[] = "name = ?";        $params[] = $body['name']; }
        if (isset($body['description'])) { $fields[] = "description = ?"; $params[] = $body['description']; }
        if (isset($body['color']))       { $fields[] = "color = ?";       $params[] = $body['color']; }
        if (empty($fields)) error('Không có gì để cập nhật.', 422);
        $params[] = $id;
        $this->pdo->prepare("UPDATE customer_groups SET " . implode(', ', $fields) . " WHERE id = ?")
                  ->execute($params);
        $this->show($id);
    }

    // DELETE /api/customer-groups/{id}
    public function destroy(int $id): void {
        requireAdminOrManager();
        $this->_findOrFail($id);
        // Bỏ nhóm khỏi các KH thuộc nhóm này
        $this->pdo->prepare("UPDATE customers SET customer_group_id = NULL WHERE customer_group_id = ?")
                  ->execute([$id]);
        $this->pdo->prepare("DELETE FROM customer_groups WHERE id = ?")->execute([$id]);
        success(null, 'Đã xóa nhóm khách hàng.');
    }

    // POST /api/customer-groups/{id}/add-customer
    public function addCustomer(int $groupId): void {
        requireAuth();
        $this->_findOrFail($groupId);
        $body = getJsonBody();
        if (empty($body['customer_id'])) error('Thiếu customer_id.', 422);
        $this->pdo->prepare("UPDATE customers SET customer_group_id = ? WHERE id = ? AND deleted_at IS NULL")
                  ->execute([$groupId, $body['customer_id']]);
        success(null, 'Đã thêm khách hàng vào nhóm.');
    }

    // POST /api/customer-groups/{id}/remove-customer
    public function removeCustomer(int $groupId): void {
        requireAuth();
        $body = getJsonBody();
        if (empty($body['customer_id'])) error('Thiếu customer_id.', 422);
        $this->pdo->prepare("UPDATE customers SET customer_group_id = NULL WHERE id = ? AND customer_group_id = ?")
                  ->execute([$body['customer_id'], $groupId]);
        success(null, 'Đã xóa khách hàng khỏi nhóm.');
    }

    private function _findOrFail(int $id): array {
        $stmt = $this->pdo->prepare("SELECT * FROM customer_groups WHERE id = ?");
        $stmt->execute([$id]);
        $g = $stmt->fetch();
        if (!$g) error('Không tìm thấy nhóm khách hàng.', 404);
        return $g;
    }
}
