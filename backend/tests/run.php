<?php
declare(strict_types=1);
// QA script.  Run from the backend folder:  php tests/run.php
// Part 1 needs nothing. Part 2 needs MySQL + the API running on http://localhost:8000.
require __DIR__ . '/../lib.php';

$pass = $fail = 0;
function check(string $name, bool $ok): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $name . PHP_EOL;
}

/** @return array{0:int,1:array,2:array} [status, json, headers] */
function call(string $method, string $path, ?string $body = null, array $headers = []): array
{
    $ctx = stream_context_create(['http' => [
        'method' => $method, 'ignore_errors' => true, 'timeout' => 8, 'content' => $body,
        'header' => implode("\r\n", array_merge(['Content-Type: application/json'], $headers)),
    ]]);
    $raw = @file_get_contents('http://localhost:8000' . $path, false, $ctx);
    $hdrs = $http_response_header ?? [];
    $status = isset($hdrs[0]) && preg_match('/\s(\d{3})\s/', $hdrs[0], $m) ? (int) $m[1] : 0;
    return [$status, json_decode((string) $raw, true) ?? [], $hdrs];
}

echo "Part 1: unit tests (no server needed)\n";
check('63+1 = 64 districts, all unique', count(DISTRICTS) === 64 && count(array_unique(DISTRICTS)) === 64);
foreach (['01812345678', '8801812345678', '+8801812345678', '+880 1812-345678', ' 018 1234 5678 '] as $p) {
    check("phone accepted: '$p'", normalize_phone($p) === '01812345678');
}
foreach (['', 'abc', '0181234567', '018123456789', '01212345678', '02812345678', '+8801812', '018-12345-67x'] as $p) {
    check("phone rejected: '$p'", normalize_phone($p) === null);
}
foreach (['01312345678' => 'Grameenphone', '01712345678' => 'Grameenphone', '01412345678' => 'Banglalink', '01912345678' => 'Banglalink',
          '01512345678' => 'Teletalk', '01612345678' => 'Robi', '01812345678' => 'Robi'] as $phone => $op) {
    check("$phone is $op", detect_operator($phone) === $op);
}
$good = ['name' => '  Rahim   Uddin ', 'email' => ' Rahim@Example.COM ', 'phone' => '018 1234-5678', 'password' => 'secret1234', 'district' => 'Dhaka', 'area' => 'Mirpur'];
[$clean, $err] = validate_registration($good);
check('valid input passes and is cleaned', !$err && $clean['name'] === 'Rahim Uddin' && $clean['email'] === 'rahim@example.com' && $clean['phone'] === '01812345678');
check('password is kept exactly as typed (spaces allowed)', validate_registration(['password' => ' pass word '] + $good)[0]['password'] === ' pass word ');
[, $err] = validate_registration([]);
check('empty input: all 6 fields flagged', count($err) === 6);
foreach (['name' => 'A', 'email' => 'not-an-email', 'phone' => '123', 'district' => 'Atlantis', 'area' => '', 'password' => 'short'] as $field => $bad) {
    [, $err] = validate_registration([$field => $bad] + $good);
    check("bad $field flagged", isset($err[$field]) && count($err) === 1);
}
[, $err] = validate_registration(['password' => str_repeat('a', 73)] + $good);
check('password over 72 bytes flagged', isset($err['password']));
[, $err] = validate_registration(['password' => str_repeat('a', 72)] + $good);
check('password of exactly 72 bytes accepted', !isset($err['password']));
[, $err] = validate_registration(['password' => ['x']] + $good);
check('non-string password (array) flagged, no crash', isset($err['password']));
[, $err] = validate_registration(['name' => str_repeat('a', 101)] + $good);
check('name over 100 chars flagged', isset($err['name']));
[, $err] = validate_registration(['name' => ['array']] + $good);
check('non-string name (array) flagged, no crash', isset($err['name']));
[, $err] = validate_registration(['phone' => '01112345678'] + $good);
check('unknown operator prefix 011 flagged', isset($err['phone']));

