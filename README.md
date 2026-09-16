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
callback (invoked once `$USER`/session are available) and is used only to
restrict the debug bar to admins when Tracy is in production mode — it
does not enable Tracy itself.

## Setup

1. Add these two lines to your site's `config.php`, above
   `require_once(__DIR__ . '/lib/setup.php');`:

   ```php
   require_once(__DIR__ . '/admin/tool/tracy/classes/bootstrap.php');
   \tool_tracy\bootstrap::enable();
   ```

2. Tracy itself is vendored under `thirdparty/tracy/` (pinned version in
   `thirdparty/tracy/VERSION.txt`) rather than pulled in via Composer at
   runtime, per Moodle's third-party library policy — see
   `thirdpartylibs.xml`.

3. Configure via environment variables (read before Moodle's config/DB
   exist, see point above):

   | Variable | Purpose | Default |
   |---|---|---|
   | `MOODLE_TRACY_MODE` | `development`, `production`, or unset for auto-detect | auto-detect |
   | `MOODLE_TRACY_LOG_DIR` | Directory Tracy writes its logs to | disabled |

   In `moodle-docker`, add these under the `webserver` service's
   `environment:` in a `local.yml` override, or `docker compose exec -e`.

4. Visit the site — the debug bar should appear in the corner. Admin
   settings are under *Site administration → Plugins → Admin tools →
   Tracy debugger*.

## Known limitations (skeleton, not production-hardened)

- No `MOODLE_TRACY_MODE=production` cookie/IP allowlist wiring yet —
  Tracy's own production-mode detection is used as-is.
- `tool_tracy_after_config()` only toggles `Debugger::$showBar`; it does
  not affect whether errors are logged/emailed in production mode.
- Not yet tested against Moodle's own exception handler and AJAX/CLI
  entry points beyond the standard web request.
