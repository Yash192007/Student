Project Name:Student Portal

Pages
1.home
2.about us
3.contact us
4.login
5.dashboard
6.result
7.notices
8.fees details
9.cources
10.attendance

Ussers:
Student
Teachers
Admin

## StudentHub PHP portal

The main site remains available at `website/index.html`. Its registration, login, and role dashboards use the PHP application in `website/studenthub`; serve the `website` directory through PHP/Apache instead of a static-only server.

### Local setup (XAMPP on Windows)

1. Start Apache and MySQL in XAMPP.
2. Import `website/studenthub/schema.sql` into MySQL. For a database that was created before faculty accounts were added, import `website/studenthub/migration-faculty.sql` once instead.
3. Configure PHP with `mysqli`, `pdo_mysql`, and `fileinfo` enabled. The local development defaults connect to `127.0.0.1:3306` as `root` with an empty password; change these through environment variables if your local MySQL differs.
4. In PowerShell, from the project directory, set `STUDENTHUB_DB_HOST`, `STUDENTHUB_DB_NAME`, `STUDENTHUB_DB_USER`, `STUDENTHUB_DB_PASSWORD`, and optionally `STUDENTHUB_DB_PORT` for your PHP/Apache process. Do not commit credentials.
5. Create the first administrator from a terminal:

   ```powershell
   $env:STUDENTHUB_DB_HOST = "127.0.0.1"
   $env:STUDENTHUB_DB_NAME = "studenthub"
   $env:STUDENTHUB_DB_USER = "root"
   $env:STUDENTHUB_DB_PASSWORD = "your-local-database-password"
   $env:STUDENTHUB_ADMIN_PASSWORD = "ChangeMe!2026-UseYourOwn"
   C:\xampp\php\php.exe website\studenthub\create-admin.php
   Remove-Item Env:\STUDENTHUB_ADMIN_PASSWORD
   ```

   Enter the administrator's name and email when prompted. On shared or production systems, use a dedicated least-privilege database user and a password manager/secret store.
6. Open `http://localhost/<your-site-path>/studenthub/login.php`. Admins can add faculty accounts from **Faculty accounts**; students register through **Register**. Role selection on the landing page is checked against the account's stored role.

The PHP built-in server can be used for local development only:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8001 -t website
```

### Database relationships

- Each `users` record can have one `students` or one `faculty` profile, selected by its role.
- A user can create many `events`; removing that user leaves the events in place and clears `created_by`.
- Students and events have a many-to-many relationship through `registrations`, which prevents duplicate registration for the same event.
- Remember-me tokens and audit-log entries reference their user, with foreign keys that remove tokens or retain audit history safely when accounts are deleted.
- Email addresses and profile ownership are unique; tables use InnoDB, foreign keys, UTF-8, and prepared statements.

### Deployment checklist

- Set `STUDENTHUB_ENV=production` and provide non-empty database host, database name, database user, and database password via the hosting platform's secret configuration. Production mode refuses to connect with the local root/empty-password defaults.
- Serve `website` over HTTPS with a supported PHP 8.1+ runtime and MySQL/MariaDB. Configure Apache/PHP-FPM to pass the database environment variables and HTTPS state to PHP.
- Disable PHP `display_errors` and enable `log_errors`; send application/server logs to a monitored location that is not web-accessible.
- Import the schema (or the one-time faculty migration), create the first admin from the deployment CLI, and then create faculty accounts inside the admin dashboard. Never expose the CLI bootstrap as a web endpoint.
- Allow the PHP process to write only to `website/studenthub/storage` and `website/studenthub/uploads`. Keep those directories non-executable and inaccessible for directory listing; the included Apache rules deny direct access to contact submissions and PHP uploads.
- Back up the database and contact-submission file, configure log rotation/monitoring, and use your hosting provider's TLS, firewall, and database backup controls before opening the site to the public.
- Use an Apache virtual host or equivalent rewrite configuration to route HTTPS requests to the `website` document root. The local PHP built-in server is for development, not production.
