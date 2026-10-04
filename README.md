# OMSC ELIA

Vanilla PHP 8.x, PDO/MySQL, and locally hosted Bootstrap 5.3.3. The only roles are `admin` and `client`. Includes the approved public landing page, authentication and recovery, client registration, Admin user management, partnerships/MOU–MOA records, request management, pre-departure/post-travel monitoring, retained document uploads, review workflows, and database notifications.

## Local setup (XAMPP)

1. Start Apache and MySQL. Keep this directory at `C:\xampp\htdocs\elia-system`.
2. Create a database named `elia_system` with `utf8mb4_unicode_ci` collation. Import [database/schema.sql](database/schema.sql), then [database/request-management.sql](database/request-management.sql), [database/travel-monitoring.sql](database/travel-monitoring.sql), [database/password-recovery.sql](database/password-recovery.sql), and [database/partnerships.sql](database/partnerships.sql), in that order, **once each**. Existing installations need only their unapplied migrations. All five have already been applied to this workspace's local database. No accounts or official requirement mappings are seeded. Do not rerun the migrations.
3. Defaults in `config/app.php` use the local database (`127.0.0.1`, root, blank database password) and domain-root routing. This workspace's ignored `config/local.php` sets `base_path` to `/elia-system` for Apache's subfolder deployment. For other installations, copy `config/local.example.php` to `config/local.php` and enter the database credentials and base path. Database settings also accept `ELIA_DB_HOST`, `ELIA_DB_PORT`, `ELIA_DB_NAME`, `ELIA_DB_USER`, and `ELIA_DB_PASSWORD` environment variables; local.php takes precedence.
4. Create an account from a terminal:

   ```powershell
   php scripts/create-user.php admin admin@example.edu "ELIA Administrator"
   php scripts/create-user.php client client@example.edu "Example Client"
   ```

   Each command reads a password from standard input. Use a unique password of 12–72 bytes. Interactive input may be visible, so use a private terminal. No passwords are shipped or stored in plaintext.
5. Open `http://localhost/elia-system/` (or `http://localhost:8080/elia-system/` on this machine, where Apache listens on port 8080).

Successful logins redirect to `/elia-system/admin/dashboard.php` or `/elia-system/client/dashboard.php`. Set `base_path` to an empty string for `/admin/dashboard.php` and `/client/dashboard.php` at the domain root.

`index.php` stays public, including for signed-in users. Guests see Login/Create Account; signed-in users see their Dashboard and a POST Logout form. Get Started opens `register.php`. Successful registration creates a Client account and returns to `login.php`; it does not automatically sign the user in. Public registration cannot create Admin accounts. Provision the first Admin with the CLI tool; existing administrators can create subsequent accounts through User management. Admin and Client pages call `requireAdmin()` and `requireClient()` respectively.

## Admin user management

- Open **User management** in the Admin sidebar (`admin/users/index.php`). Search by name/email, filter by role/status, and browse 20 accounts per page. Last login is a successful sign-in timestamp, not an online indicator.
- Create Admin or Client accounts with a confirmed initial password, or edit an account's name, email, role, and active status. Public registration remains Client-only. Share initial credentials privately. Password recovery is available from the login page after email delivery is configured below. Admins do not view existing passwords or reset links.
- Deactivation blocks login and existing access on the next authenticated request. Role changes apply on the next request. Accounts and their historical requests/documents are retained; there is no delete action.
- An administrator cannot deactivate themselves or change their own role. Transaction locks and server-side guards protect the last active administrator and recheck the acting administrator's authority. Stale forms are rejected; reload the account before retrying. Login timestamps/counters do not invalidate an edit form.
- Uses the existing `users` table; no migration or seeded accounts are needed. Shared name/email validation is in `includes/registration.php`; account queries and mutation policy are in `includes/users.php`. POST actions require Admin authorization and CSRF, and creation uses the existing password policy and hashing.
- Run `C:\xampp\php\php.exe tests/users.php http://127.0.0.1:8080/elia-system` against the local development installation. Tests create temporary fixture accounts and remove them afterwards. Last-admin tests use a connection-local temporary table so existing accounts are unaffected.

## Password reset and recovery

