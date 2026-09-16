# Roadmap

## Phase 1 — safe, functional skeleton (current)

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
- [x] Basic automated test coverage: `tests/bootstrap_test.php` (PHPUnit —
      mode-string mapping, CLI disables the bar, `hijackedHandlers()`) and
      `tests/ajax_smoke.sh` (real-HTTP check that Tracy stays out of JSON
      AJAX responses — not possible as a PHPUnit test, see README.md).
- [ ] `handler_check_panel` rendering test (getTab()/getPanel() output).
- [ ] Explicit check: installing this plugin on an already-live site with no
      `config.php` changes and default settings must be a complete no-op
      (nothing enabled, nothing visible, no behaviour change).

## Phase 2 — more debug bar panels

Goal: once the base is solid, make Tracy genuinely useful for day-to-day
Moodle development, not just crash reporting.

- [ ] **Routing/page panel** — current `$PAGE` type, URL, layout, matched
      script.
- [ ] **User panel** — current `$USER` summary (id, username, roles/
      capabilities in the current context). Must respect `restricttoadmins`
      the same way the bar itself does — this panel is exactly the kind of
      thing that must never leak to a non-admin production visitor.
- [ ] **Session panel** — session id, `$SESSION` contents, active cache
      stores.
- [ ] **Nice to have: database/query panel** (query log, timing, N+1
      detection). Likely blocked without patching Moodle core: unlike e.g.
      Doctrine's SQL logger, `moodle_database` doesn't expose a public
      per-query hook to tap into from a plugin. Needs research before
      committing to it — worth checking `$DB->perf_get_reads()`/
      `perf_get_writes()` and what `tool_profiling`/`report_performance`
      already tap into, to see if there's enough exposed without touching
      core. Track as uncertain, not planned, until that research lands.

## Explicitly out of scope for now

- Cookie/IP allowlist wiring for Tracy's production-mode detection — its
  own detection is used as-is.
- Auto-reclaiming hijacked handlers. The plugin reports the hijack: it does
  not attempt to re-register itself over Moodle's own handlers.
