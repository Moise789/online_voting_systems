<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= e($title) ?> · <?= e(app_config('APP_NAME', 'Mobile and Online Voting System')) ?></title>
    <script>
        (() => {
            const theme = localStorage.getItem('voting-system-theme') || 'light';
            document.documentElement.dataset.bsTheme = theme;
            document.documentElement.dataset.theme = theme;
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <?php if ($user): ?>
    <aside class="sidebar" id="appSidebar">
        <a class="brand" href="<?= e(page_url('dashboard')) ?>"><span class="brand-mark"><i class="bi bi-check2-square"></i></span><span><strong>Vote<span>Blue</span></strong><small>Voting platform</small></span></a>
        <div class="sidebar-label">Workspace</div>
        <nav class="side-nav">
            <a href="<?= e(page_url('dashboard')) ?>"><i class="bi bi-grid-1x2"></i> Dashboard</a>
            <a href="<?= e(page_url('elections')) ?>"><i class="bi bi-calendar2-check"></i> Elections</a>
            <a href="<?= e(page_url('candidates')) ?>"><i class="bi bi-people"></i> Candidates</a>
            <?php if ($user['account_type'] === 'admin' || is_junior_admin($user)): ?><a href="<?= e(page_url('voters')) ?>"><i class="bi bi-person-vcard"></i> Registered voters</a><?php endif ?>
            <?php if ($user['account_type'] === 'voter'): ?><a href="<?= e(page_url('history')) ?>"><i class="bi bi-clock-history"></i> Voting history</a><?php endif ?>
            <a href="<?= e(page_url('results')) ?>"><i class="bi bi-bar-chart-line"></i> Results</a>
            <?php if ($user['account_type'] === 'admin' || is_junior_admin($user)): ?><div class="sidebar-label mt-4">Administration</div><a href="<?= e(page_url('activity')) ?>"><i class="bi bi-activity"></i> Activity logs</a><?php endif ?>
            <?php if ($user['account_type'] === 'admin'): ?><a href="<?= e(page_url('votes')) ?>"><i class="bi bi-check2-all"></i> Vote records</a><?php endif ?>
        </nav>
        <div class="sidebar-bottom"><a href="<?= e(page_url('profile')) ?>"><i class="bi bi-person-gear"></i> Profile & security</a><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button class="sidebar-logout"><i class="bi bi-box-arrow-left"></i> Sign out</button></form></div>
    </aside>
    <?php endif ?>
    <div class="main-shell <?= $user ? '' : 'main-shell-public' ?>">
        <header class="topbar">
            <?php if ($user): ?><button class="btn btn-link sidebar-toggle" id="sidebarToggle" aria-label="Toggle navigation"><i class="bi bi-list"></i></button><div class="topbar-context"><span class="eyebrow"><?= $user['account_type'] === 'admin' ? 'Main Admin' : (is_junior_admin($user) ? 'Junior Admin · Voter' : 'Voter account') ?></span><strong><?= e($title) ?></strong></div><?php else: ?><a class="brand brand-public" href="<?= e(page_url('login')) ?>"><span class="brand-mark"><i class="bi bi-check2-square"></i></span><strong>Vote<span>Blue</span></strong></a><?php endif ?>
            <div class="topbar-actions"><button type="button" class="theme-toggle" id="themeToggle" aria-label="Switch color theme"><i class="bi bi-moon-stars"></i><span>Dark mode</span></button><?php if ($user): ?><div class="profile-chip"><span class="avatar"><?= e(mb_strtoupper(mb_substr($user['full_name'], 0, 1, 'UTF-8'), 'UTF-8')) ?></span><span class="profile-name"><?= e($user['full_name']) ?></span></div><form method="post" class="topbar-logout-form"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button type="submit" class="topbar-logout"><i class="bi bi-box-arrow-right"></i><span>Sign out</span></button></form><?php endif ?></div>
        </header>
        <main class="content">
            <?php foreach ($flashes as $notice): ?><div class="alert alert-<?= e($notice['type']) ?> alert-dismissible fade show app-alert" role="alert"><i class="bi <?= $notice['type'] === 'success' ? 'bi-check-circle' : ($notice['type'] === 'warning' ? 'bi-exclamation-triangle' : 'bi-info-circle') ?>"></i> <?= e($notice['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endforeach ?>
            <?= $content ?>
        </main>
        <footer class="app-footer"><span><?= e(app_config('APP_NAME', 'Mobile and Online Voting System')) ?></span><span>Developed by Alain Junior NEZERWA, Sage Sabana and Moise Kwizera</span></footer>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>window.votingChartData = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="assets/js/app.js"></script>
</body>
</html>
