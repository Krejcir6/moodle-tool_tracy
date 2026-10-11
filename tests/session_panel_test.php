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
 * Tests for \tool_tracy\session_panel.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_panel_test extends \basic_testcase {
    public function test_gettab_contains_svg_icon(): void {
        bootstrap::enable('development');
        $this->assertStringContainsString('<svg', (new session_panel())->getTab());
    }

    /**
     * The raw PHP session ID must never be shown, regardless of scrubbing.
     *
     * @runInSeparateProcess
     */
    public function test_getpanel_never_shows_the_raw_session_id(): void {
        bootstrap::enable('development');

        $id = session_id();
        $html = (new session_panel())->getPanel();

        if ($id !== '') {
            $this->assertStringNotContainsString($id, $html);
        }
    }

    /**
     * sesskey must be hidden unconditionally, as belt-and-braces alongside
     * the scrubber.
     *
     * @runInSeparateProcess
     */
    public function test_getpanel_explicitly_excludes_sesskey_even_without_the_scrubber(): void {
        global $SESSION;
        bootstrap::enable('development');

        $secret = 'THE_REAL_SESSKEY_' . bin2hex(random_bytes(4));
        $SESSION->sesskey = $secret;

        $html = (new session_panel())->getPanel();

        $this->assertStringNotContainsString($secret, $html);
    }

    /**
     * $SESSION values that look sensitive by name must be masked.
     *
     * @runInSeparateProcess
     */
    public function test_getpanel_masks_sensitive_looking_session_values(): void {
        global $SESSION;
        bootstrap::enable('development');

        $secret = 'super-secret-' . bin2hex(random_bytes(4));
        $SESSION->some_oauth_token = $secret;

        $html = (new session_panel())->getPanel();

        $this->assertStringNotContainsString($secret, $html);
    }
}
