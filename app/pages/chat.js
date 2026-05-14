// ──────────────────────────────────────────────────────────
// chat.js — Chăm sóc khách hàng (CSKH): Chat 2 cột
// ──────────────────────────────────────────────────────────

const Chat = {
  state: { page: 1, status: '' },
  _activeId: null,
  _pollTimer: null,
  _sending: false,

  init() {
    clearInterval(this._pollTimer);
    this._activeId = null;
    this.render();
    this.loadList();
  },

  render() {
    // Chat chiếm toàn bộ main content (không padding)
    const main = document.getElementById('mainContent');
    main.style.padding = '0';
    main.style.overflow = 'hidden';
    document.getElementById('pageContent').style.height = '100%';
    document.getElementById('pageContent').innerHTML = `
      <div class="chat-wrap" style="height:100%">
        <div class="chat-list">
          <div class="chat-list-header">
            <h3>CSKH</h3>
            <select class="filter-select" style="font-size:11px" onchange="Chat.onFilter(this.value)">
              <option value="">Tất cả</option>
              <option value="open">Đang mở</option>
              <option value="closed">Đã đóng</option>
            </select>
            <button class="btn btn-primary btn-sm" onclick="Chat.openCreate()">+</button>
          </div>
          <div class="chat-list-body" id="convList">
            <div style="padding:24px;text-align:center"><span class="spinner"></span></div>
          </div>
        </div>
        <div class="chat-main" id="chatMain">
          <div class="chat-empty" id="chatEmpty">
            <div style="font-size:32px">💬</div>
            <div>Chọn một hội thoại để bắt đầu</div>
            <div style="font-size:12px;color:var(--muted)">hoặc tạo hội thoại mới</div>
          </div>
        </div>
      </div>`;
  },

  onFilter(v) { this.state.status = v; this.state.page = 1; this.loadList(); },

  async loadList() {
    const el = document.getElementById('convList');
    if (!el) return;
    try {
      const res = await api.get('/conversations', { ...this.state, per_page: 50 });
      if (!res.data.length) {
        el.innerHTML = '<div style="padding:24px;text-align:center;color:var(--muted);font-size:13px">Chưa có hội thoại nào</div>';
        return;
      }
      el.innerHTML = res.data.map(c => `
        <div class="conv-item ${this._activeId == c.id ? 'active' : ''}" onclick="Chat.openConv(${c.id})">
          <div class="avatar-sm" style="flex-shrink:0">${initial(c.customer_name||'?')}</div>
          <div class="conv-item-body">
            <div class="conv-item-name">${c.customer_name||'Khách hàng'}</div>
            <div class="conv-item-preview">${c.title||typeLabel(c.type)}</div>
            <div class="conv-item-meta">
              <span class="conv-item-time">${fmtDate(c.updated_at)}</span>
              ${c.unread_count > 0 ? `<span class="conv-unread">${c.unread_count}</span>` : ''}
            </div>
          </div>
        </div>`).join('');
    } catch(e) {
      el.innerHTML = `<div style="padding:16px;color:var(--danger);font-size:13px">${e.message}</div>`;
    }
  },

  async openConv(id) {
    this._activeId = id;
    // highlight active
    document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
    const el = document.querySelector(`.conv-item[onclick="Chat.openConv(${id})"]`);
    if (el) el.classList.add('active');

    clearInterval(this._pollTimer);
    await this._renderConv(id);
    this._pollTimer = setInterval(() => this._pollMessages(id), 8000);
  },

  async _renderConv(id) {
    const main = document.getElementById('chatMain');
    if (!main) return;
    let conv;
    try { conv = (await api.get('/conversations/' + id)).data; } catch(e) { toast(e.message, 'error'); return; }

    main.innerHTML = `
      <div class="chat-header">
        <div class="avatar-sm">${initial(conv.customer_name||'?')}</div>
        <div class="chat-header-info">
          <div class="ch-name">${conv.customer_name || 'Khách hàng'}</div>
          <div class="ch-sub">${typeLabel(conv.type)} · ${conv.status === 'open' ? '<span style="color:var(--success)">Đang mở</span>' : '<span style="color:var(--muted)">Đã đóng</span>'}</div>
        </div>
        ${conv.status === 'open' ? `<button class="btn btn-secondary btn-sm" onclick="Chat.closeConv(${id})">Đóng hội thoại</button>` : ''}
      </div>
      <div class="chat-messages" id="chatMsgs"><span class="spinner"></span></div>
      ${conv.status === 'open' ? `
      <div class="chat-input-bar">
        <textarea id="chatInput" placeholder="Nhập tin nhắn..." rows="1"
          onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();Chat.sendMsg(${id})}"
          oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,120)+'px'"></textarea>
        <button class="chat-send-btn" id="chatSendBtn" onclick="Chat.sendMsg(${id})">Gửi</button>
      </div>` : `<div style="padding:12px 20px;text-align:center;font-size:12px;color:var(--muted);background:var(--white);border-top:1px solid var(--border)">Hội thoại đã đóng</div>`}`;

    await this._loadMessages(id);
  },

  async _loadMessages(id, append = false) {
    const el = document.getElementById('chatMsgs');
    if (!el) return;
    if (!append) el.innerHTML = `<div style="text-align:center;padding:20px"><span class="spinner"></span></div>`;
    try {
      const res = await api.get(`/conversations/${id}/messages`, { per_page: 100 });
      const msgs = res.data || [];
      if (!msgs.length) {
        el.innerHTML = '<div style="text-align:center;padding:30px;color:var(--muted);font-size:13px">Chưa có tin nhắn nào. Hãy bắt đầu cuộc trò chuyện!</div>';
        return;
      }
      let lastDate = '';
      el.innerHTML = msgs.map(m => {
        const senderName = m.employee_name || 'Nhân viên';
        const mine = !!m.sender_employee_id;
        const dateStr = m.created_at ? m.created_at.slice(0, 10) : '';
        let sep = '';
        if (dateStr && dateStr !== lastDate) { sep = `<div class="chat-date-sep">${dateStr}</div>`; lastDate = dateStr; }
        return `${sep}<div class="msg-bubble ${mine ? 'mine' : 'other'}">
          <div class="msg-text">${escHtml(m.message)}</div>
          <div class="msg-meta">${mine ? 'Bạn' : senderName} · ${fmtTime(m.created_at)}</div>
        </div>`;
      }).join('');
      el.scrollTop = el.scrollHeight;
    } catch(e) { el.innerHTML = `<div style="padding:16px;color:var(--danger)">${e.message}</div>`; }
  },

  async _pollMessages(id) {
    if (this._activeId !== id) return;
    const el = document.getElementById('chatMsgs');
    if (!el) { clearInterval(this._pollTimer); return; }
    try {
      const res = await api.get(`/conversations/${id}/messages`, { per_page: 100 });
      const msgs = res.data || [];
      if (!msgs.length) return;
      let lastDate = '';
      el.innerHTML = msgs.map(m => {
        const senderName = m.employee_name || 'Nhân viên';
        const mine = !!m.sender_employee_id;
        const dateStr = m.created_at ? m.created_at.slice(0, 10) : '';
        let sep = '';
        if (dateStr && dateStr !== lastDate) { sep = `<div class="chat-date-sep">${dateStr}</div>`; lastDate = dateStr; }
        return `${sep}<div class="msg-bubble ${mine ? 'mine' : 'other'}">
          <div class="msg-text">${escHtml(m.message)}</div>
          <div class="msg-meta">${mine ? 'Bạn' : senderName} · ${fmtTime(m.created_at)}</div>
        </div>`;
      }).join('');
      el.scrollTop = el.scrollHeight;
      // refresh unread in list
      this.loadList();
    } catch(e) {}
  },

  async sendMsg(id) {
    if (this._sending) return;
    const inp = document.getElementById('chatInput');
    if (!inp) return;
    const msg = inp.value.trim();
    if (!msg) return;
    this._sending = true;
    const btn = document.getElementById('chatSendBtn');
    if (btn) { btn.disabled = true; btn.textContent = '...'; }
    try {
      await api.post(`/conversations/${id}/messages`, { message: msg });
      inp.value = '';
      inp.style.height = 'auto';
      await this._loadMessages(id);
    } catch(e) { toast(e.message, 'error'); }
    finally {
      this._sending = false;
      if (btn) { btn.disabled = false; btn.textContent = 'Gửi'; }
    }
  },

  async closeConv(id) {
    if (!await confirmDialog('Đóng hội thoại này? Nhân viên sẽ không thể gửi thêm tin nhắn.')) return;
    try {
      await api.patch('/conversations/' + id + '/close', {});
      toast('Đã đóng hội thoại', 'success');
      clearInterval(this._pollTimer);
      this._activeId = null;
      this.loadList();
      document.getElementById('chatMain').innerHTML = '<div class="chat-empty"><div>Hội thoại đã được đóng</div></div>';
    } catch(e) { toast(e.message, 'error'); }
  },

  async openCreate() {
    let customers = [];
    try { customers = (await api.get('/customers', { per_page: 200 })).data || []; } catch(e) {}
    const cOpts = customers.map(c => `<option value="${c.id}">${c.full_name} — ${c.phone || ''}</option>`).join('');
    const result = await openModal('Tạo hội thoại mới', `
      <div class="form-field"><label>Khách hàng *</label>
        <select name="customer_id"><option value="">— Chọn khách hàng —</option>${cOpts}</select>
      </div>
      <div class="form-field"><label>Tiêu đề</label><input name="title" placeholder="VD: Hỗ trợ đơn hàng #ORD-2024-001"></div>
      <div class="form-field"><label>Loại hội thoại</label>
        <select name="type">
          <option value="general_support">Hỗ trợ chung</option>
          <option value="product_consulting">Tư vấn sản phẩm</option>
          <option value="return_request">Yêu cầu trả hàng</option>
          <option value="exchange_request">Yêu cầu đổi hàng</option>
        </select>
      </div>
    `);
    if (result !== 'save') return;
    const body = getFormData();
    if (!body.customer_id) { toast('Vui lòng chọn khách hàng', 'error'); return; }
    try {
      const res = await api.post('/conversations', body);
      toast('Tạo hội thoại thành công', 'success');
      closeModal();
      await this.loadList();
      this.openConv(res.data.id);
    } catch(e) { toast(e.message, 'error'); }
  },

  destroy() {
    clearInterval(this._pollTimer);
    this._activeId = null;
    // restore content padding
    const main = document.getElementById('mainContent');
    if (main) { main.style.padding = ''; main.style.overflow = ''; }
    const pc = document.getElementById('pageContent');
    if (pc) pc.style.height = '';
  },
};

// Helpers
function typeLabel(t) {
  return { product_consulting: 'Tư vấn SP', return_request: 'Trả hàng', exchange_request: 'Đổi hàng', general_support: 'Hỗ trợ chung' }[t] || t;
}
function fmtTime(dt) {
  if (!dt) return '';
  const d = new Date(dt.replace(' ', 'T'));
  return d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
}
function escHtml(s) {
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
}
