const Products = {
  state: { page: 1, search: '', status: '' },
  init() { this.render(); this.load(); },
  render() {
    document.getElementById('pageContent').innerHTML = `
      <div class="page-header">
        <h2>Quản lý Sản phẩm</h2>
        <div class="page-header-actions">
          <button class="btn btn-primary btn-sm" onclick="Products.openCreate()">+ Thêm sản phẩm</button>
        </div>
      </div>
      <div class="card">
        <div class="search-bar">
          <input class="search-input" placeholder="Tìm kiếm tên sản phẩm, SKU..." oninput="Products.onSearch(this.value)">
          <select class="filter-select" onchange="Products.onFilter('status',this.value)">
            <option value="">Tất cả</option><option value="active">Đang bán</option><option value="inactive">Ngừng bán</option>
          </select>
        </div>
        <div class="table-wrap">
          <table><thead><tr>
            <th>Sản phẩm</th><th>SKU</th><th>Giá bán</th><th>Tồn kho</th><th>Trạng thái</th><th style="text-align:right">Thao tác</th>
          </tr></thead><tbody id="prod-tbody"></tbody></table>
        </div>
        <div id="prod-pagination"></div>
      </div>`;
  },
  _t: null,
  onSearch(v) { clearTimeout(this._t); this._t = setTimeout(()=>{ this.state.search=v; this.state.page=1; this.load(); },400); },
  onFilter(k,v) { this.state[k]=v; this.state.page=1; this.load(); },
  async load() {
    const tbody = document.getElementById('prod-tbody');
    if (!tbody) return;
    tbody.innerHTML = `<tr class="loading-row"><td colspan="6"><span class="spinner"></span> Đang tải...</td></tr>`;
    try {
      const res = await api.get('/products', {...this.state, per_page:15});
      tbody.innerHTML = res.data.map(p => `<tr>
        <td><div style="font-weight:600">${p.name}</div><div class="text-muted" style="font-size:11px">${(p.description||'').slice(0,60)}</div></td>
        <td><span style="font-family:monospace;color:var(--primary)">${p.sku||'—'}</span></td>
        <td style="font-weight:600">${fmtMoney(p.price)}</td>
        <td style="color:${p.stock_quantity<5?'var(--danger)':'inherit'};font-weight:${p.stock_quantity<5?'700':'400'}">${p.stock_quantity}</td>
        <td>${badge(p.status, {active:'Đang bán',inactive:'Ngừng bán'}[p.status])}</td>
        <td><div class="action-cell">
          <button class="btn btn-secondary btn-sm" onclick="Products.openEdit(${p.id})">Sửa</button>
          <button class="btn btn-danger btn-sm" onclick="Products.delete(${p.id},'${p.name}')">Xóa</button>
        </div></td>
      </tr>`).join('') || `<tr class="empty-row"><td colspan="6">Không có dữ liệu</td></tr>`;
      if (res.pagination) renderPagination(document.getElementById('prod-pagination'), res.pagination, `(p)=>{Products.state.page=p;Products.load()}`);
    } catch(e) { toast(e.message,'error'); tbody.innerHTML=`<tr class="empty-row"><td colspan="6">${e.message}</td></tr>`; }
  },
  async openCreate() {
    const result = await openModal('Thêm sản phẩm', `
      <div class="form-field"><label>Tên sản phẩm *</label><input name="name" placeholder="VD: iPhone 15 Pro Max"></div>
      <div class="form-row">
        <div class="form-field"><label>Mã SKU</label><input name="sku" placeholder="VD: IPH-15PM"></div>
        <div class="form-field"><label>Giá bán (VND) *</label><input name="price" type="number" placeholder="33990000"></div>
      </div>
      <div class="form-row">
        <div class="form-field"><label>Số lượng tồn kho</label><input name="stock_quantity" type="number" value="0"></div>
        <div class="form-field"><label>Trạng thái</label><select name="status">
          <option value="active">Đang bán</option><option value="inactive">Ngừng bán</option>
        </select></div>
      </div>
      <div class="form-field"><label>Mô tả</label><textarea name="description" placeholder="Mô tả sản phẩm..."></textarea></div>
    `);
    if (result !== 'save') return;
    const body = getFormData();
    if (!body.name||!body.price) { toast('Vui lòng nhập tên và giá sản phẩm','error'); return; }
    try { await api.post('/products', body); toast('Thêm sản phẩm thành công','success'); closeModal(); this.load(); }
    catch(e) { toast(e.message,'error'); }
  },
  async openEdit(id) {
    let p;
    try { p = (await api.get('/products/'+id)).data; } catch(e) { toast(e.message,'error'); return; }
    const result = await openModal('Sửa sản phẩm', `
      <div class="form-field"><label>Tên sản phẩm</label><input name="name" value="${p.name||''}"></div>
      <div class="form-row">
        <div class="form-field"><label>Giá bán (VND)</label><input name="price" type="number" value="${p.price||''}"></div>
        <div class="form-field"><label>Tồn kho</label><input name="stock_quantity" type="number" value="${p.stock_quantity||0}"></div>
      </div>
      <div class="form-row">
        <div class="form-field"><label>Mã SKU</label><input name="sku" value="${p.sku||''}"></div>
        <div class="form-field"><label>Trạng thái</label><select name="status">
          <option value="active" ${p.status==='active'?'selected':''}>Đang bán</option>
          <option value="inactive" ${p.status==='inactive'?'selected':''}>Ngừng bán</option>
        </select></div>
      </div>
      <div class="form-field"><label>Mô tả</label><textarea name="description">${p.description||''}</textarea></div>
    `);
    if (result !== 'save') return;
    const body = getFormData();
    try { await api.put('/products/'+id, body); toast('Cập nhật thành công','success'); closeModal(); this.load(); }
    catch(e) { toast(e.message,'error'); }
  },
  async delete(id, name) {
    if (!await confirmDialog(`Xóa sản phẩm "${name}"?`)) return;
    try { await api.delete('/products/'+id); toast('Đã xóa sản phẩm','success'); this.load(); }
    catch(e) { toast(e.message,'error'); }
  },
};

