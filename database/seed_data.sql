-- =====================================================
-- SEED DATA PHONG PHÚ — Customer Management CRM
-- Chạy SAU khi đã chạy db_seed.sql gốc VÀ migration_crm_features.sql
-- =====================================================

USE customer_management;

-- ── 1. Thêm nhóm khách hàng (bổ sung vào nhóm đã có từ migration) ──────
INSERT IGNORE INTO customer_groups (name, description, color, created_by) VALUES
('Khách miền Trung', N'Khách hàng khu vực Đà Nẵng, Huế, Vinh...', '#d97706', 1),
('KH giới thiệu',    N'Được giới thiệu từ khách hàng cũ',           '#0891b2', 1),
('Chiến dịch Hè 2025', N'KH tham gia khuyến mãi hè 2025',          '#dc2626', 1);

-- ── 2. Tags ──────────────────────────────────────────────
INSERT IGNORE INTO customer_tags (name, color) VALUES
('VIP',         '#dc2626'),
('Tiềm năng',   '#2563eb'),
('Thân thiết',  '#16a34a'),
('Khó tính',    '#d97706'),
('Mới',         '#6d28d9'),
('Ưu tiên',     '#0891b2'),
('Chờ xử lý',   '#be185d');

-- ── 3. Thêm khách hàng ──────────────────────────────────
INSERT IGNORE INTO customers (tier_id, assigned_employee_id, full_name, phone, email, gender, date_of_birth, address, source, status, note, created_by, customer_group_id) VALUES
-- Khách VIP hạng Vàng
(3, 2, N'Nguyễn Thị Hoa',     '0901111001', 'hoa.nguyen@gmail.com',    'female', '1985-03-15', N'45 Lý Thường Kiệt, Hoàn Kiếm, Hà Nội',   'referral', 'active', N'KH thân thiết, mua hàng đều đặn', 2, 1),
(3, 2, N'Trần Văn Minh',      '0901111002', 'minh.tran@company.vn',    'male',   '1978-07-22', N'123 Điện Biên Phủ, Bình Thạnh, TP.HCM',   'facebook', 'active', N'Giám đốc công ty XYZ, mua số lượng lớn', 2, 4),
(3, 3, N'Lê Thị Thanh',       '0901111003', 'thanh.le@hotmail.com',    'female', '1990-12-08', N'78 Trần Phú, Hải Châu, Đà Nẵng',          'website',  'active', N'Hay giới thiệu bạn bè', 3, 3),
(3, 2, N'Phạm Đức Anh',       '0901111004', 'duc.anh@gmail.com',       'male',   '1982-05-30', N'22 Nguyễn Huệ, Quận 1, TP.HCM',           'store',    'active', N'Doanh nhân, thích sản phẩm cao cấp', 2, 2),
-- Khách hạng Bạc
(2, 3, N'Hoàng Thị Lan',      '0901111005', 'lan.hoang@gmail.com',     'female', '1995-09-14', N'56 Bà Triệu, Hoàn Kiếm, Hà Nội',          'zalo',     'active', N'', 3, 1),
(2, 2, N'Vũ Quang Huy',       '0901111006', 'huy.vu@gmail.com',        'male',   '1988-02-28', N'34 Cách Mạng Tháng 8, Quận 3, TP.HCM',    'facebook', 'active', N'Hay hỏi giá, cần tư vấn kỹ', 2, 2),
(2, 3, N'Ngô Thị Kim',        '0901111007', 'kim.ngo@yahoo.com',       'female', '1992-11-03', N'90 Lê Lợi, Quận Hải Châu, Đà Nẵng',       'referral', 'active', N'', 3, 3),
(2, 2, N'Đặng Văn Long',      '0901111008', 'long.dang@gmail.com',     'male',   '1975-04-17', N'12 Đinh Tiên Hoàng, Bình Thạnh, TP.HCM',  'store',    'active', N'', 2, 4),
-- Khách hạng Đồng
(1, 3, N'Bùi Thị Phương',     '0901111009', 'phuong.bui@gmail.com',    'female', '1998-06-25', N'67 Kim Mã, Ba Đình, Hà Nội',               'website',  'active', N'', 3, 1),
(1, 2, N'Lý Văn Tùng',        '0901111010', 'tung.ly@gmail.com',       'male',   '2000-01-12', N'45 Võ Văn Tần, Quận 3, TP.HCM',            'facebook', 'active', N'Khách trẻ, mua online nhiều', 2, 5),
(1, 3, N'Mai Thị Xuân',       '0901111011', 'xuan.mai@gmail.com',      'female', '1993-03-08', N'23 Trần Hưng Đạo, Hoàn Kiếm, Hà Nội',     'zalo',     'active', N'', 3, 1),
(1, 2, N'Trịnh Văn Đức',      '0901111012', 'duc.trinh@gmail.com',     'male',   '1987-08-19', N'89 Nguyễn Thị Minh Khai, Quận 1, TP.HCM', 'referral', 'active', N'Bạn của KH Nguyễn Thị Hoa', 2, 6),
-- Khách không hạng
(NULL, 3, N'Lương Thị Hằng',  '0901111013', 'hang.luong@gmail.com',    'female', '1996-05-20', N'34 Lạch Tray, Ngô Quyền, Hải Phòng',      'website',  'active', N'Khách mới, đang tìm hiểu', 3, NULL),
(NULL, 2, N'Đinh Văn Khôi',   '0901111014', 'khoi.dinh@gmail.com',     'male',   '1991-10-07', N'78 Hai Bà Trưng, Hoàn Kiếm, Hà Nội',      'facebook', 'active', N'', 2, 1),
(NULL, 3, N'Phan Thị Ngọc',   '0901111015', 'ngoc.phan@gmail.com',     'female', '1999-07-31', N'12 Hùng Vương, Hải Châu, Đà Nẵng',         'store',    'inactive', N'Đã lâu không mua', 3, 3),
(NULL, 2, N'Hồ Văn Tiến',     '0901111016', 'tien.ho@gmail.com',       'male',   '1984-12-15', N'56 Pasteur, Quận 3, TP.HCM',               'zalo',     'active', N'', 2, 2),
(2, 3, N'Cao Thị Bích',       '0901111017', 'bich.cao@gmail.com',      'female', '1994-04-09', N'89 Lê Văn Sỹ, Quận Phú Nhuận, TP.HCM',   'referral', 'active', N'Thích mua theo gói combo', 2, 6),
(1, 2, N'Tô Minh Nhật',       '0901111018', 'nhat.to@gmail.com',       'male',   '2001-09-03', N'23 Nguyễn Du, Hai Bà Trưng, Hà Nội',       'website',  'active', N'', 2, 1),
(NULL, 3, N'Đỗ Thị Linh',     '0901111019', 'linh.do@gmail.com',       'female', '1997-02-14', N'45 Trường Chinh, Thanh Xuân, Hà Nội',      'facebook', 'active', N'Sinh nhật tháng 2, cần nhắc', 3, NULL),
(3, 2, N'Quách Văn Tuấn',     '0901111020', 'tuan.quach@company.vn',   'male',   '1979-11-28', N'67 Nguyễn Văn Linh, Quận 7, TP.HCM',      'referral', 'active', N'Đối tác, mua số lượng lớn hàng tháng', 2, 4);

