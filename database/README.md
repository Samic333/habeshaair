# Database — HabeshAir

Three tables: `charter_requests`, `contact_messages`, `admin_users`.
MySQL 5.7+ / 8.0 / MariaDB 10.4+. Charset `utf8mb4`, engine InnoDB.

## Import (cPanel phpMyAdmin)

1. cPanel → MySQL Databases → create database (e.g. `cpuser_habesha`) and a
   user with full privileges; note the credentials.
2. cPanel → phpMyAdmin → select that database.
3. Import → Choose file → upload `schema.sql` → Go.
4. Confirm three tables exist: `charter_requests`, `contact_messages`,
   `admin_users` (with one seeded row in `admin_users`).

## Replace the admin password hash

The seed row contains a placeholder hash that will not match any password.
Generate a real bcrypt hash locally and update the row:

```sh
php -r "echo password_hash('YourTempPass', PASSWORD_BCRYPT, ['cost'=>12]);"
```

Copy the output (starts with `$2y$12$…`) and run in phpMyAdmin SQL tab:

```sql
UPDATE admin_users
   SET password_hash = '<paste hash here>'
 WHERE username = 'admin';
```

Sign in at `/admin/login.php` with `admin` and the password you hashed.
Change it to a long, unique password before going live.

## Local development (MAMP)

1. MAMP → start MySQL on port 8889.
2. MAMP phpMyAdmin → create database `habeshair_local`, create user.
3. Import `schema.sql`.
4. Generate hash and update admin row as above.
5. In `public_html/includes/config.php`, set `db.host = 'localhost'`,
   `db.port = 8889` (if needed), name + user + pass.
