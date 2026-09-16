<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Globals tool_tracy_capture_query()/tool_tracy_log_query() — the two
 * targets of an OPTIONAL, dev-only core patch (dev/moodle_database.patch)
 * to lib/dml/moodle_database.php's fix_sql_params() and query_log().
 *
 * Moodle core does not expose a public per-query hook: query_start()/
 * query_end()/query_log() are all protected, and query_log() itself only
 * ever writes to the error log, never to anything a plugin can read back
 * (confirmed by reading lib/dml/moodle_database.php — see ROADMAP.md). Two
 * hooks, not one: by the time query_log() runs, a query's params have
 * already been rewritten from named to positional by fix_sql_params(), so
 * they can no longer be matched against the scrubber by key — see
 * classes/query_log.php for the full reasoning and how the two are
 * correlated back together safely.
 *
 * Both are guarded by function_exists() exactly like bdump.php's
 * replacement functions: on any site that hasn't applied the patch, the
 * checks in core are false and moodle_database behaves exactly as core
 * ships it.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!function_exists('tool_tracy_capture_query')) {
    function tool_tracy_capture_query(?string $sql, ?array $params): void
    {
        \tool_tracy\query_log::capture($sql, $params);
    }
}

if (!function_exists('tool_tracy_log_query')) {
    function tool_tracy_log_query(?string $sql, ?array $params, float $time, mixed $error = false): void
    {
        \tool_tracy\query_log::commit($sql, $params, $time, $error);
    }
}
