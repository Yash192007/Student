<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
portal_start_session();

$values = [
    'name' => '',
    'email' => '',
    'mobile' => '',
    'course' => '',
    'year' => '',
    'gender' => '',
];
$errors = [];
$message = '';
$courses = ['BCA', 'BBA', 'BSc', 'BCom', 'BA'];
$genders = ['Male', 'Female', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($values as $key => $_) {
        $posted = $_POST[$key] ?? '';
        $values[$key] = is_string($posted) ? trim($posted) : '';
    }

    $password = $_POST['password'] ?? '';
    $confirmation = $_POST['confirm_password'] ?? '';
    $termsAccepted = ($_POST['terms'] ?? '') === 'accepted';

    if ($values['name'] === '' || !preg_match("/^[A-Za-z]+(?:[ '-][A-Za-z]+)*$/", $values['name']) || strlen($values['name']) > 120) {
        $errors['name'] = 'Enter a name using letters, spaces, apostrophes, or hyphens (up to 120 characters).';
    }
    $values['email'] = strtolower($values['email']);
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 254) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (!preg_match('/^[6-9][0-9]{9}$/', $values['mobile'])) {
        $errors['mobile'] = 'Enter a valid 10-digit Indian mobile number.';
    }
    if (!in_array($values['course'], $courses, true)) {
        $errors['course'] = 'Choose a course from the list.';
    }
    if (!in_array($values['year'], ['1', '2', '3', '4'], true)) {
        $errors['year'] = 'Choose a valid year.';
    }
    if (!in_array($values['gender'], $genders, true)) {
        $errors['gender'] = 'Choose a gender option.';
    }
    if (!is_string($password) || !password_meets_policy($password)) {
        $errors['password'] = 'Use 8-72 characters with uppercase, lowercase, a number, and a special character.';
    }
    if (!is_string($confirmation) || !is_string($password) || !hash_equals($password, $confirmation)) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }
    if (!$termsAccepted) {
        $errors['terms'] = 'You must agree to the Terms and Conditions.';
    }

    if (!$errors) {
        try {
            $db = portal_db();
            $db->begin_transaction();
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $statement = $db->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
            $statement->bind_param('sss', $values['name'], $values['email'], $passwordHash);
            $statement->execute();
            $userId = (int) $db->insert_id;

            $year = (int) $values['year'];
            $student = $db->prepare('INSERT INTO students (user_id, mobile, course, year, gender) VALUES (?, ?, ?, ?, ?)');
            $student->bind_param('issis', $userId, $values['mobile'], $values['course'], $year, $values['gender']);
            $student->execute();
            $studentId = (int) $db->insert_id;
            audit_log($userId, 'student_registered', 'student', $studentId);
            $db->commit();
            set_flash('success', 'Your account was created. You can now sign in.');
            redirect('login.php');
        } catch (mysqli_sql_exception $error) {
            if (isset($db)) {
                $db->rollback();
            }
            error_log('StudentHub registration failed: ' . $error->getMessage());
            if ($error->getCode() === 1062) {
                $errors['email'] = 'An account with this email already exists.';
            } else {
                http_response_code(503);
                $message = 'Registration could not be completed. Check the database setup and try again.';
            }
        }
    }
}

if ($errors) {
    http_response_code(422);
}

function field_error(array $errors, string $key): void
{
    if (isset($errors[$key])) {
        echo '<span class="portal-message error" role="alert">' . h($errors[$key]) . '</span>';
    }
}

page_header('Student registration');
?>
<section class="portal-panel">
  <h1>Create your StudentHub account</h1>
  <p>Register as a student. Your password is stored as a secure hash.</p>
  <?php if ($message !== ''): ?><p class="portal-message error" role="alert"><?= h($message) ?></p><?php endif; ?>
  <form class="portal-form-grid" method="post" action="register.php">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <div class="portal-field"><label for="name">Full name</label><input id="name" name="name" maxlength="120" autocomplete="name" required value="<?= h($values['name']) ?>"><?php field_error($errors, 'name'); ?></div>
    <div class="portal-field"><label for="email">Email</label><input id="email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= h($values['email']) ?>"><?php field_error($errors, 'email'); ?></div>
    <div class="portal-field"><label for="mobile">Indian mobile number</label><input id="mobile" name="mobile" inputmode="numeric" pattern="[6-9][0-9]{9}" maxlength="10" required value="<?= h($values['mobile']) ?>"><?php field_error($errors, 'mobile'); ?></div>
    <div class="portal-field"><label for="course">Course</label><select id="course" name="course" required><option value="">Choose course</option><?php foreach ($courses as $course): ?><option value="<?= h($course) ?>" <?= $values['course'] === $course ? 'selected' : '' ?>><?= h($course) ?></option><?php endforeach; ?></select><?php field_error($errors, 'course'); ?></div>
    <div class="portal-field"><label for="year">Year</label><select id="year" name="year" required><option value="">Choose year</option><?php for ($year = 1; $year <= 4; $year++): ?><option value="<?= $year ?>" <?= $values['year'] === (string) $year ? 'selected' : '' ?>>Year <?= $year ?></option><?php endfor; ?></select><?php field_error($errors, 'year'); ?></div>
    <div class="portal-field"><label for="gender">Gender</label><select id="gender" name="gender" required><option value="">Choose option</option><?php foreach ($genders as $gender): ?><option value="<?= h($gender) ?>" <?= $values['gender'] === $gender ? 'selected' : '' ?>><?= h($gender) ?></option><?php endforeach; ?></select><?php field_error($errors, 'gender'); ?></div>
    <div class="portal-field"><label for="password">Password</label><input id="password" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required><?php field_error($errors, 'password'); ?><span class="portal-muted">8-72 characters with uppercase, lowercase, number, and special character.</span></div>
    <div class="portal-field"><label for="confirm-password">Confirm password</label><input id="confirm-password" name="confirm_password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required><?php field_error($errors, 'confirm_password'); ?></div>
    <div class="portal-field full-width"><label><input type="checkbox" name="terms" value="accepted" required <?= isset($_POST['terms']) ? 'checked' : '' ?>> I agree to the Terms and Conditions.</label><?php field_error($errors, 'terms'); ?></div>
    <div class="portal-actions full-width"><button class="button button-primary" type="submit">Create account</button><a href="login.php">Already registered? Sign in</a></div>
  </form>
</section>
<?php page_footer(); ?>
