<?php
/**
 * ================================================================
 * controllers/ReturnExchangeController.php — Đổi / Hoàn trả hàng
 * ================================================================
 * Xử lý toàn bộ vòng đời yêu cầu đổi/trả của khách hàng:
 *
 *   GET   /api/return-exchange-requests            — Danh sách (filter + phân trang)
 *   GET   /api/return-exchange-requests/{id}       — Chi tiết kèm sản phẩm
 *   POST  /api/return-exchange-requests            — Tạo yêu cầu mới
 *   PATCH /api/return-exchange-requests/{id}/approve  — Duyệt yêu cầu
 *   PATCH /api/return-exchange-requests/{id}/reject   — Từ chối yêu cầu
 *   PATCH /api/return-exchange-requests/{id}/complete — Hoàn thành xử lý
 *
 * Vòng đời trạng thái:
 *   pending → approved  → completed
 *           → rejected
 *           → cancelled
 */
class ReturnExchangeController {
    private PDO $pdo;

    /** @param PDO $pdo Kết nối database được inject từ index.php */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * GET /api/return-exchange-requests — Danh sách yêu cầu đổi/trả.
     *
     * Mọi nhân viên đều có thể xem (không phân biệt role).
     * Hỗ trợ filter:
     *   ?status=pending        — Lọc theo trạng thái
     *   ?request_type=return   — Chỉ lấy yêu cầu hoàn trả
     *   ?request_type=exchange — Chỉ lấy yêu cầu đổi hàng
     *   ?customer_id=5         — Yêu cầu của 1 khách hàng cụ thể
     */
    public function index(): void {
        $actor  = requireAuth();
        $p      = getPaginationParams();
        $status = $_GET['status']       ?? '';
        $type   = $_GET['request_type'] ?? '';
        $custId = $_GET['customer_id']  ?? '';

        $where = []; $params = [];
        if ($status) { $where[] = "r.status = ?"; $params[] = $status; }

        // Whitelist loại yêu cầu để tránh SQL injection
        if ($type && in_array($type, ['return', 'exchange'])) {
            $where[] = "r.request_type = ?"; $params[] = $type;
        }
        if ($custId) { $where[] = "r.customer_id = ?"; $params[] = $custId; }

        $sql   = $where ? "WHERE " . implode(" AND ", $where) : "";
        $total = $this->pdo->prepare("SELECT COUNT(*) FROM return_exchange_requests r $sql");
        $total->execute($params);
        $total = (int) $total->fetchColumn();

        // JOIN để lấy tên KH, mã đơn hàng, và tên NV xử lý
        $stmt = $this->pdo->prepare(
            "SELECT r.*, c.full_name as customer_name, o.order_code, e.full_name as employee_name
             FROM return_exchange_requests r
             LEFT JOIN customers c  ON r.customer_id  = c.id
             LEFT JOIN orders o     ON r.order_id     = o.id
             LEFT JOIN employees e  ON r.employee_id  = e.id
             $sql ORDER BY r.created_at DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}"
        );
        $stmt->execute($params);
        paginated($stmt->fetchAll(), $total, $p['page'], $p['per_page']);
    }

    /**
     * GET /api/return-exchange-requests/{id} — Chi tiết yêu cầu kèm sản phẩm.
     *
     * Trả về thông tin yêu cầu + mảng items[] gồm:
     *   - Sản phẩm cũ (đang trả lại): old_product_name
     *   - Sản phẩm mới (đổi sang):    new_product_name
     *   - Số lượng đặt ban đầu:       ordered_quantity
     *
     * @param int $id  ID yêu cầu đổi/trả
     */
    public function show(int $id): void {
        requireAuth();
        $req = $this->_findOrFail($id);

        // Lấy chi tiết sản phẩm trong yêu cầu
        // LEFT JOIN với products 2 lần: 1 lần cho sản phẩm cũ, 1 lần cho sản phẩm mới
        $items = $this->pdo->prepare(
            "SELECT rei.*,
                    oi.quantity as ordered_quantity,
                    p_old.name as old_product_name,
                    p_new.name as new_product_name
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

    /**
     * POST /api/return-exchange-requests — Tạo yêu cầu đổi/trả mới.
     *
     * Mọi nhân viên đều có thể tạo yêu cầu.
     * Các trường bắt buộc: customer_id, order_id, request_type, reason
     *
     * request_type phải là 'return' (hoàn tiền) hoặc 'exchange' (đổi hàng).
     * Có thể gắn với hội thoại CSKH (conversation_id) nếu có.
     */
    public function store(): void {
        $actor = requireAuth();
        $body  = getJsonBody();

        // Validate tất cả các trường bắt buộc
        $required = ['customer_id', 'order_id', 'request_type', 'reason'];
        foreach ($required as $f) {
            if (empty($body[$f])) error("Thiếu trường bắt buộc: $f.", 422);
        }

        // Chỉ chấp nhận 2 loại yêu cầu hợp lệ
        if (!in_array($body['request_type'], ['return', 'exchange'])) {
            error('Loại yêu cầu không hợp lệ.', 422);
        }

        $this->pdo->prepare(
            "INSERT INTO return_exchange_requests
             (customer_id, order_id, conversation_id, request_type, reason, employee_id, customer_note)
             VALUES (?,?,?,?,?,?,?)"
        )->execute([
            $body['customer_id'],
            $body['order_id'],
            $body['conversation_id'] ?? null, // Liên kết với chat CSKH (tùy chọn)
            $body['request_type'],
            $body['reason'],
            $actor['id'],                     // Nhân viên tạo yêu cầu
            $body['customer_note'] ?? null,   // Ghi chú thêm của khách
        ]);

        $id = $this->pdo->lastInsertId();
        $this->show($id); // Trả về chi tiết yêu cầu vừa tạo
    }

    /**
     * PATCH /api/return-exchange-requests/{id}/approve — Duyệt yêu cầu.
     *
     * Ghi lại: nhân viên duyệt (employee_id), ghi chú của admin (admin_note).
     * Trạng thái chuyển sang 'approved'.
     *
     * @param int $id  ID yêu cầu cần duyệt
     */
    public function approve(int $id): void {
        $actor = requireAuth(); // Mọi nhân viên có thể duyệt (front-end kiểm soát role)
        $this->_findOrFail($id);
        $body  = getJsonBody();

        $this->pdo->prepare(
            "UPDATE return_exchange_requests
             SET status = 'approved', admin_note = ?, employee_id = ?
             WHERE id = ?"
        )->execute([
            $body['admin_note'] ?? null, // Lý do/ghi chú khi duyệt
            $actor['id'],                // Ghi lại ai đã duyệt
            $id,
        ]);
        $this->show($id);
    }

    /**
     * PATCH /api/return-exchange-requests/{id}/reject — Từ chối yêu cầu.
     *
     * Ghi lại: nhân viên từ chối và lý do (admin_note — bắt buộc về UX).
     * Trạng thái chuyển sang 'rejected'.
     *
     * @param int $id  ID yêu cầu cần từ chối
     */
    public function reject(int $id): void {
        $actor = requireAuth();
        $this->_findOrFail($id);
        $body  = getJsonBody();

        $this->pdo->prepare(
            "UPDATE return_exchange_requests
             SET status = 'rejected', admin_note = ?, employee_id = ?
             WHERE id = ?"
        )->execute([
            $body['admin_note'] ?? null, // Lý do từ chối (nên bắt buộc)
            $actor['id'],
            $id,
        ]);
        $this->show($id);
    }

    /**
     * PATCH /api/return-exchange-requests/{id}/complete — Hoàn tất xử lý.
     *
     * Dùng khi đã thực hiện xong việc hoàn tiền hoặc gửi sản phẩm đổi.
     * Trạng thái chuyển sang 'completed'.
     *
     * @param int $id  ID yêu cầu cần đánh dấu hoàn thành
     */
    public function complete(int $id): void {
        requireAuth();
        $this->_findOrFail($id);
        $this->pdo->prepare(
            "UPDATE return_exchange_requests SET status = 'completed' WHERE id = ?"
        )->execute([$id]);
        $this->show($id);
    }

    /**
     * Tìm yêu cầu đổi/trả theo ID hoặc ném lỗi 404.
     *
     * JOIN để lấy thêm: tên KH, mã đơn hàng, tên NV xử lý.
     * Dùng nội bộ trước mọi thao tác cập nhật trạng thái.
     *
     * @param int $id  ID yêu cầu cần tìm
     * @return array   Bản ghi yêu cầu đổi/trả đầy đủ
     */
    private function _findOrFail(int $id): array {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, c.full_name as customer_name, o.order_code, e.full_name as employee_name
             FROM return_exchange_requests r
             LEFT JOIN customers c  ON r.customer_id = c.id
             LEFT JOIN orders o     ON r.order_id    = o.id
             LEFT JOIN employees e  ON r.employee_id = e.id
             WHERE r.id = ?"
        );
        $stmt->execute([$id]);
        $req = $stmt->fetch();
        if (!$req) error('Không tìm thấy yêu cầu.', 404);
        return $req;
    }
}
