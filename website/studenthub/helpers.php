<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const PORTAL_SESSION_TIMEOUT = 1800;
const PORTAL_REMEMBER_COOKIE = 'studenthub_remember';

function h(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function password_meets_policy(string $password, int $minimumLength = 8): bool
{
    $pattern = '/^(?=.{'.$minimumLength.',72}$)(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).*$/';
    return strlen($password) <= 72 && preg_match($pattern, $password) === 1;
}

function csrf_token(): string
{
    portal_start_session();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    portal_start_session();
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        exit('The form expired. Return to the previous page and try again.');
    }
}

function bind_params(mysqli_stmt $statement, string $types, array &$params): void
{
    $arguments = [$types];
    foreach ($params as &$value) {
        $arguments[] = &$value;
    }
    call_user_func_array([$statement, 'bind_param'], $arguments);
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function portal_url(string $path = ''): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('#^(.*?)/studenthub(?:/|$)#', $script, $matches)) {
        $siteRoot = rtrim($matches[1], '/');
    } else {
        $siteRoot = rtrim(portal_config('STUDENTHUB_PUBLIC_BASE', ''), '/');
    }

    return $siteRoot . '/studenthub/' . ltrim($path, '/');
}

function dashboard_for_role(string $role): string
{
    return match ($role) {
        'admin' => 'admin-dashboard.php',
        'faculty' => 'faculty-dashboard.php',
        default => 'student-dashboard.php',
    };
}

function set_flash(string $type, string $message): void
{
    portal_start_session();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    portal_start_session();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function audit_log(?int $actorId, string $action, string $entityType, ?int $entityId = null, array $details = []): void
{
    $statement = portal_db()->prepare(
        'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, details_json) VALUES (?, ?, ?, ?, ?)'
    );
    $detailsJson = json_encode($details, JSON_THROW_ON_ERROR);
    $statement->bind_param('issis', $actorId, $action, $entityType, $entityId, $detailsJson);
    $statement->execute();
}

function current_user(bool $redirectOnTimeout = true): ?array
{
    portal_start_session();
    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
        if ($lastActivity > 0 && time() - $lastActivity > PORTAL_SESSION_TIMEOUT) {
            logout_user(false);
            if ($redirectOnTimeout) {
                redirect(portal_url('login.php?expired=1'));
            }
            return null;
        }
        $_SESSION['last_activity'] = time();
        return $_SESSION['user'];
    }

    $cookie = $_COOKIE[PORTAL_REMEMBER_COOKIE] ?? '';
    if (is_string($cookie) && preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $cookie, $matches)) {
        $db = portal_db();
        $statement = $db->prepare(
            'SELECT rt.id AS remember_id, u.id AS user_id, u.name, u.email, u.role FROM remember_tokens rt JOIN users u ON u.id = rt.user_id WHERE rt.selector = ? AND rt.token_hash = ? AND rt.expires_at > NOW()'
        );
        $tokenHash = hash('sha256', $matches[2]);
        $statement->bind_param('ss', $matches[1], $tokenHash);
        $statement->execute();
        $user = $statement->get_result()->fetch_assoc();

        if ($user) {
            $selector = bin2hex(random_bytes(12));
            $token = bin2hex(random_bytes(32));
            $newHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);
            $update = $db->prepare('UPDATE remember_tokens SET selector = ?, token_hash = ?, expires_at = ? WHERE id = ?');
            $rememberId = (int) $user['remember_id'];
            $update->bind_param('sssi', $selector, $newHash, $expiresAt, $rememberId);
            $update->execute();
            set_remember_cookie($selector, $token, time() + 60 * 60 * 24 * 30);
            $userId = (int) $user['user_id'];
            $_SESSION['user'] = [
                'id' => $userId,
                'name' => (string) $user['name'],
                'email' => (string) $user['email'],
                'role' => (string) $user['role'],
            ];
            $_SESSION['last_activity'] = time();
            session_regenerate_id(true);
            return $_SESSION['user'];
        }
        clear_remember_cookie();
    }

    return null;
}