-- ── 4. Gán tags cho khách hàng (customer_id mới thêm có id từ ~6) ──
-- Lấy id tự động -- Dùng subquery để an toàn
INSERT IGNORE INTO customer_tag_links (customer_id, tag_id)
SELECT c.id, t.id FROM customers c, customer_tags t
WHERE c.phone = '0901111001' AND t.name IN ('VIP', 'Thân thiết');

INSERT IGNORE INTO customer_tag_links (customer_id, tag_id)
SELECT c.id, t.id FROM customers c, customer_tags t
WHERE c.phone = '0901111002' AND t.name IN ('VIP', 'Ưu tiên');

INSERT IGNORE INTO customer_tag_links (customer_id, tag_id)
SELECT c.id, t.id FROM customers c, customer_tags t
WHERE c.phone = '0901111003' AND t.name IN ('Thân thiết');

INSERT IGNORE INTO customer_tag_links (customer_id, tag_id)
SELECT c.id, t.id FROM customers c, customer_tags t
WHERE c.phone = '0901111004' AND t.name IN ('VIP', 'Tiềm năng');

INSERT IGNORE INTO customer_tag_links (customer_id, tag_id)
SELECT c.id, t.id FROM customers c, customer_tags t
WHERE c.phone = '0901111006' AND t.name IN ('Khó tính');

