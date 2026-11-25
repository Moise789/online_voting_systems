<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        handle_post();
    } catch (Throwable $exception) {
        $status = $exception instanceof RuntimeException && !$exception instanceof PDOException ? 400 : 500;
        http_response_code($status);
        $initialSetupAction = ($_POST['action'] ?? '') === 'create_initial_admin';
        if ($initialSetupAction && ($exception instanceof PDOException || !$exception instanceof RuntimeException)) {
            error_log('Initial Main Admin setup failed due to an internal error.');
            flash('error', 'The account could not be created because of a server or database error. No credentials were recorded.');
        } elseif ($exception instanceof PDOException || !$exception instanceof RuntimeException) {
            error_log('Application request failed: ' . $exception->getMessage());
            flash('error', 'The request could not be completed due to a server or database error. Please try again later.');
        } else {
            flash('error', $exception->getMessage());
        }
        $page = is_string($_GET['page'] ?? null) ? $_GET['page'] : post_redirect_page();
        redirect($page, request_redirect_params($page));
    }
}

try {
    render_page();
} catch (Throwable $exception) {
    if ($exception instanceof PDOException || !$exception instanceof RuntimeException) {
        error_log('Page rendering failed: ' . $exception->getMessage());
    }
    http_response_code(500);
    $message = $exception instanceof PDOException || !$exception instanceof RuntimeException
        ? 'A server or database error prevented this page from loading.'
        : $exception->getMessage();
    render_layout('System notice', '<div class="alert alert-danger"><h1 class="h5">Unable to load this page</h1><p class="mb-0">' . e($message) . '</p></div>');
}

function post_redirect_page(): string
{
    $action = $_POST['action'] ?? '';
    return match ($action) {
        'register' => 'register',
        'create_initial_admin' => 'setup-admin',
        'request_reset' => 'forgot-password',
        'reset_password' => 'new-password',
        'verify_otp', 'resend_otp' => 'verify-otp',
        'change_password', 'update_profile' => 'profile',
        'admin_update_voter' => 'voter',
        'save_election', 'close_election' => 'elections',
        'save_candidate' => 'candidates',
        'promote', 'demote' => 'voters',
        'vote' => 'election',
        default => 'login',
    };
}

function request_redirect_params(string $page): array
{
    $params = [];
    if ($page === 'reset' && is_string($_POST['token'] ?? null)) {
        $params['token'] = $_POST['token'];
    }
    if ($page === 'election' && filter_var($_POST['election_id'] ?? null, FILTER_VALIDATE_INT)) {
        $params['id'] = (int) $_POST['election_id'];
    }
    if ($page === 'voter' && filter_var($_POST['voter_id'] ?? null, FILTER_VALIDATE_INT)) {
        $params['id'] = (int) $_POST['voter_id'];
    }
    if ($page === 'elections' && in_array($_POST['action'] ?? '', ['save_election','close_election'], true)) {
        $params['status'] = 'all';
    }
    return $params;
}
