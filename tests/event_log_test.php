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
 * @covers \tool_tracy\event_log
 * @covers \tool_tracy\observer
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class event_log_test extends \advanced_testcase {
    /**
     * observer::event_triggered() must stay a no-op until Tracy is enabled.
     *
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
     * observer::event_triggered() must record the event once Tracy is enabled.
     *
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
        $last = end($entries);
        if ($last === false) {
            $this->fail('No event was recorded.');
        }
        $this->assertSame(\core\event\user_loggedin::class, $last['class']);
    }

    /**
     * event_log must cap stored entries to bound memory use.
     *
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
