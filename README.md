# OMSC ELIA

Vanilla PHP 8.x, PDO/MySQL, and locally hosted Bootstrap 5.3.3. The only roles are `admin` and `client`. Includes the approved public landing page, authentication, client registration, request management, versioned document uploads, review workflows, and database notifications.

## Local setup (XAMPP)

1. Start Apache and MySQL. Keep this directory at `C:\xampp\htdocs\elia-system`.
2. Create a database named `elia_system` with `utf8mb4_unicode_ci` collation. Import [database/schema.sql](database/schema.sql), then [database/request-management.sql](database/request-management.sql). Existing foundation installations need only the second migration. No accounts or official requirement mappings are seeded. Both scripts can be rerun without clearing existing records.
3. Defaults in `config/app.php` match local XAMPP (`127.0.0.1`, root, blank database password). For other installations, copy `config/local.example.php` to `config/local.php` and enter the database credentials and base path. Database settings also accept `ELIA_DB_HOST`, `ELIA_DB_PORT`, `ELIA_DB_NAME`, `ELIA_DB_USER`, and `ELIA_DB_PASSWORD` environment variables; local.php takes precedence.
4. Create an account from a terminal:

   ```powershell
   php scripts/create-user.php admin admin@example.edu "ELIA Administrator"
   php scripts/create-user.php client client@example.edu "Example Client"
   ```

   Each command reads a password from standard input. Use a unique password of 12–72 bytes. Interactive input may be visible, so use a private terminal. No passwords are shipped or stored in plaintext.
5. Open `http://localhost/elia-system/` (or `http://localhost:8080/elia-system/` on this machine, where Apache listens on port 8080).

Successful logins redirect to `/elia-system/admin/dashboard.php` or `/elia-system/client/dashboard.php`. Set `base_path` to an empty string for `/admin/dashboard.php` and `/client/dashboard.php` at the domain root.

`index.php` stays public, including for signed-in users. Guests see Login/Create Account; signed-in users see their Dashboard and a POST Logout form. Get Started opens `register.php`. Successful registration creates a Client account and returns to `login.php`; it does not automatically sign the user in. Public registration cannot create Admin accounts. Provision Admin accounts only with the CLI tool. Admin and Client pages call `requireAdmin()` and `requireClient()` respectively.

## Security and deployment

- Password hashing/verification, regenerated login sessions, 30-minute idle expiry, HttpOnly and SameSite cookies, and Secure cookies on HTTPS.
- CSRF protection for all POST actions; role and active-account checks read the database on every authenticated request.
- Registration validates names, email, password length and confirmation on the server. Passwords are hashed, the client role is fixed in SQL, and the unique email index prevents duplicate accounts.
- Five unsuccessful attempts lock an existing account for 15 minutes. Errors do not disclose whether an account exists. Add web-server rate limiting when exposing the login publicly.
- Prepared PDO statements, escaped output, security headers, and generic browser errors; details go to the PHP error log.
- Apache must allow the supplied `.htaccess` rules (`AllowOverride All` or equivalent). They deny HTTP access to configuration, includes, database scripts, account tools, and tests, and disable directory listing. Configure equivalent deny rules on other servers. PHP's development server does not enforce these files.
- Before public deployment, enable HTTPS, use a dedicated database account with only the required privileges, protect PHP logs outside the web root, and set production PHP `display_errors=Off`. If HTTPS terminates at a reverse proxy, configure the web server to pass a trusted HTTPS indicator to PHP.
- Bootstrap assets are vendored under `assets/vendor/bootstrap`; their upstream MIT license notices are retained. The approved Inter and Source Serif 4 fonts load from Google Fonts with system fallbacks when unavailable. The content security policy permits only those font/style hosts in addition to local assets.

## Request management

1. Sign in as Admin and open **Request types & checklists**. Five initial request types are seeded: Conference / Meeting Assessment, Referendum / BOT, Pre-Departure, Post-Travel, and Other ELIA Transaction.
2. Add ELIA's official requirements, instructions, required/optional flags, and ordering. Mark the type's checklist reviewed and ready after configuration. Editing a requirement resets readiness for new requests. Types may be deactivated; existing requests remain accessible. An intentionally empty checklist must also be explicitly marked ready.
3. Clients select a type, inspect its checklist, save a draft, fill its information, upload documents, and submit. Drafts may be incomplete. Submission requires title, purpose, destination, country, start/end dates, and all required documents. An absent optional document does not block submission.
4. Admin starts review, reviews each uploaded document, and approves, requests revision, or rejects the request. Document revision/rejection requires document-level remarks; returning the request for revision requires a separate request-level explanation. Save document decisions, then use **Request revision** to notify the client and unlock corrections.
5. The client replaces marked documents, edits request information if needed, and resubmits. Earlier files and their review decisions remain available. Verified documents cannot be silently replaced during revision.
6. After approval, Admin moves the request through In Progress → Post Travel → Completed. This phase tracks these states; a separate monitoring module is not included. Pre-Departure and Post-Travel are also configurable request types.

Workflow: `draft → submitted → under_review → approved → in_progress → post_travel → completed`. Review can return `for_revision → resubmitted → under_review` or end in `rejected`. Clients may cancel drafts, submitted/resubmitted requests awaiting review, and requests returned for revision. Each transition records the actor, timestamp, and remarks and creates database notifications. No email or SMS is sent.

