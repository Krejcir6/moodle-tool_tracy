# tool_tracy

Integrates the [Tracy](https://tracy.nette.org) debugger into Moodle: bluescreen
on uncaught errors/exceptions and the debug bar.

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

`lib.php`'s `tool_tracy_after_config()` is a regular Moodle plugin callback
(invoked once `$USER`/session are available). It is the standard activation
path and applies the configured activation scope.

## Standard activation

The admin setting **Tracy activation** controls both activation and debug bar
visibility. It defaults to **Administrators only**. Choose **Disabled** in
production; choose **All users** only for controlled development diagnostics.
The Tracy display mode is selected internally for this development-focused
plugin. File logging is disabled in this milestone.

The standard activation happens after Moodle bootstrap, once the current user
is known. Requests from users outside the selected scope are left entirely to
Moodle and do not register Tracy handlers or panels.

## Experimental low-level activation

### Low-level: config.php

Add these lines to your site's `config.php`, above
`require_once(__DIR__ . '/lib/setup.php');`:

```php
require_once(__DIR__ . '/admin/tool/tracy/classes/bootstrap.php');
\tool_tracy\bootstrap::enable(\Tracy\Debugger::Development);
```

This path is **experimental and not recommended**. It is retained only for
diagnosing failures during Moodle's own bootstrap. It runs before Moodle knows
the current user, so it bypasses the administrators-only activation mode and
can affect every request. The operator must explicitly accept that behavior.

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

The standard admin-settings path is the recommended path. It does not require
editing `config.php` and can enforce the default administrators-only scope.

### Choosing who handles errors after bootstrap

PHP only ever has one active exception/error handler at a time — no
stacking. Moodle's own `lib/setup.php` unconditionally calls
`set_exception_handler('default_exception_handler')`/`set_error_handler('default_error_handler', ...)`
as part of its own bootstrap, which — if the low-level path enabled Tracy
earlier, from `config.php` — takes the handlers back for the rest of the
request. The **Error handler ownership** setting decides what happens next:

- **Moodle handlers** (default) preserve Moodle-specific AJAX error
  responses, redirects, rendering, logging, and exit semantics. Tracy still
  covers early bootstrap and fatal shutdown errors and renders its debug bar.
- **Tracy handlers** make `tool_tracy_after_config()` reclaim the handlers,
  providing Tracy bluescreens for uncaught post-bootstrap exceptions too.

When Tracy owns exceptions, `reclaimHandlers()` calls
`bootstrap::abortOpenTransactions()`, which mirrors the one thing worth
keeping from Moodle's own handler: `default_exception_handler()`'s first
line is `abort_all_db_transactions()`, because an exception handler must
never leave a Moodle DB transaction open. It's cheap and safe to always
call (a no-op if nothing is open).

## Setup

1. Pick one of the two activation paths above.

2. Tracy itself is vendored under `thirdparty/tracy/` (pinned version in
   `thirdparty/tracy/VERSION.txt`) rather than pulled in via Composer at
   runtime, per Moodle's third-party library policy — see
   `thirdpartylibs.xml`.

3. Visit the site — the debug bar should appear in the corner.

## Sensitive data

`bootstrap::enable()` always wires a [`tool_tracy\scrubber`](classes/scrubber.php)
into Tracy, at its strictest level by default — safety is the default, not
an opt-in. This matters because Moodle's `global $CFG;` convention means
`$CFG` (DB password, SMTP password, various API secrets, ...) shows up as a
local variable in the stack trace of almost any exception.

A regex over key names is still just a guess, so at strict level
`enable()` also reads the **real, current `$CFG` object** and passes every
one of its actual property names into the scrubber as an unconditional
exact-match list. `$CFG->wwwroot` gets hidden too, even though "wwwroot"
doesn't look sensitive — it's hidden because it's a real `$CFG` key, not
because of what it's named. Two, structurally different Tracy mechanisms
need feeding, since neither alone covers everything:

- `Tracy\BlueScreen::$scrubber` — a pattern-based callable, covers the
  bluescreen (stack frame locals, `$_SERVER`/`$_ENV`, ...). This is the one
  that protects `$CFG->dbpass` etc.
- `Tracy\Debugger::$keysToHide` — an *exact*-match-only array that
  `Debugger::dump()`/`dumpe()` (ad-hoc debug calls a developer writes in
  code) respect; it does not accept a scrubber callable at all.

The experimental low-level API can optionally persist URLs, user
identifiers, exception messages, stack traces, arguments, and request
context outside Moodle-managed database storage. The administrator controls
the directory's access, retention, and deletion. Keep it outside the web
root and apply an appropriate retention policy.

**`bdump()` needed a third fix.** `Tracy\Debugger::barDump()` — what
Tracy's own `bdump()` shortcut calls — applies *neither* of the above by
default; it was found live, the hard way, that `bdump($CFG)` rendered
`$CFG->dbpass` in full, in plain text, in the bar. [`bdump.php`](bdump.php)
replaces Tracy's global `bdump()` with one that explicitly forwards both
`$keysToHide` and the current scrubber — Tracy's own `functions.php`
guards its definition with `if (!function_exists('bdump'))`, so as long as
`bdump.php` is required before the Tracy library itself (see
`classes/bootstrap.php`), ours wins.

`bdump.php` also defines **`bdump_unsafe()`** — a deliberate, explicitly-named
escape hatch identical to Tracy's own unprotected `barDump()` (nothing
masked at all), for the rare case a developer genuinely needs to see a
real value. Its bar panel title always gets a visible "⚠ UNSAFE" prefix so
its output can never be mistaken for `bdump()`'s.