function set_remember_cookie(string $selector, string $token, int $expires): void
{
    setcookie(PORTAL_REMEMBER_COOKIE, $selector . ':' . $token, [
        'expires' => $expires,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clear_remember_cookie(): void
{
    setcookie(PORTAL_REMEMBER_COOKIE, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function login_user(array $user, bool $remember): void
{
    portal_start_session();
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => (string) $user['name'],
        'email' => (string) $user['email'],
        'role' => (string) $user['role'],
    ];
    $_SESSION['last_activity'] = time();
    unset($_SESSION['csrf_token']);

    if ($remember) {
        $selector = bin2hex(random_bytes(12));
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);
        $statement = portal_db()->prepare('INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)');
        $userId = (int) $user['id'];
        $statement->bind_param('isss', $userId, $selector, $hash, $expiresAt);
        $statement->execute();
        set_remember_cookie($selector, $token, time() + 60 * 60 * 24 * 30);
    }
}

function logout_user(bool $destroySession = true): void
{
    portal_start_session();
    $userId = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;
    if (isset($_SESSION['user']['id'])) {
        audit_log($userId, 'logout', 'user', $userId);
    }
    $cookie = $_COOKIE[PORTAL_REMEMBER_COOKIE] ?? '';
    if (is_string($cookie) && preg_match('/^([a-f0-9]{24}):/', $cookie, $matches)) {
        $statement = portal_db()->prepare('DELETE FROM remember_tokens WHERE selector = ?');
        $statement->bind_param('s', $matches[1]);
        $statement->execute();
    } elseif ($userId !== null) {
        $statement = portal_db()->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
        $statement->bind_param('i', $userId);
        $statement->execute();
    }
    clear_remember_cookie();

    if ($destroySession) {
        $_SESSION = [];
        $cookieParams = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $cookieParams['path'],
            'domain' => $cookieParams['domain'],
            'secure' => $cookieParams['secure'],
            'httponly' => $cookieParams['httponly'],
            'samesite' => $cookieParams['samesite'] ?? 'Lax',
        ]);
        session_destroy();
    } else {
        $_SESSION = [];
        session_regenerate_id(true);
    }
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        redirect(portal_url('login.php'));
    }
    return $user;
}

function require_role(string $role): array
{
    $user = require_auth();
    if ($user['role'] !== $role) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
    return $user;
}

function page_header(string $title): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store, private');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000');
    }
    $user = current_user();
    $flash = take_flash();
    ?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($title) ?> | Campus Portal</title>
  <link rel="stylesheet" href="../styles.css">
  <link rel="stylesheet" href="portal.css">
</head>
<body>
  <div class="page-shell">
    <header class="site-header">
      <a class="brand" href="../index.html">Campus Portal</a>
      <button class="menu-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="site-nav"><span></span><span></span><span></span></button>
      <nav class="site-nav" id="site-nav" data-server-nav="true">
        <a href="../index.html">Home</a>
        <?php if ($user): ?>
          <a href="<?= h(dashboard_for_role($user['role'])) ?>">Dashboard</a>
          <a href="../academic.html">Academic</a><a href="events.php">Events</a>
          <a href="contact.php">Contact</a><a href="../practical 6/practical6.html">Data explorer</a><a href="../photo-slider.html">Gallery</a>
          <?php if ($user['role'] === 'admin'): ?><a href="students.php">Students</a><a href="faculty.php">Faculty accounts</a><?php endif; ?>
          <form class="nav-logout" method="post" action="<?= h(portal_url('logout.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <button type="submit">Log out</button>
          </form>
        <?php else: ?>
          <a href="../academic.html">Academic</a><a href="events.php">Events</a>
          <a href="contact.php">Contact</a><a href="../practical 6/practical6.html">Data explorer</a><a href="../photo-slider.html">Gallery</a>
          <a href="login.php">Login</a><a href="register.php">Register</a>
        <?php endif; ?>
        <button class="theme-toggle" type="button" data-theme-toggle aria-label="Dark theme" aria-pressed="false">
          <span class="theme-icon" aria-hidden="true">&#9790;</span><span>Dark theme</span>
        </button>
      </nav>
    </header>
    <main class="portal-main">
      <?php if ($flash): ?>
        <p class="portal-message <?= h($flash['type']) ?>" role="status"><?= h($flash['message']) ?></p>
      <?php endif; ?>
<?php
}

function page_footer(): void
{
    ?>
    </main>
    <footer class="page-footer">Campus Portal &copy; 2026 - StudentHub</footer>
  </div>
  <script src="../script.js"></script>
</body>
</html>
<?php
}
