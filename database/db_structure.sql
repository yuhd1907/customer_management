-- =====================================================
-- 1. BẢNG HẠNG KHÁCH HÀNG: ĐỒNG / BẠC / VÀNG
-- =====================================================
CREATE TABLE IF NOT EXISTS customer_tiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    discount_percent DECIMAL(5,2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 2. BẢNG TÀI KHOẢN NHÂN VIÊN / ADMIN
-- =====================================================
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_code VARCHAR(50) UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'staff') DEFAULT 'staff',
    status ENUM('active', 'inactive', 'locked') DEFAULT 'active',
    position VARCHAR(100),
    avatar_url VARCHAR(255),
    last_login_at DATETIME,
    created_by INT NULL,
    updated_by INT NULL,
    deleted_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_employees_created_by FOREIGN KEY (created_by) REFERENCES employees(id) ON DELETE SET NULL,
    CONSTRAINT fk_employees_updated_by FOREIGN KEY (updated_by) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 3. BẢNG KHÁCH HÀNG
-- =====================================================
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tier_id INT NULL,
    assigned_employee_id INT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(100) UNIQUE,
    gender ENUM('male', 'female', 'other') DEFAULT 'other',
    date_of_birth DATE,
    address TEXT,
    status ENUM('active', 'inactive', 'blocked') DEFAULT 'active',
    note TEXT,
    created_by INT NULL,
    updated_by INT NULL,
    deleted_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_customers_tier FOREIGN KEY (tier_id) REFERENCES customer_tiers(id) ON DELETE SET NULL,
    CONSTRAINT fk_customers_assigned_employee FOREIGN KEY (assigned_employee_id) REFERENCES employees(id) ON DELETE SET NULL,
    CONSTRAINT fk_customers_created_by FOREIGN KEY (created_by) REFERENCES employees(id) ON DELETE SET NULL,
    CONSTRAINT fk_customers_updated_by FOREIGN KEY (updated_by) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 4. BẢNG SẢN PHẨM
-- =====================================================
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    sku VARCHAR(50) UNIQUE,
    description TEXT,
    price DECIMAL(12,2) NOT NULL,
    stock_quantity INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_by INT NULL,
    updated_by INT NULL,
    deleted_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_created_by FOREIGN KEY (created_by) REFERENCES employees(id) ON DELETE SET NULL,
    CONSTRAINT fk_products_updated_by FOREIGN KEY (updated_by) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 5. BẢNG ĐƠN HÀNG
-- =====================================================
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    employee_id INT NULL,
    order_code VARCHAR(50) NOT NULL UNIQUE,
    total_amount DECIMAL(12,2) DEFAULT 0,
    status ENUM('pending','confirmed','shipping','completed','cancelled','returned') DEFAULT 'pending',
    note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_orders_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 6. BẢNG CHI TIẾT ĐƠN HÀNG
-- =====================================================
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =====================================================
-- 7. BẢNG CUỘC TRÒ CHUYỆN
-- =====================================================
CREATE TABLE IF NOT EXISTS conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    employee_id INT NULL,
    title VARCHAR(255),
    type ENUM('product_consulting','return_request','exchange_request','general_support') DEFAULT 'general_support',
    status ENUM('open', 'pending', 'closed') DEFAULT 'open',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_conversations_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_conversations_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 8. BẢNG TIN NHẮN
-- =====================================================
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_type ENUM('customer', 'employee') NOT NULL,
    sender_customer_id INT NULL,
    sender_employee_id INT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_messages_conversation FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_sender_customer FOREIGN KEY (sender_customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_sender_employee FOREIGN KEY (sender_employee_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 9. BẢNG YÊU CẦU TRẢ HÀNG / ĐỔI HÀNG
-- =====================================================
CREATE TABLE IF NOT EXISTS return_exchange_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    order_id INT NOT NULL,
    conversation_id INT NULL,
    request_type ENUM('return', 'exchange') NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending','approved','rejected','completed','cancelled') DEFAULT 'pending',
    employee_id INT NULL,
    admin_note TEXT,
    customer_note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_return_exchange_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_return_exchange_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
    CONSTRAINT fk_return_exchange_conversation FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL,
    CONSTRAINT fk_return_exchange_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 10. BẢNG CHI TIẾT SẢN PHẨM TRẢ / ĐỔI
-- =====================================================
CREATE TABLE IF NOT EXISTS return_exchange_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    order_item_id INT NOT NULL,
    quantity INT NOT NULL,
    old_product_id INT NULL,
    new_product_id INT NULL,
    note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_return_exchange_items_request FOREIGN KEY (request_id) REFERENCES return_exchange_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_return_exchange_items_order_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE RESTRICT,
    CONSTRAINT fk_return_exchange_items_old_product FOREIGN KEY (old_product_id) REFERENCES products(id) ON DELETE SET NULL,
    CONSTRAINT fk_return_exchange_items_new_product FOREIGN KEY (new_product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 11. BẢNG NHẬT KÝ HOẠT ĐỘNG
-- =====================================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100),
    record_id INT,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_logs_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 12. INDEX
-- =====================================================
CREATE INDEX idx_customers_tier_id ON customers(tier_id);
CREATE INDEX idx_customers_assigned_employee_id ON customers(assigned_employee_id);
CREATE INDEX idx_customers_phone ON customers(phone);
CREATE INDEX idx_customers_status ON customers(status);
CREATE INDEX idx_employees_role ON employees(role);
CREATE INDEX idx_employees_status ON employees(status);
CREATE INDEX idx_products_status ON products(status);
CREATE INDEX idx_orders_customer_id ON orders(customer_id);
CREATE INDEX idx_orders_employee_id ON orders(employee_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_order_items_order_id ON order_items(order_id);
CREATE INDEX idx_order_items_product_id ON order_items(product_id);
CREATE INDEX idx_conversations_customer_id ON conversations(customer_id);
CREATE INDEX idx_conversations_employee_id ON conversations(employee_id);
CREATE INDEX idx_conversations_status ON conversations(status);
CREATE INDEX idx_messages_conversation_id ON messages(conversation_id);
CREATE INDEX idx_messages_sender_customer_id ON messages(sender_customer_id);
CREATE INDEX idx_messages_sender_employee_id ON messages(sender_employee_id);
CREATE INDEX idx_return_exchange_customer_id ON return_exchange_requests(customer_id);
CREATE INDEX idx_return_exchange_order_id ON return_exchange_requests(order_id);
CREATE INDEX idx_return_exchange_status ON return_exchange_requests(status);
CREATE INDEX idx_activity_logs_employee_id ON activity_logs(employee_id);
