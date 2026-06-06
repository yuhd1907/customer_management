-- =====================================================
-- SEED DATA
-- =====================================================

-- Customer Tiers
INSERT INTO customer_tiers (name, description, discount_percent) VALUES
('Đồng', 'Khách hàng mới, chưa có giao dịch nhiều', 0),
('Bạc', 'Khách hàng thường xuyên, đã mua từ 5 đơn', 5),
('Vàng', 'Khách hàng VIP, doanh thu cao', 10);

-- Admin account (password: Admin@123)
INSERT INTO employees (staff_code, full_name, username, email, phone, password_hash, role, status, position)
VALUES ('EMP001', 'Nguyễn Văn Admin', 'admin', 'admin@company.com', '0900000001',
    '$2y$12$EjY2CCKDItWvEE6GzxaMRObTsMigX6/0xYdxESV0uCimAGMnPd3pC', 'admin', 'active', 'System Administrator');

-- Manager account (password: Manager@123)
INSERT INTO employees (staff_code, full_name, username, email, phone, password_hash, role, status, position, created_by)
VALUES ('EMP002', 'Trần Thị Manager', 'manager01', 'manager@company.com', '0900000002',
    '$2y$12$oTmXF73QszU3ZdLw1Ip2hexl41wvgtFiriVhny4pBysdiwcPzmXjS', 'manager', 'active', 'Sales Manager', 1);

-- Staff account (password: Staff@123)
INSERT INTO employees (staff_code, full_name, username, email, phone, password_hash, role, status, position, created_by)
VALUES ('EMP003', 'Lê Văn Staff', 'staff01', 'staff@company.com', '0900000003',
    '$2y$12$acuwvkJ/Km7dblLSQa7pzu9/G/nYmeh/9ZPQCcIgJR.TyiH32qI8u', 'staff', 'active', 'Customer Support', 1);

-- Sample Customers
INSERT INTO customers (tier_id, assigned_employee_id, full_name, phone, email, gender, address, status, created_by) VALUES
(3, 3, 'Phạm Thị Lan', '0912345678', 'lan.pham@gmail.com', 'female', '123 Nguyễn Huệ, Q1, TP.HCM', 'active', 1),
(2, 3, 'Nguyễn Văn Bình', '0923456789', 'binh.nguyen@gmail.com', 'male', '456 Lê Lợi, Q3, TP.HCM', 'active', 1),
(1, 2, 'Trần Thị Cúc', '0934567890', 'cuc.tran@gmail.com', 'female', '789 CMT8, Q10, TP.HCM', 'active', 1),
(1, NULL, 'Lê Văn Dũng', '0945678901', NULL, 'male', 'Hà Nội', 'active', 2),
(2, 3, 'Hoàng Thị Emm', '0956789012', 'emm@gmail.com', 'female', 'Đà Nẵng', 'active', 2);

-- Sample Products
INSERT INTO products (name, sku, description, price, stock_quantity, status, created_by) VALUES
('iPhone 15 Pro Max 256GB', 'IPHONE15PM-256', 'Điện thoại Apple iPhone 15 Pro Max', 33990000, 25, 'active', 1),
('Samsung Galaxy S24 Ultra', 'SAMSUNG-S24U', 'Điện thoại Samsung S24 Ultra 256GB', 29990000, 18, 'active', 1),
('Laptop Dell XPS 15', 'DELL-XPS15', 'Laptop Dell XPS 15 inch Core i7', 45990000, 10, 'active', 1),
('Tai nghe AirPods Pro', 'AIRPODS-PRO2', 'Apple AirPods Pro thế hệ 2', 6490000, 50, 'active', 1),
('Bàn phím Keychron K2', 'KEYCHRON-K2', 'Bàn phím cơ không dây', 2290000, 30, 'active', 2);

-- Sample Orders
INSERT INTO orders (customer_id, employee_id, order_code, total_amount, status) VALUES
(1, 3, 'ORD-2024-001', 33990000, 'completed'),
(2, 3, 'ORD-2024-002', 36480000, 'shipping'),
(3, 2, 'ORD-2024-003', 6490000, 'confirmed'),
(1, 3, 'ORD-2024-004', 2290000, 'pending');

-- Sample Order Items
INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) VALUES
(1, 1, 1, 33990000, 33990000),
(2, 2, 1, 29990000, 29990000),
(2, 4, 1, 6490000, 6490000),
(3, 4, 1, 6490000, 6490000),
(4, 5, 1, 2290000, 2290000);

-- Sample Conversations
INSERT INTO conversations (customer_id, employee_id, title, type, status) VALUES
(1, 3, 'Tư vấn iPhone 15', 'product_consulting', 'closed'),
(2, 3, 'Hỗ trợ đơn hàng ORD-2024-002', 'general_support', 'open'),
(3, NULL, 'Yêu cầu đổi AirPods', 'exchange_request', 'pending');

-- Sample Messages
INSERT INTO messages (conversation_id, sender_type, sender_customer_id, sender_employee_id, message) VALUES
(1, 'customer', 1, NULL, 'Tôi muốn hỏi về iPhone 15 Pro Max màu titan?'),
(1, 'employee', NULL, 3, 'Dạ, hiện tại shop có đủ màu: Titan Đen, Titan Trắng, Titan Xanh, Titan Tự Nhiên.'),
(2, 'customer', 2, NULL, 'Đơn hàng của tôi khi nào được giao?'),
(2, 'employee', NULL, 3, 'Đơn hàng đang được vận chuyển, dự kiến 1-2 ngày nữa sẽ đến.');
