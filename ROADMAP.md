# Roadmap

## Phase 1 — safe, functional skeleton (done)

Goal: get Tracy correctly wired into Moodle without introducing a security
or stability regression, before growing the feature set. Safety is the
default, not an opt-in.

- [x] Standard activation is controlled by one setting: `off`, `admins`
      (default), or `all`. It controls both Tracy activation and bar
      visibility. Low-level `config.php` activation remains an experimental,
      not recommended escape hatch for bootstrap-time errors.
- [x] Tracy vendored under `thirdparty/tracy/`, pinned version, per Moodle's
      third-party library policy — not pulled in via Composer at runtime.
- [x] Safe defaults: standard activation is **administrators only**;
      Tracy uses its internal development mode when standard activation is
      enabled; the mode is no longer exposed as a second admin switch.
- [x] `Debugger::$strictMode = true` — no error silently downgraded to a
      log line if it can be a bluescreen instead.
- [x] Handler-hijack detection and **optional reclaiming**. The
      `handlerownership` setting defaults to `moodle`, preserving Moodle's
      AJAX responses, redirects, rendering, logging, and exit semantics.
      Selecting `tracy` makes `bootstrap::reclaim_handlers()` take the
      handlers back from Moodle's `default_exception_handler`/
      `default_error_handler` —
      reimplementing the two lines `Debugger::enable()` itself uses
      (`exceptionHandler()`/`errorHandler()` are `public static`), since
      calling `enable()` again no-ops once already enabled. High-level
      activation snapshots and restores the active Moodle handlers when
      Moodle ownership is selected; low-level activation keeps Tracy during
      early bootstrap and then leaves Moodle's handlers in control unless
      reclaiming was explicitly selected.
      - **Before reclaiming, keeps one piece of what Moodle's own handler
        did**: `default_exception_handler()`'s first line is
        `abort_all_db_transactions()` — an exception handler must never
        leave a Moodle DB transaction open. Pulled out into
        `bootstrap::abort_open_transactions()` (safe/cheap to always call —
        the underlying function no-ops if nothing is open) specifically so
        it's unit-testable without having to trigger a real, process-ending
        `exit(255)`. Confirmed live, twice: PHPUnit test opens a real
        transaction and asserts it's gone after calling it (log line
        "Database transaction aborted automatically" appears); and a real
        HTTP request that opens a transaction then throws mid-request
        renders Tracy's bluescreen (not Moodle's error page) with the same
        log line, not a dangling transaction.
- [x] `tool_tracy\scrubber`: masks sensitive-looking values (`$CFG->dbpass`
      and friends, which `global $CFG;` puts in every stack trace) before
      Tracy can render them, at its strictest level by default — see
      README.md's "Sensitive data" section. At strict level, *every* actual
      $CFG property is hidden unconditionally (not just the ones a pattern
      guesses are sensitive) — `bootstrap::enable()` passes $CFG's real key
      list in. `LEVEL_MODERATE` exists in the
      class for later, but nothing exposes a way to switch to it yet.
- [x] `bootstrap::refresh_cfg_scrubbing()`: the low-level path's `$CFG`
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
      `hijacked_handlers()`, scrubber pattern/exact-match behaviour,
      `bdump()`/`bdump_unsafe()`, panel rendering/escaping, admin setting
      defaults) and `tests/ajax_smoke.sh` (real-HTTP check that Tracy stays
      out of JSON AJAX responses — not possible as a PHPUnit test, see
      README.md). Writing the panel test also caught and fixed a real
      (non-security) double-escaping bug in `handler_check_panel::getPanel()`.
- [x] `handler_check_panel` rendering test — see above.
- [x] No-op-by-default check: `tests/noop_by_default_test.php` locks in the
      admin setting defaults (`activationmode=admins`,
      `handlerownership=moodle`) at the PHPUnit level. The deeper claim — a real
      request produces zero Tracy footprint when config.php has no
      low-level `enable()` call — can't be verified by a PHPUnit test in
      *this* dev environment (its own config.php always calls the
      low-level path, unconditionally, for developing the plugin itself),
      so it was verified manually once instead: with that config.php line
      commented out and `activationmode=off`, `/login/index.php` came back with zero
      case-insensitive occurrences of "tracy" anywhere in the response and
      no `tracy-session` cookie — only the ordinary `MoodleSession` one.

