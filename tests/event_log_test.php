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

namespace tool_tracy;

/**
 * Tests for \tool_tracy\event_log and \tool_tracy\observer.
 *
 * Like query_log, event_log's state is static/process-global, and this
 * environment's own config.php enables Tracy (and so the wildcard
 * observer) on every request — real Moodle bootstrap/session events flow
 * through the exact same static state. Every test runs
 * @runInSeparateProcess and asserts only on entries it added itself in
 * that isolated process, using \core\event\test_event or unique markers
 * where possible.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_log_test extends \advanced_testcase {
    /**
     * @runInSeparateProcess
     */
    public function test_observer_is_a_noop_when_tracy_is_not_enabled(): void {
        $this->resetAfterTest();

        $this->assertFalse(class_exists(\Tracy\Debugger::class, false), 'sanity: fresh process, nothing has enabled Tracy yet');

        $event = \core\event\user_loggedin::create([
            'objectid' => 2,
            'other' => ['username' => 'admin'],
        ]);
        observer::event_triggered($event);

        $this->assertSame([], event_log::entries());
    }

    /**
     * @runInSeparateProcess
     */
    public function test_observer_records_the_event_once_tracy_is_enabled(): void {
        $this->resetAfterTest();
        bootstrap::enable('development');

        $event = \core\event\user_loggedin::create([
            'objectid' => 2,
            'other' => ['username' => 'admin'],
        ]);
        observer::event_triggered($event);

        $entries = event_log::entries();
        $this->assertNotEmpty($entries);
        $this->assertSame(\core\event\user_loggedin::class, end($entries)['class']);
    }

    /**
     * @runInSeparateProcess
     */
    public function test_entries_cap_is_enforced(): void {
        $this->resetAfterTest();
        bootstrap::enable('development');

        $event = \core\event\user_loggedin::create(['objectid' => 2, 'other' => ['username' => 'admin']]);
        for ($i = 0; $i < 600; $i++) {
            observer::event_triggered($event);
        }

        $this->assertLessThanOrEqual(500, count(event_log::entries()));
    }
}
