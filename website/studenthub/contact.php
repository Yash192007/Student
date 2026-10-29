<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
portal_start_session();

$name = '';
$email = '';
$message = '';
$notice = '';
$noticeType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $nameInput = $_POST['name'] ?? '';
    $emailInput = $_POST['email'] ?? '';
    $messageInput = $_POST['message'] ?? '';
    $name = is_string($nameInput) ? trim($nameInput) : '';
    $email = is_string($emailInput) ? strtolower(trim($emailInput)) : '';
    $message = is_string($messageInput) ? trim($messageInput) : '';

    if ($name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || $message === '' || strlen($message) > 5000) {
        http_response_code(422);
        $notice = 'Enter a name, valid email address, and a message of up to 5,000 characters.';
        $noticeType = 'error';
    } else {
        $directory = __DIR__ . '/storage';
        $path = $directory . '/contact-submissions.jsonl';
        try {
            if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
                throw new RuntimeException('Unable to create contact storage directory.');
            }
            $record = json_encode([
                'submitted_at' => gmdate(DATE_ATOM),
                'name' => $name,
                'email' => $email,
                'message' => $message,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            $file = fopen($path, 'ab');
            if ($file === false) {
                throw new RuntimeException('Unable to open the contact storage file.');
            }
            try {
                if (!flock($file, LOCK_EX) || fwrite($file, $record) !== strlen($record) || !fflush($file)) {
                    throw new RuntimeException('Unable to safely save the contact submission.');
                }
            } finally {
                flock($file, LOCK_UN);
                fclose($file);
            }
            set_flash('success', 'Your message has been received.');
            redirect('contact.php');
        } catch (RuntimeException|JsonException $error) {
            error_log('StudentHub contact submission failed: ' . $error->getMessage());
            http_response_code(500);
            $notice = 'We could not save your message. Please try again later.';
            $noticeType = 'error';
        }
    }
}

page_header('Contact StudentHub');
?>
<section class="portal-panel">
  <h1>Contact the StudentHub team</h1>
  <p>Your message is validated on the server and stored in a locked JSON Lines file.</p>
  <?php if ($notice !== ''): ?><p class="portal-message <?= h($noticeType) ?>" role="alert"><?= h($notice) ?></p><?php endif; ?>
  <form class="portal-form" method="post" action="contact.php">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <div class="portal-field"><label for="contact-name">Name</label><input id="contact-name" name="name" maxlength="120" required value="<?= h($name) ?>"></div>
    <div class="portal-field"><label for="contact-email">Email</label><input id="contact-email" name="email" type="email" maxlength="254" required value="<?= h($email) ?>"></div>
    <div class="portal-field"><label for="contact-message">Message</label><textarea id="contact-message" name="message" maxlength="5000" required><?= h($message) ?></textarea></div>
    <button class="button button-primary" type="submit">Send message</button>
  </form>
</section>
<?php page_footer(); ?>
