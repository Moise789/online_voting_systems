<?php
declare(strict_types=1);

function effective_election_status(array $election): string
{
    if ($election['status'] === 'closed' || $election['status'] === 'draft') {
        return $election['status'];
    }
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    if ($now < new DateTimeImmutable($election['starts_at'], new DateTimeZone('UTC'))) {
        return 'scheduled';
    }
    if ($now >= new DateTimeImmutable($election['ends_at'], new DateTimeZone('UTC'))) {
        return 'closed';
    }
    return 'active';
}

function badge(string $status): string
{
    $class = match ($status) {
        'approved', 'active' => 'success',
        'pending', 'unverified', 'scheduled' => 'warning',
        'rejected', 'closed', 'deactivated' => 'secondary',
        'suspended' => 'danger',
        'draft' => 'info',
        default => 'primary',
    };
    return '<span class="badge text-bg-' . $class . '">' . e(ucfirst($status)) . '</span>';
}

function render_page(): void
{
    $page = is_string($_GET['page'] ?? null) ? $_GET['page'] : 'dashboard';
    $publicPages = ['login', 'register', 'forgot-password', 'reset', 'verify-otp', 'new-password', 'setup-admin', 'config-setup'];
    $user = current_user();
    if (!in_array($page, $publicPages, true) && !$user) {
        flash('warning', 'Please sign in to continue.');
        redirect('login');
    }
    if ($user && in_array($page, ['login', 'register'], true)) {
        redirect('dashboard');
    }
    $title = 'Dashboard';
    $chartData = null;
    ob_start();
    switch ($page) {
        case 'login': page_login(); $title = 'Sign in'; break;
        case 'setup-admin': page_setup_admin(); $title = 'Initial Main Admin setup'; break;
        case 'config-setup': page_config_setup(); $title = 'Configuration setup'; break;
        case 'register': page_register(); $title = 'Voter registration'; break;
        case 'forgot-password': page_forgot_password(); $title = 'Password recovery'; break;
        case 'reset': page_reset_password(); $title = 'Reset password'; break;
        case 'verify-otp': page_otp_verification(); $title = 'Email verification'; break;
        case 'new-password': page_new_password(); $title = 'Choose a new password'; break;
        case 'dashboard': [$chartData, $title] = page_dashboard($user); break;
        case 'elections': page_elections($user); $title = 'Elections'; break;
        case 'election': page_election($user); $title = 'Election'; break;
        case 'election-form': require_main_admin(); page_election_form(); $title = 'Manage election'; break;
        case 'candidates': page_candidates($user); $title = 'Candidates'; break;
        case 'candidate-form': require_main_admin(); page_candidate_form(); $title = 'Manage candidate'; break;
        case 'voters':
            if ($user['account_type'] !== 'admin' && !is_junior_admin($user)) {
                http_response_code(403);
                log_activity('unauthorized_access', 'page', null, ['requested' => 'voters']);
                throw new RuntimeException('Registered voter information is available to administrators only.');
            }
            page_voters($user);
            $title = 'Registered voters';
            break;
        case 'voter': require_main_admin(); page_voter_detail(); $title = 'Voter application'; break;
        case 'results': page_results($user); $title = 'Election results'; break;
        case 'history': page_voting_history($user); $title = 'Voting history'; break;
        case 'activity': page_activity($user); $title = 'Activity log'; break;
        case 'votes': require_main_admin(); page_votes(); $title = 'Vote records'; break;
        case 'profile': page_profile($user); $title = 'Profile and security'; break;
        default:
            http_response_code(404);
            echo '<div class="alert alert-warning">The requested page was not found.</div>';
            $title = 'Not found';
    }
    $content = (string) ob_get_clean();
    render_layout($title, $content, $chartData);
}

function page_setup_admin(): void
{
    if (!is_local_setup_request()) {
        http_response_code(404);
        echo '<div class="auth-shell"><div class="auth-card card"><h1 class="h4">Setup unavailable</h1><p class="mb-0 text-secondary">The initial account setup is available only from the local computer.</p></div></div>';
        return;
    }
    if ((int) pdo()->query('SELECT COUNT(*) FROM admins')->fetchColumn() !== 0) {
        ?>
        <div class="auth-shell"><section class="auth-card card">
            <div class="brand-mark"><i class="bi bi-shield-check"></i></div>
            <h1 class="h3 text-center">Setup already completed</h1>
            <p class="text-secondary text-center mb-0">An initial Main Admin account already exists. This one-time setup page is disabled.</p>
        </section></div>
        <?php
        return;
    }
    ?>
    <div class="auth-shell"><section class="auth-card card setup-card">
        <div class="brand-mark"><i class="bi bi-shield-lock"></i></div>
        <p class="eyebrow text-center">One-time local setup</p>
        <h1 class="h3 text-center">Create the Main Admin</h1>
        <p class="text-secondary text-center">This page is available only from this computer and will be disabled after the first account is created.</p>
        <div class="alert alert-info"><i class="bi bi-info-circle"></i> Choose a unique email and a strong password of 12–72 characters. Passwords are never stored in plain text.</div>
        <form method="post" class="vstack gap-3" id="initialAdminSetupForm" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_initial_admin">
            <label class="form-label">Full name
                <input class="form-control" name="full_name" maxlength="160" autocomplete="name" required>
            </label>
            <label class="form-label">Email address
                <input class="form-control" type="email" name="email" maxlength="254" autocomplete="email" required>
            </label>
            <label class="form-label">Password
                <span class="password-control"><input class="form-control" id="setupPassword" type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required><button class="password-toggle" type="button" data-password-toggle="setupPassword" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button></span>
            </label>
            <label class="form-label">Confirm password
                <span class="password-control"><input class="form-control" id="setupConfirmPassword" type="password" name="confirm_password" minlength="12" maxlength="72" autocomplete="new-password" required><button class="password-toggle" type="button" data-password-toggle="setupConfirmPassword" aria-label="Show confirm password" aria-pressed="false"><i class="bi bi-eye"></i></button></span>
            </label>
            <label class="setup-confirm"><input class="form-check-input" type="checkbox" name="setup_confirmation" value="confirmed" required><span>I confirm that I want to create the single initial Main Admin account.</span></label>
            <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-shield-check"></i> Create Main Admin account</button>
        </form>
        <p class="small text-secondary text-center mt-3 mb-0">Account creation is protected by a CSRF token and a database-level single-account constraint.</p>
    </section></div>
    <?php
}

