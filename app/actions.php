<?php
declare(strict_types=1);

function post_value(string $key, int $maxLength = 10000, bool $required = true): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        throw new RuntimeException('Invalid form submission.');
    }
    $value = trim($value);
    if ($required && $value === '') {
        throw new RuntimeException('Please complete all required fields.');
    }
    if (mb_strlen($value, 'UTF-8') > $maxLength) {
        throw new RuntimeException('One of the submitted values is too long.');
    }
    return $value;
}

function validate_personal_data(array $input): array
{
    $fullName = post_value('full_name', 160);
    $nationalId = normalize_national_id(post_value('national_id', 80));
    $email = trim(post_value('email', 254));
    $emailNormalized = normalize_email($email);
    $dob = post_value('date_of_birth', 10);
    $gender = post_value('gender', 40);
    $phone = post_value('phone', 40);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter a valid email address.');
    }
    if (mb_strlen($nationalId, 'UTF-8') < 3) {
        throw new RuntimeException('Enter a valid National ID.');
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dob);
    if (!$date || $date->format('Y-m-d') !== $dob || $date > new DateTimeImmutable('today')) {
        throw new RuntimeException('Enter a valid date of birth.');
    }
    if (!in_array($gender, ['Female', 'Male', 'Non-binary', 'Prefer not to say', 'Other'], true)) {
        throw new RuntimeException('Select a valid gender option.');
    }
    if (!preg_match('/^[0-9+(). -]{5,40}$/', $phone)) {
        throw new RuntimeException('Enter a valid phone number.');
    }

    return [
        'full_name' => $fullName,
        'national_id' => $nationalId,
        'national_id_ciphertext' => encrypt_value($nationalId),
        'national_id_hmac' => identifier_hash('national_id', $nationalId),
        'email' => $email,
        'email_normalized' => $emailNormalized,
        'email_hmac' => identifier_hash('email', $emailNormalized),
        'date_of_birth' => $dob,
        'gender' => $gender,
        'phone' => $phone,
    ];
}

function validate_password(string $password): void
{
    if (strlen($password) < 12 || strlen($password) > 72) {
        throw new RuntimeException('Passwords must contain 12 to 72 bytes.');
    }
}

function normalize_voter_status(string $status): string
{
    return match ($status) {
        'approved', 'pending' => 'unverified',
        'rejected' => 'deactivated',
        default => $status,
    };
}

function voter_status_is_active(string $status): bool
{
    $normalized = normalize_voter_status($status);
    return in_array($normalized, ['active'], true);
}

function voter_status_needs_verification(string $status): bool
{
    $normalized = normalize_voter_status($status);
    return in_array($normalized, ['unverified'], true);
}

function voter_status_is_blocked(string $status): bool
{
    $normalized = normalize_voter_status($status);
    return in_array($normalized, ['suspended', 'deactivated'], true);
}

function rate_limit_hash(string $scope, string $value): string
{
    return hash_hmac('sha256', $scope . ':' . $value, hmac_key());
}

function rate_limit_active(PDO $pdo, string $scope, string $value): bool
{
    $stmt = $pdo->prepare('SELECT blocked_until FROM auth_rate_limits WHERE limiter_hash = ?');
    $stmt->execute([rate_limit_hash($scope, $value)]);
    $blockedUntil = $stmt->fetchColumn();
    return is_string($blockedUntil) && $blockedUntil > gmdate('Y-m-d H:i:s');
}

