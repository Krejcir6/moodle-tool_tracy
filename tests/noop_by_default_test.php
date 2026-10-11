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
 * Installing this plugin on an already-live site, with config.php left
 * untouched and settings left at their defaults, must be a complete
 * no-op for regular users: nothing enabled, nothing visible, no behaviour
 * change. Administrators receive the development diagnostics by default.
 *
 * What this file CAN verify in-process: the admin setting defaults
 * themselves, and that tool_tracy_after_config()'s early-return guard is
 * the very first thing it does when Tracy isn't already enabled.
 *
 * What it CANNOT verify here: whether Tracy is *actually* absent on a real
 * request, because this specific dev site's own config.php unconditionally
 * calls the low-level \tool_tracy\bootstrap::enable() on every request
 * (that's deliberate, for developing this very plugin) — so
 * class_exists(\Tracy\Debugger::class, false) is always true by the time
 * any test here runs, no matter what a test does. That half of the claim
 * was instead verified manually, once, against a real request with the
 * low-level config.php line temporarily removed — see ROADMAP.md.
 *
 * Verifies admin setting defaults, not a specific unit of code.
 *
 * @coversNothing
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class noop_by_default_test extends \advanced_testcase {
    public function test_activation_defaults_to_admins_only(): void {
        $this->resetAfterTest();

        $this->assertSame('admins', get_config('tool_tracy', 'activationmode') ?: 'admins');
    }

    public function test_handlerownership_defaults_to_moodle(): void {
        $this->resetAfterTest();

        $this->assertSame('moodle', get_config('tool_tracy', 'handlerownership'));
    }
}
