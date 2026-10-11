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
 * Tests for \tool_tracy\user_panel.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_panel_test extends \basic_testcase {
    public function test_gettab_contains_svg_icon(): void {
        bootstrap::enable('development');
        $this->assertStringContainsString('<svg', (new user_panel())->getTab());
    }

    /**
     * $USER properties that look sensitive by name must be masked.
     *
     * @runInSeparateProcess
     */
    public function test_getpanel_masks_a_sensitive_looking_user_property(): void {
        global $USER;
        bootstrap::enable('development');

        $secret = 'super-secret-' . bin2hex(random_bytes(4));
        $USER->password = $secret;

        $html = (new user_panel())->getPanel();

        $this->assertStringNotContainsString($secret, $html);
    }

    /**
     * The panel must show login/guest/admin status flags.
     *
     * @runInSeparateProcess
     */
    public function test_getpanel_shows_login_flags(): void {
        bootstrap::enable('development');

        $html = (new user_panel())->getPanel();

        $this->assertStringContainsString('Logged in', $html);
        $this->assertStringContainsString('Site admin', $html);
    }
}
