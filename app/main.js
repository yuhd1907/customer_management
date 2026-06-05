// ──────────────────────────────────────────────────────────
// main.js — SPA Router với 2 luồng: Admin & Staff/Manager
// ──────────────────────────────────────────────────────────

let currentUser = null;
let _currentPage = null;

async function login() {
  const username = document.getElementById('loginUser').value.trim();
  const password = document.getElementById('loginPass').value.trim();
  const errEl    = document.getElementById('loginError');
  const btn      = document.getElementById('loginBtn');
  errEl.style.display = 'none';
  if (!username || !password) { errEl.textContent = 'Vui lòng nhập đầy đủ thông tin.'; errEl.style.display = ''; return; }
  btn.disabled = true; btn.textContent = 'Đang đăng nhập...';
  try {
    const res = await api.post('/login', { username, password });
    localStorage.setItem('crm_token', res.data.token);
    currentUser = res.data.employee;
    showApp();
  } catch(e) {
    errEl.textContent = e.message; errEl.style.display = '';
  }
  btn.disabled = false; btn.textContent = 'Đăng nhập';
}

function showApp() {
  document.getElementById('authWrap').style.display = 'none';
  document.getElementById('appWrap').classList.add('visible');
  document.getElementById('userName').textContent   = currentUser.full_name;
  document.getElementById('userInitial').textContent = (currentUser.full_name || '?')[0].toUpperCase();
  const rt = document.getElementById('userRole');
  rt.textContent = { admin: 'Quản trị viên', manager: 'Quản lý', staff: 'Nhân viên' }[currentUser.role] || currentUser.role;
  rt.className   = 'user-role ' + currentUser.role;
  buildSidebar();
  const defaultPage = currentUser.role === 'admin' ? 'dashboard' : 'dashboard';
  navigate(defaultPage);
}

function buildSidebar() {
  const role = currentUser.role;
  let items;

  if (role === 'admin') {
    items = [
      { section: 'Tổng quan' },
      { id: 'dashboard',       label: 'Bảng điều khiển' },
      { section: 'Hệ thống' },
      { id: 'admin-employees', label: 'Quản lý Nhân viên' },
    ];
  } else {
    // manager & staff
    items = [
      { section: 'Tổng quan' },
      { id: 'dashboard',       label: 'Thống kê & Báo cáo' },
      { section: 'Khách hàng' },
      { id: 'customers',       label: 'Danh sách KH' },
      { id: 'tiers',           label: 'Hạng khách hàng' },
      { id: 'customer-groups', label: 'Nhóm khách hàng' },
      { id: 'birthdays',       label: 'Sinh nhật tháng này' },
      { section: 'Kinh doanh' },
      { id: 'orders',          label: 'Đơn hàng' },
      { id: 'returns',         label: 'Đổi / Hoàn trả' },
      { section: 'Hỗ trợ' },
      { id: 'chat',            label: 'Chăm sóc KH (CSKH)', badge: true },
      { id: 'upcoming-tasks',  label: 'Lịch nhắc việc' },
    ];
  }

  document.getElementById('sidebar').innerHTML = items.map(i => {
    if (i.section) return `<div class="nav-section">${i.section}</div>`;
    return `<div class="nav-item" id="nav-${i.id}" onclick="navigate('${i.id}')">
      <span class="nav-dot"></span>${i.label}
      ${i.badge ? `<span class="nav-badge" id="chat-unread-badge" style="display:none">0</span>` : ''}
    </div>`;
  }).join('');

  // poll unread for staff
  if (role !== 'admin') {
    _pollUnread();
    setInterval(_pollUnread, 30000);
  }
}

async function _pollUnread() {
  try {
    const res = await api.get('/conversations', { status: 'open', per_page: 1 });
    const badge = document.getElementById('chat-unread-badge');
    if (!badge) return;
    // count total unread across all open convs — use a lightweight approach
    const total = (await api.get('/conversations', { status: 'open', per_page: 100 })).data
      .reduce((sum, c) => sum + (c.unread_count || 0), 0);
    if (total > 0) { badge.textContent = total; badge.style.display = ''; }
    else { badge.style.display = 'none'; }
  } catch(e) {}
}

function navigate(page) {
  // destroy previous page if needed (especially chat)
  if (_currentPage === 'chat' && page !== 'chat') {
    if (typeof Chat !== 'undefined') Chat.destroy();
  }
  _currentPage = page;

  document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
  const nav = document.getElementById('nav-' + page);
  if (nav) nav.classList.add('active');

  // restore content area defaults (chat may have changed them)
  if (page !== 'chat') {
    const main = document.getElementById('mainContent');
    if (main) { main.style.padding = ''; main.style.overflow = ''; }
    const pc = document.getElementById('pageContent');
    if (pc) { pc.style.height = ''; }
  }

  switch (page) {
    // Flows
    case 'dashboard':       Dashboard.init(); break;
    case 'admin-employees': AdminEmployees.init(); break;
    case 'customers':       Customers.init();  break;
    case 'tiers':           Tiers.init();      break;
    case 'orders':          Orders.init();     break;
    case 'returns':         Returns.init();    break;
    case 'chat':            Chat.init();       break;
    case 'products':        Products.init();   break;
    case 'customer-groups': CustomerGroups.init(); break;
    case 'birthdays':       Birthdays.init();      break;
    case 'upcoming-tasks':  UpcomingTasks.init();  break;
  }
}

async function logout() {
  if (typeof Chat !== 'undefined') Chat.destroy?.();
  try { await api.post('/logout', {}); } catch(e) {}
  localStorage.removeItem('crm_token');
  currentUser = null;
  _currentPage = null;
  document.getElementById('appWrap').classList.remove('visible');
  document.getElementById('authWrap').style.display = '';
  document.getElementById('loginUser').value = '';
  document.getElementById('loginPass').value = '';
}

async function init() {
  const token = localStorage.getItem('crm_token');
  if (token) {
    try { 
      const res = await api.get('/me'); 
      currentUser = res.data; 
      showApp(); 
      return;
    } catch(e) { 
      localStorage.removeItem('crm_token'); 
    }
  }
  document.getElementById('authWrap').style.display = 'flex';
}


document.addEventListener('DOMContentLoaded', init);
document.getElementById('loginPass')?.addEventListener('keydown', e => { if (e.key === 'Enter') login(); });

