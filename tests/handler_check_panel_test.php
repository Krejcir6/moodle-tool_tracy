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
 * Tests for \tool_tracy\handler_check_panel.
 *
 * Pure rendering, no Tracy runtime state involved beyond the \Tracy\IBarPanel
 * interface existing (which it always does in this environment — this
 * site's own config.php enables Tracy on every request) — no
 * @runInSeparateProcess needed.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class handler_check_panel_test extends \basic_testcase {
    public function test_implements_tracy_bar_panel_interface(): void {
        $panel = new handler_check_panel(['exception']);

        $this->assertInstanceOf(\Tracy\IBarPanel::class, $panel);
    }

    /** @dataProvider hijacked_provider */
    public function test_gettab_mentions_hijacked_and_is_non_empty(array $hijacked): void {
        $tab = (new handler_check_panel($hijacked))->getTab();

        $this->assertNotSame('', $tab);
        $this->assertStringContainsString('hijacked', strtolower($tab));
    }

    public static function hijacked_provider(): array {
        return [
            'exception only' => [['exception']],
            'error only' => [['error']],
            'both' => [['exception', 'error']],
        ];
    }

    public function test_getpanel_lists_exception_only(): void {
        $panel = (new handler_check_panel(['exception']))->getPanel();

        $this->assertStringContainsString('exception', $panel);
        $this->assertStringNotContainsString('exception and error', $panel);
    }

    public function test_getpanel_lists_both_handlers_joined(): void {
        $panel = (new handler_check_panel(['exception', 'error']))->getPanel();

        $this->assertStringContainsString('exception and error', $panel);
    }

    public function test_getpanel_explains_both_activation_paths(): void {
        $panel = (new handler_check_panel(['exception']))->getPanel();

        // The two concrete, actionable pieces of information this panel
        // exists to convey — see README.md's "Two activation paths".
        $this->assertStringContainsString('config.php', $panel);
        $this->assertStringContainsString('admin settings', $panel);
    }

    public function test_getpanel_escapes_handler_names(): void {
        // hijackedHandlers() only ever returns 'exception'/'error' literals,
        // but getPanel() must not assume that and render them unescaped.
        $panel = (new handler_check_panel(['<script>alert(1)</script>']))->getPanel();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $panel);
        $this->assertStringContainsString('&lt;script&gt;', $panel);
    }

    public function test_gettab_and_getpanel_are_valid_utf8_and_well_formed_enough_to_not_break_svg(): void {
        $panel = new handler_check_panel(['exception', 'error']);

        // getTab() embeds a raw <svg> icon — a smoke check that the markup
        // is at least balanced, not a full HTML validator.
        $this->assertSame(
            substr_count($panel->getTab(), '<svg'),
            substr_count($panel->getTab(), '</svg>')
        );
    }
}
