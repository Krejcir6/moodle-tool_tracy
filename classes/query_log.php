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
 * Stores per-query entries (SQL + timing, no params — see query_log.php at
 * the plugin root) fed by tool_tracy_log_query(), only ever called if the
 * optional dev/moodle_database.patch is applied. Without it this stays
 * empty and database_panel falls back to the aggregate perf_get_*() numbers.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tracy;

/**
 * Stores per-query entries for the request. See the file docblock above
 * for how entries get added and what happens without the core patch.
 */
final class query_log {
    /** Caps memory use on long-running/heavy scripts (CLI cron, bulk imports). */
    private const MAX_ENTRIES = 500;

    /** @var array<int, array{sql: ?string, time: float, error: mixed}> */
    private static array $entries = [];

    /**
     * Records one query entry, dropping it once MAX_ENTRIES is reached.
     *
     * @param ?string $sql The query's SQL text, or null if unavailable.
     * @param float $time The query's execution time, in seconds.
     * @param mixed $error The query's error status, or false if it succeeded.
     */
    public static function add(?string $sql, float $time, mixed $error = false): void {
        if (count(self::$entries) >= self::MAX_ENTRIES) {
            return;
        }

        self::$entries[] = [
            'sql' => $sql,
            'time' => $time,
            'error' => $error,
        ];
    }

    /**
     * All query entries recorded so far this request.
     *
     * @return array<int, array{sql: ?string, time: float, error: mixed}>
     */
    public static function entries(): array {
        return self::$entries;
    }

    /**
     * Clears all recorded query entries.
     */
    public static function clear(): void {
        self::$entries = [];
    }

    /**
     * Combined time of all recorded query entries, in seconds.
     */
    public static function total_time(): float {
        return array_sum(array_column(self::$entries, 'time'));
    }
}
