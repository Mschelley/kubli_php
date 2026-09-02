# KUBLI — PHP backend (refactored)

Community-reported, risk-classified tree hazard data, routed into a
permitting workflow. Same app as before — citizen reporting, manager
review, admin user management — reorganized into a cleaner folder
structure, running entirely on PHP (no static HTML file) against a
normalized MySQL database.

## Setup

1. Create the database and load the schema:
   ```bash
   mysql -u root -p -e "CREATE DATABASE kubli CHARACTER SET utf8mb4;"
   mysql -u root -p kubli < database/schema.sql
   ```
2. Open `config.php` and fill in your credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'kubli');
   define('DB_USER', 'your_db_user');
   define('DB_PASS', 'your_db_password');
   ```
3. Point your web server's document root at `public/` — Apache/Nginx +
   PHP 8.1+ with the `pdo_mysql` extension enabled, or for local
   testing:
   ```bash
   php -S localhost:8000 -t public
   ```
   Make sure `public/uploads/` is writable by the web server
   (`chmod -R 775 public/uploads`).
4. Open **http://localhost:8000**. `index.php` is the default document
   for both the PHP built-in server and Apache/Nginx, so no extra
   rewrite rules are needed.

> Camera access requires a secure context. `localhost` counts as one —
> it won't work if you open the file directly from disk.

## Folder layout

```
config.php                 DB credentials — outside public/, never web-reachable
database/
  schema.sql                normalized MySQL schema + seed data
src/                        application code — outside public/, not web-reachable
  bootstrap.php              session start, DB connect; every endpoint requires this first
  Http.php                   respond() / json_input() — the JSON in/out helpers
  Auth.php                   require_login() / require_admin() session guards
  Lookup.php                  resolves names <-> ids for the reference tables
  Repositories/
    UserRepository.php        every query that touches `users`
    ReportRepository.php      every query that touches `reports`
public/                     the web root — point your server here
  index.php                  the app shell — PHP, not static HTML
  assets/
    css/style.css             unchanged
    js/app.js                 unchanged (was script.js)
  api/                       HTTP endpoints — thin, delegate to src/Repositories
    signup.php, signin.php, signout.php, session.php
    reports.php, report_update.php
    users.php, user_update.php, user_create.php
  uploads/reports/            uploaded photos, organized by user id
```

## What changed from the original zip

**Fixed a broken path assumption.** The original endpoint files did
`require __DIR__ . '/../config.php'` and saved uploads to
`__DIR__ . '/../uploads/...'`, as if they lived inside an `api/` folder
— but they shipped flat at the project root next to `index.html`. Every
API call would have 404'd. They now actually live in `public/api/`, so
those relative paths work.

**Converted `index.html` to `index.php`.** Every page in the project is
now PHP-executed — the app shell sets its own `Content-Type` header on
the way out instead of relying on a static file being served as-is,
which also means it's ready for any server-side logic you add later
(e.g. reading a session before the page renders) without a rename.

**Split the 121-line `config.php` into focused files under `src/`:**
DB connection (`bootstrap.php`), JSON request/response helpers
(`Http.php`), and login/admin guards (`Auth.php`). Each endpoint now
reads as: validate input, call a repository method, respond — no
inline SQL.

**Moved database logic into two repository classes.** Every SQL
statement touching `users` lives in `UserRepository`, everything
touching `reports` lives in `ReportRepository`. Endpoints don't build
SQL directly.

**Normalized the database.** The original `reports` table stored
`context`, `status`, and `scope` as free-text/varchar columns, and
`symptoms` as a JSON array in a single column. That's replaced with:

- `location_contexts`, `report_statuses`, `permit_scopes` — small
  lookup tables for each fixed vocabulary, referenced by foreign key
  instead of repeating the string on every row
- `symptom_types` + `report_symptoms` — a proper many-to-many junction
  table instead of a JSON array, so which conditions were observed on
  a report is queryable data, not a blob
- `roles` + `account_statuses` — same treatment for the `users` table's
  `role` and `status` columns
- `submitted_by_name` on `reports` is gone — it was a duplicate of
  `users.name`, and duplicated data goes stale if a user is renamed.
  It's joined in at query time instead.

The JSON your frontend receives from `api/reports.php` and
`api/users.php` is unchanged — same field names, same shapes — so
`assets/js/app.js` didn't need any logic changes, only new file paths
for the stylesheet and script tag.

## Role and navigation changes

- **Sign-up always creates a Citizen account.** There's no role picker
  on Create Account, and `signup.php` ignores any `role` sent to it —
  new accounts are always `User`. Nobody self-registers as Manager or
  Admin.
- **Account management moved from Admin to Manager.** `users.php`,
  `user_create.php`, and `user_update.php` now require `require_manager()`
  instead of `require_admin()` (see `src/Auth.php`). An Admin session
  gets a 403 from all three. This means **your very first Manager
  account has to be created directly in the database** — there's no
  bootstrap UI for it. After loading `database/schema.sql`, sign up a
  normal citizen account, then promote it once via SQL:
  ```sql
  UPDATE users u JOIN roles r ON r.name = 'Manager'
  SET u.role_id = r.id WHERE u.email = 'your@email.com';
  ```
  From then on, that Manager can create and promote every other
  account (including other Managers and Admins) from the **User
  Accounts** tab.
- **Admin's tabs are now oversight-only:** Dashboard, Reports Log (a
  read-only table of every report with a CSV export), and Report
  Queue & Permit Routing (same status/scope controls Manager has).
  Admin no longer sees the Citizen Reporter tab or the User Accounts
  tab — it doesn't submit reports and doesn't touch accounts.
- **Manager's tabs:** Dashboard, Report Queue & Permit Routing, User
  Accounts, About.
- **Citizen's tabs:** Dashboard, Citizen Reporter, About.
- **Camera flow simplified:** there's no "Open camera" button anymore
  — the browser's permission prompt fires as soon as the Citizen
  Reporter tab opens. The shutter button is now labeled **Capture**,
  and taking a photo freezes the frame (swaps to the still image) with
  a brief flash for visual confirmation.

**The risk classification logic and the dashboard rendering are
unchanged.**

## Setting up to test on localhost

1. Make sure PHP 8.1+ with the `pdo_mysql` extension is installed
   (`php -v`, `php -m | grep pdo_mysql`), and a MySQL/MariaDB server is
   running locally.
2. Create the database and load the schema (see **Setup** above).
3. From the project root:
   ```bash
   php -S localhost:8000 -t public
   ```
4. Open `http://localhost:8000` in a browser and create a citizen
   account via **Create account**.
5. Promote that account to Manager directly in the database (see
   **Role and navigation changes** above) so you have someone who can
   create Admin/Manager accounts through the UI from then on.
6. To reset all data, drop and recreate the database, then reload
   `database/schema.sql`.

## Security notes for going further

Same prototype-grade caveats as before: add CSRF tokens on
state-changing requests, rate limit signup/signin, set
`'secure' => true` on the session cookie once you're on HTTPS, and
tighten upload validation (the current check only looks at MIME type).
