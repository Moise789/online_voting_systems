<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

function load_environment(string $file): void
{
    if (!is_file($file) || !is_readable($file)) {
        return;
    }

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name !== '' && getenv($name) === false) {
            if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }
}

$configFile = safe_local_config_path();
load_environment($configFile);
$configuredEnvironment = strtolower((string) getenv('APP_ENV'));
if (!is_file($configFile) && $configuredEnvironment !== 'production') {
    $projectConfigFile = APP_ROOT . '/.env';
    if (environment_file_declares_production($projectConfigFile)) {
        putenv('APP_ENV=production');
        $_ENV['APP_ENV'] = 'production';
    } else {
        load_environment($projectConfigFile);
    }
}
if ((getenv('APP_ENV') === false || getenv('APP_ENV') === '')
    && isset($_SERVER['HTTP_HOST'])
    && !in_array(strtolower(preg_replace('/:\d+$/', '', (string) $_SERVER['HTTP_HOST']) ?? ''), ['localhost', '127.0.0.1', '[::1]'], true)) {
    putenv('APP_ENV=production');
    $_ENV['APP_ENV'] = 'production';
}

$timezone = getenv('APP_TIMEZONE') ?: 'Africa/Nairobi';
try {
    date_default_timezone_set($timezone);
} catch (Throwable $exception) {
    date_default_timezone_set('UTC');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('voting_system_session');
    session_start();
}
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store, private');
}

require_once APP_ROOT . '/app/security.php';
require_once APP_ROOT . '/app/mailer.php';
require_once APP_ROOT . '/app/actions.php';
require_once APP_ROOT . '/app/pages.php';

function app_config(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    return $value === false || $value === '' ? $default : $value;
}

function assert_production_configuration(): void
{
    if (strtolower((string) app_config('APP_ENV', 'development')) !== 'production') {
        return;
    }

    $required = [
        'APP_NAME', 'APP_BASE_URL', 'APP_TIMEZONE',
        'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD',
        'APP_ENCRYPTION_KEY', 'APP_HMAC_KEY',
        'SMTP_HOST', 'SMTP_PORT', 'SMTP_SECURITY', 'SMTP_USERNAME',
        'SMTP_PASSWORD', 'SMTP_FROM_EMAIL', 'SMTP_FROM_NAME',
    ];
    foreach ($required as $name) {
        $value = getenv($name);
        if ($value === false || trim($value) === '') {
            throw new RuntimeException('Production configuration is incomplete. Check the private configuration file and run the configuration checks.');
        }
    }
}

function environment_file_declares_production(string $file): bool
{
    if (!is_file($file) || !is_readable($file)) {
        return false;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (preg_match('/^\s*APP_ENV\s*=\s*["\']?production["\']?\s*$/i', $line)) {
            return true;
        }
    }
    return false;
}

function safe_local_config_path(): string
{
    $defaultWindows = 'C:/xampp/private/online_voting_system.env';
    $defaultUnix = '/etc/online_voting_system.env';
    return PHP_OS_FAMILY === 'Windows' ? $defaultWindows : $defaultUnix;
}

function pdo(): PDO
{
    assert_production_configuration();
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }
    $host = app_config('DB_HOST', '127.0.0.1');
    $port = app_config('DB_PORT', '3306');
    $name = app_config('DB_NAME', 'online_voting_system');
    $user = app_config('DB_USER', 'root');
    $password = app_config('DB_PASSWORD', '');
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $connection = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
    ]);
    return $connection;
}

function encryption_key(): string
{
    assert_production_configuration();
    $encoded = app_config('APP_ENCRYPTION_KEY');
    $key = $encoded === null ? false : base64_decode($encoded, true);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('APP_ENCRYPTION_KEY must be a base64-encoded 32-byte key. Generate one with: php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"');
    }
    return $key;
}

function hmac_key(): string
{
    assert_production_configuration();
    $encoded = app_config('APP_HMAC_KEY');
    $key = $encoded === null ? false : base64_decode($encoded, true);
    if ($key === false || strlen($key) < 32) {
        throw new RuntimeException('APP_HMAC_KEY must be a base64-encoded key of at least 32 bytes. Generate one with: php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"');
    }
    return $key;
}

function encrypt_value(string $value): string
{
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($value, 'aes-256-gcm', encryption_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('Unable to protect sensitive information.');
    }
    return 'v1:' . base64_encode($iv . $tag . $ciphertext);
}

