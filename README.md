# Hướng Dẫn Chi Tiết Hệ Thống CRM

Tài liệu này giải thích cực kỳ chi tiết về **Luồng hoạt động Backend**, **Giao diện người dùng (Các nút bấm trỏ đến đâu)** và **Giải thích Code** cho toàn bộ các chức năng trong hệ thống CRM của bạn.

---

## 1. Chức năng Đăng Nhập & Phân Quyền (Auth)

### 📌 Giao diện (Frontend)
- **File giao diện:** `app/pages/templates/login.html`
- **File xử lý sự kiện:** `app/pages/auth.js`
- **Các nút bấm & Hành động:**
  - Nút **"Đăng nhập"** (`<button type="submit">`): Kích hoạt sự kiện `onsubmit` trong form. Frontend lấy `email` và `password`, sau đó gọi API `POST /api/auth/login`.

### ⚙️ Luồng Backend & Giải thích Code
- **File Controller:** `controllers/AuthController.php`
- **Hàm `login()`:**
  1. **Nhận dữ liệu:** Backend dùng `getJsonBody()` để lấy email và password.
  2. **Tìm nhân viên:** Dùng PDO truy vấn `SELECT * FROM employees WHERE email = ? AND deleted_at IS NULL AND status = 'active'`. Nếu không tìm thấy, báo lỗi 401.
  3. **Kiểm tra mật khẩu:** Dùng `password_verify($password, $emp['password_hash'])` để so khớp mật khẩu mã hóa.
  4. **Tạo JWT Token:**
     - Mã hóa base64 Header và Payload (chứa thông tin `id`, `email`, `role` của user).
     - Tạo Signature bằng hàm `hash_hmac('sha256')` với một khóa bí mật (Secret Key) quy định trong hệ thống.
  5. **Trả về Client:** Trả về JSON chứa `token` và đối tượng `user`. Frontend sẽ lưu `token` này vào `localStorage.getItem('crm_token')` để gắn vào Header ở các request tiếp theo.

---

## 2. Quản Lý Khách Hàng (Customers)

### 📌 Giao diện (Frontend)
- **File giao diện:** `app/pages/templates/customers.html`
- **File xử lý sự kiện:** `app/pages/customers.js`
- **Các nút bấm & Hành động:**
  - **Nút "+ Thêm khách hàng":** Mở Modal `#modal-customer`. Khi lưu, frontend gọi API `POST /api/customers`.
  - **Nút "Sửa" (Icon bút):** Gọi `Customers.edit(id)`. Frontend lấy dữ liệu hiện tại, điền vào modal, khi lưu gọi `PUT /api/customers/{id}`.
  - **Nút "Xóa" (Icon thùng rác):** Gọi `Customers.delete(id)`. Frontend hiển thị hộp thoại xác nhận, sau đó gọi `DELETE /api/customers/{id}`.
  - **Nút "Xuất Excel":** Gọi trực tiếp URL backend `/api/customers/export` qua thẻ `window.open` để tải file CSV.

### ⚙️ Luồng Backend & Giải thích Code
- **File Controller:** `controllers/CustomerController.php`
- **Lọc và Phân trang (Hàm `index`):**
  - Nhận các query params như `?search=...&status=...&page=...`.
  - Chạy `SELECT COUNT(*)` để đếm tổng số KH.
  - Chạy `SELECT c.*, e.full_name as assigned_employee_name FROM customers c LEFT JOIN ... LIMIT ? OFFSET ?`. JOIN bảng `employees` để lấy ra tên nhân viên phụ trách.
- **Tạo mới (Hàm `store`):**
  - Validate số điện thoại: `SELECT id FROM customers WHERE phone = ?`. Nếu có báo lỗi (Mỗi KH 1 số điện thoại).
  - Nếu `assigned_employee_id` trống, tự động gán khách hàng này cho nhân viên đang đăng nhập.
  - Sau khi `INSERT`, hệ thống tự động gọi hàm `log()` ghi vào bảng `activity_logs` để kiểm tra ai đã thao tác.
