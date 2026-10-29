<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_role('admin');
$db = portal_db();

$counts = [
    'users' => (int) $db->query('SELECT COUNT(*) AS count FROM users')->fetch_assoc()['count'],
    'students' => (int) $db->query('SELECT COUNT(*) AS count FROM students')->fetch_assoc()['count'],
    'faculty' => (int) $db->query("SELECT COUNT(*) AS count FROM users WHERE role = 'faculty'")->fetch_assoc()['count'],
    'events' => (int) $db->query('SELECT COUNT(*) AS count FROM events')->fetch_assoc()['count'],
    'registrations' => (int) $db->query("SELECT COUNT(*) AS count FROM registrations WHERE status = 'registered'")->fetch_assoc()['count'],
];
$latest = $db->query(
    "SELECT u.name, u.email, e.title, r.registered_at
     FROM registrations r
     JOIN students s ON s.id = r.student_id
     JOIN users u ON u.id = s.user_id
     JOIN events e ON e.id = r.event_id
     WHERE r.status = 'registered'
     ORDER BY r.registered_at DESC LIMIT 8"
)->fetch_all(MYSQLI_ASSOC);
$audit = $db->query(
    'SELECT a.action, a.entity_type, a.entity_id, a.created_at, u.name AS actor_name
     FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_user_id
     ORDER BY a.created_at DESC LIMIT 10'
)->fetch_all(MYSQLI_ASSOC);

page_header('Administrator dashboard');
?>
<header class="portal-panel">
  <p class="eyebrow">Admin dashboard</p>
  <h1>StudentHub administration</h1>
  <p>Manage student accounts and campus events. This page is restricted to administrators.</p>
  <div class="portal-actions"><a class="button button-primary" href="students.php">Manage students</a><a class="button button-secondary" href="faculty.php">Manage faculty logins</a><a class="button button-secondary" href="events.php">Manage events</a></div>
</header>
<section class="portal-grid" aria-label="Portal statistics">
  <?php foreach (['users' => 'Total users', 'students' => 'Students', 'faculty' => 'Faculty', 'events' => 'Events', 'registrations' => 'Active registrations'] as $key => $label): ?>
    <article class="portal-card"><strong><?= h($label) ?></strong><p class="portal-stat"><?= h($counts[$key]) ?></p></article>
  <?php endforeach; ?>
</section>
<section class="portal-panel">
  <h2>Latest event registrations</h2>
  <div class="portal-table-wrap"><table class="portal-table">
    <thead><tr><th>Student</th><th>Email</th><th>Event</th><th>Registered</th></tr></thead>
    <tbody><?php foreach ($latest as $registration): ?><tr><td><?= h($registration['name']) ?></td><td><?= h($registration['email']) ?></td><td><?= h($registration['title']) ?></td><td><?= h($registration['registered_at']) ?></td></tr><?php endforeach; ?>
    <?php if (!$latest): ?><tr><td colspan="4">No registrations yet.</td></tr><?php endif; ?></tbody>
  </table></div>
</section>
<section class="portal-panel">
  <h2>Recent audit log</h2>
  <div class="portal-table-wrap"><table class="portal-table">
    <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Entity</th><th>ID</th></tr></thead>
    <tbody><?php foreach ($audit as $entry): ?><tr><td><?= h($entry['created_at']) ?></td><td><?= h($entry['actor_name'] ?? 'System') ?></td><td><?= h($entry['action']) ?></td><td><?= h($entry['entity_type']) ?></td><td><?= h($entry['entity_id']) ?></td></tr><?php endforeach; ?>
    <?php if (!$audit): ?><tr><td colspan="5">No audit records yet.</td></tr><?php endif; ?></tbody>
  </table></div>
</section>
<?php page_footer(); ?>
