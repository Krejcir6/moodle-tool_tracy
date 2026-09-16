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
 * Target of the wildcard observer registered in db/events.php.
 *
 * Unlike everything else in this plugin, Moodle calls this on *every*
 * triggered event on *every* request once the plugin is installed —
 * regardless of whether Tracy is actually enabled (the observer
 * registration itself doesn't know or care about that). The early return
 * below is what keeps this a no-op on a site where the plugin is present
 * but not enabled, consistent with the rest of the plugin (see
 * ROADMAP.md's no-op-by-default check).
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tracy;

final class observer
{
    public static function event_triggered(\core\event\base $event): void
    {
        if (!class_exists(\Tracy\Debugger::class, false)) {
            return;
        }

        event_log::add($event);
    }
}
