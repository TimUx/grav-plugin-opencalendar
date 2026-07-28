# Event Calendar (vkurko) assets

This directory is intentionally empty in git.

OpenCalendar loads Event Calendar **5.10.1** from the jsDelivr CDN at runtime:

- https://cdn.jsdelivr.net/npm/@event-calendar/build@5.10.1/dist/event-calendar.min.css
- https://cdn.jsdelivr.net/npm/@event-calendar/build@5.10.1/dist/event-calendar.min.js

To vendor locally (optional, offline installs):

```bash
mkdir -p assets/vendor/event-calendar
curl -L -o assets/vendor/event-calendar/event-calendar.min.css \
  https://cdn.jsdelivr.net/npm/@event-calendar/build@5.10.1/dist/event-calendar.min.css
curl -L -o assets/vendor/event-calendar/event-calendar.min.js \
  https://cdn.jsdelivr.net/npm/@event-calendar/build@5.10.1/dist/event-calendar.min.js
```

Then update `opencalendar.php` asset registration to use `plugin://opencalendar/assets/vendor/event-calendar/...`.
