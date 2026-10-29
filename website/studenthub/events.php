<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
$user = require_auth();
page_header('Campus events');
?>
<section class="portal-panel">
  <h1>Campus events</h1>
  <p>Events load through the StudentHub JSON API. Updates appear without a full page reload.</p>
  <p id="events-status" class="portal-message" role="status" aria-live="polite">Loading events...</p>
  <?php if ($user['role'] === 'admin'): ?>
    <h2 id="event-form-heading">Add event</h2>
    <form id="event-form" class="portal-form-grid" enctype="multipart/form-data">
      <input type="hidden" name="id" value="">
      <div class="portal-field"><label for="event-title">Title</label><input id="event-title" name="title" maxlength="160" required></div>
      <div class="portal-field"><label for="event-date">Date</label><input id="event-date" name="event_date" type="date" required></div>
      <div class="portal-field"><label for="event-location">Location</label><input id="event-location" name="location" maxlength="160" required></div>
      <div class="portal-field"><label for="event-poster">Poster (JPG, PNG, or WebP; up to 2 MB)</label><input id="event-poster" name="poster" type="file" accept="image/jpeg,image/png,image/webp"></div>
      <div class="portal-field full-width"><label for="event-description">Description</label><textarea id="event-description" name="description" maxlength="5000" required></textarea></div>
      <div class="portal-actions full-width"><button class="button button-primary" type="submit">Save event</button><button id="cancel-event-edit" class="button button-secondary" type="button" hidden>Cancel edit</button></div>
    </form>
  <?php endif; ?>
</section>
<section class="portal-panel">
  <h2>Events</h2>
  <div id="event-list" class="portal-grid"></div>
</section>
<script>
(() => {
  const apiUrl = 'api/events.php';
  const csrfToken = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const isAdmin = <?= $user['role'] === 'admin' ? 'true' : 'false' ?>;
  const list = document.getElementById('event-list');
  const status = document.getElementById('events-status');
  const form = document.getElementById('event-form');
  const cancelEdit = document.getElementById('cancel-event-edit');
  let events = [];

  async function request(url, options = {}) {
    const response = await fetch(url, {
      ...options,
      headers: { ...(options.headers || {}), 'X-CSRF-Token': csrfToken },
    });
    const result = await response.json();
    if (!response.ok || !result.ok) throw new Error(result.error || `Request failed (${response.status})`);
    return result;
  }

  function showMessage(message, isError = false) {
    status.textContent = message;
    status.classList.toggle('error', isError);
  }

  function render() {
    list.replaceChildren();
    if (!events.length) {
      const empty = document.createElement('p');
      empty.className = 'portal-muted';
      empty.textContent = 'No events are available.';
      list.append(empty);
      return;
    }

    events.forEach(event => {
      const card = document.createElement('article');
      card.className = 'portal-card';
      if (event.poster_path) {
        const poster = document.createElement('img');
        poster.className = 'portal-poster';
        poster.src = event.poster_path;
        poster.alt = `Poster for ${event.title}`;
        card.append(poster);
      }
      const title = document.createElement('h3');
      title.textContent = event.title;
      const details = document.createElement('p');
      details.textContent = `${event.event_date} · ${event.location}`;
      const description = document.createElement('p');
      description.textContent = event.description;
      card.append(title, details, description);
      if (isAdmin) {
        const actions = document.createElement('div');
        actions.className = 'portal-actions';
        const edit = document.createElement('button');
        edit.className = 'button button-secondary';
        edit.type = 'button';
        edit.textContent = 'Edit';
        edit.addEventListener('click', () => {
          form.elements.id.value = event.id;
          form.elements.title.value = event.title;
          form.elements.event_date.value = event.event_date;
          form.elements.location.value = event.location;
          form.elements.description.value = event.description;
          document.getElementById('event-form-heading').textContent = 'Edit event';
          form.querySelector('button[type="submit"]').textContent = 'Save changes';
          cancelEdit.hidden = false;
          form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
        const remove = document.createElement('button');
        remove.className = 'button button-secondary';
        remove.type = 'button';
        remove.textContent = 'Delete';
        remove.addEventListener('click', async () => {
          if (!window.confirm(`Delete "${event.title}" and its registrations?`)) return;
          try {
            const body = new FormData();
            body.set('action', 'delete');
            body.set('id', event.id);
            await request(apiUrl, { method: 'POST', body });
            await loadEvents('Event deleted.');
          } catch (error) {
            showMessage(error.message, true);
          }
        });
        actions.append(edit, remove);
        card.append(actions);
      }
      list.append(card);
    });
  }

  async function loadEvents(message = 'Events loaded.') {
    try {
      const result = await request(apiUrl);
      events = result.events;
      render();
      showMessage(message);
    } catch (error) {
      showMessage(error.message, true);
    }
  }

  if (form) {
    form.addEventListener('submit', async event => {
      event.preventDefault();
      const body = new FormData(form);
      body.set('action', body.get('id') ? 'update' : 'create');
      try {
        await request(apiUrl, { method: 'POST', body });
        form.reset();
        form.elements.id.value = '';
        document.getElementById('event-form-heading').textContent = 'Add event';
        form.querySelector('button[type="submit"]').textContent = 'Save event';
        cancelEdit.hidden = true;
        await loadEvents('Event saved.');
      } catch (error) {
        showMessage(error.message, true);
      }
    });
    cancelEdit.addEventListener('click', () => {
      form.reset();
      form.elements.id.value = '';
      document.getElementById('event-form-heading').textContent = 'Add event';
      form.querySelector('button[type="submit"]').textContent = 'Save event';
      cancelEdit.hidden = true;
    });
  }

  loadEvents();
})();
</script>
<?php page_footer(); ?>
