const Employees = {
  state: { page: 1, search: '', role: '', status: '' },
  init() { this.render(); this.load(); },
  render() {
    document.getElementById('pageContent').innerHTML = `
      <div class="page-header">
        <h2>Quản lý Nhân viên</h2>
        <div class="page-header-actions">
          <button class="btn btn-primary btn-sm" onclick="Employees.openCreate()">+ Thêm nhân viên</button>
        </div>
      </div>
      <div class="card">
        <div class="search-bar">
          <input class="search-input" placeholder="Tìm kiếm tên, username, email..." oninput="Employees.onSearch(this.value)">
          <select class="filter-select" onchange="Employees.onFilter('role',this.value)">
            <option value="">Tất cả vai trò</option>
            <option value="admin">Admin</option><option value="manager">Manager</option><option value="staff">Staff</option>
          </select>
          <select class="filter-select" onchange="Employees.onFilter('status',this.value)">
            <option value="">Tất cả trạng thái</option>
            <option value="active">Hoạt động</option><option value="inactive">Không hoạt động</option><option value="locked">Đã khóa</option>
          </select>
        </div>
        <div class="table-wrap">
          <table><thead><tr>
            <th>Nhân viên</th><th>Username</th><th>Email</th><th>Vị trí</th><th>Vai trò</th><th>Trạng thái</th><th>Đăng nhập cuối</th><th style="text-align:right">Thao tác</th>
          </tr></thead><tbody id="emp-tbody"></tbody></table>
        </div>
        <div id="emp-pagination"></div>
      </div>`;
  },
  _t: null,
  onSearch(v) { clearTimeout(this._t); this._t = setTimeout(()=>{ this.state.search=v; this.state.page=1; this.load(); },400); },
  onFilter(k,v) { this.state[k]=v; this.state.page=1; this.load(); },
  async load() {
    const tbody = document.getElementById('emp-tbody');
    if (!tbody) return;
    tbody.innerHTML = `<tr class="loading-row"><td colspan="8"><span class="spinner"></span> Đang tải...</td></tr>`;
    try {
      const res = await api.get('/employees', {...this.state, per_page:15});
      tbody.innerHTML = res.data.map(e => `<tr>
        <td><div class="flex items-center gap-2"><div class="avatar-sm">${initial(e.full_name)}</div><span style="font-weight:600">${e.full_name}</span></div></td>
        <td><span style="font-family:monospace;color:var(--primary)">${e.username}</span></td>
        <td>${e.email}</td>
        <td>${e.position||'—'}</td>
        <td>${badge(e.role, {admin:'Admin',manager:'Manager',staff:'Staff'}[e.role])}</td>
        <td>${badge(e.status, {active:'Hoạt động',inactive:'Không HĐ',locked:'Đã khóa'}[e.status])}</td>
        <td>${fmtDate(e.last_login_at)}</td>
        <td><div class="action-cell">
          <button class="btn btn-secondary btn-sm" onclick="Employees.openEdit(${e.id})">Sửa</button>
          ${e.status==='locked'
            ? `<button class="btn btn-success btn-sm" onclick="Employees.toggle(${e.id},'unlock')">Mở khóa</button>`
            : `<button class="btn btn-danger btn-sm" onclick="Employees.toggle(${e.id},'lock')">Khóa</button>`}
          <button class="btn btn-secondary btn-sm" onclick="Employees.resetPwd(${e.id})">Mật khẩu</button>
        </div></td>
      </tr>`).join('') || `<tr class="empty-row"><td colspan="8">Không có dữ liệu</td></tr>`;
      if (res.pagination) renderPagination(document.getElementById('emp-pagination'), res.pagination, `(p)=>{Employees.state.page=p;Employees.load()}`);
    } catch(e) { toast(e.message,'error'); tbody.innerHTML=`<tr class="empty-row"><td colspan="8">${e.message}</td></tr>`; }
  },
  async openCreate() {
    const result = await openModal('Thêm nhân viên', `
      <div class="form-row">
        <div class="form-field"><label>Họ tên *</label><input name="full_name" placeholder="Nguyễn Văn A"></div>
        <div class="form-field"><label>Username *</label><input name="username" placeholder="nguyenvana"></div>
      </div>
      <div class="form-row">
        <div class="form-field"><label>Email *</label><input name="email" type="email" placeholder="email@domain.com"></div>
        <div class="form-field"><label>Số điện thoại</label><input name="phone" placeholder="0912345678"></div>
      </div>
      <div class="form-row">
        <div class="form-field"><label>Mật khẩu *</label><input name="password" type="password" placeholder="Tối thiểu 6 ký tự"></div>
        <div class="form-field"><label>Vai trò</label><select name="role">
          <option value="staff">Staff</option><option value="manager">Manager</option><option value="admin">Admin</option>
        </select></div>
      </div>
      <div class="form-field"><label>Vị trí công việc</label><input name="position" placeholder="VD: Sales Executive"></div>
    `);
    if (result !== 'save') return;
    const body = getFormData();
    if (!body.full_name||!body.username||!body.email||!body.password) { toast('Vui lòng điền đầy đủ thông tin bắt buộc','error'); return; }
    try { await api.post('/employees', body); toast('Thêm nhân viên thành công','success'); closeModal(); this.load(); }
    catch(e) { toast(e.message,'error'); }
  },
  async openEdit(id) {
    let e;
    try { e = (await api.get('/employees/'+id)).data; } catch(err) { toast(err.message,'error'); return; }
    const result = await openModal('Sửa nhân viên', `
      <div class="form-row">
        <div class="form-field"><label>Họ tên</label><input name="full_name" value="${e.full_name||''}"></div>
        <div class="form-field"><label>Số điện thoại</label><input name="phone" value="${e.phone||''}"></div>
      </div>
      <div class="form-row">
        <div class="form-field"><label>Email</label><input name="email" value="${e.email||''}"></div>
        <div class="form-field"><label>Vai trò</label><select name="role">
          <option value="staff" ${e.role==='staff'?'selected':''}>Staff</option>
          <option value="manager" ${e.role==='manager'?'selected':''}>Manager</option>
          <option value="admin" ${e.role==='admin'?'selected':''}>Admin</option>
        </select></div>
      </div>
      <div class="form-field"><label>Vị trí công việc</label><input name="position" value="${e.position||''}"></div>
    `);
    if (result !== 'save') return;
    const body = getFormData();
    try { await api.put('/employees/'+id, body); toast('Cập nhật thành công','success'); closeModal(); this.load(); }
    catch(err) { toast(err.message,'error'); }
  },
  async toggle(id, action) {
    const label = action==='lock' ? 'khóa' : 'mở khóa';
    if (!await confirmDialog(`Bạn có chắc muốn ${label} tài khoản này?`)) return;
    try { await api.patch(`/employees/${id}/${action}`, {}); toast(`Đã ${label} tài khoản thành công`,'success'); this.load(); }
    catch(e) { toast(e.message,'error'); }
  },
  async resetPwd(id) {
    const result = await openModal('Đặt lại mật khẩu', `
      <div class="form-field"><label>Mật khẩu mới *</label><input name="new_password" type="password" placeholder="Tối thiểu 6 ký tự"></div>
    `, 'Đặt lại');
    if (result !== 'save') return;
    const { new_password } = getFormData();
    if (!new_password || new_password.length < 6) { toast('Mật khẩu phải có ít nhất 6 ký tự','error'); return; }
    try { await api.patch(`/employees/${id}/reset-password`, { new_password }); toast('Đặt lại mật khẩu thành công','success'); closeModal(); }
    catch(e) { toast(e.message,'error'); }
  },
};