`scrubber::LEVEL_MODERATE` exists for a narrower, less trigger-happy match
list, but nothing currently exposes a way to switch to it — that's
deliberate for now, see ROADMAP.md.

**This must give the same protection regardless of which of the two
activation paths enabled Tracy.** At the point the low-level path calls
`enable()` from `config.php`, `$CFG` only has the ~30 properties
`config.php` itself sets — the few hundred more that `lib/setup.php`'s
`initialise_cfg()` loads from the `mdl_config` table (`siteidentifier` and
any DB-stored secrets among them) don't exist yet. `bootstrap::refreshCfgScrubbing()`
re-derives the $CFG key list and is called again from `tool_tracy_after_config()`
— which Moodle invokes on every request regardless of activation path —
once bootstrap has actually finished and $CFG is complete. The only
difference the low-level path is meant to have is *earlier* bluescreen
coverage (bootstrap-time crashes); the eventual scrubbing coverage itself
converges to the same thing either way.

## Debug bar panels

- **Routing** (`routing_panel`) — method/path (from `$_SERVER`, not
  `$PAGE->url`), page type/layout/heading/context/course, plus parameters
  read exclusively from `$_GET`. It never reads POST/raw bodies, uploads,
  or cookies. Sensitive-looking GET keys are masked recursively.
- **User** (`user_panel`) — a full scrubbed dump of `$USER`, not a
  hand-picked field list (see "Sensitive data" above for why that pattern
  is preferred), plus `isloggedin()`/`isguestuser()`/`is_siteadmin()`.
- **Session** (`session_panel`) — the raw PHP session ID is *never* shown,
  hardcoded regardless of the scrubber (leaking it is equivalent to
  leaking an auth cookie); `$SESSION` contents are scrubbed the same way
  as `$USER`, with `sesskey` explicitly excluded on top.
- **Database** (`database_panel`) — see below.
- **Events** (`events_panel`) — see below.

### Database panel

Aggregate counters (reads/writes/total time, via `$DB->perf_get_reads()`
etc.) are always shown — public Moodle API, no core changes needed.

The full per-query table (SQL text + timing) needs an **optional, dev-only
core patch**: `dev/moodle_database.patch`, applied to your own local
Moodle core checkout —

```bash
patch -p1 < admin/tool/tracy/dev/moodle_database.patch
# from your Moodle root
```

If `patch` doesn't apply cleanly (see "Version compatibility" below), add
this by hand inside `lib/dml/moodle_database.php`'s `query_log()`, right
after the `$time = $this->query_time();` line:

```php
if (function_exists('tool_tracy_log_query')) {
    tool_tracy_log_query($this->last_sql, $time, $error);
}
```

Moodle core exposes no public per-query hook (`query_start()`/
`query_end()`/`query_log()` are all `protected`, and `query_log()` only
ever writes to the error log — confirmed by reading
`lib/dml/moodle_database.php`, not assumed). The patch adds one
`function_exists()`-guarded call — inert on any site that hasn't applied
it, so it's safe to leave in a shared dev image.

Deliberately **SQL text only, no separate query-parameter array**. An earlier version of
this patch also captured param values (needing a second hook point in
`fix_sql_params()` to catch them before Moodle rewrites them positional,
plus scrubbing, plus correlation logic to match the two back together) —
more complexity than it was worth. Correctly parameterised Moodle queries
normally contain placeholders (`:password`, `?`), but SQL written with
literal values retains those values in `$this->last_sql`. Treat the optional
per-query panel as potentially sensitive development output; see git
history/ROADMAP.md for the full reasoning.

Slow queries are highlighted amber (&ge;5ms) or red (&ge;20ms); queries
whose exact SQL text repeats &ge;5&times; in one request (a classic N+1
smell) get a dedicated "Duplicate queries" summary showing their
*combined* time, since any single occurrence can look harmless on its own.

