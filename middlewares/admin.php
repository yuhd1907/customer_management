<?php
/**
 * Admin Middleware — Shortcut for admin-only routes
 */
function requireAdmin(): array {
    return requireRole('admin');
}

function requireAdminOrManager(): array {
    return requireRole('admin', 'manager');
}
