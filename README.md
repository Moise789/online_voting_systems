# Mobile and Online Voting System

A responsive educational voting application built with PHP 8+, MySQL 8+, PDO, Bootstrap 5, JavaScript, and Chart.js for local XAMPP development.

> **Important:** This is an educational application, not a certified government election platform. A vote is linked to the voter's account and selected candidate. It does **not** implement an anonymous secret ballot and is not suitable for legally binding or high-stakes elections.

## Requirements

- XAMPP with PHP 8+ and MySQL 8+ (or the MariaDB version bundled with XAMPP; the schema uses InnoDB, JSON, and database checks).
- PHP extensions: PDO MySQL, OpenSSL, Fileinfo, and mbstring.
- A modern browser with JavaScript enabled.
- SMTP service for password reset and registration email delivery.

## Local XAMPP setup

1. Place this project at `C:\xampp\htdocs\online_voting_system`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. In phpMyAdmin, import `database/schema.sql`. It creates the `online_voting_system` database and its tables.
4. Create `C:\xampp\private` if it does not exist. Copy `.env.example` to `C:\xampp\private\online_voting_system.env` and edit the copy in Notepad. This private file is outside the public web folder. The app checks it first. During local development only, it can fall back to the project `.env` if the private file does not exist. Production mode never falls back to the project `.env`.
5. Set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` in the private file. The shown local defaults match typical XAMPP MySQL; an empty password may be used only if your local MySQL account actually has no password. Never use an empty database password on hosting.
6. Generate independent secret keys from PowerShell:

   ```powershell
   & 'C:\xampp\php\php.exe' -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
   ```

   Run this command **twice** and put the first result in `APP_ENCRYPTION_KEY` and the second in `APP_HMAC_KEY` in the private file. Never commit these keys. Do not replace existing keys if encrypted National IDs already exist; losing/changing them makes encrypted data unreadable and identifier hashes unmatchable.
7. Set `APP_BASE_URL` to the URL where the project is served (the default is `http://localhost/online_voting_system`) and `APP_TIMEZONE` to the desired IANA timezone. Election times are stored in UTC and displayed in this configured timezone.
8. Create the initial Main Admin using the local-only, one-time setup page at `http://localhost/online_voting_system/index.php?page=setup-admin`. Enter the account details, confirm the matching password and explicit confirmation checkbox, then accept the confirmation dialog. The form is restricted to local loopback requests, protected with CSRF, validates again in PHP, and uses a transaction plus the database singleton constraint to ensure exactly one account. It is disabled after successful setup.

   Alternatively, create the initial Main Admin from a PowerShell session. The password is supplied through temporary environment variables and is not written into the project or SQL:

   ```powershell
   $env:ADMIN_INITIAL_NAME = 'System Administrator'
   $env:ADMIN_INITIAL_EMAIL = 'admin@example.test'
   $env:ADMIN_INITIAL_PASSWORD = Read-Host 'Choose a unique password of at least 12 characters' -AsSecureString |
       ForEach-Object { [System.Net.NetworkCredential]::new('', $_).Password }
   & 'C:\xampp\php\php.exe' 'C:\xampp\htdocs\online_voting_system\bin\create_admin.php'
   Remove-Item Env:\ADMIN_INITIAL_NAME, Env:\ADMIN_INITIAL_EMAIL, Env:\ADMIN_INITIAL_PASSWORD
   ```

   The setup script is CLI-only, checks the single-admin rule, rejects an email already used by a voter, and hashes the password. There is no public Main Admin registration page.
9. Configure Gmail (below), then open `http://localhost/online_voting_system/`.

If PHP says a required database table is missing, import the schema before retrying. Database exceptions are logged to the PHP/Apache error log; user-facing pages do not display raw SQL errors.

For a fresh installation, import `database/schema.sql` once. It already contains the current OTP, rate-limit, and voter-status schema; do not apply upgrade migrations to a fresh database.

For an existing installation, take and verify a full backup first. Apply only upgrades that are missing, in order: `database/upgrade_session_revocation.sql` if the `session_version` fields are missing; then `database/migration_20261105_email_otp_and_rate_limits.sql` if the email-verification column or OTP/rate-limit tables are missing; then `database/migration_20261106_status_model_cleanup.sql` only if the voter status enum still contains legacy review states. Do not re-import `database/schema.sql` into an existing database.

