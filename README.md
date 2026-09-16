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

- **Routing** (`routing_panel`) — method/URL (from `$_SERVER`, not
  `$PAGE->url`), page type/layout/heading/context/course. Sensitive-looking
  query params (`sesskey`, ...) are masked with the same `scrubber`.
- **User** (`user_panel`) — a full scrubbed dump of `$USER`, not a
  hand-picked field list (see "Sensitive data" above for why that pattern
  is preferred), plus `isloggedin()`/`isguestuser()`/`is_siteadmin()`.
- **Session** (`session_panel`) — the raw PHP session ID is *never* shown,
  hardcoded regardless of the scrubber (leaking it is equivalent to
  leaking an auth cookie); `$SESSION` contents are scrubbed the same way
  as `$USER`, with `sesskey` explicitly excluded on top.
- **Database** (`database_panel`) — see below.

### Database panel

Aggregate counters (reads/writes/total time, via `$DB->perf_get_reads()`
etc.) are always shown — public Moodle API, no core changes needed.

The full per-query table (SQL + scrubbed params + timing) needs an
**optional, dev-only core patch**: `dev/moodle_database.patch`, applied to
your own local Moodle core checkout —

```bash
patch -p1 < admin/tool/tracy/dev/moodle_database.patch
# from your Moodle root
```

Moodle core exposes no public per-query hook (`query_start()`/
`query_end()`/`query_log()` are all `protected`, and `query_log()` only
ever writes to the error log — confirmed by reading
`lib/dml/moodle_database.php`, not assumed). The patch adds two
`function_exists()`-guarded calls — inert on any site that hasn't applied
it, so it's safe to leave in a shared dev image. **Two hook points, not
one:** by the time `query_log()` runs, a query's params have already been
rewritten from named (`:password`) to positional (`0, 1, ...`) by
`fix_sql_params()`, which is called earlier in the same request — a plain
array with no key names left, so the scrubber can no longer match against
them (confirmed live: a literal `password` param leaked in full when only
`query_log()` was hooked). `fix_sql_params()` is hooked too, capturing the
still-named params before that rewrite; `classes/query_log.php` then
correlates the two by exact SQL text. If a query's params can't be
correlated (some internal calls bypass `fix_sql_params()` entirely, e.g.
`moodle_database::export_table_recordset()`), the panel shows
`(unavailable)` — never the raw, unscrubbable positional array.

Slow queries are highlighted amber (&ge;5ms) or red (&ge;20ms); queries
whose exact SQL text repeats &ge;5&times; in one request (a classic N+1
smell) get a dedicated "Duplicate queries" summary showing their
*combined* time, since any single occurrence can look harmless on its own.

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

## Known limitations (skeleton, not production-hardened)

- No cookie/IP allowlist wiring for production mode yet — pass an explicit
  `$mode` if you need per-deployment control, or rely on Tracy's own
  detection (`'detect'` / the "Auto-detect" admin setting).
- The high-level path only affects `$showBar`/logging/email the way
  `Debugger::enable()` normally does; it does not add any Moodle-specific
  error routing on top.
- `scrubber`'s strict pattern is broad by design (safety over convenience)
  but is still just a regex over key names — it can't catch a secret that
  ends up under an innocuous-looking key.
