<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
portal_start_session();

$errorMessage = '';
$requestedRole = $_GET['role'] ?? '';
$roleLabels = ['student' => 'Student', 'faculty' => 'Faculty', 'admin' => 'Admin'];
if ($requestedRole !== '' && !array_key_exists($requestedRole, $roleLabels)) {
    http_response_code(400);
    $requestedRole = '';
    $errorMessage = 'Select a valid account type.';
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $errorMessage === '') {
    $signedInUser = current_user();
    if ($signedInUser) {
        redirect(dashboard_for_role($signedInUser['role']));
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['role']) && is_string($_POST['role'])) {
    $requestedRole = $_POST['role'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    if ($requestedRole !== '' && !array_key_exists($requestedRole, $roleLabels)) {
        http_response_code(400);
        $errorMessage = 'Select a valid account type.';
    }
    if ($errorMessage === '' && is_string($email) && is_string($password) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            $normalizedEmail = strtolower(trim($email));
            $statement = portal_db()->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
            $statement->bind_param('s', $normalizedEmail);
            $statement->execute();
            $user = $statement->get_result()->fetch_assoc();

            if ($user && password_verify($password, $user['password_hash'])
                && ($requestedRole === '' || $user['role'] === $requestedRole)) {
                audit_log((int) $user['id'], 'login', 'user', (int) $user['id']);
                login_user($user, isset($_POST['remember']) && $_POST['remember'] === '1');
                redirect(dashboard_for_role($user['role']));
            }
        } catch (mysqli_sql_exception $error) {
            error_log('StudentHub login failed: ' . $error->getMessage());
            http_response_code(503);
            $errorMessage = 'Sign in is temporarily unavailable. Check the database setup and try again.';
        }
    }

    if ($errorMessage === '') {
        http_response_code(401);
        $errorMessage = $requestedRole !== '' && isset($user) && $user
            ? 'This account does not have the selected role. Choose the correct sign-in option.'
            : 'The email or password was not recognized.';
    }
}

page_header('Sign in');
?>
<section class="portal-panel">
  <h1>Sign in<?= $requestedRole !== '' && isset($roleLabels[$requestedRole]) ? ' as ' . h($roleLabels[$requestedRole]) : '' ?> to StudentHub</h1>
  <?php if (isset($_GET['logged_out'])): ?><p class="portal-message" role="status">You have been securely logged out.</p><?php endif; ?>
  <?php if (isset($_GET['expired'])): ?><p class="portal-message error" role="alert">Your session expired. Please sign in again.</p><?php endif; ?>
  <?php if ($errorMessage !== ''): ?><p class="portal-message error" role="alert"><?= h($errorMessage) ?></p><?php endif; ?>
  <form class="portal-form" method="post" action="login.php">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <?php if ($requestedRole !== '' && isset($roleLabels[$requestedRole])): ?><input type="hidden" name="role" value="<?= h($requestedRole) ?>"><?php endif; ?>
    <div class="portal-field"><label for="email">Email</label><input id="email" name="email" type="email" autocomplete="username" required></div>
    <div class="portal-field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
    <label><input type="checkbox" name="remember" value="1"> Remember me on this device for 30 days</label>
    <button class="button button-primary" type="submit">Sign in</button>
    <a href="register.php">Create a student account</a>
  </form>
</section>
<?php page_footer(); ?>