function record_rate_limit_failure(PDO $pdo, string $scope, string $value, int $limit, int $windowSeconds, int $blockSeconds): void
{
    $key = rate_limit_hash($scope, $value);
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare('INSERT IGNORE INTO auth_rate_limits (limiter_hash, attempts, window_started_at) VALUES (?, 0, ?)');
        $insert->execute([$key, $now->format('Y-m-d H:i:s')]);
        $stmt = $pdo->prepare('SELECT attempts, window_started_at FROM auth_rate_limits WHERE limiter_hash = ? FOR UPDATE');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $windowStart = new DateTimeImmutable($row['window_started_at'], new DateTimeZone('UTC'));
        if ($windowStart->modify('+' . $windowSeconds . ' seconds') <= $now) {
            $attempts = 0;
            $windowStart = $now;
        } else {
            $attempts = (int) $row['attempts'];
        }
        $attempts++;
        $blockedUntil = $attempts >= $limit
            ? $now->modify('+' . $blockSeconds . ' seconds')->format('Y-m-d H:i:s')
            : null;
        $update = $pdo->prepare('UPDATE auth_rate_limits SET attempts = ?, window_started_at = ?, blocked_until = ? WHERE limiter_hash = ?');
        $update->execute([$attempts, $windowStart->format('Y-m-d H:i:s'), $blockedUntil, $key]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function clear_rate_limit(PDO $pdo, string $scope, string $value): void
{
    $pdo->prepare('DELETE FROM auth_rate_limits WHERE limiter_hash = ?')
        ->execute([rate_limit_hash($scope, $value)]);
}

function request_ip_key(): string
{
    $address = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($address) && $address !== '' ? $address : 'unknown';
}

function otp_code_hash(string $flowHash, string $code): string
{
    return hash_hmac('sha256', 'email-otp:' . $flowHash . ':' . $code, hmac_key());
}

function issue_otp_challenge(PDO $pdo, string $purpose, string $email, ?string $payload = null, ?string $accountType = null, ?int $accountId = null, bool $sendEmail = true, bool $genericDeliveryFailure = false): void
{
    $allowed = ['registration', 'password_recovery', 'email_verification'];
    if (!in_array($purpose, $allowed, true)) {
        throw new RuntimeException('Unable to start email verification.');
    }
    $emailNormalized = normalize_email($email);
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $emailKey = $purpose . ':email';
    $ipKey = $purpose . ':ip';
    if (rate_limit_active($pdo, $emailKey, $emailNormalized) || rate_limit_active($pdo, $ipKey, request_ip_key())) {
        throw new RuntimeException('Please wait before requesting another verification code.');
    }
    record_rate_limit_failure($pdo, $emailKey, $emailNormalized, 6, 3600, 3600);
    record_rate_limit_failure($pdo, $ipKey, request_ip_key(), 12, 3600, 3600);

    $recent = $pdo->prepare(
        'SELECT last_sent_at FROM otp_challenges
         WHERE email_normalized = ? AND purpose = ? AND consumed_at IS NULL
         ORDER BY created_at DESC LIMIT 1'
    );
    $recent->execute([$emailNormalized, $purpose]);
    $lastSent = $recent->fetchColumn();
    if (is_string($lastSent) && (new DateTimeImmutable($lastSent, new DateTimeZone('UTC')))->modify('+60 seconds') > $now) {
        throw new RuntimeException('A code was sent recently. Please wait 60 seconds before requesting another.');
    }

    $flow = bin2hex(random_bytes(32));
    $flowHash = hash('sha256', $flow);
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = $now->modify('+10 minutes')->format('Y-m-d H:i:s');
    $pdo->beginTransaction();
    try {
        $invalidate = $pdo->prepare(
            'UPDATE otp_challenges SET consumed_at = ?, code_hash = NULL
             WHERE email_normalized = ? AND purpose = ? AND consumed_at IS NULL'
        );
        $invalidate->execute([$now->format('Y-m-d H:i:s'), $emailNormalized, $purpose]);
        $insert = $pdo->prepare(
            'INSERT INTO otp_challenges
                (purpose, flow_hash, email, email_normalized, account_type, account_id, payload, code_hash, expires_at, last_sent_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $purpose, $flowHash, $email, $emailNormalized, $accountType, $accountId, $payload,
            otp_code_hash($flowHash, $code), $expires, $now->format('Y-m-d H:i:s'),
        ]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    $_SESSION['otp_flow'] = $flow;
    $_SESSION['otp_purpose'] = $purpose;
    if ($sendEmail && !notify_email($email, 'email_otp_' . $purpose, 'Your verification code', "Your six-digit verification code is {$code}. It expires in 10 minutes. If you did not request this, you can ignore this message.\n")) {
        if ($genericDeliveryFailure) {
            return;
        }
        $pdo->prepare('UPDATE otp_challenges SET consumed_at = UTC_TIMESTAMP(), code_hash = NULL WHERE flow_hash = ?')
            ->execute([$flowHash]);
        unset($_SESSION['otp_flow'], $_SESSION['otp_purpose']);
        throw new RuntimeException('The verification email could not be sent. Please try again later.');
    }
}

function active_otp_challenge(PDO $pdo, ?string $expectedPurpose = null, bool $lock = false): ?array
{
    $flow = $_SESSION['otp_flow'] ?? null;
    $purpose = $_SESSION['otp_purpose'] ?? null;
    if (!is_string($flow) || !preg_match('/^[a-f0-9]{64}$/', $flow)
        || !is_string($purpose) || ($expectedPurpose !== null && $purpose !== $expectedPurpose)) {
        return null;
    }
    $sql = 'SELECT * FROM otp_challenges WHERE flow_hash = ? AND purpose = ? AND consumed_at IS NULL AND expires_at > UTC_TIMESTAMP()';
    if ($lock) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([hash('sha256', $flow), $purpose]);
    return $stmt->fetch() ?: null;
}

function masked_email(string $email): string
{
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    if ($local === '' || $domain === '') {
        return 'your email address';
    }
    $visible = mb_substr($local, 0, 1, 'UTF-8');
    return $visible . str_repeat('*', max(2, mb_strlen($local, 'UTF-8') - 1)) . '@' . $domain;
}

function otp_resend_available(array $challenge): bool
{
    $lastSent = new DateTimeImmutable($challenge['last_sent_at'], new DateTimeZone('UTC'));
    return $lastSent->modify('+60 seconds') <= new DateTimeImmutable('now', new DateTimeZone('UTC'))
        && (int) $challenge['resend_count'] < 5
        && empty($challenge['verified_at']);
}

function resend_otp_challenge(PDO $pdo): void
{
    $current = active_otp_challenge($pdo);
    if (!$current) {
        throw new RuntimeException('This code cannot be resent. Start again to request a new one.');
    }
    if (rate_limit_active($pdo, $current['purpose'] . ':resend-ip', request_ip_key())
        || rate_limit_active($pdo, $current['purpose'] . ':resend-email', $current['email_normalized'])) {
        throw new RuntimeException('Please wait before requesting another verification code.');
    }
    record_rate_limit_failure($pdo, $current['purpose'] . ':resend-ip', request_ip_key(), 8, 3600, 3600);
    record_rate_limit_failure($pdo, $current['purpose'] . ':resend-email', $current['email_normalized'], 5, 3600, 3600);
    if (rate_limit_active($pdo, $current['purpose'] . ':resend-ip', request_ip_key())
        || rate_limit_active($pdo, $current['purpose'] . ':resend-email', $current['email_normalized'])) {
        throw new RuntimeException('Please wait before requesting another verification code.');
    }
    $pdo->beginTransaction();
    try {
        $challenge = active_otp_challenge($pdo, null, true);
        if (!$challenge || !otp_resend_available($challenge)) {
            throw new RuntimeException('This code cannot be resent yet. Wait 60 seconds or start again.');
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $flowHash = hash('sha256', (string) $_SESSION['otp_flow']);
        $stmt = $pdo->prepare(
            'UPDATE otp_challenges SET code_hash = ?, attempts = 0, resend_count = resend_count + 1,
             expires_at = ?, last_sent_at = ?, verified_at = NULL WHERE id = ?'
        );
        $stmt->execute([
            otp_code_hash($flowHash, $code),
            $now->modify('+10 minutes')->format('Y-m-d H:i:s'),
            $now->format('Y-m-d H:i:s'),
            (int) $challenge['id'],
        ]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    if (in_array($challenge['purpose'], ['registration', 'email_verification'], true)
        || ($challenge['account_type'] !== null && (int) $challenge['account_id'] > 0)) {
        $body = "Your new six-digit verification code is {$code}. It expires in 10 minutes. If you did not request this, you can ignore this message.\n";
        if (!notify_email($challenge['email'], 'email_otp_' . $challenge['purpose'], 'Your verification code', $body)
            && $challenge['purpose'] !== 'password_recovery') {
            $pdo->prepare('UPDATE otp_challenges SET consumed_at = UTC_TIMESTAMP(), code_hash = NULL WHERE id = ?')
                ->execute([(int) $challenge['id']]);
            throw new RuntimeException('The verification email could not be sent. Please try again later.');
        }
    }
}

function handle_post(): void
{
    verify_csrf();
    $action = post_value('action', 50);
    switch ($action) {
        case 'login':
            action_login();
            break;
        case 'create_initial_admin':
            action_create_initial_admin();
            break;
        case 'logout':
            action_logout();
            break;
        case 'register':
            action_register();
            break;
        case 'verify_otp':
            action_verify_otp();
            break;
        case 'resend_otp':
            action_resend_otp();
            break;
        case 'request_reset':
            action_request_reset();
            break;
        case 'reset_password':
            action_reset_password();
            break;
        case 'change_password':
            action_change_password();
            break;
        case 'set_voter_status':
            action_set_voter_status();
            break;
        case 'promote':
        case 'demote':
            action_change_junior_status($action);
            break;
        case 'save_election':
            action_save_election();
            break;
        case 'close_election':
            action_close_election();
            break;
        case 'save_candidate':
            action_save_candidate();
            break;
        case 'vote':
            action_cast_vote();
            break;
        case 'update_profile':
            action_update_profile();
            break;
        case 'admin_update_voter':
            action_admin_update_voter();
            break;
        default:
            throw new RuntimeException('Unknown form action.');
    }
}

function action_create_initial_admin(): never
{
    if (!is_local_setup_request()) {
        http_response_code(404);
        throw new RuntimeException('This setup page is available only from the local computer.');
    }
    if ((int) pdo()->query('SELECT COUNT(*) FROM admins')->fetchColumn() !== 0) {
        throw new RuntimeException('The one-time Main Admin setup has already been completed.');
    }
    if (($_POST['setup_confirmation'] ?? '') !== 'confirmed') {
        throw new RuntimeException('Confirm that you want to create the initial Main Admin account.');
    }

    $fullName = post_value('full_name', 160);
    $email = trim(post_value('email', 254));
    $emailNormalized = normalize_email($email);
    $password = post_value('password', 72);
    $confirmPassword = post_value('confirm_password', 72);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter a valid email address.');
    }
    if ($password !== $confirmPassword) {
        throw new RuntimeException('The passwords do not match.');
    }
    validate_password($password);

    $pdo = pdo();
    $pdo->beginTransaction();
    try {
        if ((int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() !== 0) {
            throw new RuntimeException('The one-time Main Admin setup has already been completed.');
        }
        $voterEmail = $pdo->prepare('SELECT id FROM voters WHERE email_normalized = ?');
        $voterEmail->execute([$emailNormalized]);
        $historicalEmail = $pdo->prepare('SELECT id FROM voter_identifiers WHERE identifier_type = ? AND identifier_hmac = ?');
        $historicalEmail->execute(['email', identifier_hash('email', $emailNormalized)]);
        if ($voterEmail->fetchColumn() || $historicalEmail->fetchColumn()) {
            throw new RuntimeException('That email address is already associated with a voter application.');
        }
        $insert = $pdo->prepare(
            'INSERT INTO admins (singleton_key, full_name, email, email_normalized, password_hash)
             VALUES (1, ?, ?, ?, ?)'
        );
        $insert->execute([
            $fullName,
            $email,
            $emailNormalized,
            password_hash($password, PASSWORD_DEFAULT),
        ]);
        $adminId = (int) $pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof PDOException) {
            throw new RuntimeException('The Main Admin account could not be created. Check that the email is unused and try again.');
        }
        throw $exception;
    }

    log_activity('initial_main_admin_created', 'admin', $adminId);
    flash('success', 'The initial Main Admin account was created. Sign in with the account details you just set.');
    redirect('login');
}

function action_login(): never
{
    $login = post_value('login', 254);
    $password = post_value('password', 200);
    $pdo = pdo();
    $loginKey = filter_var($login, FILTER_VALIDATE_EMAIL)
        ? normalize_email($login)
        : normalize_national_id($login);
    if (rate_limit_active($pdo, 'login:ip', request_ip_key())
        || rate_limit_active($pdo, 'login:identifier', $loginKey)) {
        flash('error', 'Sign-in is temporarily unavailable. Please wait and try again.');
        redirect('login');
    }
    $record = null;
    $type = null;
    if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
        $normalized = normalize_email($login);
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE email_normalized = ?');
        $stmt->execute([$normalized]);
        $record = $stmt->fetch();
        $type = $record ? 'admin' : null;
        if (!$record) {
            $stmt = $pdo->prepare('SELECT * FROM voters WHERE email_normalized = ?');
            $stmt->execute([$normalized]);
            $record = $stmt->fetch();
            $type = $record ? 'voter' : null;
        }
    } else {
        $hash = identifier_hash('national_id', normalize_national_id($login));
        $stmt = $pdo->prepare('SELECT * FROM voters WHERE national_id_hmac = ?');
        $stmt->execute([$hash]);
        $record = $stmt->fetch();
        $type = $record ? 'voter' : null;
    }
    if (!$record || !password_verify($password, $record['password_hash'])) {
        record_rate_limit_failure($pdo, 'login:ip', request_ip_key(), 10, 900, 900);
        record_rate_limit_failure($pdo, 'login:identifier', $loginKey, 10, 900, 900);
        log_activity('login_failed');
        flash('error', 'The sign-in details were not recognized.');
        redirect('login');
    }
    clear_rate_limit($pdo, 'login:ip', request_ip_key());
    clear_rate_limit($pdo, 'login:identifier', $loginKey);
    if ($type === 'voter') {
        $status = normalize_voter_status((string) ($record['status'] ?? ''));
        if ($status === 'suspended') {
            flash('warning', 'Your account is suspended. Contact the administrator.');
            redirect('login');
        }
        if ($status === 'deactivated') {
            flash('warning', 'Your account is deactivated and cannot be used.');
            redirect('login');
        }
        if ($status === 'unverified' || empty($record['email_verified_at'])) {
            issue_otp_challenge($pdo, 'email_verification', $record['email'], null, 'voter', (int) $record['id']);
            flash('info', 'Verify your email address to continue signing in.');
            redirect('verify-otp');
        }
        if ($status !== 'active') {
            flash('warning', 'Your account is not active. Contact the administrator.');
            redirect('login');
        }
    }
    session_regenerate_id(true);
    $_SESSION['auth_type'] = $type;
    $_SESSION['auth_id'] = (int) $record['id'];
    $_SESSION['auth_version'] = (int) $record['session_version'];
    log_activity('login');
    flash('success', 'Welcome back, ' . $record['full_name'] . '.');
    redirect('dashboard');
}

function action_logout(): never
{
    $user = current_user();
    if ($user) {
        log_activity('logout');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], '', $params['secure'], $params['httponly']);
    }
    session_destroy();
    session_start();
    flash('success', 'You have been signed out.');
    redirect('login');
}

function action_register(): never
{
    $password = post_value('password', 200);
    $confirm = post_value('confirm_password', 200);
    if ($password !== $confirm) {
        throw new RuntimeException('The passwords do not match.');
    }
    validate_password($password);
    $data = validate_personal_data($_POST);
    $pdo = pdo();
    $existing = $pdo->prepare('SELECT voter_id FROM voter_identifiers WHERE identifier_type = ? AND identifier_hmac = ?');
    $existing->execute(['email', $data['email_hmac']]);
    if ($existing->fetchColumn()) {
        throw new RuntimeException('That email address is already associated with an application.');
    }
    $adminEmail = $pdo->prepare('SELECT id FROM admins WHERE email_normalized = ?');
    $adminEmail->execute([$data['email_normalized']]);
    if ($adminEmail->fetchColumn()) {
        throw new RuntimeException('That email address is reserved for an administrator account.');
    }
    $existing->execute(['national_id', $data['national_id_hmac']]);
    if ($existing->fetchColumn()) {
        throw new RuntimeException('That National ID is already associated with an application.');
    }
    $payload = encrypt_value(json_encode([
        'full_name' => $data['full_name'],
        'national_id_ciphertext' => $data['national_id_ciphertext'],
        'national_id_hmac' => $data['national_id_hmac'],
        'email' => $data['email'],
        'email_normalized' => $data['email_normalized'],
        'email_hmac' => $data['email_hmac'],
        'date_of_birth' => $data['date_of_birth'],
        'gender' => $data['gender'],
        'phone' => $data['phone'],
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ], JSON_THROW_ON_ERROR));
    issue_otp_challenge($pdo, 'registration', $data['email'], $payload);
    flash('info', 'A six-digit verification code was sent to your email address.');
    redirect('verify-otp');
}

function action_request_reset(): never
{
    $email = normalize_email(post_value('email', 254));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter a valid email address.');
    }
    $pdo = pdo();
    if (rate_limit_active($pdo, 'recovery:ip', request_ip_key())
        || rate_limit_active($pdo, 'recovery:email', $email)) {
        flash('success', 'If an eligible account uses that email, a verification code will be sent.');
        redirect('forgot-password');
    }
    record_rate_limit_failure($pdo, 'recovery:ip', request_ip_key(), 6, 900, 900);
    record_rate_limit_failure($pdo, 'recovery:email', $email, 4, 3600, 3600);
    $adminStmt = $pdo->prepare('SELECT id, email FROM admins WHERE email_normalized = ?');
    $adminStmt->execute([$email]);
    $admin = $adminStmt->fetch();
    $voterStmt = $pdo->prepare("SELECT id, email, status FROM voters WHERE email_normalized = ? AND status = 'active' AND email_verified_at IS NOT NULL");
    $voterStmt->execute([$email]);
    $voter = $voterStmt->fetch();
    $account = $admin ? ['admin', $admin] : ($voter ? ['voter', $voter] : [null, null]);
    try {
        issue_otp_challenge(
            $pdo,
            'password_recovery',
            $email,
            null,
            $account[0],
            $account[1] ? (int) $account[1]['id'] : null,
            $account[1] !== null,
            true
        );
    } catch (RuntimeException $exception) {
        if ($exception instanceof PDOException) {
            throw $exception;
        }
        flash('success', 'If an eligible account uses that email, a verification code will be sent.');
        redirect('forgot-password');
    }
    log_activity('password_reset_requested');
    flash('success', 'If an eligible account uses that email, a verification code will be sent.');
    redirect('verify-otp');
}

function action_reset_password(): never
{
    complete_password_recovery();
}

function complete_password_recovery(): never
{
    $password = post_value('password', 200);
    $confirm = post_value('confirm_password', 200);
    if ($password !== $confirm) {
        throw new RuntimeException('The new passwords do not match.');
    }
    validate_password($password);
    $pdo = pdo();
    $pdo->beginTransaction();
    try {
        $challenge = active_otp_challenge($pdo, 'password_recovery', true);
        if (!$challenge || empty($challenge['verified_at']) || !$challenge['account_type'] || !(int) $challenge['account_id']) {
            throw new RuntimeException('The verified recovery session is invalid or expired. Request a new code.');
        }
        $table = $challenge['account_type'] === 'admin' ? 'admins' : 'voters';
        $accountSql = $challenge['account_type'] === 'admin'
            ? 'SELECT id, email_normalized FROM admins WHERE id = ? FOR UPDATE'
            : "SELECT id, email_normalized, status, email_verified_at FROM voters WHERE id = ? FOR UPDATE";
        $accountStmt = $pdo->prepare($accountSql);
        $accountStmt->execute([(int) $challenge['account_id']]);
        $account = $accountStmt->fetch();
        if (!$account || $account['email_normalized'] !== $challenge['email_normalized']
            || ($challenge['account_type'] === 'voter'
                && (!voter_status_is_active((string) $account['status']) || empty($account['email_verified_at'])))) {
            throw new RuntimeException('This recovery request is no longer valid. Request a new code.');
        }
        $update = $pdo->prepare("UPDATE {$table} SET password_hash = ?, session_version = session_version + 1 WHERE id = ?");
        $update->execute([password_hash($password, PASSWORD_DEFAULT), (int) $challenge['account_id']]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('The account could not be updated. Request a new recovery code.');
        }
        $used = $pdo->prepare('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE account_type = ? AND account_id = ? AND used_at IS NULL');
        $used->execute([$challenge['account_type'], (int) $challenge['account_id']]);
        $invalidate = $pdo->prepare(
            "UPDATE otp_challenges SET consumed_at = UTC_TIMESTAMP(), code_hash = NULL
             WHERE purpose = 'password_recovery' AND account_type = ? AND account_id = ? AND consumed_at IS NULL"
        );
        $invalidate->execute([$challenge['account_type'], (int) $challenge['account_id']]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    unset($_SESSION['otp_flow'], $_SESSION['otp_purpose']);
    session_regenerate_id(true);
    log_activity('password_reset_completed', $challenge['account_type'], (int) $challenge['account_id']);
    flash('success', 'Password reset successfully. You can now sign in.');
    redirect('login');
}

function action_verify_otp(): never
{
    $code = post_value('otp', 6);
    if (!preg_match('/^[0-9]{6}$/', $code)) {
        throw new RuntimeException('Enter the six-digit verification code.');
    }
    $pdo = pdo();
    $pdo->beginTransaction();
    try {
        $challenge = active_otp_challenge($pdo, null, true);
        if (!$challenge) {
            throw new RuntimeException('This code is invalid or expired. Start again to request a new one.');
        }
        if (!empty($challenge['verified_at'])) {
            throw new RuntimeException('This verification code has already been used.');
        }
        if ((int) $challenge['attempts'] >= 5) {
            $pdo->prepare('UPDATE otp_challenges SET consumed_at = UTC_TIMESTAMP(), code_hash = NULL WHERE id = ?')
                ->execute([(int) $challenge['id']]);
            $pdo->commit();
            throw new RuntimeException('This code has expired after too many attempts. Request a new one.');
        }
        $flowHash = hash('sha256', (string) $_SESSION['otp_flow']);
        if (!is_string($challenge['code_hash']) || !hash_equals($challenge['code_hash'], otp_code_hash($flowHash, $code))) {
            $attempts = (int) $challenge['attempts'] + 1;
            $stmt = $pdo->prepare(
                'UPDATE otp_challenges SET attempts = ?, consumed_at = IF(? >= 5, UTC_TIMESTAMP(), consumed_at),
                 code_hash = IF(? >= 5, NULL, code_hash) WHERE id = ?'
            );
            $stmt->execute([$attempts, $attempts, $attempts, (int) $challenge['id']]);
            $pdo->commit();
            throw new RuntimeException($attempts >= 5
                ? 'This code has expired after too many attempts. Request a new one.'
                : 'The code is incorrect. Check it and try again.');
        }
        $now = gmdate('Y-m-d H:i:s');
        if ($challenge['purpose'] === 'registration') {
            $payload = json_decode(decrypt_value((string) $challenge['payload']), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                throw new RuntimeException('The registration could not be verified.');
            }
            $emailCheck = $pdo->prepare('SELECT voter_id FROM voter_identifiers WHERE identifier_type = ? AND identifier_hmac = ?');
            $emailCheck->execute(['email', $payload['email_hmac']]);
            $nationalCheck = $pdo->prepare('SELECT voter_id FROM voter_identifiers WHERE identifier_type = ? AND identifier_hmac = ?');
            $nationalCheck->execute(['national_id', $payload['national_id_hmac']]);
            $adminEmail = $pdo->prepare('SELECT id FROM admins WHERE email_normalized = ?');
            $adminEmail->execute([$payload['email_normalized']]);
            if ($emailCheck->fetchColumn() || $nationalCheck->fetchColumn() || $adminEmail->fetchColumn()) {
                throw new RuntimeException('The registration could not be completed because an identifier is already in use.');
            }
            $insert = $pdo->prepare(
                "INSERT INTO voters
                    (full_name, national_id_ciphertext, national_id_hmac, email, email_normalized, email_verified_at,
                     date_of_birth, gender, phone, password_hash, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')"
            );
            $insert->execute([
                $payload['full_name'], $payload['national_id_ciphertext'], $payload['national_id_hmac'],
                $payload['email'], $payload['email_normalized'], $now, $payload['date_of_birth'],
                $payload['gender'], $payload['phone'], $payload['password_hash'],
            ]);
            $voterId = (int) $pdo->lastInsertId();
            $registry = $pdo->prepare('INSERT INTO voter_identifiers (voter_id, identifier_type, identifier_hmac) VALUES (?, ?, ?)');
            $registry->execute([$voterId, 'email', $payload['email_hmac']]);
            $registry->execute([$voterId, 'national_id', $payload['national_id_hmac']]);
            $pdo->prepare('UPDATE otp_challenges SET verified_at = ?, consumed_at = ?, code_hash = NULL WHERE id = ?')
                ->execute([$now, $now, (int) $challenge['id']]);
            $pdo->commit();
            unset($_SESSION['otp_flow'], $_SESSION['otp_purpose']);
            log_activity('voter_registered', 'voter', $voterId);
            notify_email($payload['email'], 'registration_received', 'Registration successful', "Hello {$payload['full_name']},\n\nYour account is active and ready to use.\n");
            flash('success', 'Registration successful. Your account is active and ready to use.');
            redirect('login');
        }
        if ($challenge['purpose'] === 'email_verification') {
            if ($challenge['account_type'] !== 'voter' || !(int) $challenge['account_id']) {
                throw new RuntimeException('This verification request is no longer available.');
            }
            $voterStmt = $pdo->prepare("SELECT id, email, email_normalized, status, session_version FROM voters WHERE id = ? FOR UPDATE");
            $voterStmt->execute([(int) $challenge['account_id']]);
            $voter = $voterStmt->fetch();
            if (!$voter
                || !in_array(normalize_voter_status((string) $voter['status']), ['unverified', 'active'], true)
                || $voter['email_normalized'] !== $challenge['email_normalized']) {
                throw new RuntimeException('This verification request is no longer available.');
            }
            $pdo->prepare("UPDATE voters SET status = 'active', email_verified_at = ? WHERE id = ?")->execute([$now, (int) $voter['id']]);
            $pdo->prepare('UPDATE otp_challenges SET verified_at = ?, consumed_at = ?, code_hash = NULL WHERE id = ?')
                ->execute([$now, $now, (int) $challenge['id']]);
            $pdo->commit();
            unset($_SESSION['otp_flow'], $_SESSION['otp_purpose']);
            session_regenerate_id(true);
            $_SESSION['auth_type'] = 'voter';
            $_SESSION['auth_id'] = (int) $voter['id'];
            $_SESSION['auth_version'] = (int) $voter['session_version'];
            log_activity('voter_email_verified', 'voter', (int) $voter['id']);
            flash('success', 'Your email address is verified.');
            redirect('dashboard');
        }
        $pdo->prepare('UPDATE otp_challenges SET verified_at = ?, code_hash = NULL WHERE id = ?')
            ->execute([$now, (int) $challenge['id']]);
        $pdo->commit();
        flash('success', 'Your code was verified. Choose a new password to finish recovery.');
        redirect('new-password');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function action_resend_otp(): never
{
    resend_otp_challenge(pdo());
    flash('success', 'If this request is eligible, a new verification code has been sent.');
    redirect('verify-otp');
}

function action_change_password(): never
{
    $user = require_login();
    $current = post_value('current_password', 200);
    $password = post_value('password', 200);
    $confirm = post_value('confirm_password', 200);
    if (!password_verify($current, $user['password_hash'])) {
        throw new RuntimeException('The current password is incorrect.');
    }
    if ($password !== $confirm) {
        throw new RuntimeException('The new passwords do not match.');
    }
    validate_password($password);
    $pdo = pdo();
    $table = $user['account_type'] === 'admin' ? 'admins' : 'voters';
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE {$table} SET password_hash = ?, session_version = session_version + 1 WHERE id = ?");
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
        $pdo->prepare('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE account_type = ? AND account_id = ? AND used_at IS NULL')
            ->execute([$user['account_type'], (int) $user['id']]);
        $pdo->prepare(
            'UPDATE otp_challenges SET consumed_at = UTC_TIMESTAMP(), code_hash = NULL
             WHERE account_type = ? AND account_id = ? AND purpose = ? AND consumed_at IS NULL'
        )->execute([$user['account_type'], (int) $user['id'], 'password_recovery']);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    log_activity('password_changed', $user['account_type'], (int) $user['id']);
    unset($_SESSION['auth_type'], $_SESSION['auth_id'], $_SESSION['auth_version']);
    session_regenerate_id(true);
    flash('success', 'Your password has been updated. Sign in again with your new password.');
    redirect('login');
}

function action_set_voter_status(): never
{
    $admin = require_main_admin();
    $voterId = filter_var($_POST['voter_id'] ?? null, FILTER_VALIDATE_INT);
    $status = post_value('status', 20);
    if (!$voterId) {
        throw new RuntimeException('Invalid voter account.');
    }
    if (!in_array($status, ['unverified', 'active', 'suspended', 'deactivated'], true)) {
        throw new RuntimeException('Select a valid account status.');
    }
    $pdo = pdo();
    if ($status === 'active') {
        $current = $pdo->prepare('SELECT status, email_verified_at FROM voters WHERE id = ?');
        $current->execute([$voterId]);
        $currentStatus = $current->fetch();
        if (!$currentStatus) {
            throw new RuntimeException('The voter account could not be found.');
        }
        if (!in_array((string) $currentStatus['status'], ['active', 'suspended'], true)
            || empty($currentStatus['email_verified_at'])) {
            throw new RuntimeException('Only active or suspended voters with verified email can be set active. Unverified or deactivated accounts cannot be activated manually.');
        }
    }
    $stmt = $pdo->prepare('UPDATE voters SET status = ?, session_version = session_version + 1 WHERE id = ?');
    $stmt->execute([$status, $voterId]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('The voter account could not be updated.');
    }
    log_activity('voter_status_updated', 'voter', (int) $voterId, ['status' => $status]);
    flash('success', 'Voter account status updated.');
    redirect('voters');
}

function action_change_junior_status(string $action): never
{
    $admin = require_main_admin();
    $voterId = filter_var($_POST['voter_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$voterId) {
        throw new RuntimeException('Invalid voter account.');
    }
    $pdo = pdo();
    $stmt = $pdo->prepare("SELECT id, full_name, is_junior_admin, status FROM voters WHERE id = ? AND status = 'active' AND email_verified_at IS NOT NULL");
    $stmt->execute([$voterId]);
    $voter = $stmt->fetch();
    if (!$voter) {
        throw new RuntimeException('Only active voters can hold Junior Admin privileges.');
    }
    if (($action === 'promote' && (bool) $voter['is_junior_admin']) || ($action === 'demote' && !(bool) $voter['is_junior_admin'])) {
        throw new RuntimeException('The account already has the requested permission state.');
    }
    if ($action === 'promote') {
        $stmt = $pdo->prepare('UPDATE voters SET is_junior_admin = 1, promoted_by = ?, promoted_at = UTC_TIMESTAMP(), session_version = session_version + 1 WHERE id = ?');
        $stmt->execute([(int) $admin['id'], $voterId]);
    } else {
        $stmt = $pdo->prepare('UPDATE voters SET is_junior_admin = 0, promoted_by = NULL, promoted_at = NULL, session_version = session_version + 1 WHERE id = ?');
        $stmt->execute([$voterId]);
    }
    log_activity('junior_admin_' . $action, 'voter', (int) $voterId);
    flash('success', $action === 'promote' ? 'Voter promoted to Junior Admin.' : 'Junior Admin privileges removed.');
    redirect('voters');
}

function action_save_election(): never
{
    $admin = require_main_admin();
    $id = filter_var($_POST['election_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
    $name = post_value('name', 180);
    $description = post_value('description', 10000, false);
    $status = post_value('status', 20);
    $validStatuses = ['draft', 'scheduled', 'active'];
    if (!in_array($status, $validStatuses, true)) {
        throw new RuntimeException('Select a valid election status.');
    }
    $startInput = post_value('starts_at', 30);
    $endInput = post_value('ends_at', 30);
    $startsAt = utc_datetime(str_replace('T', ' ', $startInput));
    $endsAt = utc_datetime(str_replace('T', ' ', $endInput));
    if ($endsAt <= $startsAt) {
        throw new RuntimeException('The election end must be after its start.');
    }
    $pdo = pdo();
    $pdo->beginTransaction();
    try {
        if ($id) {
            $stmt = $pdo->prepare('SELECT * FROM elections WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $existing = $stmt->fetch();
            if (!$existing) {
                throw new RuntimeException('Election not found.');
            }
            if ($existing['status'] === 'closed' || effective_election_status($existing) === 'closed') {
                throw new RuntimeException('A closed election cannot be reopened or edited.');
            }
            $votes = $pdo->prepare('SELECT COUNT(*) FROM votes WHERE election_id = ?');
            $votes->execute([$id]);
            if ((int) $votes->fetchColumn() > 0) {
                throw new RuntimeException('Election details cannot be changed after votes have been cast.');
            }
            $stmt = $pdo->prepare('UPDATE elections SET name = ?, description = ?, starts_at = ?, ends_at = ?, status = ? WHERE id = ?');
            $stmt->execute([$name, $description, $startsAt, $endsAt, $status, $id]);
            $event = 'election_updated';
            $targetId = $id;
        } else {
            $stmt = $pdo->prepare('INSERT INTO elections (name, description, starts_at, ends_at, status, created_by) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $description, $startsAt, $endsAt, $status, (int) $admin['id']]);
            $event = 'election_created';
            $targetId = (int) $pdo->lastInsertId();
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    log_activity($event, 'election', $targetId);
    flash('success', $id ? 'Election updated.' : 'Election created.');
    redirect('elections');
}

function action_close_election(): never
{
    require_main_admin();
    $id = filter_var($_POST['election_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id) {
        throw new RuntimeException('Invalid election.');
    }
    $pdo = pdo();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id, status FROM elections WHERE id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $election = $stmt->fetch();
        if (!$election || $election['status'] === 'closed') {
            throw new RuntimeException('Election was not found or is already closed.');
        }
        $pdo->prepare("UPDATE elections SET status = 'closed', closed_at = UTC_TIMESTAMP() WHERE id = ?")
            ->execute([$id]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    log_activity('election_closed', 'election', (int) $id);
    flash('success', 'Election closed. Results are now available to voters.');
    redirect('elections');
}

function action_save_candidate(): never
{
    require_main_admin();
    $id = filter_var($_POST['candidate_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
    $name = post_value('full_name', 160);
    $party = post_value('party', 160, false);
    $bio = post_value('biography', 5000, false);
    $pdo = pdo();
    $submittedElectionId = filter_var($_POST['election_id'] ?? null, FILTER_VALIDATE_INT);
    $newPhotoPath = null;
    $oldPhotoPath = null;
    $pdo->beginTransaction();
    try {
        if ($id) {
            $lookup = $pdo->prepare('SELECT election_id FROM candidates WHERE id = ?');
            $lookup->execute([$id]);
            $actualElectionId = $lookup->fetchColumn();
            if (!$actualElectionId) {
                throw new RuntimeException('Candidate not found.');
            }
            $electionId = (int) $actualElectionId;
            if (!$submittedElectionId || $submittedElectionId !== $electionId) {
                throw new RuntimeException('A candidate cannot be reassigned to another election.');
            }
        } else {
            if (!$submittedElectionId) {
                throw new RuntimeException('Select an election.');
            }
            $electionId = $submittedElectionId;
        }
        $electionStmt = $pdo->prepare('SELECT * FROM elections WHERE id = ? FOR UPDATE');
        $electionStmt->execute([$electionId]);
        $election = $electionStmt->fetch();
        if (!$election) {
            throw new RuntimeException('Selected election does not exist.');
        }
        if ($election['status'] === 'closed' || effective_election_status($election) === 'closed') {
            throw new RuntimeException('Candidates cannot be added to or changed in a closed election.');
        }
        $voteCheck = $pdo->prepare('SELECT COUNT(*) FROM votes WHERE election_id = ?');
        $voteCheck->execute([$electionId]);
        if ((int) $voteCheck->fetchColumn() > 0) {
            throw new RuntimeException('Candidates cannot be added, changed, or removed after votes have been cast.');
        }
        if ($id) {
            $existingStmt = $pdo->prepare('SELECT * FROM candidates WHERE id = ? AND election_id = ? FOR UPDATE');
            $existingStmt->execute([$id, $electionId]);
            $existing = $existingStmt->fetch();
            if (!$existing) {
                throw new RuntimeException('Candidate or election changed. Reload and try again.');
            }
            $oldPhotoPath = $existing['photo_path'];
            $photoPath = $oldPhotoPath;
        } else {
            $photoPath = '';
        }
        $file = $_FILES['photo'] ?? null;
        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $newPhotoPath = store_candidate_photo($file);
            $photoPath = $newPhotoPath;
        } elseif (!$id) {
            throw new RuntimeException('Upload a candidate photograph.');
        }
        if ($id) {
            $stmt = $pdo->prepare('UPDATE candidates SET full_name = ?, party = ?, biography = ?, photo_path = ? WHERE id = ? AND election_id = ?');
            $stmt->execute([$name, $party, $bio, $photoPath, $id, $electionId]);
            $event = 'candidate_updated';
            $targetId = $id;
        } else {
            $stmt = $pdo->prepare('INSERT INTO candidates (election_id, full_name, party, biography, photo_path) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$electionId, $name, $party, $bio, $photoPath]);
            $event = 'candidate_created';
            $targetId = (int) $pdo->lastInsertId();
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($newPhotoPath !== null) {
            delete_candidate_photo($newPhotoPath);
        }
        throw $exception;
    }
    if ($newPhotoPath !== null && $oldPhotoPath !== null && $newPhotoPath !== $oldPhotoPath) {
        delete_candidate_photo($oldPhotoPath);
    }
    log_activity($event, 'candidate', $targetId);
    flash('success', $id ? 'Candidate updated.' : 'Candidate added.');
    redirect('candidates');
}

function store_candidate_photo(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > 3 * 1024 * 1024
        || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Photograph upload failed or exceeds the 3 MB limit.');
    }
    $image = @getimagesize($file['tmp_name']);
    $mime = is_array($image) ? ($image['mime'] ?? '') : '';
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime]) || (int) ($image[0] ?? 0) < 1 || (int) ($image[1] ?? 0) < 1
        || (int) $image[0] * (int) $image[1] > 25000000) {
        throw new RuntimeException('Photographs must be JPEG, PNG, or WebP images.');
    }
    $filename = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
    $destination = APP_ROOT . '/uploads/candidates/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Could not store the candidate photograph.');
    }
    return 'uploads/candidates/' . $filename;
}

function delete_candidate_photo(string $relativePath): void
{
    if (!preg_match('#^uploads/candidates/[a-f0-9]{40}\.(?:jpg|png|webp)$#', $relativePath)) {
        return;
    }
    $absolute = APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($absolute) && !unlink($absolute)) {
        error_log('Unable to remove a candidate photograph after an update.');
    }
}

function action_cast_vote(): never
{
    $user = require_login();
    if ($user['account_type'] !== 'voter' || !voter_status_is_active((string) $user['status'])) {
        throw new RuntimeException('Only active voters can cast a vote.');
    }
    $electionId = filter_var($_POST['election_id'] ?? null, FILTER_VALIDATE_INT);
    $candidateId = filter_var($_POST['candidate_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$electionId || !$candidateId) {
        throw new RuntimeException('Select a valid candidate.');
    }
    $pdo = pdo();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM elections WHERE id = ? FOR UPDATE');
        $stmt->execute([$electionId]);
        $election = $stmt->fetch();
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if (!$election || in_array($election['status'], ['draft', 'closed'], true)
            || $now < new DateTimeImmutable($election['starts_at'], new DateTimeZone('UTC'))
            || $now >= new DateTimeImmutable($election['ends_at'], new DateTimeZone('UTC'))) {
            throw new RuntimeException('This election is not currently accepting votes.');
        }
        $candidate = $pdo->prepare('SELECT id FROM candidates WHERE id = ? AND election_id = ?');
        $candidate->execute([$candidateId, $electionId]);
        if (!$candidate->fetchColumn()) {
            throw new RuntimeException('The selected candidate does not belong to this election.');
        }
        $stmt = $pdo->prepare('INSERT INTO votes (voter_id, election_id, candidate_id) VALUES (?, ?, ?)');
        $stmt->execute([(int) $user['id'], $electionId, $candidateId]);
        $pdo->commit();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception->getCode() === '23000') {
            throw new RuntimeException('You have already voted in this election.');
        }
        throw $exception;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    log_activity('vote_cast', 'election', (int) $electionId);
    flash('success', 'Your vote was recorded successfully. Votes cannot be changed.');
    redirect('election', ['id' => $electionId]);
}

function action_update_profile(): never
{
    $user = require_login();
    if ($user['account_type'] === 'admin') {
        $name = post_value('full_name', 160);
        $email = trim(post_value('email', 254));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email address.');
        }
        $stmt = pdo()->prepare('SELECT COUNT(*) FROM voters WHERE email_normalized = ?');
        $stmt->execute([normalize_email($email)]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException('That email is associated with a voter account.');
        }
        $historical = pdo()->prepare('SELECT voter_id FROM voter_identifiers WHERE identifier_type = ? AND identifier_hmac = ?');
        $historical->execute(['email', identifier_hash('email', normalize_email($email))]);
        if ($historical->fetchColumn()) {
            throw new RuntimeException('That email is reserved to a voter application.');
        }
        pdo()->prepare('UPDATE admins SET full_name = ?, email = ?, email_normalized = ? WHERE id = ?')
            ->execute([$name, $email, normalize_email($email), (int) $user['id']]);
    } else {
        $name = post_value('full_name', 160);
        $phone = post_value('phone', 40);
        if (!preg_match('/^[0-9+(). -]{5,40}$/', $phone)) {
            throw new RuntimeException('Enter a valid phone number.');
        }
        pdo()->prepare('UPDATE voters SET full_name = ?, phone = ? WHERE id = ?')->execute([$name, $phone, (int) $user['id']]);
    }
    log_activity('profile_updated', $user['account_type'], (int) $user['id']);
    flash('success', 'Your profile was updated.');
    redirect('profile');
}

function action_admin_update_voter(): never
{
    $admin = require_main_admin();
    $voterId = filter_var($_POST['voter_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$voterId) {
        throw new RuntimeException('Invalid voter record.');
    }
    $data = validate_personal_data($_POST);
    $pdo = pdo();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM voters WHERE id = ? FOR UPDATE');
        $stmt->execute([$voterId]);
        $old = $stmt->fetch();
        if (!$old) {
            throw new RuntimeException('Voter record not found.');
        }
        $ownerCheck = $pdo->prepare('SELECT voter_id FROM voter_identifiers WHERE identifier_type = ? AND identifier_hmac = ?');
        foreach ([['email', $data['email_hmac']], ['national_id', $data['national_id_hmac']]] as [$type, $hash]) {
            $ownerCheck->execute([$type, $hash]);
            $owner = $ownerCheck->fetchColumn();
            if ($owner && (int) $owner !== $voterId) {
                throw new RuntimeException('An email address or National ID is reserved to another applicant.');
            }
        }
        $adminEmail = $pdo->prepare('SELECT id FROM admins WHERE email_normalized = ?');
        $adminEmail->execute([$data['email_normalized']]);
        if ($adminEmail->fetchColumn()) {
            throw new RuntimeException('That email address is reserved for the Main Admin.');
        }
        $previousProfile = [
            'full_name' => $old['full_name'],
            'date_of_birth' => $old['date_of_birth'],
            'gender' => $old['gender'],
            'phone' => $old['phone'],
            'status' => $old['status'],
        ];
        $newProfile = [
            'full_name' => $data['full_name'],
            'date_of_birth' => $data['date_of_birth'],
            'gender' => $data['gender'],
            'phone' => $data['phone'],
            'status' => $old['status'],
        ];
        $pdo->prepare(
            'INSERT INTO voter_profile_changes
                (voter_id, changed_by, previous_email, new_email, previous_national_id_ciphertext,
                 new_national_id_ciphertext, previous_profile, new_profile)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $voterId, (int) $admin['id'], $old['email'], $data['email'], $old['national_id_ciphertext'],
            $data['national_id_ciphertext'], json_encode($previousProfile, JSON_THROW_ON_ERROR), json_encode($newProfile, JSON_THROW_ON_ERROR),
        ]);
        $pdo->prepare(
            'UPDATE voters SET email_verified_at = IF(email_normalized <> ?, NULL, email_verified_at),
             session_version = session_version + IF(email_normalized <> ?, 1, 0),
             full_name = ?, national_id_ciphertext = ?, national_id_hmac = ?, email = ?, email_normalized = ?,
             date_of_birth = ?, gender = ?, phone = ? WHERE id = ?'
        )->execute([
            $data['email_normalized'], $data['email_normalized'], $data['full_name'], $data['national_id_ciphertext'], $data['national_id_hmac'], $data['email'],
            $data['email_normalized'], $data['date_of_birth'], $data['gender'], $data['phone'], $voterId,
        ]);
        $registry = $pdo->prepare('INSERT IGNORE INTO voter_identifiers (voter_id, identifier_type, identifier_hmac) VALUES (?, ?, ?)');
        $registry->execute([$voterId, 'email', $data['email_hmac']]);
        $registry->execute([$voterId, 'national_id', $data['national_id_hmac']]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    log_activity('voter_profile_updated_by_admin', 'voter', (int) $voterId, ['changed_by' => (int) $admin['id']]);
    flash('success', 'Voter profile updated and previous identifiers preserved.');
    redirect('voter', ['id' => $voterId]);
}
