# StudentHub Campus Portal

StudentHub is a PHP and MySQL campus portal served from the `website` directory. It includes a public landing page, secure student registration and sign-in, role-specific dashboards, student and faculty administration, event management, and contact form processing.

> **Credential safety:** There are no default or demo credentials. Do not add real passwords, database secrets, or production credentials to this repository. Create the first administrator from the command line after configuring the database.

## Features

- **Student accounts:** Server-side validation and password hashing during registration. Students can view their portal dashboard and campus events.
- **Faculty accounts:** Faculty sign-in and a faculty dashboard. An administrator provisions and manages faculty accounts.
- **Administrator tools:** Protected dashboard, student search and management, faculty account management, event management, and audit records for key actions.
- **Events:** Authenticated event listing through a JSON API. Administrators can create, edit, and delete events without reloading the page. JPG, PNG, and WebP posters are accepted up to 2 MB.
- **Authentication protections:** Role checks, CSRF tokens, regenerated session IDs, secure logout, a 30-minute inactivity timeout, and optional rotating remember-me tokens.
- **Contact form:** Validates submissions on the server and appends them to a locked JSON Lines file in `website/studenthub/storage`.
- **Shared navigation:** Static pages use the common site navigation; portal pages show role-aware navigation.
- **Database:** MySQL/MariaDB schema with foreign keys, unique constraints, indexes, and prepared statements. Both MySQLi and PDO connectors are provided.

## Requirements

- PHP **8.1 or later**
- MySQL **8.0+** or a compatible MariaDB version
- PHP extensions: `mysqli`, `pdo_mysql`, and `fileinfo`
- Apache with `mod_rewrite`/`.htaccess` support for the included directory protections when deploying under Apache

## Local setup with XAMPP on Windows

### 1. Start services

Start **Apache** and **MySQL** in the XAMPP Control Panel. Confirm that PHP has the required extensions enabled in `php.ini`.

### 2. Create the database

For a new installation, import [`website/studenthub/schema.sql`](website/studenthub/schema.sql) using phpMyAdmin or the MySQL command-line client. The schema creates the `studenthub` database and its tables.

If you already have a StudentHub database created from the earlier schema, do not re-import the new schema over it. Apply [`website/studenthub/migration-faculty.sql`](website/studenthub/migration-faculty.sql) once to add faculty support.

### 3. Configure the database connection

For local development only, the application defaults to:

| Setting | Default |
| --- | --- |
| Host | `127.0.0.1` |
| Port | `3306` |
| Database | `studenthub` |
| User | `root` |
| Password | Empty |

If your local MySQL setup differs, set these environment variables for the PHP process:

- `STUDENTHUB_DB_HOST`
- `STUDENTHUB_DB_PORT` (optional; defaults to `3306`)
- `STUDENTHUB_DB_NAME`
- `STUDENTHUB_DB_USER`
- `STUDENTHUB_DB_PASSWORD`

The local defaults are not suitable for a public or production server.

### 4. Start the development server

