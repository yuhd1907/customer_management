// TOAST
const toasts = document.getElementById('toasts');
function toast(msg, type = 'info') {
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.textContent = msg;
  toasts.appendChild(el);
  setTimeout(() => { el.classList.add('hide'); setTimeout(() => el.remove(), 250); }, 3000);
}

// MODAL
let modalResolve = null;
const modalOverlay = document.getElementById('modalOverlay');
const modalTitle   = document.getElementById('modalTitle');
const modalContent = document.getElementById('modalContent');
const modalSaveBtn = document.getElementById('modalSave');

function openModal(title, html, saveLabel = 'Lưu') {
  modalTitle.textContent = title;
  modalContent.innerHTML = html;
  modalSaveBtn.textContent = saveLabel;
  modalOverlay.classList.add('open');
  return new Promise(resolve => { modalResolve = resolve; });
}
function closeModal(result = null) {
  modalOverlay.classList.remove('open');
  if (modalResolve) { modalResolve(result); modalResolve = null; }
}
document.getElementById('modalClose').onclick  = () => closeModal();
document.getElementById('modalCancel').onclick = () => closeModal();
modalSaveBtn.onclick = () => closeModal('save');
modalOverlay.onclick = e => { if (e.target === modalOverlay) closeModal(); };

function getFormData(selector = '#modalContent') {
  const data = {};
  document.querySelectorAll(`${selector} [name]`).forEach(el => { data[el.name] = el.value.trim(); });
  return data;
}
function confirmDialog(msg) {
  return new Promise(resolve => resolve(window.confirm(msg)));
}

// PAGINATION
function renderPagination(container, pagination, onPageChange) {
  const { total, per_page, current_page, last_page } = pagination;
  const from = (current_page - 1) * per_page + 1;
  const to   = Math.min(current_page * per_page, total);
  let html = `<div class="pagination">
    <span class="pagination-info">Hiển thị ${from}–${to} / ${total} bản ghi</span>
    <button class="page-btn" ${current_page===1?'disabled':''} onclick="(${onPageChange})(${current_page-1})">&#8249;</button>`;
  let pages = [];
  for (let i = 1; i <= last_page; i++) {
    if (i===1||i===last_page||Math.abs(i-current_page)<=2) pages.push(i);
    else if (pages[pages.length-1]!=='…') pages.push('…');
  }
  pages.forEach(p => {
    if (p==='…') html += `<button class="page-btn" disabled style="border:none;background:none">…</button>`;
    else html += `<button class="page-btn ${p===current_page?'active':''}" onclick="(${onPageChange})(${p})">${p}</button>`;
  });
  html += `<button class="page-btn" ${current_page===last_page?'disabled':''} onclick="(${onPageChange})(${current_page+1})">&#8250;</button></div>`;
  container.innerHTML = html;
}

// BADGE HELPERS
const STATUS_CLASS = {
  active:'badge-green', inactive:'badge-gray', blocked:'badge-red', locked:'badge-red',
  admin:'badge-purple', manager:'badge-blue', staff:'badge-yellow',
  pending:'badge-yellow', confirmed:'badge-blue', shipping:'badge-blue',
  completed:'badge-green', cancelled:'badge-red', returned:'badge-red',
  open:'badge-green', closed:'badge-gray', approved:'badge-green', rejected:'badge-red',
};
function badge(val, label) {
  return `<span class="badge ${STATUS_CLASS[val]||'badge-gray'}">${label||val}</span>`;
}
function fmtDate(d)  { return d ? new Date(d).toLocaleDateString('vi-VN') : '—'; }
function fmtMoney(n) { return n ? Number(n).toLocaleString('vi-VN') + ' ₫' : '—'; }
function initial(name) { return (name||'?')[0].toUpperCase(); }
