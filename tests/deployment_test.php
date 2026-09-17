<?php

namespace tool_tracy;

class deployment_test extends \advanced_testcase {
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
