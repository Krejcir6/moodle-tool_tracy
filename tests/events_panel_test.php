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
 * Tests for \tool_tracy\events_panel.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class events_panel_test extends \advanced_testcase {
    public function test_gettab_contains_svg_icon(): void {
        $this->assertStringContainsString('<svg', (new events_panel())->getTab());
    }

    /**
     * @runInSeparateProcess
     */
    public function test_getpanel_shows_message_when_no_events_triggered(): void {
        bootstrap::enable('development');

        $html = (new events_panel())->getPanel();

        $this->assertStringContainsString('No events triggered', $html);
    }

    /**
     * @runInSeparateProcess
     */
    public function test_getpanel_lists_a_real_thirdparty_observer(): void {
        $this->resetAfterTest();
        bootstrap::enable('development');

        $event = \core\event\user_loggedin::create(['objectid' => 2, 'other' => ['username' => 'admin']]);
        observer::event_triggered($event);

        $html = (new events_panel())->getPanel();

        // tool_log's observer is registered for the '*' wildcard in core,
        // same as ours — if it shows up, the class-hierarchy walk in
        // events_panel::observersFor() is finding more than just itself.
        $this->assertStringContainsString('tool_log', $html);
        $this->assertStringContainsString('tool_tracy', $html);
    }

    /**
     * @runInSeparateProcess
     */
    public function test_getpanel_masks_sensitive_event_data(): void {
        $this->resetAfterTest();
        bootstrap::enable('development');

        $secret = 'super-secret-' . bin2hex(random_bytes(4));
        $event = \core\event\user_loggedin::create([
            'objectid' => 2,
            'other' => ['username' => 'admin', 'password' => $secret],
        ]);
        observer::event_triggered($event);

        $html = (new events_panel())->getPanel();

        $this->assertStringNotContainsString($secret, $html);
    }

    /**
     * @runInSeparateProcess
     */
    public function test_getpanel_groups_repeated_events_by_class(): void {
        $this->resetAfterTest();
        bootstrap::enable('development');

        $event = \core\event\user_loggedin::create(['objectid' => 2, 'other' => ['username' => 'admin']]);
        observer::event_triggered($event);
        observer::event_triggered($event);
        observer::event_triggered($event);

        $html = (new events_panel())->getPanel();

        $this->assertStringContainsString('&times; 3', $html);
        $this->assertSame(1, substr_count($html, 'core\event\user_loggedin &times;'));
    }
}
