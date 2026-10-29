<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
$admin = require_role('admin');
$db = portal_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $transactionStarted = false;
    try {
        if ($action === 'delete' && $id && $id > 0) {
            $db->begin_transaction();
            $transactionStarted = true;
            $lookup = $db->prepare('SELECT user_id FROM faculty WHERE id = ?');
            $lookup->bind_param('i', $id);
            $lookup->execute();
            $record = $lookup->get_result()->fetch_assoc();
            if (!$record) {
                $db->rollback();
                set_flash('error', 'Faculty account was not found.');
                redirect('faculty.php');
            }
            $userId = (int) $record['user_id'];
            $remove = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'faculty'");
            $remove->bind_param('i', $userId);
            $remove->execute();
            if ($remove->affected_rows) {
                audit_log((int) $admin['id'], 'faculty_deleted', 'faculty', $id);
            }
            $db->commit();
            $transactionStarted = false;
            set_flash($remove->affected_rows ? 'success' : 'error', $remove->affected_rows ? 'Faculty account removed.' : 'Faculty account was not found.');
            redirect('faculty.php');
        }

        $name = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
        $email = strtolower(trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : ''));
        $department = trim(is_string($_POST['department'] ?? null) ? $_POST['department'] : '');
        $title = trim(is_string($_POST['title'] ?? null) ? $_POST['title'] : '');
        $password = $_POST['password'] ?? '';
        if (!in_array($action, ['create', 'update'], true) || $name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($email) > 254 || $department === '' || strlen($department) > 120 || $title === ''
            || strlen($title) > 80 || !is_string($password)
            || ($action === 'create' && !password_meets_policy($password, 12))
            || ($action === 'update' && (!$id || $id < 1 || ($password !== '' && !password_meets_policy($password, 12))))) {
            throw new InvalidArgumentException('Enter valid profile details and use a 12-character initial or reset password with uppercase, lowercase, a number, and a special character.');
        }

        $db->begin_transaction();
        $transactionStarted = true;
        if ($action === 'create') {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $userInsert = $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'faculty')");
            $userInsert->bind_param('sss', $name, $email, $passwordHash);
            $userInsert->execute();
            $userId = (int) $db->insert_id;
            $profileInsert = $db->prepare('INSERT INTO faculty (user_id, department, title) VALUES (?, ?, ?)');
            $profileInsert->bind_param('iss', $userId, $department, $title);
            $profileInsert->execute();
            $facultyId = (int) $db->insert_id;
            audit_log((int) $admin['id'], 'faculty_created', 'faculty', $facultyId);
        } else {
            $lookup = $db->prepare('SELECT user_id FROM faculty WHERE id = ?');
            $lookup->bind_param('i', $id);
            $lookup->execute();
            $record = $lookup->get_result()->fetch_assoc();
            if (!$record) {
                $db->rollback();
                set_flash('error', 'Faculty account was not found.');
                redirect('faculty.php');
            }
            $userId = (int) $record['user_id'];
            $userUpdate = $db->prepare("UPDATE users SET name = ?, email = ? WHERE id = ? AND role = 'faculty'");
            $userUpdate->bind_param('ssi', $name, $email, $userId);
            $userUpdate->execute();
            if ($password !== '') {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $passwordUpdate = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role = 'faculty'");
                $passwordUpdate->bind_param('si', $passwordHash, $userId);
                $passwordUpdate->execute();
                $tokens = $db->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
                $tokens->bind_param('i', $userId);
                $tokens->execute();
            }
            $profileUpdate = $db->prepare('UPDATE faculty SET department = ?, title = ? WHERE id = ?');
            $profileUpdate->bind_param('ssi', $department, $title, $id);
            $profileUpdate->execute();
            $facultyId = (int) $id;
            audit_log((int) $admin['id'], $password !== '' ? 'faculty_updated_password' : 'faculty_updated', 'faculty', $facultyId);
        }
        $db->commit();
        $transactionStarted = false;
        set_flash('success', $action === 'create' ? 'Faculty account created. Share its initial password securely.' : 'Faculty account updated.');
        redirect('faculty.php');
    } catch (InvalidArgumentException $error) {
        if ($transactionStarted) {
            $db->rollback();
        }
        http_response_code(422);
        $formError = $error->getMessage();
    } catch (mysqli_sql_exception $error) {
        if ($transactionStarted) {
            $db->rollback();
        }
        error_log('StudentHub faculty management failed: ' . $error->getMessage());
        http_response_code($error->getCode() === 1062 ? 409 : 500);
        $formError = $error->getCode() === 1062 ? 'That email address already has an account.' : 'Faculty account could not be saved.';
    }
}