echo "\nPart 2: API tests (server + MySQL)\n";
[$status] = call('GET', '/api/health');
if ($status !== 200) {
    echo "  SKIP  API is not running on localhost:8000. Start it, then run this again.\n";
} else {
    $n = (string) random_int(10000000, 99999999);           // unique per run so reruns do not collide
    $email = "qa-$n@test.invalid";
    $phone = "018$n";
    $reg = fn (array $over = []) => call('POST', '/api/register', json_encode($over + ['name' => 'QA Tester', 'email' => $email, 'phone' => $phone, 'password' => 'secret1234', 'district' => 'Dhaka', 'area' => 'Mirpur']));
    $login = fn (string $p, string $pw) => call('POST', '/api/login', json_encode(['phone' => $p, 'password' => $pw]));
    $key = ['X-Admin-Key: ' . env('ADMIN_KEY')];

    [$s, $j] = call('GET', '/api/districts');
    check('GET /districts returns 64', $s === 200 && count($j['districts']) === 64);
    [$s, $j] = $reg();
    check('register OK: 201 + Robi + Mirpur, Dhaka', $s === 201 && $j['message'] === 'You are a Robi user from Mirpur, Dhaka.');
    [$s, $j] = $reg(['email' => "other-$n@test.invalid"]);
    check('same phone again: 422 on phone', $s === 422 && isset($j['errors']['phone']));
    [$s, $j] = $reg(['phone' => "017$n"]);
    check('same email again: 422 on email', $s === 422 && isset($j['errors']['email']));
    [$s, $j] = $reg(['email' => "gp-$n@test.invalid", 'phone' => "017$n", 'district' => 'Sylhet', 'area' => 'Zindabazar']);
    check('017 number: Grameenphone from Zindabazar, Sylhet', $s === 201 && $j['message'] === 'You are a Grameenphone user from Zindabazar, Sylhet.');
    [$s, $j] = $reg(['email' => 'bad', 'phone' => '1', 'name' => '', 'area' => '', 'district' => 'x', 'password' => 'x']);
    check('all-bad input: 422 with 6 field errors', $s === 422 && count($j['errors']) === 6);
    [$s, $j] = $reg(['email' => "short-$n@test.invalid", 'phone' => "016$n", 'password' => '1234567']);
    check('7-character password: 422 on password, nothing saved', $s === 422 && isset($j['errors']['password']));
    [$s] = call('POST', '/api/register', '{oops');
    check('broken JSON: 400', $s === 400);
    [$s] = call('POST', '/api/register', '"just a string"');
    check('JSON that is not an object: 400', $s === 400);
    [$s] = call('POST', '/api/register', '[]');
    check('empty JSON array: 422', $s === 422);
    [$s, $j] = $reg(['email' => "x-$n@test.invalid", 'phone' => "019$n", 'name' => "Robert'); DROP TABLE registrations;--"]);
    check('SQL-injection text is stored as plain text (201)', $s === 201);
    [$s, $j] = call('GET', '/api/admin/registrations', null, $key);
    check('table still exists after injection attempt', $s === 200 && $j['total'] >= 3);

    echo "  -- password and login\n";
    $row = db()->query("SELECT password_hash FROM registrations WHERE phone = '$phone'")->fetch();
    check('database stores a bcrypt hash, not the password', str_starts_with($row['password_hash'], '$2y$') && !str_contains($row['password_hash'], 'secret1234'));
    [$s, $j] = $login($phone, 'secret1234');
    check('login OK: 200 + Robi + Mirpur, Dhaka', $s === 200 && $j['message'] === 'You are a Robi user from Mirpur, Dhaka.');
    check('login response has no password or hash', !array_key_exists('password', $j) && !array_key_exists('password_hash', $j));
    [$s, $j] = $login('+880' . substr($phone, 1), 'secret1234');
    check('login works with +880 phone format', $s === 200);
    [$s, $j] = $login("017$n", 'secret1234');
    check('login with 017 number: Grameenphone', $s === 200 && str_contains($j['message'], 'Grameenphone') && str_contains($j['message'], 'Zindabazar, Sylhet'));
    [$s, $j] = $login($phone, 'Secret1234');
    check('wrong password (different case): 401', $s === 401 && $j['message'] === 'Wrong phone number or password.');
    [$s2, $j2] = $login('01899999999', 'secret1234');
    check('unknown phone gives the same 401 message', $s2 === 401 && $j2['message'] === $j['message']);
    [$s] = $login('abc', 'x');
    check('garbage phone: 401', $s === 401);
    [$s] = call('POST', '/api/login', '{"phone":["x"],"password":{"a":1}}');
    check('non-string phone/password: 401, no crash', $s === 401);
    [$s] = call('POST', '/api/login', '{oops');
    check('login with broken JSON: 400', $s === 400);

    $m = (string) random_int(10000000, 99999999);
    $lock = call('POST', '/api/register', json_encode(['name' => 'Lock Test', 'email' => "lock-$m@test.invalid", 'phone' => "019$m", 'password' => 'rightpass1', 'district' => 'Khulna', 'area' => 'Sonadanga']));
    for ($i = 1; $i <= 4; $i++) { [$s] = $login("019$m", "wrong$i"); }
    check('4 wrong passwords are still 401', $s === 401);
    $login("019$m", 'rightpass1');   // a correct login resets the counter
    for ($i = 1; $i <= 5; $i++) { [$s] = $login("019$m", "wrong$i"); }
    check('5 wrong passwords after a success: still 401 (counter was reset)', $s === 401);
    [$s, $j] = $login("019$m", 'rightpass1');
    check('account locked now: even the right password gets 429', $s === 429);

    [$s] = call('GET', '/api/admin/registrations');
    check('admin without key: 401', $s === 401);
    [$s] = call('GET', '/api/admin/registrations', null, ['X-Admin-Key: wrong']);
    check('admin wrong key: 401', $s === 401);
    [$s, $j] = call('GET', '/api/admin/registrations', null, $key);
    $emails = array_column($j['registrations'] ?? [], 'email');
    check('admin right key: 200 and sees the new registration', $s === 200 && in_array($email, $emails, true));
    check('admin sums per operator equal total', array_sum(array_column($j['by_operator'], 'total')) === $j['total']);
    check('admin data never contains passwords or hashes', !str_contains(json_encode($j), 'password') && !str_contains(json_encode($j), '$2y$'));
    [$s] = call('GET', '/api/nope');
    check('unknown route: 404', $s === 404);
    [$s] = call('GET', '/api/register');
    check('GET on /register: 404', $s === 404);
    [$s, , $h] = call('OPTIONS', '/api/register');
    check('CORS preflight: 204 + allow-origin header', $s === 204 && str_contains(implode('|', $h), 'Access-Control-Allow-Origin'));

    echo "\n  Cleanup the test rows with:\n  mysql -u root -p -e \"DELETE FROM simlocator.registrations WHERE email LIKE '%@test.invalid'\"\n";
}

echo PHP_EOL . "$pass passed, $fail failed" . PHP_EOL;
exit($fail ? 1 : 0);
