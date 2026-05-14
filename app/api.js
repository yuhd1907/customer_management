const API_BASE = '/customer_management/api';

const api = {
  BASE: API_BASE,
  token: () => localStorage.getItem('crm_token'),

  async req(method, path, body, params) {
    let url = API_BASE + path;
    if (params) {
      const q = new URLSearchParams(Object.fromEntries(Object.entries(params).filter(([,v]) => v !== '' && v != null)));
      if ([...q].length) url += '?' + q;
    }
    const headers = { 'Content-Type': 'application/json' };
    if (this.token()) headers['Authorization'] = 'Bearer ' + this.token();
    const opts = { method, headers };
    if (body && !['GET','DELETE'].includes(method)) opts.body = JSON.stringify(body);
    const res = await fetch(url, opts);
    const data = await res.json();
    if (res.status === 401) {
      localStorage.removeItem('crm_token');
      window.location.reload();
      throw { status: 401, message: 'Phiên đăng nhập hết hạn' };
    }
    if (!res.ok) throw { status: res.status, message: data.message || 'Lỗi không xác định' };
    return data;
  },

  get:    (path, params) => api.req('GET',    path, null, params),
  post:   (path, body)   => api.req('POST',   path, body),
  put:    (path, body)   => api.req('PUT',    path, body),
  patch:  (path, body)   => api.req('PATCH',  path, body),
  delete: (path)         => api.req('DELETE', path),
};