The status cleanup migration preserves voter IDs, identifiers, foreign keys, votes, and historical registration data. It maps legacy `pending` and `approved` accounts to `unverified`, and legacy `rejected` accounts to `deactivated`. It does not automatically activate any legacy `approved` voter, even if `email_verified_at` is non-null, because an old timestamp alone does not establish that the current OTP flow verified the address. The migration records these mappings in `voter_status_migration_review`; eligible pending/approved users must complete a fresh email OTP before the application activates their accounts, while rejected users remain deactivated. Review the pre-migration counts and take a verified backup before applying it. DDL commits implicitly, so rollback requires restoring the full backup during a maintenance window.

## Configuration wizard and setup validation

The project includes a read-only configuration validator at `http://localhost/online_voting_system/index.php?page=config-setup`. It is restricted to local development, never accepts secrets, and never writes configuration files. Enter values only in `C:\xampp\private\online_voting_system.env` using a local editor, then refresh the validator.

Required values:

1. `APP_NAME` — the project or system name shown to users.
2. `APP_ENV` — `development` or `production`.
3. `APP_BASE_URL` — full public URL, for example `http://localhost/online_voting_system`.
4. `APP_TIMEZONE` — a valid IANA timezone such as `Africa/Nairobi`.
5. `DB_HOST` — MySQL host, usually `127.0.0.1`.
6. `DB_PORT` — MySQL port, usually `3306`.
7. `DB_NAME` — the database name.
8. `DB_USER` — MySQL username.
9. `DB_PASSWORD` — local MySQL password (may be blank only if the local MySQL account has no password).
10. `APP_ENCRYPTION_KEY` — base64-encoded 32-byte key.
11. `APP_HMAC_KEY` — base64-encoded key at least 32 bytes.
12. `SMTP_HOST` — usually `smtp.gmail.com` for Gmail.
13. `SMTP_PORT` — usually `587`.
14. `SMTP_SECURITY` — `starttls`, `ssl`, or `none`; use `starttls` for Gmail.
15. `SMTP_USERNAME` — Gmail address or SMTP username.
16. `SMTP_PASSWORD` — Gmail App Password or SMTP password.
17. `SMTP_FROM_EMAIL` — sender address.
18. `SMTP_FROM_NAME` — sender display name.
19. `ADMIN_EMAIL` — administrator contact email.
The validator checks: required fields, valid key format, database status, required tables, PHP version/extensions, writable directories, production safety, the application URL, and the private config directory.

Use `C:\xampp\private\online_voting_system.env` for local configuration. Do not enter private credentials in any browser form or paste them into ChatGPT, Copilot, GitHub, or logs. The application never prints actual passwords, keys, or SMTP secrets in the validation results.

Generate keys safely on your own machine:

```powershell
& 'C:\xampp\php\php.exe' -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

Run this command twice and keep the outputs in your private local config file. Do not overwrite existing valid keys without explicit confirmation because losing them can make previously encrypted data unreadable.

## SMTP and email testing

For Gmail, set these values in `C:\xampp\private\online_voting_system.env`:

```ini
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_SECURITY=starttls
SMTP_USERNAME=your-full-gmail-address@gmail.com
SMTP_PASSWORD=your-16-character-google-app-password
SMTP_FROM_EMAIL=your-full-gmail-address@gmail.com
SMTP_FROM_NAME=Online Voting System
```

In the Google Account security settings, enable 2-Step Verification and create an **App Password** for this application. Use that App Password—not your normal Google password—for `SMTP_PASSWORD`. Use the same Gmail address for `SMTP_USERNAME` and `SMTP_FROM_EMAIL`. Keep both fields blank until you enter them yourself, and never share or commit the App Password. If Gmail blocks sign-in or your account does not offer App Passwords, use an approved SMTP relay or a local test service such as Mailpit. Delivery failures are logged without logging OTP values; actual Gmail delivery must be tested after you configure valid credentials.

Voter registration sends a six-digit email OTP before the account can be completed. Codes expire after 10 minutes, allow five incorrect entries, and can be resent after 60 seconds. Password recovery uses the same OTP flow and gives the same generic response for known and unknown addresses. Successful verification creates an active voter account immediately; password recovery updates the password, invalidates outstanding reset tokens, and revokes existing sessions.

## Database migrations and backups

Before any database migration, export a backup and keep it outside public access and source control. From PowerShell with XAMPP, for example:

```powershell
& 'C:\xampp\mysql\bin\mysqldump.exe' --host=127.0.0.1 --user=root --single-transaction --routines --triggers --databases online_voting_system --result-file='C:\secure-backups\online_voting_system.sql'
```

Store database backups, voter data, uploaded images, and application keys securely. Test that a backup can be restored before relying on it. For a new installation, import `database/schema.sql` once. For an existing installation, apply only the documented upgrade scripts that it still needs, each **once**, in order: session revocation (if missing), then `migration_20261105_email_otp_and_rate_limits.sql`, then `migration_20261106_status_model_cleanup.sql` if the database still contains legacy `pending`, `approved`, or `rejected` voter statuses. Do not re-import the initial schema into an existing database.
Back up the project files separately, excluding `.env`, `storage/backups`, and runtime logs from Git. Keep private config and encryption keys in a separate protected backup; losing the keys prevents access to encrypted National IDs.

## Running the automated integration tests

Start the XAMPP MySQL service, then run this from PowerShell:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\tests\integration.ps1
```

