<?php

class CustomerTaskController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // GET /api/customers/{customerId}/tasks
    public function index(int $customerId): void {
        requireAuth();
        $status = $_GET['status'] ?? '';
        $where  = ["t.customer_id = ?"];
        $params = [$customerId];
        if ($status && in_array($status, ['pending','done','cancelled'])) {
            $where[] = "t.status = ?"; $params[] = $status;
        }
        $sql  = "WHERE " . implode(" AND ", $where);
        $stmt = $this->pdo->prepare(
            "SELECT t.*, e.full_name as employee_name
             FROM customer_tasks t
             LEFT JOIN employees e ON t.employee_id = e.id
             $sql ORDER BY t.due_date ASC"
        );
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // GET /api/tasks/upcoming — tất cả tasks sắp tới (cho sidebar dashboard)
    public function upcoming(): void {
        $actor = requireAuth();
        $where  = ["t.status = 'pending' AND t.due_date >= NOW() AND t.due_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)"];
        $params = [];
        if ($actor['role'] === 'staff') {
            $where[] = "t.employee_id = ?"; $params[] = $actor['id'];
        }
        $sql  = "WHERE " . implode(" AND ", $where);
        $stmt = $this->pdo->prepare(
            "SELECT t.*, c.full_name as customer_name, c.phone as customer_phone
             FROM customer_tasks t
             LEFT JOIN customers c ON t.customer_id = c.id
             $sql ORDER BY t.due_date ASC LIMIT 20"
        );
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // GET /api/customers/birthdays — KH sinh nhật trong tháng
    public function birthdays(): void {
        requireAuth();
        $month = $_GET['month'] ?? date('m');
        $stmt  = $this->pdo->prepare(
            "SELECT c.id, c.full_name, c.phone, c.email, c.date_of_birth,
                    ct.name as tier_name, e.full_name as assigned_employee_name,
                    DAY(c.date_of_birth) as birth_day
             FROM customers c
             LEFT JOIN customer_tiers ct ON c.tier_id = ct.id
             LEFT JOIN employees e ON c.assigned_employee_id = e.id
             WHERE c.deleted_at IS NULL AND MONTH(c.date_of_birth) = ?
             ORDER BY DAY(c.date_of_birth) ASC"
        );
        $stmt->execute([(int)$month]);
        success($stmt->fetchAll());
    }

    // POST /api/customers/{customerId}/tasks
    public function store(int $customerId): void {
        $actor = requireAuth();
        $body  = getJsonBody();
        if (empty($body['title']))    error('Tiêu đề không được để trống.', 422);
        if (empty($body['due_date'])) error('Ngày hạn không được để trống.', 422);

        $priority = $body['priority'] ?? 'normal';
        if (!in_array($priority, ['low','normal','high'])) $priority = 'normal';

        $this->pdo->prepare(
            "INSERT INTO customer_tasks (customer_id, employee_id, title, description, due_date, priority)
             VALUES (?,?,?,?,?,?)"
        )->execute([
            $customerId,
            $body['employee_id'] ?? $actor['id'],
            $body['title'],
            $body['description'] ?? null,
            $body['due_date'],
            $priority,
        ]);
        $id   = $this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare(
            "SELECT t.*, e.full_name as employee_name
             FROM customer_tasks t LEFT JOIN employees e ON t.employee_id = e.id WHERE t.id = ?"
        );
        $stmt->execute([$id]);
        success($stmt->fetch(), 'Đã tạo lịch nhắc.', 201);
    }

    // PATCH /api/customers/{customerId}/tasks/{id}/done
    public function markDone(int $customerId, int $id): void {
        requireAuth();
        $this->pdo->prepare(
            "UPDATE customer_tasks SET status = 'done' WHERE id = ? AND customer_id = ?"
        )->execute([$id, $customerId]);
        success(null, 'Đã đánh dấu hoàn thành.');
    }

    // PATCH /api/customers/{customerId}/tasks/{id}/cancel
    public function cancel(int $customerId, int $id): void {
        requireAuth();
        $this->pdo->prepare(
            "UPDATE customer_tasks SET status = 'cancelled' WHERE id = ? AND customer_id = ?"
        )->execute([$id, $customerId]);
        success(null, 'Đã hủy lịch nhắc.');
    }

    // DELETE /api/customers/{customerId}/tasks/{id}
    public function destroy(int $customerId, int $id): void {
        requireAuth();
        $this->pdo->prepare(
            "DELETE FROM customer_tasks WHERE id = ? AND customer_id = ?"
        )->execute([$id, $customerId]);
        success(null, 'Đã xóa lịch nhắc.');
    }
}