## Phase 2 — more debug bar panels (current)

Goal: once the base is solid, make Tracy genuinely useful for day-to-day
Moodle development, not just crash reporting.

- [x] **Routing panel** (`routing_panel`) — method + path from `$_SERVER`
      (not `$PAGE->url`, which isn't reliably set yet when this could run),
      `$PAGE` type/layout/heading/context/course. Parameters are read only
      from `$_GET`; POST/raw bodies, uploads, and cookies are never inspected.
      Sensitive-looking names (`sesskey`, `token`, ...) are masked recursively.
- [x] **User panel** (`user_panel`) — deliberately a full scrubbed dump of
      `$USER` (`bootstrap::scrubbed_dump_options()`) rather than a hand-picked
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
      applied it.
      - v1 logged params too, which needed a second hook point in
        `fix_sql_params()` (params are already rewritten positional —
        key names gone — by the time `query_log()` runs, confirmed live
        with a literal `password` param that leaked in full when only
        `query_log()` was hooked) plus FIFO correlation logic to match the
        two hooks' data back together by exact SQL text.
      - Simplified to **SQL text + timing only, single hook, no separate params
        column** — simpler than the params version needed to be, since
        params were the *only* reason two hook points and correlation
        existed. Correctly parameterised SQL normally contains placeholders,
        but literal values remain visible, so the panel is documented as
        potentially sensitive development output.
      - The patch's insertion point (`query_log()`, right after
        `$time = $this->query_time();`) has been unchanged in core since
        2014 per `git blame` — good evidence for cross-version
        portability, though only actually tested against this repo's
        4.3dev checkout. README.md documents a manual one-line fallback
        for versions where `patch -p1` doesn't apply cleanly.
- [x] SVG icons on every panel's tab (including the Phase 1
      `handler_check_panel`), colour-shifting to red on `database_panel`'s
      icon when a slow/frequent query is present.
- [x] **Events panel** (`events_panel`) — a wildcard event observer
      (`db/events.php`, `'eventname' => '*'`), the same first-class,
      documented mechanism `logstore_standard` uses to catch every event;
      no core patch needed here, unlike the database panel. Two things,
      not just "an event fired":
      - Which events actually triggered, grouped by event class (not
        instance) with a count, same pattern as the database panel's
        duplicate-query summary. `other`/event data goes through
        `bootstrap::scrubbed_dump_options()`.
      - For each triggered event class, which observers Moodle would
        invoke for it — `\core\event\manager::get_all_observers()` is
        public API; matching an event to the observers that apply to it
        (including its whole parent-class chain, since `'*'` registers
        for `\core\event\base`) needed reimplementing the same trivial
        `get_class()`/`get_parent_class()` walk `manager::get_observing_classes()`
        does internally (that one method is `protected`) — confirmed live
        against a real triggered event that this correctly lists
        third-party observers (`tool_log`, `tool_monitor`), not just our
        own.
      - `classes/observer.php` (the callback Moodle actually invokes on
        *every* event, on *every* request, once the plugin is installed —
        regardless of whether Tracy is enabled) early-returns if Tracy
        isn't active, same no-op-by-default principle as the rest of the
        plugin.
      - Adding `db/events.php` to an already-installed plugin needed a
        version bump + `admin/cli/upgrade.php` run for Moodle to notice
        and register the new observer.

## Tooling

