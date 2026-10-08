# ELIA database setup on Hostinger

For a **new, empty production database**. `hostinger-production.sql` combines,
in order, schema.sql, request-management.sql, travel-monitoring.sql,
password-recovery.sql, and partnerships.sql. It includes the five request types
but no accounts, uploaded documents, real records, or official checklist mappings.
Regenerate the bundle when those source migrations change.

## 1. Create and import

1. In hPanel, select your website, then **Databases > Management**.
2. Create a MySQL database and database user with a unique password. Copy the
   complete database name and username, including the `u..._` prefix.
3. Open that database in **phpMyAdmin** and confirm it is empty.
4. Select **Import**, choose `hostinger-production.sql`, then run the import once.
5. Confirm there are 13 tables and five rows in `request_types`.

The bundle does not create a database or database user and does not require
GRANT or SUPER privileges. Do not import the individual migrations afterwards.
If import fails, stop and inspect the error: MySQL DDL is not rolled back as a
single transaction, so the database may be partly initialized. Use a new empty
database for a fresh retry; do not rerun over existing production records.

For an existing installation, back it up and apply only missing migrations.
To transfer existing local records, use a private full SQL export instead of
this fresh-install bundle, and transfer its uploaded documents separately.

## 2. Connect the application

1. Upload the application to your website's `public_html` directory (or your
   intended subfolder), including its `.htaccess` files.
2. Copy `config/hostinger.example.php` to `config/local.php` **on the server**.
3. Replace database name, user, password, host, domain, and private storage path
   with your actual hosting details. `localhost` is the usual host; use hPanel's
   displayed value. Never use the local XAMPP root account on production.
4. For a domain-root installation, keep `base_path` empty. For a subfolder,
   set it to `/elia-system` and append that path to `app_url`.
5. Create the configured document directory outside `public_html` and ensure
   PHP can write to it. Keep credentials in the ignored `config/local.php`.
6. Use PHP 8.x with pdo_mysql, mbstring, fileinfo, and zip enabled. Enable HTTPS,
   turn off PHP `display_errors`, and enable server error logging.

`config/app.php` already loads local.php, and `config/database.php` already uses
these settings with utf8mb4 and prepared statements. No source credential edit
is needed. Keep password recovery disabled until mail delivery is configured.

## 3. Create the first administrator

If SSH is available, run from the application's directory:

```sh
php scripts/create-user.php admin your-email@example.com "ELIA Administrator"
```

Enter a unique password of 12–72 bytes when prompted. Terminal input may be
visible. No default administrator or password is included in the SQL file.

Without SSH, register your own account through the website, then use the private
hPanel phpMyAdmin SQL tab to promote that exact account. Replace the example
email with the address you just registered (escape any apostrophe as two
apostrophes in SQL):

```sql
SELECT id, email, role FROM users WHERE email = 'your-email@example.com';
UPDATE users SET role = 'admin', auth_version = auth_version + 1
WHERE email = 'your-email@example.com' AND role = 'client';
```

Verify the selected account first, then sign out and sign in again. Configure
and confirm the official checklists in **Request types & checklists** before
clients submit requests. Verify sign-in and a document upload/download after
deployment. Successful local SQL validation does not verify the live host.

Official Hostinger guides:

- https://www.hostinger.com/support/1583542-how-to-create-a-new-mysql-database-in-hostinger/
- https://www.hostinger.com/support/1864324-how-to-upload-and-set-up-your-database-at-hostinger/
- https://www.hostinger.com/support/1884149-how-to-import-a-database-with-phpmyadmin-in-hostinger/
