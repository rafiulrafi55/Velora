<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/vendor/autoload.php';

function sendSmtpEmail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): array
{
    $cfg = require __DIR__ . '/smtp-config.php';

    $required = ['host', 'port', 'username', 'password', 'from_email', 'from_name', 'encryption'];
    foreach ($required as $key) {
        if (!isset($cfg[$key]) || (string)$cfg[$key] === '') {
            return ['ok' => false, 'error' => 'SMTP configuration missing: ' . $key];
        }
    }

    $timeout = (int)$cfg['timeout'];
    if ($timeout < 20) {
        $timeout = 20;
    }

    $smtpUser = trim((string)$cfg['username']);
    $smtpPass = preg_replace('/\s+/', '', (string)$cfg['password']);
    $smtpHost = trim((string)$cfg['host']);
    $smtpPort = (int)$cfg['port'];
    $fromEmail = trim((string)$cfg['from_email']);
    $fromName = trim((string)$cfg['from_name']);
    $encryption = trim((string)$cfg['encryption']);

    $lastError = 'Unknown SMTP error.';

    for ($attempt = 1; $attempt <= 2; $attempt++) {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->CharSet = 'UTF-8';
            $mail->Host = $smtpHost;
            $mail->Port = $smtpPort;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
            $mail->SMTPSecure = $encryption === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Timeout = $timeout;

            if ($attempt === 2) {
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ];
            }

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail, trim($toName));
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody !== '' ? $textBody : strip_tags($htmlBody);

            $mail->send();

            return ['ok' => true, 'error' => ''];
        } catch (Exception $e) {
            $lastError = $e->getMessage();
            error_log('SMTP send failed (attempt ' . $attempt . '): ' . $lastError);

            if ($attempt < 2) {
                usleep(500000);
            }
        }
    }

    return ['ok' => false, 'error' => $lastError];
}