- Use **Forgot your password?** on `login.php`. Both Admin and Client accounts use the same recovery pages. Public responses are the same for unknown, inactive, throttled, and eligible accounts; no reset links or passwords are displayed in the request response.
- Email links expire after 30 minutes and are single-use. Only a SHA-256 hash of a random 32-byte token is stored. A new issued link replaces the previous one. Links stop working if account email, role, or active status is changed through User management.
- GET requests never consume a link. The link moves into the visitor's server-side session and redirects to a clean URL; password submission requires CSRF and matching passwords under the existing 12–72-byte policy. Recovery pages load local assets only, disable caching and referrers, and do not include the token in the form. Configure web/proxy access logs to omit query strings for `reset-password.php` so initial reset URLs are not retained in logs.
- Successful reset hashes the new password, clears login cooldown, consumes the token, and increments `users.auth_version`. All earlier authenticated sessions lose access on their next request. The user must sign in normally; recovery never changes their role or activates a disabled account. Applying the migration also requires existing sessions to sign in again.
- Database-backed limits allow three requests per email per hour and twenty per source IP per hour, with a 60-second resend cooldown. The app uses `REMOTE_ADDR`, not untrusted forwarded headers; configure trusted proxy handling at the server if needed. Expired limiter rows are removed in bounded batches. Tokens and passwords are never written to application logs.

### Configure email delivery

Delivery is disabled by default. In ignored `config/local.php`, set:

```php
'app_url' => 'https://your-elia-domain.example/elia-system',
'mail_from' => 'no-reply@your-elia-domain.example',
'recovery_mail_enabled' => true,
```

`app_url` is the exact public application URL including its subfolder (if any). The local example is `http://localhost:8080/elia-system`; HTTP is accepted only for localhost/loopback. Reset URLs never use the incoming Host header. `ELIA_APP_URL` and `ELIA_MAIL_FROM` can supply these two values through environment variables instead.

Configure PHP's `mail()` delivery through the hosting mail service or XAMPP's sendmail/SMTP relay. For authenticated SMTP on Windows, configure the sendmail wrapper and `sendmail_path` in the PHP installation used by Apache; credentials belong in the server's private mail configuration, not this repository. Restart Apache after changing PHP settings. Use an authorized sender and verify inbox/spam delivery with an account whose mailbox you control before enabling recovery for users. `mail()` success means relay acceptance, not confirmed inbox delivery.

If configuration or relay hand-off fails, the public response remains generic and a non-sensitive diagnostic is logged. A failed hand-off removes the newly issued token. This workspace has no production sender/relay configured, and no real recovery emails have been sent during automated testing.

Test with `C:\xampp\php\php.exe tests/password-recovery.php http://127.0.0.1:8080/elia-system`. The suite captures mail in memory through a test transport and exercises HTTP reset, validation, replay/expiry, role preservation, session revocation, throttling, and account changes. It does not prove real mailbox delivery.

## Partnerships and MOU–MOA records

- Admin sidebar → **Partnerships & agreements**. Add a partner institution with country, address, contact details, website, and notes. Institution/country pairs must be unique. Search/filter the partner list, with 20 records per page.
- Open a partner to add multiple MOU/MOA agreements. Each agreement has a unique reference, title, recorded status, date signed, effective dates, notes, and archive flag. The institution cannot be reassigned when editing an agreement. Renewals can be recorded as separate agreements to retain earlier effective periods.
- A Signed record requires signing and effective-start dates. Effective-end date is optional for open-ended agreements and cannot precede the start date. Displayed status is derived as Draft, Upcoming, Active, Expired, Terminated, or Archived using `Asia/Manila` (the configured timezone). End dates are inclusive. Archive and termination take precedence over effective dates. No automatic renewal or official approval is performed.
- The MOU/MOA tab searches reference/title/institution and filters type and derived status. The partner's **View agreements** link scopes this list to that institution. Both lists paginate.
- Archive/restore through **Record availability** in the edit form. Records are never deleted by the UI. Archived partners cannot receive new agreements, and archived partners/agreements cannot receive uploads. Existing metadata remains editable and downloads remain available to Admins. Restoring a partner makes its individually unarchived agreements current again.
- Agreement uploads accept PDF/DOC/DOCX/JPG/JPEG/PNG up to 10 MB. Each upload is retained with a sequence number, description, uploader, and timestamp. Sequence numbers apply to all uploads for the agreement, including supporting documents; they are not legal agreement revision numbers. Previous files are never overwritten. Authorized downloads always use attachment responses; storage is not served directly.
- Every page/action/download requires Admin access. POSTs require CSRF. Mutations validate on the server, use prepared statements and transactions, and reject stale revisions. Client accounts have no access to partnership records. Partner metadata tracks creator and latest editor; this module does not provide a full field-change audit trail.
- New tables: `partners`, `partnership_agreements`, `partnership_documents`. No sample partnerships or official agreements are seeded. `includes/partnerships.php` holds the policies/repository. `includes/documents.php` shares file validation, storage paths, and attachment streaming with Request Management.
- Test: `C:\xampp\php\php.exe tests/partnerships.php http://127.0.0.1:8080/elia-system`. Fixtures and uploads are removed afterwards. On Windows, the CLI test process needs permission to delete files written by Apache.

