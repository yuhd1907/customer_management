-- =====================================================
-- FIX: Cập nhật password_hash đúng cho các tài khoản
-- Admin@123  → admin@company.com
-- Manager@123 → manager@company.com
-- Staff@123  → staff@company.com
-- =====================================================

UPDATE employees SET password_hash = '$2y$12$EjY2CCKDItWvEE6GzxaMRObTsMigX6/0xYdxESV0uCimAGMnPd3pC'
WHERE email = 'admin@company.com';

UPDATE employees SET password_hash = '$2y$12$oTmXF73QszU3ZdLw1Ip2hexl41wvgtFiriVhny4pBysdiwcPzmXjS'
WHERE email = 'manager@company.com';

UPDATE employees SET password_hash = '$2y$12$acuwvkJ/Km7dblLSQa7pzu9/G/nYmeh/9ZPQCcIgJR.TyiH32qI8u'
WHERE email = 'staff@company.com';
