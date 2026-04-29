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
        'company'   => 'HabeshAir',
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
        'from_name'   => 'HabeshAir',
        'admin_to'    => 'info@habeshair.com',
        // SMTP (only used when driver = 'smtp', requires PHPMailer in includes/lib/PHPMailer/)
        'smtp_host'   => 'mail.habeshair.com',
        'smtp_port'   => 465,
        'smtp_secure' => 'ssl',                // 'ssl' or 'tls'
        'smtp_user'   => 'no-reply@habeshair.com',
        'smtp_pass'   => 'CHANGE_ME',
        // Reply-To plus-addressing for outbound RFQs. Phase 3's IMAP cron
        // routes airline replies back to the originating dispatch via this
        // tag. Address pattern: <local>+<token>@<domain>.
        'reply_inbox_local'  => 'replies',
        'reply_inbox_domain' => 'habeshair.com',
    ],
    'security' => [
        'csrf_ttl'           => 7200,          // seconds
        'rate_form_per_hour' => 5,             // submissions per IP per hour
        'rate_login_window'  => 900,           // 15 min
        'rate_login_max'     => 5,             // attempts within window
    ],
    'sheets' => [
        // Google Apps Script web app URL deployed to push submissions to
        // the "HabeshAir — Charter Requests" / "HabeshAir — Contact Messages"
        // sheets in the HabeshAir Drive folder. Leave blank to disable.
        'webhook_url' => '',
        'secret'      => '',
    ],
    'sheets_sync' => [
        // Google Sheet "Publish to web → CSV" URL for the Airlines directory.
        // Sheet → File → Share → Publish to web → choose sheet → CSV → Publish.
        // Columns: sheet_row_id|name|iata|icao|base_country|email|cname|phone|whatsapp|website|fleet|regions|services|pax_max|kg_max|rating|notes|active
        'airlines_csv_url' => '',
    ],
    'cron' => [
        // Shared secret required as ?secret=XXX on /cron/* URLs.
        // Generate with: php -r "echo bin2hex(random_bytes(16));"
        'secret' => '',
    ],
    'analytics' => [
        // Public paths that should NOT be tracked. /admin /cron /assets are
        // already filtered automatically.
        'skip_paths' => ['/healthz'],
        // Local / known-internal IPs to skip
        'skip_ips'   => ['127.0.0.1', '::1'],
    ],
];
