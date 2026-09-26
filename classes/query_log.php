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

final class query_log {
    /** Caps memory use on long-running/heavy scripts (CLI cron, bulk imports). */
    private const MAX_ENTRIES = 500;

    /** @var array<int, array{sql: ?string, time: float, error: mixed}> */
    private static array $entries = [];

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

    /** @return array<int, array{sql: ?string, time: float, error: mixed}> */
    public static function entries(): array {
        return self::$entries;
    }

    public static function clear(): void {
        self::$entries = [];
    }

    public static function totalTime(): float {
        return array_sum(array_column(self::$entries, 'time'));
    }
}
