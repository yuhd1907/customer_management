/**
 * ================================================================
 * api.js — HTTP Client (Lớp giao tiếp với Backend API)
 * ================================================================
 * File này đóng vai trò là "cầu nối" duy nhất giữa giao diện (frontend)
 * và máy chủ (backend PHP). Mọi request lấy/gửi dữ liệu đều đi qua đây.
 *
 * Cách dùng:
 *   api.get('/customers', { page: 1, search: 'Lan' })
 *   api.post('/customers', { full_name: 'Lan', phone: '0909...' })
 *   api.put('/customers/5', { status: 'inactive' })
 *   api.delete('/customers/5')
 */

/** URL gốc của toàn bộ API. Nếu đổi tên thư mục dự án, sửa ở đây. */
const API_BASE = '/customer_management/api';

const api = {
  BASE: API_BASE,

  /**
   * Lấy JWT token đang được lưu trong localStorage của trình duyệt.
   * Token này được tạo lúc đăng nhập và gửi kèm mọi request để server
   * xác thực người dùng là ai.
   * @returns {string|null} Chuỗi token hoặc null nếu chưa đăng nhập.
   */
  token: () => localStorage.getItem('crm_token'),

  /**
   * Hàm gửi HTTP request lõi — tất cả các method bên dưới đều gọi hàm này.
   *
   * @param {string} method  - Phương thức HTTP: 'GET', 'POST', 'PUT', 'PATCH', 'DELETE'
   * @param {string} path    - Đường dẫn API, VD: '/customers', '/orders/5'
   * @param {object} body    - Dữ liệu gửi lên (chỉ dùng cho POST/PUT/PATCH)
   * @param {object} params  - Tham số lọc trên URL (query string), VD: { page:1, search:'Lan' }
   * @returns {Promise<object>} Kết quả JSON trả về từ server
   * @throws Ném lỗi nếu server trả về HTTP 4xx hoặc 5xx
   */
  async req(method, path, body, params) {
    // --- Bước 1: Xây dựng URL đầy đủ ---
    let url = API_BASE + path;

    // Nếu có params lọc (VD: search, page, status), ghép vào URL dưới dạng query string
    // Bỏ qua các giá trị rỗng ('') hoặc null để URL gọn hơn
    if (params) {
      const q = new URLSearchParams(
        Object.fromEntries(Object.entries(params).filter(([,v]) => v !== '' && v != null))
      );
      if ([...q].length) url += '?' + q;
    }

    // --- Bước 2: Thiết lập Headers ---
    const headers = { 'Content-Type': 'application/json' };

    // Nếu đã đăng nhập, đính kèm JWT token vào header Authorization
    // Server PHP sẽ đọc header này để biết ai đang gọi API
    if (this.token()) headers['Authorization'] = 'Bearer ' + this.token();

    // --- Bước 3: Cấu hình request ---
    const opts = { method, headers };

    // Chỉ gửi body (dữ liệu JSON) nếu method là POST/PUT/PATCH
    // GET và DELETE không có body
    if (body && !['GET', 'DELETE'].includes(method)) opts.body = JSON.stringify(body);

    // --- Bước 4: Gửi request và xử lý phản hồi ---
    const res  = await fetch(url, opts);  // Gửi request lên server
    const data = await res.json();        // Phân tích JSON từ server trả về

    // Xử lý lỗi 401 (Unauthorized): Token hết hạn hoặc không hợp lệ
    // → Xóa token cũ, tải lại trang để hiện màn hình đăng nhập
    if (res.status === 401) {
      localStorage.removeItem('crm_token');
      window.location.href = '/customer_management/index.html';
      throw { status: 401, message: 'Phiên đăng nhập hết hạn' };
    }

    // Nếu server báo lỗi khác (400, 403, 404, 500...), ném lỗi để
    // nơi gọi (try/catch) có thể bắt và hiển thị thông báo
    if (!res.ok) throw { status: res.status, message: data.message || 'Lỗi không xác định' };

    return data; // Trả về dữ liệu thành công
  },

  // ── Các phương thức gọi nhanh ──────────────────────────────────
  /** Lấy dữ liệu từ server (có thể kèm bộ lọc qua params) */
  get:    (path, params) => api.req('GET',    path, null, params),

  /** Tạo mới một bản ghi trên server */
  post:   (path, body)   => api.req('POST',   path, body),

  /** Cập nhật toàn bộ thông tin một bản ghi */
  put:    (path, body)   => api.req('PUT',    path, body),

  /** Cập nhật một phần thông tin (VD: chỉ đổi trạng thái) */
  patch:  (path, body)   => api.req('PATCH',  path, body),

  /** Xóa một bản ghi khỏi server */
  delete: (path)         => api.req('DELETE', path),
};