INSERT IGNORE INTO customer_tag_links (customer_id, tag_id)
SELECT c.id, t.id FROM customers c, customer_tags t
WHERE c.phone = '0901111010' AND t.name IN ('Mới', 'Tiềm năng');

INSERT IGNORE INTO customer_tag_links (customer_id, tag_id)
SELECT c.id, t.id FROM customers c, customer_tags t
WHERE c.phone = '0901111015' AND t.name IN ('Chờ xử lý');

INSERT IGNORE INTO customer_tag_links (customer_id, tag_id)
SELECT c.id, t.id FROM customers c, customer_tags t
WHERE c.phone = '0901111020' AND t.name IN ('VIP', 'Ưu tiên', 'Thân thiết');

-- ── 5. Thêm sản phẩm ──────────────────────────────────────
INSERT IGNORE INTO products (name, sku, description, price, stock_quantity, status, created_by) VALUES
('Laptop Dell XPS 15',        'LAP-001', N'Laptop cao cấp cho doanh nghiệp',    35000000, 15, 'active', 1),
('iPhone 15 Pro Max 256GB',   'PHO-001', N'Điện thoại cao cấp Apple',           33000000, 20, 'active', 1),
('Samsung Galaxy S24 Ultra',  'PHO-002', N'Điện thoại Android cao cấp',         30000000, 18, 'active', 1),
('MacBook Pro M3 14"',        'LAP-002', N'Laptop Apple cho sáng tạo',          52000000, 8,  'active', 1),
('iPad Pro 12.9 M2',          'TAB-001', N'Máy tính bảng chuyên nghiệp',        28000000, 12, 'active', 1),
('AirPods Pro 2nd Gen',       'EAR-001', N'Tai nghe không dây cao cấp',          6500000, 50, 'active', 1),
('Apple Watch Series 9',      'WAT-001', N'Đồng hồ thông minh',                 12000000, 25, 'active', 1),
('Chuột Logitech MX Master 3','ACC-001', N'Chuột không dây cao cấp',              2200000, 30, 'active', 1),
('Bàn phím Keychron K8',      'ACC-002', N'Bàn phím cơ không dây',               2800000, 20, 'active', 1),
('Màn hình LG 27" 4K',        'MON-001', N'Màn hình 4K chuyên đồ hoạ',          15000000, 10, 'active', 1),
('Ổ cứng SSD Samsung 1TB',    'STO-001', N'Ổ cứng di động tốc độ cao',           2500000, 40, 'active', 1),
('Router WiFi 6 Asus',        'NET-001', N'Bộ phát WiFi thế hệ mới',             3500000, 15, 'active', 1);

