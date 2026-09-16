# Roadmap

## Phase 1 — safe, functional skeleton (done)

Goal: get Tracy correctly wired into Moodle without introducing a security
or stability regression, before growing the feature set. Safety is the
default, not an opt-in.

- [x] Two independent activation paths: low-level (`config.php`, catches
      bootstrap-time errors) and high-level (admin settings, no file edits).
- [x] Tracy vendored under `thirdparty/tracy/`, pinned version, per Moodle's
      third-party library policy — not pulled in via Composer at runtime.
- [x] Safe defaults: high-level path is **off** (`enabled = 0`), `mode`
      defaults to `detect` (never forces development mode on), debug bar
      **restricted to `moodle/site:config` users in production mode** by
      default (`restricttoadmins = 1`).
- [x] `Debugger::$strictMode = true` — no error silently downgraded to a
      log line if it can be a bluescreen instead.
- [x] Handler-hijack detection + diagnostic panel: report, don't silently
      fight Moodle for control of `set_exception_handler`/`set_error_handler`.
- [x] `tool_tracy\scrubber`: masks sensitive-looking values (`$CFG->dbpass`
      and friends, which `global $CFG;` puts in every stack trace) before
      Tracy can render them, at its strictest level by default — see
      README.md's "Sensitive data" section. At strict level, *every* actual
      $CFG property is hidden unconditionally (not just the ones a pattern
      guesses are sensitive) — `bootstrap::enable()` passes $CFG's real key
      list in. `LEVEL_MODERATE` exists in the
      class for later, but nothing exposes a way to switch to it yet.
- [x] `bootstrap::refreshCfgScrubbing()`: the low-level path's `$CFG`
      snapshot at `enable()` time is incomplete (~30 keys vs. ~620 once
      `lib/setup.php` finishes) — `tool_tracy_after_config()` now re-derives
      it once $CFG is actually complete, on every request, regardless of
      which path enabled Tracy. Both paths converge to the same scrubbing
      coverage; the only remaining difference is *when* Tracy starts
      catching errors, not how well it hides secrets once it has.
- [x] `bdump.php`: found live that `bdump($CFG)` rendered `$CFG->dbpass` in
      full — `Tracy\Debugger::barDump()` (what `bdump()` calls) applies
      neither `$keysToHide` nor a scrubber at all, unlike `dump()`/the
      bluescreen. Replaces Tracy's own `bdump()` (same
      `function_exists()` guard trick Tracy itself uses) with one that
      does. `bdump_unsafe()` is the explicit, clearly-labelled opt-out for
      when a real value is genuinely needed.
- [x] Basic automated test coverage: `tests/bootstrap_test.php` +
      `tests/scrubber_test.php` + `tests/bdump_test.php` +
      `tests/handler_check_panel_test.php` + `tests/noop_by_default_test.php`
      (PHPUnit — mode-string mapping, CLI disables the bar,
      `hijackedHandlers()`, scrubber pattern/exact-match behaviour,
      `bdump()`/`bdump_unsafe()`, panel rendering/escaping, admin setting
      defaults) and `tests/ajax_smoke.sh` (real-HTTP check that Tracy stays
      out of JSON AJAX responses — not possible as a PHPUnit test, see
      README.md). Writing the panel test also caught and fixed a real
      (non-security) double-escaping bug in `handler_check_panel::getPanel()`.
- [x] `handler_check_panel` rendering test — see above.
- [x] No-op-by-default check: `tests/noop_by_default_test.php` locks in the
      three admin setting defaults (`enabled=0`, `mode=detect`,
      `restricttoadmins=1`) at the PHPUnit level. The deeper claim — a real
      request produces zero Tracy footprint when config.php has no
      low-level `enable()` call — can't be verified by a PHPUnit test in
      *this* dev environment (its own config.php always calls the
      low-level path, unconditionally, for developing the plugin itself),
      so it was verified manually once instead: with that config.php line
      commented out and `enabled=0`, `/login/index.php` came back with zero
      case-insensitive occurrences of "tracy" anywhere in the response and
      no `tracy-session` cookie — only the ordinary `MoodleSession` one.