function decrypt_value(string $value): string
{
    if (!str_starts_with($value, 'v1:')) {
        throw new RuntimeException('Stored sensitive information uses an unsupported format.');
    }
    $packed = base64_decode(substr($value, 3), true);
    if ($packed === false || strlen($packed) < 29) {
        throw new RuntimeException('Stored sensitive information is invalid.');
    }
    $plaintext = openssl_decrypt(substr($packed, 28), 'aes-256-gcm', encryption_key(), OPENSSL_RAW_DATA, substr($packed, 0, 12), substr($packed, 12, 16));
    if ($plaintext === false) {
        throw new RuntimeException('Unable to decrypt sensitive information. Check the configured application key.');
    }
    return $plaintext;
}

function identifier_hash(string $type, string $value): string
{
    return hash_hmac('sha256', $type . ':' . $value, hmac_key());
}

function normalize_email(string $email): string
{
    return mb_strtolower(trim($email), 'UTF-8');
}

function normalize_national_id(string $nationalId): string
{
    return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $nationalId) ?? $nationalId), 'UTF-8');
}

function required_configuration_fields(): array
{
    return [
        ['name' => 'APP_NAME', 'label' => 'Application name', 'required' => true],
        ['name' => 'APP_ENV', 'label' => 'Application environment', 'required' => true],
        ['name' => 'APP_BASE_URL', 'label' => 'Application base URL', 'required' => true],
        ['name' => 'APP_TIMEZONE', 'label' => 'Application timezone', 'required' => true],
        ['name' => 'DB_HOST', 'label' => 'MySQL database host', 'required' => true],
        ['name' => 'DB_PORT', 'label' => 'MySQL database port', 'required' => true],
        ['name' => 'DB_NAME', 'label' => 'MySQL database name', 'required' => true],
        ['name' => 'DB_USER', 'label' => 'MySQL database username', 'required' => true],
        ['name' => 'DB_PASSWORD', 'label' => 'MySQL database password (may be blank only for local development)', 'required' => false],
        ['name' => 'APP_ENCRYPTION_KEY', 'label' => 'Application encryption key', 'required' => true],
        ['name' => 'APP_HMAC_KEY', 'label' => 'Application HMAC key', 'required' => true],
        ['name' => 'SMTP_HOST', 'label' => 'SMTP host', 'required' => true],
        ['name' => 'SMTP_PORT', 'label' => 'SMTP port', 'required' => true],
        ['name' => 'SMTP_SECURITY', 'label' => 'SMTP encryption method', 'required' => true],
        ['name' => 'SMTP_USERNAME', 'label' => 'SMTP username', 'required' => true],
        ['name' => 'SMTP_PASSWORD', 'label' => 'SMTP password (Gmail App Password)', 'required' => true],
        ['name' => 'SMTP_FROM_EMAIL', 'label' => 'SMTP sender email', 'required' => true],
        ['name' => 'SMTP_FROM_NAME', 'label' => 'SMTP sender display name', 'required' => true],
        ['name' => 'ADMIN_EMAIL', 'label' => 'Administrator contact email', 'required' => false],
    ];
}

function config_field_value(string $name): string
{
    $value = app_config($name, '');
    return is_string($value) ? $value : '';
}

