/**
 * ================================================================
 * app/ui.js — Thư viện UI dùng chung cho toàn ứng dụng
 * ================================================================
 * Cung cấp các thành phần UI tái sử dụng:
 *   - Toast: thông báo nổi tự biến mất
 *   - Modal: hộp thoại popup có form
 *   - Pagination: thanh phân trang
 *   - Badge: nhãn màu sắc theo trạng thái
 *   - Các hàm định dạng: ngày, tiền tệ, chữ cái đầu
 *
 * Tất cả các hàm này là global (không dùng module ES6)
 * để mọi page module đều có thể gọi trực tiếp.
 */

// ================================================================
// TOAST — Thông báo nổi góc màn hình
// ================================================================

/** Container #toasts trong HTML — nơi chứa tất cả toast messages */
const toasts = document.getElementById('toasts');

/**
 * Hiển thị thông báo nổi tự biến mất sau 3 giây.
 *
 * @param {string} msg   Nội dung thông báo
 * @param {string} type  Loại thông báo: 'success' | 'error' | 'info' (mặc định)
 *
 * Cách dùng:
 *   toast('Lưu thành công!', 'success');  // Hiển thị 3s rồi tự biến mất
 *   toast('Có lỗi xảy ra.', 'error');
 *
 * Cơ chế biến mất:
 *   - Sau 3000ms: thêm class 'hide' để kích hoạt CSS animation fade-out
 *   - Sau 250ms nữa: xóa element khỏi DOM
 */
function toast(msg, type = 'info') {
  const el = document.createElement('div');
  el.className = `toast ${type}`; // CSS class quyết định màu sắc
  el.textContent = msg;
  toasts.appendChild(el);

  // Chuỗi 2 setTimeout: đợi 3s → fade-out → xóa DOM
  setTimeout(() => {
    el.classList.add('hide');              // Kích hoạt CSS transition ẩn
    setTimeout(() => el.remove(), 250);    // Xóa khỏi DOM sau khi animation xong
  }, 3000);
}

// ================================================================
// MODAL — Hộp thoại popup (dùng Promise để await kết quả)
// ================================================================

/**
 * modalResolve lưu trữ hàm resolve() của Promise đang chờ.
 * Khi người dùng bấm Lưu hoặc Hủy, closeModal() gọi resolve()
 * để Promise trong openModal() được giải quyết.
 */
let modalResolve = null;

// Tham chiếu các element của modal trong HTML (lấy 1 lần, dùng nhiều lần)
const modalOverlay = document.getElementById('modalOverlay'); // Lớp mờ đè lên nền
const modalTitle   = document.getElementById('modalTitle');   // Tiêu đề modal
const modalContent = document.getElementById('modalContent'); // Vùng chứa form
const modalSaveBtn = document.getElementById('modalSave');    // Nút Lưu

/**
 * Mở hộp thoại modal với nội dung HTML tùy ý.
 *
 * @param {string} title      Tiêu đề modal (VD: 'Thêm khách hàng')
 * @param {string} html       HTML của form bên trong
 * @param {string} saveLabel  Nhãn nút xác nhận (mặc định: 'Lưu')
 * @returns {Promise<string|null>}
 *   - Resolve 'save'  khi người dùng bấm nút Lưu
 *   - Resolve null    khi bấm Hủy, nút X, hoặc click ra ngoài
 *
 * Cách dùng:
 *   const result = await openModal('Thêm KH', '<input name="name"/>');
 *   if (result === 'save') {
 *     const data = getFormData(); // { name: '...' }
 *   }
 */
function openModal(title, html, saveLabel = 'Lưu') {
  modalTitle.textContent   = title;
  modalContent.innerHTML   = html;      // Render HTML form vào modal
  modalSaveBtn.textContent = saveLabel;
  modalOverlay.classList.add('open');   // CSS class 'open' làm modal hiện ra

  // Trả về Promise — caller dùng await để chờ kết quả
  return new Promise(resolve => { modalResolve = resolve; });
}

/**
 * Đóng modal và giải quyết Promise đang chờ.
 *
 * @param {string|null} result  Kết quả trả về cho caller của openModal()
 */
function closeModal(result = null) {
  modalOverlay.classList.remove('open'); // Ẩn modal
  if (modalResolve) {
    modalResolve(result);  // Giải quyết Promise → caller tiếp tục chạy
    modalResolve = null;   // Reset để tránh gọi resolve 2 lần
  }
}

// Gắn sự kiện đóng modal cho các nút và overlay
document.getElementById('modalClose').onclick  = () => closeModal();  // Nút X
document.getElementById('modalCancel').onclick = () => closeModal();  // Nút Hủy
modalSaveBtn.onclick = () => closeModal('save');                       // Nút Lưu → trả về 'save'
// Click vào phần mờ bên ngoài modal (overlay) → đóng
modalOverlay.onclick = e => { if (e.target === modalOverlay) closeModal(); };

