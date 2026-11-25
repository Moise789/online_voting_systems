# Hosting configuration checklist

Use this list when you deploy the project to a hosting provider.

## Values you choose or generate on your computer

- `APP_NAME`
- `APP_ENV`
- `APP_BASE_URL` (use the final public HTTPS URL supplied by your host)
- `APP_TIMEZONE`
- `APP_ENCRYPTION_KEY`
- `APP_HMAC_KEY`
- `SMTP_FROM_NAME`
- `ADMIN_EMAIL`

## Values supplied by the hosting provider

- `DB_HOST` and `DB_PORT`
- `DB_NAME`, `DB_USER`, and `DB_PASSWORD`
- Public domain, HTTPS URL, and document root
- File and directory permissions
- PHP version/extensions and outbound SMTP restrictions
- SSL certificate and HTTPS settings

## Values supplied by your Gmail account

- Gmail address for `SMTP_USERNAME` and `SMTP_FROM_EMAIL`
- Google App Password for `SMTP_PASSWORD`
- Gmail SMTP settings: `SMTP_HOST=smtp.gmail.com`, `SMTP_PORT=587`, `SMTP_SECURITY=starttls`
- 2-Step Verification status

## Values generated automatically by the application

- Session IDs
- CSRF tokens
- One-time verification codes
- Activity log entries
- Rate-limit counters

The encryption and HMAC keys are generated locally with PHP's secure random generator; the application does not create, display, or rotate them. Store them in the hosting provider's private environment settings or outside the public web root. Never use development keys or blank SMTP credentials in production.

## Safe deployment reminder

Keep all private values in a private local or hosting config file. Never paste passwords, app passwords, or encryption keys into public chat, a browser form, GitHub, or logs.
