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
        bootstrap::enable('development');
        $this->assertStringContainsString('<svg', (new events_panel())->getTab());
    }

    /**
     * The panel must show a placeholder message when no events fired.
     *
     * @runInSeparateProcess
     */
    public function test_getpanel_shows_message_when_no_events_triggered(): void {
        bootstrap::enable('development');

        $html = (new events_panel())->getPanel();

        $this->assertStringContainsString('No events triggered', $html);
    }

    /**
     * The observer list must include a real third-party observer, not
     * just our own.
     *
     * @runInSeparateProcess
     */
    public function test_getpanel_lists_a_real_thirdparty_observer(): void {
        $this->resetAfterTest();
        bootstrap::enable('development');

        $event = \core\event\user_loggedin::create(['objectid' => 2, 'other' => ['username' => 'admin']]);
        observer::event_triggered($event);

        $html = (new events_panel())->getPanel();

        // Tool_log's '*' observer showing up confirms the class-hierarchy
        // walk finds more than just our own.
        $this->assertStringContainsString('tool_log', $html);
        $this->assertStringContainsString('tool_tracy', $html);
    }

    /**
     * Event data must be scrubbed before rendering.
     *
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
     * A secret stored under a generic key inside "other" must still be
     * masked fail-closed.
     *
     * @runInSeparateProcess
     */
    public function test_getpanel_masks_a_secret_stored_under_a_generic_other_key(): void {
        $this->resetAfterTest();
        bootstrap::enable('development');

        $secret = 'generic-value-secret-' . bin2hex(random_bytes(4));
        $event = \core\event\user_loggedin::create([
            'objectid' => 2,
            'other' => ['username' => $secret],
        ]);
        observer::event_triggered($event);

        $html = (new events_panel())->getPanel();

        $this->assertStringNotContainsString($secret, $html);
    }

    /**
     * Repeated events of the same class must be grouped once, with a count.
     *
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