function page_config_setup(): void
{
    if (!is_local_setup_request()) {
        http_response_code(403);
        echo '<div class="auth-shell"><div class="auth-card card"><h1 class="h4">Configuration unavailable</h1><p class="mb-0 text-secondary">The configuration wizard is available only from the local computer.</p></div></div>';
        return;
    }

    $checks = runtime_configuration_checks();
    ?>
    <div class="auth-shell">
        <div class="auth-card card" style="max-width: 980px;">
            <div class="brand-mark"><i class="bi bi-tools"></i></div>
            <h1 class="h3 text-center">Configuration setup wizard</h1>
            <p class="text-secondary text-center">This page checks your local configuration. It never accepts or saves passwords, encryption keys, or other credentials.</p>
            <div class="alert alert-info"><strong>Private configuration file:</strong> <code>C:\xampp\private\online_voting_system.env</code><br>Open this file locally in Notepad to enter configuration. Never enter private credentials in this browser page, chat, source code, or GitHub.</div>

            <hr>

            <h2 class="h4">Configuration validation</h2>
            <div class="vstack gap-2">
                <?php foreach ($checks as $check): ?>
                    <?php
                    $statusClass = match ($check['status']) {
                        'pass' => 'success',
                        'warning' => 'warning',
                        default => 'danger',
                    };
                    ?>
                    <div class="alert alert-<?= e($statusClass) ?> mb-0">
                        <strong><?= e(strtoupper($check['status'])) ?>:</strong> <?= e($check['label']) ?> — <?= e($check['message']) ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <hr>

            <h2 class="h4">Gmail SMTP setup</h2>
            <ol>
                <li>Open your Google Account and enable 2-Step Verification.</li>
                <li>Go to Google Account &gt; Security &gt; App passwords.</li>
                <li>Create an app password for this project and copy the 16-character value.</li>
                <li>Enter it into <code>SMTP_PASSWORD</code> in your local private config file. Do not share it in chat or source control.</li>
                <li>Use your Gmail address for <code>SMTP_USERNAME</code> and <code>SMTP_FROM_EMAIL</code>.</li>
                <li>Set <code>SMTP_HOST=smtp.gmail.com</code>, <code>SMTP_PORT=587</code>, and <code>SMTP_SECURITY=starttls</code>.</li>
                <li>Send a test email using the project registration or password recovery flow and check the SMTP error log if sending fails.</li>
            </ol>

            <h2 class="h4">Encryption keys</h2>
            <p>Generate a fresh key locally and store it in your private config file only:</p>
            <pre class="bg-light p-3 rounded">&lt;C:\xampp\php\php.exe&gt; -r "echo base64_encode(random_bytes(32)), PHP_EOL;"</pre>
            <p>Run the command twice. Put the first result in <code>APP_ENCRYPTION_KEY</code> and the second in <code>APP_HMAC_KEY</code>. Do not replace existing keys if encrypted National IDs already exist; losing or changing them may make stored data unusable.</p>

            <h2 class="h4">Hosting checklist</h2>
            <p>Use the hosting checklist in the project docs to gather the values supplied by your host, Gmail, and local machine. Do not paste private credentials into the chat window.</p>
        </div>
    </div>
    <?php
}

function render_layout(string $title, string $content, ?array $chartData = null): void
{
    $user = current_user();
    $flashes = take_flashes();
    require APP_ROOT . '/app/layout.php';
}

function page_login(): void
{
    ?>
    <div class="auth-shell">
        <div class="auth-card card">
            <div class="brand-mark"><i class="bi bi-check2-square"></i></div>
            <h1 class="h3 text-center">Welcome back</h1>
            <p class="text-secondary text-center">Sign in to the Mobile and Online Voting System.</p>
            <form method="post" class="vstack gap-3">
                <?= csrf_field() ?><input type="hidden" name="action" value="login">
                <label class="form-label">Email or National ID
                    <input class="form-control" name="login" autocomplete="username" required>
                </label>
                <label class="form-label">Password
                    <input class="form-control" name="password" type="password" autocomplete="current-password" required>
                </label>
                <button class="btn btn-primary btn-lg" type="submit">Sign in</button>
            </form>
            <div class="d-flex justify-content-between mt-3 small">
                <a href="<?= e(page_url('forgot-password')) ?>">Forgot password?</a>
                <a href="<?= e(page_url('register')) ?>">Register to vote</a>
            </div>
        </div>
    </div>
    <?php
}

function person_fields(array $values = []): void
{
    $gender = $values['gender'] ?? '';
    ?>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Full name<input class="form-control" name="full_name" maxlength="160" value="<?= e($values['full_name'] ?? '') ?>" required></label></div>
        <div class="col-md-6"><label class="form-label">National ID<input class="form-control" name="national_id" maxlength="80" value="<?= e($values['national_id'] ?? '') ?>" required></label></div>
        <div class="col-md-6"><label class="form-label">Date of birth<input class="form-control" type="date" name="date_of_birth" value="<?= e($values['date_of_birth'] ?? '') ?>" max="<?= e(date('Y-m-d')) ?>" required></label></div>
        <div class="col-md-6"><label class="form-label">Gender<select class="form-select" name="gender" required><option value="">Select</option><?php foreach (['Female','Male','Non-binary','Prefer not to say','Other'] as $option): ?><option <?= $gender === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach ?></select></label></div>
        <div class="col-md-6"><label class="form-label">Phone number<input class="form-control" type="tel" name="phone" maxlength="40" value="<?= e($values['phone'] ?? '') ?>" required></label></div>
        <div class="col-md-6"><label class="form-label">Email address<input class="form-control" type="email" name="email" maxlength="254" value="<?= e($values['email'] ?? '') ?>" required></label></div>
    </div>
    <?php
}

function page_register(): void
{
    ?>
    <div class="auth-shell"><div class="auth-card card auth-card-wide">
        <h1 class="h3">Voter registration</h1>
        <p class="text-secondary">Register, verify your email, and your account becomes active automatically.</p>
        <form method="post" class="vstack gap-3" data-validate>
            <?= csrf_field() ?><input type="hidden" name="action" value="register">
            <?php person_fields() ?>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Password<input class="form-control" type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required></label></div>
                <div class="col-md-6"><label class="form-label">Confirm password<input class="form-control" type="password" name="confirm_password" minlength="12" maxlength="72" autocomplete="new-password" required></label></div>
            </div>
            <div class="form-text">Use at least 12 characters. Your National ID is encrypted. Registration requires a successful email verification code before the account can be used.</div>
            <button class="btn btn-primary" type="submit">Continue to email verification</button>
        </form>
        <p class="small mt-3 mb-0"><a href="<?= e(page_url('login')) ?>">Already registered? Sign in</a></p>
    </div></div>
    <?php
}

function page_forgot_password(): void
{
    ?>
    <div class="auth-shell"><div class="auth-card card">
        <div class="brand-mark"><i class="bi bi-shield-lock"></i></div>
        <h1 class="h3">Recover your account</h1>
        <p class="text-secondary">Enter your account email. If eligible, you’ll receive a six-digit verification code.</p>
        <form method="post" class="vstack gap-3">
            <?= csrf_field() ?><input type="hidden" name="action" value="request_reset">
            <label class="form-label">Email address<input class="form-control" type="email" name="email" maxlength="254" autocomplete="email" required></label>
            <button class="btn btn-primary">Send verification code</button>
        </form>
        <p class="small mt-3 mb-0"><a href="<?= e(page_url('login')) ?>">Return to sign in</a></p>
    </div></div>
    <?php
}

function page_reset_password(): void
{
    echo '<div class="auth-shell"><div class="auth-card card"><h1 class="h3">Password recovery updated</h1><p class="text-secondary">For your security, password recovery now requires an email verification code.</p><a class="btn btn-primary" href="' . e(page_url('forgot-password')) . '">Request a verification code</a></div></div>';
}