-- ── 6. Thêm đơn hàng với nhiều trạng thái ────────────────
-- Lấy customer_id qua subquery
INSERT INTO orders (customer_id, employee_id, order_code, total_amount, status, note) VALUES
((SELECT id FROM customers WHERE phone='0901111001'), 2, 'ORD-2024-001', 35000000, 'completed', N'Giao hàng thành công'),
((SELECT id FROM customers WHERE phone='0901111001'), 2, 'ORD-2024-002', 6500000,  'completed', N''),
((SELECT id FROM customers WHERE phone='0901111002'), 2, 'ORD-2024-003', 52000000, 'completed', N'Mua cho công ty'),
((SELECT id FROM customers WHERE phone='0901111002'), 2, 'ORD-2024-004', 30000000, 'shipping',  N'Đang giao'),
((SELECT id FROM customers WHERE phone='0901111003'), 3, 'ORD-2024-005', 28000000, 'completed', N''),
((SELECT id FROM customers WHERE phone='0901111004'), 2, 'ORD-2024-006', 33000000, 'confirmed', N''),
((SELECT id FROM customers WHERE phone='0901111005'), 3, 'ORD-2024-007', 12000000, 'completed', N''),
((SELECT id FROM customers WHERE phone='0901111006'), 2, 'ORD-2024-008', 15000000, 'pending',   N'Chờ xác nhận'),
((SELECT id FROM customers WHERE phone='0901111007'), 3, 'ORD-2024-009', 2800000,  'completed', N''),
((SELECT id FROM customers WHERE phone='0901111008'), 2, 'ORD-2024-010', 2500000,  'cancelled', N'KH hủy'),
((SELECT id FROM customers WHERE phone='0901111009'), 3, 'ORD-2024-011', 6500000,  'completed', N''),
((SELECT id FROM customers WHERE phone='0901111010'), 2, 'ORD-2024-012', 3500000,  'pending',   N''),
((SELECT id FROM customers WHERE phone='0901111017'), 2, 'ORD-2024-013', 2200000,  'completed', N''),
((SELECT id FROM customers WHERE phone='0901111020'), 2, 'ORD-2024-014', 52000000, 'completed', N'Đơn lớn tháng 11'),
((SELECT id FROM customers WHERE phone='0901111020'), 2, 'ORD-2024-015', 33000000, 'shipping',  N'');

-- Order items
INSERT INTO order_items (order_id, product_id, quantity, price, subtotal)
SELECT o.id, p.id, 1, p.price, p.price
FROM orders o, products p
WHERE o.order_code='ORD-2024-001' AND p.sku='LAP-001';

INSERT INTO order_items (order_id, product_id, quantity, price, subtotal)
SELECT o.id, p.id, 1, p.price, p.price
FROM orders o, products p
WHERE o.order_code='ORD-2024-002' AND p.sku='EAR-001';

INSERT INTO order_items (order_id, product_id, quantity, price, subtotal)
SELECT o.id, p.id, 1, p.price, p.price
FROM orders o, products p
WHERE o.order_code='ORD-2024-003' AND p.sku='LAP-002';

INSERT INTO order_items (order_id, product_id, quantity, price, subtotal)
SELECT o.id, p.id, 1, p.price, p.price
FROM orders o, products p
WHERE o.order_code='ORD-2024-004' AND p.sku='PHO-002';

INSERT INTO order_items (order_id, product_id, quantity, price, subtotal)
SELECT o.id, p.id, 1, p.price, p.price
FROM orders o, products p
WHERE o.order_code='ORD-2024-005' AND p.sku='TAB-001';

INSERT INTO order_items (order_id, product_id, quantity, price, subtotal)
SELECT o.id, p.id, 1, p.price, p.price
FROM orders o, products p
WHERE o.order_code='ORD-2024-014' AND p.sku='LAP-002';

-- ── 7. Cập nhật total_spent cho khách hàng ───────────────
UPDATE customers c SET total_spent = (
    SELECT COALESCE(SUM(total_amount), 0)
    FROM orders
    WHERE customer_id = c.id AND status IN ('completed','shipping')
);