## Security and deployment

- Password hashing/verification, regenerated login sessions, 30-minute idle expiry, HttpOnly and SameSite cookies, and Secure cookies on HTTPS.
- CSRF protection for all POST actions; role and active-account checks read the database on every authenticated request.
- Registration validates names, email, password length and confirmation on the server. Passwords are hashed, the client role is fixed in SQL, and the unique email index prevents duplicate accounts.
- Five unsuccessful attempts lock an existing account for 15 minutes. Errors do not disclose whether an account exists. Add web-server rate limiting when exposing the login publicly.
- Prepared PDO statements, escaped output, security headers, and generic browser errors; details go to the PHP error log.
- Apache must allow the supplied `.htaccess` rules (`AllowOverride All` or equivalent). They deny HTTP access to configuration, includes, database scripts, account tools, and tests, and disable directory listing. Configure equivalent deny rules on other servers. PHP's development server does not enforce these files.
- Before public deployment, enable HTTPS, use a dedicated database account with only the required privileges, protect PHP logs outside the web root, and set production PHP `display_errors=Off`. If HTTPS terminates at a reverse proxy, configure the web server to pass a trusted HTTPS indicator to PHP.
- Bootstrap assets are vendored under `assets/vendor/bootstrap`; their upstream MIT license notices are retained. The approved Inter and Source Serif 4 fonts load from Google Fonts with system fallbacks when unavailable.

## Request management

1. Sign in as Admin and open **Request types & checklists**. Five initial request types are seeded: Conference / Meeting Assessment, Referendum / BOT, Pre-Departure, Post-Travel, and Other ELIA Transaction.
2. Add ELIA's official requirements, instructions, required/optional flags, and ordering. Mark the type's checklist reviewed and ready after configuration. Editing a requirement resets readiness for new requests. Types may be deactivated; existing requests remain accessible. An intentionally empty checklist must also be explicitly marked ready.
3. Clients select a type, inspect its checklist, save a draft, fill its information, upload documents, and submit. Drafts may be incomplete. Submission requires title, purpose, destination, country, start/end dates, and all required documents. An absent optional document does not block submission.
4. Admin starts review, reviews each uploaded document, and approves, requests revision, or rejects the request. Document revision/rejection requires document-level remarks; returning the request for revision requires a separate request-level explanation. Save document decisions, then use **Request revision** to notify the client and unlock corrections.
5. The client replaces marked documents, edits request information if needed, and resubmits. Earlier files and their review decisions remain available. Verified documents cannot be silently replaced during revision.
6. After approval, Admin initializes the pre-departure checklist and deadline on the request. Required pre-departure documents must be verified before In Progress. In Post Travel, Admin initializes the post-travel checklist and deadline. Both stages' required documents must be verified before completion. Pre-Departure and Post-Travel also remain available as standalone request types; monitoring stages belong to the original request and do not create another request.

Workflow: `draft → submitted → under_review → approved → in_progress → post_travel → completed`. Review can return `for_revision → resubmitted → under_review` or end in `rejected`. Clients may cancel drafts, submitted/resubmitted requests awaiting review, and requests returned for revision. Each transition records the actor, timestamp, and remarks and creates database notifications. No email or SMS is sent.

Reference numbers use the database-generated request ID, for example `ELIA-2026-000001`, with a unique database index. The sequence does not reset each year; gaps are expected from unsubmitted/cancelled drafts. References are assigned once on first submission.

`request_requirements` stores a per-request snapshot, so later template edits do not change an existing request's checklist. `request_documents` stores one row per upload version. Its template association is obtained through that snapshot, and its path is derived from the configured storage directory plus the generated filename rather than duplicated in the database. Reviewed versions cannot be edited; correction requires a new version. Request locks and revision counters reject stale/concurrent changes.

Uploads accept PDF, DOC, DOCX, JPG, JPEG, and PNG, up to 10 MB each. Extension and server-detected MIME must agree; Word archives and image structure are checked. Files use cryptographically generated names and are served only through an authorized attachment endpoint. The default `uploads/.htaccess` denies direct HTTP access; this has been tested under Apache. For production, preferably set `document_storage` in `config/local.php` to a writable directory outside the web root. Back up that directory together with the database. Do not expose uploads through PHP's development server, which ignores `.htaccess`.