- [x] **PHPStan** (`phpstan.neon`, level `max`, `composer.json`/dev-only). Has
      to run from within a real Moodle checkout — `scanDirectories` points
      at the surrounding install's `lib/`/`privacy` for Moodle core
      symbols (`moodle_database`, `context`, the event system, PHPUnit
      base classes, ...), since this repo alone has no Moodle core to
      check against. Tracy needed `bootstrapFiles` (actually `require`s
      `thirdparty/tracy/src/tracy.php`), not `scanDirectories` — passive
      directory scanning silently failed to make PHPStan aware of
      `Tracy\Debugger`/`Tracy\Dumper`/`Tracy\IBarPanel`, confirmed by
      testing both; not fully understood why, `bootstrapFiles` just
      reliably worked instead. First real run caught one genuine (minor)
      bug: `admin_setting_configcheckbox`'s `$defaultsetting` is
      documented `string`, `settings.php` was passing `1`/`0` (int).
      `[OK] No errors` after fixes — see README.md's "Static analysis".
      Moodle 5.1+'s `public/` layout needs a second config,
      `phpstan-public.neon`: `lib/`/`privacy/` move under `public/`, but
      `vendor/` (PHPUnit, which `tests/*.php`'s base classes need) stays
      at the Moodle root — no single `%currentWorkingDirectory%` covers
      both, confirmed live against a real Moodle 5.1 `public/` checkout
      (129 spurious "undefined method" errors on `tests/*.php` without
      the fix). Bumped from level 5 to `max` (currently 9): fixed the
      real findings (missing generic array value types throughout,
      unnecessary `method_exists()` guards on three `moodle_database`
      methods that are unconditionally defined, a `getmypid()`
      `int|false` case, and several tests indexing into a possibly-empty
      `end()`/possibly-`mixed` result without narrowing first) rather
      than suppressing them — `[OK] No errors` on both layouts, confirmed
      with a cleared result cache across repeated runs.

## Planned

- [ ] **Custom panels registered by other plugins.** Let any Moodle plugin
      add its own tab to the debug bar (its own `\Tracy\IBarPanel`), so
      project-specific diagnostics (a course format's cache, an integration's
      API calls, a queue) sit next to the built-in Routing/User/Session/
      Database/Events panels. Sketch: a Moodle hook (e.g.
      `\tool_tracy\hook\register_panels`) dispatched from
      `tool_tracy_after_config()` after the built-in panels are added; plugins
      subscribe in their own `db/hooks.php` and call `$hook->add_panel()`.
      Open questions: panels must go through `bootstrap::scrubbed_dump_options()`
      (document it, maybe offer a helper), and a panel that throws must not
      break the bar (Tracy already isolates a failing panel).

## Explicitly out of scope for now

- Trusted proxy and advanced IP allowlist handling beyond Moodle's
  `getremoteaddr()` configuration.
- Controlled Tracy file logging with retention and privacy management.

## Maturity graduation criteria

For a dev-only tool, "stable" never means "safe for production" - staying
disabled in production is a permanent design constraint, not something
maturity changes. These gates are about how much the *implementation*
itself can be trusted, not about where it's allowed to run.

### Alpha → beta

- [ ] All five panels (Routing/User/Session/Database/Events) + bluescreen
      + both activation paths work with no known bug - CI green across
      the whole supported Moodle/PHP/DB matrix.
- [x] PHPStan level 9 clean (now `max`, see "Tooling" above).
- [ ] Every item under "Known limitations" above is either fixed or
      explicitly reclassified as a permanent design constraint (not left
      as "still unfinished"). Trusted proxy handling, for example, is
      likely to stay out of scope forever - that has to be a decision,
      not a gap.
- [ ] No open P1/security issue on the tracker for a stretch of real,
      day-to-day use on an actual Moodle project (no fixed timebox - long
      enough that nothing's surprised me recently).
- [ ] The "Planned" custom-panels hook stays *outside* this gate - it's
      new functionality, not hardening of what exists, and can land
      after beta.

### Beta → stable (final)

- [ ] 2-3 calendar months in beta with no regression (not just a usage
      count - real time passing without a surprise).
- [ ] Verified on real projects/installs, not just CI - which ones and
      how many is a judgment call at the time, not a fixed number.
- [ ] From stable onward: no breaking change without a major version
      bump - the point of "stable" is that it's safe to rely on this in
      daily workflow without re-checking behaviour on every update.
- [ ] README/landing page/FAQ describe final behaviour - no leftover
      "experimental" wording that isn't actually still experimental.
