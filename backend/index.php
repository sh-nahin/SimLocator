<?php
declare(strict_types=1);

// Start:  php -S localhost:8000 index.php      (run inside the backend folder)
require __DIR__ . '/lib.php';

function respond(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function read_json(): array
{
    $body = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($body) ? $body : respond(400, ['message' => 'Request body must be valid JSON.']);
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: ' . env('FRONTEND_ORIGIN', 'http://localhost:3000'));
header('Access-Control-Allow-Headers: Content-Type, X-Admin-Key');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Vary: Origin');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

if ($method === 'OPTIONS') {
    respond(204, []);
}

try {
    switch ("$method $path") {
        case 'GET /api/health':
            respond(200, ['status' => 'ok']);

        case 'GET /api/districts':
            respond(200, ['districts' => DISTRICTS]);

        case 'POST /api/register':
            [$v, $errors] = validate_registration(read_json());
            if ($errors) {
                respond(422, ['message' => 'Please fix the highlighted fields.', 'errors' => $errors]);
            }
            $operator = detect_operator($v['phone']);
            try {
                db()->prepare('INSERT INTO registrations (name, email, phone, operator, district, area, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$v['name'], $v['email'], $v['phone'], $operator, $v['district'], $v['area'], password_hash($v['password'], PASSWORD_DEFAULT)]);
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                $field = str_contains($e->getMessage(), 'uq_phone') ? 'phone' : 'email';
                respond(422, ['message' => 'Please fix the highlighted fields.', 'errors' => [$field => "This $field is already registered."]]);
            }
            respond(201, profile($v['name'], $operator, $v['district'], $v['area']));

        case 'POST /api/login':
            $body = read_json();
            $phone = normalize_phone(is_string($body['phone'] ?? null) ? $body['phone'] : '');
            $password = is_string($body['password'] ?? null) ? $body['password'] : '';
            $user = null;
            if ($phone !== null) {
                $st = db()->prepare('SELECT id, name, operator, district, area, password_hash, failed_logins, locked_until > NOW() AS locked FROM registrations WHERE phone = ?');
                $st->execute([$phone]);
                $user = $st->fetch() ?: null;
            }
            if ($user && $user['locked']) {
                respond(429, ['message' => 'Too many wrong passwords. Please try again in 10 minutes.']);
            }
            // Always check one hash, even for an unknown phone, so the response time does not reveal who is registered.
            $ok = password_verify($password, $user['password_hash'] ?? DUMMY_HASH);
            if (!$user || !$ok) {
                if ($user) {
                    $n = (int) $user['failed_logins'] + 1;
                    db()->prepare('UPDATE registrations SET failed_logins = IF(? >= ?, 0, ?), locked_until = IF(? >= ?, NOW() + INTERVAL 10 MINUTE, locked_until) WHERE id = ?')
                        ->execute([$n, MAX_LOGIN_FAILURES, $n, $n, MAX_LOGIN_FAILURES, $user['id']]);
                }
                respond(401, ['message' => 'Wrong phone number or password.']);
            }
            if ((int) $user['failed_logins'] > 0) {
                db()->prepare('UPDATE registrations SET failed_logins = 0, locked_until = NULL WHERE id = ?')->execute([$user['id']]);
            }
            respond(200, profile($user['name'], $user['operator'], $user['district'], $user['area']));

        case 'GET /api/admin/registrations':
            $adminKey = env('ADMIN_KEY');
            if ($adminKey === '') {
                respond(503, ['message' => 'ADMIN_KEY is not set in backend/.env.']);
            }
            if (!hash_equals($adminKey, $_SERVER['HTTP_X_ADMIN_KEY'] ?? '')) {
                respond(401, ['message' => 'Wrong admin key.']);
            }
            $rows = db()->query('SELECT id, name, email, phone, operator, district, area, created_at FROM registrations ORDER BY id DESC LIMIT 500')->fetchAll();
            $counts = db()->query('SELECT operator, COUNT(*) AS total FROM registrations GROUP BY operator ORDER BY total DESC')->fetchAll();
            respond(200, [
                'total' => (int) db()->query('SELECT COUNT(*) FROM registrations')->fetchColumn(),
                'by_operator' => array_map(fn ($r) => ['operator' => $r['operator'], 'total' => (int) $r['total']], $counts),
                'registrations' => $rows,
            ]);

        default:
            respond(404, ['message' => 'Not found.']);
    }
} catch (Throwable $e) {
    $status = $e->getCode() === 503 ? 503 : 500;
    if ($status === 500) {
        error_log($e::class . ': ' . $e->getMessage());
    }
    respond($status, ['message' => $status === 503 ? $e->getMessage() : 'Something went wrong on our side.']);
}