-- ── 8. Thêm hoạt động (timeline) ─────────────────────────
INSERT INTO customer_activities (customer_id, employee_id, type, title, content, activity_date) VALUES
((SELECT id FROM customers WHERE phone='0901111001'), 2, 'call',    N'Gọi điện tư vấn sản phẩm mới',        N'KH quan tâm đến MacBook Pro M3, hẹn gặp tuần sau',       NOW() - INTERVAL 5 DAY),
((SELECT id FROM customers WHERE phone='0901111001'), 2, 'meeting', N'Gặp mặt tại showroom',                  N'Đã demo sản phẩm, KH rất hài lòng, sẽ đặt hàng sớm',    NOW() - INTERVAL 2 DAY),
((SELECT id FROM customers WHERE phone='0901111001'), 2, 'note',    N'Ghi chú: KH thích màu Space Gray',       NULL,                                                       NOW() - INTERVAL 1 DAY),
((SELECT id FROM customers WHERE phone='0901111002'), 2, 'email',   N'Gửi báo giá đơn hàng tháng 12',         N'Đã gửi file PDF, chờ phản hồi',                           NOW() - INTERVAL 3 DAY),
((SELECT id FROM customers WHERE phone='0901111002'), 2, 'call',    N'Xác nhận đơn hàng ORD-2024-004',        N'KH đồng ý, yêu cầu giao trước 20/12',                    NOW() - INTERVAL 1 DAY),
((SELECT id FROM customers WHERE phone='0901111003'), 3, 'call',    N'Chăm sóc sau mua hàng',                  N'KH hài lòng với iPad Pro, không có phàn nàn',             NOW() - INTERVAL 7 DAY),
((SELECT id FROM customers WHERE phone='0901111006'), 2, 'call',    N'KH gọi hỏi về chính sách đổi trả',      N'Giải thích chính sách, KH có vẻ chưa hài lòng lắm',      NOW() - INTERVAL 4 DAY),
((SELECT id FROM customers WHERE phone='0901111020'), 2, 'meeting', N'Họp bàn hợp đồng đại lý Q1/2025',       N'Thống nhất mua 5 MacBook và 10 iPhone mỗi tháng',        NOW() - INTERVAL 10 DAY),
((SELECT id FROM customers WHERE phone='0901111020'), 2, 'email',   N'Gửi hợp đồng đại lý',                   N'Đã ký, hiệu lực từ 01/01/2025',                           NOW() - INTERVAL 5 DAY);

-- ── 9. Lịch nhắc việc ────────────────────────────────────
INSERT INTO customer_tasks (customer_id, employee_id, title, description, due_date, priority, status) VALUES
((SELECT id FROM customers WHERE phone='0901111001'), 2, N'Gọi điện xác nhận đặt hàng MacBook',    N'KH đã hẹn sẽ quyết định sau 5 ngày',         NOW() + INTERVAL 1 DAY,  'high',   'pending'),
((SELECT id FROM customers WHERE phone='0901111002'), 2, N'Theo dõi đơn hàng ORD-2024-004',        N'Đảm bảo giao hàng đúng hẹn trước 20/12',      NOW() + INTERVAL 3 DAY,  'high',   'pending'),
((SELECT id FROM customers WHERE phone='0901111003'), 3, N'Gửi email chương trình ưu đãi tháng 12', NULL,                                            NOW() + INTERVAL 2 DAY,  'normal', 'pending'),
((SELECT id FROM customers WHERE phone='0901111005'), 3, N'Chúc mừng sinh nhật KH tháng 9',        N'Tặng voucher 5% và thiệp sinh nhật',           NOW() + INTERVAL 5 DAY,  'normal', 'pending'),
((SELECT id FROM customers WHERE phone='0901111006'), 2, N'Gọi lại giải thích chính sách đổi trả', N'KH đang băn khoăn về đơn hàng tháng trước',   NOW() + INTERVAL 1 DAY,  'high',   'pending'),
((SELECT id FROM customers WHERE phone='0901111010'), 2, N'Tư vấn nâng cấp từ AirPods sang Apple Watch', NULL,                                       NOW() + INTERVAL 7 DAY,  'low',    'pending'),
((SELECT id FROM customers WHERE phone='0901111015'), 3, N'Liên hệ lại KH đã lâu không mua',       N'KH inactive, cần kích hoạt lại',              NOW() + INTERVAL 2 DAY,  'normal', 'pending'),
((SELECT id FROM customers WHERE phone='0901111020'), 2, N'Họp đánh giá hợp đồng Q1/2025',         N'Review doanh số tháng 1',                      NOW() + INTERVAL 14 DAY, 'high',   'pending');

