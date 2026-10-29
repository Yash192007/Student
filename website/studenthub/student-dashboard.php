<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
$user = require_role('student');
$db = portal_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
    if (!$eventId || $eventId < 1) {
        set_flash('error', 'Choose a valid event.');
        redirect('student-dashboard.php');
    }
    $lookup = $db->prepare('SELECT id FROM students WHERE user_id = ?');
    $lookup->bind_param('i', $user['id']);
    $lookup->execute();
    $student = $lookup->get_result()->fetch_assoc();
    if (!$student) {
        http_response_code(404);
        exit('Student profile not found.');
    }
    $studentId = (int) $student['id'];
    $statement = $db->prepare("INSERT INTO registrations (student_id, event_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE status = 'registered', registered_at = CURRENT_TIMESTAMP");
    $statement->bind_param('ii', $studentId, $eventId);
    try {
        $statement->execute();
        set_flash('success', 'You are registered for the event.');
    } catch (mysqli_sql_exception $error) {
        if ($error->getCode() === 1452) {
            set_flash('error', 'That event is no longer available.');
        } else {
            error_log('StudentHub event registration failed: ' . $error->getMessage());
            http_response_code(500);
            set_flash('error', 'Registration could not be completed.');
        }
    }
    redirect('student-dashboard.php');
}

$profileQuery = $db->prepare('SELECT s.course, s.year, s.mobile, s.gender FROM students s WHERE s.user_id = ?');
$profileQuery->bind_param('i', $user['id']);
$profileQuery->execute();
$profile = $profileQuery->get_result()->fetch_assoc();
if (!$profile) {
    http_response_code(404);
    exit('Student profile not found.');
}

$eventsQuery = $db->prepare(
    "SELECT e.id, e.title, e.event_date, e.location, e.description, e.poster_path, r.status AS registration_status
     FROM events e LEFT JOIN registrations r ON r.event_id = e.id
       AND r.student_id = (SELECT id FROM students WHERE user_id = ?)
     WHERE e.event_date >= CURRENT_DATE
     ORDER BY e.event_date, e.id"
);
$eventsQuery->bind_param('i', $user['id']);
$eventsQuery->execute();
$events = $eventsQuery->get_result()->fetch_all(MYSQLI_ASSOC);

page_header('Student dashboard');
?>
<header class="portal-panel">
  <p class="eyebrow">Student dashboard</p>
  <h1>Welcome, <?= h($user['name']) ?></h1>
  <p><?= h($profile['course']) ?> · Year <?= h($profile['year']) ?> · <?= h($user['email']) ?></p>
</header>
<section class="portal-panel">
  <h2>Upcoming events</h2>
  <?php if (!$events): ?><p class="portal-muted">There are no upcoming events right now.</p><?php endif; ?>
  <div class="portal-grid">
    <?php foreach ($events as $event): ?>
      <article class="portal-card">
        <?php if ($event['poster_path']): ?><img class="portal-poster" src="<?= h($event['poster_path']) ?>" alt="Poster for <?= h($event['title']) ?>"><?php endif; ?>
        <h3><?= h($event['title']) ?></h3>
        <p><?= h($event['event_date']) ?> · <?= h($event['location']) ?></p>
        <p><?= h($event['description']) ?></p>
        <form method="post" action="student-dashboard.php">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="event_id" value="<?= h($event['id']) ?>">
          <button class="button button-primary" type="submit" <?= $event['registration_status'] === 'registered' ? 'disabled' : '' ?>><?= $event['registration_status'] === 'registered' ? 'Registered' : 'Register for event' ?></button>
        </form>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php page_footer(); ?>
