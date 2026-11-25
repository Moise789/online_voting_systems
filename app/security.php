<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_local_setup_request(): bool
{
    if (strtolower((string) app_config('APP_ENV', 'development')) === 'production') {
        return false;
    }

    foreach (['HTTP_FORWARDED', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_HOST'] as $header) {
        if (isset($_SERVER[$header]) && $_SERVER[$header] !== '') {
            return false;
        }
    }

    $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    if (!in_array(trim($host, '[]'), ['localhost', '127.0.0.1', '::1'], true)) {
        return false;
    }
    if (!is_string($remoteAddress)) {
        return false;
    }
    $packed = @inet_pton($remoteAddress);
    if ($packed === false) {
        return false;
    }
    $loopbackV4 = inet_pton('127.0.0.1');
    $loopbackV6 = inet_pton('::1');
    $mappedV4Prefix = str_repeat("\0", 10) . "\xff\xff";
    return hash_equals($loopbackV4, $packed)
        || hash_equals($loopbackV6, $packed)
        || (strlen($packed) === 16
            && substr($packed, 0, 12) === $mappedV4Prefix
            && hash_equals($loopbackV4, substr($packed, 12)));
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = $_POST['_csrf'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        throw new RuntimeException('Your session expired or the form token was invalid. Please try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['_flashes'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $flashes = $_SESSION['_flashes'] ?? [];
    unset($_SESSION['_flashes']);
    return is_array($flashes) ? $flashes : [];
}

function redirect(string $page = 'dashboard', array $params = []): never
{
    $query = http_build_query(['page' => $page] + $params);
    header('Location: index.php?' . $query, true, 303);
    exit;
}

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    $kind = $_SESSION['auth_type'] ?? null;
    $id = filter_var($_SESSION['auth_id'] ?? null, FILTER_VALIDATE_INT);
    if (!in_array($kind, ['admin', 'voter'], true) || !$id) {
        return null;
    }
    $table = $kind === 'admin' ? 'admins' : 'voters';
    $statement = pdo()->prepare("SELECT * FROM {$table} WHERE id = ?");
    $statement->execute([$id]);
    $record = $statement->fetch();
    if (!$record
        || !isset($_SESSION['auth_version'])
        || (int) $_SESSION['auth_version'] !== (int) $record['session_version']
        || ($kind === 'voter'
            && (!in_array(normalize_voter_status((string) $record['status']), ['active'], true)
                || empty($record['email_verified_at'])))) {
        unset($_SESSION['auth_type'], $_SESSION['auth_id'], $_SESSION['auth_version']);
        return null;
    }
    $user = $record + ['account_type' => $kind];
    $user['account_type'] = $kind;
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        flash('warning', 'Please sign in with an active account to continue.');
        redirect('login');
    }
    return $user;
}

function require_main_admin(): array
{
    $user = require_login();
    if ($user['account_type'] !== 'admin') {
        http_response_code(403);
        log_activity('unauthorized_access', 'page', null, ['requested' => $_GET['page'] ?? '']);
        throw new RuntimeException('This action is available to the Main Admin only.');
    }
    return $user;
}

function is_main_admin(): bool
{
    return (current_user()['account_type'] ?? null) === 'admin';
}

function is_junior_admin(?array $user = null): bool
{
    $user ??= current_user();
    return ($user['account_type'] ?? null) === 'voter' && !empty($user['is_junior_admin']);
}

function log_activity(string $event, ?string $targetType = null, ?int $targetId = null, array $details = []): void
{
    try {
        $user = current_user();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $packedIp = is_string($ip) ? @inet_pton($ip) : false;
        $stmt = pdo()->prepare(
            'INSERT INTO activity_logs (actor_type, actor_id, event_type, target_type, target_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $user['account_type'] ?? 'system',
            $user['id'] ?? null,
            $event,
            $targetType,
            $targetId,
            $details === [] ? null : json_encode($details, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            $packedIp === false ? null : $packedIp,
        ]);
    } catch (Throwable $exception) {
        error_log('Activity log write failed: ' . $exception->getMessage());
    }
}

function app_url(array $params = []): string
{
    assert_production_configuration();
    $base = rtrim(app_config('APP_BASE_URL', 'http://localhost/online_voting_system'), '/');
    return $base . '/index.php?' . http_build_query($params);
}

function local_datetime(?string $utc): string
{
    if (!$utc) {
        return '';
    }
    $date = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
    return $date->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('M j, Y g:i A');
}

function utc_datetime(string $local): string
{
    $date = new DateTimeImmutable($local, new DateTimeZone(date_default_timezone_get()));
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function page_url(string $page, array $extra = []): string
{
    return 'index.php?' . http_build_query(['page' => $page] + $extra);
}

function pagination(int $total, int $perPage = 10): array
{
    $requested = filter_var($_GET['per_page'] ?? $perPage, FILTER_VALIDATE_INT);
    if (in_array($requested, [10, 25, 50], true)) {
        $perPage = $requested;
    }
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($pages, max(1, (int) ($_GET['p'] ?? 1)));
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'per_page' => $perPage, 'total' => $total];
}