function validate_config_value(string $name, string $value): array
{
    $label = 'Configuration value';
    foreach (required_configuration_fields() as $field) {
        if ($field['name'] === $name) {
            $label = $field['label'];
            break;
        }
    }

    if ($value === '' && $name === 'DB_PASSWORD' && strtolower((string) app_config('APP_ENV', 'development')) !== 'production') {
        return ['status' => 'warning', 'message' => 'The local database password is blank; set one if your XAMPP MySQL account requires it and never use this in production.'];
    }
    if ($value === '') {
        return ['status' => 'fail', 'message' => $label . ' is missing.'];
    }

    switch ($name) {
        case 'APP_ENV':
            return in_array(strtolower($value), ['development', 'production'], true)
                ? ['status' => 'pass', 'message' => 'Environment is set to a supported value.']
                : ['status' => 'fail', 'message' => 'Choose either development or production.'];
        case 'APP_BASE_URL':
            $parts = parse_url($value);
            return filter_var($value, FILTER_VALIDATE_URL) !== false
                && is_array($parts)
                && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
                && isset($parts['host'])
                && !isset($parts['user'])
                && !isset($parts['pass'])
                && !isset($parts['query'])
                && !isset($parts['fragment'])
                ? ['status' => 'pass', 'message' => 'Base URL is a valid URL.']
                : ['status' => 'fail', 'message' => 'Use a full http or https URL such as http://localhost/online_voting_system, without embedded credentials.'];
        case 'DB_HOST':
        case 'SMTP_HOST':
            return preg_match('/^[A-Za-z0-9.-]+$/', $value) === 1
                ? ['status' => 'pass', 'message' => 'Host name format is valid.']
                : ['status' => 'fail', 'message' => 'Enter a valid host name without spaces or URL syntax.'];
        case 'DB_NAME':
            return preg_match('/^[A-Za-z0-9_]+$/', $value) === 1
                ? ['status' => 'pass', 'message' => 'Database name format is valid.']
                : ['status' => 'fail', 'message' => 'Use letters, numbers, and underscores for the database name.'];
        case 'DB_USER':
            return preg_match('/^[A-Za-z0-9_.@-]+$/', $value) === 1
                ? ['status' => 'pass', 'message' => 'Database username format is valid.']
                : ['status' => 'fail', 'message' => 'Enter a valid database username.'];
        case 'APP_TIMEZONE':
            try {
                new DateTimeZone($value);
                return ['status' => 'pass', 'message' => 'Timezone is valid.'];
            } catch (Throwable $exception) {
                return ['status' => 'fail', 'message' => 'Use a valid IANA timezone name.'];
            }
        case 'DB_PORT':
            return is_numeric($value) && (int) $value >= 1 && (int) $value <= 65535
                ? ['status' => 'pass', 'message' => 'Database port is valid.']
                : ['status' => 'fail', 'message' => 'Database port must be a valid MySQL port.'];
        case 'APP_ENCRYPTION_KEY':
            $decoded = base64_decode($value, true);
            return $decoded !== false && strlen($decoded) === 32
                ? ['status' => 'pass', 'message' => 'Encryption key has the expected format and length.']
                : ['status' => 'fail', 'message' => 'Use a base64-encoded 32-byte key.'];
        case 'APP_HMAC_KEY':
            $decoded = base64_decode($value, true);
            return $decoded !== false && strlen($decoded) >= 32
                ? ['status' => 'pass', 'message' => 'HMAC key has the expected format and length.']
                : ['status' => 'fail', 'message' => 'Use a base64-encoded key of at least 32 bytes.'];
        case 'SMTP_PORT':
            return is_numeric($value) && (int) $value >= 1 && (int) $value <= 65535
                ? ['status' => 'pass', 'message' => 'SMTP port is valid.']
                : ['status' => 'fail', 'message' => 'Use a valid SMTP port.'];
        case 'SMTP_SECURITY':
            return in_array(strtolower($value), ['none', 'ssl', 'starttls'], true)
                ? ['status' => 'pass', 'message' => 'SMTP security method is valid.']
                : ['status' => 'fail', 'message' => 'Use none, ssl, or starttls. Gmail should use starttls.'];
        case 'ADMIN_EMAIL':
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                ? ['status' => 'pass', 'message' => 'Administrator email is valid.']
                : ['status' => 'fail', 'message' => 'Enter a valid administrator email.'];
        case 'SMTP_USERNAME':
        case 'SMTP_FROM_EMAIL':
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                ? ['status' => 'pass', 'message' => 'Email address format is valid.']
                : ['status' => 'fail', 'message' => 'Enter a valid email address.'];
        case 'SMTP_FROM_NAME':
            return preg_match('/^[^\r\n]{1,160}$/u', $value) === 1
                ? ['status' => 'pass', 'message' => 'Sender display name is valid.']
                : ['status' => 'fail', 'message' => 'Enter a display name without line breaks.'];
        default:
            return ['status' => 'pass', 'message' => 'Value is present.'];
    }
}

