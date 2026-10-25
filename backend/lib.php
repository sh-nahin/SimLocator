<?php
declare(strict_types=1);

/** First three digits of a Bangladeshi mobile number => operator. */
const OPERATORS = [
    '013' => 'Grameenphone', '017' => 'Grameenphone',
    '014' => 'Banglalink',   '019' => 'Banglalink',
    '015' => 'Teletalk',
    '016' => 'Robi',         '018' => 'Robi',
];

const DISTRICTS = [
    'Bagerhat', 'Bandarban', 'Barguna', 'Barishal', 'Bhola', 'Bogura', 'Brahmanbaria', 'Chandpur', 'Chapainawabganj', 'Chattogram',
    'Chuadanga', "Cox's Bazar", 'Cumilla', 'Dhaka', 'Dinajpur', 'Faridpur', 'Feni', 'Gaibandha', 'Gazipur',
    'Gopalganj', 'Habiganj', 'Jamalpur', 'Jashore', 'Jhalokathi', 'Jhenaidah', 'Joypurhat', 'Khagrachhari',
    'Khulna', 'Kishoreganj', 'Kurigram', 'Kushtia', 'Lakshmipur', 'Lalmonirhat', 'Madaripur', 'Magura',
    'Manikganj', 'Meherpur', 'Moulvibazar', 'Munshiganj', 'Mymensingh', 'Naogaon', 'Narail', 'Narayanganj',
    'Narsingdi', 'Natore', 'Netrokona', 'Nilphamari', 'Noakhali', 'Pabna', 'Panchagarh', 'Patuakhali',
    'Pirojpur', 'Rajbari', 'Rajshahi', 'Rangamati', 'Rangpur', 'Satkhira', 'Shariatpur', 'Sherpur',
    'Sirajganj', 'Sunamganj', 'Sylhet', 'Tangail', 'Thakurgaon',
];

/** Wrong-password limit: after this many failures the account is locked for 10 minutes (see index.php). */
const MAX_LOGIN_FAILURES = 5;

/** A valid hash of a random password. Checked when the phone is unknown so the response time is the same. */
const DUMMY_HASH = '$2y$10$AfTrJH6Fvu09foGJrUHcSeYKkct9zVs5Y0YFlcgqEUnuf9chU7oma';

/** Reads backend/.env once. Real environment variables win. */
function env(string $key, string $default = ''): string
{
    static $file = null;
    if ($file === null) {
        $file = [];
        $path = __DIR__ . '/.env';
        foreach (is_file($path) ? file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [] as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $file[trim($k)] = trim($v);
        }
    }
    $real = getenv($key);
    return $real !== false ? $real : ($file[$key] ?? $default);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', env('DB_HOST', '127.0.0.1'), env('DB_PORT', '3306'), env('DB_NAME', 'simlocator')),
                env('DB_USER', 'simlocator'),
                env('DB_PASS'),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
            );
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            throw new RuntimeException('Cannot connect to the database. Check that MySQL is running and backend/.env is correct.', 503);
        }
    }
    return $pdo;
}

/** Accepts 01XXXXXXXXX, 8801XXXXXXXXX, +8801XXXXXXXXX (spaces and dashes allowed). Returns 01XXXXXXXXX or null. */
function normalize_phone(string $raw): ?string
{
    $digits = preg_replace('/[\s\-]/', '', $raw);
    if (str_starts_with($digits, '+880')) {
        $digits = '0' . substr($digits, 4);
    } elseif (str_starts_with($digits, '880')) {
        $digits = '0' . substr($digits, 3);
    }
    return preg_match('/^01[3-9]\d{8}$/', $digits) ? $digits : null;
}

function detect_operator(string $phone): ?string
{
    return OPERATORS[substr($phone, 0, 3)] ?? null;
}

/** @return array{0: array<string,string>, 1: array<string,string>} [clean values, errors by field] */
function validate_registration(array $in): array
{
    $text = fn (string $k): string => is_string($in[$k] ?? null) ? trim(preg_replace('/\s+/', ' ', $in[$k])) : '';
    $name = $text('name');
    $email = strtolower($text('email'));
    $area = $text('area');
    $district = $text('district');
    $phone = normalize_phone($text('phone'));
    $password = is_string($in['password'] ?? null) ? $in['password'] : '';   // never trimmed or altered
    $errors = [];

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors['name'] = 'Enter your name (2 to 100 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if ($phone === null || detect_operator($phone) === null) {
        $errors['phone'] = 'Enter a valid Bangladeshi mobile number, for example 018XXXXXXXX.';
    }
    if (strlen($password) < 8 || strlen($password) > 72) {   // bcrypt only uses the first 72 bytes
        $errors['password'] = 'Password must be 8 to 72 characters.';
    }
    if (!in_array($district, DISTRICTS, true)) {
        $errors['district'] = 'Choose a district from the list.';
    }
    if (mb_strlen($area) < 2 || mb_strlen($area) > 60) {
        $errors['area'] = 'Enter your area (2 to 60 characters), for example Mirpur.';
    }

    return [['name' => $name, 'email' => $email, 'phone' => (string) $phone, 'password' => $password, 'district' => $district, 'area' => $area], $errors];
}

/** What the website shows after registering or logging in. */
function profile(string $name, string $operator, string $district, string $area): array
{
    return [
        'name' => $name, 'operator' => $operator, 'district' => $district, 'area' => $area,
        'message' => "You are a $operator user from $area, $district.",
    ];
}
