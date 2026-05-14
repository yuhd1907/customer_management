<?php

class CustomerTierController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // GET /api/customer-tiers
    public function index(): void {
        requireAuth();
        $tiers = $this->pdo->query(
            "SELECT ct.*, (SELECT COUNT(*) FROM customers WHERE tier_id = ct.id AND deleted_at IS NULL) as customer_count
             FROM customer_tiers ct ORDER BY discount_percent ASC"
        )->fetchAll();
        success($tiers);
    }

    // GET /api/customer-tiers/{id}
    public function show(int $id): void {
        requireAuth();
        $tier = $this->_findOrFail($id);
        success($tier);
    }

    // POST /api/customer-tiers
    public function store(): void {
        requireAdmin();
        $body = getJsonBody();

        if (empty($body['name'])) error('Thiếu tên hạng.', 422);

        $chk = $this->pdo->prepare("SELECT id FROM customer_tiers WHERE name = ?");
        $chk->execute([$body['name']]);
        if ($chk->fetch()) error('Tên hạng đã tồn tại.', 409);

        $this->pdo->prepare("INSERT INTO customer_tiers (name, description, discount_percent) VALUES (?,?,?)")
                  ->execute([$body['name'], $body['description'] ?? null, $body['discount_percent'] ?? 0]);

        $id = $this->pdo->lastInsertId();
        $this->show($id);
    }

    // PUT /api/customer-tiers/{id}
    public function update(int $id): void {
        requireAdmin();
        $this->_findOrFail($id);
        $body = getJsonBody();

        $fields = []; $params = [];
        if (isset($body['name']))             { $fields[] = "name = ?";             $params[] = $body['name']; }
        if (isset($body['description']))      { $fields[] = "description = ?";      $params[] = $body['description']; }
        if (isset($body['discount_percent'])) { $fields[] = "discount_percent = ?"; $params[] = $body['discount_percent']; }

        if (empty($fields)) error('Không có trường nào để cập nhật.', 422);
        $params[] = $id;

        $this->pdo->prepare("UPDATE customer_tiers SET " . implode(', ', $fields) . " WHERE id = ?")
                  ->execute($params);
        $this->show($id);
    }

    // DELETE /api/customer-tiers/{id}
    public function destroy(int $id): void {
        requireAdmin();
        $this->_findOrFail($id);

        $used = $this->pdo->prepare("SELECT COUNT(*) FROM customers WHERE tier_id = ? AND deleted_at IS NULL");
        $used->execute([$id]);
        if ((int)$used->fetchColumn() > 0) error('Không thể xóa hạng đang có khách hàng sử dụng.', 400);

        $this->pdo->prepare("DELETE FROM customer_tiers WHERE id = ?")->execute([$id]);
        success(null, 'Đã xóa hạng khách hàng.');
    }

    private function _findOrFail(int $id): array {
        $stmt = $this->pdo->prepare("SELECT * FROM customer_tiers WHERE id = ?");
        $stmt->execute([$id]);
        $tier = $stmt->fetch();
        if (!$tier) error('Không tìm thấy hạng khách hàng.', 404);
        return $tier;
    }
}
