<?php

class MessageController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // GET /api/conversations/{conversationId}/messages
    public function index(int $conversationId): void {
        requireAuth();
        $p = getPaginationParams();

        $total = $this->pdo->prepare("SELECT COUNT(*) FROM messages WHERE conversation_id = ?");
        $total->execute([$conversationId]);
        $total = (int) $total->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT m.*, cu.full_name as customer_name, e.full_name as employee_name
             FROM messages m
             LEFT JOIN customers cu ON m.sender_customer_id = cu.id
             LEFT JOIN employees e  ON m.sender_employee_id = e.id
             WHERE m.conversation_id = ?
             ORDER BY m.created_at ASC
             LIMIT {$p['per_page']} OFFSET {$p['offset']}"
        );
        $stmt->execute([$conversationId]);
        paginated($stmt->fetchAll(), $total, $p['page'], $p['per_page']);

        // Mark messages as read
        $actor = $GLOBALS['auth_user'];
        $this->pdo->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_employee_id != ?")
                  ->execute([$conversationId, $actor['id']]);
    }

    // POST /api/conversations/{conversationId}/messages
    public function store(int $conversationId): void {
        $actor = requireAuth();
        $body  = getJsonBody();

        if (empty($body['message'])) error('Nội dung tin nhắn không được để trống.', 422);

        $this->pdo->prepare(
            "INSERT INTO messages (conversation_id, sender_type, sender_employee_id, message) VALUES (?,?,?,?)"
        )->execute([$conversationId, 'employee', $actor['id'], $body['message']]);

        // Update conversation updated_at
        $this->pdo->prepare("UPDATE conversations SET updated_at = NOW(), status = 'open' WHERE id = ?")
                  ->execute([$conversationId]);

        $id   = $this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare("SELECT * FROM messages WHERE id = ?");
        $stmt->execute([$id]);
        success($stmt->fetch(), 'Tin nhắn đã được gửi.', 201);
    }
}
