<?php
/**
 * mailer.php — sends transactional email.
 *
 * Default driver is PHP mail() with proper headers. For production deliverability
 * (DKIM/SPF), drop PHPMailer files into includes/lib/PHPMailer/ and switch
 * config 'mail.driver' to 'smtp'. The SMTP path is stub-loaded only if PHPMailer
 * exists, so the site runs out of the box without any external library.
 */

function send_admin_notification(string $subject, string $textBody, string $htmlBody = ''): bool {
    $to       = (string)cfg('mail.admin_to', cfg('app.email', 'info@habeshair.com'));
    $fromAddr = (string)cfg('mail.from_email', 'no-reply@habeshair.com');
    $fromName = (string)cfg('mail.from_name', cfg('app.company', 'HabeshAir'));
    $driver   = (string)cfg('mail.driver', 'mail');

    if ($driver === 'smtp' && _phpmailer_available()) {
        return _send_via_phpmailer($to, $fromAddr, $fromName, $subject, $textBody, $htmlBody);
    }
    return _send_via_mail($to, $fromAddr, $fromName, $subject, $textBody, $htmlBody);
}

function _send_via_mail(string $to, string $fromAddr, string $fromName, string $subject, string $text, string $html): bool {
    $boundary = '=_HA_' . bin2hex(random_bytes(8));
    $headers  = [];
    $headers[] = 'From: ' . sprintf('"%s" <%s>', addslashes($fromName), $fromAddr);
    $headers[] = 'Reply-To: ' . $fromAddr;
    $headers[] = 'X-Mailer: HabeshAir/PHP';
    $headers[] = 'MIME-Version: 1.0';

    if ($html !== '') {
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $body  = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $text . "\r\n\r\n";
        $body .= "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $html . "\r\n\r\n";
        $body .= "--$boundary--\r\n";
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $body = $text;
    }

    $ok = @mail($to, $subject, $body, implode("\r\n", $headers), '-f' . $fromAddr);
    if (!$ok) {
        @file_put_contents(__DIR__ . '/../logs/mail.log',
            sprintf("[%s] mail() failed to %s subject=%s\n", date('c'), $to, $subject),
            FILE_APPEND);
    }
    return (bool)$ok;
}

function _phpmailer_available(): bool {
    return file_exists(__DIR__ . '/lib/PHPMailer/PHPMailer.php');
}

function _send_via_phpmailer(string $to, string $fromAddr, string $fromName, string $subject, string $text, string $html): bool {
    require_once __DIR__ . '/lib/PHPMailer/Exception.php';
    require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/lib/PHPMailer/SMTP.php';
    try {
        $m = new PHPMailer\PHPMailer\PHPMailer(true);
        $m->isSMTP();
        $m->Host       = (string)cfg('mail.smtp_host');
        $m->Port       = (int)cfg('mail.smtp_port', 465);
        $m->SMTPAuth   = true;
        $m->Username   = (string)cfg('mail.smtp_user');
        $m->Password   = (string)cfg('mail.smtp_pass');
        $secure = (string)cfg('mail.smtp_secure', 'ssl');
        $m->SMTPSecure = $secure === 'tls' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $m->CharSet    = 'UTF-8';
        $m->setFrom($fromAddr, $fromName);
        $m->addAddress($to);
        $m->addReplyTo($fromAddr, $fromName);
        $m->Subject    = $subject;
        if ($html !== '') {
            $m->isHTML(true);
            $m->Body    = $html;
            $m->AltBody = $text;
        } else {
            $m->Body = $text;
        }
        $m->send();
        return true;
    } catch (\Throwable $e) {
        @file_put_contents(__DIR__ . '/../logs/mail.log',
            sprintf("[%s] SMTP failed: %s\n", date('c'), $e->getMessage()),
            FILE_APPEND);
        return false;
    }
}