Reference numbers use the database-generated request ID, for example `ELIA-2026-000001`, with a unique database index. The sequence does not reset each year; gaps are expected from unsubmitted/cancelled drafts. References are assigned once on first submission.

`request_requirements` stores a per-request snapshot, so later template edits do not change an existing request's checklist. `request_documents` stores one row per upload version. Its template association is obtained through that snapshot, and its path is derived from the configured storage directory plus the generated filename rather than duplicated in the database. Reviewed versions cannot be edited; correction requires a new version. Request locks and revision counters reject stale/concurrent changes.

Uploads accept PDF, DOC, DOCX, JPG, JPEG, and PNG, up to 10 MB each. Extension and server-detected MIME must agree; Word archives and image structure are checked. Files use cryptographically generated names and are served only through an authorized attachment endpoint. The default `uploads/.htaccess` denies direct HTTP access; this has been tested under Apache. For production, preferably set `document_storage` in `config/local.php` to a writable directory outside the web root. Back up that directory together with the database. Do not expose uploads through PHP's development server, which ignores `.htaccess`.

Configure PHP `upload_max_filesize` to at least `10M` and `post_max_size` above `10M` (for example `16M`) and ensure Apache can write to the storage directory. Current local PHP limits are 40 MB; the application still enforces 10 MB. DOCX validation requires `zip`, and MIME validation requires `fileinfo`.

Official checklist mappings were not supplied. Sample requirements exist only as temporary integration-test fixtures, not as institutional defaults. ELIA must configure and confirm the five checklists before clients can create their requests.

## Verification

PHP extensions: `pdo_mysql`, `mbstring`, `session`, `fileinfo`, and `zip`; the test runner also uses `curl`.

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
php tests/foundation.php http://127.0.0.1:8080/elia-system
php tests/requests.php http://127.0.0.1:8080/elia-system
```

Start Apache and MySQL first; replace the port if needed. Alternatively, for isolated development:

```powershell
php -S 127.0.0.1:8097 -t C:\xampp\htdocs
```

Then in another terminal:

```powershell
php tests/foundation.php http://127.0.0.1:8097/elia-system
```

Run against a local development database only. Tests create uniquely named accounts and remove them in a `finally` block. They cover public landing content/navigation, client registration, forged admin roles, validation, duplicate emails, guest guards, role redirects/isolation, session regeneration, CSRF, logout, account deactivation, escaping, lockout, and asset paths. Use Apache to verify deny rules and HTTPS to verify Secure cookies. Check mobile navigation and keyboard focus in a browser before deployment.

Request tests exercise creation, type-specific checklists, submission validation, uploads, individual review, revision/re-upload, approval, completion, rejection/cancellation, history, notifications, configuration, filters, and pagination. Security checks include cross-client access/mutation/download, forged roles/owners/statuses, unsafe uploads, stale forms, and unique references. Fixtures are removed afterward, including uploaded files. On Windows, run the tests under an account allowed to delete files created by Apache; a restricted shell may otherwise be unable to clean uploaded fixtures.

Activity, report, analytics, partnership, email, and SMS modules remain outside this phase.

## File organization

- `config/`: application settings, deployment configuration example, PDO connection.
- `includes/`: bootstrap, session, CSRF, authentication and URL helpers; shared header, sidebar, footer, and dashboard content.
- `actions/`: login, registration, and logout POST handlers.
- `admin/dashboard.php`, `client/dashboard.php`: role-protected dashboard entry points.
- `index.php`, `login.php`, `register.php`: public landing page, unified login, and client registration.
- `includes/auth-links.php`, `includes/styles.php`: shared public authentication links and stylesheet loading.
- `includes/registration.php`: registration validation and client account persistence.
- `assets/css/theme.css`, `assets/css/landing.css`: shared approved typography/colors and the extracted landing-page styles.
- `assets/`: shared custom CSS and local Bootstrap CSS/JavaScript.
- `database/schema.sql`: users table with a unique email index and role constraints.
- `scripts/create-user.php`: CLI account provisioning.
- `tests/foundation.php`: repeatable HTTP integration checks.
- `.htaccess` files: directory listing and private-file restrictions.

Request module files:

- `database/request-management.sql`: seven new tables, foreign keys, indexes, and five initial types.
- `client/requests/index.php`, `create.php`, `view.php`: client entry points.
- `admin/requests/index.php`, `view.php`, `admin/request-types.php`: admin queue, review, and configuration.
- `includes/requests/repository.php`, `validation.php`, `workflow.php`, `documents.php`: data access, validation, transitions, and upload/version handling.
- `includes/requests/form.php`, `list.php`, `view.php`: shared presentation.
- `actions/requests/save.php`, `status.php`, `upload.php`, `review-document.php`, `download.php`, `configure.php`: authorized action handlers.
- `notifications.php`, `includes/notifications.php`, `actions/notifications/read.php`: paginated notifications and owner-only read marking.
- `uploads/.htaccess`: direct-access denial.
- `tests/http.php`: shared HTTP test helpers; `tests/requests.php`: request integration coverage.
