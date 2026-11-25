(() => {
    const root = document.documentElement;
    const themeButton = document.getElementById('themeToggle');
    const applyTheme = (theme) => {
        const selected = theme === 'dark' ? 'dark' : 'light';
        root.dataset.theme = selected;
        root.dataset.bsTheme = selected;
        localStorage.setItem('voting-system-theme', selected);
        if (themeButton) {
            themeButton.innerHTML = selected === 'dark'
                ? '<i class="bi bi-sun"></i><span>Light mode</span>'
                : '<i class="bi bi-moon-stars"></i><span>Dark mode</span>';
            themeButton.setAttribute('aria-label', `Switch to ${selected === 'dark' ? 'light' : 'dark'} mode`);
        }
        if (window.votingChart) {
            const color = getComputedStyle(root).getPropertyValue('--text-muted').trim();
            window.votingChart.options.plugins.legend.labels.color = color;
            window.votingChart.options.scales.x.ticks.color = color;
            window.votingChart.options.scales.y.ticks.color = color;
            window.votingChart.options.scales.x.grid.color = getComputedStyle(root).getPropertyValue('--border').trim();
            window.votingChart.options.scales.y.grid.color = getComputedStyle(root).getPropertyValue('--border').trim();
            window.votingChart.update();
        }
    };
    applyTheme(root.dataset.theme || 'light');
    themeButton?.addEventListener('click', () => applyTheme(root.dataset.theme === 'dark' ? 'light' : 'dark'));

    document.getElementById('sidebarToggle')?.addEventListener('click', () => {
        document.getElementById('appSidebar')?.classList.toggle('sidebar-open');
    });

    document.querySelectorAll('form[data-validate]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const password = form.querySelector('[name="password"]');
            const confirm = form.querySelector('[name="confirm_password"]');
            if (password && confirm && password.value !== confirm.value) {
                event.preventDefault();
                window.alert('The passwords do not match.');
            }
        });
        document.querySelectorAll('[data-password-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.passwordToggle);
                if (!input) return;
                const reveal = input.type === 'password';
                input.type = reveal ? 'text' : 'password';
                button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
                button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
                button.innerHTML = reveal ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
            });
        });

        document.getElementById('initialAdminSetupForm')?.addEventListener('submit', (event) => {
            const form = event.currentTarget;
            const password = form.querySelector('[name="password"]');
            const confirmPassword = form.querySelector('[name="confirm_password"]');
            if (password.value !== confirmPassword.value) {
                event.preventDefault();
                window.alert('The passwords do not match.');
                confirmPassword.focus();
                return;
            }
            if (password.value.length < 12 || password.value.length > 72) {
                event.preventDefault();
                window.alert('Use a password of 12 to 72 characters.');
                password.focus();
                return;
            }
            if (!window.confirm('Create the single initial Main Admin account now? This setup page will be disabled afterward.')) {
                event.preventDefault();
            }
        });
    });

    const chartCanvas = document.getElementById('overviewChart');
    if (chartCanvas && window.Chart && window.votingChartData) {
        const style = getComputedStyle(root);
        const muted = style.getPropertyValue('--text-muted').trim();
        const border = style.getPropertyValue('--border').trim();
        window.votingChart = new Chart(chartCanvas, {
            type: 'bar',
            data: {
                labels: window.votingChartData.labels,
                datasets: [{
                    label: 'Count',
                    data: window.votingChartData.values,
                    backgroundColor: ['#247fd0', '#77b9f4', '#0c4e92'],
                    borderRadius: 7,
                    maxBarThickness: 64
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false, labels: { color: muted } } },
                scales: {
                    x: { ticks: { color: muted }, grid: { display: false } },
                    y: { beginAtZero: true, ticks: { color: muted, precision: 0 }, grid: { color: border } }
                }
            }
        });
    }
})();