$faculty = $db->query(
    'SELECT f.id, u.name, u.email, f.department, f.title, f.created_at
     FROM faculty f JOIN users u ON u.id = f.user_id
     ORDER BY u.name'
)->fetch_all(MYSQLI_ASSOC);
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
$editing = null;
if ($editId) {
    $edit = $db->prepare(
        'SELECT f.id, u.name, u.email, f.department, f.title
         FROM faculty f JOIN users u ON u.id = f.user_id WHERE f.id = ?'
    );
    $edit->bind_param('i', $editId);
    $edit->execute();
    $editing = $edit->get_result()->fetch_assoc() ?: null;
}

page_header('Faculty accounts');
?>
<section class="portal-panel">
  <h1>Faculty accounts</h1>
  <p>Create or update faculty logins. Faculty can sign in through the Faculty option on the home page and access their protected dashboard.</p>
  <?php if (isset($formError)): ?><p class="portal-message error" role="alert"><?= h($formError) ?></p><?php endif; ?>
  <form class="portal-form-grid" method="post" action="faculty.php">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= h($editing['id']) ?>"><?php endif; ?>
    <div class="portal-field"><label for="faculty-name">Full name</label><input id="faculty-name" name="name" maxlength="120" required value="<?= h($editing['name'] ?? '') ?>"></div>
    <div class="portal-field"><label for="faculty-email">Email</label><input id="faculty-email" name="email" type="email" maxlength="254" required value="<?= h($editing['email'] ?? '') ?>"></div>
    <div class="portal-field"><label for="faculty-department">Department</label><input id="faculty-department" name="department" maxlength="120" required value="<?= h($editing['department'] ?? '') ?>"></div>
    <div class="portal-field"><label for="faculty-title">Title</label><input id="faculty-title" name="title" maxlength="80" placeholder="Professor, Lecturer..." required value="<?= h($editing['title'] ?? '') ?>"></div>
    <div class="portal-field"><label for="faculty-password"><?= $editing ? 'Reset password (leave blank to keep current password)' : 'Initial password' ?></label><input id="faculty-password" name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" <?= $editing ? '' : 'required' ?>><span class="portal-muted">12-72 characters with uppercase, lowercase, a number, and a special character.</span></div>
    <div class="portal-actions"><button class="button button-primary" type="submit"><?= $editing ? 'Save faculty account' : 'Create faculty account' ?></button><?php if ($editing): ?><a href="faculty.php">Cancel edit</a><?php endif; ?></div>
  </form>
</section>
<section class="portal-panel">
  <h2>Current faculty</h2>
  <div class="portal-table-wrap"><table class="portal-table">
    <thead><tr><th>Name</th><th>Email</th><th>Department</th><th>Title</th><th>Added</th><th>Actions</th></tr></thead>
    <tbody><?php foreach ($faculty as $member): ?><tr>
      <td><?= h($member['name']) ?></td><td><?= h($member['email']) ?></td><td><?= h($member['department']) ?></td><td><?= h($member['title']) ?></td><td><?= h($member['created_at']) ?></td>
      <td><a href="faculty.php?edit=<?= h($member['id']) ?>">Edit</a>
        <form method="post" action="faculty.php" onsubmit="return confirm('Remove this faculty account?');">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($member['id']) ?>">
        <button type="submit">Remove</button>
      </form></td>
    </tr><?php endforeach; ?>
    <?php if (!$faculty): ?><tr><td colspan="6">No faculty accounts have been added.</td></tr><?php endif; ?></tbody>
  </table></div>
</section>
<?php page_footer(); ?>
