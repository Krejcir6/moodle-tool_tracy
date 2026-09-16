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
 * Stores per-query entries fed to it by the global tool_tracy_capture_query()/
 * tool_tracy_log_query() (see query_log.php at the plugin root), which are
 * only ever called at all if the optional, dev-only core patch in
 * dev/moodle_database.patch has been applied — see README.md's "Database
 * panel" section. Without that patch, this class simply never receives
 * anything, and database_panel falls back to the aggregate
 * perf_get_reads()/perf_get_writes()/perf_get_queries_time() numbers,
 * which are public Moodle API and need no patch.
 *
 * Two hook points, not one, and why: by the time moodle_database::query_log()
 * runs (where timing/error status are available), the query's params have
 * already been rewritten from named (:password) to positional (0, 1, ...)
 * by fix_sql_params() — a plain array with no key names left for the
 * scrubber to match against. capture() is called from fix_sql_params()
 * itself, before that rewrite, so it still has the original names; commit()
 * (called from query_log(), with timing) correlates the two by matching the
 * exact (already table-name-fixed) SQL text, FIFO per distinct SQL string —
 * safe even for the same query repeated with different params, since
 * PHP/Moodle's DB access is synchronous and calls can't interleave.
 *
 * If a query reaches commit() with nothing pending for its SQL (some
 * internal calls build SQL and call query_start() directly, bypassing
 * fix_sql_params() — e.g. moodle_database::export_table_recordset())
 * `params` is left null rather than falling back to the unscrubbable
 * positional array — see database_panel.php's rendering of that case.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tracy;

final class query_log
{
    /** Caps memory use on long-running/heavy scripts (CLI cron, bulk imports). */
    private const MAX_ENTRIES = 500;

    /** Per distinct SQL string — guards against a pending queue growing unbounded. */
    private const MAX_PENDING_PER_SQL = 50;

    /** @var array<string, array<int, array|null>> */
    private static array $pending = [];

    /** @var array<int, array{sql: ?string, params: ?array, time: float, error: mixed}> */
    private static array $entries = [];

    /**
     * Called from fix_sql_params(), with the query's original, still-named
     * params — before they get rewritten positional.
     */
    public static function capture(?string $sql, ?array $params): void
    {
        if ($sql === null) {
            return;
        }

        self::$pending[$sql] ??= [];
        if (count(self::$pending[$sql]) >= self::MAX_PENDING_PER_SQL) {
            array_shift(self::$pending[$sql]);
        }
        self::$pending[$sql][] = $params;
    }

    /**
     * Called from query_log(), once timing/error status are known. $params
     * here is deliberately unused for display — it's the already-positional
     * array query_log() itself sees, which the scrubber can no longer
     * usefully protect (see class docblock); it's accepted only so the core
     * patch's two hook signatures stay simple and symmetric.
     */
    public static function commit(?string $sql, ?array $params, float $time, mixed $error = false): void
    {
        if (count(self::$entries) >= self::MAX_ENTRIES) {
            return;
        }

        $named = null;
        if ($sql !== null && !empty(self::$pending[$sql])) {
            $named = array_shift(self::$pending[$sql]);
        }

        self::$entries[] = [
            'sql' => $sql,
            'params' => $named,
            'time' => $time,
            'error' => $error,
        ];
    }

    /** @return array<int, array{sql: ?string, params: ?array, time: float, error: mixed}> */
    public static function entries(): array
    {
        return self::$entries;
    }

    public static function totalTime(): float
    {
        return array_sum(array_column(self::$entries, 'time'));
    }
}