/**
 * Thu thập dữ liệu tất cả input/select/textarea có thuộc tính [name] trong modal.
 *
 * @param {string} selector  CSS selector của container (mặc định: '#modalContent')
 * @returns {Object}         Object { fieldName: value } — key là giá trị của thuộc tính 'name'
 *
 * Ví dụ HTML:
 *   <input name="full_name" value="Lan" />
 *   <select name="status"><option value="active" selected>...</option></select>
 * → Trả về: { full_name: 'Lan', status: 'active' }
 */
function getFormData(selector = '#modalContent') {
  const data = {};
  // querySelectorAll('[name]') chọn mọi element có thuộc tính name
  document.querySelectorAll(`${selector} [name]`).forEach(el => {
    data[el.name] = el.value.trim(); // trim() bỏ khoảng trắng thừa
  });
  return data;
}

/**
 * Hiển thị hộp thoại xác nhận của trình duyệt.
 *
 * Được bọc trong Promise để dùng với async/await giống openModal().
 * window.confirm() là hàm đồng bộ nên không cần setTimeout.
 *
 * @param {string} msg  Câu hỏi xác nhận
 * @returns {Promise<boolean>}  true nếu bấm OK, false nếu bấm Cancel
 */
function confirmDialog(msg) {
  return new Promise(resolve => resolve(window.confirm(msg)));
}

// ================================================================
// PAGINATION — Thanh phân trang
// ================================================================

/**
 * Vẽ thanh phân trang vào container HTML.
 *
 * Hiển thị: "Hiển thị 1–15 / 100 bản ghi" + các nút số trang
 * Thuật toán smart pagination: hiển thị trang 1, trang cuối, và ±2 trang xung quanh trang hiện tại.
 * Ví dụ 10 trang, đang ở trang 5: [1] ... [3][4][5][6][7] ... [10]
 *
 * @param {HTMLElement} container    Element sẽ chứa HTML phân trang
 * @param {Object}      pagination   Object từ API: { total, per_page, current_page, last_page }
 * @param {Function}    onPageChange Callback khi bấm số trang: (page: number) => void
 *
 * Lưu ý kỹ thuật về onPageChange:
 *   Hàm được nhúng thẳng vào HTML dưới dạng string: onclick="(${onPageChange})(${p})"
 *   Đây là kỹ thuật dùng toString() của function, hoạt động được nhưng có giới hạn:
 *   hàm truyền vào phải KHÔNG có closure (không tham chiếu biến bên ngoài).
 */
function renderPagination(container, pagination, onPageChange) {
  const { total, per_page, current_page, last_page } = pagination;

  // Tính chỉ số bản ghi đầu và cuối của trang hiện tại
  const from = (current_page - 1) * per_page + 1;
  const to   = Math.min(current_page * per_page, total); // Trang cuối có thể ít hơn per_page

  // Nút "Trang trước" — disabled nếu đang ở trang đầu
  let html = `<div class="pagination">
    <span class="pagination-info">Hiển thị ${from}–${to} / ${total} bản ghi</span>
    <button class="page-btn" ${current_page===1?'disabled':''} onclick="(${onPageChange})(${current_page-1})">&#8249;</button>`;

  // Tính danh sách số trang cần hiển thị (với dấu ... cho khoảng bỏ qua)
  let pages = [];
  for (let i = 1; i <= last_page; i++) {
    // Hiển thị: trang đầu (1), trang cuối, và các trang trong vòng ±2 từ trang hiện tại
    if (i===1 || i===last_page || Math.abs(i-current_page)<=2) {
      pages.push(i);
    } else if (pages[pages.length-1] !== '…') {
      pages.push('…'); // Chỉ thêm '...' một lần liên tiếp
    }
  }

  // Render từng trang / dấu "..."
  pages.forEach(p => {
    if (p === '…') {
      // Dấu ... không thể click
      html += `<button class="page-btn" disabled style="border:none;background:none">…</button>`;
    } else {
      // Trang hiện tại có class 'active'; các trang khác có thể click
      html += `<button class="page-btn ${p===current_page?'active':''}" onclick="(${onPageChange})(${p})">${p}</button>`;
    }
  });

  // Nút "Trang sau" — disabled nếu đang ở trang cuối
  html += `<button class="page-btn" ${current_page===last_page?'disabled':''} onclick="(${onPageChange})(${current_page+1})">&#8250;</button></div>`;

  container.innerHTML = html;
}

// ================================================================
// BADGE — Nhãn màu sắc theo trạng thái
// ================================================================

/**
 * Map từ giá trị trạng thái → CSS class của badge.
 *
 * Màu sắc quy ước:
 *   green  = tốt/hoạt động/hoàn thành
 *   gray   = trung tính/đã đóng/không hoạt động
 *   red    = xấu/bị chặn/bị hủy/từ chối
 *   yellow = đang chờ/cần chú ý
 *   blue   = đang xử lý/xác nhận/quản lý
 *   purple = đặc biệt (admin)
 */
