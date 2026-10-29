<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
$admin = require_role('admin');
$db = portal_db();
$courses = ['BCA', 'BBA', 'BSc', 'BCom', 'BA'];
$genders = ['Male', 'Female', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $transactionStarted = false;
    try {
        if ($action === 'delete' && $id && $id > 0) {
            $db->begin_transaction();
            $transactionStarted = true;
            $lookup = $db->prepare('SELECT user_id FROM students WHERE id = ?');
            $lookup->bind_param('i', $id);
            $lookup->execute();
            $record = $lookup->get_result()->fetch_assoc();
            if (!$record) {
                $db->rollback();
                set_flash('error', 'Student record was not found.');
                redirect('students.php');
            }
            $userId = (int) $record['user_id'];
            $delete = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
            $delete->bind_param('i', $userId);
            $delete->execute();
            if ($delete->affected_rows) {
                audit_log((int) $admin['id'], 'student_deleted', 'student', $id);
            }
            $db->commit();
            $transactionStarted = false;
            set_flash($delete->affected_rows ? 'success' : 'error', $delete->affected_rows ? 'Student removed.' : 'Student record was not found.');
            redirect('students.php');
        }

        $name = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
        $email = strtolower(trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : ''));
        $mobile = trim(is_string($_POST['mobile'] ?? null) ? $_POST['mobile'] : '');
        $course = is_string($_POST['course'] ?? null) ? $_POST['course'] : '';
        $yearValue = is_string($_POST['year'] ?? null) ? $_POST['year'] : '';
        $gender = is_string($_POST['gender'] ?? null) ? $_POST['gender'] : '';
        $initialPassword = $_POST['initial_password'] ?? '';
        if ($action !== 'create' && $action !== 'update') {
            throw new InvalidArgumentException('Choose a valid student action.');
        }
        if ($name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254
            || !preg_match('/^[6-9][0-9]{9}$/', $mobile) || !in_array($course, $courses, true)
            || !in_array($yearValue, ['1', '2', '3', '4'], true) || !in_array($gender, $genders, true)
            || ($action === 'update' && (!$id || $id < 1))
            || ($action === 'create' && (!is_string($initialPassword) || !password_meets_policy($initialPassword, 12)))) {
            throw new InvalidArgumentException('Check the profile fields and use a 12-character initial password with uppercase, lowercase, a number, and a special character.');
        }

        $year = (int) $yearValue;
        $db->begin_transaction();
        $transactionStarted = true;
        if ($action === 'create') {
            $passwordHash = password_hash($initialPassword, PASSWORD_DEFAULT);
            $userInsert = $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'student')");
            $userInsert->bind_param('sss', $name, $email, $passwordHash);
            $userInsert->execute();
            $userId = (int) $db->insert_id;
            $studentInsert = $db->prepare('INSERT INTO students (user_id, mobile, course, year, gender) VALUES (?, ?, ?, ?, ?)');
            $studentInsert->bind_param('issis', $userId, $mobile, $course, $year, $gender);
            $studentInsert->execute();
            $studentId = (int) $db->insert_id;
            audit_log((int) $admin['id'], 'student_created', 'student', $studentId);
            $db->commit();
            $transactionStarted = false;
            set_flash('success', 'Student added. Share the initial password with them securely.');
        } else {
            $lookup = $db->prepare('SELECT user_id FROM students WHERE id = ?');
            $lookup->bind_param('i', $id);
            $lookup->execute();
            $record = $lookup->get_result()->fetch_assoc();
            if (!$record) {
                $db->rollback();
                set_flash('error', 'Student record was not found.');
                redirect('students.php');
            }
            $userId = (int) $record['user_id'];
            $userUpdate = $db->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
            $userUpdate->bind_param('ssi', $name, $email, $userId);
            $userUpdate->execute();
            $studentUpdate = $db->prepare('UPDATE students SET mobile = ?, course = ?, year = ?, gender = ? WHERE id = ?');
            $studentUpdate->bind_param('ssisi', $mobile, $course, $year, $gender, $id);
            $studentUpdate->execute();
            audit_log((int) $admin['id'], 'student_updated', 'student', $id);
            $db->commit();
            $transactionStarted = false;
            set_flash('success', 'Student details updated.');
        }
        redirect('students.php');
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
        error_log('StudentHub student management failed: ' . $error->getMessage());
        http_response_code($error->getCode() === 1062 ? 409 : 500);
        $formError = $error->getCode() === 1062 ? 'That email address is already in use.' : 'The student record could not be saved.';
    }
}

$search = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
$courseFilter = is_string($_GET['course'] ?? null) ? $_GET['course'] : '';
$where = [];
$params = [];
$types = '';
if ($search !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR s.course LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term);
    $types .= 'sss';
}
if ($courseFilter !== '' && in_array($courseFilter, $courses, true)) {
    $where[] = 's.course = ?';
    $params[] = $courseFilter;
    $types .= 's';
}
$sql = 'SELECT s.id, u.name, u.email, s.mobile, s.course, s.year, s.gender FROM students s JOIN users u ON u.id = s.user_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY u.name';
$list = $db->prepare($sql);
if ($params) {
    bind_params($list, $types, $params);
}
$list->execute();
$students = $list->get_result()->fetch_all(MYSQLI_ASSOC);