**Version compatibility.** The patch's insertion point — right after
`$time = $this->query_time();` in `query_log()` — has been unchanged in
Moodle core since 2014 (`git blame`), so it should apply cleanly across a
wide range of versions. That's evidence of stability, not a guarantee for
every version: this has only actually been tested against the 4.3dev
checkout in this repo's history. If `patch -p1` reports a failed hunk,
use the manual one-liner above instead.

### Events panel

Registers a wildcard event observer in `db/events.php`
(`'eventname' => '*'`) — the same first-class, documented mechanism
`logstore_standard` uses to catch every event. Unlike the database panel,
**no core patch needed**.

Two things, not just "an event fired":

- Which events actually triggered, grouped by event class with a count
  (same pattern as the database panel's duplicate-query summary) — event
  data (including `other`) is scrubbed the same way as everywhere else.
- For each triggered event class, the observers Moodle would actually
  invoke for it — `\core\event\manager::get_all_observers()` is public
  API, but matching an event to *which* of those apply needs its whole
  parent-class chain too (a `'*'` registration becomes
  `\core\event\base` internally, so an event several levels down still
  matches it). That walk is done by `manager::get_observing_classes()`,
  which is `protected` — `events_panel::observersFor()` reimplements the
  same handful of `get_class()`/`get_parent_class()` lines rather than
  reaching into a private internal. Confirmed live against a real
  triggered event that this lists genuine third-party observers
  (`tool_log`, `tool_monitor`), not just this plugin's own.

`classes/observer.php` — the callback Moodle invokes on *every* event on
*every* request once the plugin is installed, regardless of whether Tracy
is enabled — early-returns if Tracy isn't active, the same no-op-by-default
principle as the rest of the plugin.

Adding `db/events.php` to an already-installed copy of this plugin needs a
version bump and `admin/cli/upgrade.php` (or the admin *Notifications*
page) for Moodle to notice and register the new observer — it won't pick
it up from the file alone.

## Testing

- `tests/*_test.php` — standard PHPUnit:
  `vendor/bin/phpunit --testsuite tool_tracy_testsuite` (pointing directly
  at `admin/tool/tracy/tests/` does not work with Moodle's PHPUnit runner).
  `query_log_test.php`/`database_panel_test.php` run every test
  `@runInSeparateProcess` and match on a unique marker SQL string rather
  than asserting on `entries()`'s overall shape: this environment's own
  `config.php` enables the query-logging core patch unconditionally, so
  real bootstrap queries flow through the exact same static state.
- `tests/ajax_smoke.sh` — **not** a PHPUnit test, and can't be one: the
  thing it checks (Tracy correctly staying out of a JSON AJAX response) is
  decided by `Tracy\Helpers::isHtmlMode()`, which has a hard `!isCli()`
  condition and always returns `false` under PHPUnit's CLI SAPI regardless
  of what a test fakes. It has to be a real HTTP request against a running
  site: `MOODLE_URL=http://localhost:8000 tests/ajax_smoke.sh`.

## Static analysis

```bash
composer install   # once, installs phpstan/phpstan as a dev dependency
                    # (via Docker: docker run --rm -v "$PWD:/app" -w /app composer:2 install)

# from the Moodle root, with this plugin at the usual admin/tool/tracy path:
php admin/tool/tracy/vendor/bin/phpstan analyse --memory-limit=1G \
    -c admin/tool/tracy/phpstan.neon
```

Has to run from a real Moodle checkout (`%currentWorkingDirectory%/lib` and
`.../privacy` in `phpstan.neon`'s `scanDirectories`) — this repo on its own
has no Moodle core to check against, so `moodle_database`, `context`,
`moodle_url`, the event system, PHPUnit base classes etc. would all show up
as "unknown class" otherwise. Tracy itself is loaded via `bootstrapFiles`
(requiring `thirdparty/tracy/src/tracy.php` for real, rather than passively
scanning the directory, which — unexplained why, but confirmed by testing
both — didn't make PHPStan aware of `Tracy\Debugger`/`Tracy\Dumper`/
`Tracy\IBarPanel` the way `scanDirectories` did for Moodle's own `lib/`).
`--memory-limit=1G`: scanning all of `lib/` needs more than PHP's 128M
default.

## Known limitations (skeleton, not production-hardened)

- The standard activation mode has an optional IP allowlist. Trusted proxy
  handling follows Moodle's `getremoteaddr()` configuration; custom proxy
  header handling is not provided.
- Error handler ownership is configurable. With the default Moodle ownership,
  the high-level path keeps Moodle's exception/error routing while Tracy
  still provides its debug bar and fatal shutdown handling. Selecting Tracy
  ownership replaces Moodle's post-bootstrap handlers and provides Tracy
  bluescreens for uncaught exceptions.
- `scrubber`'s strict pattern is broad by design (safety over convenience)
  but is still just a regex over key names — it can't catch a secret that
  ends up under an innocuous-looking key.
