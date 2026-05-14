<?php

class StatisticsController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // GET /api/statistics/dashboard
    public function dashboard(): void {
        requireAuth();

        $stats = [
            'total_customers'        => (int) $this->pdo->query("SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL")->fetchColumn(),
            'total_employees'        => (int) $this->pdo->query("SELECT COUNT(*) FROM employees WHERE deleted_at IS NULL")->fetchColumn(),
            'total_products'         => (int) $this->pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL")->fetchColumn(),
            'total_orders'           => (int) $this->pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
            'orders_pending'         => (int) $this->pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(),
            'orders_completed'       => (int) $this->pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn(),
            'total_revenue'          => (float) $this->pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status = 'completed'")->fetchColumn(),
            'open_conversations'     => (int) $this->pdo->query("SELECT COUNT(*) FROM conversations WHERE status = 'open'")->fetchColumn(),
            'pending_return_exchange'=> (int) $this->pdo->query("SELECT COUNT(*) FROM return_exchange_requests WHERE status = 'pending'")->fetchColumn(),
        ];

        // Customers by tier
        $tierBreakdown = $this->pdo->query(
            "SELECT ct.name as tier, COUNT(c.id) as count
             FROM customer_tiers ct
             LEFT JOIN customers c ON c.tier_id = ct.id AND c.deleted_at IS NULL
             GROUP BY ct.id, ct.name ORDER BY ct.discount_percent ASC"
        )->fetchAll();
        $stats['customers_by_tier'] = $tierBreakdown;

        // Orders last 6 months
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

        // Top 5 customers by order value
        $topCustomers = $this->pdo->query(
            "SELECT c.full_name, c.phone, COUNT(o.id) as order_count,
                    COALESCE(SUM(o.total_amount),0) as total_spent
             FROM customers c
             LEFT JOIN orders o ON o.customer_id = c.id AND o.status = 'completed'
             WHERE c.deleted_at IS NULL
             GROUP BY c.id, c.full_name, c.phone
             ORDER BY total_spent DESC LIMIT 5"
        )->fetchAll();
        $stats['top_customers'] = $topCustomers;

        success($stats);
    }
}
