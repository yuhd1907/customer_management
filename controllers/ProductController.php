<?php

class ProductController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // GET /api/products
    public function index(): void {
        requireAuth();
        $p      = getPaginationParams();
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';

        $where = ['deleted_at IS NULL']; $params = [];
        if ($search) {
            $where[] = "(name LIKE ? OR sku LIKE ?)";
            $s = "%$search%"; array_push($params, $s, $s);
        }
        if ($status && in_array($status, ['active','inactive'])) {
            $where[] = "status = ?"; $params[] = $status;
        }

        $sql   = "WHERE " . implode(" AND ", $where);
        $total = $this->pdo->prepare("SELECT COUNT(*) FROM products $sql");
        $total->execute($params);
        $total = (int) $total->fetchColumn();

        $stmt  = $this->pdo->prepare("SELECT * FROM products $sql ORDER BY id DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}");
        $stmt->execute($params);
        paginated($stmt->fetchAll(), $total, $p['page'], $p['per_page']);
    }

    // GET /api/products/{id}
    public function show(int $id): void {
        requireAuth();
        success($this->_findOrFail($id));
    }

    // POST /api/products
    public function store(): void {
        $actor = requireAdminOrManager();
        $body  = getJsonBody();

        if (empty($body['name']))  error('Thiếu tên sản phẩm.', 422);
        if (!isset($body['price'])) error('Thiếu giá sản phẩm.', 422);

        if (!empty($body['sku'])) {
            $chk = $this->pdo->prepare("SELECT id FROM products WHERE sku = ? AND deleted_at IS NULL");
            $chk->execute([$body['sku']]);
            if ($chk->fetch()) error('SKU đã tồn tại.', 409);
        }

        $this->pdo->prepare(
            "INSERT INTO products (name, sku, description, price, stock_quantity, status, created_by)
             VALUES (?,?,?,?,?,?,?)"
        )->execute([
            $body['name'],
            $body['sku'] ?? null,
            $body['description'] ?? null,
            $body['price'],
            $body['stock_quantity'] ?? 0,
            $body['status'] ?? 'active',
            $actor['id'],
        ]);
        $id = $this->pdo->lastInsertId();
        $this->show($id);
    }

    // PUT /api/products/{id}
    public function update(int $id): void {
        $actor = requireAdminOrManager();
        $this->_findOrFail($id);
        $body  = getJsonBody();

        $fields = []; $params = [];
        $map = ['name','sku','description','price','stock_quantity','status'];
        foreach ($map as $f) {
            if (isset($body[$f])) { $fields[] = "$f = ?"; $params[] = $body[$f]; }
        }
        if (empty($fields)) error('Không có trường nào để cập nhật.', 422);
        $fields[] = "updated_by = ?"; $params[] = $actor['id'];
        $params[] = $id;

        $this->pdo->prepare("UPDATE products SET " . implode(', ', $fields) . " WHERE id = ?")
                  ->execute($params);
        $this->show($id);
    }

    // DELETE /api/products/{id}
    public function destroy(int $id): void {
        requireAdmin();
        $this->_findOrFail($id);
        $this->pdo->prepare("UPDATE products SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
        success(null, 'Đã xóa sản phẩm.');
    }

    private function _findOrFail(int $id): array {
        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $p = $stmt->fetch();
        if (!$p) error('Không tìm thấy sản phẩm.', 404);
        return $p;
    }
}
