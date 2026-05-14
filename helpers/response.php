<?php

function response(bool $success, string $message, $data = null, int $code = 200): void {
    http_response_code($code);
    $body = ['success' => $success, 'message' => $message];
    if ($data !== null) $body['data'] = $data;
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function success($data = null, string $message = 'Thành công', int $code = 200): void {
    response(true, $message, $data, $code);
}

function error(string $message = 'Lỗi', int $code = 400, $data = null): void {
    response(false, $message, $data, $code);
}

function paginated(array $items, int $total, int $page, int $per_page, string $message = 'Thành công'): void {
    http_response_code(200);
    echo json_encode([
        'success'    => true,
        'message'    => $message,
        'data'       => $items,
        'pagination' => [
            'total'       => $total,
            'per_page'    => $per_page,
            'current_page'=> $page,
            'last_page'   => (int) ceil($total / $per_page),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

function getPaginationParams(): array {
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $per_page = min(100, max(1, (int)($_GET['per_page'] ?? 15)));
    $offset   = ($page - 1) * $per_page;
    return compact('page', 'per_page', 'offset');
}