- **Xóa mềm (Hàm `destroy`):**
  - **Code:** `UPDATE customers SET deleted_at = NOW() WHERE id = ?`.
  - **Lý do:** KHÔNG xóa thật (DELETE FROM) vì nếu xóa thật sẽ làm hỏng các bảng Đơn hàng (`orders`) hay Hội thoại (`conversations`) đang liên kết với khách hàng này qua Khóa ngoại (Foreign Key).

---

## 3. Quản Lý Đơn Hàng (Orders)

### 📌 Giao diện (Frontend)
- **File giao diện:** `app/pages/templates/orders.html`
- **File xử lý sự kiện:** `app/pages/orders.js`
- **Các nút bấm & Hành động:**
  - **Nút "+ Tạo đơn hàng":** Mở Modal `#modal-order`.
  - **Nút "+ Thêm SP" (Trong form tạo đơn):** Mở dropdown chọn sản phẩm, frontend tính tiền động: `Số lượng * Đơn giá`.
  - **Nút "Lưu đơn hàng":** Lấy toàn bộ danh sách sản phẩm và thông tin KH, gọi API `POST /api/orders`.
  - **Dropdown Trạng thái (Pending, Shipping...):** Khi chọn, gọi `PATCH /api/orders/{id}/status` để cập nhật trạng thái đơn.

### ⚙️ Luồng Backend & Giải thích Code
- **File Controller:** `controllers/OrderController.php`
- **Tạo đơn hàng (Hàm `store`):**
  - Đây là phần **phức tạp nhất** vì phải dùng **DB Transaction** (`$this->pdo->beginTransaction()`).
  - **Bước 1:** Tạo mã đơn hàng tự động (VD: `ORD-2024-0001`).
  - **Bước 2:** Lặp qua từng sản phẩm (`$body['items']`), check kho:
    - Code: `SELECT * FROM products WHERE id = ?`. Lấy `price` từ DB (không tin giá từ frontend gửi lên để tránh gian lận).
    - So sánh: `if ($product['stock_quantity'] < $item['quantity']) error('Hết hàng')`.
  - **Bước 3:** Insert bảng `orders` (Lưu thông tin KH, tổng tiền).
  - **Bước 4:** Lặp qua từng sản phẩm, Insert vào `order_items`.
  - **Bước 5 (Trừ kho):** Update lại bảng `products`: `UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?`.
  - **Bước 6:** Hoàn tất gọi `$this->pdo->commit()`. Nếu bất kỳ bước nào lỗi (như lỗi mạng, hết kho giữa chừng), hệ thống gọi `$this->pdo->rollBack()` để hủy toàn bộ quá trình, đảm bảo dữ liệu không bị sai lệch.

---

## 4. Quản Lý Sản Phẩm (Products)

### 📌 Giao diện (Frontend)
- **File giao diện:** `app/pages/templates/products.html`
- **File xử lý sự kiện:** `app/pages/others.js` (Object `Products`)
- **Các nút bấm & Hành động:**
  - **Nút "+ Thêm sản phẩm":** Mở form nhập Tên, SKU (Mã vạch), Giá, Tồn kho.
  - **Toggle trạng thái:** Chuyển đổi trạng thái Đang bán (active) và Ngừng bán (inactive).

### ⚙️ Luồng Backend & Giải thích Code
- **File Controller:** `controllers/ProductController.php`
- Backend cung cấp các API CRUD tiêu chuẩn. Khi cập nhật (`PUT /api/products`), backend kiểm tra xem mã `SKU` đã bị trùng với sản phẩm khác chưa.
- **Code Validate SKU:** `SELECT id FROM products WHERE sku = ? AND id != ?`. Đảm bảo mã sản phẩm trong kho là độc nhất.

---

## 5. Quản Lý Đổi Trả (Return & Exchange)

### 📌 Giao diện (Frontend)
- **File giao diện:** `app/pages/templates/returns.html`
- **File xử lý sự kiện:** `app/pages/returns.js`
- **Các nút bấm & Hành động:**
  - **Nút "Tạo yêu cầu đổi trả":** Cần nhập ID Đơn hàng (`order_id`) và chọn loại (Hoàn tiền `return` hoặc Đổi hàng `exchange`).
  - **Duyệt/Từ chối:** Cập nhật trạng thái đổi trả thành `approved` hoặc `rejected`.