From the repository root in PowerShell:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8001 -t website
```

Open [http://127.0.0.1:8001/](http://127.0.0.1:8001/) for the portal home page. The PHP built-in server is for local development; use Apache or another production web server for deployment.

### 5. Create the first administrator

The bootstrap script is **CLI-only** and refuses web requests. Set a strong, unique password of 12–72 characters containing uppercase and lowercase letters, a number, and a special character. Avoid placing the password directly in a command that will be saved in shell history.

Example PowerShell setup, run from the repository root:

```powershell
$env:STUDENTHUB_DB_HOST = "127.0.0.1"
$env:STUDENTHUB_DB_NAME = "studenthub"
$env:STUDENTHUB_DB_USER = "root"
$env:STUDENTHUB_DB_PASSWORD = ""
$secure = Read-Host "New admin password" -AsSecureString
$ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
try {
    $env:STUDENTHUB_ADMIN_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)
    & 'C:\xampp\php\php.exe' '.\website\studenthub\create-admin.php'
} finally {
    Remove-Item Env:\STUDENTHUB_ADMIN_PASSWORD -ErrorAction SilentlyContinue
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr)
}
```

The script prompts for the administrator's name and email address. After creation, sign in through [`website/studenthub/login.php`](website/studenthub/login.php), choosing the **Admin** role. Administrators can create faculty accounts from the Faculty accounts page; students can register through the public registration page.

## Pages and routes

| Route | Purpose | Access |
| --- | --- | --- |
| `website/index.html` | Portal home page and role sign-in choices | Public |
| `website/studenthub/register.php` | Student account registration | Public |
| `website/studenthub/login.php` | Student, faculty, and admin sign-in | Public |
| `website/studenthub/contact.php` | Validated contact form | Public |
| `website/studenthub/student-dashboard.php` | Student dashboard | Student |
| `website/studenthub/faculty-dashboard.php` | Faculty dashboard | Faculty |
| `website/studenthub/admin-dashboard.php` | Administrative overview | Admin |
| `website/studenthub/students.php` | Student search and management | Admin |
| `website/studenthub/faculty.php` | Faculty account management | Admin |
| `website/studenthub/events.php` | Event list and admin event editor | Signed-in users; editing is admin-only |
| `website/studenthub/logout.php` | Secure sign-out (POST with CSRF token) | Signed-in users |
| `website/studenthub/api/events.php` | JSON event listing and admin CRUD | Signed-in users; changes are admin-only |

Some legacy `.html` pages remain as entry points and route users to the current PHP flows.

## Database model

The schema in [`website/studenthub/schema.sql`](website/studenthub/schema.sql) defines:

- `users`: unique email, password hash, and account role (`student`, `faculty`, or `admin`).
- `students`: one-to-one student profile, linked to `users`.
- `faculty`: one-to-one faculty profile, linked to `users`.
- `events`: event details and optional poster path; `created_by` references the creating user.
- `registrations`: student/event relationship with a unique constraint preventing duplicate registrations for the same event.
- `remember_tokens`: hashed persistent-login tokens with expiry.
- `audit_logs`: records selected account and administrative actions.

The database uses InnoDB, foreign keys, and `utf8mb4`. Database reads and writes in the PHP application use prepared statements.

## Configuration and deployment

For production, configure the PHP/web-server process with:

- `STUDENTHUB_ENV=production`
- `STUDENTHUB_DB_HOST`
- `STUDENTHUB_DB_NAME`
- `STUDENTHUB_DB_USER`
- `STUDENTHUB_DB_PASSWORD` (must be non-empty)
- `STUDENTHUB_DB_PORT` if the database is not on port `3306`

Production mode rejects missing database settings. Store secrets in the hosting provider's secret manager or protected environment configuration, not in source files.

Before making the site public:

1. Use HTTPS and a supported PHP/MySQL deployment. Do not use PHP's built-in server as the public production server.
2. Set up a dedicated database and least-privilege database user; do not use the local XAMPP `root` account.
3. Import the schema and create the initial admin using the deployment CLI. Never expose `create-admin.php` through the web server.
4. Disable `display_errors`, enable `log_errors`, and keep logs outside the public document root.
5. Restrict write access to `website/studenthub/storage` and `website/studenthub/uploads`. Ensure uploads cannot execute as scripts and directories cannot be browsed.
6. Confirm Apache protections in the included `.htaccess` files are active, or configure equivalent restrictions in your web server.
7. Configure TLS, firewall rules, backups, log monitoring/rotation, and an operational process for rotating credentials.
8. Test registration, sign-in for each role, access restrictions, logout, event poster validation, and error handling in the deployed environment.

The contact form writes personal information to `website/studenthub/storage/contact-submissions.jsonl`. Protect this file, limit access to authorized operators, and define an appropriate retention and deletion policy before collecting real submissions.

## Validation checks

Run these checks from the repository root on Windows:

```powershell
$php = 'C:\xampp\php\php.exe'
Get-ChildItem '.\website\studenthub' -Recurse -Filter '*.php' |
    ForEach-Object { & $php -l $_.FullName }
node --check '.\website\script.js'
git diff --check
```

The PHP linter should report no syntax errors for every PHP file.

## Project layout

```text
website/
  index.html                 Public landing page
  script.js                  Shared navigation and site interactions
  styles.css                 Site-wide styles
  studenthub/
    api/events.php           JSON events API
    schema.sql               New-install database schema
    migration-faculty.sql    One-time upgrade for older databases
    create-admin.php         CLI-only first-admin bootstrap
    *.php                    Registration, authentication, dashboards, and management
    storage/                 Contact submissions (must not be public)
    uploads/                 Event posters (must not be executable)
```
