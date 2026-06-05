<?php
/**
 * ================================================================
 * middlewares/admin.php — Kiểm tra quyền Admin / Admin+Manager
 * ================================================================
 * File này cung cấp 2 hàm "shortcut" thường dùng nhất trong các
 * Controller để bảo vệ các route nhạy cảm.
 *
 * Cách dùng trong Controller:
 *
 *   // Chỉ Admin mới được tạo/xóa nhân viên
 *   public function store(): void {
 *       requireAdmin();
 *       // ... logic tạo nhân viên
 *   }
 *
 *   // Admin và Manager đều được duyệt đổi/trả hàng
 *   public function approve(int $id): void {
 *       requireAdminOrManager();
 *       // ... logic duyệt
 *   }
 */

/**
 * Chỉ cho phép tài khoản có vai trò "admin" truy cập.
 *
 * Nếu người gọi không phải admin → trả về HTTP 403 Forbidden.
 * Nếu chưa đăng nhập → trả về HTTP 401 Unauthorized.
 *
 * @return array  Thông tin admin đang đăng nhập
 */
function requireAdmin(): array {
    // Ủy quyền cho requireRole() với tham số cố định là 'admin'
    return requireRole('admin');
}

/**
 * Cho phép cả "admin" và "manager" truy cập.
 *
 * Dùng cho các tính năng cần cấp quản lý nhưng không chỉ giới hạn admin,
 * VD: duyệt yêu cầu đổi/trả, xem báo cáo nâng cao.
 *
 * @return array  Thông tin user (admin hoặc manager) đang đăng nhập
 */
function requireAdminOrManager(): array {
    // Truyền nhiều vai trò vào requireRole() — user cần có MỘT TRONG HAI
    return requireRole('admin', 'manager');
}