### ⚙️ Luồng Backend & Giải thích Code
- **File Controller:** `controllers/ReturnExchangeController.php`
- Khi tạo đổi trả (`POST /api/returns`), backend check xem đơn hàng có thật không: `SELECT * FROM orders WHERE id = ?`.
- Nếu trạng thái đổi trả được cập nhật sang `approved` (Đã duyệt), backend sẽ có logic (hoặc có thể mở rộng) để hoàn lại số lượng sản phẩm vào kho: `UPDATE products SET stock_quantity = stock_quantity + ?`.

---

## 6. Hội Thoại / Chăm Sóc Khách Hàng (Conversations)

### 📌 Giao diện (Frontend)
- **File giao diện:** `app/pages/templates/conversations.html`
- **File xử lý sự kiện:** `app/pages/others.js` (Object `Conversations`)
- **Các nút bấm & Hành động:**
  - Tương tự như chức năng quản lý khách hàng, hiển thị danh sách các "Ticket" (Yêu cầu hỗ trợ) từ khách hàng.
  - Nút **"Đóng ticket"**: Cập nhật trạng thái từ `open` sang `closed`.

### ⚙️ Luồng Backend & Giải thích Code
- **File Controller:** `controllers/ConversationController.php` và `MessageController.php`.
- Một `Conversation` đại diện cho 1 phiên hỗ trợ (1 Ticket).
- Bên trong 1 `Conversation` có nhiều `Messages` (Bảng tin nhắn).
- API `GET /api/conversations/{id}/messages` sẽ JOIN với bảng `employees` và `customers` để phân biệt tin nhắn nào của khách hàng gửi, tin nhắn nào do nhân viên trả lời.

---

## 7. Thống Kê Báo Cáo (Dashboard & Thống Kê)

### 📌 Giao diện (Frontend)
- **File giao diện:** `app/pages/templates/dashboard.html`
- **File xử lý sự kiện:** `app/pages/dashboard.js`
- **Hiển thị:** Các thẻ hiển thị Tổng KH, Doanh thu, Đơn hàng và biểu đồ (sử dụng thư viện Chart.js).

### ⚙️ Luồng Backend & Giải thích Code
- **File Controller:** `controllers/StatisticsController.php`
- Backend có các hàm truy vấn phức tạp để tính toán số liệu.
- **Tổng doanh thu:** `SELECT SUM(total_amount) FROM orders WHERE status = 'completed' AND DATE(created_at) = CURDATE()` (Lấy doanh thu các đơn hàng đã hoàn thành trong ngày).
- **Thống kê theo ngày (Biểu đồ):** 
  ```sql
  SELECT DATE(created_at) as date, SUM(total_amount) as revenue
  FROM orders 
  WHERE status = 'completed' 
  GROUP BY DATE(created_at)
  ```
  Truy vấn này nhóm (GROUP BY) các đơn hàng theo ngày để vẽ lên biểu đồ cột hoặc đường ở màn hình Dashboard.

---

## Tổng kết Về Kiến Trúc

- **Bảo mật (Security):** Mọi Controller đều gọi `requireAuth()` (Kiểm tra Token JWT). Với thao tác Xóa/Cấp quyền, Controller gọi `requireAdmin()` (Check role của người dùng).
- **Kiến trúc RESTful API:** 
  - `GET` để lấy dữ liệu.
  - `POST` để tạo mới.
  - `PUT/PATCH` để cập nhật.
  - `DELETE` để xóa.
- **Frontend SPA (Single Page Application):** Thay vì tải lại toàn bộ trang web (F5), hàm `loadTemplate('tên_trang')` tải HTML thô và chèn vào thẻ `div#pageContent`. Dữ liệu (JSON) được lấy bằng hàm `api.get()` và dùng Javascript (Template literal `${}`) để vẽ (render) thành các hàng trong bảng (`<tr>`).
