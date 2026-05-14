// ──────────────────────────────────────────────────────────
// returns.js — Đổi/Hoàn trả hàng
// ──────────────────────────────────────────────────────────

const Returns = {
  state: { page: 1, status: '', request_type: '' },

  init() { this.render(); this.load(); },

  render() {
    document.getElementById('pageContent').innerHTML = `
      <div class="page-header">
        <h2>Đổi / Hoàn trả hàng</h2>
        <div class="page-header-actions">
          <button class="btn btn-primary btn-sm" onclick="Returns.openCreate()">+ Tạo yêu cầu</button>
        </div>
      </div>
      <div class="card">
        <div class="search-bar">
          <select class="filter-select" onchange="Returns.onFilter('status',this.value)">
            <option value="">Tất cả trạng thái</option>
            <option value="pending">Chờ duyệt</option>
            <option value="approved">Đã duyệt</option>
            <option value="rejected">Từ chối</option>
            <option value="completed">Hoàn thành</option>
          </select>
          <select class="filter-select" onchange="Returns.onFilter('request_type',this.value)">
            <option value="">Tất cả loại</option>
            <option value="return">Hoàn trả</option>
            <option value="exchange">Đổi hàng</option>
          </select>
        </div>
        <div class="table-wrap">
          <table><thead><tr>
            <th>Mã đơn</th><th>Khách hàng</th><th>Loại</th><th>Lý do</th><th>Nhân viên</th><th>Trạng thái</th><th>Ngày tạo</th><th style="text-align:right">Thao tác</th>
          </tr></thead><tbody id="ret-tbody"></tbody></table>
        </div>
        <div id="ret-pagination"></div>
      </div>`;
  },

  onFilter(k, v) { this.state[k] = v; this.state.page = 1; this.load(); },

  async load() {
    const tbody = document.getElementById('ret-tbody');
    if (!tbody) return;
    tbody.innerHTML = `<tr class="loading-row"><td colspan="8"><span class="spinner"></span> Đang tải...</td></tr>`;
    try {
      const res = await api.get('/return-exchange-requests', { ...this.state, per_page: 15 });
      const statusMap = { pending: ['badge-yellow', 'Chờ duyệt'], approved: ['badge-blue', 'Đã duyệt'], rejected: ['badge-red', 'Từ chối'], completed: ['badge-green', 'Hoàn thành'] };
      const typeMap = { return: ['badge-red', 'Hoàn trả'], exchange: ['badge-purple', 'Đổi hàng'] };
      const role = currentUser?.role;
      tbody.innerHTML = res.data.map(r => {
        const [sc, sl] = statusMap[r.status] || ['badge-gray', r.status];
        const [tc, tl] = typeMap[r.request_type] || ['badge-gray', r.request_type];
        const canApprove = (role === 'admin' || role === 'manager') && r.status === 'pending';
        const canComplete = (role === 'admin' || role === 'manager') && r.status === 'approved';
        return `<tr>
          <td><span style="font-family:monospace;font-weight:600;color:var(--primary)">${r.order_code||'—'}</span></td>
          <td style="font-weight:500">${r.customer_name||'—'}</td>
          <td><span class="badge ${tc}">${tl}</span></td>
          <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${r.reason||''}">${r.reason||'—'}</td>
          <td>${r.employee_name||'—'}</td>
          <td><span class="badge ${sc}">${sl}</span></td>
          <td>${fmtDate(r.created_at)}</td>
          <td><div class="action-cell">
            <button class="btn btn-secondary btn-sm" onclick="Returns.showDetail(${r.id})">Chi tiết</button>
            ${canApprove ? `<button class="btn btn-success btn-sm" onclick="Returns.approve(${r.id})">Duyệt</button><button class="btn btn-danger btn-sm" onclick="Returns.reject(${r.id})">Từ chối</button>` : ''}
            ${canComplete ? `<button class="btn btn-primary btn-sm" onclick="Returns.complete(${r.id})">Hoàn thành</button>` : ''}
          </div></td>
        </tr>`;
      }).join('') || `<tr class="empty-row"><td colspan="8">Không có yêu cầu nào</td></tr>`;
      if (res.pagination) renderPagination(document.getElementById('ret-pagination'), res.pagination, `(p)=>{Returns.state.page=p;Returns.load()}`);
    } catch(e) { toast(e.message, 'error'); tbody.innerHTML = `<tr class="empty-row"><td colspan="8">${e.message}</td></tr>`; }
  },

  async openCreate() {
    let customers = [], orders = [];
    try { customers = (await api.get('/customers', { per_page: 200 })).data || []; } catch(e) {}
    const cOpts = customers.map(c => `<option value="${c.id}">${c.full_name} — ${c.phone||''}</option>`).join('');
    const result = await openModal('Tạo yêu cầu đổi/trả hàng', `
      <div class="form-field"><label>Khách hàng *</label>
        <select name="customer_id" id="retCustSel" onchange="Returns._loadOrders(this.value)">
          <option value="">— Chọn khách hàng —</option>${cOpts}
        </select>
      </div>
      <div class="form-field"><label>Đơn hàng *</label>
        <select name="order_id" id="retOrdSel"><option value="">— Chọn đơn hàng trước —</option></select>
      </div>
      <div class="form-row">
        <div class="form-field"><label>Loại yêu cầu *</label>
          <select name="request_type">
            <option value="return">Hoàn trả hàng</option>
            <option value="exchange">Đổi hàng</option>
          </select>
        </div>
      </div>
      <div class="form-field"><label>Lý do *</label><textarea name="reason" placeholder="Mô tả lý do yêu cầu..."></textarea></div>
      <div class="form-field"><label>Ghi chú của khách</label><textarea name="customer_note" placeholder="Ghi chú thêm..."></textarea></div>
    `);
    if (result !== 'save') return;
    const body = getFormData();
    if (!body.customer_id || !body.order_id || !body.reason) { toast('Vui lòng điền đầy đủ thông tin bắt buộc', 'error'); return; }
    try { await api.post('/return-exchange-requests', body); toast('Tạo yêu cầu thành công', 'success'); closeModal(); this.load(); }
    catch(e) { toast(e.message, 'error'); }
  },

  async _loadOrders(customerId) {
    const sel = document.getElementById('retOrdSel');
    if (!sel || !customerId) return;
    sel.innerHTML = '<option value="">Đang tải...</option>';
    try {
      const res = await api.get('/orders', { customer_id: customerId, status: 'completed', per_page: 50 });
      const orders = res.data || [];
      sel.innerHTML = orders.length
        ? orders.map(o => `<option value="${o.id}">${o.order_code} — ${fmtMoney(o.total_amount)}</option>`).join('')
        : '<option value="">Không có đơn hàng hoàn thành</option>';
    } catch(e) { sel.innerHTML = '<option value="">Lỗi tải đơn hàng</option>'; }
  },

  async showDetail(id) {
    let r;
    try { r = (await api.get('/return-exchange-requests/' + id)).data; } catch(e) { toast(e.message, 'error'); return; }
    const typeLabel = { return: 'Hoàn trả hàng', exchange: 'Đổi hàng' };
    const statusLabel = { pending: 'Chờ duyệt', approved: 'Đã duyệt', rejected: 'Từ chối', completed: 'Hoàn thành' };
    await openModal('Chi tiết yêu cầu #' + id, `
      <div class="info-grid" style="margin-bottom:12px">
        <div class="info-item"><div class="info-label">Khách hàng</div><div class="info-val">${r.customer_name||'—'}</div></div>
        <div class="info-item"><div class="info-label">Mã đơn hàng</div><div class="info-val" style="color:var(--primary);font-weight:600">${r.order_code||'—'}</div></div>
        <div class="info-item"><div class="info-label">Loại yêu cầu</div><div class="info-val">${typeLabel[r.request_type]||r.request_type}</div></div>
        <div class="info-item"><div class="info-label">Trạng thái</div><div class="info-val">${statusLabel[r.status]||r.status}</div></div>
        <div class="info-item"><div class="info-label">Nhân viên</div><div class="info-val">${r.employee_name||'—'}</div></div>
        <div class="info-item"><div class="info-label">Ngày tạo</div><div class="info-val">${fmtDate(r.created_at)}</div></div>
      </div>
      <div class="form-field"><label>Lý do</label><div style="padding:8px 12px;background:var(--bg);border-radius:7px;font-size:13px">${r.reason||'—'}</div></div>
      ${r.customer_note ? `<div class="form-field"><label>Ghi chú khách hàng</label><div style="padding:8px 12px;background:var(--bg);border-radius:7px;font-size:13px">${r.customer_note}</div></div>` : ''}
      ${r.admin_note ? `<div class="form-field"><label>Ghi chú admin</label><div style="padding:8px 12px;background:var(--warning-l);border-radius:7px;font-size:13px">${r.admin_note}</div></div>` : ''}
    `, 'Đóng');
  },

  async approve(id) {
    const result = await openModal('Duyệt yêu cầu #' + id, `
      <div class="form-field"><label>Ghi chú (tuỳ chọn)</label><textarea name="admin_note" placeholder="Ghi chú khi duyệt..."></textarea></div>
    `, 'Duyệt');
    if (result !== 'save') return;
    const body = getFormData();
    try { await api.patch('/return-exchange-requests/' + id + '/approve', body); toast('Đã duyệt yêu cầu', 'success'); closeModal(); this.load(); }
    catch(e) { toast(e.message, 'error'); }
  },

  async reject(id) {
    const result = await openModal('Từ chối yêu cầu #' + id, `
      <div class="form-field"><label>Lý do từ chối *</label><textarea name="admin_note" placeholder="Lý do từ chối..."></textarea></div>
    `, 'Từ chối');
    if (result !== 'save') return;
    const body = getFormData();
    if (!body.admin_note) { toast('Vui lòng nhập lý do từ chối', 'error'); return; }
    try { await api.patch('/return-exchange-requests/' + id + '/reject', body); toast('Đã từ chối yêu cầu', 'success'); closeModal(); this.load(); }
    catch(e) { toast(e.message, 'error'); }
  },

  async complete(id) {
    if (!await confirmDialog('Xác nhận hoàn thành yêu cầu này?')) return;
    try { await api.patch('/return-exchange-requests/' + id + '/complete', {}); toast('Đã hoàn thành', 'success'); this.load(); }
    catch(e) { toast(e.message, 'error'); }
  },
};