const Orders = {
  state: { page: 1, status: '' },
  _items: [],
  init() { this._items=[]; this.render(); this.load(); },
  render() {
    document.getElementById('pageContent').innerHTML = `
      <div class="page-header"><h2>Quản lý Đơn hàng</h2>
        <div class="page-header-actions"><button class="btn btn-primary btn-sm" onclick="Orders.openCreate()">+ Tạo đơn hàng</button></div>
      </div>
      <div class="card">
        <div class="search-bar">
          <select class="filter-select" onchange="Orders.onFilter('status',this.value)">
            <option value="">Tất cả trạng thái</option>
            <option value="pending">Chờ xử lý</option><option value="confirmed">Đã xác nhận</option>
            <option value="shipping">Đang giao</option><option value="completed">Hoàn thành</option>
            <option value="cancelled">Đã hủy</option>
          </select>
        </div>
        <div class="table-wrap">
          <table><thead><tr>
            <th>Mã đơn</th><th>Khách hàng</th><th>Nhân viên</th><th>Tổng tiền</th><th>Trạng thái</th><th>Ngày tạo</th><th style="text-align:right">Cập nhật TT</th>
          </tr></thead><tbody id="ord-tbody"></tbody></table>
        </div>
        <div id="ord-pagination"></div>
      </div>`;
  },
  onFilter(k,v) { this.state[k]=v; this.state.page=1; this.load(); },
  async load() {
    const tbody = document.getElementById('ord-tbody');
    if (!tbody) return;
    tbody.innerHTML = `<tr class="loading-row"><td colspan="7"><span class="spinner"></span> Đang tải...</td></tr>`;
    try {
      const res = await api.get('/orders', {...this.state, per_page:15});
      const statusLabel = {pending:'Chờ xử lý',confirmed:'Đã xác nhận',shipping:'Đang giao',completed:'Hoàn thành',cancelled:'Đã hủy',returned:'Đã trả hàng'};
      tbody.innerHTML = res.data.map(o => `<tr>
        <td><span style="font-family:monospace;font-weight:600;color:var(--primary)">${o.order_code}</span></td>
        <td><div style="font-weight:500">${o.customer_name||'—'}</div><div class="text-muted" style="font-size:11px">${o.customer_phone||''}</div></td>
        <td>${o.employee_name||'—'}</td>
        <td style="font-weight:600">${fmtMoney(o.total_amount)}</td>
        <td>${badge(o.status, statusLabel[o.status])}</td>
        <td>${fmtDate(o.created_at)}</td>
        <td><div class="action-cell">
          <select class="filter-select" style="font-size:12px" onchange="Orders.changeStatus(${o.id},this.value)">
            <option value="">Đổi trạng thái...</option>
            ${Object.entries(statusLabel).map(([k,v])=>`<option value="${k}">${v}</option>`).join('')}
          </select>
        </div></td>
      </tr>`).join('') || `<tr class="empty-row"><td colspan="7">Không có đơn hàng</td></tr>`;
      if (res.pagination) renderPagination(document.getElementById('ord-pagination'), res.pagination, `(p)=>{Orders.state.page=p;Orders.load()}`);
    } catch(e) { toast(e.message,'error'); tbody.innerHTML=`<tr class="empty-row"><td colspan="7">${e.message}</td></tr>`; }
  },
  async changeStatus(id, status) {
    if (!status) return;
    try { await api.patch('/orders/'+id+'/status', {status}); toast('Cập nhật trạng thái thành công','success'); this.load(); }
    catch(e) { toast(e.message,'error'); }
  },
  async openCreate() {
    this._items=[];
    let customers=[], products=[];
    try { customers=(await api.get('/customers',{per_page:200})).data||[]; } catch(e){}
    try { products=(await api.get('/products',{status:'active',per_page:200})).data||[]; } catch(e){}
    const cOpts=customers.map(c=>`<option value="${c.id}">${c.full_name} — ${c.phone||''}</option>`).join('');
    const pOpts=products.map(p=>`<option value="${p.id}" data-price="${p.price}">${p.name} (${fmtMoney(p.price)})</option>`).join('');
    const result = await openModal('Tạo đơn hàng mới', `
      <div class="form-field"><label>Khách hàng *</label><select name="customer_id"><option value="">— Chọn khách hàng —</option>${cOpts}</select></div>
      <div class="form-field"><label>Sản phẩm *</label>
        <div style="display:flex;gap:8px;margin-bottom:8px">
          <select id="op-sel" style="flex:1;border:1px solid var(--border2);border-radius:7px;padding:8px 12px;font-size:13px"><option value="">— Chọn sản phẩm —</option>${pOpts}</select>
          <input id="op-qty" type="number" value="1" min="1" style="width:70px;border:1px solid var(--border2);border-radius:7px;padding:8px;font-size:13px">
          <button class="btn btn-secondary btn-sm" type="button" onclick="Orders._add()">Thêm</button>
        </div>
        <div id="op-list"></div>
      </div>
      <div class="form-field"><label>Ghi chú</label><textarea name="note" placeholder="Ghi chú đơn hàng..."></textarea></div>
    `);
    if (result !== 'save') return;
    const body=getFormData();
    if (!body.customer_id) { toast('Vui lòng chọn khách hàng','error'); return; }
    if (!this._items.length) { toast('Vui lòng thêm ít nhất 1 sản phẩm','error'); return; }
    body.items=this._items;
    try { await api.post('/orders',body); toast('Tạo đơn hàng thành công','success'); closeModal(); this._items=[]; this.load(); }
    catch(e) { toast(e.message,'error'); }
  },
  _add() {
    const s=document.getElementById('op-sel'), q=parseInt(document.getElementById('op-qty').value)||1;
    if (!s||!s.value) { toast('Chọn sản phẩm trước','error'); return; }
    const price=parseFloat(s.options[s.selectedIndex].dataset.price)||0, name=s.options[s.selectedIndex].text;
    const ex=this._items.find(i=>i.product_id==s.value);
    if (ex) ex.quantity+=q; else this._items.push({product_id:parseInt(s.value),quantity:q,_name:name,_price:price});
    const el=document.getElementById('op-list'); if (!el) return;
    el.innerHTML=this._items.map((i,idx)=>`<div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid var(--border)"><span style="flex:1;font-size:13px">${i._name}</span><span>x${i.quantity}</span><span style="color:var(--primary);font-weight:600;min-width:90px;text-align:right">${fmtMoney(i._price*i.quantity)}</span><button class="btn btn-danger btn-sm" type="button" onclick="Orders._items.splice(${idx},1);Orders._add()">Xóa</button></div>`).join('');
  },
  async cancel(id) {
    if (!await confirmDialog('Hủy đơn hàng này?')) return;
    try { await api.delete('/orders/'+id); toast('Đã hủy đơn hàng','success'); this.load(); }
    catch(e) { toast(e.message,'error'); }
  },
};

