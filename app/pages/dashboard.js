// ──────────────────────────────────────────────────────────
// dashboard.js — Thống kê & Báo cáo (Clean Version)
// ──────────────────────────────────────────────────────────

const Dashboard = {
  _charts: [],

  _destroyCharts() {
    this._charts.forEach(c => { try { c.destroy(); } catch(e) {} });
    this._charts = [];
  },

  async init() {
    this._destroyCharts();
    const pc = document.getElementById('pageContent');

    // Tải HTML skeleton từ file template riêng
    pc.innerHTML = await loadTemplate('dashboard');

    try {
      const res = await api.get('/statistics/dashboard');
      const s = res.data;

      const kpis = [
        { label: 'Tổng khách hàng', val: s.total_customers, sub: 'Đang hoạt động', color: '#4361ee' },
        { label: 'Doanh thu', val: fmtMoney(s.total_revenue), sub: 'Đơn hoàn thành', color: '#16a34a' },
        { label: 'Đơn hàng', val: s.total_orders, sub: (s.orders_pending || 0) + ' đang chờ', color: '#d97706' },
        { label: 'Hỗ trợ', val: (s.pending_return_exchange || 0) + (s.open_conversations || 0), sub: 'Yêu cầu chờ xử lý', color: '#dc2626' }
      ];

      document.getElementById('dStatsGrid').innerHTML = kpis.map(c => `
        <div class="stat-card" style="border-left-color:${c.color}">
          <div class="stat-label">${c.label}</div>
          <div class="stat-value" style="color:${c.color}" title="${c.val}">${c.val}</div>
          <div class="stat-sub">${c.sub}</div>
        </div>`).join('');

      // Charts
      this._renderRevenueChart(s.monthly_orders || []);
      this._renderTierChart(s.customers_by_tier || []);

      // Table
      const tops = s.top_customers || [];
      document.getElementById('dTopCustomers').innerHTML = tops.length ? tops.map((c, i) => `
        <tr>
          <td style="text-align:center;width:40px">${i + 1}</td>
          <td style="font-weight:600">${c.full_name}</td>
          <td>${c.phone}</td>
          <td style="text-align:center">${c.order_count}</td>
          <td style="text-align:right;font-weight:600;color:var(--success)">${fmtMoney(c.total_spent)}</td>
        </tr>`).join('') : '<tr><td colspan="5" class="text-center">Chưa có dữ liệu</td></tr>';

    } catch(e) { toast('Lỗi tải thống kê: ' + e.message, 'error'); }
  },

  _renderRevenueChart(data) {
    const el = document.getElementById('revenueChart');
    if (!data.length) return el.parentElement.innerHTML = '<p class="text-muted text-center p-4">Chưa có dữ liệu</p>';
    
    this._charts.push(new Chart(el, {
      type: 'bar',
      data: {
        labels: data.map(m => 'Tháng ' + parseInt(m.month.split('-')[1])),
        datasets: [{
          data: data.map(m => parseFloat(m.revenue) || 0),
          backgroundColor: '#4361ee',
          borderRadius: 4
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => v >= 1e6 ? (v/1e6).toFixed(0) + 'Tr' : v } } }
      }
    }));
  },

  _renderTierChart(data) {
    const el = document.getElementById('tierChart');
    if (!data.length) return el.parentElement.innerHTML = '<p class="text-muted text-center p-4">Chưa có dữ liệu</p>';

    this._charts.push(new Chart(el, {
      type: 'doughnut',
      data: {
        labels: data.map(t => t.tier || 'Khác'),
        datasets: [{
          data: data.map(t => parseInt(t.count) || 0),
          backgroundColor: ['#CD7F32','#6B7280','#EAB308','#8B5CF6','#06B6D4']
        }]
      },
      options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { position: 'bottom' } } }
    }));
  }
};
