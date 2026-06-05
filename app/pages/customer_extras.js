// ──────────────────────────────────────────────────────────
// customer_extras.js — Nhóm KH, Sinh nhật, Lịch nhắc
// ──────────────────────────────────────────────────────────

// ── NHÓM KHÁCH HÀNG ───────────────────────────────────────
const CustomerGroups = {
  async init() { await this.render(); this.load(); },

  async render() {
    document.getElementById('pageContent').innerHTML = await loadTemplate('groups');
  },

  async load() {
    const grid = document.getElementById('cg-grid');
    if (!grid) return;
    try {
      const res = await api.get('/customer-groups');
      if (!res.data.length) {
        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--muted)">Chưa có nhóm nào. Tạo nhóm đầu tiên!</div>';
        return;
      }
      grid.innerHTML = res.data.map(g => `
        <div class="card" style="cursor:default">
          <div class="card-header" style="border-left:4px solid ${g.color}">
            <h3 style="flex:1">${g.name}</h3>
            <span class="badge badge-blue">${g.customer_count} KH</span>
          </div>
          <div style="padding:14px 20px">
            <div style="font-size:12px;color:var(--muted);margin-bottom:12px">${g.description || 'Không có mô tả'}</div>
            <div style="display:flex;gap:8px">
              <button class="btn btn-secondary btn-sm" onclick="CustomerGroups.viewMembers(${g.id},'${g.name}')">Xem KH</button>
              <button class="btn btn-secondary btn-sm" onclick="CustomerGroups.openEdit(${g.id})">Sửa</button>
              <button class="btn btn-danger btn-sm" onclick="CustomerGroups.delete(${g.id},'${g.name}')">Xóa</button>
            </div>
          </div>
        </div>`).join('');
    } catch(e) { toast(e.message, 'error'); }
  },

  async openCreate() {
    const result = await openModal('Tạo nhóm khách hàng', `
      <div class="form-field"><label>Tên nhóm *</label><input name="name" placeholder="VD: Khách Hà Nội"></div>
      <div class="form-field"><label>Mô tả</label><textarea name="description" placeholder="Mô tả nhóm..."></textarea></div>
      <div class="form-field"><label>Màu sắc</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          ${['#2563eb','#16a34a','#d97706','#dc2626','#6d28d9','#0891b2','#be185d'].map(c =>
            `<span onclick="document.querySelector('[name=color]').value='${c}';document.querySelectorAll('.color-swatch').forEach(e=>e.style.outline='none');this.style.outline='3px solid #000'"
              class="color-swatch" style="width:24px;height:24px;border-radius:50%;background:${c};cursor:pointer;display:inline-block"></span>`
          ).join('')}
          <input name="color" type="color" value="#2563eb" style="width:30px;height:24px;border-radius:4px;border:1px solid var(--border2);cursor:pointer">
        </div>
      </div>
    `);
    if (result !== 'save') return;
    const body = getFormData();
    if (!body.name) { toast('Vui lòng nhập tên nhóm', 'error'); return; }
    try { await api.post('/customer-groups', body); toast('Tạo nhóm thành công', 'success'); closeModal(); this.load(); }
    catch(e) { toast(e.message, 'error'); }
  },

  async openEdit(id) {
    let g;
    try { g = (await api.get('/customer-groups/' + id)).data; } catch(e) { toast(e.message,'error'); return; }
    const result = await openModal('Sửa nhóm', `
      <div class="form-field"><label>Tên nhóm</label><input name="name" value="${g.name}"></div>
      <div class="form-field"><label>Mô tả</label><textarea name="description">${g.description||''}</textarea></div>
      <div class="form-field"><label>Màu</label><input name="color" type="color" value="${g.color||'#2563eb'}"></div>
    `);
    if (result !== 'save') return;
    const body = getFormData();
    try { await api.put('/customer-groups/' + id, body); toast('Cập nhật thành công', 'success'); closeModal(); this.load(); }
    catch(e) { toast(e.message, 'error'); }
  },

  async delete(id, name) {
    if (!await confirmDialog(`Xóa nhóm "${name}"? Các khách hàng sẽ không bị xóa, chỉ bị bỏ khỏi nhóm.`)) return;
    try { await api.delete('/customer-groups/' + id); toast('Đã xóa nhóm', 'success'); this.load(); }
    catch(e) { toast(e.message, 'error'); }
  },

  async viewMembers(id, name) {
    let g;
    try { g = (await api.get('/customer-groups/' + id)).data; } catch(e) { toast(e.message,'error'); return; }
    const rows = g.customers || [];
    await openModal(`Thành viên nhóm: ${name}`, `
      <div style="margin-bottom:12px;color:var(--muted);font-size:13px">${rows.length} khách hàng</div>
      ${rows.length ? `<div class="table-wrap"><table>
        <thead><tr><th>Tên</th><th>SĐT</th><th>Hạng</th><th></th></tr></thead>
        <tbody>${rows.map(c => `<tr>
          <td style="font-weight:600">${c.full_name}</td>
          <td>${c.phone||'—'}</td>
          <td>${c.tier_name ? `<span class="badge badge-purple">${c.tier_name}</span>` : '—'}</td>
          <td><button class="btn btn-danger btn-sm" onclick="CustomerGroups._remove(${id},${c.id})">Xóa</button></td>
        </tr>`).join('')}</tbody>
      </table></div>` : '<div style="text-align:center;padding:20px;color:var(--muted)">Nhóm chưa có thành viên</div>'}
    `, 'Đóng');
  },

  async _remove(groupId, custId) {
    try { await api.post(`/customer-groups/${groupId}/remove-customer`, { customer_id: custId }); toast('Đã xóa khỏi nhóm','success'); closeModal(); this.load(); }
    catch(e) { toast(e.message,'error'); }
  },
};


