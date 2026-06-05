<?php
/**
 * ================================================================
 * helpers/jwt.php — Tạo & Xác thực JSON Web Token (JWT)
 * ================================================================
 * JWT là cơ chế xác thực "stateless": server không cần lưu session.
 * Thay vào đó, sau khi đăng nhập thành công, server tạo một chuỗi
 * token có chứa thông tin người dùng (id, role) và ký bằng khóa bí mật.
 *
 * Cấu trúc JWT: HEADER.PAYLOAD.SIGNATURE (ngăn cách bởi dấu chấm)
 *   - HEADER:    loại thuật toán (HS256)
 *   - PAYLOAD:   dữ liệu người dùng (id, role, thời gian tạo, hết hạn)
 *   - SIGNATURE: chữ ký HMAC-SHA256 để chống giả mạo
 *
 * Hằng số cần khai báo trong config/database.php:
 *   JWT_SECRET — Khóa bí mật (chuỗi ngẫu nhiên, KHÔNG được lộ)
 *   JWT_EXPIRE  — Thời gian sống tính bằng giây (VD: 86400 = 24 giờ)
 */

/**
 * Tạo một JWT token mới từ dữ liệu payload.
 *
 * Quy trình:
 *  1. Mã hóa Header (thuật toán) → Base64URL
 *  2. Thêm thời gian tạo (iat) và hết hạn (exp) vào Payload
 *  3. Mã hóa Payload → Base64URL
 *  4. Ký chữ ký HMAC-SHA256 với JWT_SECRET
 *  5. Ghép lại: header.payload.signature
 *
 * @param array $payload  Dữ liệu muốn nhúng vào token (VD: ['id'=>1, 'role'=>'admin'])
 * @return string         Chuỗi JWT hoàn chỉnh
 */
function jwtEncode(array $payload): string {
    // Header: chỉ định loại token và thuật toán mã hóa chữ ký
    $header  = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));

    // Gắn thêm thời điểm hết hạn (exp) và thời điểm tạo (iat) vào payload
    $payload['exp'] = time() + JWT_EXPIRE; // VD: hiện tại + 86400 giây
    $payload['iat'] = time();              // Thời điểm token được tạo

    $body    = base64url_encode(json_encode($payload));

    // Tạo chữ ký: ký cặp "header.body" bằng khóa bí mật JWT_SECRET
    // Nếu ai đó sửa payload, chữ ký sẽ không khớp → token bị từ chối
    $sig     = base64url_encode(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));

    return "$header.$body.$sig";
}

/**
 * Xác thực và giải mã một JWT token.
 *
 * Quy trình kiểm tra:
 *  1. Token phải có đúng 3 phần (header.payload.signature)
 *  2. Tính lại chữ ký từ header + payload → so sánh với signature trong token
 *     (dùng hash_equals để chống timing attack)
 *  3. Giải mã payload, kiểm tra thời gian hết hạn (exp)
 *
 * @param string $token  Chuỗi JWT cần kiểm tra
 * @return array|null    Trả về payload nếu hợp lệ, null nếu không hợp lệ/hết hạn
 */
function jwtDecode(string $token): ?array {
    $parts = explode('.', $token);

    // JWT phải gồm đúng 3 phần: header, payload, signature
    if (count($parts) !== 3) return null;

    [$header, $body, $sig] = $parts;

    // Tính lại chữ ký kỳ vọng và so sánh an toàn với chữ ký trong token
    // hash_equals ngăn chặn tấn công "timing attack" (đo thời gian so sánh chuỗi)
    $expected = base64url_encode(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
    if (!hash_equals($expected, $sig)) return null; // Chữ ký sai → token bị giả mạo

    // Giải mã payload từ Base64URL về mảng PHP
    $payload = json_decode(base64url_decode($body), true);

    // Kiểm tra payload hợp lệ và token chưa hết hạn
    if (!$payload || (isset($payload['exp']) && $payload['exp'] < time())) return null;

    return $payload; // Trả về dữ liệu người dùng đã được xác thực
}

/**
 * Mã hóa dữ liệu thành chuỗi Base64URL.
 * Base64URL là biến thể của Base64 an toàn dùng trong URL:
 *  - Thay '+' → '-', '/' → '_' (các ký tự đặc biệt trong URL)
 *  - Xóa ký tự đệm '=' ở cuối
 *
 * @param string $data  Dữ liệu nhị phân cần mã hóa
 * @return string       Chuỗi Base64URL
 */
function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Giải mã chuỗi Base64URL về dữ liệu gốc.
 * Đảo ngược quá trình mã hóa của base64url_encode():
 *  - Thay '-' → '+', '_' → '/'
 *  - Thêm lại '=' đệm cho đúng kích thước bội số 4 trước khi decode
 *
 * @param string $data  Chuỗi Base64URL
 * @return string       Dữ liệu gốc
 */
function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}