Configure PHP `upload_max_filesize` to at least `10M` and `post_max_size` above `10M` (for example `16M`) and ensure Apache can write to the storage directory. Current local PHP limits are 40 MB; the application still enforces 10 MB. DOCX validation requires `zip`, and MIME validation requires `fileinfo`.

Official checklist mappings were not supplied. Sample requirements exist only as temporary integration-test fixtures, not as institutional defaults. ELIA must configure and confirm the five checklists before clients can create their requests.

## Pre-departure and post-travel monitoring

- In **Request types & checklists**, assign each requirement to Submission, Pre-departure, or Post-travel. Mark each stage ready separately. Editing/moving a template resets readiness only for its affected stages. An intentionally empty stage still requires explicit readiness confirmation and initialization.
- The submission checklist is copied at draft creation. Each monitoring checklist is copied once, when Admin initializes that stage on the approved request. Template edits cannot silently change an initialized checklist. Existing submission records retain their original stage and documents after migration.
- Open **Travel monitoring** from either role's sidebar. Admin sees all approved/ongoing/post-travel/completed requests; clients see only their own. Filter by stage, missing documents, pending verification, corrections, overdue, or awaiting setup. Lists contain at most 20 requests per page.
- A single deadline applies to the required documents in each stage. Admin sets it during initialization and supplies instructions. A deadline is mandatory if required documents exist; no default deadline or institutional policy is invented. Later deadline changes require a reason and are saved with the actor/time in the existing request history.
- Deadlines are inclusive calendar dates in `Asia/Manila` (configured as `timezone`). A request is overdue after that date while required documents remain unverified, including pending ELIA review. The queue distinguishes missing uploads, pending verification, and corrections. Optional documents do not affect the stage's completion or overdue counts.
- Monitoring documents use the same upload, versioning, verification, and protected download handlers. A stage upload immediately enters pending review and notifies Admin; there is no second request-submission step. Client corrections remain in the same overall request status, and the previous reviewed file is retained. Pending/correction documents may be replaced; verified documents are locked. Monitoring review decisions notify the client immediately.
- Admin must initialize and finish pre-departure before advancing to In Progress or Post Travel. Completing a request checks both initialized stages and the physical presence of verified required files. Completed requests cannot accept uploads, reviews, or deadline edits. Stage setup/deadline edits appear as **Monitoring update** entries in the existing timeline.
- Existing in-progress/post-travel requests can have their pre-departure checklist initialized and completed retrospectively so they are not stuck after migration. This does not change their status or fabricate past verification. Already completed records are preserved and display that no stage checklist was recorded when applicable.
- Stage changes, stage initialization, deadline changes, and document decisions create database notifications. Overdue indicators are calculated when pages load; automatic scheduled reminders/email/SMS are not implemented.

No new monitoring tables are introduced. The migration extends `request_types`, `requirement_templates`, `request_requirements`, and `requests`. All document versions, notifications, and audit history continue using the existing tables.

## Verification

PHP extensions: `pdo_mysql`, `mbstring`, `session`, `fileinfo`, and `zip`; the test runner also uses `curl`.

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
php tests/foundation.php http://127.0.0.1:8080/elia-system
php tests/requests.php http://127.0.0.1:8080/elia-system
php tests/monitoring.php http://127.0.0.1:8080/elia-system
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

Monitoring tests cover stage isolation/readiness, deadline validation and changes, frozen checklists, overdue filters, ownership/CSRF/role restrictions, monitoring uploads/revisions, departure/completion guards, notifications, and pagination. `tests/request-helpers.php` shares lifecycle test helpers with the request suite.

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

Monitoring additions:

- `database/travel-monitoring.sql`: one-time extension of existing tables.
- `admin/monitoring.php`, `client/monitoring.php`: guarded monitoring entry points.
- `includes/requests/monitoring.php`: stage policies, progress/deadline calculations, queue queries, and transactional initialization/deadline updates.
- `includes/requests/monitoring-list.php`, `monitoring-panel.php`: shared queue and per-request monitoring presentation.
- `actions/requests/monitoring.php`: Admin-only, CSRF-protected stage initialization/deadline action.
- `tests/monitoring.php`, `tests/request-helpers.php`: monitoring coverage and shared lifecycle helpers.

The existing request-type editor, document handlers, request workflow/details, notifications, and sidebar were extended rather than duplicated. Bootstrap restores friendly exception handling and skips browser sessions for CLI commands; invalid CSRF forms return HTTP 403, avoiding Apache treating the unsupported 419 response as a server error. The upload directory's deny rule is explicitly retained by `.gitignore`.