## Phase 2 — more debug bar panels (current)

Goal: once the base is solid, make Tracy genuinely useful for day-to-day
Moodle development, not just crash reporting.

- [x] **Routing panel** (`routing_panel`) — method + URL from `$_SERVER`
      (not `$PAGE->url`, which isn't reliably set yet when this could run),
      `$PAGE` type/layout/heading/context/course. Query string params with
      sensitive-looking names (`sesskey`, `token`, ...) are masked via the
      same `scrubber` pattern, reused rather than reinvented.
- [x] **User panel** (`user_panel`) — deliberately a full scrubbed dump of
      `$USER` (`bootstrap::scrubbedDumpOptions()`) rather than a hand-picked
      field list, same reasoning as `$CFG`: any auth/SSO/profile-field
      plugin can add properties, and a hand-picked list would need updating
      every time one does. Plus `isloggedin()`/`isguestuser()`/
      `is_siteadmin()` flags. Always the *viewing* user's own data, but the
      bar is visible to every logged-in user (not just admins) whenever
      Tracy is in development mode, so it's still fully scrubbed.
- [x] **Session panel** (`session_panel`) — the raw PHP session ID is never
      shown, hardcoded, regardless of what the scrubber would catch
      (equivalent to leaking an auth cookie); `$SESSION` contents go
      through the same scrubbed dump as `$USER`, with `sesskey` explicitly
      unset as a belt-and-braces on top of that.
- [x] **Database panel** (`database_panel`) — two tiers. Aggregate counters
      (`$DB->perf_get_reads()`/`perf_get_writes()`/`perf_get_queries_time()`)
      are public API, always shown, no core changes. A full per-query table
      needs the optional `dev/moodle_database.patch` (see below) — without
      it, the panel explains how to get it instead of showing nothing.
      Slow queries (&ge;5ms amber, &ge;20ms red) and queries repeated
      &ge;5&times; in one request (classic N+1) are colour-coded per row,
      plus a dedicated "Duplicate queries" summary table with each
      duplicated query's *combined* time — the number that actually matters
      for an N+1 smell, since any single occurrence can look harmless.
- [x] **Database panel per-query log**: confirmed, not just suspected,
      that `moodle_database` exposes no public per-query hook —
      `query_start()`/`query_end()`/`query_log()` are all `protected`, and
      `query_log()` only ever writes to the error log. Implemented anyway,
      as an opt-in dev-only core patch (`dev/moodle_database.patch`),
      guarded by `function_exists()` so it's inert on any site that hasn't
      applied it. **Two hook points, not one**, discovered live: by the
      time `query_log()` runs, a query's params have already been
      rewritten from named (`:password`) to positional (`0, 1, ...`) by
      `fix_sql_params()` — tested with a literal `password` param and
      confirmed it leaked in full when only hooking `query_log()`.
      `fix_sql_params()` is hooked too, capturing the still-named params
      before that rewrite; `classes/query_log.php` correlates the two back
      together by exact SQL text (FIFO per distinct string — safe even for
      the same query repeated with different params, since DB access is
      synchronous). If correlation ever fails for a query (some internal
      calls bypass `fix_sql_params()` entirely), params are shown as
      `(unavailable)`, never as the unscrubbable raw positional array.
- [x] SVG icons on every panel's tab (including the Phase 1
      `handler_check_panel`), colour-shifting to red on `database_panel`'s
      icon when a slow/frequent query is present.

Next up: an **events panel** (Moodle event observers/triggered events for
the request).

## Explicitly out of scope for now

- Cookie/IP allowlist wiring for Tracy's production-mode detection — its
  own detection is used as-is.
- Auto-reclaiming hijacked handlers. The plugin reports the hijack: it does
  not attempt to re-register itself over Moodle's own handlers.
