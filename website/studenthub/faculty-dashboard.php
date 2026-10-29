<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
$user = require_role('faculty');
$db = portal_db();

$profileQuery = $db->prepare('SELECT department, title FROM faculty WHERE user_id = ?');
$profileQuery->bind_param('i', $user['id']);
$profileQuery->execute();
$profile = $profileQuery->get_result()->fetch_assoc();
if (!$profile) {
    http_response_code(404);
    exit('Faculty profile not found. Contact an administrator.');
}

$studentCount = (int) $db->query('SELECT COUNT(*) AS count FROM students')->fetch_assoc()['count'];
$eventCount = (int) $db->query('SELECT COUNT(*) AS count FROM events WHERE event_date >= CURRENT_DATE')->fetch_assoc()['count'];
$registrations = $db->query(
    "SELECT e.title, e.event_date, u.name AS student_name, u.email AS student_email, r.registered_at
     FROM registrations r
     JOIN students s ON s.id = r.student_id
     JOIN users u ON u.id = s.user_id
     JOIN events e ON e.id = r.event_id
     WHERE r.status = 'registered'
     ORDER BY r.registered_at DESC LIMIT 10"
)->fetch_all(MYSQLI_ASSOC);

page_header('Faculty dashboard');
?>
<header class="portal-panel">
  <p class="eyebrow">Faculty dashboard</p>
  <h1>Welcome, <?= h($user['name']) ?></h1>
  <p><?= h($profile['title']) ?> · <?= h($profile['department']) ?></p>
</header>
<section class="portal-grid" aria-label="Faculty overview">
  <article class="portal-card"><strong>Students</strong><p class="portal-stat"><?= h($studentCount) ?></p></article>
  <article class="portal-card"><strong>Upcoming events</strong><p class="portal-stat"><?= h($eventCount) ?></p></article>
  <article class="portal-card"><strong>Workspace</strong><p><a href="events.php">View campus events</a></p></article>
</section>
<section class="portal-panel">
  <h2>Recent event registrations</h2>
  <div class="portal-table-wrap"><table class="portal-table">
    <thead><tr><th>Student</th><th>Email</th><th>Event</th><th>Event date</th><th>Registered</th></tr></thead>
    <tbody><?php foreach ($registrations as $registration): ?><tr>
      <td><?= h($registration['student_name']) ?></td><td><?= h($registration['student_email']) ?></td><td><?= h($registration['title']) ?></td><td><?= h($registration['event_date']) ?></td><td><?= h($registration['registered_at']) ?></td>
    </tr><?php endforeach; ?>
    <?php if (!$registrations): ?><tr><td colspan="5">No registrations yet.</td></tr><?php endif; ?></tbody>
  </table></div>
</section>
<?php page_footer(); ?>