const STATUS_CLASS = {
  // Trạng thái tài khoản / khách hàng
  active:    'badge-green',
  inactive:  'badge-gray',
  blocked:   'badge-red',
  locked:    'badge-red',

  // Vai trò nhân viên
  admin:     'badge-purple',
  manager:   'badge-blue',
  staff:     'badge-yellow',

  // Trạng thái đơn hàng
  pending:   'badge-yellow',
  confirmed: 'badge-blue',
  shipping:  'badge-blue',
  completed: 'badge-green',
  cancelled: 'badge-red',
  returned:  'badge-red',

  // Trạng thái hội thoại / yêu cầu đổi trả
  open:      'badge-green',
  closed:    'badge-gray',
  approved:  'badge-green',
  rejected:  'badge-red',
};

/**
 * Tạo HTML cho nhãn (badge) màu sắc.
 *
 * @param {string} val    Giá trị trạng thái (dùng để tra màu từ STATUS_CLASS)
 * @param {string} label  Nhãn hiển thị (nếu bỏ qua, dùng chính val)
 * @returns {string}      HTML string: <span class="badge badge-green">Hoạt động</span>
 *
 * Ví dụ:
 *   badge('active', 'Hoạt động')   → <span class="badge badge-green">Hoạt động</span>
 *   badge('pending', 'Chờ duyệt')  → <span class="badge badge-yellow">Chờ duyệt</span>
 *   badge('unknown')               → <span class="badge badge-gray">unknown</span>
 */
function badge(val, label) {
  // Fallback về 'badge-gray' nếu không tìm thấy trong STATUS_CLASS
  return `<span class="badge ${STATUS_CLASS[val] || 'badge-gray'}">${label || val}</span>`;
}

// ================================================================
// FORMAT HELPERS — Định dạng dữ liệu hiển thị
// ================================================================

/**
 * Định dạng ngày tháng theo tiêu chuẩn Việt Nam (dd/mm/yyyy).
 *
 * @param {string|null} d  Chuỗi ngày giờ ISO (VD: '2024-05-14T10:30:00')
 * @returns {string}       Ngày dạng 'dd/mm/yyyy' hoặc '—' nếu không có giá trị
 */
function fmtDate(d) {
  return d ? new Date(d).toLocaleDateString('vi-VN') : '—';
}

/**
 * Định dạng số tiền Việt Nam với dấu phân cách và ký hiệu tiền tệ.
 *
 * @param {number|string|null} n  Số tiền (VD: 1500000)
 * @returns {string}              Dạng '1.500.000 ₫' hoặc '—' nếu không có giá trị
 */
function fmtMoney(n) {
  return n ? Number(n).toLocaleString('vi-VN') + ' ₫' : '—';
}

/**
 * Lấy ký tự đầu tiên của tên để làm avatar placeholder.
 *
 * Dùng khi không có ảnh đại diện: hiển thị chữ cái đầu trên nền màu.
 *
 * @param {string|null} name  Tên đầy đủ (VD: 'Nguyễn Thị Lan')
 * @returns {string}          Chữ cái đầu viết hoa (VD: 'N'), '?' nếu name rỗng
 */
function initial(name) {
  return (name || '?')[0].toUpperCase();
}

// ================================================================
// TEMPLATE LOADER — Tải HTML template từ file riêng với cache
// ================================================================

/**
 * Cache lưu HTML template đã tải — tránh fetch lại khi chuyển trang qua lại.
 * Key = tên template (VD: 'dashboard'), Value = HTML string.
 */
const _templateCache = {};

/**
 * Tải nội dung HTML từ file template riêng trong app/pages/templates/.
 * Có cơ chế cache: chỉ fetch lần đầu, các lần sau lấy từ bộ nhớ.
 *
 * @param {string} name  Tên template không có đuôi .html (VD: 'dashboard', 'customers')
 * @returns {Promise<string>}  HTML string của template
 *
 * Cách dùng trong page module:
 *   async render() {
 *     const html = await loadTemplate('customers');
 *     document.getElementById('pageContent').innerHTML = html;
 *   }
 */
async function loadTemplate(name) {
  if (!_templateCache[name]) {
    const res = await fetch(`app/pages/templates/${name}.html?v=2.1`);
    if (!res.ok) throw new Error(`Không tải được template: ${name}`);
    _templateCache[name] = await res.text();
  }
  return _templateCache[name];
}

/**
 * Xóa cache của một hoặc tất cả template.
 * Dùng khi cần force reload template (development).
 * @param {string} [name]  Tên template cần xóa, bỏ qua để xóa tất cả
 */
function clearTemplateCache(name) {
  if (name) delete _templateCache[name];
  else Object.keys(_templateCache).forEach(k => delete _templateCache[k]);
}

