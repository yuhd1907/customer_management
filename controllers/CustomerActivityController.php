<?php

class CustomerActivityController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // GET /api/customers/{customerId}/activities
    public function index(int $customerId): void {
        requireAuth();
        $p    = getPaginationParams();
        $type = $_GET['type'] ?? '';

        $where  = ["a.customer_id = ?"];
        $params = [$customerId];
        if ($type && in_array($type, ['call','meeting','email','note','other'])) {
            $where[] = "a.type = ?"; $params[] = $type;
        }
        $sql = "WHERE " . implode(" AND ", $where);

        $total = $this->pdo->prepare("SELECT COUNT(*) FROM customer_activities a $sql");
        $total->execute($params);
        $total = (int) $total->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT a.*, e.full_name as employee_name
             FROM customer_activities a
             LEFT JOIN employees e ON a.employee_id = e.id
             $sql ORDER BY a.activity_date DESC
             LIMIT {$p['per_page']} OFFSET {$p['offset']}"
        );
        $stmt->execute($params);
        paginated($stmt->fetchAll(), $total, $p['page'], $p['per_page']);
    }

    // POST /api/customers/{customerId}/activities
    public function store(int $customerId): void {
        $actor = requireAuth();
        $body  = getJsonBody();

        if (empty($body['title'])) error('Tiêu đề không được để trống.', 422);
        $type = $body['type'] ?? 'note';
        if (!in_array($type, ['call','meeting','email','note','other'])) $type = 'note';

        $date = !empty($body['activity_date']) ? $body['activity_date'] : date('Y-m-d H:i:s');

        $this->pdo->prepare(
            "INSERT INTO customer_activities (customer_id, employee_id, type, title, content, activity_date)
             VALUES (?,?,?,?,?,?)"
        )->execute([
            $customerId,
            $actor['id'],
            $type,
            $body['title'],
            $body['content'] ?? null,
            $date,
        ]);
        $id   = $this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare(
            "SELECT a.*, e.full_name as employee_name
             FROM customer_activities a
             LEFT JOIN employees e ON a.employee_id = e.id
             WHERE a.id = ?"
        );
        $stmt->execute([$id]);
        success($stmt->fetch(), 'Đã ghi nhận hoạt động.', 201);
    }

    // DELETE /api/customers/{customerId}/activities/{id}
    public function destroy(int $customerId, int $id): void {
        requireAuth();
        $this->pdo->prepare(
            "DELETE FROM customer_activities WHERE id = ? AND customer_id = ?"
        )->execute([$id, $customerId]);
        success(null, 'Đã xóa hoạt động.');
    }
}