The test runner creates a uniquely named disposable database, imports the schema and migration, starts the PHP built-in server and a local fake SMTP server, exercises OTP, recovery, rate-limit, session, and voting workflows, and drops only the test database it created. It does not send email to Gmail or change the `online_voting_system` database. The test runner currently assumes the default local XAMPP MySQL `root` account has no password; adjust the test script's MySQL CLI arguments if your local server uses different credentials.

The test scripts and fixtures are for a local source checkout only and are intentionally excluded from the hosting ZIP.

## Features and access rules

- **Main Admin:** one provisioned account; manages elections, candidates, and Junior Admin privileges; can inspect linked vote records and audit history.
- **Junior Admin:** an active voter with additional access. Can view basic voter data with National IDs masked and see aggregate election results, but cannot edit, promote, demote, or export voter data. These restrictions are enforced on the server.
- **Voter:** registers, verifies their email by OTP, becomes active immediately, votes once per open election, and sees their own candidate-level voting history.
- New voter accounts remain `unverified` until email verification succeeds. Verified accounts become `active` and may sign in immediately; suspended or deactivated accounts are blocked.
- Results are visible to voters only after the election closes (by its scheduled end or early Main Admin closure). Ties are joint wins; a zero-vote election has no winner and shows zero votes/0% for every candidate.
- Vote submission uses a database transaction, a unique voter/election constraint, and a candidate/election foreign key. Voting architecture is not changed by the OTP or account-status work.
- Candidate photos must be JPEG, PNG, or WebP, up to 3 MB. Candidate and election details cannot be changed after votes have been cast.
- Light/dark themes persist in browser `localStorage`; charts update their text/grid colors with the active theme.

## Security and operations

- Passwords use PHP's `password_hash()` / `password_verify()`.
- Passwords must be between 12 and 72 bytes to avoid bcrypt truncation on the PHP 8.0 XAMPP runtime.
- Session cookies are HttpOnly and SameSite; session IDs are regenerated after login.
- Per-account session versions revoke all active sessions after a password reset or change. A password change requires signing in again.
- All state-changing forms use CSRF tokens, and database access uses PDO prepared statements.
- National IDs are encrypted with AES-256-GCM and separately keyed-HMACed for lookup. Email/National ID historical ownership is kept in `voter_identifiers`.
- Email OTPs are random, 10-minute, single-use codes; only keyed hashes are stored. Attempts, resend cooldowns, and temporary login/recovery limits are enforced server-side.
- Password recovery requires an email OTP, invalidates outstanding legacy password reset tokens, and increments the account session version to revoke existing sessions.
- Sensitive files and configuration must remain outside source control. The project `.gitignore` excludes `.env`, uploaded photographs, and runtime logs.
- Use HTTPS, restricted database credentials, backups, and access-controlled deployment settings outside local development. Do not expose the XAMPP development server to the public internet.

## Git and GitHub

Initialize Git in the project directory if desired. Review `.gitignore` before the first commit and never commit `.env`, generated keys, SMTP credentials, voter data, uploaded images, or runtime logs. The `.env.example` file contains setting names only.

## Deployment

Use a supported PHP/MySQL version with PDO MySQL, OpenSSL, Fileinfo, and mbstring, and serve the site only over HTTPS. Place the private config at `/etc/online_voting_system.env` when your provider permits it, or set the required variables in the hosting control panel's protected environment settings. Do not rely on a project-root `.env` on production hosting. Set a production URL, timezone, independent secret keys, restricted database account, SMTP relay, safe upload limits, and protected error logs. Back up files, database, and keys separately and test restores. Confirm the hosting provider's Apache/Nginx rules prevent direct access to private files and use a supported PHP/MySQL version. This application remains educational and has not been certified or independently audited for official election use.
