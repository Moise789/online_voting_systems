<?php
declare(strict_types=1);

function notify_email(string $recipient, string $type, string $subject, string $body): bool
{
    $status = 'sent';
    $error = null;
    try {
        smtp_send($recipient, $subject, $body);
    } catch (Throwable $exception) {
        $status = 'failed';
        $error = mb_substr($exception->getMessage(), 0, 500, 'UTF-8');
        error_log('Email notification failed (' . $type . '): ' . $exception->getMessage());
    }

    try {
        $stmt = pdo()->prepare('INSERT INTO email_notifications (recipient_email, notification_type, status, error_message) VALUES (?, ?, ?, ?)');
        $stmt->execute([$recipient, $type, $status, $error]);
    } catch (Throwable $exception) {
        error_log('Could not record email notification status: ' . $exception->getMessage());
    }
    return $status === 'sent';
}

function smtp_send(string $recipient, string $subject, string $body): void
{
    assert_production_configuration();
    $host = app_config('SMTP_HOST');
    $from = app_config('SMTP_FROM_EMAIL');
    if ($host === null || $from === null) {
        throw new RuntimeException('SMTP is not configured. Set SMTP_HOST and SMTP_FROM_EMAIL in the private configuration file.');
    }
    $port = (int) app_config('SMTP_PORT', '587');
    $security = app_config('SMTP_SECURITY', 'starttls');
    $transport = $security === 'ssl' ? 'ssl://' : 'tcp://';
    $socket = stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT);
    if ($socket === false) {
        throw new RuntimeException('Could not connect to SMTP server: ' . $errstr);
    }
    stream_set_timeout($socket, 10);
    try {
        smtp_expect($socket, [220]);
        smtp_command($socket, 'EHLO localhost', [250]);
        if ($security === 'starttls') {
            smtp_command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Could not establish SMTP TLS.');
            }
            smtp_command($socket, 'EHLO localhost', [250]);
        } elseif ($security !== 'ssl' && $security !== 'none') {
            throw new RuntimeException('SMTP_SECURITY must be ssl, starttls, or none.');
        }
        $username = app_config('SMTP_USERNAME');
        $password = app_config('SMTP_PASSWORD');
        if ($username !== null) {
            smtp_command($socket, 'AUTH LOGIN', [334]);
            smtp_command($socket, base64_encode($username), [334]);
            smtp_command($socket, base64_encode($password ?? ''), [235]);
        }
        smtp_command($socket, 'MAIL FROM:<' . smtp_address($from) . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . smtp_address($recipient) . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);
        $fromName = app_config('SMTP_FROM_NAME', 'Mobile and Online Voting System');
        $headers = [
            'From: ' . smtp_header($fromName) . ' <' . smtp_address($from) . '>',
            'To: <' . smtp_address($recipient) . '>',
            'Subject: ' . smtp_header($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        $message = implode("\r\n", $headers) . "\r\n\r\n" . preg_replace('/(?m)^\./', '..', str_replace(["\r\n", "\r"], "\n", $body));
        fwrite($socket, str_replace("\n", "\r\n", $message) . "\r\n.\r\n");
        smtp_expect($socket, [250]);
        smtp_command($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}

function smtp_command($socket, string $command, array $expected): string
{
    if (fwrite($socket, $command . "\r\n") === false) {
        throw new RuntimeException('SMTP command write failed.');
    }
    return smtp_expect($socket, $expected);
}

function smtp_expect($socket, array $expected): string
{
    $response = '';
    do {
        $line = fgets($socket, 2048);
        if ($line === false) {
            throw new RuntimeException('SMTP server closed the connection unexpectedly.');
        }
        $response .= $line;
    } while (isset($line[3]) && $line[3] === '-');
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expected, true)) {
        throw new RuntimeException('SMTP server rejected a request (' . $code . ').');
    }
    return $response;
}

function smtp_address(string $address): string
{
    if (filter_var($address, FILTER_VALIDATE_EMAIL) === false || preg_match('/[\r\n<>]/', $address)) {
        throw new RuntimeException('Invalid SMTP email address.');
    }
    return $address;
}

function smtp_header(string $value): string
{
    if (preg_match('/[\r\n]/', $value)) {
        throw new RuntimeException('Invalid SMTP header value.');
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}
