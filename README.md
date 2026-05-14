# AZ CRM — Hệ thống Quản lý Khách hàng

Ứng dụng quản lý khách hàng nội bộ xây dựng bằng **PHP thuần + MySQL** (backend REST API) và **HTML/CSS/JavaScript** (frontend SPA), chạy trên môi trường Laragon.

---

## Mục lục

1. [Cài đặt](#cài-đặt)
2. [Phân quyền người dùng](#phân-quyền-người-dùng)
3. [Chức năng chi tiết](#chức-năng-chi-tiết)
   - [Xác thực](#1-xác-thực)
   - [Quản lý Nhân viên](#2-quản-lý-nhân-viên-admin)
   - [Quản lý Khách hàng](#3-quản-lý-khách-hàng)
   - [Hạng Khách hàng](#4-hạng-khách-hàng-customer-tiers)
   - [Nhóm Khách hàng](#5-nhóm-khách-hàng-customer-groups)
   - [Hoạt động & Ghi chú](#6-hoạt-động--ghi-chú-khách-hàng)
   - [Lịch nhắc việc](#7-lịch-nhắc-việc-tasks)
   - [Sinh nhật khách hàng](#8-sinh-nhật-khách-hàng)
   - [Sản phẩm](#9-quản-lý-sản-phẩm)
   - [Đơn hàng](#10-quản-lý-đơn-hàng)
   - [Đổi / Hoàn trả](#11-đổi--hoàn-trả-hàng)
   - [Chăm sóc khách hàng (Chat)](#12-chăm-sóc-khách-hàng-chat)
   - [Thống kê & Báo cáo](#13-thống-kê--báo-cáo)
4. [API Endpoints](#api-endpoints)
5. [Cấu trúc Database](#cấu-trúc-database)
6. [Cấu trúc thư mục](#cấu-trúc-thư-mục)

---

## Cài đặt

### Yêu cầu
- [Laragon](https://laragon.org/) (PHP 8.0+, MySQL 8.0+)
- Trình duyệt hiện đại (Chrome, Edge, Firefox)

### Các bước thực hiện

```bash
# 1. Clone/copy project vào thư mục web
C:\laragon\www\customer_management\

# 2. Tạo database và import schema
mysql -u root -p1907 -e "CREATE DATABASE IF NOT EXISTS customer_management;"
Get-Content database\db_structure.sql | mysql -u root -p1907 customer_management

# 3. Import dữ liệu mẫu
Get-Content database\seed_data.sql | mysql -u root -p1907 customer_management

# 4. Khởi động Laragon và truy cập
http://localhost/customer_management/app.html
```

### Tài khoản mặc định

| Username | Password | Vai trò |
|---|---|---|
| `admin` | `admin123` | Quản trị viên |
| `manager1` | `manager123` | Quản lý |
| `staff1` | `staff123` | Nhân viên |

---

## Phân quyền người dùng

Hệ thống có 3 vai trò với quyền truy cập khác nhau:

| Chức năng | Admin | Manager | Staff |
|---|:---:|:---:|:---:|
| Bảng điều khiển (Dashboard) | ✅ | ✅ | ✅ |
| Quản lý Nhân viên | ✅ | ❌ | ❌ |
| Quản lý Khách hàng | ✅ | ✅ | ✅ |
| Duyệt Đổi/Hoàn trả | ✅ | ✅ | ❌ |
| Xem báo cáo thống kê | ✅ | ✅ | ✅ |
| Chăm sóc KH (Chat) | ✅ | ✅ | ✅ |

Token xác thực JWT được lưu trong `localStorage`, hết hạn sau **24 giờ**.

---

## Chức năng chi tiết

### 1. Xác thực

- **Đăng nhập:** Nhập `username` + `password`, nhận JWT token.
- **Ghi nhớ phiên:** Token được lưu, tự động đăng nhập lại khi F5.
- **Đăng xuất:** Xóa token khỏi localStorage và server.
- **Quick fill:** Nút điền nhanh thông tin demo cho Admin/Manager/Staff.

---

### 2. Quản lý Nhân viên *(Admin)*

> Chỉ tài khoản `admin` mới thấy menu này.

**Các thao tác:**
- **Xem danh sách** nhân viên với bộ lọc theo vai trò (Admin/Manager/Staff).
- **Thêm nhân viên:** Nhập họ tên, username, email, SĐT, mật khẩu, vai trò, vị trí công việc.
- **Sửa thông tin:** Cập nhật họ tên, email, SĐT, vai trò, vị trí.
- **Khóa / Mở khóa** tài khoản nhân viên.
- **Đặt lại mật khẩu** mà không cần biết mật khẩu cũ.
- Hiển thị **lần đăng nhập cuối** của từng nhân viên.

---

### 3. Quản lý Khách hàng

**Danh sách & Tìm kiếm:**
- Tìm kiếm theo tên, SĐT, email (real-time debounce 400ms).
- Lọc theo: **Hạng khách hàng**, **Nhóm**, **Nguồn** (Website/Facebook/Zalo/Giới thiệu/Cửa hàng), **Trạng thái**.
- Phân trang 10 bản ghi/trang.

**Thêm / Sửa khách hàng:**
- Thông tin: họ tên, SĐT (duy nhất), email, giới tính, ngày sinh, địa chỉ, nguồn, ghi chú.
- Gán hạng, nhóm, nhân viên phụ trách.

**Panel chi tiết khách hàng** (slide-in từ phải):
- Thông tin cá nhân đầy đủ.
- Tab **Đơn hàng:** lịch sử mua hàng, trạng thái, tổng tiền.
- Tab **Hoạt động:** timeline các tương tác (gọi điện, email, gặp mặt...).
- Tab **Công việc:** các task nhắc nhở liên quan đến KH.
- Tab **Tags:** gắn nhãn phân loại khách hàng.

**Import / Export:**
- **Xuất CSV:** tải toàn bộ danh sách KH ra file CSV.
- **Nhập CSV:** upload file CSV để thêm hàng loạt KH mới.

  Định dạng CSV cần có header: `full_name,phone,email,gender,date_of_birth,address,source,note`

---

### 4. Hạng Khách hàng *(Customer Tiers)*

Phân loại khách hàng theo mức chi tiêu để áp dụng ưu đãi:

| Hạng | Mô tả |
|---|---|
| Đồng | Khách hàng mới, chi tiêu thấp |
| Bạc | Khách hàng thân thiết |
| Vàng | Khách hàng VIP |

**Thao tác:**
- Xem danh sách hạng hiện có.
- **Tạo hạng mới:** đặt tên, mô tả, phần trăm giảm giá (`discount_percent`).
- **Sửa / Xóa** hạng (xóa sẽ gỡ hạng của các KH liên quan).
- Gán hạng thủ công cho từng KH qua trang chi tiết.

---

### 5. Nhóm Khách hàng *(Customer Groups)*

Nhóm KH theo chiến dịch hoặc đặc điểm kinh doanh:

**Thao tác:**
- **Tạo nhóm mới:** đặt tên, mô tả.
- **Xem thành viên** trong nhóm.
- **Thêm / Xóa khách hàng** vào nhóm.
- Lọc danh sách KH theo nhóm.

---

### 6. Hoạt động & Ghi chú Khách hàng

Ghi lại lịch sử tương tác với từng khách hàng:

**Các loại hoạt động:**
- Gọi điện, Email, Gặp mặt, Ghi chú chung.

**Thao tác:**
- Xem timeline hoạt động theo thứ tự thời gian (mới nhất trước).
- **Thêm hoạt động mới:** chọn loại, nội dung, ngày giờ.
- **Xóa** hoạt động không cần thiết.

---

### 7. Lịch nhắc việc *(Tasks)*

Tạo công việc cần thực hiện liên quan đến khách hàng:

**Thao tác:**
- **Tạo task:** tiêu đề, mô tả, deadline, gán cho nhân viên.
- **Đánh dấu hoàn thành** (Done) hoặc **Hủy** task.
- **Xóa** task.
- Menu **"Lịch nhắc việc":** xem tất cả task sắp đến hạn (upcoming) của toàn bộ KH.

---

### 8. Sinh nhật Khách hàng

- Hiển thị danh sách KH có **sinh nhật trong tháng hiện tại**.
- Giúp nhân viên chủ động liên hệ chúc mừng, gửi ưu đãi.

---

### 9. Quản lý Sản phẩm

**Thao tác:**
- Xem danh sách sản phẩm với trạng thái (Đang bán / Ngừng bán).
- **Thêm sản phẩm:** tên, SKU (mã sản phẩm), mô tả, giá bán, tồn kho.
- **Sửa / Xóa** sản phẩm.
- Lọc theo trạng thái.

---

### 10. Quản lý Đơn hàng

**Vòng đời đơn hàng:**
```
pending → confirmed → shipping → completed
                              → cancelled
                              → returned
```

**Thao tác:**
- Xem danh sách đơn với bộ lọc trạng thái, khách hàng.
- **Tạo đơn hàng mới:** chọn KH, thêm sản phẩm + số lượng, ghi chú.
- **Cập nhật trạng thái** đơn hàng.
- **Xem chi tiết** đơn: danh sách sản phẩm, tổng tiền, thông tin KH, nhân viên tạo đơn.
- Xem lịch sử đơn hàng từng KH trong panel chi tiết KH.

---

### 11. Đổi / Hoàn trả hàng

Quy trình xử lý yêu cầu từ khách hàng:

**Trạng thái:**
```
pending → approved → completed
        → rejected
```

**Tạo yêu cầu (tất cả nhân viên):**
1. Chọn khách hàng → hệ thống tải danh sách đơn đã hoàn thành.
2. Chọn đơn hàng cụ thể.
3. Chọn loại: **Hoàn trả hàng** hoặc **Đổi hàng**.
4. Nhập lý do và ghi chú.

**Duyệt / Từ chối (Admin & Manager):**
- Duyệt kèm ghi chú.
- Từ chối với lý do bắt buộc.
- Đánh dấu **Hoàn thành** sau khi xử lý xong.

> Tất cả nhân viên đều thấy danh sách yêu cầu (cả đang chờ lẫn đã xử lý).

---

### 12. Chăm sóc Khách hàng *(Chat)*

Giao diện nhắn tin nội bộ giữa nhân viên và khách hàng:

**Tính năng:**
- **Danh sách cuộc hội thoại** bên trái, hiển thị tên KH, tin nhắn cuối, thời gian.
- **Huy hiệu số chưa đọc** (badge đỏ) trên menu sidebar, tự cập nhật mỗi 30 giây.
- **Gửi tin nhắn** với phím Enter hoặc nút Gửi.
- **Tạo hội thoại mới:** chọn KH, loại hỗ trợ (tư vấn sản phẩm, hoàn trả, đổi hàng, hỗ trợ chung).
- **Đóng hội thoại** khi đã giải quyết xong.
- **Gán nhân viên** phụ trách cho từng hội thoại.
- Các loại hội thoại: `product_consulting`, `return_request`, `exchange_request`, `general_support`.

---

### 13. Thống kê & Báo cáo *(Dashboard)*

Hiển thị tổng quan tình hình kinh doanh:

**KPI Cards:**
| Chỉ số | Nội dung |
|---|---|
| Tổng khách hàng | Tổng KH đang hoạt động |
| Doanh thu | Tổng tiền từ đơn hàng hoàn thành |
| Đơn hàng | Tổng số đơn, đơn đang chờ |
| Hỗ trợ | Số yêu cầu đổi/trả + chat chưa xử lý |

**Biểu đồ:**
- **Cột:** Doanh thu 6 tháng gần nhất (Chart.js).
- **Tròn (Doughnut):** Cơ cấu khách hàng theo hạng (Đồng/Bạc/Vàng...).

**Bảng Top 5:** Khách hàng chi tiêu nhiều nhất với số đơn và tổng chi tiêu.

---

## API Endpoints

Base URL: `http://localhost/customer_management/api`

### Xác thực
| Method | Endpoint | Mô tả |
|---|---|---|
| POST | `/login` | Đăng nhập, nhận JWT |
| GET | `/me` | Lấy thông tin user hiện tại |
| POST | `/logout` | Đăng xuất |

### Nhân viên
| Method | Endpoint | Mô tả |
|---|---|---|
| GET | `/employees` | Danh sách nhân viên |
| POST | `/employees` | Tạo nhân viên mới |
| GET | `/employees/{id}` | Chi tiết nhân viên |
| PUT | `/employees/{id}` | Cập nhật thông tin |
| DELETE | `/employees/{id}` | Xóa nhân viên |
| PATCH | `/employees/{id}/lock` | Khóa tài khoản |
| PATCH | `/employees/{id}/unlock` | Mở khóa tài khoản |
| PATCH | `/employees/{id}/reset-password` | Đặt lại mật khẩu |

### Khách hàng
| Method | Endpoint | Mô tả |
|---|---|---|
| GET | `/customers` | Danh sách (search, filter, phân trang) |
| POST | `/customers` | Tạo KH mới |
| GET | `/customers/{id}` | Chi tiết KH |
| PUT | `/customers/{id}` | Cập nhật KH |
| DELETE | `/customers/{id}` | Xóa KH |
| PATCH | `/customers/{id}/tier` | Cập nhật hạng |
| PATCH | `/customers/{id}/assign` | Gán nhân viên phụ trách |
| GET | `/customers/export` | Xuất CSV |
| POST | `/customers/import` | Nhập từ CSV |
| GET | `/customers/birthdays` | KH sinh nhật tháng này |

### Hạng / Nhóm / Tags
| Method | Endpoint | Mô tả |
|---|---|---|
| GET/POST | `/customer-tiers` | Danh sách & tạo hạng |
| GET/PUT/DELETE | `/customer-tiers/{id}` | Chi tiết, sửa, xóa |
| GET/POST | `/customer-groups` | Danh sách & tạo nhóm |
| POST | `/customer-groups/{id}/add-customer` | Thêm KH vào nhóm |
| POST | `/customer-groups/{id}/remove-customer` | Xóa KH khỏi nhóm |
| GET/POST | `/customer-tags` | Danh sách & tạo tag |
| GET/POST | `/customers/{id}/tags` | Lấy & gán tags cho KH |

### Hoạt động & Tasks
| Method | Endpoint | Mô tả |
|---|---|---|
| GET/POST | `/customers/{id}/activities` | Lịch sử hoạt động |
| DELETE | `/customers/{id}/activities/{id}` | Xóa hoạt động |
| GET/POST | `/customers/{id}/tasks` | Công việc của KH |
| PATCH | `/customers/{id}/tasks/{id}/done` | Đánh dấu hoàn thành |
| PATCH | `/customers/{id}/tasks/{id}/cancel` | Hủy task |
| DELETE | `/customers/{id}/tasks/{id}` | Xóa task |
| GET | `/tasks/upcoming` | Tất cả task sắp đến hạn |

### Sản phẩm & Đơn hàng
| Method | Endpoint | Mô tả |
|---|---|---|
| GET/POST | `/products` | Danh sách & tạo sản phẩm |
| GET/PUT/DELETE | `/products/{id}` | Chi tiết, sửa, xóa |
| GET/POST | `/orders` | Danh sách & tạo đơn hàng |
| GET/PUT/DELETE | `/orders/{id}` | Chi tiết, sửa, xóa |
| PATCH | `/orders/{id}/status` | Cập nhật trạng thái đơn |

### Chat & Đổi/Trả
| Method | Endpoint | Mô tả |
|---|---|---|
| GET/POST | `/conversations` | Danh sách & tạo hội thoại |
| GET/PUT | `/conversations/{id}` | Chi tiết, cập nhật |
| PATCH | `/conversations/{id}/close` | Đóng hội thoại |
| PATCH | `/conversations/{id}/assign` | Gán nhân viên |
| GET/POST | `/conversations/{id}/messages` | Tin nhắn trong hội thoại |
| GET/POST | `/return-exchange-requests` | Danh sách & tạo yêu cầu |
| GET | `/return-exchange-requests/{id}` | Chi tiết yêu cầu |
| PATCH | `/return-exchange-requests/{id}/approve` | Duyệt |
| PATCH | `/return-exchange-requests/{id}/reject` | Từ chối |
| PATCH | `/return-exchange-requests/{id}/complete` | Hoàn thành |

### Thống kê
| Method | Endpoint | Mô tả |
|---|---|---|
| GET | `/statistics/dashboard` | Dữ liệu tổng quan Dashboard |

---

## Cấu trúc Database

### Các bảng chính

| Bảng | Mô tả |
|---|---|
| `employees` | Tài khoản nhân viên (admin/manager/staff) |
| `customers` | Thông tin khách hàng |
| `customer_tiers` | Hạng KH (Đồng/Bạc/Vàng...) |
| `customer_groups` | Nhóm KH |
| `customer_group_members` | Quan hệ KH ↔ Nhóm |
| `customer_tags` | Nhãn/Tags |
| `customer_tag_assignments` | Gán tag cho KH |
| `customer_activities` | Nhật ký hoạt động |
| `customer_tasks` | Công việc/nhắc nhở |
| `products` | Danh mục sản phẩm |
| `orders` | Đơn hàng |
| `order_items` | Chi tiết sản phẩm trong đơn |
| `conversations` | Hội thoại chăm sóc KH |
| `messages` | Tin nhắn |
| `return_exchange_requests` | Yêu cầu đổi/hoàn trả |
| `return_exchange_items` | Sản phẩm trong yêu cầu đổi/trả |
| `activity_logs` | Nhật ký hệ thống |

### Quan hệ chính

```
customers ──── customer_tiers (nhiều KH thuộc 1 hạng)
customers ──── employees (mỗi KH có 1 NV phụ trách)
customers ──── customer_groups (nhiều-nhiều)
orders    ──── customers
orders    ──── order_items ──── products
conversations ──── customers, employees
messages      ──── conversations
return_exchange_requests ──── customers, orders
```

---

## Cấu trúc thư mục

```
customer_management/
├── app/                      # Frontend SPA
│   ├── api.js                # HTTP client (fetch wrapper)
│   ├── ui.js                 # Helpers: modal, toast, pagination
│   ├── main.js               # Router, sidebar, auth flow
│   ├── style.css             # Toàn bộ CSS
│   └── pages/
│       ├── customers.js      # Module Khách hàng
│       ├── customer_extras.js# Nhóm KH, Sinh nhật, Tasks
│       ├── others.js         # Tiers, Products, Orders
│       ├── returns.js        # Đổi/Hoàn trả
│       ├── chat.js           # Module Chat CSKH
│       ├── employees.js      # Quản lý Nhân viên
│       └── dashboard.js      # Thống kê & Báo cáo
├── config/
│   └── database.php          # Kết nối PDO + JWT config
├── controllers/              # Xử lý logic nghiệp vụ
│   ├── AuthController.php
│   ├── CustomerController.php
│   ├── EmployeeController.php
│   ├── OrderController.php
│   ├── ReturnExchangeController.php
│   ├── StatisticsController.php
│   └── ...
├── helpers/
│   ├── jwt.php               # Tạo & xác thực JWT token
│   └── response.php          # Chuẩn hóa JSON response
├── middlewares/
│   ├── auth.php              # Xác thực token
│   └── admin.php             # Kiểm tra quyền admin
├── database/
│   ├── db_structure.sql      # Schema các bảng
│   └── seed_data.sql         # Dữ liệu mẫu
├── public/
│   ├── index.php             # API Router (entry point)
│   └── .htaccess             # Rewrite rule cho public/
├── app.html                  # SPA entry point
├── router.php                # Route tĩnh (HTML/JS/CSS/API)
└── .htaccess                 # Rewrite toàn bộ request
```

---

## Stack công nghệ

| Thành phần | Công nghệ |
|---|---|
| Backend | PHP 8.0+ (không dùng framework) |
| Database | MySQL 8.0 |
| Xác thực | JWT (HS256) |
| Frontend | HTML5, Vanilla CSS, Vanilla JS (SPA) |
| Biểu đồ | Chart.js 4.x (CDN) |
| Font | Inter (Google Fonts) |
| Web server | Laragon (Apache + mod_rewrite) |

---

*Cập nhật lần cuối: 05/2026*