-- ── 10. Hội thoại CSKH ───────────────────────────────────
INSERT INTO conversations (customer_id, employee_id, title, type, status) VALUES
((SELECT id FROM customers WHERE phone='0901111001'), 2, N'Tư vấn MacBook Pro M3',              'product_consulting',  'open'),
((SELECT id FROM customers WHERE phone='0901111002'), 2, N'Hỗ trợ đơn hàng ORD-2024-004',      'general_support',     'open'),
((SELECT id FROM customers WHERE phone='0901111006'), 2, N'Phàn nàn về chính sách đổi trả',    'return_request',      'open'),
((SELECT id FROM customers WHERE phone='0901111003'), 3, N'Hỗ trợ sau mua hàng iPad Pro',      'general_support',     'closed'),
((SELECT id FROM customers WHERE phone='0901111008'), 2, N'Yêu cầu hủy đơn ORD-2024-010',      'return_request',      'closed');

-- Thêm tin nhắn
INSERT INTO messages (conversation_id, sender_type, sender_employee_id, message, is_read) VALUES
((SELECT id FROM conversations WHERE title=N'Tư vấn MacBook Pro M3'), 'employee', 2, N'Chào anh/chị! Em có thể tư vấn thêm về MacBook Pro M3 không ạ?', 1),
((SELECT id FROM conversations WHERE title=N'Tư vấn MacBook Pro M3'), 'customer', NULL, N'Ừ, cho mình hỏi về dung lượng RAM và SSD có thể nâng cấp không?', 1),
((SELECT id FROM conversations WHERE title=N'Tư vấn MacBook Pro M3'), 'employee', 2, N'MacBook Pro M3 có thể chọn RAM từ 18GB đến 36GB và SSD từ 512GB đến 4TB tùy cấu hình ạ.', 0),
((SELECT id FROM conversations WHERE title=N'Hỗ trợ đơn hàng ORD-2024-004'), 'employee', 2, N'Đơn hàng của anh đang trên đường giao, dự kiến 2-3 ngày nữa ạ.', 1),
((SELECT id FROM conversations WHERE title=N'Hỗ trợ đơn hàng ORD-2024-004'), 'customer', NULL, N'Ok em, nhớ gọi trước khi giao nhé!', 1),
((SELECT id FROM conversations WHERE title=N'Phàn nàn về chính sách đổi trả'), 'customer', NULL, N'Tại sao đơn hàng tháng trước của mình không được đổi trả?', 0),
((SELECT id FROM conversations WHERE title=N'Phàn nàn về chính sách đổi trả'), 'employee', 2, N'Anh ơi, chính sách đổi trả của bên em là 30 ngày từ ngày mua. Đơn của anh đã qua 35 ngày rồi ạ.', 0);

-- ── 11. Yêu cầu đổi/trả ──────────────────────────────────
INSERT INTO return_exchange_requests (customer_id, order_id, request_type, reason, status, employee_id) VALUES
((SELECT id FROM customers WHERE phone='0901111001'),
 (SELECT id FROM orders WHERE order_code='ORD-2024-001'),
 'exchange', N'Muốn đổi sang màu Silver thay vì Space Gray', 'approved', 2),

((SELECT id FROM customers WHERE phone='0901111008'),
 (SELECT id FROM orders WHERE order_code='ORD-2024-010'),
 'return', N'Không còn nhu cầu sử dụng', 'completed', 2),

((SELECT id FROM customers WHERE phone='0901111003'),
 (SELECT id FROM orders WHERE order_code='ORD-2024-005'),
 'exchange', N'iPad bị lỗi màn hình sau 2 tuần sử dụng', 'pending', 3);

SELECT CONCAT('Seed data hoàn tất! Đã thêm ',
    (SELECT COUNT(*) FROM customers WHERE phone LIKE '090111100%'), ' khách hàng mẫu') AS result;
