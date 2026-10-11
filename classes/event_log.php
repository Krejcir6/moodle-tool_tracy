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
 * Stores triggered events fed to it by \tool_tracy\observer (a wildcard
 * event observer registered in db/events.php — first-class Moodle API,
 * unlike query_log's need for a core patch).
 *
 * Stores raw event data; scrubbing is applied at render time by
 * events_panel, same pattern as query_log/database_panel.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tracy;

/**
 * Stores triggered events for the request. See the file docblock above
 * for how events get added and when scrubbing is applied.
 */
final class event_log {
    /** Caps memory use on long-running/heavy scripts (CLI cron, bulk imports). */
    private const MAX_ENTRIES = 500;

    /** @var array<int, array{class: string, data: array}> */
    private static array $entries = [];

    /**
     * Records one triggered event, dropping it once MAX_ENTRIES is reached.
     */
    public static function add(\core\event\base $event): void {
        if (count(self::$entries) >= self::MAX_ENTRIES) {
            return;
        }

        self::$entries[] = [
            'class' => get_class($event),
            'data' => $event->get_data(),
        ];
    }

    /**
     * All events recorded so far this request.
     *
     * @return array<int, array{class: string, data: array}>
     */
    public static function entries(): array {
        return self::$entries;
    }
}