const Tiers = {
  init() { this.render(); this.load(); },
  render() {
    document.getElementById('pageContent').innerHTML = `
      <div class="page-header">
        <h2>Hạng Khách hàng</h2>
        <div class="page-header-actions">
          <button class="btn btn-primary btn-sm" onclick="Tiers.openCreate()">+ Thêm hạng</button>
        </div>
      </div>
      <div class="card">
        <div class="table-wrap">
          <table><thead><tr>
            <th>Tên hạng</th><th>Mô tả</th><th>Chiết khấu</th><th>Số khách hàng</th><th style="text-align:right">Thao tác</th>
          </tr></thead><tbody id="tier-tbody"></tbody></table>
        </div>
      </div>`;
  },
  async load() {
    const tbody = document.getElementById('tier-tbody');
    if (!tbody) return;
    tbody.innerHTML = `<tr class="loading-row"><td colspan="5"><span class="spinner"></span> Đang tải...</td></tr>`;
    try {
      const res = await api.get('/customer-tiers');
      tbody.innerHTML = res.data.map(t => `<tr>
        <td style="font-weight:600">${t.name}</td>
        <td>${t.description||'—'}</td>
        <td style="color:var(--success);font-weight:600">${t.discount_percent}%</td>
        <td>${t.customer_count} khách hàng</td>
        <td><div class="action-cell">
          <button class="btn btn-secondary btn-sm" onclick="Tiers.openEdit(${t.id},'${t.name}',${t.discount_percent},'${t.description||''}')">Sửa</button>
          <button class="btn btn-danger btn-sm" onclick="Tiers.delete(${t.id},'${t.name}')">Xóa</button>
        </div></td>
      </tr>`).join('') || `<tr class="empty-row"><td colspan="5">Chưa có hạng nào</td></tr>`;
    } catch(e) { toast(e.message,'error'); }
  },
  async openCreate() {
    const result = await openModal('Thêm hạng khách hàng', `
      <div class="form-field"><label>Tên hạng *</label><input name="name" placeholder="VD: Vàng, Bạch Kim..."></div>
      <div class="form-field"><label>Chiết khấu (%)</label><input name="discount_percent" type="number" value="0" min="0" max="100"></div>
      <div class="form-field"><label>Mô tả</label><textarea name="description" placeholder="Mô tả về hạng khách hàng..."></textarea></div>
    `);
    if (result !== 'save') return;
    const body = getFormData();
    if (!body.name) { toast('Vui lòng nhập tên hạng','error'); return; }
    try { await api.post('/customer-tiers', body); toast('Thêm hạng thành công','success'); closeModal(); this.load(); }
    catch(e) { toast(e.message,'error'); }
  },
  async openEdit(id, name, discount, desc) {
    const result = await openModal('Sửa hạng khách hàng', `
      <div class="form-field"><label>Tên hạng</label><input name="name" value="${name}"></div>
      <div class="form-field"><label>Chiết khấu (%)</label><input name="discount_percent" type="number" value="${discount}" min="0" max="100"></div>
      <div class="form-field"><label>Mô tả</label><textarea name="description">${desc}</textarea></div>
    `);
    if (result !== 'save') return;
    const body = getFormData();
    try { await api.put('/customer-tiers/'+id, body); toast('Cập nhật thành công','success'); closeModal(); this.load(); }
    catch(e) { toast(e.message,'error'); }
  },
  async delete(id, name) {
    if (!await confirmDialog(`Xóa hạng "${name}"?`)) return;
    try { await api.delete('/customer-tiers/'+id); toast('Đã xóa hạng','success'); this.load(); }
    catch(e) { toast(e.message,'error'); }
  },
};