function page_otp_verification(): void
{
    $challenge = active_otp_challenge(pdo());
    if (!$challenge) {
        echo '<div class="auth-shell"><div class="auth-card card"><h1 class="h3">Verification unavailable</h1><p class="text-secondary">This code is invalid, expired, or already used. Start again to request a new code.</p><a class="btn btn-primary" href="' . e(page_url('forgot-password')) . '">Continue</a></div></div>';
        return;
    }
    if (!empty($challenge['verified_at']) && $challenge['purpose'] === 'password_recovery') {
        echo '<div class="auth-shell"><div class="auth-card card"><h1 class="h3">Code already verified</h1><p class="text-secondary">Continue to choose a new password.</p><a class="btn btn-primary" href="' . e(page_url('new-password')) . '">Choose a new password</a></div></div>';
        return;
    }
    $resendAvailable = otp_resend_available($challenge);
    ?>
    <div class="auth-shell"><div class="auth-card card">
        <div class="brand-mark"><i class="bi bi-envelope-check"></i></div>
        <p class="eyebrow text-center">Secure verification</p>
        <h1 class="h3 text-center">Enter your verification code</h1>
        <p class="text-secondary text-center">We sent a six-digit code to <strong><?= e(masked_email($challenge['email'])) ?></strong>. It expires in 10 minutes.</p>
        <form method="post" class="vstack gap-3">
            <?= csrf_field() ?><input type="hidden" name="action" value="verify_otp">
            <label class="form-label">Six-digit code<input class="form-control otp-input" type="text" name="otp" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" required autofocus></label>
            <button class="btn btn-primary btn-lg">Verify code</button>
        </form>
        <form method="post" class="mt-3">
            <?= csrf_field() ?><input type="hidden" name="action" value="resend_otp">
            <button class="btn btn-outline-primary w-100" <?= $resendAvailable ? '' : 'disabled' ?>>Resend code<?= $resendAvailable ? '' : ' (wait 60 seconds)' ?></button>
        </form>
        <p class="small text-secondary text-center mt-3 mb-0">For your security, a code can be used once and has five attempts.</p>
    </div></div>
    <?php
}

function page_new_password(): void
{
    $challenge = active_otp_challenge(pdo(), 'password_recovery');
    if (!$challenge || empty($challenge['verified_at']) || !$challenge['account_type'] || !(int) $challenge['account_id']) {
        echo '<div class="auth-shell"><div class="auth-card card"><h1 class="h3">Verification required</h1><p class="text-secondary">Verify a recovery code before choosing a new password.</p><a class="btn btn-primary" href="' . e(page_url('forgot-password')) . '">Start password recovery</a></div></div>';
        return;
    }
    ?>
    <div class="auth-shell"><div class="auth-card card">
        <div class="brand-mark"><i class="bi bi-key"></i></div>
        <h1 class="h3">Choose a new password</h1>
        <p class="text-secondary">Code verified for <?= e(masked_email($challenge['email'])) ?>. Use 12–72 characters.</p>
        <form method="post" class="vstack gap-3">
            <?= csrf_field() ?><input type="hidden" name="action" value="reset_password">
            <label class="form-label">New password<input class="form-control" type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
            <label class="form-label">Confirm new password<input class="form-control" type="password" name="confirm_password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
            <button class="btn btn-primary">Update password</button>
        </form>
    </div></div>
    <?php
}

function page_dashboard(array $user): array
{
    $pdo = pdo();
    if ($user['account_type'] === 'admin' || is_junior_admin($user)) {
        $stats = [
            'Registered voters' => (int) $pdo->query('SELECT COUNT(*) FROM voters')->fetchColumn(),
            'Active voters' => (int) $pdo->query("SELECT COUNT(*) FROM voters WHERE status = 'active'")->fetchColumn(),
            'Unverified accounts' => (int) $pdo->query("SELECT COUNT(*) FROM voters WHERE status = 'unverified'")->fetchColumn(),
            'Junior Admins' => (int) $pdo->query('SELECT COUNT(*) FROM voters WHERE is_junior_admin = 1')->fetchColumn(),
            'Candidates' => (int) $pdo->query('SELECT COUNT(*) FROM candidates')->fetchColumn(),
            'Elections' => (int) $pdo->query('SELECT COUNT(*) FROM elections')->fetchColumn(),
            'Active elections' => (int) $pdo->query("SELECT COUNT(*) FROM elections WHERE status IN ('scheduled','active') AND starts_at <= UTC_TIMESTAMP() AND ends_at > UTC_TIMESTAMP()")->fetchColumn(),
            'Votes cast' => (int) $pdo->query('SELECT COUNT(*) FROM votes')->fetchColumn(),
        ];
        ?>
        <section class="page-heading"><div><p class="eyebrow"><?= is_main_admin() ? 'Main Admin' : 'Junior Admin' ?> workspace</p><h1>System overview</h1><p class="text-secondary">A live view of registrations, elections, and participation.</p></div></section>
        <div class="stat-grid"><?php foreach ($stats as $label => $value): ?><article class="stat-card"><span><?= e($label) ?></span><strong><?= number_format($value) ?></strong></article><?php endforeach ?></div>
        <div class="row g-4 mt-1">
            <div class="col-xl-7"><section class="card section-card h-100"><div class="section-title"><div><h2 class="h5">Participation snapshot</h2><p class="text-secondary small mb-0">Active and unverified accounts, and total votes</p></div></div><div class="chart-wrap"><canvas id="overviewChart" aria-label="System statistics chart"></canvas></div></section></div>
            <div class="col-xl-5"><section class="card section-card h-100"><div class="section-title"><h2 class="h5">Recent activity</h2><a href="<?= e(page_url('activity')) ?>">View all</a></div><?php render_recent_activity() ?></section></div>
        </div>
        <?php
        $chart = ['labels' => ['Active voters', 'Unverified accounts', 'Votes cast'], 'values' => [$stats['Active voters'], $stats['Unverified accounts'], $stats['Votes cast']]];
        return [$chart, 'System overview'];
    }
    ?>
    <section class="page-heading"><div><p class="eyebrow">Voter workspace</p><h1>Hello, <?= e($user['full_name']) ?></h1><p class="text-secondary">Your upcoming elections and voting activity are summarized here.</p></div></section>
    <?php render_voter_election_cards($user, 3) ?>
    <div class="row g-4 mt-1">
        <div class="col-xl-8"><section class="card section-card"><div class="section-title"><h2 class="h5">Recent voting history</h2><a href="<?= e(page_url('history')) ?>">View history</a></div><?php render_voter_history($user, 5) ?></section></div>
        <div class="col-xl-4"><section class="card section-card"><h2 class="h5">Your account</h2><p class="text-secondary">Account status</p><?= badge(normalize_voter_status((string) ($user['status'] ?? 'active'))) ?><p class="text-secondary mt-4">Junior Admin permissions</p><?= !empty($user['is_junior_admin']) ? badge('active') . ' <span>Enabled</span>' : '<span>Not assigned</span>' ?></section></div>
    </div>
    <?php
    return [null, 'Voter dashboard'];
}

function render_voter_election_cards(array $user, int $limit = 0): void
{
    $stmt = pdo()->query('SELECT * FROM elections ORDER BY starts_at DESC');
    $elections = $stmt->fetchAll();
    if ($limit > 0) {
        $elections = array_slice($elections, 0, $limit);
    }
    if (!$elections) {
        echo '<div class="empty-state">No elections have been scheduled yet.</div>';
        return;
    }
    echo '<div class="election-grid">';
    foreach ($elections as $election) {
        $status = effective_election_status($election);
        $voteStmt = pdo()->prepare('SELECT 1 FROM votes WHERE voter_id = ? AND election_id = ?');
        $voteStmt->execute([(int) $user['id'], (int) $election['id']]);
        $voted = (bool) $voteStmt->fetchColumn();
        echo '<article class="card election-card"><div class="d-flex justify-content-between gap-2">' . badge($status) . ($voted ? '<span class="badge text-bg-primary">Voted</span>' : '') . '</div>';
        echo '<h2 class="h5 mt-3">' . e($election['name']) . '</h2><p class="text-secondary">' . e(mb_strimwidth($election['description'], 0, 130, '…')) . '</p>';
        echo '<div class="small text-secondary mb-3"><i class="bi bi-calendar3"></i> ' . e(local_datetime($election['starts_at'])) . '</div>';
        echo '<a class="btn btn-outline-primary btn-sm" href="' . e(page_url('election', ['id' => $election['id']])) . '">View election</a></article>';
    }
    echo '</div>';
}

