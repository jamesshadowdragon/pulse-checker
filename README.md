# PulseCheck API — File Monitor Storage

This version does **not use MySQL** for monitor storage.

When a monitor is created, PulseCheck creates:

```text
htdocs/monitors/monitor_1.json
htdocs/monitors/monitor_2.json
htdocs/monitors/monitor_3.json
```

The JSON files contain the monitor URL, token, status and check history.

## Important security detail

`monitors/.htaccess` denies direct HTTP access to the entire `monitors` directory. The dashboard/API can read the files through PHP, but visitors cannot request the JSON files directly.

Do not delete `monitors/.htaccess`.

## InfinityFree setup

Upload the project contents into `htdocs`:

```text
htdocs/
├── .htaccess
├── config.php
├── cron.php
├── index.php
├── api/
│   ├── monitors.php
│   ├── check.php
│   └── history.php
└── monitors/
    └── .htaccess
```

Make sure PHP can write to the `monitors` directory. The API attempts to create it automatically, but it is included in the ZIP so you can upload it directly.

## Create monitor

POST:

`/api/monitors.php`

Body:

```json
{"url":"https://example.com"}
```

A file such as `monitors/monitor_1.json` is created automatically.

## Check monitor

POST:

`/api/check.php?id=1`

Header:

`X-PulseCheck-Token: MONITOR_TOKEN`

## History

GET:

`/api/history.php?id=1&limit=100`

## Delete

DELETE:

`/api/monitors.php?id=1`

Header:

`X-PulseCheck-Token: MONITOR_TOKEN`

## Cron

Set a strong `PULSECHECK_CRON_SECRET` environment variable if available.

Then call:

`/cron.php?secret=YOUR_CRON_SECRET`

The cron checks monitors that have not been checked in the last two minutes.

## Note

File-based storage is convenient for simple hosting, but it is less scalable than a database for a large number of monitors.
