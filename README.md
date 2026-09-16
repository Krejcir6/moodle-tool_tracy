# tool_tracy

Integrates the [Tracy](https://tracy.nette.org) debugger into Moodle: bluescreen
on uncaught errors/exceptions, the debug bar, and file logging.

## Installation

Clone this repository into `<moodleroot>/admin/tool/tracy` (the directory
name must be `tracy` — Moodle derives the component name `tool_tracy` from
its parent/self folder names), then follow **Setup** below.

## Why an admin tool, and why the config.php line

This is an `admin/tool` plugin (like `tool_profiling`, `tool_generator`,
`tool_log`) rather than a `local` plugin, since it's a development/debugging
aid for admins, not a sitewide feature.

Moodle's plugin/hook system only becomes available partway through
`lib/setup.php`. To catch errors during Moodle's own bootstrap (DB
connection, session start, early lib includes) Tracy has to be enabled
*before* that file runs, so it can't rely purely on Moodle's plugin
lifecycle. That's why `classes/bootstrap.php` is written to be safe to
`require` directly from `config.php`, with no dependency on `$DB`,
autoloading, or anything else Moodle normally provides.

`lib.php`'s `tool_tracy_after_config()` is a regular Moodle plugin
callback (invoked once `$USER`/session are available). It does two things:
it's the *high-level* activation path (see below), and — regardless of
which path enabled Tracy — it restricts the debug bar to admins when
Tracy is in production mode.

## Two activation paths

### Low-level: config.php

Add these lines to your site's `config.php`, above
`require_once(__DIR__ . '/lib/setup.php');`:

```php
require_once(__DIR__ . '/admin/tool/tracy/classes/bootstrap.php');
\tool_tracy\bootstrap::enable(\Tracy\Debugger::Development);
```

`enable()` takes `enable(string|bool|null $mode = null, ?string $logDirectory = null)`.
`$mode` is `'development'`, `'production'`, or `null`/`'detect'`/anything
else to auto-detect from the client IP (a raw `Tracy\Debugger::Development`/
`::Production` bool also works). Deciding *how* to
pick the mode for a given deployment (hardcoded, per-hostname, env var,
whatever that site's `config.php` already does for `$CFG->dbtype` etc.)
is deliberately left up to that `config.php` — the plugin itself takes
no opinion on it and reads no environment variables.

This is the only path that catches errors during Moodle's own bootstrap
(DB connection, session start, early lib includes), since it runs before
`lib/setup.php` — Moodle's plugin/hook system isn't available yet at that
point, so it can't rely on the plugin lifecycle. It always takes priority:
if config.php already called `enable()`, the high-level path below is a
no-op.

### High-level: admin settings

If you don't need bootstrap-time coverage and don't want to touch
`config.php`, go to *Site administration → Plugins → Admin tools → Tracy
debugger* and turn on **Enable Tracy (admin settings)**, with a **Mode**
and optional **Log directory**. `tool_tracy_after_config()` applies these
once `$USER`/session exist — later than the low-level path, but with zero
file edits.

## Setup

1. Pick one of the two activation paths above.

2. Tracy itself is vendored under `thirdparty/tracy/` (pinned version in
   `thirdparty/tracy/VERSION.txt`) rather than pulled in via Composer at
   runtime, per Moodle's third-party library policy — see
   `thirdpartylibs.xml`.

3. Visit the site — the debug bar should appear in the corner.

## Testing

- `tests/bootstrap_test.php` — standard PHPUnit (`vendor/bin/phpunit
  admin/tool/tracy/tests/`). Covers `bootstrap::enable()`'s mode-string
  mapping, that the debug bar is disabled under CLI, and
  `bootstrap::hijackedHandlers()`.
- `tests/ajax_smoke.sh` — **not** a PHPUnit test, and can't be one: the
  thing it checks (Tracy correctly staying out of a JSON AJAX response) is
  decided by `Tracy\Helpers::isHtmlMode()`, which has a hard `!isCli()`
  condition and always returns `false` under PHPUnit's CLI SAPI regardless
  of what a test fakes. It has to be a real HTTP request against a running
  site: `MOODLE_URL=http://localhost:8000 tests/ajax_smoke.sh`.

## Known limitations (skeleton, not production-hardened)

- No cookie/IP allowlist wiring for production mode yet — pass an explicit
  `$mode` if you need per-deployment control, or rely on Tracy's own
  detection (`'detect'` / the "Auto-detect" admin setting).
- The high-level path only affects `$showBar`/logging/email the way
  `Debugger::enable()` normally does; it does not add any Moodle-specific
  error routing on top.
