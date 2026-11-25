<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$options = getopt('', ['port:', 'spool:']);
$port = filter_var($options['port'] ?? null, FILTER_VALIDATE_INT);
$spool = $options['spool'] ?? null;
if (!$port || !is_string($spool) || $spool === '') {
    fwrite(STDERR, "A port and spool path are required.\n");
    exit(2);
}

$server = stream_socket_server('tcp://127.0.0.1:' . $port, $errorCode, $errorMessage);
if ($server === false) {
    fwrite(STDERR, "Could not start the local SMTP test server.\n");
    exit(3);
}

while ($client = @stream_socket_accept($server, -1)) {
    stream_set_timeout($client, 10);
    fwrite($client, "220 localhost test SMTP\r\n");
    while (($line = fgets($client, 2048)) !== false) {
        $command = strtoupper(trim($line));
        if (str_starts_with($command, 'EHLO ') || str_starts_with($command, 'HELO ')) {
            fwrite($client, "250-localhost\r\n250 AUTH LOGIN\r\n");
        } elseif (str_starts_with($command, 'MAIL FROM:') || str_starts_with($command, 'RCPT TO:')) {
            fwrite($client, "250 OK\r\n");
        } elseif ($command === 'DATA') {
            fwrite($client, "354 End data with <CR><LF>.<CR><LF>\r\n");
            $message = '';
            while (($data = fgets($client, 2048)) !== false && trim($data) !== '.') {
                if (str_starts_with($data, '..')) {
                    $data = substr($data, 1);
                }
                $message .= $data;
            }
            file_put_contents($spool, $message . "\n---MESSAGE---\n", FILE_APPEND | LOCK_EX);
            fwrite($client, "250 Message accepted\r\n");
        } elseif ($command === 'QUIT') {
            fwrite($client, "221 Bye\r\n");
            break;
        } else {
            fwrite($client, "250 OK\r\n");
        }
    }
    fclose($client);
}

fclose($server);