function runtime_configuration_checks(): array
{
    $results = [];
    foreach (required_configuration_fields() as $field) {
        $name = $field['name'];
        $value = config_field_value($name);
        if (!$field['required'] && $name !== 'DB_PASSWORD' && $value === '') {
            continue;
        }
        $results[] = [
            'name' => $name,
            'label' => $field['label'],
            'status' => validate_config_value($name, $value)['status'],
            'message' => validate_config_value($name, $value)['message'],
        ];
    }

    $dbOk = false;
    try {
        $pdo = pdo();
        $pdo->query('SELECT 1');
        $dbOk = true;
    } catch (Throwable $exception) {
        $dbOk = false;
    }

    $results[] = [
        'name' => 'database_connection',
        'label' => 'Database connection',
        'status' => $dbOk ? 'pass' : 'fail',
        'message' => $dbOk ? 'Database connection succeeded.' : 'Database connection failed. Check DB_HOST, DB_PORT, DB_NAME, DB_USER and DB_PASSWORD.',
    ];

    if ($dbOk) {
        $requiredTables = [
            'admins', 'voters', 'voter_identifiers', 'voter_profile_changes',
            'elections', 'candidates', 'votes', 'password_resets', 'activity_logs',
            'email_notifications', 'otp_challenges', 'auth_rate_limits',
        ];
        $tables = [];
        $statement = pdo()->query('SHOW TABLES');
        while ($row = $statement->fetch(PDO::FETCH_NUM)) {
            $tables[] = strtolower((string) $row[0]);
        }
        foreach ($requiredTables as $table) {
            $exists = in_array(strtolower($table), $tables, true);
            $results[] = [
                'name' => 'table_' . $table,
                'label' => 'Table ' . $table,
                'status' => $exists ? 'pass' : 'fail',
                'message' => $exists ? 'Required table exists.' : 'Required table is missing.',
            ];
        }
    }

    foreach (['openssl', 'pdo_mysql', 'fileinfo', 'mbstring'] as $extension) {
        $loaded = extension_loaded($extension);
        $results[] = [
            'name' => 'extension_' . $extension,
            'label' => 'PHP extension ' . $extension,
            'status' => $loaded ? 'pass' : 'fail',
            'message' => $loaded ? 'Extension is available.' : 'Install or enable this PHP extension.',
        ];
    }

    $phpSupported = PHP_VERSION_ID >= 80000;
    $results[] = [
        'name' => 'php_version',
        'label' => 'PHP version',
        'status' => $phpSupported ? 'pass' : 'fail',
        'message' => $phpSupported ? 'PHP 8.0 or newer is available.' : 'Install PHP 8.0 or newer.',
    ];

    foreach (['storage', 'storage/logs', 'storage/backups', 'uploads/candidates'] as $relativePath) {
        $directory = APP_ROOT . '/' . ltrim($relativePath, '/');
        $exists = is_dir($directory);
        $writable = $exists && is_writable($directory);
        $results[] = [
            'name' => 'directory_' . str_replace('/', '_', $relativePath),
            'label' => 'Directory ' . $relativePath,
            'status' => $exists && $writable ? 'pass' : 'warning',
            'message' => $exists
                ? ($writable ? 'Directory is available and writable.' : 'Directory exists but is not writable.')
                : 'Directory is missing or not accessible.',
        ];
    }

    $environment = strtolower((string) config_field_value('APP_ENV'));
    $inProduction = $environment === 'production';
    $baseUrl = (string) config_field_value('APP_BASE_URL');
    $productionSafe = !$inProduction || (
        str_starts_with(strtolower($baseUrl), 'https://')
        && !in_array(strtolower($baseUrl), ['http://localhost/online_voting_system', 'http://localhost'], true)
        && !is_file(APP_ROOT . '/.env')
    );
    $results[] = [
        'name' => 'production_defaults',
        'label' => 'Production defaults',
        'status' => $productionSafe ? 'pass' : ($inProduction ? 'fail' : 'warning'),
        'message' => $inProduction
            ? ($productionSafe ? 'Production uses HTTPS and does not rely on a project-root .env file.' : 'Production must use HTTPS and a private configuration file; remove the project-root .env file.')
            : 'Development settings are acceptable for local testing.',
    ];

    $privateConfig = safe_local_config_path();
    $privateDir = realpath(dirname($privateConfig));
    $publicRoot = realpath(APP_ROOT);
    $privateOutsideWebRoot = $privateDir !== false && $publicRoot !== false
        && !str_starts_with(strtolower($privateDir . DIRECTORY_SEPARATOR), strtolower($publicRoot . DIRECTORY_SEPARATOR));
    $results[] = [
        'name' => 'private_config_location',
        'label' => 'Private configuration location',
        'status' => $privateOutsideWebRoot ? 'pass' : 'fail',
        'message' => $privateOutsideWebRoot ? 'The fixed private configuration directory is outside the public project directory.' : 'The private configuration directory is missing or is inside the public project directory.',
    ];

    return $results;
}
