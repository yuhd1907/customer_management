<?php
/**
 * ================================================================
 * controllers/StatisticsController.php — Số liệu tổng hợp Dashboard
 * ================================================================
 * Cung cấp dữ liệu thống kê cho màn hình Dashboard:
 *   GET /api/statistics/dashboard — Tổng hợp toàn bộ KPI và biểu đồ
 *
 * Dữ liệu trả về gồm 7 nhóm:
 *   1. Các chỉ số KPI đơn lẻ (total_customers, total_revenue...)
 *   2. Phân bổ KH theo hạng (customers_by_tier)
 *   3. Doanh thu & đơn hàng theo tháng (monthly_orders) — 6 tháng gần nhất
 *   4. Top 5 khách hàng chi tiêu nhiều nhất (top_customers)
 */
class StatisticsController {
    private PDO $pdo;

    /** @param PDO $pdo Kết nối database được inject từ index.php */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * GET /api/statistics/dashboard — Lấy toàn bộ dữ liệu Dashboard.
     *
     * Chạy nhiều câu query nhỏ rồi gộp vào 1 response duy nhất.
     * Mọi nhân viên đều có quyền xem dashboard.
     *
     * Lưu ý hiệu suất: mỗi lần load dashboard chạy ~10 câu query.
     * Có thể tối ưu bằng cache nếu hệ thống có nhiều người dùng đồng thời.
     */
    public function dashboard(): void {
        requireAuth(); // Mọi nhân viên đều có quyền xem

        // ── Nhóm 1: KPI đơn lẻ ─────────────────────────────────────
        $stats = [
            // Tổng KH đang hoạt động (không tính đã xóa mềm)
            'total_customers'         => (int) $this->pdo->query(
                "SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL"
            )->fetchColumn(),

            // Tổng nhân viên đang hoạt động
            'total_employees'         => (int) $this->pdo->query(
                "SELECT COUNT(*) FROM employees WHERE deleted_at IS NULL"
            )->fetchColumn(),

            // Tổng sản phẩm đang kinh doanh
            'total_products'          => (int) $this->pdo->query(
                "SELECT COUNT(*) FROM products WHERE deleted_at IS NULL"
            )->fetchColumn(),

            // Tổng số đơn hàng (tất cả trạng thái)
            'total_orders'            => (int) $this->pdo->query(
                "SELECT COUNT(*) FROM orders"
            )->fetchColumn(),

            // Đơn đang chờ xử lý (cần chú ý)
            'orders_pending'          => (int) $this->pdo->query(
                "SELECT COUNT(*) FROM orders WHERE status = 'pending'"
            )->fetchColumn(),

            // Đơn đã hoàn thành
            'orders_completed'        => (int) $this->pdo->query(
                "SELECT COUNT(*) FROM orders WHERE status = 'completed'"
            )->fetchColumn(),

            // Tổng doanh thu — chỉ tính đơn đã hoàn thành (status = completed)
            // COALESCE: trả về 0 nếu chưa có đơn nào (tránh NULL)
            'total_revenue'           => (float) $this->pdo->query(
                "SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = 'completed'"
            )->fetchColumn(),

            // Số hội thoại CSKH đang mở (cần phản hồi)
            'open_conversations'      => (int) $this->pdo->query(
                "SELECT COUNT(*) FROM conversations WHERE status = 'open'"
            )->fetchColumn(),

            // Yêu cầu đổi/trả đang chờ xử lý
            'pending_return_exchange' => (int) $this->pdo->query(
                "SELECT COUNT(*) FROM return_exchange_requests WHERE status = 'pending'"
            )->fetchColumn(),
        ];

        // ── Nhóm 2: Phân bổ KH theo hạng ────────────────────────────
        // LEFT JOIN để luôn hiển thị đủ các hạng, kể cả hạng chưa có KH nào
        // ORDER BY discount_percent ASC: sắp từ hạng thấp → hạng cao
        $tierBreakdown = $this->pdo->query(
            "SELECT ct.name as tier, COUNT(c.id) as count
             FROM customer_tiers ct
             LEFT JOIN customers c ON c.tier_id = ct.id AND c.deleted_at IS NULL
             GROUP BY ct.id, ct.name
             ORDER BY ct.discount_percent ASC"
        )->fetchAll();
        $stats['customers_by_tier'] = $tierBreakdown;

        // ── Nhóm 3: Đơn hàng và doanh thu theo tháng (6 tháng gần nhất) ──
        // DATE_FORMAT: gom nhóm theo tháng (format: '2024-05')
        // DATE_SUB(NOW(), INTERVAL 6 MONTH): lọc 6 tháng gần nhất
        // Dùng cho biểu đồ đường (line chart) trên Dashboard
        $monthly = $this->pdo->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as total_orders,
                    COALESCE(SUM(total_amount), 0) as revenue
             FROM orders
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY month ASC"
        )->fetchAll();
        $stats['monthly_orders'] = $monthly;

        // ── Nhóm 4: Top 5 KH chi tiêu nhiều nhất ────────────────────
        // Chỉ tính đơn status = 'completed' (đã thanh toán xong)
        // COALESCE: KH chưa có đơn nào sẽ có total_spent = 0
        $topCustomers = $this->pdo->query(
            "SELECT c.full_name, c.phone,
                    COUNT(o.id) as order_count,
                    COALESCE(SUM(o.total_amount), 0) as total_spent
             FROM customers c
             LEFT JOIN orders o ON o.customer_id = c.id AND o.status = 'completed'
             WHERE c.deleted_at IS NULL
             GROUP BY c.id, c.full_name, c.phone
             ORDER BY total_spent DESC
             LIMIT 5"
        )->fetchAll();
        $stats['top_customers'] = $topCustomers;

        // Trả về toàn bộ dữ liệu trong 1 response
        success($stats);
    }
}