const Conversations = {
  state: { page: 1, status: '' },
  _empList: [],
  init() { this.render(); this.load(); },
  render() {
    document.getElementById('pageContent').innerHTML = `
      <div class="page-header"><h2>Hội thoại Khách hàng</h2>
        <div class="page-header-actions"><button class="btn btn-primary btn-sm" onclick="Conversations.openCreate()">+ Tạo hội thoại</button></div>
      </div>
      <div class="card">
        <div class="search-bar">
          <select class="filter-select" onchange="Conversations.onFilter('status',this.value)">
            <option value="">Tất cả</option><option value="open">Đang mở</option><option value="closed">Đã đóng</option>
          </select>
        </div>
        <div class="table-wrap">
          <table><thead><tr>
            <th>Tiêu đề</th><th>Khách hàng</th><th>Nhân viên</th><th>Loại</th><th>Trạng thái</th><th>Chưa đọc</th><th>Cập nhật</th><th style="text-align:right">Thao tác</th>
          </tr></thead><tbody id="conv-tbody"></tbody></table>
        </div>
        <div id="conv-pagination"></div>
      </div>`;
  },
  onFilter(k,v) { this.state[k]=v; this.state.page=1; this.load(); },
  async _loadEmps() {
    if (!this._empList.length) {
      try { this._empList=(await api.get('/employees',{per_page:200})).data||[]; } catch(e){}
    }
    return this._empList;
  },
  async load() {
    const tbody=document.getElementById('conv-tbody');
    if (!tbody) return;
    tbody.innerHTML=`<tr class="loading-row"><td colspan="8"><span class="spinner"></span> Đang tải...</td></tr>`;
    const tl={product_consulting:'Tư vấn sản phẩm',return_request:'Yêu cầu trả hàng',exchange_request:'Yêu cầu đổi hàng',general_support:'Hỗ trợ chung'};
    try {
      const res=await api.get('/conversations',{...this.state,per_page:15});
      tbody.innerHTML=res.data.map(c=>`<tr>
        <td style="font-weight:500">${c.title||'(Không có tiêu đề)'}</td>
        <td>${c.customer_name||'—'}</td>
        <td>${c.employee_name||'<span class="text-muted">Chưa phân công</span>'}</td>
        <td><span class="text-muted" style="font-size:12px">${tl[c.type]||c.type}</span></td>
        <td>${badge(c.status,{open:'Đang mở',closed:'Đã đóng'}[c.status])}</td>
        <td>${c.unread_count>0?`<span style="color:var(--danger);font-weight:700">${c.unread_count}</span>`:'0'}</td>
        <td>${fmtDate(c.updated_at)}</td>
        <td><div class="action-cell">
          <button class="btn btn-secondary btn-sm" onclick="Conversations.openEdit(${c.id})">Sửa</button>
          <button class="btn btn-secondary btn-sm" onclick="Conversations.assign(${c.id})">Gán NV</button>
          ${c.status==='open'?`<button class="btn btn-danger btn-sm" onclick="Conversations.close(${c.id})">Đóng</button>`:''}
        </div></td>
      </tr>`).join('')||`<tr class="empty-row"><td colspan="8">Không có hội thoại</td></tr>`;
      if (res.pagination) renderPagination(document.getElementById('conv-pagination'),res.pagination,`(p)=>{Conversations.state.page=p;Conversations.load()}`);
    } catch(e) { toast(e.message,'error'); tbody.innerHTML=`<tr class="empty-row"><td colspan="8">${e.message}</td></tr>`; }
  },
  async openCreate() {
    let customers=[];
    try { customers=(await api.get('/customers',{per_page:200})).data||[]; } catch(e){}
    const cOpts=customers.map(c=>`<option value="${c.id}">${c.full_name} — ${c.phone||''}</option>`).join('');
    const result=await openModal('Tạo hội thoại mới',`
      <div class="form-field"><label>Khách hàng *</label><select name="customer_id"><option value="">— Chọn —</option>${cOpts}</select></div>
      <div class="form-field"><label>Tiêu đề</label><input name="title" placeholder="Tiêu đề hội thoại..."></div>
      <div class="form-field"><label>Loại</label><select name="type">
        <option value="general_support">Hỗ trợ chung</option>
        <option value="product_consulting">Tư vấn sản phẩm</option>
        <option value="return_request">Yêu cầu trả hàng</option>
        <option value="exchange_request">Yêu cầu đổi hàng</option>
      </select></div>
    `);
    if (result!=='save') return;
    const body=getFormData();
    if (!body.customer_id) { toast('Vui lòng chọn khách hàng','error'); return; }
    try { await api.post('/conversations',body); toast('Tạo hội thoại thành công','success'); closeModal(); this.load(); }
    catch(e) { toast(e.message,'error'); }
  },
  async openEdit(id) {
    let c;
    try { c=(await api.get('/conversations/'+id)).data; } catch(e) { toast(e.message,'error'); return; }
    const tl={general_support:'Hỗ trợ chung',product_consulting:'Tư vấn sản phẩm',return_request:'Yêu cầu trả hàng',exchange_request:'Yêu cầu đổi hàng'};
    const result=await openModal('Sửa hội thoại',`
      <div class="form-field"><label>Tiêu đề</label><input name="title" value="${c.title||''}"></div>
      <div class="form-field"><label>Loại</label><select name="type">
        ${Object.entries(tl).map(([k,v])=>`<option value="${k}" ${c.type===k?'selected':''}>${v}</option>`).join('')}
      </select></div>
    `);
    if (result!=='save') return;
    const body=getFormData();
    try { await api.put('/conversations/'+id,body); toast('Cập nhật thành công','success'); closeModal(); this.load(); }
    catch(e) { toast(e.message,'error'); }
  },
  async assign(id) {
    const emps=await this._loadEmps();
    const eOpts=emps.map(e=>`<option value="${e.id}">${e.full_name} (${e.role})</option>`).join('');
    const result=await openModal('Gán nhân viên phụ trách',`
      <div class="form-field"><label>Chọn nhân viên</label><select name="employee_id"><option value="">— Chọn —</option>${eOpts}</select></div>
    `,'Gán');
    if (result!=='save') return;
    const {employee_id}=getFormData();
    if (!employee_id) { toast('Vui lòng chọn nhân viên','error'); return; }
    try { await api.patch('/conversations/'+id+'/assign',{employee_id}); toast('Gán nhân viên thành công','success'); closeModal(); this.load(); }
    catch(e) { toast(e.message,'error'); }
  },
  async close(id) {
    if (!await confirmDialog('Đóng hội thoại này?')) return;
    try { await api.patch('/conversations/'+id+'/close',{}); toast('Đã đóng hội thoại','success'); this.load(); }
    catch(e) { toast(e.message,'error'); }
  },
};