function page_elections(array $user): void
{
    ?>
    <section class="page-heading"><div><p class="eyebrow">Election calendar</p><h1>Elections</h1><p class="text-secondary">Review schedules, status, and participation.</p></div><?php if (is_main_admin()): ?><a class="btn btn-primary" href="<?= e(page_url('election-form')) ?>"><i class="bi bi-plus-lg"></i> New election</a><?php endif ?></section>
    <?php if ($user['account_type'] === 'voter'): render_voter_election_cards($user); else: render_election_table(true); endif ?>
    <?php
}

function render_election_table(bool $manage): void
{
    $pagination = pagination((int) pdo()->query('SELECT COUNT(*) FROM elections')->fetchColumn());
    $stmt = pdo()->prepare('SELECT e.*,
        (SELECT COUNT(*) FROM candidates c WHERE c.election_id = e.id) AS candidate_count,
        (SELECT COUNT(*) FROM votes v WHERE v.election_id = e.id) AS vote_count
        FROM elections e ORDER BY e.starts_at DESC LIMIT ? OFFSET ?');
    $stmt->bindValue(1, $pagination['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(2, $pagination['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    ?>
    <div class="table-responsive card section-card p-0"><table class="table align-middle mb-0"><thead><tr><th>Election</th><th>Schedule</th><th>Status</th><th>Candidates</th><th>Votes</th><th></th></tr></thead><tbody>
    <?php if (!$rows): ?><tr><td colspan="6" class="text-center p-4 text-secondary">No elections available.</td></tr><?php endif ?>
    <?php foreach ($rows as $row): $status = effective_election_status($row); ?>
        <tr><td><strong><?= e($row['name']) ?></strong><div class="small text-secondary"><?= e(mb_strimwidth($row['description'], 0, 90, '…')) ?></div></td><td class="small"><?= e(local_datetime($row['starts_at'])) ?><br>to <?= e(local_datetime($row['ends_at'])) ?></td><td><?= badge($status) ?></td><td><?= (int) $row['candidate_count'] ?></td><td><?= (int) $row['vote_count'] ?></td><td><div class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="<?= e(page_url('election', ['id' => $row['id']])) ?>">Open</a><?php if ($manage && $status !== 'closed'): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(page_url('election-form', ['id' => $row['id']])) ?>">Edit</a><?php endif ?></div>
        <?php if ($manage && $status !== 'closed'): ?><form method="post" class="mt-2" onsubmit="return confirm('Close this election now? Results will become visible to voters.');"><?= csrf_field() ?><input type="hidden" name="action" value="close_election"><input type="hidden" name="election_id" value="<?= (int) $row['id'] ?>"><button class="btn btn-sm btn-outline-danger">Close election</button></form><?php endif ?></td></tr>
    <?php endforeach ?>
    </tbody></table></div>
    <?php render_pagination('elections', $pagination); ?>
    <?php
}

function page_election(array $user): void
{
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    $stmt = pdo()->prepare('SELECT * FROM elections WHERE id = ?');
    $stmt->execute([$id ?: 0]);
    $election = $stmt->fetch();
    if (!$election) {
        echo '<div class="alert alert-warning">Election not found.</div>';
        return;
    }
    $status = effective_election_status($election);
    $voter = $user['account_type'] === 'voter';
    if ($voter && $status === 'closed') {
        ?>
        <section class="page-heading"><div><p class="eyebrow">Election closed</p><h1><?= e($election['name']) ?></h1><p class="text-secondary">Results are now published.</p></div><?= badge($status) ?></section>
        <?php render_results($election, false); return;
    }
    $stmt = pdo()->prepare('SELECT * FROM candidates WHERE election_id = ? ORDER BY full_name');
    $stmt->execute([$id]);
    $candidates = $stmt->fetchAll();
    $voted = false;
    if ($voter) {
        $check = pdo()->prepare('SELECT candidate_id, cast_at FROM votes WHERE voter_id = ? AND election_id = ?');
        $check->execute([(int) $user['id'], (int) $id]);
        $voted = $check->fetch();
    }
    ?>
    <section class="page-heading"><div><p class="eyebrow">Election details</p><h1><?= e($election['name']) ?></h1><p class="text-secondary"><?= nl2br(e($election['description'])) ?></p><div class="small text-secondary"><?= e(local_datetime($election['starts_at'])) ?> – <?= e(local_datetime($election['ends_at'])) ?></div></div><?= badge($status) ?></section>
    <?php if ($voter && $voted): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> Your vote is recorded. You cannot change it.</div><?php elseif ($voter && $status === 'active'): ?><div class="alert alert-primary">Select one candidate and confirm your choice. Votes are linked to voters in this educational system and cannot be changed.</div><?php elseif ($status !== 'active'): ?><div class="alert alert-info">Voting is not currently open for this election.</div><?php endif ?>
    <?php if (!$candidates): ?><div class="empty-state">No candidates have been added to this election.</div><?php endif ?>
    <div class="candidate-grid">
    <?php foreach ($candidates as $candidate): ?>
        <article class="card candidate-card"><img src="<?= e($candidate['photo_path']) ?>" alt="Photograph of <?= e($candidate['full_name']) ?>"><div class="candidate-body"><h2 class="h5"><?= e($candidate['full_name']) ?></h2><div class="text-primary fw-semibold"><?= e($candidate['party']) ?></div><p class="text-secondary"><?= nl2br(e($candidate['biography'])) ?></p>
        <?php if ($voter && $status === 'active' && !$voted): ?><form method="post" onsubmit="return confirm('Confirm this vote? Your vote cannot be edited or deleted.');"><?= csrf_field() ?><input type="hidden" name="action" value="vote"><input type="hidden" name="election_id" value="<?= (int) $election['id'] ?>"><input type="hidden" name="candidate_id" value="<?= (int) $candidate['id'] ?>"><button class="btn btn-primary w-100">Vote for this candidate</button></form><?php endif ?>
        <?php if (is_main_admin()): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(page_url('candidate-form', ['id' => $candidate['id']])) ?>">Edit candidate</a><?php endif ?>
        </div></article>
    <?php endforeach ?>
    </div>
    <?php
}

function page_election_form(): void
{
    $election = ['id' => '', 'name' => '', 'description' => '', 'status' => 'draft', 'starts_at' => '', 'ends_at' => ''];
    if ($id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT)) {
        $stmt = pdo()->prepare('SELECT * FROM elections WHERE id = ?');
        $stmt->execute([$id]);
        $found = $stmt->fetch();
        if ($found) {
            $election = $found;
            $election['starts_at'] = (new DateTimeImmutable($found['starts_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d\TH:i');
            $election['ends_at'] = (new DateTimeImmutable($found['ends_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d\TH:i');
        }
    }
    ?>
    <section class="page-heading"><div><p class="eyebrow">Main Admin</p><h1><?= $election['id'] ? 'Edit election' : 'Create election' ?></h1></div></section>
    <div class="card section-card"><form method="post" class="vstack gap-3">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_election"><input type="hidden" name="election_id" value="<?= e($election['id']) ?>">
        <label class="form-label">Election name<input class="form-control" name="name" maxlength="180" value="<?= e($election['name']) ?>" required></label>
        <label class="form-label">Description<textarea class="form-control" name="description" rows="4"><?= e($election['description']) ?></textarea></label>
        <div class="row g-3"><div class="col-md-6"><label class="form-label">Starts at (<?= e(date_default_timezone_get()) ?>)<input class="form-control" type="datetime-local" name="starts_at" value="<?= e($election['starts_at']) ?>" required></label></div><div class="col-md-6"><label class="form-label">Ends at<input class="form-control" type="datetime-local" name="ends_at" value="<?= e($election['ends_at']) ?>" required></label></div></div>
        <label class="form-label">Initial status<select class="form-select" name="status"><?php foreach (['draft','scheduled','active'] as $state): ?><option value="<?= e($state) ?>" <?= $election['status'] === $state ? 'selected' : '' ?>><?= e(ucfirst($state)) ?></option><?php endforeach ?></select></label>
        <div><button class="btn btn-primary">Save election</button> <a class="btn btn-outline-secondary" href="<?= e(page_url('elections')) ?>">Cancel</a></div>
    </form></div>
    <?php
}

function page_candidates(array $user): void
{
    $search = trim((string) ($_GET['q'] ?? ''));
    $page = max(1, (int) ($_GET['p'] ?? 1));
    $where = '';
    $params = [];
    if ($search !== '') {
        $where = ' WHERE c.full_name LIKE ? OR c.party LIKE ? OR e.name LIKE ?';
        $params = ['%' . $search . '%', '%' . $search . '%', '%' . $search . '%'];
    }
    $count = pdo()->prepare('SELECT COUNT(*) FROM candidates c JOIN elections e ON e.id = c.election_id' . $where);
    $count->execute($params);
    $pagination = pagination((int) $count->fetchColumn());
    $stmt = pdo()->prepare('SELECT c.*, e.name AS election_name FROM candidates c JOIN elections e ON e.id = c.election_id' . $where . ' ORDER BY e.starts_at DESC, c.full_name LIMIT ? OFFSET ?');
    foreach ($params as $i => $param) {
        $stmt->bindValue($i + 1, $param);
    }
    $stmt->bindValue(count($params) + 1, $pagination['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $pagination['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    ?>
    <section class="page-heading"><div><p class="eyebrow">Candidate directory</p><h1>Candidates</h1><p class="text-secondary">Browse candidates by election.</p></div><?php if (is_main_admin()): ?><a class="btn btn-primary" href="<?= e(page_url('candidate-form')) ?>"><i class="bi bi-plus-lg"></i> Add candidate</a><?php endif ?></section>
    <form class="search-bar mb-3" method="get"><input type="hidden" name="page" value="candidates"><input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search candidates or elections"><button class="btn btn-outline-primary">Search</button></form>
    <?php if (!$rows): ?><div class="empty-state">No candidates matched your search.</div><?php endif ?>
    <div class="candidate-grid"><?php foreach ($rows as $candidate): ?><article class="card candidate-card"><img src="<?= e($candidate['photo_path']) ?>" alt="Photograph of <?= e($candidate['full_name']) ?>"><div class="candidate-body"><span class="eyebrow"><?= e($candidate['election_name']) ?></span><h2 class="h5"><?= e($candidate['full_name']) ?></h2><div class="text-primary fw-semibold"><?= e($candidate['party']) ?></div><p class="text-secondary"><?= nl2br(e($candidate['biography'])) ?></p><?php if (is_main_admin()): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(page_url('candidate-form', ['id' => $candidate['id']])) ?>">Edit</a><?php endif ?></div></article><?php endforeach ?></div>
    <?php render_pagination('candidates', $pagination, ['q' => $search]); ?>
    <?php
}

function page_candidate_form(): void
{
    $candidate = ['id' => '', 'election_id' => '', 'full_name' => '', 'party' => '', 'biography' => ''];
    if ($id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT)) {
        $stmt = pdo()->prepare('SELECT * FROM candidates WHERE id = ?');
        $stmt->execute([$id]);
        $candidate = $stmt->fetch() ?: $candidate;
    }
    $elections = pdo()->query("SELECT id, name FROM elections WHERE status <> 'closed' ORDER BY starts_at DESC")->fetchAll();
    ?>
    <section class="page-heading"><div><p class="eyebrow">Main Admin</p><h1><?= $candidate['id'] ? 'Edit candidate' : 'Add candidate' ?></h1></div></section>
    <div class="card section-card"><form method="post" enctype="multipart/form-data" class="vstack gap-3">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_candidate"><input type="hidden" name="candidate_id" value="<?= e($candidate['id']) ?>">
        <label class="form-label">Election<select class="form-select" name="election_id" <?= $candidate['id'] ? 'disabled' : '' ?> required><option value="">Select an election</option><?php foreach ($elections as $election): ?><option value="<?= (int) $election['id'] ?>" <?= (int) $candidate['election_id'] === (int) $election['id'] ? 'selected' : '' ?>><?= e($election['name']) ?></option><?php endforeach ?></select><?php if ($candidate['id']): ?><input type="hidden" name="election_id" value="<?= (int) $candidate['election_id'] ?>"><?php endif ?></label>
        <label class="form-label">Candidate name<input class="form-control" name="full_name" maxlength="160" value="<?= e($candidate['full_name']) ?>" required></label>
        <label class="form-label">Party or organization<input class="form-control" name="party" maxlength="160" value="<?= e($candidate['party']) ?>"></label>
        <label class="form-label">Biography<textarea class="form-control" name="biography" rows="4"><?= e($candidate['biography']) ?></textarea></label>
        <label class="form-label">Photograph (JPEG, PNG, WebP; maximum 3 MB)<input class="form-control" type="file" name="photo" accept="image/jpeg,image/png,image/webp" <?= $candidate['id'] ? '' : 'required' ?>></label>
        <?php if (!empty($candidate['photo_path'])): ?><img class="form-photo-preview" src="<?= e($candidate['photo_path']) ?>" alt="Current candidate photograph"><?php endif ?>
        <div><button class="btn btn-primary">Save candidate</button> <a class="btn btn-outline-secondary" href="<?= e(page_url('candidates')) ?>">Cancel</a></div>
    </form></div>
    <?php
}

function page_voters(array $user): void
{
    $isAdmin = is_main_admin();
    $search = trim((string) ($_GET['q'] ?? ''));
    $status = trim((string) ($_GET['status'] ?? ''));
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(full_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
        array_push($params, '%' . $search . '%', '%' . $search . '%', '%' . $search . '%');
    }
    if ($isAdmin && in_array($status, ['unverified','active','suspended','deactivated'], true)) {
        $where[] = 'status = ?';
        $params[] = normalize_voter_status($status);
    } elseif (!$isAdmin) {
        $where[] = "status = 'active'";
    }
    $clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $count = pdo()->prepare('SELECT COUNT(*) FROM voters' . $clause);
    $count->execute($params);
    $pagination = pagination((int) $count->fetchColumn());
    $stmt = pdo()->prepare('SELECT id, full_name, national_id_ciphertext, email, date_of_birth, gender, phone, status, is_junior_admin, created_at FROM voters' . $clause . ' ORDER BY created_at DESC LIMIT ? OFFSET ?');
    foreach ($params as $i => $param) {
        $stmt->bindValue($i + 1, $param);
    }
    $stmt->bindValue(count($params) + 1, $pagination['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $pagination['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    ?>
    <section class="page-heading"><div><p class="eyebrow"><?= $isAdmin ? 'Main Admin' : 'Read-only directory' ?></p><h1>Registered voters</h1><p class="text-secondary"><?= $isAdmin ? 'Manage active account status and Junior Admin access.' : 'Junior Admin access is read-only. Sensitive identifiers are masked.' ?></p></div></section>
    <form class="search-bar mb-3" method="get"><input type="hidden" name="page" value="voters"><input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search name, email or phone"><?php if ($isAdmin): ?><select name="status" class="form-select"><option value="">All statuses</option><?php foreach (['unverified','active','suspended','deactivated'] as $option): ?><option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option><?php endforeach ?></select><?php endif ?><button class="btn btn-outline-primary">Filter</button></form>
    <div class="table-responsive card section-card p-0"><table class="table align-middle mb-0"><thead><tr><th>Voter</th><th>National ID</th><th>Contact</th><th>Status</th><th>Junior Admin</th><?php if ($isAdmin): ?><th>Actions</th><?php endif ?></tr></thead><tbody>
    <?php if (!$rows): ?><tr><td colspan="<?= $isAdmin ? 6 : 5 ?>" class="text-center p-4 text-secondary">No voters found.</td></tr><?php endif ?>
    <?php foreach ($rows as $row): $nationalId = decrypt_value($row['national_id_ciphertext']); $statusValue = normalize_voter_status((string) ($row['status'] ?? '')); ?>
        <tr><td><strong><?= e($row['full_name']) ?></strong><div class="small text-secondary">ID #<?= (int) $row['id'] ?> · <?= e($row['date_of_birth']) ?></div></td><td><?= e(mask_national_id($nationalId)) ?></td><td><div><?= e($row['email']) ?></div><div class="small text-secondary"><?= e($row['phone']) ?></div></td><td><?= badge($statusValue) ?><div class="small text-secondary"><?= e(local_datetime($row['created_at'])) ?></div></td><td><?= $row['is_junior_admin'] ? badge('active') : '—' ?></td>
        <?php if ($isAdmin): ?><td><a class="btn btn-sm btn-outline-primary mb-1" href="<?= e(page_url('voter', ['id' => $row['id']])) ?>">Details</a>
            <form method="post" class="mt-1"><?= csrf_field() ?><input type="hidden" name="action" value="set_voter_status"><input type="hidden" name="voter_id" value="<?= (int) $row['id'] ?>"><select name="status" class="form-select form-select-sm mb-1"><option value="active" <?= $statusValue === 'active' ? 'selected' : '' ?>>Active</option><option value="unverified" <?= $statusValue === 'unverified' ? 'selected' : '' ?>>Unverified</option><option value="suspended" <?= $statusValue === 'suspended' ? 'selected' : '' ?>>Suspend</option><option value="deactivated" <?= $statusValue === 'deactivated' ? 'selected' : '' ?>>Deactivate</option></select><button class="btn btn-sm btn-outline-secondary">Update status</button></form>
            <?php if ($statusValue === 'active'): ?>
                <form method="post" class="mt-1" onsubmit="return confirm('Change this voter’s Junior Admin privileges?');"><?= csrf_field() ?><input type="hidden" name="voter_id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="action" value="<?= $row['is_junior_admin'] ? 'demote' : 'promote' ?>"><button class="btn btn-sm btn-outline-secondary"><?= $row['is_junior_admin'] ? 'Demote' : 'Promote' ?></button></form>
            <?php endif ?>
        </td><?php endif ?></tr>
    <?php endforeach ?>
    </tbody></table></div>
    <?php render_pagination('voters', $pagination, ['q' => $search, 'status' => $status]); ?>
    <?php
}

function mask_national_id(string $value): string
{
    $length = mb_strlen($value, 'UTF-8');
    return str_repeat('•', max(0, $length - 4)) . mb_substr($value, -4, null, 'UTF-8');
}

function page_voter_detail(): void
{
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    $stmt = pdo()->prepare('SELECT * FROM voters WHERE id = ?');
    $stmt->execute([$id ?: 0]);
    $voter = $stmt->fetch();
    if (!$voter) {
        echo '<div class="alert alert-warning">Voter record not found.</div>';
        return;
    }
    $profileChanges = pdo()->prepare('SELECT c.*, a.full_name AS changed_by_name FROM voter_profile_changes c JOIN admins a ON a.id = c.changed_by WHERE c.voter_id = ? ORDER BY c.created_at DESC');
    $profileChanges->execute([$id]);
    $statusValue = normalize_voter_status((string) $voter['status']);
    ?>
    <section class="page-heading"><div><p class="eyebrow">Main Admin · voter ID <?= (int) $id ?></p><h1><?= e($voter['full_name']) ?></h1></div><?= badge($statusValue) ?></section>
    <div class="row g-4"><div class="col-xl-5"><section class="card section-card"><div class="section-title"><h2 class="h5">Current profile</h2><a class="btn btn-sm btn-outline-primary" href="<?= e(page_url('voter', ['id' => $id, 'edit' => 1])) ?>">Edit profile</a></div>
    <?php if (isset($_GET['edit'])): ?>
        <form method="post" class="vstack gap-3">
            <?= csrf_field() ?><input type="hidden" name="action" value="admin_update_voter"><input type="hidden" name="voter_id" value="<?= (int) $voter['id'] ?>">
            <?php person_fields([
                'full_name' => $voter['full_name'],
                'national_id' => decrypt_value($voter['national_id_ciphertext']),
                'email' => $voter['email'],
                'date_of_birth' => $voter['date_of_birth'],
                'gender' => $voter['gender'],
                'phone' => $voter['phone'],
            ]) ?>
            <button class="btn btn-primary">Save voter information</button>
        </form>
    <?php else: ?><dl class="detail-list">
        <?php foreach (['Email address' => $voter['email'], 'National ID' => decrypt_value($voter['national_id_ciphertext']), 'Date of birth' => $voter['date_of_birth'], 'Gender' => $voter['gender'], 'Phone' => $voter['phone'], 'Registered' => local_datetime($voter['created_at'])] as $label => $value): ?><div><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div><?php endforeach ?>
    </dl><?php endif ?></section></div>
    <div class="col-xl-7"><section class="card section-card"><h2 class="h5">Account status</h2><dl class="detail-list mb-0">
        <div><dt>Current account status</dt><dd><?= e($statusValue) ?></dd></div>
        <div><dt>Email verification</dt><dd><?= !empty($voter['email_verified_at']) ? e(local_datetime($voter['email_verified_at'])) : 'Not verified' ?></dd></div>
        <div><dt>Junior Admin</dt><dd><?= !empty($voter['is_junior_admin']) ? 'Enabled' : 'Not assigned' ?></dd></div>
    </dl></section></div></div>
    <section class="card section-card mt-4"><h2 class="h5">Administrator profile change history</h2><div class="table-responsive"><table class="table"><thead><tr><th>Changed</th><th>Administrator</th><th>Email change</th><th>National ID change</th></tr></thead><tbody>
        <?php foreach ($profileChanges as $change): ?><tr><td><?= e(local_datetime($change['created_at'])) ?></td><td><?= e($change['changed_by_name']) ?></td><td><?= e($change['previous_email']) ?><br><span class="text-secondary">→ <?= e($change['new_email']) ?></span></td><td><?= e(decrypt_value($change['previous_national_id_ciphertext'])) ?><br><span class="text-secondary">→ <?= e(decrypt_value($change['new_national_id_ciphertext'])) ?></span></td></tr><?php endforeach ?>
        <?php if ($profileChanges->rowCount() === 0): ?><tr><td colspan="4" class="text-secondary">No administrator profile changes.</td></tr><?php endif ?>
    </tbody></table></div></section>
    <?php
}

function page_results(array $user): void
{
    $electionId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    $elections = pdo()->query('SELECT * FROM elections ORDER BY starts_at DESC')->fetchAll();
    if (!$electionId && $elections) {
        $electionId = (int) $elections[0]['id'];
    }
    ?>
    <section class="page-heading"><div><p class="eyebrow">Transparent tally</p><h1>Election results</h1><p class="text-secondary">Voters can view results after an election closes.</p></div></section>
    <?php if (!$elections): ?><div class="empty-state">No election results are available.</div><?php return; endif ?>
    <form method="get" class="search-bar mb-4"><input type="hidden" name="page" value="results"><select name="id" class="form-select" onchange="this.form.submit()"><?php foreach ($elections as $election): ?><option value="<?= (int) $election['id'] ?>" <?= $electionId === (int) $election['id'] ? 'selected' : '' ?>><?= e($election['name']) ?></option><?php endforeach ?></select><button class="btn btn-outline-primary">View</button></form>
    <?php foreach ($elections as $election): if ((int) $election['id'] === $electionId) { render_results($election, $user['account_type'] === 'voter'); break; } endforeach ?>
    <?php
}

function render_results(array $election, bool $voterView): void
{
    $status = effective_election_status($election);
    if ($voterView && $status !== 'closed') {
        echo '<div class="alert alert-info">Results are hidden from voters until this election closes.</div>';
        return;
    }
    $stmt = pdo()->prepare('SELECT c.id, c.full_name, c.party, c.photo_path, COUNT(v.id) AS votes FROM candidates c LEFT JOIN votes v ON v.candidate_id = c.id AND v.election_id = c.election_id WHERE c.election_id = ? GROUP BY c.id ORDER BY votes DESC, c.full_name');
    $stmt->execute([(int) $election['id']]);
    $rows = $stmt->fetchAll();
    $total = array_sum(array_map(static fn(array $row): int => (int) $row['votes'], $rows));
    $top = $total > 0 && $rows ? (int) $rows[0]['votes'] : 0;
    $winners = $total > 0 ? array_filter($rows, static fn(array $row): bool => (int) $row['votes'] === $top) : [];
    ?>
    <section class="card section-card mb-4"><div class="section-title"><div><p class="eyebrow mb-1"><?= e($election['name']) ?></p><h2 class="h4 mb-0">Final results</h2></div><?= badge($status) ?></div><div class="results-summary"><div><span>Total votes cast</span><strong><?= number_format($total) ?></strong></div><div><span><?= $total === 0 ? 'Outcome' : (count($winners) > 1 ? 'Joint winners' : 'Winning candidate') ?></span><strong><?= $total === 0 ? 'No votes received' : e(implode(', ', array_column($winners, 'full_name'))) ?></strong></div></div></section>
    <?php if (!$rows): ?><div class="empty-state">No candidates were registered for this election.</div><?php else: ?>
        <div class="table-responsive card section-card p-0"><table class="table align-middle mb-0"><thead><tr><th>Candidate</th><th>Votes</th><th>Share</th><th>Result</th></tr></thead><tbody><?php foreach ($rows as $row): $percent = $total > 0 ? ((int) $row['votes'] * 100 / $total) : 0; ?>
            <tr><td><div class="result-candidate"><img src="<?= e($row['photo_path']) ?>" alt=""><div><strong><?= e($row['full_name']) ?></strong><div class="small text-secondary"><?= e($row['party']) ?></div></div></div></td><td><?= number_format((int) $row['votes']) ?></td><td><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1"><div class="progress-bar" style="width: <?= e((string) $percent) ?>%"></div></div><span><?= number_format($percent, 2) ?>%</span></div></td><td><?= $total > 0 && (int) $row['votes'] === $top ? '<span class="badge text-bg-primary">Winner' . (count($winners) > 1 ? ' · tied' : '') . '</span>' : '—' ?></td></tr>
        <?php endforeach ?></tbody></table></div>
    <?php endif ?>
    <?php
}

function page_voting_history(array $user): void
{
    if ($user['account_type'] !== 'voter') {
        redirect('results');
    }
    render_voter_history($user, 0);
}

function render_voter_history(array $user, int $limit): void
{
    $count = pdo()->prepare('SELECT COUNT(*) FROM votes WHERE voter_id = ?');
    $count->execute([(int) $user['id']]);
    $pagination = pagination((int) $count->fetchColumn());
    $sql = 'SELECT v.cast_at, e.id AS election_id, e.name AS election_name, c.full_name AS candidate_name, c.photo_path
            FROM votes v JOIN elections e ON e.id = v.election_id JOIN candidates c ON c.id = v.candidate_id
            WHERE v.voter_id = ? ORDER BY v.cast_at DESC';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    } else {
        $sql .= ' LIMIT ' . $pagination['per_page'] . ' OFFSET ' . $pagination['offset'];
    }
    $stmt = pdo()->prepare($sql);
    $stmt->execute([(int) $user['id']]);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        echo '<div class="empty-state">You have not voted in an election yet.</div>';
        return;
    }
    ?>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Election</th><th>Candidate</th><th>Cast at</th><th>Status</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><a href="<?= e(page_url('election', ['id' => $row['election_id']])) ?>"><?= e($row['election_name']) ?></a></td><td><div class="result-candidate"><img src="<?= e($row['photo_path']) ?>" alt=""><strong><?= e($row['candidate_name']) ?></strong></div></td><td><?= e(local_datetime($row['cast_at'])) ?></td><td><?= badge('voted') ?></td></tr><?php endforeach ?></tbody></table></div>
    <?php if ($limit === 0) render_pagination('history', $pagination); ?>
    <?php
}

function render_recent_activity(): void
{
    $rows = pdo()->query('SELECT event_type, target_type, target_id, created_at FROM activity_logs ORDER BY created_at DESC LIMIT 8')->fetchAll();
    if (!$rows) {
        echo '<div class="empty-state py-4">No activity recorded yet.</div>';
        return;
    }
    echo '<ul class="activity-list">';
    foreach ($rows as $row) {
        echo '<li><span class="activity-icon"><i class="bi bi-activity"></i></span><div><strong>' . e(ucwords(str_replace('_', ' ', $row['event_type']))) . '</strong><div class="small text-secondary">' . e(local_datetime($row['created_at'])) . '</div></div></li>';
    }
    echo '</ul>';
}

function page_activity(array $user): void
{
    if ($user['account_type'] === 'voter' && !is_junior_admin($user)) {
        http_response_code(403);
        throw new RuntimeException('Activity logs are available to administrators only.');
    }
    $total = (int) pdo()->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn();
    $pagination = pagination($total);
    $stmt = pdo()->prepare('SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT ? OFFSET ?');
    $stmt->bindValue(1, $pagination['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(2, $pagination['offset'], PDO::PARAM_INT);
    $stmt->execute();
    ?>
    <section class="page-heading"><div><p class="eyebrow">Read-only audit trail</p><h1>Activity logs</h1><p class="text-secondary">Security-relevant events; secrets and credentials are excluded.</p></div></section>
    <div class="table-responsive card section-card p-0"><table class="table align-middle mb-0"><thead><tr><th>Event</th><th>Actor</th><th>Target</th><th>Details</th><th>Time</th></tr></thead><tbody><?php foreach ($stmt as $row): ?><tr><td><?= e(ucwords(str_replace('_', ' ', $row['event_type']))) ?></td><td><?= e(ucfirst($row['actor_type'])) ?><?= $row['actor_id'] ? ' #' . (int) $row['actor_id'] : '' ?></td><td><?= e($row['target_type'] ?? '—') ?><?= $row['target_id'] ? ' #' . (int) $row['target_id'] : '' ?></td><td class="small"><?= e($row['details'] ?? '—') ?></td><td><?= e(local_datetime($row['created_at'])) ?></td></tr><?php endforeach ?></tbody></table></div>
    <?php render_pagination('activity', $pagination); ?>
    <?php
}

function page_votes(): void
{
    $count = (int) pdo()->query('SELECT COUNT(*) FROM votes')->fetchColumn();
    $pagination = pagination($count);
    $stmt = pdo()->prepare('SELECT v.id, v.cast_at, voter.id AS voter_id, voter.full_name AS voter_name, e.name AS election_name, c.full_name AS candidate_name FROM votes v JOIN voters voter ON voter.id = v.voter_id JOIN elections e ON e.id = v.election_id JOIN candidates c ON c.id = v.candidate_id ORDER BY v.cast_at DESC LIMIT ? OFFSET ?');
    $stmt->bindValue(1, $pagination['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(2, $pagination['offset'], PDO::PARAM_INT);
    $stmt->execute();
    ?>
    <section class="page-heading"><div><p class="eyebrow">Main Admin only</p><h1>Vote records</h1><p class="text-secondary">Linked vote records are immutable in ordinary application workflows.</p></div></section>
    <div class="table-responsive card section-card p-0"><table class="table mb-0"><thead><tr><th>Voter</th><th>Election</th><th>Candidate</th><th>Cast at</th></tr></thead><tbody><?php foreach ($stmt as $row): ?><tr><td><?= e($row['voter_name']) ?> <span class="text-secondary">#<?= (int) $row['voter_id'] ?></span></td><td><?= e($row['election_name']) ?></td><td><?= e($row['candidate_name']) ?></td><td><?= e(local_datetime($row['cast_at'])) ?></td></tr><?php endforeach ?></tbody></table></div>
    <?php render_pagination('votes', $pagination); ?>
    <?php
}

function page_profile(array $user): void
{
    ?>
    <section class="page-heading"><div><p class="eyebrow">Account settings</p><h1>Profile and security</h1></div></section>
    <div class="row g-4"><div class="col-xl-6"><section class="card section-card"><h2 class="h5">Profile information</h2><form method="post" class="vstack gap-3">
        <?= csrf_field() ?><input type="hidden" name="action" value="update_profile">
        <label class="form-label">Full name<input class="form-control" name="full_name" maxlength="160" value="<?= e($user['full_name']) ?>" required></label>
        <?php if ($user['account_type'] === 'admin'): ?><label class="form-label">Email address<input class="form-control" type="email" name="email" value="<?= e($user['email']) ?>" required></label><?php else: ?><label class="form-label">Email address<input class="form-control" value="<?= e($user['email']) ?>" disabled></label><label class="form-label">Phone<input class="form-control" name="phone" value="<?= e($user['phone']) ?>" required></label><?php endif ?>
        <button class="btn btn-primary">Save profile</button>
    </form></section></div><div class="col-xl-6"><section class="card section-card"><h2 class="h5">Change password</h2><form method="post" class="vstack gap-3">
        <?= csrf_field() ?><input type="hidden" name="action" value="change_password">
        <label class="form-label">Current password<input class="form-control" type="password" name="current_password" required></label>
        <label class="form-label">New password<input class="form-control" type="password" name="password" minlength="12" maxlength="72" required></label>
        <label class="form-label">Confirm new password<input class="form-control" type="password" name="confirm_password" minlength="12" maxlength="72" required></label>
        <button class="btn btn-primary">Update password</button>
    </form></section></div></div>
    <?php
}

function render_pagination(string $page, array $pagination, array $extra = []): void
{
    echo '<nav class="mt-3 d-flex align-items-center justify-content-between flex-wrap gap-2" aria-label="Pagination"><span class="small text-secondary">' . (int) $pagination['total'] . ' records · page ' . (int) $pagination['page'] . ' of ' . (int) $pagination['pages'] . '</span>';
    echo '<form method="get" class="d-flex align-items-center gap-2 small"><input type="hidden" name="page" value="' . e($page) . '">';
    foreach ($extra as $key => $value) {
        if ($value !== '') {
            echo '<input type="hidden" name="' . e((string) $key) . '" value="' . e((string) $value) . '">';
        }
    }
    echo '<label for="recordsPerPage">Per page</label><select class="form-select form-select-sm" id="recordsPerPage" name="per_page" onchange="this.form.submit()">';
    foreach ([10, 25, 50] as $size) {
        echo '<option value="' . $size . '"' . ($size === $pagination['per_page'] ? ' selected' : '') . '>' . $size . '</option>';
    }
    echo '</select></form>';
    if ($pagination['pages'] <= 1) {
        echo '</nav>';
        return;
    }
    $numberParams = ['per_page' => $pagination['per_page']] + $extra;
    $previous = max(1, $pagination['page'] - 1);
    $next = min($pagination['pages'], $pagination['page'] + 1);
    echo '<ul class="pagination pagination-sm mb-0">';
    echo '<li class="page-item ' . ($pagination['page'] === 1 ? 'disabled' : '') . '"><a class="page-link" href="' . e(page_url($page, ['p' => $previous] + $numberParams)) . '" aria-label="Previous page">Previous</a></li>';
    $visiblePages = [1];
    for ($number = max(2, $pagination['page'] - 2); $number <= min($pagination['pages'] - 1, $pagination['page'] + 2); $number++) {
        $visiblePages[] = $number;
    }
    if ($pagination['pages'] > 1) {
        $visiblePages[] = $pagination['pages'];
    }
    $lastPage = 0;
    foreach (array_unique($visiblePages) as $number) {
        if ($lastPage > 0 && $number > $lastPage + 1) {
            echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        $params = ['p' => $number] + $numberParams;
        echo '<li class="page-item ' . ($number === $pagination['page'] ? 'active' : '') . '"><a class="page-link" href="' . e(page_url($page, $params)) . '">' . $number . '</a></li>';
        $lastPage = $number;
    }
    echo '<li class="page-item ' . ($pagination['page'] === $pagination['pages'] ? 'disabled' : '') . '"><a class="page-link" href="' . e(page_url($page, ['p' => $next] + $numberParams)) . '" aria-label="Next page">Next</a></li>';
    echo '</ul></nav>';
}