// ── SINH NHẬT THÁNG NÀY ──────────────────────────────────
const Birthdays = {
  _month: new Date().getMonth() + 1,

  async init() { await this.render(); this.load(); },

  async render() {
    document.getElementById('pageContent').innerHTML = await loadTemplate('birthdays');
    // Đồng bộ tháng được chọn vào select
    const sel = document.getElementById('bday-month-sel');
    if (sel) sel.value = this._month;
  },

  _changeMonth(m) { this._month = parseInt(m); this.load(); },

  async load() {
    const el = document.getElementById('bday-body');
    if (!el) return;
    try {
      const res  = await api.get('/customers/birthdays', { month: this._month });
      const today = new Date().getDate();
      const thisMonth = new Date().getMonth() + 1;

      if (!res.data.length) {
        el.innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted)">Không có khách hàng nào có sinh nhật trong tháng này</div>';
        return;
      }
      el.innerHTML = `<div class="table-wrap"><table>
        <thead><tr><th>Ngày</th><th>Khách hàng</th><th>SĐT</th><th>Email</th><th>Hạng</th><th>NV phụ trách</th><th></th></tr></thead>
        <tbody>${res.data.map(c => {
          const isToday = c.birth_day == today && this._month == thisMonth;
          return `<tr style="${isToday?'background:var(--warning-l)':''}">
            <td style="font-weight:700;color:var(--primary)">${c.birth_day}</td>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div class="avatar-sm">${initial(c.full_name)}</div>
                <div>
                  <div style="font-weight:600">${c.full_name}</div>
                  ${isToday ? '<div style="font-size:11px;color:var(--warning);font-weight:600">🎂 Hôm nay!</div>' : ''}
                </div>
              </div>
            </td>
            <td>${c.phone||'—'}</td>
            <td>${c.email||'—'}</td>
            <td>${c.tier_name ? `<span class="badge badge-purple">${c.tier_name}</span>` : '—'}</td>
            <td>${c.assigned_employee_name||'—'}</td>
            <td><button class="btn btn-primary btn-sm" onclick="Birthdays.createNote(${c.id},'${c.full_name}')">Ghi nhớ</button></td>
          </tr>`;
        }).join('')}</tbody>
      </table></div>`;
    } catch(e) { el.innerHTML = `<div style="padding:20px;color:var(--danger)">${e.message}</div>`; }
  },

  async createNote(id, name) {
    const result = await openModal(`Ghi nhớ sinh nhật — ${name}`, `
      <div class="form-field"><label>Nội dung ghi nhớ</label>
        <textarea name="content" placeholder="VD: Đã gọi chúc mừng sinh nhật, tặng voucher 10%...">Chúc mừng sinh nhật ${name}!</textarea>
      </div>
    `);
    if (result !== 'save') return;
    const body = getFormData();
    try {
      await api.post(`/customers/${id}/activities`, {
        type: 'note', title: `Chúc mừng sinh nhật ${name}`, content: body.content
      });
      toast('Đã ghi nhớ thành công', 'success'); closeModal();
    } catch(e) { toast(e.message, 'error'); }
  },
};


// ── LỊCH NHẮC VIỆC (UPCOMING TASKS) ──────────────────────
const UpcomingTasks = {
  async init() { await this.render(); this.load(); },

  async render() {
    document.getElementById('pageContent').innerHTML = await loadTemplate('tasks');
  },

  async load() {
    const el = document.getElementById('task-upcoming');
    if (!el) return;
    try {
      const res = await api.get('/tasks/upcoming');
      if (!res.data.length) {
        el.innerHTML = '<div style="padding:24px;text-align:center;color:var(--muted)">Không có lịch nhắc nào trong 7 ngày tới</div>';
        return;
      }
      const prioMap = { high: ['badge-red','Cao'], normal: ['badge-yellow','Thường'], low: ['badge-gray','Thấp'] };
      el.innerHTML = res.data.map(t => {
        const [pc, pl] = prioMap[t.priority] || ['badge-gray', t.priority];
        const due = new Date(t.due_date.replace(' ','T'));
        const isOverdue = due < new Date();
        return `<div style="padding:12px 20px;border-bottom:1px solid var(--border);display:flex;gap:10px;align-items:flex-start">
          <div style="flex:1">
            <div style="font-weight:600;font-size:13px">${t.title}</div>
            <div style="font-size:12px;color:var(--muted);margin-top:2px">${t.customer_name} · ${t.customer_phone||''}</div>
            <div style="font-size:11px;margin-top:4px;color:${isOverdue?'var(--danger)':'var(--text2)'};font-weight:${isOverdue?'700':'400'}">
              ${isOverdue ? '⚠ Quá hạn: ' : ''}${fmtDate(t.due_date)}
            </div>
          </div>
          <span class="badge ${pc}">${pl}</span>
        </div>`;
      }).join('');
    } catch(e) { el.innerHTML = `<div style="padding:16px;color:var(--danger)">${e.message}</div>`; }
  },
};
