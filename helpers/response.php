<?php
/**
 * ================================================================
 * helpers/response.php — Chuẩn hóa JSON Response cho toàn bộ API
 * ================================================================
 * Mọi API endpoint đều dùng các hàm này để trả về dữ liệu.
 * Đảm bảo cấu trúc JSON đồng nhất:
 *
 *   {
 *     "success": true|false,
 *     "message": "Thông báo",
 *     "data": { ... }       // Tùy chọn
 *   }
 *
 * Lợi ích: Frontend chỉ cần kiểm tra trường "success" là biết ngay
 * kết quả thành công hay thất bại, không cần đoán HTTP status code.
 */

/**
 * Hàm gốc — Xây dựng và gửi một JSON response về client.
 *
 * Lưu ý: Hàm này gọi exit() ngay sau khi echo, tức là PHP dừng xử lý
 * sau khi gọi hàm này. Đây là thiết kế cố ý để đảm bảo chỉ một response
 * được gửi đi mỗi request.
 *
 * @param bool   $success  true = thành công, false = thất bại
 * @param string $message  Thông báo mô tả kết quả
 * @param mixed  $data     Dữ liệu đi kèm (mảng, object, null)
 * @param int    $code     HTTP Status Code (200, 201, 400, 401, 403, 404, 500...)
 */
function response(bool $success, string $message, $data = null, int $code = 200): void {
    http_response_code($code);  // Đặt HTTP status code cho response
    $body = ['success' => $success, 'message' => $message];

    // Chỉ thêm trường 'data' vào response nếu thực sự có dữ liệu
    // Tránh trả về "data": null khi không cần thiết
    if ($data !== null) $body['data'] = $data;

    // JSON_UNESCAPED_UNICODE: giữ nguyên ký tự Unicode (tiếng Việt), không escape
    // JSON_PRETTY_PRINT: định dạng JSON dễ đọc (dùng cho development; có thể bỏ ở production)
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit; // Dừng toàn bộ PHP execution sau khi đã gửi response
}

/**
 * Gửi response thành công (HTTP 200 hoặc 201).
 *
 * Dùng cho các trường hợp: lấy dữ liệu, tạo mới, cập nhật thành công.
 *
 * Ví dụ:
 *   success($customerData);                        // HTTP 200
 *   success($newCustomer, 'Tạo thành công', 201); // HTTP 201 Created
 *
 * @param mixed  $data     Dữ liệu trả về (object, array, null)
 * @param string $message  Thông báo (mặc định: 'Thành công')
 * @param int    $code     HTTP Status Code (mặc định: 200)
 */
function success($data = null, string $message = 'Thành công', int $code = 200): void {
    response(true, $message, $data, $code);
}

/**
 * Gửi response lỗi (HTTP 400, 401, 403, 404, 422...).
 *
 * Dùng khi: dữ liệu không hợp lệ, không có quyền, không tìm thấy bản ghi...
 *
 * Ví dụ:
 *   error('Không tìm thấy khách hàng.', 404);
 *   error('Số điện thoại đã tồn tại.', 422);
 *   error('Không có quyền.', 403);
 *
 * @param string $message  Mô tả lỗi (hiển thị cho người dùng)
 * @param int    $code     HTTP Status Code (mặc định: 400 Bad Request)
 * @param mixed  $data     Thông tin lỗi chi tiết (tùy chọn)
 */
function error(string $message = 'Lỗi', int $code = 400, $data = null): void {
    response(false, $message, $data, $code);
}

/**
 * Gửi response có phân trang — dùng cho các danh sách dài (customers, orders...).
 *
 * Cấu trúc response trả về:
 *   {
 *     "success": true,
 *     "data": [...],          // Mảng bản ghi của trang hiện tại
 *     "pagination": {
 *       "total":        100,  // Tổng số bản ghi
 *       "per_page":      15,  // Số bản ghi mỗi trang
 *       "current_page":   2,  // Trang hiện tại
 *       "last_page":      7   // Tổng số trang
 *     }
 *   }
 *
 * Frontend dùng thông tin pagination để vẽ nút chuyển trang.
 *
 * @param array  $items     Mảng bản ghi của trang hiện tại
 * @param int    $total     Tổng số bản ghi (COUNT(*) từ SQL)
 * @param int    $page      Trang hiện tại (từ query param ?page=)
 * @param int    $per_page  Số bản ghi mỗi trang
 * @param string $message   Thông báo (mặc định: 'Thành công')
 */
function paginated(array $items, int $total, int $page, int $per_page, string $message = 'Thành công'): void {
    http_response_code(200);
    echo json_encode([
        'success'    => true,
        'message'    => $message,
        'data'       => $items,
        'pagination' => [
            'total'        => $total,
            'per_page'     => $per_page,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $per_page), // Tổng trang = làm tròn lên (total / per_page)
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Đọc và giải mã JSON body từ request body (dùng cho POST/PUT/PATCH).
 *
 * PHP không tự phân tích JSON body như form data, phải đọc thủ công
 * từ luồng đầu vào 'php://input'.
 *
 * Ví dụ: Client gửi { "full_name": "Lan", "phone": "0909" }
 * → Hàm này trả về ['full_name' => 'Lan', 'phone' => '0909']
 *
 * @return array  Mảng PHP từ JSON body, hoặc mảng rỗng [] nếu body trống/không hợp lệ
 */
function getJsonBody(): array {
    $raw = file_get_contents('php://input'); // Đọc toàn bộ request body dưới dạng chuỗi
    return json_decode($raw, true) ?? [];    // Giải mã JSON, trả về [] nếu thất bại
}

/**
 * Lấy các tham số phân trang từ URL query string.
 *
 * Client gửi: GET /api/customers?page=2&per_page=20
 * → Hàm này tính offset = (2-1) * 20 = 20 để dùng trong câu SQL LIMIT/OFFSET
 *
 * Giới hạn an toàn:
 *  - page    >= 1 (không cho phép trang âm)
 *  - per_page: từ 1 đến 100 (tránh query quá lớn làm chậm server)
 *
 * @return array  Mảng gồm: ['page' => int, 'per_page' => int, 'offset' => int]
 */
function getPaginationParams(): array {
    $page     = max(1, (int)($_GET['page'] ?? 1));            // Trang hiện tại, tối thiểu là 1
    $per_page = min(100, max(1, (int)($_GET['per_page'] ?? 15))); // Số dòng/trang, tối đa 100
    $offset   = ($page - 1) * $per_page;                     // Vị trí bắt đầu trong SQL
    return compact('page', 'per_page', 'offset');             // Trả về mảng gọn với compact()
}
