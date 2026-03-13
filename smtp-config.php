<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

function smtp_env(string $name, string $default = ''): string
{
    $value = getenv($name);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return trim((string)$value);
}

return [
    'host' => smtp_env('SMTP_HOST', 'smtp.gmail.com'),
    'port' => (int)(smtp_env('SMTP_PORT', '587')),
    'username' => smtp_env('SMTP_USERNAME', 'rafiulrafi55@gmail.com'),
    'password' => smtp_env('SMTP_PASSWORD', 'ptcn aypl roeg jzuw'),
    'from_email' => smtp_env('SMTP_FROM_EMAIL', 'rafiulrafi55@gmail.com'),
    'from_name' => smtp_env('SMTP_FROM_NAME', 'Velora'),
    'encryption' => smtp_env('SMTP_ENCRYPTION', 'tls'),
    'timeout' => (int)(smtp_env('SMTP_TIMEOUT', '15')),
];
