# HabeshaAir.com

Premium air charter brokerage platform for Africa, the Middle East, and beyond.
Custom PHP 8 site for Namecheap cPanel shared hosting. No Node.js, no build step.

- Live: https://habeshair.com
- Stack: PHP 8.x · MySQL · vanilla HTML/CSS/JS · PHPMailer (SMTP)
- Repo: https://github.com/Samic333/habeshaair

## Local development

Requires PHP 8.1+ (`brew install php`) and MAMP for MySQL.

```sh
# 1. MAMP: start MySQL on port 8889. Create database `habeshair_local` + user.
# 2. phpMyAdmin → Import database/schema.sql
# 3. Generate an admin password hash and update the seed row:
#    php -r "echo password_hash('YourTempPass', PASSWORD_BCRYPT, ['cost'=>12]);"
#    Run: UPDATE admin_users SET password_hash='<hash>' WHERE id=1;
# 4. Copy includes/config.sample.php → includes/config.php and fill in creds.
# 5. From public_html/, run:
cd public_html
php -S localhost:8000
```

Open `http://localhost:8000/`. Admin: `http://localhost:8000/admin/login.php`.

## Deploying to Namecheap (cPanel)

1. cPanel → MySQL Databases → create db + user with full privileges.
2. cPanel → phpMyAdmin → Import `database/schema.sql`.
3. Generate a real bcrypt hash locally (see above) and update `admin_users`.
4. cPanel → Git Version Control → connect this repo, branch `main`, deploy path
   `/home/<cpuser>/repositories/habeshaair`. The `.cpanel.yml` script copies
   `public_html/` to the public web root automatically on each pull.
5. cPanel File Manager → create `public_html/includes/config.php` with real
   creds (this file is gitignored; deploys never overwrite it).
6. Permissions: dirs 755, files 644, `logs/` writable, `config.php` 600.
7. cPanel → Email Deliverability → enable SPF + DKIM for `habeshair.com`.

## Project structure

```
HabeshaAir/
├── .cpanel.yml                # cPanel git auto-deploy script
├── database/
│   ├── schema.sql             # CREATE TABLEs + seed admin
│   └── README.md
└── public_html/
    ├── .htaccess              # HTTPS, friendly URLs, security, gzip, 404
    ├── index.php · services.php · request.php · request-success.php ·
    │   how-it-works.php · about.php · contact.php · contact-success.php ·
    │   faq.php · privacy.php · terms.php · 404.php
    ├── includes/              # bootstrap, db, csrf, validation, mailer, header, footer
    ├── admin/                 # login, dashboard, requests, messages
    ├── assets/css · js · images/
    └── logs/
```

## Reference

- Domain (single 'a'): habeshair.com
- Admin email: info@habeshair.com
- WhatsApp: +1 (480) 915-9971
- Operator language: "HabeshaAir arranges flights on behalf of its clients with
  FAA Certified Part 135 air carriers or foreign equivalents."
