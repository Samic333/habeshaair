<?php
/**
 * config.sample.php — committed template.
 * Copy to includes/config.php (gitignored) and fill in real credentials.
 * On Namecheap, create config.php directly via cPanel File Manager.
 */
return [
    'app' => [
        'env'       => 'production',           // 'development' or 'production'
        'base_url'  => 'https://habeshair.com',
        'timezone'  => 'UTC',
        'company'   => 'HabeshaAir',
        'email'     => 'info@habeshair.com',
        'whatsapp'  => '14809159971',          // E.164 without + for wa.me links
        'whatsapp_display' => '+1 (480) 915-9971',
    ],
    'db' => [
        'host'    => 'localhost',
        'name'    => 'habeshair_local',
        'user'    => 'habesha_local',
        'pass'    => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'driver'      => 'mail',               // 'mail' (PHP built-in) or 'smtp' (PHPMailer)
        'from_email'  => 'no-reply@habeshair.com',
        'from_name'   => 'HabeshaAir',
        'admin_to'    => 'info@habeshair.com',
        // SMTP (only used when driver = 'smtp', requires PHPMailer in includes/lib/PHPMailer/)
        'smtp_host'   => 'mail.habeshair.com',
        'smtp_port'   => 465,
        'smtp_secure' => 'ssl',                // 'ssl' or 'tls'
        'smtp_user'   => 'no-reply@habeshair.com',
        'smtp_pass'   => 'CHANGE_ME',
    ],
    'security' => [
        'csrf_ttl'           => 7200,          // seconds
        'rate_form_per_hour' => 5,             // submissions per IP per hour
        'rate_login_window'  => 900,           // 15 min
        'rate_login_max'     => 5,             // attempts within window
    ],
];
