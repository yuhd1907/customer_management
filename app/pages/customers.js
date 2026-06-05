// ──────────────────────────────────────────────────────────
// customers.js — Quản lý Khách hàng + Detail Panel (CRM)
// ──────────────────────────────────────────────────────────

// Helpers
function sourceLabel(s) {
  return {website:'Website',facebook:'Facebook',zalo:'Zalo',referral:'Giới thiệu',store:'Cửa hàng',other:'Khác'}[s]||s;
}

const Customers = {
  state: { page: 1, search: '', tier_id: '', status: '', source: '', customer_group_id: '' },
  _tiers: [], _groups: [], _tags: [],

  async init() {
    try {
      [this._tiers, this._groups, this._tags] = await Promise.all([
        api.get('/customer-tiers').then(r => r.data || []),
        api.get('/customer-groups').then(r => r.data || []),
        api.get('/customer-tags').then(r => r.data || []),
      ]);
    } catch(e) {}
    await this.render();
    this.load();
  },

  async render() {
    // Tải HTML skeleton từ file template riêng
    document.getElementById('pageContent').innerHTML = await loadTemplate('customers');

    // Điền động các options filter (cần dữ liệu từ API)
    const tierSel  = document.querySelector('[onchange*="tier_id"]');
    const groupSel = document.querySelector('[onchange*="customer_group_id"]');
    if (tierSel)  tierSel.insertAdjacentHTML('beforeend',
      this._tiers.map(t => `<option value="${t.id}">${t.name}</option>`).join(''));
    if (groupSel) groupSel.insertAdjacentHTML('beforeend',
      this._groups.map(g => `<option value="${g.id}">${g.name}</option>`).join(''));
  },

  _t: null,
  onSearch(v) { clearTimeout(this._t); this._t = setTimeout(() => { this.state.search = v; this.state.page = 1; this.load(); }, 400); },
  onFilter(k, v) { this.state[k] = v; this.state.page = 1; this.load(); },

  async load() {
    const tbody = document.getElementById('cust-tbody');
    if (!tbody) return;
    tbody.innerHTML = `<tr class="loading-row"><td colspan="8"><span class="spinner"></span> Đang tải...</td></tr>`;
    try {
      const res = await api.get('/customers', { ...this.state, per_page: 10 });
      if (!res.data.length) { tbody.innerHTML = `<tr class="empty-row"><td colspan="8">Không tìm thấy khách hàng</td></tr>`; return; }
      tbody.innerHTML = res.data.map(c => `<tr class="row-link" onclick="Customers.openDetail(${c.id})">
        <td><div style="display:flex;align-items:center;gap:10px">
          <div class="avatar-sm">${initial(c.full_name)}</div>
          <div>
            <div style="font-weight:600">${c.full_name}</div>
            <div style="font-size:11px;color:var(--muted)">${c.email||''}</div>
          </div>
        </div></td>
        <td>${c.phone||'—'}</td>
        <td>
          ${c.tier_name ? `<span class="badge badge-purple">${c.tier_name}</span>` : ''}
          ${c.group_name ? `<span class="badge badge-blue" style="margin-left:2px">${c.group_name}</span>` : ''}
          ${(!c.tier_name && !c.group_name) ? '—' : ''}
        </td>
        <td><span style="font-size:11px;background:var(--bg);padding:2px 6px;border-radius:4px;color:var(--text2)">${sourceLabel(c.source||'other')}</span></td>
        <td style="font-weight:600;color:var(--success)">${fmtMoney(c.total_spent||0)}</td>
        <td>${badge(c.status,{active:'Hoạt động',inactive:'Không HĐ',blocked:'Bị chặn'}[c.status])}</td>
        <td>${fmtDate(c.created_at)}</td>
        <td><div class="action-cell" onclick="event.stopPropagation()">
          <button class="btn btn-secondary btn-sm" onclick="Customers.openEdit(${c.id})">Sửa</button>
          <button class="btn btn-danger btn-sm" onclick="Customers.delete(${c.id},'${c.full_name}')">Xóa</button>
        </div></td>
      </tr>`).join('');
      if (res.pagination) renderPagination(document.getElementById('cust-pagination'), res.pagination, `(p)=>{Customers.state.page=p;Customers.load()}`);
    } catch(e) { toast(e.message,'error'); tbody.innerHTML=`<tr class="empty-row"><td colspan="8">${e.message}</td></tr>`; }
  },

  async exportCSV() {
    try {
      const res = await fetch(`${api.BASE}/customers/export`, {
        headers: { 'Authorization': 'Bearer ' + api.token() }
      });
      if (!res.ok) {
        let msg = 'Lỗi không xác định';
        try { const d = await res.json(); msg = d.message || msg; } catch(e) {}
        throw new Error(msg);
      }
      const blob = await res.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `customers_${Date.now()}.csv`;
      a.click();
      window.URL.revokeObjectURL(url);
    } catch(e) {
      toast('Lỗi khi tải file: ' + e.message, 'error');
    }
  },

  async importCSV() {
    const input = document.createElement('input');
    input.type = 'file'; input.accept = '.csv';
    input.onchange = async () => {
      if (!input.files[0]) return;
      const fd = new FormData(); fd.append('file', input.files[0]);
      try {
        const res = await fetch(`${api.BASE}/customers/import`, {
          method:'POST', headers:{ Authorization: 'Bearer ' + localStorage.getItem('crm_token') }, body: fd
        });
        const json = await res.json();
        toast(`Đã import: +${json.data?.inserted||0} KH, bỏ qua ${json.data?.skipped||0}`, 'success');
        Customers.load();
      } catch(e) { toast('Import thất bại: ' + e.message, 'error'); }
    };
    input.click();
  },

  async openDetail(id) {
    // Show overlay immediately with spinner
    const overlay = document.getElementById('detailOverlay');
    const body    = document.getElementById('dpBody');
    const avatar  = document.getElementById('dpAvatar');
    const nameEl  = document.getElementById('dpName');
    const editBtn = document.getElementById('dpEditBtn');

    body.innerHTML = '<div style="text-align:center;padding:40px"><span class="spinner"></span></div>';
    nameEl.textContent = 'Đang tải...';
    overlay.classList.add('open');

    try {
      const c = (await api.get('/customers/' + id)).data;
      avatar.textContent = initial(c.full_name);
      nameEl.textContent = c.full_name;
      editBtn.onclick = () => { overlay.classList.remove('open'); Customers.openEdit(id); };

      const tierBadge = c.tier_name ? `<span class="badge badge-purple">${c.tier_name}</span>` : '—';
      body.innerHTML = `
        <div class="info-grid">
          <div class="info-item"><div class="info-label">SĐT</div><div class="info-val">${c.phone||'—'}</div></div>
          <div class="info-item"><div class="info-label">Email</div><div class="info-val">${c.email||'—'}</div></div>
          <div class="info-item"><div class="info-label">Sinh nhật</div><div class="info-val">${c.date_of_birth ? fmtDate(c.date_of_birth).split(' ')[0] : '—'}</div></div>
          <div class="info-item"><div class="info-label">Hạng KH</div><div class="info-val">${tierBadge}</div></div>
          <div class="info-item"><div class="info-label">Trạng thái</div><div class="info-val">${badge(c.status,{active:'Hoạt động',inactive:'Không HĐ'}[c.status])}</div></div>
          <div class="info-item"><div class="info-label">Tổng chi tiêu</div><div class="info-val" style="color:var(--success);font-weight:700">${fmtMoney(c.total_spent||0)}</div></div>
          <div class="info-item"><div class="info-label">Ngày tạo</div><div class="info-val">${fmtDate(c.created_at)}</div></div>
        </div>
        ${c.address ? `<div style="margin-bottom:16px;font-size:13px"><span style="font-weight:600;color:var(--muted);font-size:11px;text-transform:uppercase">Địa chỉ</span><div style="margin-top:4px">${c.address}</div></div>` : ''}
        ${c.note ? `<div style="margin-bottom:16px;font-size:13px"><span style="font-weight:600;color:var(--muted);font-size:11px;text-transform:uppercase">Ghi chú</span><div style="margin-top:4px;padding:10px;background:var(--bg);border-radius:7px">${c.note}</div></div>` : ''}

        <div class="tabs" style="margin-top:8px">
          <button class="tab-btn active" onclick="Customers._tab(this,'dp-orders')">Đơn hàng</button>
          <button class="tab-btn" onclick="Customers._tab(this,'dp-returns')">Đổi/Trả</button>
          <button class="tab-btn" onclick="Customers._tab(this,'dp-convs')">CSKH</button>
          <button class="tab-btn" onclick="Customers._tab(this,'dp-timeline')">Timeline</button>
          <button class="tab-btn" onclick="Customers._tab(this,'dp-tasks')">Lịch nhắc</button>
        </div>

        <div id="dp-orders" class="tab-panel active">
          <div style="padding:20px;text-align:center"><span class="spinner"></span></div>
        </div>
        <div id="dp-returns" class="tab-panel"></div>
        <div id="dp-convs" class="tab-panel"></div>
        <div id="dp-timeline" class="tab-panel"></div>
        <div id="dp-tasks" class="tab-panel"></div>
      `;
      this._loadCustOrders(id);
    } catch(e) {
      body.innerHTML = `<div style="padding:24px;color:var(--danger)">${e.message}</div>`;
    }
  },

  _tab(btn, panelId) {
    btn.closest('.tabs').querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    const panel = document.getElementById(panelId);
    if (panel) {
      panel.classList.add('active');
      if (!panel.dataset.loaded) {
        panel.dataset.loaded = '1';
        const id = parseInt(document.getElementById('dpName').dataset.id);
        if (panelId === 'dp-returns')  this._loadCustReturns(id);
        if (panelId === 'dp-convs')    this._loadCustConvs(id);
        if (panelId === 'dp-timeline') this._loadTimeline(id);
        if (panelId === 'dp-tasks')    this._loadTasks(id);
      }
    }
  },


  async _loadCustOrders(customerId) {
    const el = document.getElementById('dp-orders');
    if (!el) return;
    document.getElementById('dpName').dataset.id = customerId;
    try {
      const res = await api.get('/orders', { customer_id: customerId, per_page: 20 });
      const sl = { pending: 'Chờ xử lý', confirmed: 'Đã xác nhận', shipping: 'Đang giao', completed: 'Hoàn thành', cancelled: 'Đã hủy', returned: 'Đã trả' };
      el.innerHTML = res.data.length ? `
        <table><thead><tr><th>Mã đơn</th><th>Tổng tiền</th><th>Trạng thái</th><th>Ngày tạo</th></tr></thead>
        <tbody>${res.data.map(o => `<tr>
          <td style="font-family:monospace;color:var(--primary);font-weight:600">${o.order_code}</td>
          <td style="font-weight:600">${fmtMoney(o.total_amount)}</td>
          <td>${badge(o.status, sl[o.status])}</td>
          <td>${fmtDate(o.created_at)}</td>
        </tr>`).join('')}</tbody></table>` : '<div style="padding:24px;text-align:center;color:var(--muted)">Chưa có đơn hàng</div>';
    } catch(e) { el.innerHTML = `<div style="padding:16px;color:var(--danger)">${e.message}</div>`; }
  },

  async _loadCustReturns(customerId) {
    const el = document.getElementById('dp-returns');
    if (!el) return;
    try {
      const res = await api.get('/return-exchange-requests', { customer_id: customerId, per_page: 20 });
      const sl = { pending: 'Chờ duyệt', approved: 'Đã duyệt', rejected: 'Từ chối', completed: 'Hoàn thành' };
      el.innerHTML = res.data.length ? `
        <table><thead><tr><th>Loại</th><th>Mã đơn</th><th>Lý do</th><th>Trạng thái</th></tr></thead>
        <tbody>${res.data.map(r => `<tr>
          <td>${r.request_type === 'return' ? 'Hoàn trả' : 'Đổi hàng'}</td>
          <td style="font-family:monospace;color:var(--primary)">${r.order_code||'—'}</td>
          <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${r.reason||'—'}</td>
          <td>${badge(r.status, sl[r.status])}</td>
        </tr>`).join('')}</tbody></table>` : '<div style="padding:24px;text-align:center;color:var(--muted)">Chưa có yêu cầu</div>';
    } catch(e) { el.innerHTML = `<div style="padding:16px;color:var(--danger)">${e.message}</div>`; }
  },

  async _loadCustConvs(customerId) {
    const el = document.getElementById('dp-convs');
    if (!el) return;
    try {
      const res = await api.get('/conversations', { customer_id: customerId, per_page: 20 });
      el.innerHTML = res.data.length ? `
        <table><thead><tr><th>Tiêu đề</th><th>Loại</th><th>Trạng thái</th><th>Cập nhật</th><th></th></tr></thead>
        <tbody>${res.data.map(c => `<tr>
          <td style="font-weight:500">${c.title || '(Không tiêu đề)'}</td>
          <td><span class="text-muted" style="font-size:12px">${typeLabel(c.type)}</span></td>
          <td>${badge(c.status, { open: 'Đang mở', closed: 'Đã đóng' }[c.status])}</td>
          <td>${fmtDate(c.updated_at)}</td>
          <td><button class="btn btn-secondary btn-sm" onclick="document.getElementById('detailOverlay').classList.remove('open');sessionStorage.setItem('open_conv','${c.id}');window.location.href='chat.html'">Mở chat</button></td>
        </tr>`).join('')}</tbody></table>
        <div style="padding:12px 16px">
          <button class="btn btn-primary btn-sm" onclick="Customers._createConvForCust(${customerId})">+ Tạo hội thoại mới</button>
        </div>` : `<div style="padding:24px;text-align:center;color:var(--muted)">Chưa có hội thoại nào
          <div style="margin-top:12px"><button class="btn btn-primary btn-sm" onclick="Customers._createConvForCust(${customerId})">+ Tạo hội thoại</button></div>
        </div>`;
    } catch(e) { el.innerHTML = `<div style="padding:16px;color:var(--danger)">${e.message}</div>`; }
  },

  async _createConvForCust(customerId) {
    const result = await openModal('Tạo hội thoại mới', `
      <div class="form-field"><label>Tiêu đề</label><input name="title" placeholder="VD: Hỗ trợ đơn hàng..."></div>
      <div class="form-field"><label>Loại</label><select name="type">
        <option value="general_support">Hỗ trợ chung</option>
        <option value="product_consulting">Tư vấn sản phẩm</option>
        <option value="return_request">Yêu cầu trả hàng</option>
        <option value="exchange_request">Yêu cầu đổi hàng</option>
      </select></div>
      <input type="hidden" name="customer_id" value="${customerId}">
    `);
    if (result !== 'save') return;
    const body = getFormData();
    body.customer_id = customerId;
    try {
      const res = await api.post('/conversations', body);
      toast('Tạo hội thoại thành công', 'success');
      closeModal();
      document.getElementById('detailOverlay').classList.remove('open');
      sessionStorage.setItem('open_conv', res.data.id);
      window.location.href = 'chat.html';
    } catch(e) { toast(e.message, 'error'); }
  },

  async openCreate() {
    const tierOpts = this._tiers.map(t => `<option value="${t.id}">${t.name}</option>`).join('');
    const groupOpts = this._groups.map(g => `<option value="${g.id}">${g.name}</option>`).join('');
    const result = await openModal('Thêm khách hàng', `
      <div class="form-row">
        <div class="form-field"><label>Họ tên *</label><input name="full_name" placeholder="Nguyễn Thị B"></div>
        <div class="form-field"><label>SĐT *</label><input name="phone" placeholder="0901234567"></div>
      </div>
      <div class="form-row">
        <div class="form-field"><label>Email</label><input name="email" type="email" placeholder="email@example.com"></div>
        <div class="form-field"><label>Sinh nhật</label><input name="date_of_birth" type="date"></div>
      </div>
      <div class="form-row">
        <div class="form-field"><label>Hạng KH</label>
          <select name="tier_id"><option value="">Chưa xếp hạng</option>${tierOpts}</select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-field"><label>Nhóm KH</label>
          <select name="customer_group_id"><option value="">Không thuộc nhóm nào</option>${groupOpts}</select>
        </div>
        <div class="form-field"><label>Nguồn khách</label>
          <select name="source">
            <option value="other">Khác</option>
            <option value="website">Website</option>
            <option value="facebook">Facebook</option>
            <option value="zalo">Zalo</option>
            <option value="referral">Giới thiệu</option>
            <option value="store">Cửa hàng</option>
          </select>
        </div>
      </div>
      <div class="form-field"><label>Địa chỉ</label><input name="address" placeholder="Địa chỉ..."></div>
      <div class="form-field"><label>Ghi chú</label><textarea name="note" placeholder="Ghi chú về khách hàng..."></textarea></div>
    `);
    if (result !== 'save') return;
    const body = getFormData();
    if (!body.full_name) { toast('Vui lòng nhập họ tên', 'error'); return; }
    try { await api.post('/customers', body); toast('Thêm khách hàng thành công', 'success'); closeModal(); Customers.load(); }
    catch(e) { toast(e.message, 'error'); }
  },

  async openEdit(id) {
    let c;
    try { c = (await api.get('/customers/' + id)).data; } catch(e) { toast(e.message, 'error'); return; }
    const tierOpts = this._tiers.map(t => `<option value="${t.id}" ${c.tier_id == t.id ? 'selected' : ''}>${t.name}</option>`).join('');
    const groupOpts = this._groups.map(g => `<option value="${g.id}" ${c.customer_group_id == g.id ? 'selected' : ''}>${g.name}</option>`).join('');
    const result = await openModal('Sửa khách hàng', `
      <div class="form-row">
        <div class="form-field"><label>Họ tên *</label><input name="full_name" value="${c.full_name||''}"></div>
        <div class="form-field"><label>SĐT *</label><input name="phone" value="${c.phone||''}"></div>
      </div>
      <div class="form-row">
        <div class="form-field"><label>Email</label><input name="email" value="${c.email||''}"></div>
        <div class="form-field"><label>Sinh nhật</label><input name="date_of_birth" type="date" value="${c.date_of_birth||''}"></div>
      </div>
      <div class="form-row">
        <div class="form-field"><label>Hạng KH</label>
          <select name="tier_id"><option value="">Chưa xếp hạng</option>${tierOpts}</select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-field"><label>Nhóm KH</label>
          <select name="customer_group_id"><option value="">Không thuộc nhóm nào</option>${groupOpts}</select>
        </div>
        <div class="form-field"><label>Nguồn khách</label>
          <select name="source">
            <option value="other" ${c.source==='other'?'selected':''}>Khác</option>
            <option value="website" ${c.source==='website'?'selected':''}>Website</option>
            <option value="facebook" ${c.source==='facebook'?'selected':''}>Facebook</option>
            <option value="zalo" ${c.source==='zalo'?'selected':''}>Zalo</option>
            <option value="referral" ${c.source==='referral'?'selected':''}>Giới thiệu</option>
            <option value="store" ${c.source==='store'?'selected':''}>Cửa hàng</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-field"><label>Địa chỉ</label><input name="address" value="${c.address||''}"></div>
        <div class="form-field"><label>Trạng thái</label><select name="status">
          <option value="active" ${c.status==='active'?'selected':''}>Hoạt động</option>
          <option value="inactive" ${c.status==='inactive'?'selected':''}>Không HĐ</option>
        </select></div>
      </div>
      <div class="form-field"><label>Ghi chú</label><textarea name="note">${c.note||''}</textarea></div>
    `);
    if (result !== 'save') return;
    const body = getFormData();
    try { await api.put('/customers/' + id, body); toast('Cập nhật thành công', 'success'); closeModal(); Customers.load(); }
    catch(e) { toast(e.message, 'error'); }
  },

  async delete(id, name) {
    if (!await confirmDialog(`Xóa khách hàng "${name}"? Thao tác không thể hoàn tác.`)) return;
    try { await api.delete('/customers/' + id); toast('Đã xóa khách hàng', 'success'); Customers.load(); }
    catch(e) { toast(e.message, 'error'); }
  },

  // ── Timeline ──────────────────────────────────────────
  async _loadTimeline(customerId) {
    const el = document.getElementById('dp-timeline');
    if (!el) return;
    const typeIcon = { call:'📞', meeting:'🤝', email:'✉️', note:'📝', other:'📌' };
    const typeLabel = { call:'Gọi điện', meeting:'Gặp mặt', email:'Email', note:'Ghi chú', other:'Khác' };
    try {
      const res = await api.get(`/customers/${customerId}/activities`, { per_page: 50 });
      const acts = res.data || [];
      el.innerHTML = `
        <div style="padding:10px 16px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border)">
          <span style="font-weight:600;font-size:13px">Lịch sử hoạt động</span>
          <button class="btn btn-primary btn-sm" onclick="Customers._addActivity(${customerId})">+ Ghi nhận</button>
        </div>
        <div class="timeline">
          ${acts.length ? acts.map(a => `
            <div class="timeline-item">
              <div class="timeline-icon">${typeIcon[a.type]||'📌'}</div>
              <div class="timeline-body">
                <div class="timeline-title">${a.title}</div>
                <div class="timeline-meta">${typeLabel[a.type]||a.type} · ${a.employee_name||'Nhân viên'} · ${fmtDate(a.activity_date)}</div>
                ${a.content ? `<div class="timeline-content">${a.content}</div>` : ''}
              </div>
            </div>`).join('') : '<div style="padding:24px;text-align:center;color:var(--muted)">Chưa có hoạt động nào. Hãy ghi nhận lần liên hệ đầu tiên!</div>'}
        </div>`;
    } catch(e) { el.innerHTML = `<div style="padding:16px;color:var(--danger)">${e.message}</div>`; }
  },

  async _addActivity(customerId) {
    const result = await openModal('Ghi nhận hoạt động', `
      <div class="form-field"><label>Loại hoạt động</label>
        <select name="type">
          <option value="call">📞 Gọi điện</option>
          <option value="meeting">🤝 Gặp mặt</option>
          <option value="email">✉️ Email</option>
          <option value="note" selected>📝 Ghi chú</option>
          <option value="other">📌 Khác</option>
        </select>
      </div>
      <div class="form-field"><label>Tiêu đề *</label><input name="title" placeholder="VD: Gọi điện tư vấn sản phẩm mới"></div>
      <div class="form-field"><label>Nội dung</label><textarea name="content" placeholder="Chi tiết nội dung trao đổi..."></textarea></div>
      <div class="form-field"><label>Thời điểm</label><input name="activity_date" type="datetime-local" value="${new Date().toISOString().slice(0,16)}"></div>
    `);
    if (result !== 'save') return;
    const body = getFormData();
    if (!body.title) { toast('Vui lòng nhập tiêu đề', 'error'); return; }
    try {
      await api.post(`/customers/${customerId}/activities`, body);
      toast('Đã ghi nhận hoạt động', 'success'); closeModal();
      const el = document.getElementById('dp-timeline');
      if (el) { delete el.dataset.loaded; el.innerHTML = ''; this._loadTimeline(customerId); }
    } catch(e) { toast(e.message, 'error'); }
  },

  // ── Lịch nhắc ─────────────────────────────────────────
  async _loadTasks(customerId) {
    const el = document.getElementById('dp-tasks');
    if (!el) return;
    const prioMap = { high:['badge-red','Cao'], normal:['badge-yellow','Thường'], low:['badge-gray','Thấp'] };
    try {
      const res = await api.get(`/customers/${customerId}/tasks`);
      const tasks = res.data || [];
      el.innerHTML = `
        <div style="padding:10px 16px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border)">
          <span style="font-weight:600;font-size:13px">Lịch nhắc việc</span>
          <button class="btn btn-primary btn-sm" onclick="Customers._addTask(${customerId})">+ Thêm nhắc</button>
        </div>
        ${tasks.length ? `<div>${tasks.map(t => {
          const [pc,pl] = prioMap[t.priority]||['badge-gray',t.priority];
          const isOverdue = t.status==='pending' && new Date(t.due_date.replace(' ','T')) < new Date();
          return `<div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;gap:10px;align-items:flex-start">
            <div style="flex:1">
              <div style="font-weight:600;font-size:13px;${t.status==='done'?'text-decoration:line-through;color:var(--muted)':''}">${t.title}</div>
              <div style="font-size:11px;margin-top:3px;color:${isOverdue?'var(--danger)':'var(--text2)'}">
                ${isOverdue?'⚠ Quá hạn: ':''}${fmtDate(t.due_date)} · ${t.employee_name||'—'}
              </div>
            </div>
            <span class="badge ${pc}">${pl}</span>
            ${t.status==='pending' ? `<button class="btn btn-secondary btn-sm" onclick="Customers._doneTask(${customerId},${t.id})">Xong</button>` : `<span class="badge badge-green">${t.status==='done'?'Xong':'Hủy'}</span>`}
          </div>`;
        }).join('')}</div>` : '<div style="padding:24px;text-align:center;color:var(--muted)">Chưa có lịch nhắc. Thêm nhắc nhở cho lần liên hệ tới!</div>'}`;
    } catch(e) { el.innerHTML = `<div style="padding:16px;color:var(--danger)">${e.message}</div>`; }
  },

  async _addTask(customerId) {
    const result = await openModal('Thêm lịch nhắc', `
      <div class="form-field"><label>Tiêu đề *</label><input name="title" placeholder="VD: Gọi lại tư vấn gói sản phẩm mới"></div>
      <div class="form-field"><label>Mô tả</label><textarea name="description" placeholder="Chi tiết..."></textarea></div>
      <div class="form-row">
        <div class="form-field"><label>Hạn hoàn thành *</label><input name="due_date" type="datetime-local"></div>
        <div class="form-field"><label>Ưu tiên</label>
          <select name="priority">
            <option value="normal">Thường</option>
            <option value="high">Cao</option>
            <option value="low">Thấp</option>
          </select>
        </div>
      </div>
    `);
    if (result !== 'save') return;
    const body = getFormData();
    if (!body.title || !body.due_date) { toast('Vui lòng nhập tiêu đề và hạn hoàn thành', 'error'); return; }
    try {
      await api.post(`/customers/${customerId}/tasks`, body);
      toast('Đã thêm lịch nhắc', 'success'); closeModal();
      const el = document.getElementById('dp-tasks');
      if (el) { delete el.dataset.loaded; el.innerHTML=''; this._loadTasks(customerId); }
    } catch(e) { toast(e.message, 'error'); }
  },

  async _doneTask(customerId, taskId) {
    try {
      await api.patch(`/customers/${customerId}/tasks/${taskId}/done`, {});
      toast('Đã đánh dấu hoàn thành', 'success');
      const el = document.getElementById('dp-tasks');
      if (el) { delete el.dataset.loaded; el.innerHTML=''; this._loadTasks(customerId); }
    } catch(e) { toast(e.message, 'error'); }
  },
};

