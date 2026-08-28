# KUBLI — PHP backend

This replaces the previous Supabase backend with a plain PHP + SQL backend.
No Node, no external services — just PHP and (optionally) MySQL.

## Fastest way to try it (zero setup)

The app ships configured for **SQLite** by default, so it needs nothing installed
beyond PHP itself. From this folder:

```bash
php -S localhost:8000
```

Then open **http://localhost:8000** in your browser. A `data/kubli.sqlite`
database file is created automatically on first request — nothing to install
or configure.

> Camera access requires a secure context. `localhost` counts as one, so the
> camera will work here — it won't if you just double-click `index.html`.

## Switching to MySQL for real deployment

1. Create a database and load the schema:
   ```bash
   mysql -u root -p -e "CREATE DATABASE kubli CHARACTER SET utf8mb4;"
   mysql -u root -p kubli < schema.sql
   ```
2. Open `config.php` and change:
   ```php
   define('DB_DRIVER', 'mysql'); // was 'sqlite'
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'kubli');
   define('DB_USER', 'your_db_user');
   define('DB_PASS', 'your_db_password');
   ```
3. Deploy the folder to any PHP host (Apache/Nginx + PHP 8.1+, or `php -S`).
   Make sure the `uploads/` directory is writable by the web server
   (`chmod -R 775 uploads`).

## What changed from the Supabase version

- **Auth** — plain PHP sessions + `password_hash()`/`password_verify()`
  instead of Supabase Auth. Session cookie keeps you signed in across
  refreshes, same as before.
- **Database** — `users` and `reports` tables (see `schema.sql`) instead of
  Supabase Postgres tables. Same fields, same risk-classification logic
  (unchanged, still computed in the browser in `script.js`).
- **Photo storage** — uploaded photos are saved to
  `uploads/reports/{user_id}/...` on disk and served as static files,
  instead of Supabase Storage.
- **API** — a small set of PHP endpoints under `api/` that `script.js` now
  calls with `fetch()`:
  - `signup.php`, `signin.php`, `signout.php`, `session.php`
  - `reports.php` (GET list / POST create with photo), `report_update.php`
    (Manager: status + permit scope)
  - `users.php` (Admin: list accounts), `user_update.php` (Admin: role/status),
    `user_create.php` (Admin: "Add account" — generates a temporary password,
    shown once in the confirmation dialog since there's no password field in
    that form)

## File layout

```
index.html            — same UI as before, just without the Supabase <script> tag
style.css              — unchanged
script.js               — rewritten to call the PHP API instead of Supabase
config.php              — DB connection + session bootstrap (SQLite or MySQL)
schema.sql               — MySQL table definitions
api/
  signup.php, signin.php, signout.php, session.php
  reports.php, report_update.php
  users.php, user_update.php, user_create.php
data/                    — SQLite database lives here (auto-created)
uploads/reports/         — uploaded photos land here, organized by user id
```

## Security notes for going further

This is a prototype-grade backend, good enough to demo and iterate on. Before
any real deployment you'd want to add: CSRF tokens on state-changing requests,
rate limiting on signup/signin, HTTPS-only cookies (`secure => true` in
`config.php` once you're on HTTPS), and stricter upload validation (the
current check only looks at MIME type).