$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
$editing = null;
if ($editId) {
    $edit = $db->prepare('SELECT s.id, u.name, u.email, s.mobile, s.course, s.year, s.gender FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = ?');
    $edit->bind_param('i', $editId);
    $edit->execute();
    $editing = $edit->get_result()->fetch_assoc() ?: null;
}

page_header('Manage students');
?>
<section class="portal-panel">
  <h1>Student management</h1>
  <p>Add, edit, search, filter, or remove student records.</p>
  <?php if (isset($formError)): ?><p class="portal-message error" role="alert"><?= h($formError) ?></p><?php endif; ?>
  <form class="portal-form-grid" method="post" action="students.php">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= h($editing['id']) ?>"><?php endif; ?>
    <div class="portal-field"><label for="student-name">Name</label><input id="student-name" name="name" maxlength="120" required value="<?= h($editing['name'] ?? '') ?>"></div>
    <div class="portal-field"><label for="student-email">Email</label><input id="student-email" name="email" type="email" maxlength="254" required value="<?= h($editing['email'] ?? '') ?>"></div>
    <div class="portal-field"><label for="student-mobile">Mobile</label><input id="student-mobile" name="mobile" inputmode="numeric" pattern="[6-9][0-9]{9}" maxlength="10" required value="<?= h($editing['mobile'] ?? '') ?>"></div>
    <div class="portal-field"><label for="student-course">Course</label><select id="student-course" name="course" required><option value="">Choose course</option><?php foreach ($courses as $course): ?><option value="<?= h($course) ?>" <?= ($editing['course'] ?? '') === $course ? 'selected' : '' ?>><?= h($course) ?></option><?php endforeach; ?></select></div>
    <div class="portal-field"><label for="student-year">Year</label><select id="student-year" name="year" required><option value="">Choose year</option><?php for ($year = 1; $year <= 4; $year++): ?><option value="<?= $year ?>" <?= (int) ($editing['year'] ?? 0) === $year ? 'selected' : '' ?>>Year <?= $year ?></option><?php endfor; ?></select></div>
    <div class="portal-field"><label for="student-gender">Gender</label><select id="student-gender" name="gender" required><option value="">Choose option</option><?php foreach ($genders as $gender): ?><option value="<?= h($gender) ?>" <?= ($editing['gender'] ?? '') === $gender ? 'selected' : '' ?>><?= h($gender) ?></option><?php endforeach; ?></select></div>
    <?php if (!$editing): ?><div class="portal-field full-width"><label for="initial-password">Initial password</label><input id="initial-password" name="initial_password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required><span class="portal-muted">12-72 characters with uppercase, lowercase, a number, and a special character.</span></div><?php endif; ?>
    <div class="portal-actions full-width"><button class="button button-primary" type="submit"><?= $editing ? 'Save changes' : 'Add student' ?></button><?php if ($editing): ?><a href="students.php">Cancel edit</a><?php endif; ?></div>
  </form>
</section>
<section class="portal-panel">
  <h2>Find students</h2>
  <form class="portal-form-grid" method="get" action="students.php">
    <div class="portal-field"><label for="student-search">Name, email, or course</label><input id="student-search" name="q" value="<?= h($search) ?>"></div>
    <div class="portal-field"><label for="course-filter">Filter course</label><select id="course-filter" name="course"><option value="">All courses</option><?php foreach ($courses as $course): ?><option value="<?= h($course) ?>" <?= $courseFilter === $course ? 'selected' : '' ?>><?= h($course) ?></option><?php endforeach; ?></select></div>
    <div class="portal-actions"><button class="button button-secondary" type="submit">Search</button></div>
  </form>
  <div class="portal-table-wrap"><table class="portal-table">
    <thead><tr><th>Name</th><th>Email</th><th>Mobile</th><th>Course</th><th>Year</th><th>Gender</th><th>Actions</th></tr></thead>
    <tbody><?php foreach ($students as $student): ?><tr>
      <td><?= h($student['name']) ?></td><td><?= h($student['email']) ?></td><td><?= h($student['mobile']) ?></td><td><?= h($student['course']) ?></td><td><?= h($student['year']) ?></td><td><?= h($student['gender']) ?></td>
      <td><a href="students.php?edit=<?= h($student['id']) ?>">Edit</a>
        <form class="portal-inline" method="post" action="students.php" onsubmit="return confirm('Delete this student and their event registrations?');">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($student['id']) ?>">
          <button type="submit">Delete</button>
        </form>
      </td>
    </tr><?php endforeach; ?><?php if (!$students): ?><tr><td colspan="7">No students match those filters.</td></tr><?php endif; ?></tbody>
  </table></div>
</section>
<?php page_footer(); ?>
