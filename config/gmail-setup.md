# Gmail SMTP setup guide

## 1. Find the Gmail address

Use the Gmail address that you want to send registration and password recovery emails from. This must be the same address used for both `SMTP_USERNAME` and `SMTP_FROM_EMAIL`.

## 2. Generate a Google App Password

1. Sign in to your Google Account.
2. Go to Security.
3. Turn on 2-Step Verification if it is not already enabled.
4. Open App passwords.
5. Choose an app name such as `Online Voting System`.
6. Copy the 16-character App Password shown by Google.

Never use your main Google password in the app. Always use the App Password instead.

## 3. Enter the App Password securely

Put the App Password in your private local config file under `SMTP_PASSWORD`.

Do not paste it into a public webpage, GitHub, source code, or a chat transcript.

## 4. Configure the sender details

Set these values in the private config file:

- `SMTP_HOST=smtp.gmail.com`
- `SMTP_PORT=587`
- `SMTP_SECURITY=starttls`
- `SMTP_USERNAME=your-full-gmail-address@gmail.com`
- `SMTP_PASSWORD=your-16-character-google-app-password`
- `SMTP_FROM_EMAIL=your-full-gmail-address@gmail.com`
- `SMTP_FROM_NAME=Online Voting System`

## 5. Test delivery

Open the registration or password recovery page in the app and request an email. If the email arrives, the configuration is working. If it does not, check the PHP error log and the project SMTP configuration.

## 6. Diagnose Gmail SMTP problems

Common issues:

- App Password not used
- 2-Step Verification not enabled
- Wrong Gmail address or sender email
- Incorrect SMTP port or encryption setting
- Less secure app sign-in blocked by Google
- Hosting server blocked outbound SMTP

When Gmail rejects the login, regenerate the App Password and update `SMTP_PASSWORD` in the private config file. Do not print the App Password in logs or error messages.
