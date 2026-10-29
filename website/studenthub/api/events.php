<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

function api_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

function api_event_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function save_poster(array $upload): ?string
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'] ?? '')) {
        throw new InvalidArgumentException('Poster upload failed.');
    }
    if ((int) $upload['size'] > 2 * 1024 * 1024) {
        throw new InvalidArgumentException('Poster must be 2 MB or smaller.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        throw new InvalidArgumentException('Poster must be a JPG, PNG, or WebP image.');
    }

    $directory = dirname(__DIR__) . '/uploads';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to prepare poster storage.');
    }
    $filename = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($upload['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('Unable to store poster image.');
    }
    return 'uploads/' . $filename;
}

function remove_poster(?string $posterPath): void
{
    if (!$posterPath || !preg_match('/^uploads\/[a-f0-9]{40}\.(jpg|png|webp)$/', $posterPath)) {
        return;
    }
    $directory = realpath(dirname(__DIR__) . '/uploads');
    $file = realpath(dirname(__DIR__) . '/' . $posterPath);
    if ($directory !== false && $file !== false && dirname($file) === $directory && is_file($file) && !unlink($file)) {
        error_log('StudentHub could not remove old event poster: ' . $file);
    }
}

$user = current_user(false);
if (!$user) {
    api_response(['ok' => false, 'error' => 'Sign in is required.'], 401);
}

$db = portal_db();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $search = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
    if ($search === '') {
        $result = $db->query('SELECT id, title, description, event_date, location, poster_path FROM events ORDER BY event_date, id');
        api_response(['ok' => true, 'events' => $result->fetch_all(MYSQLI_ASSOC)]);
    }
    $term = '%' . $search . '%';
    $statement = $db->prepare('SELECT id, title, description, event_date, location, poster_path FROM events WHERE title LIKE ? OR location LIKE ? OR description LIKE ? ORDER BY event_date, id');
    $statement->bind_param('sss', $term, $term, $term);
    $statement->execute();
    api_response(['ok' => true, 'events' => $statement->get_result()->fetch_all(MYSQLI_ASSOC)]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_response(['ok' => false, 'error' => 'Use GET to list events or POST to change events.'], 405);
}
if ($user['role'] !== 'admin') {
    api_response(['ok' => false, 'error' => 'Administrator access is required.'], 403);
}
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_string($csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    api_response(['ok' => false, 'error' => 'The request token is invalid or expired. Refresh the page and try again.'], 400);
}

try {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $input = json_decode(file_get_contents('php://input') ?: '', true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($input)) {
            api_response(['ok' => false, 'error' => 'Request data must be a JSON object.'], 400);
        }
    } else {
        $input = $_POST;
    }

    $action = $input['action'] ?? '';
    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
    if ($action === 'delete') {
        if (!$id || $id < 1) {
            api_response(['ok' => false, 'error' => 'A valid event ID is required.'], 422);
        }
        $find = $db->prepare('SELECT poster_path FROM events WHERE id = ?');
        $find->bind_param('i', $id);
        $find->execute();
        $existing = $find->get_result()->fetch_assoc();
        if (!$existing) {
            api_response(['ok' => false, 'error' => 'Event not found.'], 404);
        }
        $db->begin_transaction();
        $delete = $db->prepare('DELETE FROM events WHERE id = ?');
        $delete->bind_param('i', $id);
        $delete->execute();
        audit_log((int) $user['id'], 'event_deleted', 'event', $id);
        $db->commit();
        remove_poster($existing['poster_path']);
        api_response(['ok' => true, 'message' => 'Event deleted.']);
    }

    $title = trim(is_string($input['title'] ?? null) ? $input['title'] : '');
    $description = trim(is_string($input['description'] ?? null) ? $input['description'] : '');
    $eventDate = is_string($input['event_date'] ?? null) ? $input['event_date'] : '';
    $location = trim(is_string($input['location'] ?? null) ? $input['location'] : '');
    if (!in_array($action, ['create', 'update'], true) || $title === '' || strlen($title) > 160
        || $description === '' || strlen($description) > 5000 || !api_event_date($eventDate)
        || $location === '' || strlen($location) > 160 || ($action === 'update' && (!$id || $id < 1))) {
        api_response(['ok' => false, 'error' => 'Enter a title, description, valid date, and location within the allowed lengths.'], 422);
    }

    $posterPath = null;
    if (isset($_FILES['poster']) && is_array($_FILES['poster'])) {
        $posterPath = save_poster($_FILES['poster']);
    }

    if ($action === 'create') {
        $db->begin_transaction();
        $statement = $db->prepare('INSERT INTO events (title, description, event_date, location, poster_path, created_by) VALUES (?, ?, ?, ?, ?, ?)');
        $actorId = (int) $user['id'];
        $statement->bind_param('sssssi', $title, $description, $eventDate, $location, $posterPath, $actorId);
        $statement->execute();
        $eventId = (int) $db->insert_id;
        audit_log($actorId, 'event_created', 'event', $eventId);
        $db->commit();
    } else {
        $find = $db->prepare('SELECT poster_path FROM events WHERE id = ?');
        $find->bind_param('i', $id);
        $find->execute();
        $existing = $find->get_result()->fetch_assoc();
        if (!$existing) {
            if ($posterPath) remove_poster($posterPath);
            api_response(['ok' => false, 'error' => 'Event not found.'], 404);
        }
        $db->begin_transaction();
        if ($posterPath === null) {
            $statement = $db->prepare('UPDATE events SET title = ?, description = ?, event_date = ?, location = ? WHERE id = ?');
            $statement->bind_param('ssssi', $title, $description, $eventDate, $location, $id);
        } else {
            $statement = $db->prepare('UPDATE events SET title = ?, description = ?, event_date = ?, location = ?, poster_path = ? WHERE id = ?');
            $statement->bind_param('sssssi', $title, $description, $eventDate, $location, $posterPath, $id);
        }
        $statement->execute();
        audit_log((int) $user['id'], 'event_updated', 'event', $id);
        $db->commit();
        if ($posterPath !== null) remove_poster($existing['poster_path']);
        $eventId = (int) $id;
    }

    api_response(['ok' => true, 'message' => 'Event saved.', 'id' => $eventId]);
} catch (InvalidArgumentException $error) {
    api_response(['ok' => false, 'error' => $error->getMessage()], 422);
} catch (JsonException $error) {
    api_response(['ok' => false, 'error' => 'Request body contains invalid JSON.'], 400);
} catch (mysqli_sql_exception|RuntimeException $error) {
    if ($db->errno === 0) {
        $db->rollback();
    }
    error_log('StudentHub event API failed: ' . $error->getMessage());
    api_response(['ok' => false, 'error' => 'The event could not be saved. Check server configuration and try again.'], 500);
}
