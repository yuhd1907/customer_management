<?php

class CustomerTagController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // GET /api/customer-tags
    public function index(): void {
        requireAuth();
        $stmt = $this->pdo->query(
            "SELECT t.*, COUNT(l.customer_id) as usage_count
             FROM customer_tags t
             LEFT JOIN customer_tag_links l ON l.tag_id = t.id
             GROUP BY t.id ORDER BY t.name ASC"
        );
        success($stmt->fetchAll());
    }

    // POST /api/customer-tags
    public function store(): void {
        requireAdminOrManager();
        $body = getJsonBody();
        if (empty($body['name'])) error('Tên tag không được để trống.', 422);
        $chk = $this->pdo->prepare("SELECT id FROM customer_tags WHERE name = ?");
        $chk->execute([$body['name']]);
        if ($chk->fetch()) error('Tag đã tồn tại.', 409);
        $this->pdo->prepare("INSERT INTO customer_tags (name, color) VALUES (?,?)")
                  ->execute([$body['name'], $body['color'] ?? '#6d28d9']);
        $id   = $this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare("SELECT * FROM customer_tags WHERE id = ?");
        $stmt->execute([$id]);
        success($stmt->fetch(), 'Tạo tag thành công.', 201);
    }

    // PUT /api/customer-tags/{id}
    public function update(int $id): void {
        requireAdminOrManager();
        $body = getJsonBody();
        $fields = []; $params = [];
        if (isset($body['name']))  { $fields[] = "name = ?";  $params[] = $body['name']; }
        if (isset($body['color'])) { $fields[] = "color = ?"; $params[] = $body['color']; }
        if (empty($fields)) error('Không có gì để cập nhật.', 422);
        $params[] = $id;
        $this->pdo->prepare("UPDATE customer_tags SET " . implode(', ', $fields) . " WHERE id = ?")
                  ->execute($params);
        $stmt = $this->pdo->prepare("SELECT * FROM customer_tags WHERE id = ?");
        $stmt->execute([$id]);
        success($stmt->fetch());
    }

    // DELETE /api/customer-tags/{id}
    public function destroy(int $id): void {
        requireAdminOrManager();
        $this->pdo->prepare("DELETE FROM customer_tag_links WHERE tag_id = ?")->execute([$id]);
        $this->pdo->prepare("DELETE FROM customer_tags WHERE id = ?")->execute([$id]);
        success(null, 'Đã xóa tag.');
    }

    // GET /api/customers/{customerId}/tags
    public function getCustomerTags(int $customerId): void {
        requireAuth();
        $stmt = $this->pdo->prepare(
            "SELECT t.* FROM customer_tags t
             JOIN customer_tag_links l ON l.tag_id = t.id
             WHERE l.customer_id = ?
             ORDER BY t.name ASC"
        );
        $stmt->execute([$customerId]);
        success($stmt->fetchAll());
    }

    // POST /api/customers/{customerId}/tags
    public function setCustomerTags(int $customerId): void {
        requireAuth();
        $body   = getJsonBody();
        $tagIds = $body['tag_ids'] ?? [];

        // Xóa tags cũ
        $this->pdo->prepare("DELETE FROM customer_tag_links WHERE customer_id = ?")
                  ->execute([$customerId]);

        // Thêm tags mới
        if (!empty($tagIds)) {
            $stmt = $this->pdo->prepare("INSERT IGNORE INTO customer_tag_links (customer_id, tag_id) VALUES (?,?)");
            foreach ($tagIds as $tagId) {
                if (is_numeric($tagId)) $stmt->execute([$customerId, (int)$tagId]);
            }
        }
        $this->getCustomerTags($customerId);
    }
}
