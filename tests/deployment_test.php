<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_tracy;

/**
 * Tests for the deployment-mismatch guard: normalize_root(), the
 * deployment record, and the activation Check API report.
 *
 * @covers ::tool_tracy_normalize_root
 * @covers ::tool_tracy_deployment_matches
 * @covers ::tool_tracy_disable_if_deployment_mismatch
 * @covers ::tool_tracy_activationmode_updated
 * @covers \tool_tracy\check\activation
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class deployment_test extends \advanced_testcase {
    public function test_normalize_root_ignores_trailing_slash_and_host_case(): void {
        $this->assertSame(
            'https://example.test/moodle',
            tool_tracy_normalize_root('HTTPS://EXAMPLE.TEST/moodle/')
        );
    }

    public function test_deployment_matches_current_wwwroot(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->delete_records('tool_tracy_deploy');

        $DB->insert_record('tool_tracy_deploy', (object) [
            'wwwroot' => 'https://dev.example.test',
            'timecreated' => time(),
        ]);

        $this->assertTrue(tool_tracy_deployment_matches('https://dev.example.test/'));
        $this->assertFalse(tool_tracy_deployment_matches('https://www.example.test/'));
    }

    public function test_mismatched_deployment_is_persistently_disabled(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->delete_records('tool_tracy_deploy');
        set_config('activationmode', 'admins', 'tool_tracy');

        $DB->insert_record('tool_tracy_deploy', (object) [
            'wwwroot' => 'https://dev.example.test',
            'timecreated' => time(),
        ]);

        $this->assertTrue(tool_tracy_disable_if_deployment_mismatch('https://www.example.test/'));

        $this->assertSame('off', get_config('tool_tracy', 'activationmode'));
    }

    public function test_security_check_reports_activation_modes(): void {
        $this->resetAfterTest();
        $check = new \tool_tracy\check\activation();

        set_config('activationmode', 'off', 'tool_tracy');
        $this->assertSame(\core\check\result::OK, $check->get_result()->get_status());

        set_config('activationmode', 'admins', 'tool_tracy');
        $this->assertSame(\core\check\result::INFO, $check->get_result()->get_status());

        set_config('activationmode', 'all', 'tool_tracy');
        $this->assertSame(\core\check\result::WARNING, $check->get_result()->get_status());
    }

    public function test_security_check_reports_deployment_mismatch(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->delete_records('tool_tracy_deploy');
        $DB->insert_record('tool_tracy_deploy', (object) [
            'wwwroot' => 'https://dev.example.test',
            'timecreated' => time(),
        ]);
        set_config('activationmode', 'admins', 'tool_tracy');

        $result = (new \tool_tracy\check\activation())->get_result();

        $this->assertSame(\core\check\result::ERROR, $result->get_status());
    }

    public function test_activationmode_update_registers_current_deployment(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $DB->delete_records('tool_tracy_deploy');
        $DB->insert_record('tool_tracy_deploy', (object) [
            'wwwroot' => 'https://dev.example.test',
            'timecreated' => time(),
        ]);
        set_config('activationmode', 'admins', 'tool_tracy');

        tool_tracy_activationmode_updated('tool_tracy/activationmode');

        $deployment = $DB->get_record('tool_tracy_deploy', [], '*', IGNORE_MULTIPLE);
        $this->assertSame(tool_tracy_normalize_root($CFG->wwwroot), $deployment->wwwroot);
    }
}
